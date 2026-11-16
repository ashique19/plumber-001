-- Optional customer post box (standard invoice buyer print + profile national_address.post_box).
-- Idempotent for live MySQL (cPanel). Run manually; app does not auto-migrate on server.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'post_box'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `post_box` VARCHAR(32) NULL AFTER `vat_number`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;