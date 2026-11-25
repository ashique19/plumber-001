-- Adds invoices.zheart_seller: the seller block Z-Heart returns when it signs an invoice.
-- MariaDB 10.3+ (cPanel). Safe to run more than once. Equivalent of:
--   php artisan migrate --force   (migration 2026_11_25_120000_add_zheart_seller_to_invoices)
-- Run in phpMyAdmin with the plumber database selected. Run only one of the two (SQL or migrate).

SET NAMES utf8mb4;

ALTER TABLE `invoices`
  ADD COLUMN IF NOT EXISTS `zheart_seller` LONGTEXT NULL AFTER `qr_tlv_base64`;

-- Record the migration so a later `php artisan migrate` skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_25_120000_add_zheart_seller_to_invoices', COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`
HAVING COALESCE(SUM(`migration` = '2026_11_25_120000_add_zheart_seller_to_invoices'), 0) = 0;
