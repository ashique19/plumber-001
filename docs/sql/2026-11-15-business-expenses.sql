-- Business operating expenses. Safe to re-run in phpMyAdmin.
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