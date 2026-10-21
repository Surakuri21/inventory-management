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
$existing = fetchOne("SELECT * FROM products WHERE id = :id", ['id' => $productId]);
if (!$existing) { setFlashMessage('error', 'Product not found.'); redirect('products'); }
$skuRaw = trim($_POST['sku'] ?? '');
$nameRaw = trim($_POST['name'] ?? '');
$descriptionRaw = trim($_POST['description'] ?? '');
$sku = ($skuRaw !== '' && mb_strlen($skuRaw) <= 50) ? $skuRaw : '';
$name = ($nameRaw !== '' && mb_strlen($nameRaw) <= 200) ? $nameRaw : '';
$description = (mb_strlen($descriptionRaw) <= 2000) ? $descriptionRaw : null;
$categoryId = validateId($_POST['category_id'] ?? null);
$supplierId = validateId($_POST['supplier_id'] ?? null) ?: null;
$unitPrice = (float) ($_POST['unit_price'] ?? 0);
$costPrice = (float) ($_POST['cost_price'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 0);
$lowStockThreshold = (int) ($_POST['low_stock_threshold'] ?? 10);
$unitOfMeasureRaw = trim($_POST['unit_of_measure'] ?? '');
$unitOfMeasure = ($unitOfMeasureRaw !== '' && mb_strlen($unitOfMeasureRaw) <= 20) ? $unitOfMeasureRaw : 'pcs';
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];
if ($sku === '') { $errors[] = 'SKU is required.'; }
elseif ($sku !== $existing['sku']) {
    $dup = fetchOne("SELECT id FROM products WHERE sku = :sku AND id != :id", ['sku' => $sku, 'id' => $productId]);
    if ($dup) { $errors[] = 'SKU must be unique.'; }
}
if ($name === '') { $errors[] = 'Product name is required.'; }
if ($categoryId === null) { $errors[] = 'Category is required.'; } else {
    $cat = fetchOne("SELECT id FROM categories WHERE id = :id AND is_active = 1", ['id' => $categoryId]);
    if (!$cat) { $errors[] = 'Selected category is invalid.'; }
}
if ($supplierId !== null) {
    $sup = fetchOne("SELECT id FROM suppliers WHERE id = :id AND is_active = 1", ['id' => $supplierId]);
    if (!$sup) { $errors[] = 'Selected supplier is invalid.'; }
}
if ($unitPrice < 0 || $costPrice < 0) { $errors[] = 'Prices cannot be negative.'; }
if ($quantity < 0) { $errors[] = 'Quantity cannot be negative.'; }
if ($lowStockThreshold < 0) { $errors[] = 'Low stock threshold cannot be negative.'; }
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('products/edit?id=' . $productId); }
$sql = "UPDATE products SET sku = :sku, name = :name, description = :description, category_id = :category_id, supplier_id = :supplier_id, unit_price = :unit_price, cost_price = :cost_price, quantity = :quantity, low_stock_threshold = :low_stock_threshold, unit_of_measure = :unit_of_measure, is_active = :is_active WHERE id = :id";
execute($sql, [
    'sku' => $sku, 'name' => $name, 'description' => $description ?: null,
    'category_id' => $categoryId, 'supplier_id' => $supplierId,
    'unit_price' => $unitPrice, 'cost_price' => $costPrice,
    'quantity' => $quantity, 'low_stock_threshold' => $lowStockThreshold,
    'unit_of_measure' => $unitOfMeasure, 'is_active' => $isActive, 'id' => $productId,
]);
$oldVals = [
    'sku' => $existing['sku'], 'name' => $existing['name'], 'quantity' => (int)$existing['quantity'],
    'unit_price' => (float)$existing['unit_price'], 'category_id' => (int)$existing['category_id'],
];
logAuditAction('update', 'products', $productId, $oldVals, [
    'sku' => $sku, 'name' => $name, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'category_id' => $categoryId,
]);

// ─── Supabase Sync (Direction 2: PHP → Astro/Supabase) ────────────────────
$syncProduct = [
    'id' => $productId,
    'sku' => $sku,
    'name' => $name,
    'description' => $description ?: null,
    'category_id' => $categoryId,
    'supplier_id' => $supplierId,
    'unit_price' => $unitPrice,
    // NOTE: cost_price intentionally omitted — inventory cost must not leave the IMS.
    'quantity' => $quantity,
    'low_stock_threshold' => $lowStockThreshold,
    'unit_of_measure' => $unitOfMeasure,
    'is_active' => $isActive,
];
$category = $categoryId ? fetchOne("SELECT name FROM categories WHERE id = :id", ['id' => $categoryId]) : [];
$supplier = $supplierId ? fetchOne("SELECT name FROM suppliers WHERE id = :id", ['id' => $supplierId]) : [];
$syncResult = syncProductToSupabase($syncProduct, $category, $supplier);
$syncWarning = '';
if (!$syncResult['success']) {
    // Surface mirror failures — see create.php for the rationale.
    error_log('Supabase sync warning (update): ' . $syncResult['message']);
    $syncWarning = ' Warning: the storefront mirror could not be updated, so changes may not appear on the storefront yet.';
}

setFlashMessage('success', 'Product updated successfully (SKU: ' . e($sku) . ').' . $syncWarning);
redirect('products');
