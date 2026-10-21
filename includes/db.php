<?php
/**
 * Database Connection
 * 
 * This file provides a PDO connection singleton for the application.
 * All database interactions should use this connection to ensure
 * consistent use of PDO with prepared statements.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Get a PDO database connection instance.
 * Uses a singleton pattern to avoid multiple connections.
 * 
 * @return PDO The PDO connection instance
 * @throws PDOException If connection fails
 */
function getPDO(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        
        if (!is_array($config)) {
            throw new RuntimeException('Critical Infrastructure Failure: database configuration did not return an array.');
        }
        
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['dbname'],
            $config['charset']
        );
        
        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // Log the error and throw a generic message
            // In production, log to a file instead of displaying
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed. Please check your configuration.');
        }
    }
    
    return $pdo;
}

/**
 * Execute a SELECT query and return all results.
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params Parameters for the prepared statement
 * @return array Array of result rows
 */
function fetchAll(string $sql, array $params = []): array
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a SELECT query and return a single row.
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params Parameters for the prepared statement
 * @return array|false Single result row or false if no match
 */
function fetchOne(string $sql, array $params = [])
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Execute a SELECT query and return a single column value.
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params Parameters for the prepared statement
 * @return string|false Single value or false if no match
 */
function fetchColumn(string $sql, array $params = [])
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Execute an INSERT query and return the last inserted ID.
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params Parameters for the prepared statement
 * @return string The last inserted ID
 */
function insert(string $sql, array $params = []): string
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return (string) getPDO()->lastInsertId();
}

/**
 * Execute an UPDATE or DELETE query and return affected rows count.
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params Parameters for the prepared statement
 * @return int Number of affected rows
 */
function execute(string $sql, array $params = []): int
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->rowCount();
}

/**
 * Start a database transaction.
 * 
 * @return bool True on success
 */
/**
 * Execute a SELECT query with a row lock (FOR UPDATE / FOR SHARE) on the
 * SAME PDO instance used by subsequent writes. Locks are per-connection, so
 * the $pdo argument MUST be the getPDO() singleton — never a separate connection.
 *
 * @param PDO    $pdo    The PDO instance (must be getPDO() to share the connection)
 * @param string $sql    The SQL query — append `FOR UPDATE` or `FOR SHARE` inline
 * @param array  $params Parameters for the prepared statement
 * @return array|false   Single result row or false if no match
 */
function fetchOneLocked(PDO $pdo, string $sql, array $params = []): array|false
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Start a database transaction.
 *
 * @return bool True on success
 */
function beginTransaction(): bool
{
    return getPDO()->beginTransaction();
}

/**
 * Commit a database transaction.
 * 
 * @return bool True on success
 */
function commitTransaction(): bool
{
    return getPDO()->commit();
}

/**
 * Rollback a database transaction.
 * 
 * @return bool True on success
 */
function rollbackTransaction(): bool
{
    return getPDO()->rollBack();
}
