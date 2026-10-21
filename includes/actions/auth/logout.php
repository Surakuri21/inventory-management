<?php
/**
 * Logout Action Handler
 * 
 * Logs the current user out and redirects to login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Log the user out
logoutUser();

// Set success message
setFlashMessage('success', 'You have been logged out successfully.');

// Redirect to login page
redirect('login');