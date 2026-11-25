-- Adds invoices.official_number: the tax invoice number Z-Heart returns in data.invoice_number.
-- MariaDB 10.3+ (cPanel). Safe to run more than once. Equivalent of:
--   php artisan migrate --force   (migration 2026_11_26_040000_add_official_number_to_invoices)
-- Run in phpMyAdmin with the plumber database selected. Run only one of the two (SQL or migrate).

SET NAMES utf8mb4;

ALTER TABLE `invoices`
  ADD COLUMN IF NOT EXISTS `official_number` VARCHAR(127) NULL AFTER `document_number`;

-- Unique when set; multiple NULLs stay allowed.
CREATE UNIQUE INDEX IF NOT EXISTS `invoices_official_number_unique` ON `invoices` (`official_number`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_26_040000_add_official_number_to_invoices', COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`
HAVING COALESCE(SUM(`migration` = '2026_11_26_040000_add_official_number_to_invoices'), 0) = 0;
