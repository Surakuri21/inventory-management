<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/supabase.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid CSRF token.');
    redirect('products/create');
}

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

if (empty($sku)) {
    $errors[] = 'SKU is required.';
} elseif (fetchOne("SELECT id FROM products WHERE sku = :sku", ['sku' => $sku])) {
    $errors[] = 'SKU must be unique.';
}

if (empty($name)) {
    $errors[] = 'Product name is required.';
}

if ($categoryId === null) {
    $errors[] = 'Category is required.';
} else {
    $cat = fetchOne("SELECT id FROM categories WHERE id = :id AND is_active = 1", ['id' => $categoryId]);
    if (!$cat) {
        $errors[] = 'Selected category is invalid.';
    }
}

if ($supplierId !== null) {
    $sup = fetchOne("SELECT id FROM suppliers WHERE id = :id AND is_active = 1", ['id' => $supplierId]);
    if (!$sup) {
        $errors[] = 'Selected supplier is invalid.';
    }
}

if ($unitPrice < 0 || $costPrice < 0) {
    $errors[] = 'Prices cannot be negative.';
}

if ($quantity < 0) {
    $errors[] = 'Quantity cannot be negative.';
}

if ($lowStockThreshold < 0) {
    $errors[] = 'Low stock threshold cannot be negative.';
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    redirect('products/create');
}

$sql = "INSERT INTO products
    (sku, name, description, category_id, supplier_id, unit_price, cost_price, quantity, low_stock_threshold, unit_of_measure, is_active)
    VALUES
    (:sku, :name, :description, :category_id, :supplier_id, :unit_price, :cost_price, :quantity, :low_stock_threshold, :unit_of_measure, :is_active)";

$productId = insert($sql, [
    'sku' => $sku,
    'name' => $name,
    'description' => $description ?: null,
    'category_id' => $categoryId,
    'supplier_id' => $supplierId,
    'unit_price' => $unitPrice,
    'cost_price' => $costPrice,
    'quantity' => $quantity,
    'low_stock_threshold' => $lowStockThreshold,
    'unit_of_measure' => $unitOfMeasure,
    'is_active' => $isActive,
]);

logAuditAction('create', 'products', (int) $productId, null, [
    'sku' => $sku,
    'name' => $name,
    'category_id' => $categoryId,
    'supplier_id' => $supplierId,
]);

// ─── Supabase Sync (Direction 2: PHP → Astro/Supabase) ────────────────────
$syncProduct = [
    'id' => (int) $productId,
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
    // Log but don't fail the request — storefront sync is eventually consistent.
    // However, the admin MUST be told: a silent failure here means the
    // storefront never sees this product (silent data loss).
    error_log('Supabase sync warning (create): ' . $syncResult['message']);
    $syncWarning = ' Warning: the storefront mirror could not be updated, so this product may not appear on the storefront yet.';
}

setFlashMessage('success', 'Product created successfully (SKU: ' . e($sku) . ').' . $syncWarning);
redirect('products');
