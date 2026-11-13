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
