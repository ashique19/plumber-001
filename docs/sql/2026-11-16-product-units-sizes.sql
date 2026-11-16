-- Product units, sizes, SKU counter, product FKs, ZATCA VAT category, archive flags.
-- Idempotent for phpMyAdmin / cPanel. Safe to re-run.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `product_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name_en` VARCHAR(255) NOT NULL,
    `name_ar` VARCHAR(255) NOT NULL,
    `name_bn` VARCHAR(160) NOT NULL,
    `code` VARCHAR(16) NOT NULL,
    `ubl_unit_code` VARCHAR(16) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `product_units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_sizes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `measurement_type` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_sku_counters` (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `last_value` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products columns (guarded)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_unit_id');
SET @ddl := IF(@col = 0, 'ALTER TABLE `products` ADD COLUMN `product_unit_id` BIGINT UNSIGNED NULL AFTER `product_category_id`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_size_id');
SET @ddl := IF(@col = 0, 'ALTER TABLE `products` ADD COLUMN `product_size_id` BIGINT UNSIGNED NULL AFTER `product_unit_id`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'vat_zatca_category');
SET @ddl := IF(@col = 0, 'ALTER TABLE `products` ADD COLUMN `vat_zatca_category` CHAR(1) NOT NULL DEFAULT ''S'' AFTER `vat_rate`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'archived_at');
SET @ddl := IF(@col = 0, 'ALTER TABLE `products` ADD COLUMN `archived_at` TIMESTAMP NULL AFTER `updated_at`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'name_i18n_review');
SET @ddl := IF(@col = 0, 'ALTER TABLE `products` ADD COLUMN `name_i18n_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `name_bn`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Foreign keys (best-effort)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'products_product_unit_id_foreign');
SET @ddl := IF(@fk = 0, 'ALTER TABLE `products` ADD CONSTRAINT `products_product_unit_id_foreign` FOREIGN KEY (`product_unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'products_product_size_id_foreign');
SET @ddl := IF(@fk = 0, 'ALTER TABLE `products` ADD CONSTRAINT `products_product_size_id_foreign` FOREIGN KEY (`product_size_id`) REFERENCES `product_sizes` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO `product_units` (`code`, `name_en`, `name_ar`, `name_bn`, `ubl_unit_code`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('PCS', 'Piece', 'قطعة', 'পিস', 'PCE', 1, NOW(), NOW()),
    ('M', 'Meter', 'متر', 'মিটার', 'MTR', 1, NOW(), NOW()),
    ('BOX', 'Box', 'صندوق', 'বাক্স', 'BX', 1, NOW(), NOW()),
    ('ROLL', 'Roll', 'لفة', 'রোল', 'RO', 1, NOW(), NOW()),
    ('KG', 'Kilogram', 'كيلوغرام', 'কিলোগ্রাম', 'KGM', 1, NOW(), NOW()),
    ('L', 'Liter', 'لتر', 'লিটার', 'LTR', 1, NOW(), NOW()),
    ('SET', 'Set', 'مجموعة', 'সেট', 'SET', 1, NOW(), NOW()),
    ('HR', 'Hour', 'ساعة', 'ঘণ্টা', 'HUR', 1, NOW(), NOW()),
    ('JOB', 'Job', 'مهمة', 'কাজ', 'E48', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name_en` = VALUES(`name_en`),
    `name_ar` = VALUES(`name_ar`),
    `name_bn` = VALUES(`name_bn`),
    `ubl_unit_code` = VALUES(`ubl_unit_code`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();

INSERT INTO `product_sizes` (`name`, `measurement_type`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('1/2 Inch', 'Diameter', 1, NOW(), NOW()),
    ('3/4 Inch', 'Diameter', 1, NOW(), NOW()),
    ('1 Inch', 'Diameter', 1, NOW(), NOW()),
    ('1 1/4 Inch', 'Diameter', 1, NOW(), NOW()),
    ('1 1/2 Inch', 'Diameter', 1, NOW(), NOW()),
    ('2 Inch', 'Diameter', 1, NOW(), NOW()),
    ('3 Inch', 'Diameter', 1, NOW(), NOW()),
    ('4 Inch', 'Diameter', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `measurement_type` = VALUES(`measurement_type`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();

SET @pcs_id := (SELECT `id` FROM `product_units` WHERE `code` = 'PCS' LIMIT 1);

UPDATE `products`
SET `name_ar` = `name_en`, `name_i18n_review` = 1
WHERE (`name_ar` IS NULL OR TRIM(`name_ar`) = '') AND `name_en` IS NOT NULL AND TRIM(`name_en`) <> '';

UPDATE `products`
SET `name_bn` = `name_en`, `name_i18n_review` = 1
WHERE (`name_bn` IS NULL OR TRIM(`name_bn`) = '') AND `name_en` IS NOT NULL AND TRIM(`name_en`) <> '';

UPDATE `products`
SET `product_unit_id` = @pcs_id
WHERE `product_unit_id` IS NULL AND @pcs_id IS NOT NULL;

UPDATE `products`
SET `vat_zatca_category` = 'S'
WHERE `vat_zatca_category` IS NULL OR TRIM(`vat_zatca_category`) = '';

INSERT INTO `product_sku_counters` (`key`, `last_value`)
SELECT 'PRD', COALESCE(MAX(CAST(SUBSTRING(`sku`, 5) AS UNSIGNED)), 0)
FROM `products`
WHERE `sku` REGEXP '^PRD-[0-9]+$'
ON DUPLICATE KEY UPDATE `last_value` = GREATEST(`product_sku_counters`.`last_value`, VALUES(`last_value`));