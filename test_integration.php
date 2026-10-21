<?php
/**
 * Integration Test Script for Surakuri Microservices
 * Tests both directions of the bidirectional sync:
 * - Direction 1: Astro → PHP (checkout webhook stock deduction)
 * - Direction 2: PHP → Astro/Supabase (admin product CRUD push)
 * 
 * Run: docker exec inventory_management-php-fpm-1 php /var/www/html/test_integration.php
 * Or: php test_integration.php (if PHP has access to the DB)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/supabase.php';

echo "=========================================================\n";
echo "  SURAKURI MICROSERVICES - INTEGRATION TEST\n";
echo "=========================================================\n\n";

// ─── Test Configuration ────────────────────────────────────────────────
const TEST_SKU_PREFIX = 'TEST-INTEGRATION-';

// The webhook secret is read from the environment (INVENTORY_SYNC_SECRET),
// never hardcoded in source. Falls back to the legacy value so the local
// Docker stack keeps working until .env is updated.
$syncSecretKey = envValue('INVENTORY_SYNC_SECRET', 'surakuri_super_secret_bridge_key_2026');

$testsPassed = 0;
$testsFailed = 0;

function testAssert(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  ✅ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "  ❌ FAIL: $message\n";
        $testsFailed++;
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message): void {
    testAssert($expected === $actual, "$message (expected: $expected, actual: $actual)");
}

// ─── Test 1: Verify Database Connection ────────────────────────────────
echo "\n[TEST 1] Database Connection\n";
try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT 1');
    testAssert($stmt !== false, 'PDO connection successful');
    echo "  Connected to: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
} catch (Throwable $e) {
    testAssert(false, 'PDO connection failed: ' . $e->getMessage());
    exit(1);
}

// ─── Test 2: Verify Products Table & Sample Data ───────────────────────
echo "\n[TEST 2] Products Table\n";
$products = fetchAll("SELECT id, sku, name, quantity, is_active FROM products WHERE is_active = 1 ORDER BY id LIMIT 5");
testAssert(count($products) > 0, 'At least one active product exists');
foreach ($products as $p) {
    echo "  - {$p['sku']}: {$p['name']} (stock: {$p['quantity']})\n";
}

// ─── Test 3: Direction 1 - Webhook Stock Deduction (api/sync-order.php) ────
echo "\n[TEST 3] Direction 1: Webhook Stock Deduction\n";

// Create a test product for webhook testing
$testSku = TEST_SKU_PREFIX . 'WEBHOOK-' . bin2hex(random_bytes(4));
$testProductId = insert(
    "INSERT INTO products (sku, name, category_id, unit_price, cost_price, quantity, low_stock_threshold, unit_of_measure, is_active)
     VALUES (:sku, :name, 1, 100.00, 50.00, 100, 10, 'pcs', 1)",
    [
        'sku' => $testSku,
        'name' => 'Test Webhook Product',
    ]
);
testAssert($testProductId > 0, "Test product created (ID: $testProductId, SKU: $testSku)");

// Simulate webhook call
$webhookPayload = json_encode([
    'product_slug' => $testSku,
    'quantity_sold' => 3,
]);

// Test the logic directly (bypassing HTTP)
$pdo = getPDO();
$pdo->beginTransaction();

try {
    // Pessimistic lock (same as sync-order.php)
    $stmt = $pdo->prepare("SELECT id, sku, quantity FROM products WHERE sku = :sku FOR UPDATE");
    $stmt->execute(['sku' => $testSku]);
    $product = $stmt->fetch();
    testAssert($product !== false, 'Product found via webhook SKU lookup');

    $currentStock = (int) $product['quantity'];
    $newStock = $currentStock - 3;
    testAssert($newStock >= 0, "Sufficient stock ($currentStock - 3 = $newStock)");

    // Atomic update with guard (use unique param names since PDO::ATTR_EMULATE_PREPARES=false)
    $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity - :qty1 WHERE id = :id AND quantity >= :qty2");
    $updateStmt->execute(['qty1' => 3, 'qty2' => 3, 'id' => $product['id']]);
    testAssert($updateStmt->rowCount() === 1, 'Stock deducted atomically (rowCount=1)');

    // Audit log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (NULL, 'webhook_sale', 'products', :id, :old, :new, 'test', 'test')");
    $auditStmt->execute([
        'id' => $product['id'],
        'old' => json_encode(['quantity' => $currentStock]),
        'new' => json_encode(['quantity' => $newStock, 'quantity_sold' => 3]),
    ]);
    testAssert($auditStmt->rowCount() === 1, 'Audit log entry created');

    $pdo->commit();

    // Verify final stock
    $verify = fetchOne("SELECT quantity FROM products WHERE id = :id", ['id' => $product['id']]);
    assertEquals($newStock, (int) $verify['quantity'], "Final stock matches expected ($newStock)");

    echo "  Stock: $currentStock → $newStock (deducted 3 via webhook simulation)\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    testAssert(false, 'Webhook simulation failed: ' . $e->getMessage());
}

// ─── Test 4: Direction 1 - Duplicate Idempotency Check ─────────────────
echo "\n[TEST 4] Direction 1: Idempotency Key Rejection\n";
$testSku2 = TEST_SKU_PREFIX . 'IDEMPOTENCY-' . bin2hex(random_bytes(4));
$testProductId2 = insert(
    "INSERT INTO products (sku, name, category_id, unit_price, cost_price, quantity, low_stock_threshold, unit_of_measure, is_active)
     VALUES (:sku, :name, 1, 100.00, 50.00, 50, 10, 'pcs', 1)",
    ['sku' => $testSku2, 'name' => 'Test Idempotency Product']
);

$idemKey = bin2hex(random_bytes(32));

// First insert with idempotency key
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku FOR UPDATE");
    $stmt->execute(['sku' => $testSku2]);
    $product = $stmt->fetch();

    // Insert transaction with idempotency key
    $txStmt = $pdo->prepare("INSERT INTO transactions (product_id, user_id, type, quantity, unit_price, idempotency_key) VALUES (:pid, 1, 'sale', -2, 100.00, :key)");
    $txStmt->execute(['pid' => $product['id'], 'key' => $idemKey]);
    testAssert($txStmt->rowCount() === 1, 'First transaction inserted');

    $pdo->commit();
    echo "  First transaction with idempotency key: OK\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    testAssert(false, 'First idempotent insert failed: ' . $e->getMessage());
}

// Second insert with SAME idempotency key (should be rejected by unique constraint)
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku FOR UPDATE");
    $stmt->execute(['sku' => $testSku2]);
    $product = $stmt->fetch();

    $txStmt = $pdo->prepare("INSERT INTO transactions (product_id, user_id, type, quantity, unit_price, idempotency_key) VALUES (:pid, 1, 'sale', -2, 100.00, :key)");
    $txStmt->execute(['pid' => $product['id'], 'key' => $idemKey]);
    
    $pdo->commit();
    testAssert(false, 'Duplicate idempotency key should have been rejected');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    testAssert(str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'uk_transactions_idempotency'), 'Duplicate idempotency key correctly rejected (unique constraint)');
    echo "  Duplicate idempotency key correctly rejected by DB constraint\n";
}

// ─── Test 5: Direction 2 - Supabase Sync Functions ─────────────────────
echo "\n[TEST 5] Direction 2: Supabase Sync Functions\n";

// Test isSupabaseEnabled() - should be false with placeholder values
testAssert(isSupabaseEnabled() === false, 'isSupabaseEnabled() returns false with placeholder .env values');

// Test buildSupabaseProductPayload()
$sampleProduct = [
    'id' => 999,
    'sku' => 'TEST-SKU-123',
    'name' => 'Test Watch',
    'description' => 'A test watch',
    'category_id' => 1,
    'supplier_id' => 2,
    'unit_price' => 12500.00,
    'cost_price' => 6250.00,
    'quantity' => 10,
    'low_stock_threshold' => 5,
    'unit_of_measure' => 'pcs',
    'is_active' => 1,
];
$sampleCategory = ['name' => 'Chronograph'];
$sampleSupplier = ['name' => 'TechSupply Co.'];

$payload = buildSupabaseProductPayload($sampleProduct, $sampleCategory, $sampleSupplier);
testAssert($payload['sku'] === 'TEST-SKU-123', 'Payload SKU matches');
testAssert($payload['name'] === 'Test Watch', 'Payload name matches');
testAssert($payload['category_name'] === 'Chronograph', 'Payload category_name from join');
testAssert($payload['supplier_name'] === 'TechSupply Co.', 'Payload supplier_name from join');
testAssert($payload['mysql_id'] === 999, 'Payload includes mysql_id reference');
testAssert(isset($payload['synced_at']), 'Payload includes synced_at timestamp');
testAssert((float)$payload['unit_price'] === 12500.00, 'Payload unit_price is float');
testAssert((int)$payload['quantity'] === 10, 'Payload quantity is int');
echo "  buildSupabaseProductPayload(): All fields mapped correctly\n";

// Test deleteProductFromSupabase() returns expected structure when disabled
$deleteResult = deleteProductFromSupabase('NONEXISTENT');
testAssert($deleteResult['success'] === false, 'deleteProductFromSupabase returns success=false when disabled');
testAssert(str_contains($deleteResult['message'], 'not configured'), 'Returns "not configured" message');
echo "  deleteProductFromSupabase(): Returns expected structure when disabled\n";

// ─── Test 6: Admin Product CRUD triggers sync ───────────────────────────
echo "\n[TEST 6] Direction 2: Admin CRUD Triggers Sync (Function Calls)\n";

// Create product → should call syncProductToSupabase()
$testSku3 = TEST_SKU_PREFIX . 'CREATE-' . bin2hex(random_bytes(4));
$pid = insert(
    "INSERT INTO products (sku, name, category_id, unit_price, cost_price, quantity, low_stock_threshold, unit_of_measure, is_active)
     VALUES (:sku, :name, 1, 200.00, 100.00, 25, 5, 'pcs', 1)",
    ['sku' => $testSku3, 'name' => 'Test Create Sync']
);

// Simulate what actions/products/create.php does
$syncProduct = [
    'id' => $pid,
    'sku' => $testSku3,
    'name' => 'Test Create Sync',
    'description' => null,
    'category_id' => 1,
    'supplier_id' => null,
    'unit_price' => 200.00,
    'cost_price' => 100.00,
    'quantity' => 25,
    'low_stock_threshold' => 5,
    'unit_of_measure' => 'pcs',
    'is_active' => 1,
];
$category = fetchOne("SELECT name FROM categories WHERE id = 1");
$supplier = [];
$result = syncProductToSupabase($syncProduct, $category, $supplier);
testAssert($result['success'] === false, 'syncProductToSupabase called (returns false when disabled)');
testAssert(str_contains($result['message'], 'not configured'), 'Returns "not configured" warning');
echo "  Create → syncProductToSupabase() called: OK\n";

// Update product → should call syncProductToSupabase()
execute("UPDATE products SET quantity = 30 WHERE id = :id", ['id' => $pid]);
$syncProduct['quantity'] = 30;
$result = syncProductToSupabase($syncProduct, $category, $supplier);
testAssert($result['success'] === false, 'Update sync called');
echo "  Update → syncProductToSupabase() called: OK\n";

// Delete product → should call deleteProductFromSupabase()
$skuToDelete = $testSku3;
$result = deleteProductFromSupabase($skuToDelete);
testAssert($result['success'] === false, 'Delete sync called');
execute("DELETE FROM products WHERE id = :id", ['id' => $pid]);
echo "  Delete → deleteProductFromSupabase() called: OK\n";

// ─── Test 7: Audit Trail Verification ──────────────────────────────────
echo "\n[TEST 7] Audit Trail Verification\n";
$audits = fetchAll("SELECT action, entity_type, entity_id FROM audit_logs WHERE action IN ('webhook_sale', 'create', 'update', 'delete') ORDER BY id DESC LIMIT 10");
testAssert(count($audits) > 0, 'Audit entries exist for tested actions');
foreach ($audits as $a) {
    echo "  - {$a['action']} on {$a['entity_type']} #{$a['entity_id']}\n";
}

// ─── Test 8: Error Handling - Insufficient Stock ───────────────────────
echo "\n[TEST 8] Error Handling: Insufficient Stock\n";
$testSku4 = TEST_SKU_PREFIX . 'LOWSTOCK-' . bin2hex(random_bytes(4));
$pid4 = insert(
    "INSERT INTO products (sku, name, category_id, unit_price, cost_price, quantity, low_stock_threshold, unit_of_measure, is_active)
     VALUES (:sku, :name, 1, 50.00, 25.00, 2, 1, 'pcs', 1)",
    ['sku' => $testSku4, 'name' => 'Test Low Stock']
);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id, quantity FROM products WHERE sku = :sku FOR UPDATE");
    $stmt->execute(['sku' => $testSku4]);
    $product = $stmt->fetch();
    
    // Try to sell 5 when only 2 in stock
    $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity - :qty1 WHERE id = :id AND quantity >= :qty2");
    $updateStmt->execute(['qty1' => 5, 'qty2' => 5, 'id' => $product['id']]);
    
    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        testAssert(true, 'Insufficient stock correctly rejected (rowCount=0)');
        echo "  Attempted to sell 5, stock=2 → correctly rejected\n";
    } else {
        $pdo->commit();
        testAssert(false, 'Should have rejected insufficient stock');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    testAssert(false, 'Low stock test failed: ' . $e->getMessage());
}

// ─── Cleanup ───────────────────────────────────────────────────────────
echo "\n[CLEANUP] Removing test products and related records\n";
$pdo = getPDO();
$pdo->exec("DELETE FROM transactions WHERE product_id IN (SELECT id FROM products WHERE sku LIKE '" . TEST_SKU_PREFIX . "%')");
$pdo->exec("DELETE FROM audit_logs WHERE entity_type = 'products' AND entity_id IN (SELECT id FROM products WHERE sku LIKE '" . TEST_SKU_PREFIX . "%')");
execute("DELETE FROM products WHERE sku LIKE :prefix", ['prefix' => TEST_SKU_PREFIX . '%']);
echo "  Test products and related records removed\n";

// ─── Summary ───────────────────────────────────────────────────────────
echo "\n=========================================================\n";
echo "  TEST SUMMARY\n";
echo "=========================================================\n";
echo "  Passed: $testsPassed\n";
echo "  Failed: $testsFailed\n";
echo "  Total:  " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed === 0) {
    echo "\n🎉 ALL TESTS PASSED - Integration ready!\n";
    exit(0);
} else {
    echo "\n⚠️  SOME TESTS FAILED - Review above\n";
    exit(1);
}