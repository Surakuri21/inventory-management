<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('transactions'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF token.'); redirect('transactions'); }

// Idempotency key validation
$idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));
if ($idempotencyKey === '' || strlen($idempotencyKey) !== 64 || !ctype_xdigit($idempotencyKey)) {
    setFlashMessage('error', 'Invalid idempotency key.');
    redirect('transactions');
}

// Check if this idempotency key was already used
$existing = fetchOne(
    "SELECT id FROM transactions WHERE idempotency_key = :key",
    ['key' => $idempotencyKey]
);
if ($existing) {
    setFlashMessage('warning', 'This transaction was already processed (duplicate submission).');
    redirect('transactions');
}

$productId = validateId($_POST['product_id'] ?? null);
if ($productId === null) { setFlashMessage('error', 'Invalid product.'); redirect('transactions'); }
$type = $_POST['type'] ?? '';
$allowed = ['sale', 'restock', 'adjustment', 'return'];
if (!in_array($type, $allowed, true)) { setFlashMessage('error', 'Invalid transaction type.'); redirect('transactions'); }
$quantity = (int) ($_POST['quantity'] ?? 0);
if ($quantity <= 0) { setFlashMessage('error', 'Quantity must be positive.'); redirect('transactions'); }
$unitPrice = (float) ($_POST['unit_price'] ?? 0);
if ($unitPrice < 0) { setFlashMessage('error', 'Unit price cannot be negative.'); redirect('transactions'); }
$ref = sanitizeString($_POST['reference_number'] ?? '', 50) ?: null;
$notesRaw = trim($_POST['notes'] ?? '');
$notes = mb_strlen($notesRaw) <= 1000 ? $notesRaw : null;
// Signed delta based on type rules: sale = out (-), restock/return = in (+)
if ($type === 'adjustment') {
    $delta = (int) ($_POST['delta_quantity'] ?? 0);
} else {
    $delta = ($type === 'sale') ? -$quantity : $quantity;
}

$pdo = getPDO();
beginTransaction();
try {
    // Pessimistic lock: acquire exclusive row lock BEFORE computing stock so
    // concurrent transactions serialize. Uses the SAME pdo connection for lock + write.
    $product = fetchOneLocked(
        $pdo,
        "SELECT id, quantity, unit_price FROM products WHERE id = :id FOR UPDATE",
        ['id' => $productId]
    );
    if (!$product) {
        $pdo->rollBack();
        setFlashMessage('error', 'Product not found.');
        redirect('transactions');
    }

    // Atomic update: DB checks available stock at write time (prevents negative stock
    // and lost-update races under concurrent requests).
    // Note: distinct placeholder names (:delta_set / :delta_where) — native prepares
    // (ATTR_EMULATE_PREPARES=false) reject repeated named parameters (HY093).
    $guard = ($delta < 0) ? -$delta : 0;
    $stmt = $pdo->prepare(
        "UPDATE products SET quantity = quantity + :delta_set WHERE id = :id AND quantity + :delta_where >= :guard"
    );
    $stmt->execute([
        'delta_set' => $delta,
        'delta_where' => $delta,
        'id' => $productId,
        'guard' => $guard,
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        setFlashMessage('error', 'Insufficient stock for this transaction.');
        redirect('transactions');
    }

    $sql = "INSERT INTO transactions (product_id, user_id, type, quantity, unit_price, reference_number, notes, idempotency_key) VALUES (:pid, :uid, :type, :qty, :price, :ref, :notes, :idem)";
    insert($sql, [
        'pid' => $productId,
        'uid' => currentUserId(),
        'type' => $type,
        'qty' => $delta,
        'price' => $unitPrice,
        'ref' => $ref,
        'notes' => $notes,
        'idem' => $idempotencyKey,
    ]);
    commitTransaction();
    logAuditAction(
        'transaction_' . $type,
        'transactions',
        $productId,
        ['quantity_before' => (int) $product['quantity']],
        ['type' => $type, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'delta' => $delta]
    );
    setFlashMessage('success', ucfirst($type) . ' transaction recorded.');
    redirect('transactions');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlashMessage('error', 'Transaction failed.');
    error_log('Transaction error: ' . $e->getMessage());
    redirect('transactions');
}
