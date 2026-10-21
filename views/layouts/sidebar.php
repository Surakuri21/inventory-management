<!-- Sidebar Navigation -->
<div class="bg-gray-800 text-white w-64 flex flex-col min-h-screen">
    <!-- Logo -->
    <div class="flex items-center justify-center py-4 border-b border-gray-700">
        <i class="fas fa-boxes fa-2x mr-2 text-blue-400"></i>
        <span class="text-xl font-bold">InventoryPro</span>
    </div>
    
    <!-- User Info -->
    <div class="px-4 py-3 border-b border-gray-700">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-lg"></i>
            </div>
            <div class="ml-3">
                <p class="font-medium"><?php echo e(currentUsername() ?? 'Guest'); ?></p>
                <p class="text-sm text-gray-400 capitalize">
                    <?php 
                    $role = currentUserRole() ?? 'guest';
                    echo $role === 'admin' ? 'Administrator' : 'Standard User';
                    ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Profile Link (all authenticated users) -->
    <?php if (isLoggedIn()): ?>
    <div class="px-4 py-2">
        <a href="/profile" 
           class="<?php echo ($route === 'profile') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors rounded">
            <i class="fas fa-user-circle mr-3"></i>
            Profile
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Navigation Menu -->
    <nav class="mt-4 flex-1">
        <ul class="space-y-1">
            <!-- Dashboard -->
            <li>
                <a href="/dashboard" 
                   class="<?php echo ($route === 'dashboard') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- Products -->
            <li>
                <a href="/products" 
                   class="<?php echo ($route === 'products') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                    <i class="fas fa-box-open mr-3"></i>
                    Products
                </a>
            </li>
            
            <!-- Transactions -->
            <li>
                <a href="/transactions" 
                   class="<?php echo ($route === 'transactions') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    Transactions
                </a>
            </li>
            
            <!-- Admin-only sections -->
            <?php if (isAdmin()): ?>
                <!-- Categories -->
                <li>
                    <a href="/categories" 
                       class="<?php echo ($route === 'categories') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                        <i class="fas fa-tags mr-3"></i>
                        Categories
                    </a>
                </li>
                
                <!-- Suppliers -->
                <li>
                    <a href="/suppliers" 
                       class="<?php echo ($route === 'suppliers') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                        <i class="fas fa-truck mr-3"></i>
                        Suppliers
                    </a>
                </li>
                
                <!-- Users -->
                <li>
                    <a href="/users" 
                       class="<?php echo ($route === 'users') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                        <i class="fas fa-users mr-3"></i>
                        Users
                    </a>
                </li>
                
                <!-- Audit Logs -->
                <li>
                    <a href="/audit" 
                       class="<?php echo ($route === 'audit') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> flex items-center px-4 py-2 transition-colors">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        Audit Logs
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Logout -->
    <div class="border-t border-gray-700 p-4">
        <a href="/logout" 
           class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-2 transition-colors rounded">
            <i class="fas fa-sign-out-alt mr-3"></i>
            Logout
        </a>
    </div>
</div>
<!-- /Sidebar Navigation -->