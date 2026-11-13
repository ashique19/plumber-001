<?php

declare(strict_types=1);

use App\Support\Themes\PlumberThemePreset;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Storefront\PlumberStorefrontImagery;

function sqlQuote(string $value): string
{
    return str_replace("'", "''", $value);
}

function categorySeedSql(string $en, string $ar): string
{
    return <<<SQL
INSERT INTO product_categories (tenant_id, name_en, name_ar, icon_key, is_active, created_at, updated_at)
SELECT @tenant_id, '{$en}', '{$ar}', 'wrench', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM product_categories WHERE tenant_id = @tenant_id AND name_en = '{$en}');

SQL;
}

function productUpsertSql(
    string $sku,
    string $itemType,
    string $categoryEn,
    string $nameEn,
    string $nameAr,
    float $price,
    ?string $imageUrl = null,
): string {
    $nameEnSql = sqlQuote($nameEn);
    $nameArSql = sqlQuote($nameAr);
    $catEnSql = sqlQuote($categoryEn);
    $imageInsert = $imageUrl !== null ? ', image_url' : '';
    $imageInsertValue = $imageUrl !== null ? ", '".sqlQuote($imageUrl)."'" : '';
    $imageUpdate = $imageUrl !== null ? ", image_url = '".sqlQuote($imageUrl)."'" : '';

    return <<<SQL
INSERT INTO products (tenant_id, product_category_id, sku, item_type, name_en, name_ar, price_ex_vat, vat_rate{$imageInsert}, is_active, created_at, updated_at)
SELECT @tenant_id,
       (SELECT id FROM product_categories WHERE tenant_id = @tenant_id AND name_en = '{$catEnSql}' LIMIT 1),
       '{$sku}', '{$itemType}', '{$nameEnSql}', '{$nameArSql}', {$price}, 15.00{$imageInsertValue}, 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE tenant_id = @tenant_id AND sku = '{$sku}');

UPDATE products
SET item_type = '{$itemType}', name_en = '{$nameEnSql}', name_ar = '{$nameArSql}', price_ex_vat = {$price}, is_active = 1{$imageUpdate},
    product_category_id = (SELECT id FROM product_categories WHERE tenant_id = @tenant_id AND name_en = '{$catEnSql}' LIMIT 1)
WHERE tenant_id = @tenant_id AND sku = '{$sku}';

SQL;
}

$settings = PlumberThemePreset::settings('Demo Plumbing Co', 'شركة السباكة التجريبية', 'en');
$settings['content']['plumber'] = PlumberThemePreset::demoPlumberContent('Demo Plumbing Co', 'شركة السباكة التجريبية');
$settings['zatca'] = array_merge($settings['zatca'] ?? [], [
    'default_document_type' => 'standard',
    'business_category' => 'Plumbing',
    'integration_enabled' => false,
]);

$jsonSql = sqlQuote((string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$passwordHash = '$2y$10$rU1Juu0yLx1GarXjGNEgMuHi0PG0ZSLdmUZUWKacf4TOLnCSZk9F.';

$categoryMap = [
    'Emergency' => 'طوارئ',
    'Maintenance' => 'صيانة',
    'Installation' => 'تركيب',
    'Parts & materials' => 'قطع ومواد',
];

$catBlock = '';
foreach ($categoryMap as $en => $ar) {
    $catBlock .= categorySeedSql(sqlQuote($en), sqlQuote($ar));
}

$services = [
    ['SRV-LEAK', 'Emergency', 'Leak repair visit', 'زيارة إصلاح تسرب', 180.00],
    ['SRV-BURST', 'Emergency', 'Burst pipe isolation', 'عزل أنبوب منفجر', 250.00],
    ['SRV-DRAIN', 'Maintenance', 'Drain clearing', 'تسليك مجاري', 140.00],
    ['SRV-FIX', 'Maintenance', 'Fixture tune-up', 'صيانة تجهيزات', 95.00],
    ['SRV-HEAT', 'Installation', 'Water heater install', 'تركيب سخان مياه', 420.00],
    ['SRV-FILT', 'Installation', 'Filtration system install', 'تركيب نظام تنقية', 320.00],
];

$parts = [
    ['PTFE', 'PTFE tape roll', 'شريط تفلون', 8.00],
    ['WASH-KIT', 'Rubber washer kit', 'طقم حشوات مطاطية', 15.00],
    ['ELBOW-15', '15mm brass elbow', 'كوع نحاس ١٥ مم', 12.00],
    ['VALVE-ANG', 'Angle stop valve', 'صمام زاوية', 45.00],
    ['PTRAP', 'P-trap assembly', 'مصيدة P', 38.00],
    ['SEALANT', 'Plumber’s putty (tub)', 'معجون سباكة', 18.00],
];

$productSql = '';
foreach ($services as [$sku, $catEn, $nameEn, $nameAr, $price]) {
    $productSql .= productUpsertSql($sku, 'service', $catEn, $nameEn, $nameAr, $price, PlumberStorefrontImagery::pathForSku($sku));
}
foreach ($parts as [$sku, $nameEn, $nameAr, $price]) {
    $productSql .= productUpsertSql($sku, 'part', 'Parts & materials', $nameEn, $nameAr, $price);
}

$rolePermissionsTail = <<<'SQL'
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'manager' AND r.guard_name = 'staff'
WHERE p.guard_name = 'staff' AND p.name IN ('pos.access','inventory.view','reports.view','staff.manage','zatca.manage')
  AND NOT EXISTS (SELECT 1 FROM role_has_permissions x WHERE x.permission_id = p.id AND x.role_id = r.id);

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'cashier' AND r.guard_name = 'staff'
WHERE p.guard_name = 'staff' AND p.name = 'pos.access'
  AND NOT EXISTS (SELECT 1 FROM role_has_permissions x WHERE x.permission_id = p.id AND x.role_id = r.id);

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'accountant' AND r.guard_name = 'staff'
WHERE p.guard_name = 'staff' AND p.name IN ('invoices.issue','reports.view','zatca.manage')
  AND NOT EXISTS (SELECT 1 FROM role_has_permissions x WHERE x.permission_id = p.id AND x.role_id = r.id);

SQL;

$roleAssignmentTail = <<<'SQL'
SET @model_type := CONCAT('App', CHAR(92), 'Models', CHAR(92), 'TenantUser');

DELETE m
FROM model_has_roles m
INNER JOIN tenant_users u ON u.id = m.model_id AND u.tenant_id = @tenant_id
WHERE @tenant_id IS NOT NULL
  AND m.tenant_id = @tenant_id
  AND u.email IN ('owner@demo-plumber.test', 'technician@demo-plumber.test')
  AND m.model_type <> @model_type;

INSERT INTO model_has_roles (tenant_id, role_id, model_id, model_type)
SELECT @tenant_id, r.id, u.id, @model_type
FROM tenant_users u
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'owner' AND r.guard_name = 'staff'
WHERE @tenant_id IS NOT NULL
  AND u.tenant_id = @tenant_id AND u.email = 'owner@demo-plumber.test'
  AND NOT EXISTS (
    SELECT 1 FROM model_has_roles m
    WHERE m.tenant_id = @tenant_id AND m.model_type = @model_type
      AND m.model_id = u.id AND m.role_id = r.id
  );

INSERT INTO model_has_roles (tenant_id, role_id, model_id, model_type)
SELECT @tenant_id, r.id, u.id, @model_type
FROM tenant_users u
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'cashier' AND r.guard_name = 'staff'
WHERE @tenant_id IS NOT NULL
  AND u.tenant_id = @tenant_id AND u.email = 'technician@demo-plumber.test'
  AND NOT EXISTS (
    SELECT 1 FROM model_has_roles m
    WHERE m.tenant_id = @tenant_id AND m.model_type = @model_type
      AND m.model_id = u.id AND m.role_id = r.id
  );

SQL;

$permissionCacheTail = <<<'SQL'

-- Clear Spatie permission cache when CACHE_STORE=database (skipped if no `cache` table).
SET @has_cache := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'cache'
);

SET @cache_sql := IF(
  @has_cache > 0,
  'DELETE FROM `cache` WHERE `key` LIKE ''%spatie.permission.cache%''',
  'SELECT 1'
);

PREPARE cache_stmt FROM @cache_sql;
EXECUTE cache_stmt;
DEALLOCATE PREPARE cache_stmt;

SQL;

$sql = <<<SQL
-- Idempotent cPanel / phpMyAdmin patch (MySQL 8+ / MariaDB 10.4+)
-- Adds products.item_type, products.image_url (when missing), and seeds demo-plumber job catalog.
-- Assigns staff roles with backslash-safe model_type (CONCAT + CHAR(92)).
-- Clears Spatie permission cache rows when a `cache` table exists.
-- After import: if CACHE_STORE=file in .env, delete files inside storage/framework/cache/data/
--   (not bootstrap/cache) so staff permissions apply — see docs/DEPLOY-TEST.md.
-- Safe to re-run. Touches only slug demo-plumber (+ global spatie permission cache keys).

SET @demo_slug := 'demo-plumber';
SET @demo_password_hash := '{$passwordHash}';
SET @egs_uuid := '00000000-0000-4000-8000-000000000010';
SET @db := DATABASE();

SET @ddl := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'item_type'),
        'SELECT 1',
        'ALTER TABLE products ADD COLUMN item_type VARCHAR(16) NOT NULL DEFAULT ''item'' AFTER sku'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'icon_key'),
        'SELECT 1',
        'ALTER TABLE product_categories ADD COLUMN icon_key VARCHAR(32) NULL AFTER name_ar'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'image_url'),
        'SELECT 1',
        'ALTER TABLE products ADD COLUMN image_url VARCHAR(255) NULL AFTER name_ar'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO migrations (migration, batch)
SELECT '2026_11_09_100000_add_pos_catalog_presentation_columns', b.max_batch + 1
FROM (SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations) AS b
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_11_09_100000_add_pos_catalog_presentation_columns'
);

INSERT INTO tenants (slug, name, settings, created_at, updated_at)
VALUES (@demo_slug, 'Demo Plumbing Co', '{$jsonSql}', NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), settings = VALUES(settings), updated_at = NOW();

SET @tenant_id := (SELECT id FROM tenants WHERE slug = @demo_slug LIMIT 1);

INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'pos.access', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'pos.access' AND guard_name = 'staff');
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'inventory.view', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'inventory.view' AND guard_name = 'staff');
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'invoices.issue', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'invoices.issue' AND guard_name = 'staff');
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'reports.view', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'reports.view' AND guard_name = 'staff');
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'staff.manage', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'staff.manage' AND guard_name = 'staff');
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'zatca.manage', 'staff', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'zatca.manage' AND guard_name = 'staff');

INSERT INTO roles (tenant_id, name, guard_name, created_at, updated_at)
SELECT @tenant_id, 'owner', 'staff', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tenant_id AND name = 'owner' AND guard_name = 'staff');
INSERT INTO roles (tenant_id, name, guard_name, created_at, updated_at)
SELECT @tenant_id, 'manager', 'staff', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tenant_id AND name = 'manager' AND guard_name = 'staff');
INSERT INTO roles (tenant_id, name, guard_name, created_at, updated_at)
SELECT @tenant_id, 'cashier', 'staff', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tenant_id AND name = 'cashier' AND guard_name = 'staff');
INSERT INTO roles (tenant_id, name, guard_name, created_at, updated_at)
SELECT @tenant_id, 'accountant', 'staff', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tenant_id AND name = 'accountant' AND guard_name = 'staff');

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p
INNER JOIN roles r ON r.tenant_id = @tenant_id AND r.name = 'owner' AND r.guard_name = 'staff'
WHERE p.guard_name = 'staff'
  AND NOT EXISTS (SELECT 1 FROM role_has_permissions x WHERE x.permission_id = p.id AND x.role_id = r.id);

{$rolePermissionsTail}

INSERT INTO branches (tenant_id, code, name, name_ar, address, is_active, created_at, updated_at)
SELECT @tenant_id, 'ruh-main', 'Riyadh Service Center', 'مركز الرياض',
       'Al Olaya Street, Building 12, Riyadh 12211, Kingdom of Saudi Arabia', 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM branches WHERE tenant_id = @tenant_id AND code = 'ruh-main');

SET @branch_id := (SELECT id FROM branches WHERE tenant_id = @tenant_id AND code = 'ruh-main' LIMIT 1);

INSERT INTO egs_devices (tenant_id, branch_id, uuid, name, last_icv, created_at, updated_at)
SELECT @tenant_id, @branch_id, @egs_uuid, 'EGS Riyadh Demo', 0, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM egs_devices WHERE uuid = @egs_uuid);

INSERT INTO tenant_users (tenant_id, branch_id, name, email, password, is_active, created_at, updated_at)
SELECT @tenant_id, NULL, 'Owner', 'owner@demo-plumber.test', @demo_password_hash, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tenant_users WHERE tenant_id = @tenant_id AND email = 'owner@demo-plumber.test');

INSERT INTO tenant_users (tenant_id, branch_id, name, email, password, is_active, created_at, updated_at)
SELECT @tenant_id, @branch_id, 'Technician', 'technician@demo-plumber.test', @demo_password_hash, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tenant_users WHERE tenant_id = @tenant_id AND email = 'technician@demo-plumber.test');

{$roleAssignmentTail}
{$permissionCacheTail}

UPDATE products SET is_active = 0
WHERE tenant_id = @tenant_id AND sku IN ('LEAK','BURST','DRAIN','FIX','HEAT','FILT');

{$catBlock}
{$productSql}
SQL;

$out = __DIR__.'/../docs/sql/2026-11-11-plumber-job-catalog.sql';
file_put_contents($out, $sql);

echo "Wrote {$out} (".strlen($sql)." bytes)\n";
