# Browser E2E (Playwright)

Real clicks against Laravel served on **MySQL** with `DemoFancyCoffeeSeeder`.

## Run

```bash
npm run test:e2e
# single file:
bash scripts/run-e2e.sh tests/e2e/cashier/access.spec.ts
```

Environment: copy `.env.e2e.example` → `.env.e2e` (or use committed `.env.e2e` for CI agents).

Credentials (demo seed):

| Role | Email | Password |
|------|--------|----------|
| Owner | `owner@fancy-coffee.test` | `TestPassword!2026` |
| Cashier | `cashier@fancy-coffee.test` | `TestPassword!2026` |
| Platform | `admin@taskeenlab.test` | `TestPassword!2026` |

Failures write screenshots to `/opt/cursor/artifacts/e2e/`.

## Guards

Helpers in `helpers/audit.ts` fail on console errors, unexpected HTTP ≥400 on app routes, horizontal overflow, visible translation keys, and Livewire 419s.
