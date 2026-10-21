<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$suppliers = fetchAll("SELECT * FROM suppliers ORDER BY name ASC");
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-5xl mx-auto"><h1 class="text-2xl font-bold text-gray-900 mb-6">Suppliers</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?php echo $msg['type']==='error'?'bg-red-50 text-red-700 border border-red-200':'bg-green-50 text-green-700 border border-green-200'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<a href="/suppliers/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 mb-4">Add Supplier</a>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left text-sm"><thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold"><tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Contact</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Phone</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr></thead><tbody class="divide-y divide-gray-100">
<?php if (empty($suppliers)): ?><tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No suppliers.</td></tr>
<?php else: ?><?php foreach ($suppliers as $s): ?><tr class="hover:bg-gray-50">
<td class="px-5 py-3 font-medium text-gray-900"><?php echo e($s['name']); ?></td>
<td class="px-5 py-3 text-gray-600"><?php echo e($s['contact_person'] ?? '—'); ?></td>
<td class="px-5 py-3 text-gray-600"><?php echo e($s['email'] ?? '—'); ?></td>
<td class="px-5 py-3 text-gray-600"><?php echo e($s['phone'] ?? '—'); ?></td>
<td class="px-5 py-3"><?php echo $s['is_active'] ? '<span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">Active</span>' : '<span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactive</span>'; ?></td>
<td class="px-5 py-3"><a href="/suppliers/edit?id=<?php echo (int)$s['id']; ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3">Edit</a><form method="POST" action="/actions/suppliers/delete.php" class="inline" onsubmit="return confirm('Delete supplier?');"><?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button></form></td></tr>
<?php endforeach; ?><?php endif; ?></tbody></table></div>
</div></main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
