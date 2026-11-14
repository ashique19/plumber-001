-- Invoice due date (payment terms) column. Safe to re-run in phpMyAdmin.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND COLUMN_NAME = 'due_date'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `invoices` ADD COLUMN `due_date` DATE NULL AFTER `issued_at`',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;