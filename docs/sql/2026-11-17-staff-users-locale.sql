-- Per-staff UI locale (en / bn / ar). Idempotent on live MySQL.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff_users'
      AND COLUMN_NAME = 'locale'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `staff_users` ADD COLUMN `locale` VARCHAR(8) NOT NULL DEFAULT ''bn'' AFTER `is_active`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `staff_users` SET `locale` = 'bn' WHERE `locale` IS NULL OR `locale` = '';