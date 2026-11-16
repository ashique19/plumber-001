<?php

declare(strict_types=1);

$schemaPath = __DIR__.'/../database/schema/sqlite-schema.sql';
$outPath = __DIR__.'/../docs/sql/install.sql';

/**
 * @return non-empty-string
 */
function sqliteSchemaToMysql(string $sql): string
{
    $sql = preg_replace('/^INSERT INTO migrations VALUES.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\n{3,}/', "\n\n", trim($sql)) ?? $sql;

    $sql = preg_replace(
        '/"([A-Za-z_][A-Za-z0-9_]*)"/',
        '`$1`',
        $sql
    ) ?? $sql;

    $sql = preg_replace(
        '/`(\w+)`\s+integer\s+primary\s+key\s+autoincrement\s+not\s+null/i',
        '`$1` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
        $sql
    ) ?? $sql;

    $sql = preg_replace('/`(\w+)`\s+integer\s+not\s+null/i', '`$1` BIGINT NOT NULL', $sql) ?? $sql;
    $sql = preg_replace('/`(\w+)`\s+integer\b/i', '`$1` BIGINT', $sql) ?? $sql;
    $sql = preg_replace('/`(\w+_id)`\s+BIGINT\b/i', '`$1` BIGINT UNSIGNED', $sql) ?? $sql;

    $sql = preg_replace('/\bvarchar\s+not\s+null\b/', 'VARCHAR(255) NOT NULL', $sql) ?? $sql;
    $sql = preg_replace('/\bvarchar\b/', 'VARCHAR(255)', $sql) ?? $sql;

    $sql = preg_replace('/\btext\s+not\s+null\b/i', 'TEXT NOT NULL', $sql) ?? $sql;
    $sql = preg_replace('/\btext\b/i', 'TEXT', $sql) ?? $sql;

    $sql = preg_replace(
        '/`numeric`\s+not\s+null\s+default\s+\'([^\']+)\'/i',
        'DECIMAL(12,2) NOT NULL DEFAULT \'$1\'',
        $sql
    ) ?? $sql;
    $sql = preg_replace('/`(\w+)`\s+numeric\s+not\s+null/i', '`$1` DECIMAL(12,2) NOT NULL', $sql) ?? $sql;
    $sql = preg_replace('/`(\w+)`\s+numeric\b/i', '`$1` DECIMAL(12,2)', $sql) ?? $sql;

    $sql = preg_replace(
        '/(`business_settings`[\s\S]*?)`settings`\s+text\s+not\s+null/i',
        '$1`settings` JSON NOT NULL',
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace(
        '/(`branches`[\s\S]*?)`settings`\s+text/i',
        '$1`settings` JSON',
        $sql,
        1
    ) ?? $sql;

    $sql = applyMysqlColumnOverrides($sql);

    return ensureMysqlTableCharsets($sql);
}

/**
 * @return non-empty-string
 */
function ensureMysqlTableCharsets(string $sql): string
{
    return preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `[^`]+`[\s\S]*?\);/i',
        static function (array $m): string {
            $stmt = rtrim($m[0], ";\n\r ");
            if (str_contains($stmt, 'ENGINE=InnoDB') && str_contains($stmt, 'utf8mb4_unicode_ci')) {
                return $stmt.';';
            }
            if (str_contains($stmt, 'ENGINE=InnoDB')) {
                return preg_replace(
                    '/\s+ENGINE=InnoDB\b[^;]*/i',
                    ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                    $stmt,
                    1
                ).';';
            }

            return $stmt.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        },
        $sql
    ) ?? $sql;
}

function applyMysqlColumnOverrides(string $sql): string
{
    $jsonTables = [
        'customers' => ['profile'],
        'invoices' => ['payload'],
        'invoice_audit_logs' => ['context'],
        'pos_orders' => ['line_items', 'metadata'],
        'loyalty_transactions' => ['metadata'],
        'shifts' => ['metadata'],
        'business_settings_audit_logs' => ['snapshot'],
    ];

    foreach ($jsonTables as $table => $columns) {
        foreach ($columns as $column) {
            $sql = preg_replace(
                "/(CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?`{$column}`)\s+TEXT/i",
                '$1 JSON',
                $sql,
                1
            ) ?? $sql;
        }
    }

    $decimal12_3Tables = [
        'products' => ['stock_quantity', 'low_stock_threshold'],
        'ingredients' => ['quantity_on_hand', 'reorder_level', 'low_stock_threshold'],
        'inventory_movements' => ['quantity_delta'],
        'recipe_ingredients' => ['quantity'],
        'product_ingredients' => ['quantity_per_unit'],
    ];

    foreach ($decimal12_3Tables as $table => $columns) {
        $sql = preg_replace_callback(
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?\);/i",
            static function (array $m) use ($columns): string {
                $block = $m[0];
                foreach ($columns as $column) {
                    $block = preg_replace(
                        "/(`{$column}`)\s+DECIMAL\(12,2\)/i",
                        '$1 DECIMAL(12,3)',
                        $block,
                        1
                    ) ?? $block;
                }

                return $block;
            },
            $sql,
            1
        ) ?? $sql;
    }

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `ingredients`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`name_en`\s+VARCHAR\(255\)/', '`name_en` VARCHAR(160) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`name_ar`\s+VARCHAR\(255\)/', '`name_ar` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`name_bn`\s+VARCHAR\(255\)/', '`name_bn` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`unit`\s+VARCHAR\(255\)/', '`unit` VARCHAR(16) NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `inventory_movements`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`type`\s+VARCHAR\(255\)/', '`type` VARCHAR(32) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`reason`\s+VARCHAR\(255\)/', '`reason` VARCHAR(32)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `customers`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`legal_name_ar`\s+VARCHAR\(255\)/', '`legal_name_ar` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`legal_name_en`\s+VARCHAR\(255\)/', '`legal_name_en` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`vat_number`\s+VARCHAR\(255\)/', '`vat_number` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`alt_id_type`\s+VARCHAR\(255\)/', '`alt_id_type` VARCHAR(8)', $block, 1) ?? $block;
            $block = preg_replace('/`alt_id_number`\s+VARCHAR\(255\)/', '`alt_id_number` VARCHAR(64)', $block, 1) ?? $block;
            $block = preg_replace(
                '/`country_code`\s+VARCHAR\(255\)\s+NOT NULL default \'SA\'/',
                '`country_code` CHAR(2) NOT NULL default \'SA\'',
                $block,
                1
            ) ?? $block;
            $block = preg_replace('/`street_name`\s+VARCHAR\(255\)/', '`street_name` VARCHAR(120)', $block, 1) ?? $block;
            $block = preg_replace('/`city`\s+VARCHAR\(255\)/', '`city` VARCHAR(80)', $block, 1) ?? $block;
            $block = preg_replace('/`referral_code`\s+VARCHAR\(255\)/', '`referral_code` VARCHAR(32)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `suppliers`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`name`\s+VARCHAR\(255\) NOT NULL/', '`name` VARCHAR(160) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`name_ar`\s+VARCHAR\(255\)/', '`name_ar` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`contact_person`\s+VARCHAR\(255\)/', '`contact_person` VARCHAR(120)', $block, 1) ?? $block;
            $block = preg_replace('/`phone`\s+VARCHAR\(255\)/', '`phone` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`email`\s+VARCHAR\(255\)/', '`email` VARCHAR(120)', $block, 1) ?? $block;
            $block = preg_replace('/`vat_number`\s+VARCHAR\(255\)/', '`vat_number` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`cr_number`\s+VARCHAR\(255\)/', '`cr_number` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`city`\s+VARCHAR\(255\)/', '`city` VARCHAR(80)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `product_categories`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`name_bn`\s+VARCHAR\(255\)/', '`name_bn` VARCHAR(120)', $block, 1) ?? $block;
            $block = preg_replace('/`icon_key`\s+VARCHAR\(255\)/', '`icon_key` VARCHAR(32)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `products`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`item_type`\s+VARCHAR\(255\)/', '`item_type` VARCHAR(16)', $block, 1) ?? $block;
            $block = preg_replace('/`name_bn`\s+VARCHAR\(255\)/', '`name_bn` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace(
                '/`vat_zatca_category`\s+VARCHAR\(255\)\s+NOT NULL default \'S\'/',
                '`vat_zatca_category` CHAR(1) NOT NULL default \'S\'',
                $block,
                1
            ) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `product_units`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`name_bn`\s+VARCHAR\(255\)/', '`name_bn` VARCHAR(160)', $block, 1) ?? $block;
            $block = preg_replace('/`code`\s+VARCHAR\(255\)/', '`code` VARCHAR(16)', $block, 1) ?? $block;
            $block = preg_replace('/`ubl_unit_code`\s+VARCHAR\(255\)/', '`ubl_unit_code` VARCHAR(16)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `product_sku_counters`[\s\S]*?\);/i',
        static function (array $m): string {
            return preg_replace(
                '/`last_value`\s+BIGINT\s+NOT NULL default \'0\'/',
                '`last_value` INT UNSIGNED NOT NULL default \'0\'',
                $m[0],
                1
            ) ?? $m[0];
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `branches`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`code`\s+VARCHAR\(255\)/', '`code` VARCHAR(32) NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `sessions`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`ip_address`\s+VARCHAR\(255\)/', '`ip_address` VARCHAR(45)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `staff_users`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`remember_token`\s+VARCHAR\(255\)/', '`remember_token` VARCHAR(100)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `invoices`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`document_type`\s+VARCHAR\(255\)/', '`document_type` VARCHAR(32) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`status`\s+VARCHAR\(255\)/', '`status` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`source_type`\s+VARCHAR\(255\)/', '`source_type` VARCHAR(64)', $block, 1) ?? $block;
            $block = preg_replace('/`invoice_hash`\s+VARCHAR\(255\)/', '`invoice_hash` VARCHAR(512)', $block, 1) ?? $block;
            $block = preg_replace('/`previous_invoice_hash`\s+VARCHAR\(255\)/', '`previous_invoice_hash` VARCHAR(512)', $block, 1) ?? $block;
            $block = preg_replace('/`signed_xml`\s+TEXT/', '`signed_xml` LONGTEXT', $block, 1) ?? $block;
            $block = preg_replace('/`icv`\s+BIGINT\b/', '`icv` BIGINT UNSIGNED', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `invoice_payments`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`method`\s+VARCHAR\(255\)(\s+NOT\s+NULL)?/i', '`method` VARCHAR(32) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`reference`\s+VARCHAR\(255\)/', '`reference` VARCHAR(128)', $block, 1) ?? $block;
            $block = preg_replace('/`paid_on`\s+date\s+not\s+null/i', '`paid_on` DATE NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`for_retention`\s+tinyint\(1\)\s+not\s+null/i', '`for_retention` TINYINT(1) NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `pos_orders`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`status`\s+VARCHAR\(255\)/', '`status` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`payment_method`\s+VARCHAR\(255\)/', '`payment_method` VARCHAR(32)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `loyalty_transactions`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`type`\s+VARCHAR\(255\)/', '`type` VARCHAR(32) NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`points_delta`\s+BIGINT/', '`points_delta` INT NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `sessions`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`payload`\s+TEXT/', '`payload` LONGTEXT NOT NULL', $block, 1) ?? $block;
            $block = preg_replace('/`last_activity`\s+BIGINT/', '`last_activity` INT NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `customers`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`vat_number`\s+VARCHAR\(255\)/', '`vat_number` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`referral_code`\s+VARCHAR\(255\)/', '`referral_code` VARCHAR(32)', $block, 1) ?? $block;
            $block = preg_replace('/`points_balance`\s+BIGINT/', '`points_balance` BIGINT UNSIGNED', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    foreach (['product_categories', 'products'] as $table) {
        $sql = preg_replace_callback(
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?\);/i",
            static function (array $m): string {
                $block = $m[0];
                $block = preg_replace('/`sort_order`\s+BIGINT/', '`sort_order` SMALLINT UNSIGNED', $block, 1) ?? $block;

                return $block;
            },
            $sql,
            1
        ) ?? $sql;
    }

    foreach (['products', 'product_modifiers'] as $table) {
        $sql = preg_replace_callback(
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?\);/i",
            static function (array $m): string {
                return preg_replace('/`vat_rate`\s+DECIMAL\(12,2\)/', '`vat_rate` DECIMAL(5,2)', $m[0], 1) ?? $m[0];
            },
            $sql,
            1
        ) ?? $sql;
    }

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `pos_orders`[\s\S]*?\);/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`loyalty_points_redeemed`\s+BIGINT/', '`loyalty_points_redeemed` BIGINT UNSIGNED', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    return $sql;
}

$raw = file_get_contents($schemaPath);
if ($raw === false) {
    throw new RuntimeException("Unable to read schema: {$schemaPath}");
}

$sql = sqliteSchemaToMysql($raw);

$header = <<<SQL
-- Plumber 001 single-client install (MySQL 8+ / MariaDB 10.4+)
-- Generated from consolidated Laravel migrations via scripts/build-install-sql.php
-- Prefer `php artisan migrate` on greenfield hosts that can run Artisan.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';
SET FOREIGN_KEY_CHECKS=0;

SQL;

$footer = <<<SQL


INSERT IGNORE INTO migrations (migration, batch) VALUES
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('0001_01_01_000010_single_client_application_schema', 1),
('0001_01_01_000020_create_permission_tables', 1);

INSERT IGNORE INTO business_settings (id, name, settings, created_at, updated_at) VALUES
(1, 'Demo Plumbing Co', JSON_OBJECT('theme', JSON_OBJECT('layout_key', 'plumber'), 'default_locale', 'bn'), NOW(), NOW());

INSERT IGNORE INTO branches (id, code, name, name_ar, is_active, created_at, updated_at) VALUES
(1, 'ruh-main', 'Riyadh Service Center', 'مركز الرياض', 1, NOW(), NOW());

INSERT IGNORE INTO product_units (code, name_en, name_ar, name_bn, ubl_unit_code, is_active, created_at, updated_at) VALUES
('PCS', 'Piece', 'قطعة', 'পিস', 'PCE', 1, NOW(), NOW()),
('M', 'Meter', 'متر', 'মিটার', 'MTR', 1, NOW(), NOW()),
('BOX', 'Box', 'صندوق', 'বাক্স', 'BX', 1, NOW(), NOW()),
('ROLL', 'Roll', 'لفة', 'রোল', 'RO', 1, NOW(), NOW()),
('KG', 'Kilogram', 'كيلوغرام', 'কিলোগ্রাম', 'KGM', 1, NOW(), NOW()),
('L', 'Liter', 'لتر', 'লিটার', 'LTR', 1, NOW(), NOW()),
('SET', 'Set', 'مجموعة', 'সেট', 'SET', 1, NOW(), NOW()),
('HR', 'Hour', 'ساعة', 'ঘণ্টা', 'HUR', 1, NOW(), NOW()),
('JOB', 'Job', 'مهمة', 'কাজ', 'E48', 1, NOW(), NOW());

INSERT IGNORE INTO product_sizes (name, measurement_type, is_active, created_at, updated_at) VALUES
('1/2 Inch', 'Diameter', 1, NOW(), NOW()),
('3/4 Inch', 'Diameter', 1, NOW(), NOW()),
('1 Inch', 'Diameter', 1, NOW(), NOW()),
('1 1/4 Inch', 'Diameter', 1, NOW(), NOW()),
('1 1/2 Inch', 'Diameter', 1, NOW(), NOW()),
('2 Inch', 'Diameter', 1, NOW(), NOW()),
('3 Inch', 'Diameter', 1, NOW(), NOW()),
('4 Inch', 'Diameter', 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS=1;
SQL;

if (! is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}

file_put_contents($outPath, $header.$sql.$footer);
echo "Wrote {$outPath}\n";
