-- Business expense categories (slug stored on business_expenses.category). Idempotent.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `business_expense_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(64) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `business_expense_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `business_expense_categories` (`slug`, `name`, `created_at`, `updated_at`)
VALUES
    ('rent', 'Rent', NOW(), NOW()),
    ('utilities', 'Utilities', NOW(), NOW()),
    ('fuel', 'Fuel & travel', NOW(), NOW()),
    ('salaries', 'Salaries', NOW(), NOW()),
    ('supplies', 'Supplies', NOW(), NOW()),
    ('marketing', 'Marketing', NOW(), NOW()),
    ('other', 'Other', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `updated_at` = NOW();