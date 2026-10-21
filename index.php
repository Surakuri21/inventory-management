<?php
/**
 * Inventory Management System - Main Entry Point / Router
 * 
 * This file serves as the application's front controller and router.
 * It handles routing between pages and includes the appropriate views.
 */

declare(strict_types=1);

// Include all required files
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit.php';

// Start secure session
startSecureSession();

// Get the route from the normalized URI path (not $_GET) for Nginx parity
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($requestUri, '/');

// Default to dashboard if root is accessed
if ($route === '') {
    $route = 'dashboard';
}

// Parse optional action segment (e.g. /products/create → route=products, action=create)
$action = '';
if (strpos($route, '/') !== false) {
    $parts = explode('/', $route, 2);
    $action = $parts[1];
    $route = $parts[0];
}

// Validate the session before allowing access to protected routes
if (isLoggedIn()) {
    if (!validateSession()) {
        logoutUser();
        setFlashMessage('error', 'Your session has expired. Please log in again.');
        redirect('login');
    }

    // If user must change password, redirect to change-password route
    // (except when already on the change-password route or logging out)
    if (mustChangePassword() && $route !== 'change-password') {
        setFlashMessage('warning', 'You must reset your password before continuing.');
        redirect('change-password');
    }
}

// Routing logic
// Define public routes that don't require authentication
$publicRoutes = ['login', 'logout', 'profile', 'change-password'];

// Define admin-only routes
$adminRoutes = [
    'products/create', 'products/edit', 'products/delete',
    'categories/create', 'categories/edit', 'categories/delete',
    'suppliers/create', 'suppliers/edit', 'suppliers/delete',
    'users', 'users/create', 'users/edit', 'users/delete',
    'transactions/create',
    'audit',
    'settings',
];

// Check if route requires authentication (login/logout are in $publicRoutes)
if (!in_array($route, $publicRoutes)) {
    requireAuth();
}

// Check if route requires admin role
$fullRoute = $route . ($action ? '/' . $action : '');
if (in_array($fullRoute, $adminRoutes) || in_array($route, ['categories', 'suppliers', 'users', 'audit', 'settings'])) {
    // For categories, suppliers, users, and audit - only admin can access create/edit/delete
    // Standard users can view products and transactions only
    // transactions/create is admin-only (creating stock transactions)
    $adminOnlyRoutes = ['categories', 'suppliers', 'users', 'audit', 'settings'];
    if (in_array($route, $adminOnlyRoutes) && $action !== 'index' && $action !== '') {
        requireAdmin();
    }
    // For view (index) of categories, suppliers, users - require admin
    if (in_array($route, $adminOnlyRoutes) && $action === '') {
        requireAdmin();
    }
    // transactions/create requires admin
    if ($fullRoute === 'transactions/create') {
        requireAdmin();
    }
}

// Route dispatch
try {
    switch ($route) {
        case 'login':
            if (isLoggedIn()) {
                redirect('dashboard');
            }

            // Authentication POST handler (front-controller owned)
            $error = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // CSRF verification before any credential processing
                if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
                    $error = 'Invalid session token. Please refresh the page and try again.';
                } else {
                    $username = trim((string) ($_POST['username'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');

                    if ($username === '' || $password === '') {
                        // Generic message: never disclose which field was wrong
                        $error = 'Invalid username or password.';
                    } else {
                        // authenticateUser() runs password_verify() and logs the audit event
                        $user = authenticateUser($username, $password);

                        if ($user !== false) {
                            // loginUser() regenerates the session ID to defeat fixation
                            loginUser($user);
                            setFlashMessage('success', 'Welcome, ' . e((string) $user['full_name']) . '!');

                            // If user must change password, redirect to change-password
                            if (!empty($user['must_change_password'])) {
                                setFlashMessage('warning', 'Please change your password before continuing.');
                                redirect('change-password');
                            }

                            $intendedUrl = $_SESSION['intended_url'] ?? null;
                            unset($_SESSION['intended_url']);

                            // Only honour same-site absolute paths (blocks open redirect)
                            if (
                                is_string($intendedUrl)
                                && str_starts_with($intendedUrl, '/')
                                && !str_starts_with($intendedUrl, '//')
                            ) {
                                header('Location: ' . $intendedUrl);
                                exit;
                            }

                            redirect('dashboard');
                        }

                        // Generic failure message prevents user enumeration
                        $error = 'Invalid username or password.';
                    }
                }
            }

            include_once __DIR__ . '/views/auth/login.php';
            break;
            
        case 'logout':
            logoutUser();
            setFlashMessage('success', 'You have been logged out successfully.');
            redirect('login');
            break;

        case 'change-password':
            // Authenticated users with must_change_password flag are redirected here.
            // Other authenticated users can also access it from settings/profile.
            requireAuth();

            $error = '';
            $success = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
                    $error = 'Invalid session token. Please refresh the page and try again.';
                } else {
                    $currentPassword = (string) ($_POST['current_password'] ?? '');
                    $newPassword = (string) ($_POST['new_password'] ?? '');
                    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                        $error = 'All fields are required.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'New password and confirmation do not match.';
                    } elseif (strlen($newPassword) < 8) {
                        $error = 'Password must be at least 8 characters.';
                    } else {
                        // Verify current password against the database
                        $userRecord = fetchOne(
                            "SELECT id, password_hash FROM users WHERE id = :id",
                            ['id' => currentUserId()]
                        );

                        if (!$userRecord || !password_verify($currentPassword, $userRecord['password_hash'])) {
                            $error = 'Current password is incorrect.';
                        } else {
                            // Update password
                            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                            execute(
                                "UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id",
                                ['hash' => $newHash, 'id' => currentUserId()]
                            );

                            // Clear session flag
                            $_SESSION['must_change_password'] = false;

                            // Audit log
                            logAuditAction('password_change', 'users', currentUserId(), null, [
                                'ip_address' => getIpAddress(),
                            ]);

                            setFlashMessage('success', 'Password changed successfully.');
                            redirect('dashboard');
                        }
                    }
                }
            }

            include_once __DIR__ . '/views/auth/change_password.php';
            break;

        case 'profile':
            // User profile page - self-service for all authenticated users
            requireAuth();
            
            $user = fetchOne(
                "SELECT id, username, email, full_name, role, last_login, created_at FROM users WHERE id = :id",
                ['id' => currentUserId()]
            );
            $flashMessages = getFlashMessages();
            include_once __DIR__ . '/views/auth/profile.php';
            break;

        case 'dashboard':
            $stats = [
                'total_products' => fetchColumn("SELECT COUNT(*) FROM products WHERE is_active = 1"),
                'low_stock_count' => fetchColumn("SELECT COUNT(*) FROM products WHERE quantity <= low_stock_threshold AND quantity > 0 AND is_active = 1"),
                'out_of_stock_count' => fetchColumn("SELECT COUNT(*) FROM products WHERE quantity <= 0 AND is_active = 1"),
                'total_categories' => fetchColumn("SELECT COUNT(*) FROM categories WHERE is_active = 1"),
                'total_suppliers' => fetchColumn("SELECT COUNT(*) FROM suppliers WHERE is_active = 1"),
            ];
            $lowStockProducts = fetchAll("SELECT p.id, p.sku, p.name, p.quantity, p.low_stock_threshold, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.quantity <= p.low_stock_threshold AND p.quantity > 0 AND p.is_active = 1 ORDER BY p.quantity ASC LIMIT 5");
            $recentTransactions = fetchAll("SELECT t.*, p.name AS product_name, u.username AS user_name FROM transactions t JOIN products p ON t.product_id = p.id JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5");
            $recentAuditLogs = fetchAll("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10");
            $todayTransactions = fetchColumn("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE()") ?: 0;
            include_once __DIR__ . '/views/dashboard/index.php';
            break;
            
        case 'products':
            switch ($action) {
                case 'create':
                    requireAdmin();
                    include_once __DIR__ . '/views/products/create.php';
                    break;
                case 'edit':
                    requireAdmin();
                    include_once __DIR__ . '/views/products/edit.php';
                    break;
                default:
                    include_once __DIR__ . '/views/products/index.php';
                    break;
            }
            break;
            
        case 'categories':
            requireAdmin();
            switch ($action) {
                case 'create':
                    include_once __DIR__ . '/views/categories/create.php';
                    break;
                case 'edit':
                    include_once __DIR__ . '/views/categories/edit.php';
                    break;
                default:
                    include_once __DIR__ . '/views/categories/index.php';
                    break;
            }
            break;
            
        case 'suppliers':
            requireAdmin();
            switch ($action) {
                case 'create':
                    include_once __DIR__ . '/views/suppliers/create.php';
                    break;
                case 'edit':
                    include_once __DIR__ . '/views/suppliers/edit.php';
                    break;
                default:
                    include_once __DIR__ . '/views/suppliers/index.php';
                    break;
            }
            break;
            
        case 'transactions':
            requireAdmin();
            switch ($action) {
                case 'create':
                    include_once __DIR__ . '/views/transactions/create.php';
                    break;
                default:
                    include_once __DIR__ . '/views/transactions/index.php';
                    break;
            }
            break;
            
        case 'users':
            requireAdmin();
            switch ($action) {
                case 'create':
                    include_once __DIR__ . '/views/users/create.php';
                    break;
                case 'edit':
                    include_once __DIR__ . '/views/users/edit.php';
                    break;
                default:
                    include_once __DIR__ . '/views/users/index.php';
                    break;
            }
            break;
            
        case 'audit':
            requireAdmin();
            include_once __DIR__ . '/views/audit/index.php';
            break;
            
        case 'settings':
            requireAdmin();
            include_once __DIR__ . '/views/settings/index.php';
            break;
            
        case 'keepalive':
            // Session keep-alive endpoint (AJAX heartbeat). Requires auth.
            requireAuth();
            // Touch the session activity timestamp by calling isLoggedIn() path.
            // Returns empty content — session lifetime is extended by startSecureSession()
            // on every request (last_activity is refreshed at top of index.php).
            http_response_code(204);
            exit;

        default:
            // Unknown route - redirect to dashboard
            redirect('dashboard');
            break;
    }
} catch (PDOException $e) {
    // Log the error
    error_log('Database error: ' . $e->getMessage());
    setFlashMessage('error', 'A database error occurred. Please try again.');
    redirect('dashboard');
}
