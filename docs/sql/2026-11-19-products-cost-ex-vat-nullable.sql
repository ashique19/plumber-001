-- Allow unknown purchase cost on products (optional in catalog / quick-add modals).
-- Idempotent for phpMyAdmin / cPanel. Safe to re-run.
SET NAMES utf8mb4;

SET @col := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'cost_ex_vat'
    LIMIT 1
);
SET @ddl := IF(
    @col = 'NO',
    'ALTER TABLE `products` MODIFY COLUMN `cost_ex_vat` DECIMAL(12,2) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;