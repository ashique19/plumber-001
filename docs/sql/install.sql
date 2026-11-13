-- Plumber 001 single-client install (MySQL 8+ / MariaDB 10.4+)
-- Generated from consolidated Laravel migrations via scripts/build-install-sql.php
-- Prefer `php artisan migrate` on greenfield hosts that can run Artisan.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS `migrations`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `migration` VARCHAR(255) NOT NULL,
  `batch` BIGINT NOT NULL
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cache`(
  `key` VARCHAR(255) NOT NULL,
  `value` TEXT NOT NULL,
  `expiration` BIGINT NOT NULL,
  primary key(`key`)
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `cache_expiration_index` on `cache`(`expiration`);
CREATE TABLE IF NOT EXISTS `cache_locks`(
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` BIGINT NOT NULL,
  primary key(`key`)
));
CREATE INDEX `cache_locks_expiration_index` on `cache_locks`(`expiration`);
CREATE TABLE IF NOT EXISTS `jobs`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `queue` VARCHAR(255) NOT NULL,
  `payload` TEXT NOT NULL,
  `attempts` BIGINT NOT NULL,
  `reserved_at` BIGINT,
  `available_at` BIGINT NOT NULL,
  `created_at` BIGINT NOT NULL
));
CREATE INDEX `jobs_queue_index` on `jobs`(`queue`);
CREATE TABLE IF NOT EXISTS `job_batches`(
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` BIGINT NOT NULL,
  `pending_jobs` BIGINT NOT NULL,
  `failed_jobs` BIGINT NOT NULL,
  `failed_job_ids` TEXT NOT NULL,
  `options` TEXT,
  `cancelled_at` BIGINT,
  `created_at` BIGINT NOT NULL,
  `finished_at` BIGINT,
  primary key(`id`)
));
CREATE TABLE IF NOT EXISTS `failed_jobs`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` VARCHAR(255) NOT NULL,
  `queue` VARCHAR(255) NOT NULL,
  `payload` TEXT NOT NULL,
  `exception` TEXT NOT NULL,
  `failed_at` datetime not null default CURRENT_TIMESTAMP
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `failed_jobs_connection_queue_failed_at_index` on `failed_jobs`(
  `connection`,
  `queue`,
  `failed_at`
);
CREATE UNIQUE INDEX `failed_jobs_uuid_unique` on `failed_jobs`(`uuid`);
CREATE TABLE IF NOT EXISTS `business_settings`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `settings` JSON NOT NULL,
  `created_at` datetime,
  `updated_at` datetime
));
CREATE TABLE IF NOT EXISTS `sessions`(
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED,
  `ip_address` VARCHAR(255),
  `user_agent` TEXT,
  `payload` TEXT NOT NULL,
  `last_activity` BIGINT NOT NULL,
  primary key(`id`)
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `sessions_user_id_index` on `sessions`(`user_id`);
CREATE INDEX `sessions_last_activity_index` on `sessions`(`last_activity`);
CREATE TABLE IF NOT EXISTS `password_reset_tokens`(
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` datetime,
  primary key(`email`)
));
CREATE TABLE IF NOT EXISTS `branches`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `address` TEXT,
  `is_active` tinyint(1) not null default '1',
  `settings` JSON,
  `created_at` datetime,
  `updated_at` datetime
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX `branches_code_unique` on `branches`(`code`);
CREATE TABLE IF NOT EXISTS `staff_users`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` datetime,
  `password` VARCHAR(255) NOT NULL,
  `is_active` tinyint(1) not null default '1',
  `remember_token` VARCHAR(255),
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete set null
));
CREATE UNIQUE INDEX `staff_users_email_unique` on `staff_users`(`email`);
CREATE TABLE IF NOT EXISTS `customers`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255),
  `email` VARCHAR(255),
  `vat_number` VARCHAR(255),
  `referral_code` VARCHAR(255),
  `referred_by_customer_id` BIGINT UNSIGNED,
  `points_balance` BIGINT NOT NULL default '0',
  `profile` TEXT,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`referred_by_customer_id`) references `customers`(`id`) on delete set null
));
CREATE UNIQUE INDEX `customers_phone_unique` on `customers`(`phone`);
CREATE UNIQUE INDEX `customers_referral_code_unique` on `customers`(
  `referral_code`
);
CREATE TABLE IF NOT EXISTS `product_categories`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `name_en` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `name_bn` VARCHAR(255),
  `icon_key` VARCHAR(255),
  `description_en` TEXT,
  `description_ar` TEXT,
  `description_bn` TEXT,
  `sort_order` BIGINT NOT NULL default '0',
  `is_active` tinyint(1) not null default '1',
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete set null
));
CREATE INDEX `product_categories_branch_id_is_active_index` on `product_categories`(
  `branch_id`,
  `is_active`
);
CREATE TABLE IF NOT EXISTS `products`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `product_category_id` BIGINT UNSIGNED,
  `sku` VARCHAR(255),
  `item_type` VARCHAR(255) NOT NULL default 'item',
  `name_en` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `name_bn` VARCHAR(255),
  `image_url` VARCHAR(255),
  `description_en` TEXT,
  `description_ar` TEXT,
  `description_bn` TEXT,
  `price_ex_vat` DECIMAL(12,2) NOT NULL,
  `vat_rate` DECIMAL(12,2) NOT NULL default '15',
  `sort_order` BIGINT NOT NULL default '0',
  `is_active` tinyint(1) not null default '1',
  `show_on_homepage` tinyint(1) not null default '0',
  `show_on_menu` tinyint(1) not null default '1',
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete set null,
  foreign key(`product_category_id`) references `product_categories`(`id`) on delete set null
));
CREATE INDEX `products_branch_id_is_active_index` on `products`(
  `branch_id`,
  `is_active`
);
CREATE INDEX `products_show_on_homepage_is_active_index` on `products`(
  `show_on_homepage`,
  `is_active`
);
CREATE INDEX `products_show_on_menu_is_active_index` on `products`(
  `show_on_menu`,
  `is_active`
);
CREATE TABLE IF NOT EXISTS `product_modifiers`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `price_ex_vat` DECIMAL(12,2) NOT NULL default '0',
  `vat_rate` DECIMAL(12,2) NOT NULL default '15',
  `is_active` tinyint(1) not null default '1',
  `created_at` datetime,
  `updated_at` datetime
));
CREATE TABLE IF NOT EXISTS `product_product_modifier`(
  `product_id` BIGINT UNSIGNED NOT NULL,
  `product_modifier_id` BIGINT UNSIGNED NOT NULL,
  foreign key(`product_id`) references `products`(`id`) on delete cascade,
  foreign key(`product_modifier_id`) references `product_modifiers`(`id`) on delete cascade,
  primary key(`product_id`, `product_modifier_id`)
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `invoices`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `document_number` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL default 'draft',
  `referenced_invoice_id` BIGINT UNSIGNED,
  `source_type` VARCHAR(255),
  `source_id` BIGINT UNSIGNED,
  `icv` BIGINT,
  `invoice_hash` VARCHAR(255),
  `previous_invoice_hash` VARCHAR(255),
  `signed_xml` TEXT,
  `qr_tlv_base64` TEXT,
  `payload` TEXT,
  `issued_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete set null,
  foreign key(`referenced_invoice_id`) references `invoices`(`id`) on delete set null
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `invoices_source_type_source_id_index` on `invoices`(
  `source_type`,
  `source_id`
);
CREATE INDEX `invoices_status_issued_at_index` on `invoices`(
  `status`,
  `issued_at`
);
CREATE UNIQUE INDEX `invoices_document_number_unique` on `invoices`(
  `document_number`
);
CREATE TABLE IF NOT EXISTS `invoice_audit_logs`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `event` VARCHAR(255) NOT NULL,
  `context` TEXT,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`invoice_id`) references `invoices`(`id`) on delete cascade
));
CREATE TABLE IF NOT EXISTS `pos_orders`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED,
  `cashier_id` BIGINT UNSIGNED,
  `order_number` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL default 'open',
  `payment_method` VARCHAR(255),
  `subtotal_ex_vat` DECIMAL(12,2) NOT NULL default '0',
  `discount_ex_vat` DECIMAL(12,2) NOT NULL default '0',
  `promo_discount_ex_vat` DECIMAL(12,2) NOT NULL default '0',
  `loyalty_discount_ex_vat` DECIMAL(12,2) NOT NULL default '0',
  `vat_total` DECIMAL(12,2) NOT NULL default '0',
  `total_inc_vat` DECIMAL(12,2) NOT NULL default '0',
  `loyalty_points_redeemed` BIGINT NOT NULL default '0',
  `line_items` TEXT NOT NULL,
  `metadata` TEXT,
  `invoice_id` BIGINT UNSIGNED,
  `refund_credit_note_id` BIGINT UNSIGNED,
  `paid_at` datetime,
  `cancelled_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`),
  foreign key(`customer_id`) references `customers`(`id`) on delete set null,
  foreign key(`cashier_id`) references `staff_users`(`id`) on delete set null,
  foreign key(`invoice_id`) references `invoices`(`id`) on delete set null,
  foreign key(`refund_credit_note_id`) references `invoices`(`id`) on delete set null
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `pos_orders_branch_id_status_index` on `pos_orders`(
  `branch_id`,
  `status`
);
CREATE UNIQUE INDEX `pos_orders_order_number_unique` on `pos_orders`(
  `order_number`
);
CREATE TABLE IF NOT EXISTS `loyalty_transactions`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `pos_order_id` BIGINT UNSIGNED,
  `type` VARCHAR(255) NOT NULL,
  `points_delta` BIGINT NOT NULL,
  `monetary_value_ex_vat` DECIMAL(12,2),
  `metadata` TEXT,
  `occurred_at` datetime not null,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`customer_id`) references `customers`(`id`) on delete cascade,
  foreign key(`pos_order_id`) references `pos_orders`(`id`) on delete set null
));
CREATE TABLE IF NOT EXISTS `ingredients`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED,
  `name_en` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `name_bn` VARCHAR(255),
  `unit` VARCHAR(255) NOT NULL default 'g',
  `quantity_on_hand` DECIMAL(12,2) NOT NULL default '0',
  `reorder_level` DECIMAL(12,2) NOT NULL default '0',
  `low_stock_threshold` DECIMAL(12,2) NOT NULL default '0',
  `is_active` tinyint(1) not null default '1',
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete set null
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `inventory_movements`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `reason` VARCHAR(255),
  `quantity_delta` DECIMAL(12,2) NOT NULL,
  `reference_type` VARCHAR(255),
  `reference_id` BIGINT UNSIGNED,
  `user_id` BIGINT UNSIGNED,
  `pos_order_id` BIGINT UNSIGNED,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete cascade,
  foreign key(`ingredient_id`) references `ingredients`(`id`) on delete cascade,
  foreign key(`user_id`) references `staff_users`(`id`) on delete set null,
  foreign key(`pos_order_id`) references `pos_orders`(`id`) on delete set null
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `recipes`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`product_id`) references `products`(`id`) on delete cascade
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX `recipes_product_id_unique` on `recipes`(`product_id`);
CREATE TABLE IF NOT EXISTS `recipe_ingredients`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recipe_id` BIGINT UNSIGNED NOT NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  foreign key(`recipe_id`) references `recipes`(`id`) on delete cascade,
  foreign key(`ingredient_id`) references `ingredients`(`id`) on delete cascade
));
CREATE UNIQUE INDEX `recipe_ingredients_recipe_id_ingredient_id_unique` on `recipe_ingredients`(
  `recipe_id`,
  `ingredient_id`
);
CREATE TABLE IF NOT EXISTS `product_ingredients`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `quantity_per_unit` DECIMAL(12,2) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`product_id`) references `products`(`id`) on delete cascade,
  foreign key(`ingredient_id`) references `ingredients`(`id`) on delete cascade
));
CREATE UNIQUE INDEX `product_ingredients_product_id_ingredient_id_unique` on `product_ingredients`(
  `product_id`,
  `ingredient_id`
);
CREATE TABLE IF NOT EXISTS `shifts`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `staff_user_id` BIGINT UNSIGNED NOT NULL,
  `started_at` datetime not null,
  `opening_cash_float` DECIMAL(12,2),
  `closing_cash_counted` DECIMAL(12,2),
  `close_notes` TEXT,
  `ended_at` datetime,
  `metadata` TEXT,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`branch_id`) references `branches`(`id`) on delete cascade,
  foreign key(`staff_user_id`) references `staff_users`(`id`) on delete cascade
));
CREATE TABLE IF NOT EXISTS `business_settings_audit_logs`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `staff_user_id` BIGINT UNSIGNED,
  `tab` VARCHAR(255) NOT NULL,
  `snapshot` TEXT,
  `created_at` datetime,
  `updated_at` datetime,
  foreign key(`staff_user_id`) references `staff_users`(`id`) on delete set null
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `permissions`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime
)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE UNIQUE INDEX `permissions_name_guard_name_unique` on `permissions`(
  `name`,
  `guard_name`
);
CREATE TABLE IF NOT EXISTS `roles`(
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime
));
CREATE UNIQUE INDEX `roles_name_guard_name_unique` on `roles`(
  `name`,
  `guard_name`
);
CREATE TABLE IF NOT EXISTS `model_has_permissions`(
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  foreign key(`permission_id`) references `permissions`(`id`) on delete cascade,
  primary key(`permission_id`, `model_id`, `model_type`)
));
CREATE INDEX `model_has_permissions_model_id_model_type_index` on `model_has_permissions`(
  `model_id`,
  `model_type`
);
CREATE TABLE IF NOT EXISTS `model_has_roles`(
  `role_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  foreign key(`role_id`) references `roles`(`id`) on delete cascade,
  primary key(`role_id`, `model_id`, `model_type`)
));
CREATE INDEX `model_has_roles_model_id_model_type_index` on `model_has_roles`(
  `model_id`,
  `model_type`
);
CREATE TABLE IF NOT EXISTS `role_has_permissions`(
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  foreign key(`permission_id`) references `permissions`(`id`) on delete cascade,
  foreign key(`role_id`) references `roles`(`id`) on delete cascade,
  primary key(`permission_id`, `role_id`)
));

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