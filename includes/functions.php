<?php
/**
 * Shared Utility Functions
 * 
 * This file contains common helper functions for validation,
 * sanitization, flash messages, and other utilities.
 */

declare(strict_types=1);

/**
 * Set a flash message.
 * Flash messages are displayed once and then removed.
 * 
 * @param string $type 'success' or 'error'
 * @param string $message The message to display
 * @return void
 */
function setFlashMessage(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
        'time'    => time(),
    ];
}

/**
 * Get all flash messages.
 * Flash messages are removed after being retrieved.
 * 
 * @return array Array of flash messages
 */
function getFlashMessages(): array
{
    if (!isset($_SESSION['flash_messages'])) {
        return [];
    }
    
    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    
    return $messages;
}

/**
 * Check if any flash messages exist.
 * 
 * @return bool True if flash messages exist
 */
function hasFlashMessages(): bool
{
    return isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages']);
}

/**
 * Sanitize a string for safe output (HTML escaping).
 * Prevents XSS attacks by escaping special characters.
 * 
 * @param string $input The input string to sanitize
 * @return string The sanitized string
 */
function sanitize(string $input): string
{
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate an email address.
 * 
 * @param string $email The email to validate
 * @return bool True if valid email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a URL.
 * 
 * @param string $url The URL to validate
 * @return bool True if valid URL
 */
function isValidUrl(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Sanitize and validate a string input.
 * Trims whitespace and escapes HTML entities.
 * 
 * @param string $input The raw input
 * @param int $maxLength Maximum allowed length
 * @return string|null Sanitized string or null if empty
 */
function sanitizeString(string $input, int $maxLength = 255): ?string
{
    $input = trim($input);
    
    if (empty($input)) {
        return null;
    }
    
    if (mb_strlen($input) > $maxLength) {
        $input = mb_substr($input, 0, $maxLength);
    }
    
    return sanitize($input);
}

/**
 * Validate a numeric value (integer or float).
 * 
 * @param mixed $value The value to validate
 * @return float|int The validated numeric value
 */
function validateNumeric($value): float|int
{
    if (is_numeric($value)) {
        $value = (float) $value;
        // Return as integer if there's no decimal part
        if (floor($value) === $value) {
            return (int) $value;
        }
        return $value;
    }
    return 0;
}

/**
 * Validate an integer ID (must be a positive integer).
 * 
 * @param mixed $id The ID to validate
 * @return int|null The validated ID or null if invalid
 */
function validateId($id): ?int
{
    if ($id === null || $id === '') {
        return null;
    }
    
    $id = filter_var($id, FILTER_VALIDATE_INT);
    
    if ($id === false || $id <= 0) {
        return null;
    }
    
    return $id;
}

/**
 * Validate a date string.
 * 
 * @param string $date The date to validate
 * @param string $format Expected format (default: Y-m-d)
 * @return string|null Validated date in Y-m-d format or null
 */
function validateDate(string $date, string $format = 'Y-m-d'): ?string
{
    $d = DateTime::createFromFormat($format, $date);
    return $d ? $date : null;
}

/**
 * Generate a slug from a string (for URLs).
 * 
 * @param string $text The text to slugify
 * @return string The slug
 */
function slugify(string $text): string
{
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    
    // Transliterate to ASCII
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    
    // Lowercase
    $text = strtolower($text);
    
    // Trim hyphens
    $text = trim($text, '-');
    
    return $text ?: '-';
}

/**
 * Generate a CSRF token and store in session.
 * 
 * @return string The CSRF token
 */
function generateCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token.
 * 
 * @param string $token The token to verify
 * @return bool True if token is valid
 */
function verifyCsrfToken(string $token): bool
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get all categories that are active.
 * 
 * @return array Array of category records
 */
function getAllCategories(): array
{
    return fetchAll(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC"
    );
}

/**
 * Get all active suppliers.
 * 
 * @return array Array of supplier records
 */
function getAllSuppliers(): array
{
    return fetchAll(
        "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC"
    );
}

/**
 * Format a decimal/currency value for display.
 * 
 * @param float|string $amount The amount to format
 * @param string $currency Currency symbol
 * @return string Formatted amount
 */
function formatCurrency($amount, string $currency = '$'): string
{
    return $currency . number_format((float) $amount, 2, '.', ',');
}

/**
 * Format a date for display.
 * 
 * @param string $date Date string
 * @param string $format PHP date format
 * @return string Formatted date
 */
function formatDate(string $date, string $format = 'M d, Y H:i'): string
{
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Check if a product is low stock.
 * 
 * @param int $quantity Current quantity
 * @param int $threshold Threshold value
 * @return bool True if low stock
 */
function isLowStock(int $quantity, int $threshold): bool
{
    return $quantity <= $threshold && $quantity >= 0;
}

/**
 * Check if a product is out of stock.
 * 
 * @param int $quantity Current quantity
 * @return bool True if out of stock
 */
function isOutOfStock(int $quantity): bool
{
    return $quantity <= 0;
}

/**
 * Log an audit action.
 * 
 * @param string $action The action performed
 * @param string $entityType The type of entity affected
 * @param int|null $entityId The ID of the entity
 * @param array|null $oldValues Old values before change (JSON)
 * @param array|null $newValues New values after change (JSON)
 * @return void
 */
function logAuditAction(
    string $action,
    string $entityType,
    ?int $entityId,
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    // Add IP and user agent to the new values context
    $context = [
        'ip_address' => getIpAddress(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];
    
    if ($newValues === null) {
        $newValues = $context;
    } else {
        $newValues = array_merge($newValues, $context);
    }
    
    $sql = "INSERT INTO audit_logs 
            (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
            VALUES 
            (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)";
    
    execute($sql, [
        'user_id'    => currentUserId(),
        'action'     => $action,
        'entity_type'=> $entityType,
        'entity_id'  => $entityId,
        'old_values' => $oldValues ? json_encode($oldValues) : null,
        'new_values' => $newValues ? json_encode($newValues) : null,
        'ip_address' => getIpAddress(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

/**
 * Generate a unique reference number for transactions.
 * 
 * @param string $type Transaction type prefix
 * @return string The reference number
 */
function generateReferenceNumber(string $type = 'TX'): string
{
    $prefix = match(strtolower($type)) {
        'sale' => 'SAL',
        'restock' => 'RST',
        'adjustment' => 'ADJ',
        'return' => 'RET',
        default => 'TX',
    };
    
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Validate an uploaded file.
 * 
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes
 * @param array $allowedTypes Allowed MIME types
 * @return array ['valid' => bool, 'error' => string|null, 'path' => string|null]
 */
function validateUpload(array $file, int $maxSize, array $allowedTypes): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload failed.', 'path' => null];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File is too large.', 'path' => null];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['valid' => false, 'error' => 'Invalid file type.', 'path' => null];
    }
    
    return ['valid' => true, 'error' => null, 'path' => null];
}

/**
 * Check if a request is an AJAX request.
 * 
 * @return bool True if AJAX request
 */
function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Return JSON response for AJAX requests.
 * 
 * @param mixed $data Data to return as JSON
 * @param int $statusCode HTTP status code
 * @return void
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get the CSRF token for use in forms.
 * 
 * @return string The CSRF token
 */
function csrfToken(): string
{
    return generateCsrfToken();
}

/**
 * Output a CSRF token hidden field.
 * 
 * @return string HTML input element with CSRF token
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Escape output for use in HTML.
 * 
 * @param string $value The value to escape
 * @return string The escaped value
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Truncate a string to a maximum length with ellipsis.
 * 
 * @param string $text The text to truncate
 * @param int $length Maximum length
 * @return string The truncated text
 */
function truncate(string $text, int $length = 100): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length - 3) . '...';
}
