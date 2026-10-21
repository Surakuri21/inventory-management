<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';

requireAdmin();

$productId = validateId($_GET['id'] ?? null);
if ($productId === null) {
    setFlashMessage('error', 'Invalid product ID.');
    redirect('products');
}

$product = fetchOne("SELECT * FROM products WHERE id = :id", ['id' => $productId]);
if (!$product) {
    setFlashMessage('error', 'Product not found.');
    redirect('products');
}

$categories = getAllCategories();
$suppliers = getAllSuppliers();
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="/products" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
            <h1 class="text-2xl font-bold text-gray-900">Edit Product</h1>
        </div>
        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?php echo $msg['type']==='error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'; ?>">
                    <?php echo e($msg['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($formErrors)): ?>
            <div class="mb-4 px-4 py-3 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm font-medium">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="/actions/products/update.php" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5" novalidate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="sku" class="block text-sm font-semibold text-gray-700 mb-1">SKU <span class="text-red-500">*</span></label>
                    <input type="text" id="sku" name="sku" value="<?php echo e($formData['sku'] ?? $product['sku']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. SKU-001" required>
                </div>
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo e($formData['name'] ?? $product['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Product name" required>
                </div>
            </div>
            <div>
                <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"><?php echo e($formData['description'] ?? $product['description'] ?? ''); ?></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="category_id" class="block text-sm font-semibold text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                    <select id="category_id" name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">— Select —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo (isset($formData['category_id']) ? (int)$formData['category_id'] : (int)$product['category_id']) === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="supplier_id" class="block text-sm font-semibold text-gray-700 mb-1">Supplier</label>
                    <select id="supplier_id" name="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">— None —</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo (int)$sup['id']; ?>" <?php echo (isset($formData['supplier_id']) ? (int)$formData['supplier_id'] : ($product['supplier_id'] ? (int)$product['supplier_id'] : '')) === (int)$sup['id'] ? 'selected' : ''; ?>><?php echo e($sup['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                <div>
                    <label for="unit_price" class="block text-sm font-semibold text-gray-700 mb-1">Unit Price</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" value="<?php echo e((string)($formData['unit_price'] ?? $product['unit_price'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                </div>
                <div>
                    <label for="cost_price" class="block text-sm font-semibold text-gray-700 mb-1">Cost Price</label>
                    <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo e((string)($formData['cost_price'] ?? $product['cost_price'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                </div>
                <div>
                    <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-1">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="0" value="<?php echo e((string)($formData['quantity'] ?? $product['quantity'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Current stock">
                </div>
                <div>
                    <label for="low_stock_threshold" class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Threshold</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0" value="<?php echo e((string)($formData['low_stock_threshold'] ?? $product['low_stock_threshold'])); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Alert threshold">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="unit_of_measure" class="block text-sm font-semibold text-gray-700 mb-1">Unit of Measure</label>
                    <input type="text" id="unit_of_measure" name="unit_of_measure" value="<?php echo e($formData['unit_of_measure'] ?? $product['unit_of_measure']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. pcs">
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" value="1" <?php echo (isset($formData['is_active']) ? ($formData['is_active'] ? 'checked' : '') : ($product['is_active'] ? 'checked' : '')); ?> class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700 font-medium">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">Update Product</button>
                <a href="/products" class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>