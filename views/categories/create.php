<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();
$categories = getAllCategories();
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-xl mx-auto"><h1 class="text-2xl font-bold text-gray-900 mb-6">Create Category</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?php echo $msg['type']==='error'?'bg-red-50 text-red-700 border border-red-200':'bg-green-50 text-green-700 border border-green-200'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<?php if (!empty($formErrors)): ?><div class="mb-4 px-4 py-3 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm font-medium"><ul class="list-disc list-inside space-y-1"><?php foreach ($formErrors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" action="/actions/categories/create.php" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5" novalidate>
<?php echo csrfField(); ?>
<div><label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label><input type="text" id="name" name="name" value="<?php echo e($formData['name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Category name" required></div>
<div><label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Description</label><textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"><?php echo e($formData['description'] ?? ''); ?></textarea></div>
<div><label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_active" value="1" <?php echo (isset($formData['is_active']) && $formData['is_active']) || !isset($formData['is_active']) ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 border-gray-300 rounded"><span class="text-sm text-gray-700 font-medium">Active</span></label></div>
<div class="flex gap-3"><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Create Category</button><a href="/categories" class="px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Cancel</a></div>
</form>
</div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
