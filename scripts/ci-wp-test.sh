#!/usr/bin/env bash
# Integrációs teszt friss WordPressen (SQLite), a GitHub Actions és helyben is futtatható:
#   bash scripts/ci-wp-test.sh [hu|de]
# Lépések: WordPress + SQLite telepítés → Contact Form 7 → Kompasz bővítmény és téma → adatimport →
#          migráció + beállítás → füstteszt minden ügynökségre → doctor → a fő oldalak HTTP 200 és PHP-hiba nélkül.
set -euo pipefail
SITE="${1:-hu}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"
PORT="${PORT:-8899}"
WP="php $WORK/wp-cli.phar --allow-root --path=$WORK/wp"
cd "$WORK"

[ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
mkdir -p wp && $WP core download --quiet --skip-content --version="${WP_VERSION:-latest}" --locale=en_US
mkdir -p wp/wp-content/plugins wp/wp-content/themes
# SQLite adatbázis (nincs szükség MySQL-re)
curl -sSLo sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
unzip -qo sqlite.zip -d wp/wp-content/plugins
sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WORK/wp/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
  wp/wp-content/plugins/sqlite-database-integration/db.copy > wp/wp-content/db.php
$WP config create --dbname=wp --dbuser=x --dbpass=x --skip-check --quiet --extra-php <<'PHP'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('DISABLE_WP_CRON', true);
PHP
$WP core install --url="http://127.0.0.1:$PORT" --title=Kompasz --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email --quiet

curl -sSLo cf7.zip https://downloads.wordpress.org/plugin/contact-form-7.zip
unzip -qo cf7.zip -d wp/wp-content/plugins

# A teszt ugyanazt a csomagot használja, amit a deploy feltölt
if [ "$SITE" = de ]; then PLUGIN="$ROOT/build/de/kompasz-core"; else PLUGIN="$ROOT/wordpress/kompasz-core"; fi
[ -f "$PLUGIN/import/data.json" ] || { echo "Hiányzik: $PLUGIN/import/data.json (előbb: bash scripts/zip-wp.sh $SITE)"; exit 1; }
cp -r "$PLUGIN" wp/wp-content/plugins/kompasz-core
cp -r "$ROOT/wordpress/kompasz" wp/wp-content/themes/kompasz
$WP plugin activate contact-form-7 kompasz-core --quiet
$WP theme activate kompasz --quiet
$WP rewrite structure '/%postname%/' --quiet
$WP kompasz import
$WP kompasz migrate
$WP kompasz setup
$WP eval-file "$ROOT/scripts/wp/smoke.php"
$WP kompasz doctor || true   # a teszt-WordPressben nincs Google API-kulcs: a doctor itt csak tájékoztat

# HTTP: fő oldalak, egy toplista, egy ügynökség-profil (CF7 űrlappal)
php -S "127.0.0.1:$PORT" -t wp > server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 1
TOP=$($WP term list toplista --field=slug --number=1)
AGENCY=$($WP post list --post_type=ugynokseg --field=url --posts_per_page=1)
fail=0
for url in "http://127.0.0.1:$PORT/" "http://127.0.0.1:$PORT/$TOP/" "$AGENCY"; do
  code=$(curl -s -o page.html -w '%{http_code}' "$url")
  if [ "$code" != 200 ] || grep -qiE 'critical error|Fatal error|Warning:|Notice:' page.html; then echo "HIBA: $url ($code)"; fail=1; else echo "OK $code $url"; fi
done
grep -q 'wpcf7' page.html && echo "OK ajánlatkérő űrlap a profilon" || { echo "HIBA: nincs ajánlatkérő űrlap a profilon"; fail=1; }
if [ -f wp/wp-content/debug.log ] && grep -E 'PHP (Fatal|Warning|Notice|Deprecated).*kompasz' wp/wp-content/debug.log; then echo "HIBA: PHP-hibák a debug.log-ban"; fail=1; fi
exit $fail
