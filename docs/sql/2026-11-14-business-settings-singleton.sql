-- Idempotent: ensure a singleton business_settings row exists at id=1 (minimal install.sql imports).
-- Safe to run on MariaDB/MySQL after install.sql and theme/color patches.

INSERT IGNORE INTO business_settings (id, name, settings, created_at, updated_at) VALUES
(1, 'Demo Plumbing Co', JSON_OBJECT(
  'theme', JSON_OBJECT(
    'layout_key', 'plumber',
    'colors', JSON_OBJECT(
      'navy', '#0B1F3A',
      'steel', '#2D4A6F',
      'sky', '#E8F1FA',
      'ice', '#F4F8FC',
      'safety', '#E87722',
      'accent', '#1B8A8A',
      'slate', '#475569'
    )
  ),
  'default_locale', 'bn'
), NOW(), NOW());