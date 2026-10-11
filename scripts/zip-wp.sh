#!/usr/bin/env bash
# WordPress-csomagok: dist/kompasz-core.zip (bővítmény + importadatok) és dist/kompasz.zip (téma)
# A WP adminban: Bővítmények → Új hozzáadása → Feltöltés, illetve Megjelenés → Témák → Feltöltés
set -euo pipefail
cd "$(dirname "$0")/.."
[ -d node_modules/sharp ] || npm i --no-save --no-package-lock --silent sharp@0.33.5
node scripts/export-wp.mjs
rm -rf dist && mkdir -p dist
(cd wordpress && zip -qr ../dist/kompasz-core.zip kompasz-core -x '*.DS_Store' && zip -qr ../dist/kompasz.zip kompasz -x '*.DS_Store')
ls -lh dist
