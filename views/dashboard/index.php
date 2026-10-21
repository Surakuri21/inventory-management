<?php declare(strict_types=1); ?>
<?php /* Dumb view: consumes $stats, $lowStockProducts, $recentTransactions, $recentAuditLogs, $todayTransactions scoped by index.php */ ?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>

<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <h1 class="mb-6 text-2xl font-bold text-gray-900">Dashboard</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Total Products</p><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($stats['total_products']); ?></p></div><div class="p-2 bg-blue-100 rounded-full"><i class="fas fa-box-open text-blue-500"></i></div></div></div>
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Low Stock Items</p><p class="text-3xl font-bold <?php echo $stats['low_stock_count'] > 0 ? 'text-red-500' : 'text-green-500'; ?> mt-1"><?php echo number_format($stats['low_stock_count']); ?></p></div><div class="p-2 <?php echo $stats['low_stock_count'] > 0 ? 'bg-red-100 rounded-full' : 'bg-green-100 rounded-full'; ?>"><i class="fas <?php echo $stats['low_stock_count'] > 0 ? 'fa-exclamation-triangle text-red-500' : 'fa-check-circle text-green-500'; ?>"></i></div></div></div>
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Out of Stock</p><p class="text-3xl font-bold <?php echo $stats['out_of_stock_count'] > 0 ? 'text-red-500' : 'text-green-500'; ?> mt-1"><?php echo number_format($stats['out_of_stock_count']); ?></p></div><div class="p-2 <?php echo $stats['out_of_stock_count'] > 0 ? 'bg-red-100 rounded-full' : 'bg-green-100 rounded-full'; ?>"><i class="fas <?php echo $stats['out_of_stock_count'] > 0 ? 'fa-times-circle text-red-500' : 'fa-check-circle text-green-500'; ?>"></i></div></div></div>
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Categories</p><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($stats['total_categories']); ?></p></div><div class="p-2 bg-purple-100 rounded-full"><i class="fas fa-tags text-purple-500"></i></div></div></div>
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Suppliers</p><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($stats['total_suppliers']); ?></p></div><div class="p-2 bg-indigo-100 rounded-full"><i class="fas fa-truck text-indigo-500"></i></div></div></div>
            <div class="bg-white rounded-lg shadow-md p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-500">Today's Transactions</p><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format((int) $todayTransactions); ?></p></div><div class="p-2 bg-teal-100 rounded-full"><i class="fas fa-chart-bar text-teal-500"></i></div></div></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <?php if ($stats['low_stock_count'] > 0): ?>
                <div class="bg-white rounded-lg shadow-md p-6"><h2 class="mb-4 text-xl font-semibold text-gray-900 flex items-center"><i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i> Low Stock Alerts</h2><div class="space-y-3"><?php foreach ($lowStockProducts as $product): ?><div class="flex justify-between items-center p-3 bg-gray-50 rounded"><div class="flex-1 min-w-0"><p class="font-medium text-gray-900 truncate"><?php echo e($product['name']); ?></p><p class="text-sm text-gray-500">SKU: <?php echo e($product['sku']); ?> | <?php echo e($product['category_name'] ?? 'Uncategorized'); ?></p></div><div class="text-center"><div class="w-16 h-8 bg-red-100 rounded-full flex items-center justify-center text-xs font-medium"><?php echo number_format($product['quantity']); ?><span class="ml-1">/<?php echo number_format($product['low_stock_threshold']); ?></span></div><div class="mt-1 text-xs text-red-600">Low Stock</div></div></div><?php endforeach; ?></div></div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-6"><h2 class="mb-4 text-xl font-semibold text-gray-900 flex items-center"><i class="fas fa-check-circle mr-2 text-green-500"></i> Inventory Status</h2><div class="text-center py-8"><i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i><p class="text-gray-600">All products are above their low stock thresholds!</p></div></div>
            <?php endif; ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="mb-4 text-xl font-semibold text-gray-900">Recent Transactions</h2>
                <?php if (!empty($recentTransactions)): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="border-l-4 <?php echo $transaction['type'] === 'sale' ? 'border-red-500' : ($transaction['type'] === 'restock' ? 'border-green-500' : 'border-blue-500'); ?> pl-4 py-3">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900"><?php echo e($transaction['product_name']); ?></p>
                                        <p class="text-sm text-gray-500"><span class="<?php echo $transaction['type'] === 'sale' ? 'bg-red-100 text-red-800 px-2 py-1 rounded' : ($transaction['type'] === 'restock' ? 'bg-green-100 text-green-800 px-2 py-1 rounded' : 'bg-blue-100 text-blue-800 px-2 py-1 rounded'); ?>"><?php echo strtoupper($transaction['type']); ?></span> <?php echo number_format(abs($transaction['quantity'])); ?> units <?php if ($transaction['unit_price'] > 0): ?> @ <?php echo formatCurrency($transaction['unit_price']); ?> <?php endif; ?></p>
                                        <p class="text-xs text-gray-400">By: <?php echo e($transaction['user_name']); ?> | <?php echo formatDate($transaction['created_at']); ?></p>
                                        <?php if ($transaction['reference_number']): ?><p class="text-xs text-gray-400 mt-1">Ref: <?php echo e($transaction['reference_number']); ?></p><?php endif; ?>
                                    </div>
                                    <?php if (!empty($transaction['notes'])): ?><div class="ml-4 w-32 text-right text-sm"><p class="text-gray-500 italic"><?php echo e(truncate($transaction['notes'], 30)); ?></p></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><i class="fas fa-chart-line text-3xl text-gray-400 mb-4"></i><p class="text-gray-500">No recent transactions.</p></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 flex items-center"><i class="fas fa-clipboard-list mr-2 text-blue-500"></i> Recent Activity</h2>
            <?php if (!empty($recentAuditLogs)): ?>
                <div class="space-y-3">
                    <?php foreach ($recentAuditLogs as $log): ?>
                        <div class="flex justify-between items-start p-3 bg-gray-50 rounded">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <span class="w-2 h-2 <?php echo match(true) {
                                            str_contains($log['action'], 'create') => 'bg-green-500',
                                            str_contains($log['action'], 'update') => 'bg-blue-500',
                                            str_contains($log['action'], 'delete') => 'bg-red-500',
                                            str_contains($log['action'], 'login')  => 'bg-yellow-500',
                                            default                                => 'bg-gray-500'
                                        }; ?> rounded-full mr-3 mt-0.5"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php $action = str_replace('_', ' ', $log['action']); $action = ucwords($action); echo e($action); ?><?php if ($log['entity_type']): ?><span class="ml-2 text-xs text-gray-500">on <?php echo e(strtolower($log['entity_type'])); ?></span><?php endif; ?></p>
                                        <p class="text-sm text-gray-600">By: <?php echo e($log['username'] ?? 'System'); ?> | <?php echo formatDate($log['created_at']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right text-xs text-gray-400"><?php if (!empty($log['entity_id'])): ?>#<?php echo number_format($log['entity_id']); ?><?php endif; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8"><i class="fas fa-eye-slash text-3xl text-gray-400 mb-4"></i><p class="text-gray-500">No recent activity.</p></div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
