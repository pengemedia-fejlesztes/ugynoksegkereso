#!/usr/bin/env bash
# A szerveren fut (scripts/deploy.sh tölti fel és indítja): bash ~/kompasz-deploy/remote-deploy.sh <hu|de> <wp gyökér> <url> <commit>
# Előfeltétel: ~/kompasz-deploy/<site>/plugin és /theme (az új kód), ~/kompasz-deploy/<site>/.env (titkok, futás után törlődik).
set -euo pipefail
SITE="$1"; WPROOT="$2"; URL="$3"; SHA="$4"
D=~/kompasz-deploy; S="$D/$SITE"; BK=~/kompasz-backups; STAMP="$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BK" ~/bin
# shellcheck disable=SC1090
set -a; . "$S/.env"; set +a; rm -f "$S/.env"
log() { echo "[$SITE] $*"; }

# WP-CLI (ha a tárhelyen nincs)
if command -v wp >/dev/null; then WPBIN="$(command -v wp)"; else
  [ -x ~/bin/wp ] || { curl -sSLo ~/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ~/bin/wp; }
  WPBIN=~/bin/wp
fi
wp() { "$WPBIN" --path="$WPROOT" "$@"; }

# --- Új oldal: WordPress letöltése, wp-config, telepítés ---
if ! wp core is-installed 2>/dev/null; then
  [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ] && [ -n "${DB_PASSWORD:-}" ] || { echo "Nincs WordPress a $WPROOT mappában, és nincs megadva adatbázis (WP_DB_*_${SITE^^})."; exit 1; }
  LOC=$([ "$SITE" = de ] && echo de_DE || echo hu_HU)
  log "új WordPress telepítése ($LOC)"
  mkdir -p "$WPROOT"
  [ -f "$WPROOT/wp-includes/version.php" ] || wp core download --locale="$LOC" --quiet
  [ -f "$WPROOT/wp-config.php" ] || wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="${DB_HOST:-localhost}" --locale="$LOC" --quiet
  wp db create 2>/dev/null || true
  wp core install --url="$URL" --title=Kompasz --admin_user=kompasz-admin --admin_email="${ADMIN_EMAIL:-admin@pmdev.hu}" --skip-email --quiet
  wp option update blog_public 0 --quiet      # fejlesztés alatt a keresőrobotok kitiltva
  wp rewrite structure '/%postname%/' --quiet
  NEW_SITE=1
  log "admin felhasználó: kompasz-admin (jelszó: 'Elfelejtett jelszó' → ${ADMIN_EMAIL:-admin@pmdev.hu})"
fi

PL="$(wp plugin path)"; TH="$(wp theme path)"
# --- Mentés: adatbázis + a jelenlegi kód ---
DUMP="$BK/$SITE-$STAMP-${SHA:0:7}.sql.gz"
wp db export - --quiet | gzip > "$DUMP"
log "adatbázis-mentés: $DUMP ($(du -h "$DUMP" | cut -f1))"
ls -1t "$BK"/"$SITE"-*.sql.gz 2>/dev/null | tail -n +21 | xargs -r rm -f   # az utolsó 20 marad
rm -rf "$PL/kompasz-core.prev" "$TH/kompasz.prev"

rollback() {
  local code=$?
  [ $code -eq 0 ] && return
  log "HIBA ($code) – visszaállítás"
  if [ -d "$PL/kompasz-core.prev" ]; then rm -rf "$PL/kompasz-core"; mv "$PL/kompasz-core.prev" "$PL/kompasz-core"; fi
  if [ -d "$TH/kompasz.prev" ]; then rm -rf "$TH/kompasz"; mv "$TH/kompasz.prev" "$TH/kompasz"; fi
  if [ "${MIGRATING:-0}" = 1 ]; then log "adatbázis visszaállítása: $DUMP"; gunzip -c "$DUMP" | wp db import - --quiet; fi
  wp cache flush --quiet 2>/dev/null || true
  log "visszaállítva az előző állapotra"
  exit $code
}
trap rollback EXIT

# --- Csere: az új kód a helyére (a régi .prev néven, visszaállításhoz) ---
copy_in() { # forrás cél: előbb a cél mellé másol, utána két gyors átnevezés
  rm -rf "$2.new"; cp -a "$1" "$2.new"
  if [ -e "$2" ]; then mv "$2" "$2.prev"; fi
  mv "$2.new" "$2"
}
copy_in "$S/plugin" "$PL/kompasz-core"
copy_in "$S/theme" "$TH/kompasz"
log "kód kicserélve ($(cat "$PL/kompasz-core/build.json" 2>/dev/null))"

# --- Bővítmények és téma ---
wp plugin activate kompasz-core --quiet
# Contact Form 7: a szerver PHP/WordPress verziójával kompatibilis legújabb kiadás (a legújabb CF7 pl. PHP 8.3-at kér)
cf7_ok() { wp plugin activate contact-form-7 --quiet >/dev/null 2>&1; }
if ! { wp plugin is-installed contact-form-7 && cf7_ok; }; then
  wp plugin install contact-form-7 --force --quiet >/dev/null 2>&1 || true
  if ! cf7_ok; then
    for ver in $(curl -gsSf 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=contact-form-7&request[fields][versions]=1' \
        | php -r '$v = array_keys((array) (json_decode(stream_get_contents(STDIN), true)["versions"] ?? [])); $v = array_filter($v, fn($x) => preg_match("/^\d+(\.\d+)+$/", $x)); usort($v, "version_compare"); echo implode(" ", array_slice(array_reverse($v), 0, 25));'); do
      wp plugin install contact-form-7 --version="$ver" --force --quiet >/dev/null 2>&1 && cf7_ok && { log "Contact Form 7 $ver (kompatibilis a szerverrel)"; break; }
    done
  fi
  cf7_ok || { echo "A Contact Form 7 egyik verziója sem kapcsolható be ezen a szerveren (PHP $(php -r 'echo PHP_VERSION;'))."; exit 1; }
fi
wp theme activate kompasz --quiet
# Google Places API-kulcs a wp-config.php-ba (nem az adatbázisba; az admin felületen nem olvasható ki)
if [ -n "${GOOGLE_PLACES_API_KEY:-}" ]; then
  wp config set KOMPASZ_GOOGLE_API_KEY "$GOOGLE_PLACES_API_KEY" --type=constant --quiet
  wp option delete kompasz_google_api_key --quiet 2>/dev/null || true
fi

# --- Adatok és adatbázis-migráció ---
MIGRATING=1
if [ "${NEW_SITE:-0}" = 1 ] || [ -z "$(wp option get kompasz_imported 2>/dev/null)" ]; then
  log "Kompasz-adatok importja"; wp kompasz import
fi
wp kompasz migrate
MIGRATING=0
wp kompasz setup
wp rewrite flush --quiet
wp cache flush --quiet 2>/dev/null || true

# --- Ellenőrzés: minden ügynökség adatlapja, állapot ---
wp eval-file "$D/smoke.php"
wp kompasz doctor
# Első Google-szinkron (utána hetente automatikusan)
if wp eval 'exit(kompasz_google_key() && !get_option("kompasz_google_last_run") ? 0 : 1);' 2>/dev/null; then
  log "első Google-értékelés szinkron"; wp kompasz google-sync || log "figyelmeztetés: a Google-szinkron nem sikerült teljesen (részletek fent)"
fi
# Mintaoldal a külső ellenőrzéshez
wp post list --post_type=ugynokseg --posts_per_page=1 --field=url | sed "s#^$URL##" > "$S/sample-path"
trap - EXIT
rm -rf "$PL/kompasz-core.prev" "$TH/kompasz.prev"
log "kész – commit ${SHA:0:7}"
