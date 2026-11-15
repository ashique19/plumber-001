-- Product stock, suppliers, purchases, and stock movement log. Safe to re-run in phpMyAdmin.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'track_stock'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `track_stock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `show_on_menu`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'stock_quantity'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `stock_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER `track_stock`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'low_stock_threshold'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `low_stock_threshold` DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER `stock_quantity`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'cost_ex_vat'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `cost_ex_vat` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `low_stock_threshold`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `suppliers`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `name` VARCHAR(160) NOT NULL,
  `vat_number` VARCHAR(32),
  `phone` VARCHAR(32),
  `created_at` DATETIME,
  `updated_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `supplier_id` BIGINT UNSIGNED NOT NULL,
  `purchase_number` VARCHAR(64) NOT NULL,
  `purchased_at` DATETIME NOT NULL,
  `notes` TEXT,
  `created_by_staff_user_id` BIGINT UNSIGNED,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by_staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'purchases'
      AND INDEX_NAME = 'purchases_purchase_number_unique'
);

SET @ddl := IF(
    @idx_exists = 0,
    'CREATE UNIQUE INDEX `purchases_purchase_number_unique` ON `purchases` (`purchase_number`)',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `purchase_lines`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `unit_cost_ex_vat` DECIMAL(12,2) NOT NULL,
  `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 15,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_stock_movements`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `quantity_delta` DECIMAL(12,3) NOT NULL,
  `unit_cost_ex_vat` DECIMAL(12,2),
  `purchase_id` BIGINT UNSIGNED,
  `invoice_id` BIGINT UNSIGNED,
  `user_id` BIGINT UNSIGNED,
  `reason` VARCHAR(160),
  `created_at` DATETIME,
  `updated_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;