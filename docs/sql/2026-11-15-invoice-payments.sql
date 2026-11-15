-- Invoice payment ledger (partial payments, retention). Safe to re-run in phpMyAdmin.
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