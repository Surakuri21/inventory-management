<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();
$id = validateId($_GET['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid.'); redirect('categories'); }
$cat = fetchOne("SELECT id, name FROM categories WHERE id = :id", ['id' => $id]);
if (!$cat) { setFlashMessage('error', 'Not found.'); redirect('categories'); }
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-xl mx-auto"><h1 class="text-2xl font-bold mb-6">Edit Category</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?php echo $msg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<?php if (!empty($formErrors)): ?><div class="mb-4 px-4 py-3 bg-red-50 text-red-700 rounded-lg text-sm"><ul class="list-disc list-inside"><?php foreach ($formErrors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" action="/actions/categories/update.php" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
<?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
<div><label for="name" class="block text-sm font-semibold mb-1">Name *</label><input type="text" id="name" name="name" value="<?php echo e($formData['name'] ?? $cat['name']); ?>" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
<div><label for="description" class="block text-sm font-semibold mb-1">Description</label><textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border rounded-lg text-sm resize-y"><?php echo e($formData['description'] ?? ($cat['description'] ?? '')); ?></textarea></div>
<div><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo (isset($formData['is_active']) ? ($formData['is_active']?'checked':'') : ($cat['is_active']?'checked':'')); ?> class="w-4 h-4 text-blue-600"><span class="text-sm font-medium">Active</span></label></div>
<div class="flex gap-3"><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update</button><a href="/categories" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a></div>
</form>
</div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
