#!/usr/bin/env bash
# Frissítési teszt: az éles oldal útja. Friss WordPressre a RÉGI (telepített) verzió kerül a saját importjával, majd a mostani kód váltja le,
# és lefutnak a függőben lévő migrációk – pontosan úgy, ahogy a deploy teszi az élő oldalon. Utána füstteszt minden ügynökségre, doctor,
# a toplista-URL-ek változatlansága, 301 a régi címke-címekről, és a migrációk változáslistája (a felhasználónak jóváhagyásra).
#   bash scripts/ci-wp-upgrade.sh [hu|de] [régi commit]     (alap: hu, a 1.2.0 = d25a2e3)
set -euo pipefail
trap 'echo "::error title=ci-wp-upgrade ${1:-hu}::$LINENO. sor: $BASH_COMMAND"' ERR
SITE="${1:-hu}"
OLD="${2:-${OLD_COMMIT:-d25a2e3}}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"
PORT="${PORT:-8897}"
WP="${WP_CLI:-php $WORK/wp-cli.phar} --allow-root --path=$WORK/wp"
cd "$WORK"

[ -n "${WP_CLI:-}" ] || [ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
[ -f wp/wp-includes/version.php ] || { mkdir -p wp && $WP core download --quiet --skip-content --version="${WP_VERSION:-latest}" --locale=en_US; }
mkdir -p wp/wp-content/plugins wp/wp-content/themes
DB_NAME="${DB_NAME:-kompasz_up_$SITE}"; DB_USER="${DB_USER:-root}"; DB_PASSWORD="${DB_PASSWORD:-root}"; DB_HOST="${DB_HOST:-127.0.0.1}"
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
$WP option update home "http://127.0.0.1:$PORT" --quiet; $WP option update siteurl "http://127.0.0.1:$PORT" --quiet
[ -d wp/wp-content/plugins/contact-form-7 ] || { [ -f cf7.zip ] || curl -sSLfo cf7.zip https://downloads.wordpress.org/plugin/contact-form-7.zip; unzip -qo cf7.zip -d wp/wp-content/plugins; }

# --- 1. A régi verzió (mint az élő oldalon), a saját importjával ---
OLDSRC="$WORK/old-$OLD"
rm -rf "$OLDSRC"; git -C "$ROOT" worktree prune; git -C "$ROOT" worktree add -f --detach "$OLDSRC" "$OLD" >/dev/null
ln -s "$ROOT/node_modules" "$OLDSRC/node_modules" 2>/dev/null || true
if [ "$SITE" = de ]; then (cd "$OLDSRC" && bash scripts/zip-wp.sh de >/dev/null); OLDPLUGIN="$OLDSRC/build/de/kompasz-core"; else (cd "$OLDSRC" && node scripts/export-wp.mjs >/dev/null); OLDPLUGIN="$OLDSRC/wordpress/kompasz-core"; fi
cp -r "$OLDPLUGIN" wp/wp-content/plugins/kompasz-core
cp -r "$OLDSRC/wordpress/kompasz" wp/wp-content/themes/kompasz
OLDVER=$(grep -oE "KOMPASZ_CORE_VERSION', '[0-9.]+" wp/wp-content/plugins/kompasz-core/kompasz-core.php | grep -oE '[0-9.]+$')
$WP plugin activate kompasz-core contact-form-7 --quiet
$WP theme activate kompasz --quiet
$WP rewrite structure '/%postname%/' --quiet
$WP kompasz import | tail -1
$WP kompasz migrate | tail -1
$WP kompasz setup >/dev/null
echo "régi verzió: kompasz-core $OLDVER (commit $OLD), séma: $($WP option get kompasz_schema)"
# A régi szerkezet jellemzői (ezeknek kell átalakulniuk)
OLD_TOPLISTS=$($WP eval 'echo count(get_terms(["taxonomy" => "toplista", "hide_empty" => false]) ?: []);')
OLD_URLS=$($WP eval 'foreach (get_terms(["taxonomy" => "toplista", "hide_empty" => false]) ?: [] as $t) echo get_term_link($t), " ", count(get_objects_in_term($t->term_id, "toplista")), "\n";')
OLD_SERVICES_META=$($WP eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = \"kompasz_services\"");')
AGENCIES=$($WP post list --post_type=ugynokseg --post_status=any --format=count)
echo "régi szerkezet: $OLD_TOPLISTS toplista-kifejezés, $OLD_SERVICES_META kompasz_services meta, $AGENCIES ügynökség"
[ "$OLD_TOPLISTS" -gt 0 ] || { echo "::error::a régi verzióban nincs toplista-taxonómia – nem frissítési teszt"; exit 1; }
git -C "$ROOT" worktree remove --force "$OLDSRC" >/dev/null 2>&1 || true

# --- 2. Csere a mostani kódra (= deploy: feltöltés felülírással), migráció, beállítás ---
if [ "$SITE" = de ]; then NEWPLUGIN="$ROOT/build/de/kompasz-core"; else NEWPLUGIN="$ROOT/wordpress/kompasz-core"; fi
[ -f "$NEWPLUGIN/import/data.json" ] || { echo "Hiányzik: $NEWPLUGIN/import/data.json (előbb: bash scripts/zip-wp.sh $SITE)"; exit 1; }
rm -rf wp/wp-content/plugins/kompasz-core wp/wp-content/themes/kompasz
cp -r "$NEWPLUGIN" wp/wp-content/plugins/kompasz-core
cp -r "$ROOT/wordpress/kompasz" wp/wp-content/themes/kompasz
for slug in seo-by-rank-math wps-hide-login; do
  [ -d "wp/wp-content/plugins/$slug" ] || { [ -f "$slug.zip" ] || curl -sSLfo "$slug.zip" "https://downloads.wordpress.org/plugin/$slug.zip"; unzip -qo "$slug.zip" -d wp/wp-content/plugins; }
done
$WP plugin activate seo-by-rank-math wps-hide-login --quiet
$WP option update whl_page pengemedia --quiet
$WP kompasz migrate
$WP kompasz setup >/dev/null
$WP eval-file "$ROOT/scripts/wp/smoke.php"
$WP kompasz doctor || true

# --- 3. Ellenőrzés: a szerkezet átalakult, az URL-ek maradtak, 301 a régi címke-címekről ---
fail=0
NEW_TOPLISTS=$($WP eval 'echo count(get_terms(["taxonomy" => "toplista", "hide_empty" => false]) ?: []);')
NEW_SERVICES_META=$($WP eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = \"kompasz_services\"");')
[ "$NEW_TOPLISTS" = 0 ] && [ "$NEW_SERVICES_META" = 0 ] && echo "OK a régi toplista-kifejezések ($OLD_TOPLISTS) és a kompasz_services meta ($OLD_SERVICES_META) eltűntek" || { echo "::error::régi szerkezet maradt: $NEW_TOPLISTS toplista, $NEW_SERVICES_META kompasz_services"; fail=1; }
for old in kompasz_county kompasz_languages kompasz_city kompasz_work_mode kompasz_price_model kompasz_certs kompasz_industries kompasz_references; do
  n=$($WP eval "global \$wpdb; echo (int) \$wpdb->get_var(\"SELECT COUNT(*) FROM \$wpdb->postmeta WHERE meta_key = '$old' AND meta_value <> ''\");")
  [ "$n" = 0 ] || { echo "::error::régi szöveges mező maradt: $old ($n)"; fail=1; }
done
# Minden régi toplista-URL ugyanannyi (vagy több) céggel él tovább szolgáltatásként
php -S "127.0.0.1:$PORT" -t wp > server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 1
while read -r url n; do
  [ -n "$url" ] || continue
  code=$(curl -s -o page.html -w '%{http_code}' "$url")
  m=$($WP eval "\$p = trim(parse_url('$url', PHP_URL_PATH), '/'); foreach (kompasz_categories() as \$t) if (kompasz_toplist_slug(\$t) === \$p) { echo count(kompasz_list(\$t)); exit; } echo -1;")
  if [ "$code" = 200 ] && [ "$m" -ge "$n" ] && ! grep -qiE 'critical error|Fatal error|Warning:|Notice:' page.html; then echo "OK $url: $n → $m cég"; else echo "::error::toplista $url: HTTP $code, $n → $m cég"; fail=1; fi
done <<< "$OLD_URLS"
TOP=$($WP eval 'echo get_term_link(kompasz_categories()[0]);')
OLD_URL=$($WP eval '$t = kompasz_categories()[0]; echo home_url("/" . kompasz_slug("cimke") . "/" . $t->slug . "/");')
loc=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$OLD_URL")
if [ "$loc" = "301 $TOP" ]; then echo "OK 301 $OLD_URL → $TOP"; else echo "::error::régi címke-cím: $OLD_URL → $loc"; fail=1; fi
# Összevont kifejezések régi címe → 301
RED=$($WP eval '$m = (array) get_option("kompasz_redirects", []); echo count($m), " ", array_key_first($m) ?? "";')
set -- $RED
if [ "${1:-0}" -gt 0 ]; then
  loc=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "http://127.0.0.1:$PORT$2")
  case "$loc" in 301\ http*) echo "OK $1 átirányítás összevont kifejezésekről, pl. $2 → ${loc#301 }";; *) echo "::error::összevont kifejezés régi címe: $2 → $loc"; fail=1;; esac
fi
for url in "http://127.0.0.1:$PORT/" "$($WP post list --post_type=ugynokseg --field=url --posts_per_page=1)"; do
  code=$(curl -s -o page.html -w '%{http_code}' "$url")
  if [ "$code" != 200 ] || grep -qiE 'critical error|Fatal error|Warning:|Notice:' page.html; then echo "::error::HTTP $code $url"; fail=1; else echo "OK $code $url"; fi
done
if [ -f wp/wp-content/debug.log ] && grep -E 'PHP (Fatal|Warning|Notice|Deprecated).*kompasz' wp/wp-content/debug.log; then
  grep -E 'PHP (Fatal|Warning|Notice|Deprecated).*kompasz' wp/wp-content/debug.log | head -5 | sed 's/^/::error::/'; fail=1
fi
# A migrációk változáslistája (= GET /wp-json/kompasz/v1/migration-log)
echo; echo "=== Migrációk változáslistája ($OLDVER → $($WP eval 'echo KOMPASZ_CORE_VERSION;'), séma $($WP option get kompasz_schema)) ==="
$WP eval 'foreach ((array) get_option("kompasz_migration_log", []) as $l) { printf("\n[%d] %s\n", $l["version"], $l["label"]); foreach ($l["changes"] as $c) echo "  - $c\n"; }'
exit $fail
