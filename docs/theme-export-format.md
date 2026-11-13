# Portable theme export (Taskeen Lab → client)

Super-admin exports let Taskeen Lab ship a **client theme** in two forms:

| Export | Command / platform | Use |
|--------|-------------------|-----|
| **Theme bundle** | `php artisan theme:export {slug} --bundle` | Import into the multi-tenant SaaS (`theme:import`) |
| **Standalone Laravel app** | `php artisan theme:export {slug} --standalone` (default) | Zip the full app + single-tenant config for the client host |

## Bundle layout (`theme-bundle-*.zip`)

```
manifest.json       # schema taskeenlab.theme-bundle v1
tenant.json         # slug, name, settings JSON (paths point at public/ assets)
assets/             # Mirror of public/ paths (e.g. assets/images/fancy-coffee/hero.jpg)
```

## Standalone app layout (`client-{slug}-laravel.zip`)

- Full Laravel tree (no `.env`, no `vendor/` if built on a slim CI node — run `composer install` on server)
- `database/seed/client-tenant.json` — tenant row for `ClientExportedTenantSeeder`
- `theme-bundle/` — same as portable bundle (for re-import or audit)
- `.env.example` — `SINGLE_TENANT_MODE=true`, `CLIENT_TENANT_SLUG={slug}`
- `docs/CLIENT-DEPLOY.md`

Client boot: migrate → `php artisan db:seed --class=ClientExportedTenantSeeder` → `/` redirects to café storefront when `SINGLE_TENANT_MODE` is enabled.

## Platform UI

`/platform/dashboard` → **Export client Laravel app** / **Export theme bundle** (platform guard + `platform.manage`).

## Import on SaaS

```bash
php artisan theme:import /path/to/theme-bundle-fancy-coffee.zip
php artisan theme:import /path/to/bundle.zip --tenant=client-acme
```

Copies assets into `public/` and upserts the `tenants` row.
