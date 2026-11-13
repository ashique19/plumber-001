#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/public/images/fancy-coffee"
TMP="$ROOT/storage/app/tmp-fancy-coffee-images"
mkdir -p "$OUT" "$TMP"

fetch() {
  local name="$1"
  local width="$2"
  local url="$3"
  local jpg="$OUT/${name}.jpg"
  local webp="$OUT/${name}.webp"
  curl -fsSL "${url}" -o "$TMP/${name}-raw.jpg"
  cwebp -quiet -q 82 -resize "$width" 0 "$TMP/${name}-raw.jpg" -o "$webp"
  local sz
  sz=$(wc -c < "$webp")
  if [[ "$sz" -gt 204800 ]]; then
    cwebp -quiet -q 68 -resize "$width" 0 "$TMP/${name}-raw.jpg" -o "$webp"
  fi
  if command -v convert >/dev/null 2>&1; then
    convert "$TMP/${name}-raw.jpg" -resize "${width}x>" -quality 85 "$jpg"
  else
    cp "$TMP/${name}-raw.jpg" "$jpg"
  fi
  echo "OK $name webp=$(du -h "$webp" | cut -f1)"
}

U() { echo "https://images.unsplash.com/photo-$1?auto=format&fit=crop&w=$2&q=85"; }
P() { echo "https://images.pexels.com/photos/$1/pexels-photo-$1.jpeg?auto=compress&cs=tinysrgb&w=$2"; }

# Storefront hero + gallery + branches (verified subjects — see docs/image-credits.md)
fetch hero 1600 "$(P 29086396 1600)"
fetch gallery-interior 800 "$(P 31148501 800)"
fetch branch-jeddah 800 "$(P 36018819 800)"
fetch branch-riyadh 800 "$(U 1501339847302-ac426a4a7cbb 800)"

fetch gallery-pour 800 "$(P 7593507 800)"
fetch gallery-latte-art 800 "$(P 30725012 800)"
fetch gallery-beans 800 "$(P 38915876 800)"
fetch gallery-pastries 800 "$(U 1555507036-ab1f4038808a 800)"
fetch gallery-team 800 "$(P 4353572 800)"

# Menu + POS catalog
fetch espresso 800 "$(U 1510591509098-f4fdc6d0ff04 800)"
fetch flat-white 800 "$(P 312418 800)"
fetch cardamom-latte 800 "$(P 18995458 800)"
fetch arabic-coffee 800 "$(P 30146018 800)"
fetch iced-latte 800 "$(P 20220698 800)"
fetch cold-brew 800 "$(P 18997241 800)"
fetch date-maamoul 800 "$(P 30068449 800)"
fetch cheese-sambusa 800 "$(P 40535787 800)"

# About (barista latte pour) — visit.jpg left as-is (already a café interior)
fetch about 800 "$(P 9050526 800)"

# Category chips (menu landing)
fetch category-hot 800 "$(U 1509042239860-f550ce710b93 800)"
fetch category-cold 800 "$(P 20220698 800)"
fetch category-bites 800 "$(U 1555507036-ab1f4038808a 800)"

rm -rf "$TMP"
find "$OUT" -maxdepth 1 -name '*.webp' | wc -l
