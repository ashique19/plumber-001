#!/usr/bin/env bash
# cPanel-friendly permissions for Laravel writable dirs (see docs/DEPLOY.md).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

chmod -R ug+rwx storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;

echo "Applied storage/ and bootstrap/cache/ permissions."
