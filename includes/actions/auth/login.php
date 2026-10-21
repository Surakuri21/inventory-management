<?php
/**
 * Login Action Handler
 * 
 * Processes login form submissions and authenticates users.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login');
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $error = 'Invalid CSRF token. Please refresh the page and try again.';
    include_once __DIR__ . '/../../views/auth/login.php';
    exit;
}

// Get and sanitize form inputs
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
$errors = [];

if (empty($username)) {
    $errors[] = 'Username or email is required.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
}

if (!empty($errors)) {
    $error = implode(' ', $errors);
    include_once __DIR__ . '/../../views/auth/login.php';
    exit;
}

// Attempt authentication
$user = authenticateUser($username, $password);

if ($user) {
    // Authentication successful
    loginUser($user);
    
    // Set success message
    setFlashMessage('success', 'Welcome, ' . e($user['full_name']) . '! You have been logged in successfully.');
    
    // Redirect to intended page or dashboard
    $intendedUrl = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);
    
    if ($intendedUrl) {
        redirect($intendedUrl);
    } else {
        redirect('dashboard');
    }
} else {
    // Authentication failed
    $error = 'Invalid username or password. Please try again.';
    include_once __DIR__ . '/../../views/auth/login.php';
    exit;
}
