-- Quotations module tables. Safe to re-run in phpMyAdmin.
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