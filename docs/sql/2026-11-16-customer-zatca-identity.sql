-- Customer ZATCA identity columns + backfill from legacy name/profile JSON.
-- Idempotent for live MySQL (cPanel). Run manually; app does not auto-migrate on server.
SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'legal_name_ar'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `legal_name_ar` VARCHAR(160) NULL AFTER `name`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'legal_name_en'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `legal_name_en` VARCHAR(160) NULL AFTER `legal_name_ar`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'vat_registered'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `vat_registered` TINYINT(1) NOT NULL DEFAULT 0 AFTER `vat_number`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'alt_id_type'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `alt_id_type` VARCHAR(8) NULL AFTER `vat_registered`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'alt_id_number'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `alt_id_number` VARCHAR(64) NULL AFTER `alt_id_type`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'country_code'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `country_code` CHAR(2) NOT NULL DEFAULT \'SA\' AFTER `alt_id_number`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'street_name'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `street_name` VARCHAR(120) NULL AFTER `country_code`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'city'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `city` VARCHAR(80) NULL AFTER `street_name`', 'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `customers` SET
  `legal_name_en` = COALESCE(NULLIF(TRIM(`legal_name_en`), ''), TRIM(`name`)),
  `legal_name_ar` = COALESCE(NULLIF(TRIM(`legal_name_ar`), ''), NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.name_ar'))), '')),
  `vat_registered` = CASE WHEN NULLIF(TRIM(`vat_number`), '') IS NOT NULL THEN 1 ELSE 0 END,
  `country_code` = COALESCE(NULLIF(TRIM(`country_code`), ''), NULLIF(UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.country')))), 'SA')),
  `street_name` = COALESCE(NULLIF(TRIM(`street_name`), ''), NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.national_address.street'))), '')),
  `city` = COALESCE(NULLIF(TRIM(`city`), ''), NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.national_address.city'))), '')),
  `alt_id_type` = CASE
    WHEN NULLIF(TRIM(`vat_number`), '') IS NULL AND NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.cr_number'))), '') IS NOT NULL
      THEN COALESCE(NULLIF(TRIM(`alt_id_type`), ''), 'CRN')
    ELSE `alt_id_type`
  END,
  `alt_id_number` = CASE
    WHEN NULLIF(TRIM(`vat_number`), '') IS NULL
      THEN COALESCE(NULLIF(TRIM(`alt_id_number`), ''), NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`profile`, '$.cr_number'))), ''))
    ELSE `alt_id_number`
  END
WHERE `id` > 0;