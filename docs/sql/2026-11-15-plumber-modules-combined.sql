-- Idempotent rollup for plumber admin modules (PRs #25–#28 + expenses).
-- Safe on a live database that already applied 2026-11-14 patches.
-- Re-running is a no-op: CREATE IF NOT EXISTS and column/index guards only.
SET NAMES utf8mb4;

-- === #25 Quotations ===
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `quotations`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `quotation_number` VARCHAR(64) NOT NULL,
  `invoice_mode` VARCHAR(16) NOT NULL DEFAULT 'simplified',
  `validity_until` DATE NOT NULL,
  `payload` JSON NOT NULL,
  `converted_invoice_id` BIGINT UNSIGNED,
  `created_by_staff_user_id` BIGINT UNSIGNED,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`converted_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quotations'
      AND INDEX_NAME = 'quotations_quotation_number_unique'
);

SET @ddl := IF(
    @idx_exists = 0,
    'CREATE UNIQUE INDEX `quotations_quotation_number_unique` ON `quotations` (`quotation_number`)',
    'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quotations'
      AND INDEX_NAME = 'quotations_validity_until_index'
);

SET @ddl := IF(
    @idx_exists = 0,
    'CREATE INDEX `quotations_validity_until_index` ON `quotations` (`validity_until`)',
    'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- === #26 Product stock, purchases, movements ===
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
-- === #27 Invoice payments ===
SET NAMES utf8mb4;

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoice_payments'
);

SET @ddl := IF(
    @table_exists = 0,
    'CREATE TABLE `invoice_payments` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `invoice_id` BIGINT UNSIGNED NOT NULL,
        `customer_id` BIGINT UNSIGNED NULL,
        `paid_on` DATE NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `method` VARCHAR(32) NOT NULL,
        `reference` VARCHAR(128) NULL,
        `for_retention` TINYINT(1) NOT NULL DEFAULT 0,
        `recorded_by_id` BIGINT UNSIGNED NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        INDEX `invoice_payments_invoice_id_paid_on_index` (`invoice_id`, `paid_on`),
        CONSTRAINT `invoice_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
        CONSTRAINT `invoice_payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
        CONSTRAINT `invoice_payments_recorded_by_id_foreign` FOREIGN KEY (`recorded_by_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- === PR F Business expenses ===
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `business_expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `spent_on` DATE NOT NULL,
  `category` VARCHAR(64) NOT NULL,
  `vendor` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `amount_ex_vat` DECIMAL(12,2) NOT NULL,
  `recorded_by_staff_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  CONSTRAINT `business_expenses_branch_id_foreign`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_expenses_recorded_by_staff_user_id_foreign`
    FOREIGN KEY (`recorded_by_staff_user_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_expenses'
      AND INDEX_NAME = 'business_expenses_spent_on_category_index'
);

SET @ddl := IF(
    @idx_exists = 0,
    'CREATE INDEX `business_expenses_spent_on_category_index` ON `business_expenses` (`spent_on`, `category`)',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'business_expenses'
      AND INDEX_NAME = 'business_expenses_branch_id_spent_on_index'
);

SET @ddl := IF(
    @idx_exists = 0,
    'CREATE INDEX `business_expenses_branch_id_spent_on_index` ON `business_expenses` (`branch_id`, `spent_on`)',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;