<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$users = fetchAll("SELECT id, username, full_name, email, role, is_active FROM users ORDER BY id ASC");
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-5xl mx-auto"><h1 class="text-2xl font-bold text-gray-900 mb-6">Users</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm <?php echo $msg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<a href="//users/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 mb-4">Add User</a>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"><table class="w-full text-left text-sm"><thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold"><tr><th class="px-5 py-3">Username</th><th class="px-5 py-3">Full Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr></thead><tbody class="divide-y divide-gray-100">
<?php if (empty($users)): ?><tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No users.</td></tr>
<?php else: ?><?php foreach ($users as $u): ?><tr class="hover:bg-gray-50">
<td class="px-5 py-3 font-medium text-gray-900"><?php echo e($u['username']); ?></td>
<td class="px-5 py-3 text-gray-700"><?php echo e($u['full_name']); ?></td>
<td class="px-5 py-3 text-gray-600"><?php echo e($u['email']); ?></td>
<td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded-full <?php echo $u['role']==='admin'?'bg-purple-100 text-purple-700':'bg-blue-100 text-blue-700'; ?>"><?php echo ucfirst(e($u['role'])); ?></span></td>
<td class="px-5 py-3"><?php echo $u['is_active'] ? '<span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">Active</span>' : '<span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactive</span>'; ?></td>
<td class="px-5 py-3"><a href="//users/edit?id=<?php echo (int)$u['id']; ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3">Edit</a><form method="POST" action="/actions/users/delete.php" class="inline" onsubmit="return confirm('Delete user?');"><?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button></form></td></tr>
<?php endforeach; ?><?php endif; ?></tbody></table></div>
</div></main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>

