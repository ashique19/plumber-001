# Plumber 001 — backend-only POS & invoicing

Laravel 13 + Livewire 3 single-client plumbing business app: staff login at `/`, admin at `/admin/*`, POS, service catalog, inventory, and invoicing signed via **Z-Heart**.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci && npm run build
php artisan serve
```

- **Login:** `/` (no public storefront)
- **Admin:** `/admin/dashboard`, catalog, POS, invoices, business settings

Default owner (override with env): `ADMIN_EMAIL` / `ADMIN_PASSWORD` via `OwnerAdminSeeder`.

## Z-Heart signing

Set `ZHEART_BASE_URL` and `ZHEART_API_KEY` in `.env`. Business settings show API key **prefix** only and a health-check action in `/admin/business`.

## Locales

`en`, `ar`, `bn`, `ur`, `hi` — RTL for `ar` and `ur`.

## Tests

```bash
composer install
php artisan test
```

## Deploy

See [`docs/DEPLOY.md`](docs/DEPLOY.md). Fresh MySQL installs can use [`docs/sql/install.sql`](docs/sql/install.sql).
