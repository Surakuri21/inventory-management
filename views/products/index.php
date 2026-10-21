<?php
declare(strict_types=1);

// Product listing view (index)
// Routed via index.php ?route=products (action defaults to index)
// Requires authentication (standard or admin); product data fetched below.

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

// Fetch all active products with category and supplier names
$products = fetchAll("
    SELECT p.id, p.sku, p.name, p.description, p.category_id, p.supplier_id,
           p.unit_price, p.cost_price, p.quantity, p.low_stock_threshold,
           p.unit_of_measure, p.is_active, p.created_at,
           c.name AS category_name, s.name AS supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id AND c.is_active = 1
    LEFT JOIN suppliers s ON p.supplier_id = s.id AND s.is_active = 1
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 50
");

$categories = getAllCategories();
$suppliers = getAllSuppliers();

$flashMessages = getFlashMessages();
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<!-- Main content (header.php handles sidebar + layout) -->
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Products</h1>
            <?php if (isAdmin()): ?>
                <a href="/products/create"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Add Product
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
                    <?php echo $msg['type'] === 'error'
                        ? 'bg-red-50 text-red-700 border border-red-200'
                        : 'bg-green-50 text-green-700 border border-green-200'; ?>">
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

        <!-- Product table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold tracking-wider">
                        <tr>
                            <th class="px-5 py-3">SKU</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Quantity</th>
                            <th class="px-5 py-3">Unit Price</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-gray-400">
                                    <i class="fas fa-box-open text-3xl mb-2"></i>
                                    <p>No products found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <?php
                                    $lowStock = isLowStock((int)$p['quantity'], (int)$p['low_stock_threshold']);
                                    $outOfStock = isOutOfStock((int)$p['quantity']);
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-3 font-mono text-xs text-gray-600"><?php echo e($p['sku']); ?></td>
                                    <td class="px-5 py-3 font-medium text-gray-900"><?php echo e($p['name']); ?></td>
                                    <td class="px-5 py-3 text-gray-600"><?php echo e($p['category_name'] ?? 'â€”'); ?></td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="font-semibold <?php
                                                echo $outOfStock ? 'text-red-600' : ($lowStock ? 'text-amber-600' : 'text-green-600');
                                            ?>">
                                                <?php echo number_format((int)$p['quantity']); ?>
                                            </span>
                                            <?php if ($lowStock && !$outOfStock): ?>
                                                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 rounded">Low</span>
                                            <?php elseif ($outOfStock): ?>
                                                <span class="text-[10px] bg-red-100 text-red-700 px-1.5 rounded">Out</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">$<?php echo number_format((float)$p['unit_price'], 2); ?></td>
                                    <td class="px-5 py-3">
                                        <?php if ($p['is_active']): ?>
                                            <span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">Active</span>
                                        <?php else: ?>
                                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if (isAdmin()): ?>
                                            <a href="/products/edit?id=<?php echo (int)$p['id']; ?>"
                                               class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3">Edit</a>
                                            <form method="POST" action="/actions/products/delete.php" class="inline" onsubmit="return confirm('Delete this product?');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">â€”</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>

