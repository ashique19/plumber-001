-- Supplier profile columns (name_ar, contact, email, CR, address, notes, active).
-- Idempotent: safe after 2026-11-15-plumber-modules-combined.sql on live MySQL.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'name_ar'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `name_ar` VARCHAR(160) NULL AFTER `name`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'contact_person'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `contact_person` VARCHAR(120) NULL AFTER `name_ar`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'email'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `email` VARCHAR(120) NULL AFTER `phone`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'cr_number'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `cr_number` VARCHAR(32) NULL AFTER `vat_number`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'address'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `address` TEXT NULL AFTER `cr_number`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'city'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `city` VARCHAR(80) NULL AFTER `address`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'notes'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `notes` TEXT NULL AFTER `city`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'suppliers'
      AND COLUMN_NAME = 'is_active'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `suppliers` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notes`',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;