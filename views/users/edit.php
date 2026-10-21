<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$id = validateId($_GET['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid user.'); redirect('users'); }
$user = fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $id]);
if (!$user) { setFlashMessage('error', 'Not found.'); redirect('users'); }
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-xl mx-auto"><h1 class="text-2xl font-bold mb-6">Edit User</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm <?php echo $msg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<?php if (!empty($formErrors)): ?><div class="mb-4 px-4 py-3 bg-red-50 text-red-700 rounded-lg text-sm"><ul class="list-disc list-inside"><?php foreach ($formErrors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" action="/actions/users/update.php" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
<?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
<div><label for="username" class="block text-sm font-semibold mb-1">Username *</label><input type="text" id="username" name="username" value="<?php echo e($formData['username'] ?? $user['username']); ?>" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
<div><label for="email" class="block text-sm font-semibold mb-1">Email *</label><input type="email" id="email" name="email" value="<?php echo e($formData['email'] ?? $user['email']); ?>" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
<div><label for="password" class="block text-sm font-semibold mb-1">Password <span class="text-xs text-gray-400">(leave empty to keep current)</span></label><input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label for="full_name" class="block text-sm font-semibold mb-1">Full Name</label><input type="text" id="full_name" name="full_name" value="<?php echo e($formData['full_name'] ?? $user['full_name']); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div class="grid grid-cols-2 gap-4">
<div><label for="role" class="block text-sm font-semibold mb-1">Role</label><select id="role" name="role" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="standard" <?php echo (isset($formData['role']) ? ($formData['role']==='admin' ? '' : 'selected') : ($user['role']==='admin' ? '' : 'selected')); ?>>Standard</option><option value="admin" <?php echo (isset($formData['role']) ? ($formData['role']==='admin' ? 'selected' : '') : ($user['role']==='admin' ? 'selected' : '')); ?>>Admin</option></select></div>
<div><label class="inline-flex items-center gap-2 mt-6"><input type="checkbox" name="is_active" value="1" <?php echo (isset($formData['is_active']) ? ($formData['is_active'] ? 'checked' : '') : ($user['is_active'] ? 'checked' : '')); ?> class="w-4 h-4 text-blue-600"><span class="text-sm font-medium">Active</span></label></div>
</div>
<div class="flex gap-3"><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update</button><a href="//users" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a></div>
</form>
</div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>

