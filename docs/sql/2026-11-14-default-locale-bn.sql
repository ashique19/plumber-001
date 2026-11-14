-- Default Bangla UI locale for live databases (business_settings JSON).
-- Safe to run multiple times via phpMyAdmin Import.
-- Does not change session or per-user locale choices in the app.

SET NAMES utf8mb4;

UPDATE `business_settings`
SET `settings` = JSON_SET(COALESCE(`settings`, JSON_OBJECT()), '$.default_locale', 'bn')
WHERE `id` = 1;