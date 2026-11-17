-- Optional: ensure business_settings.settings has a zheart object (no new columns).
-- Safe to run multiple times via phpMyAdmin Import.
-- The application also merges defaults in PHP; this is for operators who patch JSON directly.

SET NAMES utf8mb4;

UPDATE `business_settings`
SET `settings` = JSON_SET(
    COALESCE(`settings`, JSON_OBJECT()),
    '$.zheart',
    COALESCE(JSON_EXTRACT(`settings`, '$.zheart'), JSON_OBJECT())
)
WHERE `id` = 1
  AND JSON_EXTRACT(COALESCE(`settings`, JSON_OBJECT()), '$.zheart') IS NULL;