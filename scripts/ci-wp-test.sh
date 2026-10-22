#!/usr/bin/env bash
# Integrációs teszt friss WordPressen (MySQL), a GitHub Actions és helyben is futtatható:
#   bash scripts/ci-wp-test.sh [hu|de]
# Lépések: WordPress telepítés → Contact Form 7 → Kompasz bővítmény és téma → adatimport →
#          migráció + beállítás → füstteszt minden ügynökségre → doctor → a fő oldalak HTTP 200 és PHP-hiba nélkül.
set -euo pipefail
# Hiba esetén GitHub-annotáció a hibás paranccsal (a futás összefoglalóján is látszik)
trap 'echo "::error title=ci-wp-test ${1:-hu}::$LINENO. sor: $BASH_COMMAND"' ERR
SITE="${1:-hu}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"
PORT="${PORT:-8899}"
WP="${WP_CLI:-php $WORK/wp-cli.phar} --allow-root --path=$WORK/wp"
cd "$WORK"

[ -n "${WP_CLI:-}" ] || [ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
[ -f wp/wp-includes/version.php ] || { mkdir -p wp && $WP core download --quiet --skip-content --version="${WP_VERSION:-latest}" --locale=en_US; }
mkdir -p wp/wp-content/plugins wp/wp-content/themes
# MySQL/MariaDB, mint az éles tárhelyen (GitHub Actions: előtelepített MySQL; helyben bármely MariaDB)
DB_NAME="${DB_NAME:-kompasz_ci_$SITE}"; DB_USER="${DB_USER:-root}"; DB_PASSWORD="${DB_PASSWORD:-root}"; DB_HOST="${DB_HOST:-127.0.0.1}"
rm -f wp/wp-config.php
rm -rf wp/wp-content/plugins/kompasz-core wp/wp-content/themes/kompasz wp/wp-content/uploads wp/wp-content/debug.log
$WP config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST" --skip-check --quiet --extra-php <<'PHP'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('DISABLE_WP_CRON', true);
PHP
$WP db reset --yes --quiet 2>/dev/null || $WP db create --quiet
$WP core install --url="http://127.0.0.1:$PORT" --title=Kompasz --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email --quiet
# A php -S a wp mappát szolgálja ki a gyökérben: a cím ne kapjon /wp/ előtagot
$WP option update home "http://127.0.0.1:$PORT" --quiet; $WP option update siteurl "http://127.0.0.1:$PORT" --quiet

[ -d wp/wp-content/plugins/contact-form-7 ] || [ -f cf7.zip ] || curl -sSLfo cf7.zip https://downloads.wordpress.org/plugin/contact-form-7.zip
[ -d wp/wp-content/plugins/contact-form-7 ] || unzip -qo cf7.zip -d wp/wp-content/plugins

# A teszt ugyanazt a csomagot használja, amit a deploy feltölt
if [ "$SITE" = de ]; then PLUGIN="$ROOT/build/de/kompasz-core"; else PLUGIN="$ROOT/wordpress/kompasz-core"; fi
[ -f "$PLUGIN/import/data.json" ] || { echo "Hiányzik: $PLUGIN/import/data.json (előbb: bash scripts/zip-wp.sh $SITE)"; exit 1; }
cp -r "$PLUGIN" wp/wp-content/plugins/kompasz-core
cp -r "$ROOT/wordpress/kompasz" wp/wp-content/themes/kompasz
# Hibánál a WP-CLI üzenete is annotációba kerül
act() { local out; out=$($WP plugin activate "$1" 2>&1) || { echo "::error title=plugin $1::$(echo "$out" | tr '\n' ' ' | cut -c1-500)"; return 1; }; }
# Rank Math és WPS Hide Login (mint az éles oldalon): a Kompasz velük együtt is hibátlan, a belépés a /pengemedia/ címen
for slug in seo-by-rank-math wps-hide-login; do
  [ -d "wp/wp-content/plugins/$slug" ] || { [ -f "$slug.zip" ] || curl -sSLfo "$slug.zip" "https://downloads.wordpress.org/plugin/$slug.zip"; unzip -qo "$slug.zip" -d wp/wp-content/plugins; }
done
act kompasz-core
act contact-form-7
act seo-by-rank-math
act wps-hide-login
$WP option update whl_page pengemedia --quiet
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
# Toplista = szolgáltatás: egy toplista oldal, egy toplista nélküli szolgáltatás, és a toplistás szolgáltatás régi címe (301 → toplista)
TOP_URL=$($WP eval 'echo get_term_link(kompasz_categories()[0]);')
SVC_URL=$($WP eval 'foreach (get_terms(["taxonomy" => "szolgaltatas", "hide_empty" => true]) as $t) if (!kompasz_is_toplist($t)) { echo get_term_link($t); break; }')
OLD_URL=$($WP eval '$t = kompasz_categories()[0]; echo home_url("/" . kompasz_slug("cimke") . "/" . $t->slug . "/");')
AGENCY=$($WP post list --post_type=ugynokseg --field=url --posts_per_page=1)
fail=0
loc=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$OLD_URL")
if [ "$loc" = "301 $TOP_URL" ]; then echo "OK 301 $OLD_URL → $TOP_URL"; else echo "::error::régi címke-cím: $OLD_URL → $loc (várt: 301 $TOP_URL)"; fail=1; fi
for url in "http://127.0.0.1:$PORT/" "$TOP_URL" "$SVC_URL" "$AGENCY"; do
  code=$(curl -s -o page.html -w '%{http_code}' "$url")
  if [ "$code" != 200 ] || grep -qiE 'critical error|Fatal error|Warning:|Notice:' page.html; then echo "::error::HTTP $code $url $(grep -oiE '(Fatal error|Warning|Notice):[^<]{0,200}' page.html | head -1)"; fail=1; else echo "OK $code $url"; fi
done
grep -q 'wpcf7' page.html && echo "OK ajánlatkérő űrlap a profilon" || { echo "HIBA: nincs ajánlatkérő űrlap a profilon"; fail=1; }
grep -qi 'rankmath' page.html && grep -q 'og:title' page.html && echo "OK Rank Math a profilon" || { echo "::error::nincs Rank Math kimenet a profilon"; fail=1; }
grep -q 'kmap-pub\|id="helyszin"' page.html && echo "OK térkép a profilon" || echo "figyelem: a profilon nincs térkép (nincs pozíció – a teszt nem kérdezi le a Nominatimot)"
HOME_URL=$($WP option get home)
code=$(curl -s -o login.html -w '%{http_code}' "$HOME_URL/pengemedia/")
[ "$code" = 200 ] && grep -q 'name="log"' login.html && echo "OK belépés: /pengemedia/" || { echo "::error::a /pengemedia/ nem a belépési oldal (HTTP $code)"; fail=1; }
# Admin adatlap: minden lista középen (keresős pipalista), a jobb oldalon nincs lista-doboz, nincs beírós lista
curl -s -c jar -b jar -o /dev/null "$HOME_URL/pengemedia/"
curl -s -c jar -b jar -b 'wordpress_test_cookie=WP%20Cookie%20check' -o /dev/null --data 'log=admin&pwd=admin&testcookie=1' "$HOME_URL/pengemedia/"
AID=$($WP post list --post_type=ugynokseg --field=ID --posts_per_page=1)
curl -s -b jar -o edit.html "http://127.0.0.1:$PORT/wp-admin/post.php?post=$AID&action=edit"
for tax in varos megye munkavegzes arazas nyelv szolgaltatas minosites iparag ugyfel; do grep -q "data-tax=\"$tax\"" edit.html || { echo "::error::admin adatlap: hiányzik a(z) $tax lista"; fail=1; }; done
BOX='id="(tagsdiv-(toplista|szolgaltatas|ugyfel|minosites|iparag|megye|nyelv|varos|munkavegzes|arazas)|(toplista|szolgaltatas|ugyfel|minosites|iparag|megye|nyelv|varos|munkavegzes|arazas)div)"'
if grep -qE "$BOX" edit.html; then echo "::error::admin adatlap: lista-doboz a jobb oldalon: $(grep -oE "$BOX" edit.html | head -3 | tr '\n' ' ')"; fail=1; else echo "OK admin adatlap: minden lista középen, jobb oldalon nincs lista-doboz"; fi
code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/wp-login.php")
[ "$code" = 404 ] && echo "OK wp-login.php → 404" || { echo "::error::a wp-login.php nem rejtett (HTTP $code)"; fail=1; }
if [ -f wp/wp-content/debug.log ] && grep -E 'PHP (Fatal|Warning|Notice|Deprecated).*kompasz' wp/wp-content/debug.log; then
  grep -E 'PHP (Fatal|Warning|Notice|Deprecated).*kompasz' wp/wp-content/debug.log | head -5 | sed 's/^/::error::/'; fail=1
fi
exit $fail
