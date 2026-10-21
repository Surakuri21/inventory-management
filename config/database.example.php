<?php
/**
 * Database Configuration
 * 
 * Copy this file to database.php and update with your credentials.
 * This file contains the database connection parameters.
 */

declare(strict_types=1);

return [
    // Database host (use 127.0.0.1 or localhost)
    'host'     => '127.0.0.1',
    
    // Database name
    'dbname'   => 'inventory_db',
    
    // Database username
    'username' => 'root',
    
    // Database password
    'password' => '',
    
    // Character set
    'charset'  => 'utf8mb4',
    
    // PDO options for security and performance
    'options'  => [
        // Throw exceptions on errors
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        
        // Fetch associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Disable emulated prepares to use real prepared statements
        PDO::ATTR_EMULATE_PREPARES   => false,
        
        // Make the connection persistent for performance
        PDO::ATTR_PERSISTENT         => false,
    ],
];