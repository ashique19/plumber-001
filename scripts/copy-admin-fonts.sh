#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="$ROOT/public/fonts/admin"
mkdir -p "$DEST/en" "$DEST/bn" "$DEST/ar"

copy() {
  local src="$1" dest="$2"
  install -D -m 0644 "$ROOT/$src" "$DEST/$dest"
}

# English — Plus Jakarta Sans (latin, OFL)
copy node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-400-normal.woff2 en/plus-jakarta-sans-400.woff2
copy node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-500-normal.woff2 en/plus-jakarta-sans-500.woff2
copy node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-600-normal.woff2 en/plus-jakarta-sans-600.woff2
copy node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-700-normal.woff2 en/plus-jakarta-sans-700.woff2

# Bengali — Noto Sans Bengali (bengali, OFL; compact vs Hind Siliguri on low-end devices)
copy node_modules/@fontsource/noto-sans-bengali/files/noto-sans-bengali-bengali-400-normal.woff2 bn/noto-sans-bengali-400.woff2
copy node_modules/@fontsource/noto-sans-bengali/files/noto-sans-bengali-bengali-500-normal.woff2 bn/noto-sans-bengali-500.woff2
copy node_modules/@fontsource/noto-sans-bengali/files/noto-sans-bengali-bengali-600-normal.woff2 bn/noto-sans-bengali-600.woff2

# Arabic — IBM Plex Sans Arabic (arabic, OFL)
copy node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2 ar/ibm-plex-sans-arabic-400.woff2
copy node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-500-normal.woff2 ar/ibm-plex-sans-arabic-500.woff2
copy node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-600-normal.woff2 ar/ibm-plex-sans-arabic-600.woff2

echo "Admin fonts copied to $DEST"
du -ch "$DEST"/*/*.woff2 | tail -1
for loc in en bn ar; do
  echo -n "$loc: "
  du -ch "$DEST/$loc"/*.woff2 | tail -1 | awk '{print $1}'
done