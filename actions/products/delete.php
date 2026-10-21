<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/supabase.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('products'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF token.'); redirect('products'); }
$productId = validateId($_POST['id'] ?? null);
if ($productId === null) { setFlashMessage('error', 'Invalid product ID.'); redirect('products'); }
$product = fetchOne("SELECT id, sku, name FROM products WHERE id = :id", ['id' => $productId]);
if (!$product) { setFlashMessage('error', 'Product not found.'); redirect('products'); }
execute("DELETE FROM products WHERE id = :id", ['id' => $productId]);
logAuditAction('delete', 'products', $productId, ['sku' => $product['sku'], 'name' => $product['name']], null);

// ─── Supabase Sync (Direction 2: PHP → Astro/Supabase) ────────────────────
$syncResult = deleteProductFromSupabase($product['sku']);
if (!$syncResult['success']) {
    error_log('Supabase sync warning (delete): ' . $syncResult['message']);
}

setFlashMessage('success', 'Product deleted successfully.');
redirect('products');
