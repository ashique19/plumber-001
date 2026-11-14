-- Plumber theme color palette for business_settings JSON (fresh install.sql imports).
-- Safe to run multiple times via phpMyAdmin Import.

SET NAMES utf8mb4;

UPDATE `business_settings`
SET `settings` = JSON_SET(
    COALESCE(`settings`, JSON_OBJECT()),
    '$.theme.layout_key',
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`settings`, '$.theme.layout_key')), 'plumber'),
    '$.theme.colors',
    JSON_MERGE_PATCH(
        JSON_OBJECT(
            'navy', '#0B1F3A',
            'steel', '#2D4A6F',
            'sky', '#E8F1FA',
            'ice', '#F4F8FC',
            'safety', '#E87722',
            'accent', '#1B8A8A',
            'slate', '#475569'
        ),
        COALESCE(JSON_EXTRACT(`settings`, '$.theme.colors'), JSON_OBJECT())
    )
)
WHERE `id` = 1;

UPDATE `business_settings`
SET `settings` = JSON_SET(
    COALESCE(`settings`, JSON_OBJECT()),
    '$.legal_entity.national_address.country',
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`settings`, '$.legal_entity.national_address.country')), 'SA')
)
WHERE `id` = 1
  AND JSON_EXTRACT(COALESCE(`settings`, JSON_OBJECT()), '$.legal_entity.national_address.country') IS NULL;