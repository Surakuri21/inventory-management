<?php
declare(strict_types=1);
/**
 * Change Password View
 *
 * Shown after login when must_change_password is set.
 * Also accessible to authenticated users for self-service password changes.
 */
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Change Password</h1>
            <p class="text-sm text-gray-600 mb-6">
                You must set a new password before continuing.
            </p>

            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm
                    <?php echo $msg['type'] === 'error'
                        ? 'bg-red-50 text-red-700'
                        : 'bg-green-50 text-green-700'; ?>">
                    <?php echo e($msg['message']); ?>
                </div>
            <?php endforeach; ?>

            <?php if (isset($error) && $error): ?>
                <div class="mb-4 px-4 py-3 bg-red-50 text-red-700 rounded-lg text-sm">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/change-password" class="space-y-5">
                <?php echo csrfField(); ?>

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Current Password
                    </label>
                    <input type="password" id="current_password" name="current_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                        New Password
                    </label>
                    <input type="password" id="new_password" name="new_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required minlength="8">
                    <p class="text-xs text-gray-500 mt-1">Minimum 8 characters.</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm New Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required minlength="8">
                </div>

                <button type="submit"
                        class="w-full px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
