# cPanel deploy — Plumber 001 (single client, backend-only)

## Pull & dependencies

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

Assets are built in CI/commits (`public/build`). Rebuild only if you change front-end sources:

```bash
npm ci && npm run build
```

## Permissions (cPanel)

```bash
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
chmod -R ug+rwx storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
```

## Database

On hosts without reliable `php artisan migrate`, import **`docs/sql/install.sql`** in phpMyAdmin (idempotent). It creates schema, seed data, and migration rows.

If the database was created from an **older** `install.sql` (before Nov 2026), run **`docs/sql/2026-11-14-schema-fix.sql`** once after import so column types match the application (inventory decimals, JSON columns, VARCHAR lengths).

When Artisan is available:

```bash
php artisan migrate --force
php artisan db:seed --class=OwnerAdminSeeder --force
php artisan db:seed --class=PlumberCatalogSeeder --force
```

## Environment keys (no values)

Copy `.env.example` to `.env` and set:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG`
- `APP_URL`
- `APP_LOCALE`
- `APP_ENABLED_LOCALES`
- `AUTH_GUARD` (`staff`)
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SESSION_DRIVER`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`
- `ZHEART_BASE_URL`
- `ZHEART_API_KEY`
- `ZHEART_AUTO_SUBMIT`
- `ZHEART_TIMEOUT_SIGN`
- `ZHEART_TIMEOUT_SUBMIT`
- `ZHEART_TIMEOUT_HEALTH`
- `ZHEART_RETRY_TIMES`
- `ZHEART_RETRY_SLEEP_MS`
- `ZHEART_ALLOW_SELLER_OVERRIDE`
- `MAIL_*` (as needed)

## Optimize

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point the domain document root to `public/`. Unauthenticated visitors only see the login screen at `/`.
