<?php
declare(strict_types=1);
/**
 * User Profile View
 *
 * Self-service profile page for authenticated users.
 * Shows account info and provides link to change password.
 */
$flashMessages = getFlashMessages();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
            </div>

            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm
                    <?php echo $msg['type'] === 'error'
                        ? 'bg-red-50 text-red-700'
                        : 'bg-green-50 text-green-700'; ?>">
                    <?php echo e($msg['message']); ?>
                </div>
            <?php endforeach; ?>

            <!-- Account Information -->
            <div class="border-b border-gray-100 pb-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Username</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($user['username']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($user['email']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($user['full_name'] ?? '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Role</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                <?php echo $user['role'] === 'admin'
                                    ? 'bg-purple-100 text-purple-700'
                                    : 'bg-blue-100 text-blue-700'; ?>">
                                <?php echo ucfirst(e($user['role'])); ?>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Member Since</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Security -->
            <div class="border-b border-gray-100 pb-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Security</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900">Change Password</p>
                            <p class="text-sm text-gray-500">Update your login password</p>
                        </div>
                        <a href="/change-password" 
                           class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            Change
                        </a>
                    </div>
                </div>
            </div>

            <!-- Danger Zone (admin only) -->
            <?php if ($user['role'] === 'admin'): ?>
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Administration</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg border border-red-100">
                        <div>
                            <p class="font-medium text-red-900">Manage Users</p>
                            <p class="text-sm text-red-600">Create, edit, or delete user accounts</p>
                        </div>
                        <a href="/users" 
                           class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                            Open Users
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>