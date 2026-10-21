<?php
/**
 * Login View
 * 
 * Displays the login form for user authentication.
 */
?>

<div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="flex justify-center">
                <i class="fas fa-boxes fa-3x text-blue-600"></i>
            </div>
            <h2 class="mt-4 text-center text-3xl font-extrabold text-gray-900">
                Inventory Management System
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to your account
            </p>
        </div>
        
        <form class="mt-8 space-y-6" action="/login" method="POST">
            <?php echo csrfField(); ?>
            
            <?php if (isset($error) && $error): ?>
                <div class="bg-red-100 border border-red-200 text-red-800 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo e($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Username or Email</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="Username or Email">
                </div>
                <div class="relative">
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 pr-12 sm:text-sm"
                           placeholder="Password">
                    <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            aria-label="Show/hide password" aria-pressed="false">
                        <i class="far fa-eye text-lg"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember" name="remember" type="checkbox" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-900">
                        Remember me
                    </label>
                </div>
                
                <div class="text-sm">
                    <a href="#" class="font-medium text-blue-600 hover:text-blue-500">
                        Forgot your password?
                    </a>
                </div>
            </div>
            
            <div>
                <button type="submit" 
                        class="group mt-2 w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute hidden -mt-px">
                        <i class="fas fa-lock"></i>
                    </span>
                    Sign In
                </button>
            </div>
        </form>

        <script>
            (function () {
                var pwd = document.getElementById('password');
                var btn = document.getElementById('togglePassword');
                if (!pwd || !btn) { return; }
                var icon = btn.querySelector('i');
                btn.addEventListener('click', function () {
                    var showing = pwd.type === 'text';
                    pwd.type = showing ? 'password' : 'text';
                    btn.setAttribute('aria-pressed', String(!showing));
                    icon.className = showing
                        ? 'far fa-eye text-lg'
                        : 'far fa-eye-slash text-lg';
                });
            })();
        </script>

        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                Change the default administrator password immediately after first login.
            </p>
        </div>
    </div>
</div>