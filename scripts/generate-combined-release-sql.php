<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$out = $root.'/docs/sql/2026-11-12-combined-release.sql';

$stripMigrationInserts = static function (string $sql): string {
    return preg_replace(
        '/\n-- Migration:.*\n|\nINSERT INTO migrations \(migration, batch\)[\s\S]*?;\n/m',
        "\n",
        $sql
    ) ?? $sql;
};

$read = static function (string $path) use ($root): string {
    $full = $root.'/'.$path;
    if (! is_readable($full)) {
        throw new RuntimeException("Missing SQL source: {$path}");
    }

    return file_get_contents($full);
};

$productIndexes = <<<'SQL'

-- ── Product storefront indexes (2026_11_12_120000) ─────────────────────────────
SET @ddl := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_tenant_id_show_on_homepage_is_active_index'
        ),
        'SELECT 1',
        'ALTER TABLE products ADD INDEX products_tenant_id_show_on_homepage_is_active_index (tenant_id, show_on_homepage, is_active)'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_tenant_id_show_on_menu_is_active_index'
        ),
        'SELECT 1',
        'ALTER TABLE products ADD INDEX products_tenant_id_show_on_menu_is_active_index (tenant_id, show_on_menu, is_active)'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SQL;

$rbacFix = <<<'SQL'

-- ── Tenant RBAC: cashier is POS-only (catalog/inventory admin for owner/manager) ─
DELETE rhp FROM role_has_permissions rhp
INNER JOIN permissions p ON p.id = rhp.permission_id AND p.guard_name = 'staff' AND p.name IN ('inventory.view', 'inventory.manage')
INNER JOIN roles r ON r.id = rhp.role_id AND r.guard_name = 'staff' AND r.name = 'cashier';

SQL;

$migrationsTail = <<<'SQL'

-- ── Laravel migrations table (idempotent) ────────────────────────────────────────
INSERT INTO migrations (migration, batch)
SELECT '2026_11_11_100000_add_item_type_to_products', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_11_100000_add_item_type_to_products');

INSERT INTO migrations (migration, batch)
SELECT '2026_11_12_120000_add_product_storefront_visibility_columns', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_12_120000_add_product_storefront_visibility_columns');

INSERT INTO migrations (migration, batch)
SELECT '2026_11_12_130000_add_catalog_i18n_columns', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_12_130000_add_catalog_i18n_columns');

INSERT INTO migrations (migration, batch)
SELECT '2026_11_12_140000_ingredient_inventory_enhancements', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_12_140000_ingredient_inventory_enhancements');

INSERT INTO migrations (migration, batch)
SELECT '2026_11_12_200000_add_zheart_columns_to_tenants', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_12_200000_add_zheart_columns_to_tenants');

SQL;

$header = <<<'HDR'
-- Idempotent combined production release patch (MySQL 8+ / MariaDB 10.4+)
-- Run once in phpMyAdmin after `git pull` when you cannot rely on `php artisan migrate`.
-- Order: fancy-coffee menu → ingredient inventory → demo-plumber tenant → RBAC → migrations ledger.
-- Safe to re-run. Touches fancy-coffee catalog data, global schema/permissions, and demo-plumber slug only.
--
-- Sources: docs/sql/2026-11-12-fancy-coffee-menu-restore.sql,
--          docs/sql/2026-11-12-ingredient-inventory.sql,
--          docs/sql/2026-11-11-demo-plumber-tenant.sql
-- PRs: #91 menu, #89 inventory, #75 demo-plumber tenant, #74 reconcile (app-only), #69 branches (app-only)

HDR;

$parts = [
    $header,
    "\n-- ═══ 1. Fancy Coffee menu restore ═══\n\n",
    $read('docs/sql/2026-11-12-fancy-coffee-menu-restore.sql'),
    $productIndexes,
    "\n-- ═══ 2. Ingredient inventory (#89) ═══\n\n",
    $stripMigrationInserts($read('docs/sql/2026-11-12-ingredient-inventory.sql')),
    "\n-- ═══ 3. Demo-plumber greenfield tenant (#75) ═══\n\n",
    $read('docs/sql/2026-11-11-demo-plumber-tenant.sql'),
    "\n-- ═══ 4. Z-Heart tenant columns ═══\n\n",
    $stripMigrationInserts($read('docs/sql/2026-11-12-zheart-tenant-columns.sql')),
    $rbacFix,
    $migrationsTail,
];

$combined = implode('', $parts);
file_put_contents($out, $combined);

echo 'Wrote '.$out.' ('.strlen($combined)." bytes)\n";
