<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$id = validateId($_GET['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid supplier.'); redirect('suppliers'); }
$sup = fetchOne("SELECT * FROM suppliers WHERE id = :id", ['id' => $id]);
if (!$sup) { setFlashMessage('error', 'Not found.'); redirect('suppliers'); }
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen"><div class="max-w-xl mx-auto"><h1 class="text-2xl font-bold mb-6">Edit Supplier</h1>
<?php if (!empty($flashMessages)): ?><?php foreach ($flashMessages as $msg): ?><div class="mb-4 px-4 py-3 rounded-lg text-sm <?php echo $msg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700'; ?>"><?php echo e($msg['message']); ?></div><?php endforeach; ?><?php endif; ?>
<?php if (!empty($formErrors)): ?><div class="mb-4 px-4 py-3 bg-red-50 text-red-700 rounded-lg text-sm"><ul class="list-disc list-inside"><?php foreach ($formErrors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" action="/actions/suppliers/update.php" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
<?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$sup['id']; ?>">
<div><label for="name" class="block text-sm font-semibold mb-1">Name *</label><input type="text" id="name" name="name" value="<?php echo e($formData['name'] ?? $sup['name']); ?>" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
<div><label for="contact_person" class="block text-sm font-semibold mb-1">Contact</label><input type="text" id="contact_person" name="contact_person" value="<?php echo e($formData['contact_person'] ?? $sup['contact_person'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label for="email" class="block text-sm font-semibold mb-1">Email</label><input type="email" id="email" name="email" value="<?php echo e($formData['email'] ?? $sup['email'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label for="phone" class="block text-sm font-semibold mb-1">Phone</label><input type="text" id="phone" name="phone" value="<?php echo e($formData['phone'] ?? $sup['phone'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label for="address" class="block text-sm font-semibold mb-1">Address</label><textarea id="address" name="address" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm resize-y"><?php echo e($formData['address'] ?? $sup['address'] ?? ''); ?></textarea></div>
<div><label for="city" class="block text-sm font-semibold mb-1">City</label><input type="text" id="city" name="city" value="<?php echo e($formData['city'] ?? $sup['city'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label for="country" class="block text-sm font-semibold mb-1">Country</label><input type="text" id="country" name="country" value="<?php echo e($formData['country'] ?? $sup['country'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
<div><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo (isset($formData['is_active']) ? ($formData['is_active']?'checked':'') : ($sup['is_active']?'checked':'')); ?> class="w-4 h-4 text-blue-600"><span class="text-sm font-medium">Active</span></label></div>
<div class="flex gap-3"><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update</button><a href="/suppliers" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a></div>
</form>
</div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
