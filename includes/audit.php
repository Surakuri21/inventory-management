<?php
/**
 * Audit Logging Helper
 * 
 * This file provides functions for logging audit trail entries
 * for critical system actions.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Class for structured audit logging.
 * This class centralizes all audit log operations.
 */
class AuditLogger
{
    /**
     * Log user creation.
     * 
     * @param int $userId The new user's ID
     * @param array $userData The new user's data
     * @return void
     */
    public static function logUserCreation(int $userId, array $userData): void
    {
        self::log('create', 'users', $userId, $userData);
    }
    
    /**
     * Log user update.
     * 
     * @param int $userId The user's ID
     * @param array|null $oldData Previous user data
     * @param array $newData Updated user data
     * @return void
     */
    public static function logUserUpdate(int $userId, ?array $oldData, array $newData): void
    {
        self::log('update', 'users', $userId, $oldData, $newData);
    }
    
    /**
     * Log user deletion.
     * 
     * @param int $userId The deleted user's ID
     * @param array $userData The deleted user's data
     * @return void
     */
    public static function logUserDeletion(int $userId, array $userData): void
    {
        self::log('delete', 'users', $userId, $userData);
    }
    
    /**
     * Log product creation.
     * 
     * @param int $productId The new product's ID
     * @param array $productData The new product's data
     * @return void
     */
    public static function logProductCreation(int $productId, array $productData): void
    {
        self::log('create', 'products', $productId, $productData);
    }
    
    /**
     * Log product update.
     * 
     * @param int $productId The product's ID
     * @param array|null $oldData Previous product data
     * @param array $newData Updated product data
     * @return void
     */
    public static function logProductUpdate(int $productId, ?array $oldData, array $newData): void
    {
        self::log('update', 'products', $productId, $oldData, $newData);
    }
    
    /**
     * Log product deletion.
     * 
     * @param int $productId The deleted product's ID
     * @param array $productData The deleted product's data
     * @return void
     */
    public static function logProductDeletion(int $productId, array $productData): void
    {
        self::log('delete', 'products', $productId, $productData);
    }
    
    /**
     * Log category creation.
     */
    public static function logCategoryCreation(int $categoryId, array $categoryData): void
    {
        self::log('create', 'categories', $categoryId, $categoryData);
    }
    
    /**
     * Log category update.
     */
    public static function logCategoryUpdate(int $categoryId, ?array $oldData, array $newData): void
    {
        self::log('update', 'categories', $categoryId, $oldData, $newData);
    }
    
    /**
     * Log category deletion.
     */
    public static function logCategoryDeletion(int $categoryId, array $categoryData): void
    {
        self::log('delete', 'categories', $categoryId, $categoryData);
    }
    
    /**
     * Log supplier creation.
     */
    public static function logSupplierCreation(int $supplierId, array $supplierData): void
    {
        self::log('create', 'suppliers', $supplierId, $supplierData);
    }
    
    /**
     * Log supplier update.
     */
    public static function logSupplierUpdate(int $supplierId, ?array $oldData, array $newData): void
    {
        self::log('update', 'suppliers', $supplierId, $oldData, $newData);
    }
    
    /**
     * Log supplier deletion.
     */
    public static function logSupplierDeletion(int $supplierId, array $supplierData): void
    {
        self::log('delete', 'suppliers', $supplierId, $supplierData);
    }
    
    /**
     * Log stock transaction.
     * 
     * @param int $productId The product ID
     * @param int $quantity The quantity changed
     * @param string $type Transaction type (sale, restock, adjustment, return)
     * @param int $oldQuantity Previous stock level
     * @param int $newQuantity New stock level
     * @return void
     */
    public static function logStockTransaction(
        int $productId,
        int $quantity,
        string $type,
        int $oldQuantity,
        int $newQuantity,
        ?float $unitPrice = null,
        ?string $referenceNumber = null
    ): void {
        $transactionData = [
            'product_id'       => $productId,
            'quantity'         => $quantity,
            'type'             => $type,
            'unit_price'       => $unitPrice,
            'reference_number' => $referenceNumber,
            'old_quantity'     => $oldQuantity,
            'new_quantity'     => $newQuantity,
            'change'           => $newQuantity - $oldQuantity,
        ];
        
        // Log as a transaction action in the audit trail
        self::log('stock_' . $type, 'transactions', null, $transactionData);
    }
    
    /**
     * Core audit logging function.
     * 
     * @param string $action The action performed (create, update, delete, login, logout)
     * @param string $entityType The type of entity (users, products, categories, etc.)
     * @param int|null $entityId The ID of the entity
     * @param array|null $oldValues Old values before change
     * @param array|null $newValues New values after change
     * @return void
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        // Add context information to new values
        $context = [
            'ip_address' => getIpAddress(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'timestamp'   => date('Y-m-d H:i:s'),
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
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => getIpAddress(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }
    
    /**
     * Get recent audit logs with pagination.
     * 
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of audit log records
     */
    public static function getRecentLogs(int $limit = 50, int $offset = 0): array
    {
        return fetchAll(
            "SELECT al.*, u.username, u.full_name 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC 
             LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
    }
    
    /**
     * Search audit logs.
     * 
     * @param array $filters Filters to apply
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @return array Array of audit log records
     */
    public static function searchLogs(array $filters, int $limit = 50, int $offset = 0): array
    {
        $where = [];
        $params = [];
        
        if (!empty($filters['action'])) {
            $where[] = "al.action = :action";
            $params['action'] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "al.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "al.created_at >= :from_date";
            $params['from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "al.created_at <= :to_date";
            $params['to_date'] = $filters['to_date'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT al.*, u.username, u.full_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                $whereClause
                ORDER BY al.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return fetchAll($sql, $params);
    }
}
