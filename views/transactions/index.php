<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();
$transactions = fetchAll("
    SELECT t.*, p.name AS product_name, u.username AS user_name
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 50
");
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Transactions</h1>
        <?php if (isAdmin()): ?>
            <a href="/transactions/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 mb-6">New Transaction</a>
        <?php endif; ?>
        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?php echo $msg['type']==='error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'; ?>"><?php echo e($msg['message']); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold"><tr><th class="px-5 py-3">Ref</th><th class="px-5 py-3">Product</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Quantity</th><th class="px-5 py-3">Unit Price</th><th class="px-5 py-3">User</th><th class="px-5 py-3">Created</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($transactions)): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No transactions.</td></tr>
                    <?php else: ?><?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs text-gray-600"><?php echo e($t['reference_number'] ?? '—'); ?></td>
                            <td class="px-5 py-3 font-medium text-gray-900"><?php echo e($t['product_name']); ?></td>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded-full <?php echo $t['type']==='sale'?'bg-red-100 text-red-700':($t['type']==='restock'?'bg-green-100 text-green-700':($t['type']==='return'?'bg-blue-100 text-blue-700':'bg-amber-100 text-amber-700')); ?>"><?php echo strtoupper(e($t['type'])); ?></span></td>
                            <td class="px-5 py-3 font-semibold <?php echo $t['quantity']<0?'text-red-600':'text-green-600'; ?>"><?php echo number_format($t['quantity']); ?></td>
                            <td class="px-5 py-3 text-gray-700">$<?php echo number_format((float)$t['unit_price'],2); ?></td>
                            <td class="px-5 py-3 text-gray-600"><?php echo e($t['user_name']); ?></td>
                            <td class="px-5 py-3 text-gray-500 text-xs"><?php echo formatDate($t['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
