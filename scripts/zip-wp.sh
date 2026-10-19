#!/usr/bin/env bash
# WordPress-csomagok:
#   bash scripts/zip-wp.sh      → dist/kompasz-core.zip (bővítmény + magyar importadatok) és dist/kompasz.zip (téma)
#   bash scripts/zip-wp.sh de   → dist/de/kompasz-core.zip (bővítmény + német importadatok) és dist/de/kompasz.zip
# A WP adminban: Bővítmények → Új hozzáadása → Feltöltés, illetve Megjelenés → Témák → Feltöltés
set -euo pipefail
cd "$(dirname "$0")/.."
[ -d node_modules/sharp ] || npm i --no-save --no-package-lock --silent sharp@0.33.5
if [ "${1:-hu}" = "de" ]; then
  rm -rf build/de dist/de && mkdir -p build/de dist/de
  cp -r wordpress/kompasz-core build/de/ && rm -rf build/de/kompasz-core/import
  node scripts/export-wp-de.mjs build/de/kompasz-core/import
  (cd build/de && zip -qr ../../dist/de/kompasz-core.zip kompasz-core -x '*.DS_Store')
  (cd wordpress && zip -qr ../dist/de/kompasz.zip kompasz -x '*.DS_Store')
  ls -lh dist/de
  exit 0
fi
node scripts/export-wp.mjs
rm -f dist/*.zip && mkdir -p dist
(cd wordpress && zip -qr ../dist/kompasz-core.zip kompasz-core -x '*.DS_Store' && zip -qr ../dist/kompasz.zip kompasz -x '*.DS_Store')
ls -lh dist
