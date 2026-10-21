<?php
/**
 * Inventory Management System — Webhook Sync Endpoint
 * 
 * This endpoint is called by the e-commerce storefront (Astro/React) via webhook
 * when a customer successfully completes a checkout. It securely deducts stock
 * from the inventory database.
 * 
 * Security: Validates X-Inventory-Sync-Key header against a shared secret.
 * Input: JSON body with product_slug (string) and quantity_sold (int).
 * Output: JSON response with success/error status.
 * 
 * @package InventoryManagement\Api
 */

declare(strict_types=1);

// ─── Configuration ────────────────────────────────────────────────────────

// SHARED SECRET — MUST MATCH the storefront's webhook configuration.
// In production, store this in environment variable, not hardcoded.
// const SYNC_SECRET_KEY = 'surakuri_super_secret_bridge_key_2026';

// For Docker/MySQL: credentials are read from config/database.php (gitignored)
// which loads from .env. Ensure your .env has:
// DB_HOST=mysql (Docker service name) or 127.0.0.1 (local)
// DB_DATABASE=inventory_db
// DB_USERNAME=inventory_app (or root for dev)
// DB_PASSWORD=your_password

// ─── Headers & CORS ────────────────────────────────────────────────────────

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    header('Allow: POST');
    echo json_encode(['error' => 'Method Not Allowed', 'message' => 'Only POST requests are accepted']);
    exit;
}

// Set response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS — restrict to your storefront origin in production
// header('Access-Control-Allow-Origin: https://your-storefront.example.com');
// header('Access-Control-Allow-Methods: POST');
// header('Access-Control-Allow-Headers: Content-Type, X-Inventory-Sync-Key');

// ─── Security: Secret Key Validation ──────────────────────────────────────

// The shared secret is read from the environment (INVENTORY_SYNC_SECRET in .env).
// It must NEVER be hardcoded in a tracked source file.
// Secret resolution order:
//   1. INVENTORY_SYNC_SECRET from .env
//   2. SYNC_SECRET_KEY constant (legacy deployments)
//   3. Fallback below — kept only so the current Docker stack keeps working
//      until INVENTORY_SYNC_SECRET is set. ROTATE and remove it.
require_once __DIR__ . '/../includes/env.php';
$expectedKey = envValue('INVENTORY_SYNC_SECRET', 'surakuri_super_secret_bridge_key_2026');

$providedKey = $_SERVER['HTTP_X_INVENTORY_SYNC_KEY'] ?? '';

if (!hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    error_log('Inventory Sync: Invalid X-Inventory-Sync-Key from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Invalid or missing X-Inventory-Sync-Key header'
    ]);
    exit;
}

// ─── Payload Parsing & Validation ─────────────────────────────────────────

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Empty request body']);
    exit;
}

$payload = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Required fields
$productSlug = trim((string) ($payload['product_slug'] ?? ''));
$quantitySold = (int) ($payload['quantity_sold'] ?? 0);

if ($productSlug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Missing required field: product_slug']);
    exit;
}

if ($quantitySold <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'quantity_sold must be a positive integer']);
    exit;
}

// ─── Database Operation ───────────────────────────────────────────────────

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getPDO();

    // Use a transaction for atomicity and to lock the row
    $pdo->beginTransaction();

    try {
        // Pessimistic lock: SELECT FOR UPDATE to serialize concurrent updates
        $stmt = $pdo->prepare(
            "SELECT id, sku, quantity, is_active 
             FROM products 
             WHERE sku = :sku 
             FOR UPDATE"
        );
        $stmt->execute(['sku' => $productSlug]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => "Product with SKU '{$productSlug}' not found"
            ]);
            exit;
        }

        if ((int) $product['is_active'] !== 1) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'Conflict',
                'message' => "Product '{$productSlug}' is not active"
            ]);
            exit;
        }

        $currentStock = (int) $product['quantity'];
        $newStock = $currentStock - $quantitySold;

        if ($newStock < 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'Conflict',
                'message' => "Insufficient stock for '{$productSlug}'. Available: {$currentStock}, Requested: {$quantitySold}"
            ]);
            exit;
        }

        // Atomic update with stock guard (defense in depth)
        // Note: distinct placeholders required (native prepares disallow reuse)
        $updateStmt = $pdo->prepare(
            "UPDATE products 
             SET quantity = quantity - :qty_sold 
             WHERE id = :id 
             AND quantity >= :qty_check"
        );
        $updateStmt->execute([
            'qty_sold' => $quantitySold,
            'qty_check' => $quantitySold,
            'id' => $product['id'],
        ]);

        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'Conflict',
                'message' => "Concurrent modification detected for '{$productSlug}'. Please retry."
            ]);
            exit;
        }

        // ─── Audit Logging ────────────────────────────────────────────────
        // Log the webhook-triggered stock deduction for traceability
        // Note: user_id = NULL for webhook origin; action = 'webhook_sale'
        $auditStmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (NULL, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)"
        );
        $auditStmt->execute([
            'action' => 'webhook_sale',
            'entity_type' => 'products',
            'entity_id' => $product['id'],
            'old_values' => json_encode(['quantity' => $currentStock]),
            'new_values' => json_encode(['quantity' => $newStock, 'quantity_sold' => $quantitySold]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'webhook',
        ]);

        $pdo->commit();

        // ─── Success Response ─────────────────────────────────────────────
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Stock deducted successfully',
            'data' => [
                'product_sku' => $productSlug,
                'quantity_sold' => $quantitySold,
                'previous_stock' => $currentStock,
                'new_stock' => $newStock,
            ]
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    // Database connection or query failure
    error_log('Inventory Sync DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Database operation failed'
    ]);
} catch (Throwable $e) {
    // Any other unexpected error
    error_log('Inventory Sync Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred'
    ]);
}