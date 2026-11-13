-- Idempotent schema alignment for live MySQL databases imported from older docs/sql/install.sql
-- Matches Laravel migration 0001_01_01_000010_single_client_application_schema (Nov 2026).
-- Safe to run multiple times; MODIFY is a no-op when types already match.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `ingredients`
  MODIFY `name_en` VARCHAR(160) NOT NULL,
  MODIFY `name_ar` VARCHAR(160) NULL,
  MODIFY `name_bn` VARCHAR(160) NULL,
  MODIFY `unit` VARCHAR(16) NOT NULL DEFAULT 'g',
  MODIFY `quantity_on_hand` DECIMAL(12,3) NOT NULL DEFAULT 0,
  MODIFY `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 0,
  MODIFY `low_stock_threshold` DECIMAL(12,3) NOT NULL DEFAULT 0;

ALTER TABLE `inventory_movements`
  MODIFY `type` VARCHAR(32) NOT NULL,
  MODIFY `reason` VARCHAR(32) NULL,
  MODIFY `quantity_delta` DECIMAL(12,3) NOT NULL;

ALTER TABLE `recipe_ingredients`
  MODIFY `quantity` DECIMAL(12,3) NOT NULL;

ALTER TABLE `product_ingredients`
  MODIFY `quantity_per_unit` DECIMAL(12,3) NOT NULL;

ALTER TABLE `customers`
  MODIFY `vat_number` VARCHAR(32) NULL,
  MODIFY `referral_code` VARCHAR(32) NULL,
  MODIFY `points_balance` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `profile` JSON NULL;

ALTER TABLE `invoices`
  MODIFY `document_type` VARCHAR(32) NOT NULL,
  MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  MODIFY `source_type` VARCHAR(64) NULL,
  MODIFY `icv` BIGINT UNSIGNED NULL,
  MODIFY `invoice_hash` VARCHAR(512) NULL,
  MODIFY `previous_invoice_hash` VARCHAR(512) NULL,
  MODIFY `signed_xml` LONGTEXT NULL,
  MODIFY `payload` JSON NULL;

ALTER TABLE `invoice_audit_logs`
  MODIFY `context` JSON NULL;

ALTER TABLE `pos_orders`
  MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  MODIFY `payment_method` VARCHAR(32) NULL,
  MODIFY `line_items` JSON NOT NULL,
  MODIFY `metadata` JSON NULL,
  MODIFY `loyalty_points_redeemed` BIGINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `loyalty_transactions`
  MODIFY `type` VARCHAR(32) NOT NULL,
  MODIFY `points_delta` INT NOT NULL,
  MODIFY `metadata` JSON NULL;

ALTER TABLE `shifts`
  MODIFY `metadata` JSON NULL;

ALTER TABLE `business_settings_audit_logs`
  MODIFY `snapshot` JSON NULL;

ALTER TABLE `branches`
  MODIFY `code` VARCHAR(32) NOT NULL;

ALTER TABLE `sessions`
  MODIFY `ip_address` VARCHAR(45) NULL,
  MODIFY `payload` LONGTEXT NOT NULL,
  MODIFY `last_activity` INT NOT NULL;

ALTER TABLE `staff_users`
  MODIFY `remember_token` VARCHAR(100) NULL;

ALTER TABLE `product_categories`
  MODIFY `name_bn` VARCHAR(120) NULL,
  MODIFY `icon_key` VARCHAR(32) NULL,
  MODIFY `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `products`
  MODIFY `item_type` VARCHAR(16) NOT NULL DEFAULT 'item',
  MODIFY `name_bn` VARCHAR(160) NULL,
  MODIFY `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  MODIFY `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `product_modifiers`
  MODIFY `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00;

SET FOREIGN_KEY_CHECKS=1;
