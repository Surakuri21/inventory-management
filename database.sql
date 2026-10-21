-- PHP & MySQL Inventory Management System
-- Database Schema (3rd Normal Form)
-- Engine: InnoDB | Charset: utf8mb4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'standard') NOT NULL DEFAULT 'standard',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_is_active` (`is_active`),
  KEY `idx_users_must_change` (`must_change_password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_name` (`name`),
  KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `suppliers`
-- --------------------------------------------------------
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suppliers_name` (`name`),
  KEY `idx_suppliers_is_active` (`is_active`),
  KEY `idx_suppliers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(50) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT NOT NULL DEFAULT 10,
  `unit_of_measure` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_supplier_id` (`supplier_id`),
  KEY `idx_products_is_active` (`is_active`),
  KEY `idx_products_low_stock` (`quantity`, `low_stock_threshold`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `transactions`
-- --------------------------------------------------------
CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('sale', 'restock', 'adjustment', 'return') NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `reference_number` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `idempotency_key` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transactions_idempotency` (`idempotency_key`),
  KEY `idx_transactions_product_id` (`product_id`),
  KEY `idx_transactions_user_id` (`user_id`),
  KEY `idx_transactions_type` (`type`),
  KEY `idx_transactions_created_at` (`created_at`),
  KEY `idx_transactions_reference` (`reference_number`),
  CONSTRAINT `fk_transactions_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default Data Inserts
-- --------------------------------------------------------

-- Default Admin User (password: ChangeMeNow!2026 - CHANGE IMMEDIATELY ON FIRST LOGIN!)
-- Note: The seed hash below is a bcrypt (cost=10) hash of `ChangeMeNow!2026`.
-- must_change_password=1 forces a password change on first login.
-- After deploying, change the admin password immediately (see `security/update_admin_password.sql`).
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `must_change_password`) VALUES
('admin', 'admin@example.com', '$2b$10$1r9ZEpRh9Y3YhVqj0U80meSpx2jd4bZTEucaav74HBtjzG/E2erwG', 'System Administrator', 'admin', 1, 1);

-- Default Categories
INSERT INTO `categories` (`name`, `description`) VALUES
('Electronics', 'Electronic devices and components'),
('Office Supplies', 'General office and stationery items'),
('Furniture', 'Office and warehouse furniture'),
('Tools & Equipment', 'Hand tools, power tools, and equipment'),
('Consumables', 'Disposable and consumable items');

-- Default Suppliers
INSERT INTO `suppliers` (`name`, `contact_person`, `email`, `phone`, `address`, `city`, `country`) VALUES
('TechSupply Co.', 'John Smith', 'john@techsupply.com', '+1-555-0101', '123 Tech Park', 'San Francisco', 'USA'),
('OfficeWorld Inc.', 'Jane Doe', 'jane@officeworld.com', '+1-555-0102', '456 Business Ave', 'New York', 'USA'),
('Global Furnishings', 'Bob Wilson', 'bob@globalfurnish.com', '+1-555-0103', '789 Industrial Blvd', 'Chicago', 'USA');

COMMIT;