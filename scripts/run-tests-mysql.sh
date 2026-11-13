#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export APP_ENV=testing
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-fancy_coffee_test}"
export DB_USERNAME="${DB_USERNAME:-fancy}"
export DB_PASSWORD="${DB_PASSWORD:-fancytest}"
export DB_URL=""

php artisan migrate:fresh --force --env=testing
php artisan test "$@"
