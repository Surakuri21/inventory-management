<?php
/**
 * Authentication and Session Management
 * 
 * This file handles user authentication, session security,
 * and role-based access control (RBAC).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Session configuration constants.
 */
define('SESSION_TIMEOUT', 30 * 60);        // 30 minutes in seconds
define('SESSION_TIMEOUT_WARNING', 25 * 60); // 25 minutes - warn before timeout

/**
 * Start a secure session with hardened settings.
 * 
 * This function configures session cookies for security:
 * - HttpOnly flag prevents JavaScript access
 * - Secure flag ensures HTTPS-only transmission (set to true in production)
 * - Strict mode prevents session fixation
 */
function startSecureSession(): void
{
    // Configure session cookie parameters
    session_set_cookie_params([
        'lifetime'   => 0,                          // Until browser close
        'path'       => '/',                        // Available site-wide
        'domain'     => '',                         // Current domain
        'secure'     => isset($_SERVER['HTTPS']),   // HTTPS only in production
        'httponly'   => true,                       // Prevent JS access
        'samesite'   => 'Strict',                   // Prevent CSRF
    ]);
    
    // Use strict mode to prevent session fixation
    ini_set('session.use_strict_mode', '1');
    
    // Start the session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Session expired due to inactivity
        destroySession();
        return;
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    // Set session creation time if not set
    if (!isset($_SESSION['session_created'])) {
        $_SESSION['session_created'] = time();
    }
    
    // Force session regeneration every 5 minutes for security
    if (time() - $_SESSION['session_created'] > 300) {
        session_regenerate_id(true);
        $_SESSION['session_created'] = time();
    }
}

/**
 * Authenticate a user by username/email and password.
 * 
 * @param string $username The username or email
 * @param string $password The plain-text password
 * @return array|false User data array on success, false on failure
 */
function authenticateUser(string $username, string $password): array|false
{
    // Sanitize input - no HTML allowed
    $username = trim($username);
    
    // Fetch user by username or email using prepared statement
    $sql = "SELECT id, username, password_hash, full_name, role, is_active, must_change_password 
            FROM users 
            WHERE (username = :username OR email = :email) AND is_active = 1 
            LIMIT 1";
    
    $user = fetchOne($sql, [
        'username' => $username,
        'email' => $username,
    ]);
    
    if (!$user) {
        // User not found or inactive
        return false;
    }
    
    // Verify password using password_verify (secure against timing attacks when combined with hash_equals)
    if (password_verify($password, $user['password_hash'])) {
        // Password is correct - update last_login timestamp
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = :id";
        execute($updateSql, ['id' => $user['id']]);
        
        // Regenerate session ID after successful login to prevent session fixation
        session_regenerate_id(true);
        
        // Log audit trail
        logAuditAction('login', 'users', $user['id'], null, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        
        return $user;
    }
    
    // Password verification failed
    return false;
}

/**
 * Log a user in by setting session variables.
 * 
 * @param array $user The user data from authenticateUser()
 * @return void
 */
function loginUser(array $user): void
{
    // Store minimal user info in session
    $_SESSION['user_id']                = $user['id'];
    $_SESSION['username']               = $user['username'];
    $_SESSION['role']                   = $user['role'];
    $_SESSION['must_change_password']   = (bool) $user['must_change_password'];
    
    // Regenerate session ID after login
    session_regenerate_id(true);
}

/**
 * Log the current user out.
 * 
 * @return void
 */
function logoutUser(): void
{
    // Log audit trail before destroying session
    if (isset($_SESSION['user_id'])) {
        logAuditAction('logout', 'users', $_SESSION['user_id'], null, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
    
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Destroy the current session completely.
 * 
 * @return void
 */
function destroySession(): void
{
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

/**
 * Get the currently logged-in user's ID.
 * 
 * @return int|null User ID or null if not logged in
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Get the currently logged-in user's username.
 * 
 * @return string|null Username or null if not logged in
 */
function currentUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

/**
 * Get the currently logged-in user's role.
 * 
 * @return string|null Role ('admin' or 'standard') or null if not logged in
 */
function currentUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Check if the user is logged in.
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if the current user has admin role.
 * 
 * @return bool True if admin, false otherwise
 */
function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Require authentication - redirect to login if not authenticated.
 * 
 * @return void
 */
function requireAuth(): void
{
    // Direct action scripts (actions/*/*.php) include this file but never call
    // startSecureSession() themselves — start it here so $_SESSION is available
    // (no-op when the front controller already started the session).
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }

    if (!isLoggedIn()) {
        // Store intended URL for redirect after login
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        redirect('login');
    }
}

/**
 * Require admin role - redirect or show error if not admin.
 * 
 * @return void
 */
function requireAdmin(): void
{
    requireAuth();
    
    if (!isAdmin()) {
        // Log unauthorized access attempt
        logAuditAction('unauthorized_access', 'security', currentUserId(), null, [
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        
        setFlashMessage('error', 'Access denied. Administrator privileges required.');
        redirect('dashboard');
    }
}

/**
 * Check if the current user must change their password.
 * 
 * @return bool True if password change is required
 */
function mustChangePassword(): bool
{
    return isLoggedIn() && ($_SESSION['must_change_password'] ?? false) === true;
}

/**
 * Clear the must-change-password flag after password is changed.
 * 
 * @return void
 */
function clearMustChangePassword(): void
{
    $_SESSION['must_change_password'] = false;
    $sql = "UPDATE users SET must_change_password = 0 WHERE id = :id";
    execute($sql, ['id' => currentUserId()]);
}

/**
 * Check if the session is about to expire (for warning display).
 * 
 * @return bool True if session expires within the warning window
 */
function isSessionExpiringSoon(): bool
{
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $remaining = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    return $remaining <= SESSION_TIMEOUT_WARNING && $remaining > 0;
}

/**
 * Check session validity including session token verification.
 * Used to prevent session hijacking.
 * 
 * @return bool True if session is valid
 */
function validateSession(): bool
{
    // Verify session hasn't expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        return false;
    }
    
    // Verify user still exists and is active
    if (isset($_SESSION['user_id'])) {
        $user = fetchOne(
            "SELECT is_active FROM users WHERE id = :id",
            ['id' => $_SESSION['user_id']]
        );
        
        if (!$user || !$user['is_active']) {
            return false;
        }
    }
    
    return true;
}

/**
 * Redirect to a given route/page.
 * 
 * @param string $path The path to redirect to
 * @param array $params Optional query parameters
 * @return void
 */
function redirect(string $path, array $params = []): void
{
    $url = '/' . ltrim($path, '/');
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header("Location: $url");
    exit;
}

/**
 * Get the IP address of the current user.
 * 
 * @return string The user's IP address
 */
function getIpAddress(): string
{
    // Check various headers for the real IP (behind proxy/CDN)
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RESV_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
