<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- External dependencies -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Session timeout warning -->
    <script>
        // Warn user before session expires
        const sessionTimeout = <?php echo json_encode(SESSION_TIMEOUT); ?> * 1000;
        let warningTime = sessionTimeout - (5 * 60 * 1000);

        setTimeout(() => {
            if (confirm('Your session will expire soon. Do you want to stay logged in?')) {
                fetch('/keepalive', { method: 'POST' });
            } else {
                window.location.href = '/logout';
            }
        }, warningTime);
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased">
<?php if (isLoggedIn()): ?>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex flex-col flex-1 overflow-y-auto">
            <!-- Flash Messages -->
            <?php foreach (getFlashMessages() as $message): ?>
                <div class="mx-6 mt-4 p-4 rounded-md shadow-md transition-all duration-300 ease-in-out
                    <?php echo $message['type'] === 'success'
                        ? 'bg-green-100 border border-green-200 text-green-800'
                        : 'bg-red-100 border border-red-200 text-red-800'; ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo e($message['message']); ?></p>
                        </div>
                        <div class="ml-auto pl-3">
                            <button type="button" class="inline-flex text-gray-400 hover:text-gray-600"
                                onclick="this.parentElement.parentElement.remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Page Content -->
            <div class="flex-1 overflow-y-auto p-6">
<?php else: ?>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
<?php endif; ?>