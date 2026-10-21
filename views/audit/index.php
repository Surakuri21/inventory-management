<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$logs = fetchAll("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100");
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-6xl mx-auto"><h1 class="text-2xl font-bold text-gray-900 mb-6">Audit Logs</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm <?php echo $msg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left text-sm"><thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold"><tr><th class="px-5 py-3">Action</th><th class="px-5 py-3">Entity</th><th class="px-5 py-3">User</th><th class="px-5 py-3">IP</th><th class="px-5 py-3">Created</th></tr></thead><tbody class="divide-y divide-gray-100">
<?php if (empty($logs)): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No audit logs.</td></tr>
<?php else: ?><?php foreach ($logs as $log): ?>
<tr class="hover:bg-gray-50">
<td class="px-5 py-3 text-xs font-medium text-gray-900"><?php echo e(str_replace('_', ' ', $log['action'])); ?></td>
<td class="px-5 py-3 text-xs text-gray-600"><?php echo e($log['entity_type']); ?> #<?php echo (int)($log['entity_id'] ?? 0); ?></td>
<td class="px-5 py-3 text-xs text-gray-700"><?php echo e($log['username'] ?? 'System'); ?></td>
<td class="px-5 py-3 text-xs text-gray-500 font-mono"><?php echo e($log['ip_address'] ?? '—'); ?></td>
<td class="px-5 py-3 text-xs text-gray-500"><?php echo formatDate($log['created_at']); ?></td>
</tr>
<?php endforeach; ?><?php endif; ?></tbody></table></div>
</div></main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
