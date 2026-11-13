#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

E2E_PORT="${E2E_PORT:-8765}"
E2E_BASE_URL="${E2E_BASE_URL:-http://127.0.0.1:${E2E_PORT}}"
ARTIFACTS="${E2E_ARTIFACTS:-/opt/cursor/artifacts/e2e}"
export E2E_BASE_URL

mkdir -p "$ARTIFACTS"

if ! command -v mysqladmin >/dev/null 2>&1; then
  echo "mysqladmin not found" >&2
  exit 1
fi

if ! mysqladmin ping -h127.0.0.1 -u"zatca_e2e" -p"zatca_e2e_test" --silent 2>/dev/null; then
  echo "Starting MariaDB and ensuring zatca_e2e database..."
  (sudo service mariadb start 2>/dev/null || true)
  sudo mysql -e "CREATE DATABASE IF NOT EXISTS zatca_e2e; CREATE USER IF NOT EXISTS 'zatca_e2e'@'localhost' IDENTIFIED BY 'zatca_e2e_test'; GRANT ALL ON zatca_e2e.* TO 'zatca_e2e'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || true
fi

if [[ -f .env.e2e ]]; then
  set -a && source .env.e2e && set +a
elif [[ -f .env.e2e.example ]]; then
  set -a && source .env.e2e.example && set +a
fi
if [[ -z "${APP_KEY:-}" ]]; then
  export APP_KEY="$(php artisan key:generate --show)"
fi

echo "Migrating and seeding MySQL (${DB_DATABASE})..."
php artisan migrate:fresh --force --seeder=DemoFancyCoffeeSeeder

if pgrep -f "artisan serve.*${E2E_PORT}" >/dev/null; then
  echo "Stopping existing artisan serve on :${E2E_PORT} (refresh env after migrate)"
  pkill -f "artisan serve.*${E2E_PORT}" || true
  sleep 1
fi
if ! pgrep -f "artisan serve.*${E2E_PORT}" >/dev/null; then
  echo "Starting artisan serve on :${E2E_PORT}"
  php artisan serve --host=127.0.0.1 --port="${E2E_PORT}" >/tmp/e2e-serve.log 2>&1 &
  SERVE_PID=$!
  trap 'kill "$SERVE_PID" 2>/dev/null || true' EXIT
  for _ in $(seq 1 60); do
    if curl -sf "${E2E_BASE_URL}/healthz" >/dev/null; then
      break
    fi
    sleep 0.5
  done
  if ! curl -sf "${E2E_BASE_URL}/healthz" >/dev/null; then
    echo "artisan serve failed to become healthy on :${E2E_PORT}" >&2
    tail -30 /tmp/e2e-serve.log >&2 || true
    exit 1
  fi
fi

echo "Running Playwright against ${E2E_BASE_URL}"
npx playwright test -c tests/e2e/playwright.config.ts "$@"
EXIT=$?

echo "Artifacts: ${ARTIFACTS}"
exit "$EXIT"
