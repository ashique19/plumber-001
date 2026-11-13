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

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS[^;]+;/i',
        static function (array $m): string {
            $stmt = rtrim($m[0], ";\n\r ");

            return $stmt.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        },
        $sql
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/\bCREATE (?:UNIQUE )?INDEX\b[\s\S]*?\)\s*ENGINE=InnoDB[^;]*;/i',
        static fn (array $m): string => preg_replace('/\s+ENGINE=InnoDB[^;]+/i', '', $m[0]) ?? $m[0],
        $sql
    ) ?? $sql;

    return applyMysqlColumnOverrides($sql);
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
        'ingredients' => ['quantity_on_hand', 'reorder_level', 'low_stock_threshold'],
        'inventory_movements' => ['quantity_delta'],
        'recipe_ingredients' => ['quantity'],
        'product_ingredients' => ['quantity_per_unit'],
    ];

    foreach ($decimal12_3Tables as $table => $columns) {
        $sql = preg_replace_callback(
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?ENGINE=InnoDB[^;]*;/i",
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
        '/CREATE TABLE IF NOT EXISTS `ingredients`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `inventory_movements`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `product_categories`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `products`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`item_type`\s+VARCHAR\(255\)/', '`item_type` VARCHAR(16)', $block, 1) ?? $block;
            $block = preg_replace('/`name_bn`\s+VARCHAR\(255\)/', '`name_bn` VARCHAR(160)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `branches`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`code`\s+VARCHAR\(255\)/', '`code` VARCHAR(32) NOT NULL', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `sessions`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`ip_address`\s+VARCHAR\(255\)/', '`ip_address` VARCHAR(45)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `staff_users`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`remember_token`\s+VARCHAR\(255\)/', '`remember_token` VARCHAR(100)', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `invoices`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `pos_orders`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `loyalty_transactions`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `sessions`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
        '/CREATE TABLE IF NOT EXISTS `customers`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
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
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?ENGINE=InnoDB[^;]*;/i",
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
            "/CREATE TABLE IF NOT EXISTS `{$table}`[\s\S]*?ENGINE=InnoDB[^;]*;/i",
            static function (array $m): string {
                return preg_replace('/`vat_rate`\s+DECIMAL\(12,2\)/', '`vat_rate` DECIMAL(5,2)', $m[0], 1) ?? $m[0];
            },
            $sql,
            1
        ) ?? $sql;
    }

    $sql = preg_replace_callback(
        '/CREATE TABLE IF NOT EXISTS `pos_orders`[\s\S]*?ENGINE=InnoDB[^;]*;/i',
        static function (array $m): string {
            $block = $m[0];
            $block = preg_replace('/`loyalty_points_redeemed`\s+BIGINT/', '`loyalty_points_redeemed` BIGINT UNSIGNED', $block, 1) ?? $block;

            return $block;
        },
        $sql,
        1
    ) ?? $sql;

    $sql = preg_replace_callback(
        '/\bCREATE (?:UNIQUE )?INDEX\b[\s\S]*?\)\s*ENGINE=InnoDB[^;]*;/i',
        static fn (array $m): string => preg_replace('/\s+ENGINE=InnoDB[^;]+/i', '', $m[0]) ?? $m[0],
        $sql
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
SET FOREIGN_KEY_CHECKS=0;

SQL;

$footer = <<<SQL


INSERT IGNORE INTO migrations (migration, batch) VALUES
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('0001_01_01_000010_single_client_application_schema', 1),
('0001_01_01_000020_create_permission_tables', 1);

INSERT IGNORE INTO business_settings (id, name, settings, created_at, updated_at) VALUES
(1, 'Demo Plumbing Co', JSON_OBJECT('theme', JSON_OBJECT('layout_key', 'plumber')), NOW(), NOW());

INSERT IGNORE INTO branches (id, code, name, name_ar, is_active, created_at, updated_at) VALUES
(1, 'ruh-main', 'Riyadh Service Center', 'مركز الرياض', 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS=1;
SQL;

if (! is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}

file_put_contents($outPath, $header.$sql.$footer);
echo "Wrote {$outPath}\n";
