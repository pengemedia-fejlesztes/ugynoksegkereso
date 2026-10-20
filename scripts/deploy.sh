#!/usr/bin/env bash
# Telepítés egy WordPress-oldalra SSH-n (a GitHub Actions „deploy” lépése futtatja, de kézzel is indítható):
#   bash scripts/deploy.sh hu|de
#
# Környezeti változók (GitHub: Settings → Secrets and variables → Actions → Secrets):
#   WP_SSH_HOST, WP_SSH_USER, WP_SSH_KEY (privát kulcs, ajánlott) vagy WP_SSH_PASSWORD; WP_SSH_PORT (alap: 22)
#   WP_PATH_HU / WP_PATH_DE    a WordPress gyökérmappája a szerveren
#   WP_URL_HU / WP_URL_DE      az oldal címe (alap: https://ugynoksegek.pmdev.hu, https://de.ugynoksegek.pmdev.hu)
#   GOOGLE_PLACES_API_KEY      Places API (New) kulcs → wp-config.php: KOMPASZ_GOOGLE_API_KEY
#   Új oldalhoz (ha a mappában még nincs WordPress): WP_DB_NAME_<HU|DE>, WP_DB_USER_<..>, WP_DB_PASSWORD_<..>, WP_DB_HOST_<..> (alap: localhost), WP_ADMIN_EMAIL
#
# A szerveren a scripts/wp/remote-deploy.sh fut: mentés → csere → Contact Form 7 → API-kulcs → migráció → beállítás → füstteszt → doctor,
# hiba esetén automatikus visszaállítás. Végül innen is ellenőrizzük, hogy az oldal kívülről elérhető és hibamentes.
set -euo pipefail
SITE="${1:?hu vagy de}"
UP=$(echo "$SITE" | tr '[:lower:]' '[:upper:]')
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
v() { local n="$1"; printf '%s' "${!n:-}"; }
WPROOT="$(v "WP_PATH_$UP")"
URL="$(v "WP_URL_$UP")"; [ -n "$URL" ] || URL=$([ "$SITE" = de ] && echo https://de.ugynoksegek.pmdev.hu || echo https://ugynoksegek.pmdev.hu)
if [ -z "${WP_SSH_HOST:-}" ] || [ -z "${WP_SSH_USER:-}" ] || [ -z "$WPROOT" ] || { [ -z "${WP_SSH_KEY:-}" ] && [ -z "${WP_SSH_PASSWORD:-}" ]; }; then
  echo "::warning::$SITE: nincs megadva szerver-hozzáférés (WP_SSH_HOST, WP_SSH_USER, WP_SSH_KEY vagy WP_SSH_PASSWORD, WP_PATH_$UP) – a telepítés kimarad."
  exit 0
fi

SHA="${GITHUB_SHA:-$(git -C "$ROOT" rev-parse HEAD)}"
OPTS="-p ${WP_SSH_PORT:-22} -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 -o ConnectTimeout=20"
if [ -n "${WP_SSH_KEY:-}" ]; then
  KEYFILE="$(mktemp)"; trap 'rm -f "$KEYFILE"' EXIT
  printf '%s\n' "$WP_SSH_KEY" > "$KEYFILE"; chmod 600 "$KEYFILE"
  SSHCMD="ssh $OPTS -i $KEYFILE -o BatchMode=yes"
else
  command -v sshpass >/dev/null || sudo apt-get install -y -qq sshpass >/dev/null
  export SSHPASS="$WP_SSH_PASSWORD"
  SSHCMD="sshpass -e ssh $OPTS -o PubkeyAuthentication=no -o PreferredAuthentications=password,keyboard-interactive"
fi
TARGET="$WP_SSH_USER@$WP_SSH_HOST"
rssh() { $SSHCMD "$TARGET" "$@"; }

if [ "$SITE" = de ]; then PLUGIN="$ROOT/build/de/kompasz-core/"; else PLUGIN="$ROOT/wordpress/kompasz-core/"; fi
[ -f "$PLUGIN/import/data.json" ] || { echo "Hiányzik az importadat: előbb bash scripts/zip-wp.sh $SITE"; exit 1; }
printf '{"site":"%s","commit":"%s","built":"%s"}\n' "$SITE" "$SHA" "$(date -u +%FT%TZ)" > "$PLUGIN/build.json"

echo "== $SITE → $TARGET:$WPROOT ($URL), commit ${SHA:0:7}"
# 1. Előkészített (staging) mappák a szerveren: a következő telepítés csak a változást tölti fel
rssh "mkdir -p ~/kompasz-deploy/$SITE/plugin ~/kompasz-deploy/$SITE/theme"
rsync -az --delete -e "$SSHCMD" --exclude '.DS_Store' "$PLUGIN" "$TARGET:kompasz-deploy/$SITE/plugin/"
rsync -az --delete -e "$SSHCMD" --exclude '.DS_Store' "$ROOT/wordpress/kompasz/" "$TARGET:kompasz-deploy/$SITE/theme/"
rsync -az -e "$SSHCMD" "$ROOT/scripts/wp/remote-deploy.sh" "$ROOT/scripts/wp/smoke.php" "$TARGET:kompasz-deploy/"

# 2. Titkok: egyszer használatos, csak a felhasználó által olvasható fájlban (nem parancssorban, nem naplóban)
{
  printf 'GOOGLE_PLACES_API_KEY=%q\n' "${GOOGLE_PLACES_API_KEY:-}"
  printf 'DB_NAME=%q\nDB_USER=%q\nDB_PASSWORD=%q\nDB_HOST=%q\n' "$(v "WP_DB_NAME_$UP")" "$(v "WP_DB_USER_$UP")" "$(v "WP_DB_PASSWORD_$UP")" "$(v "WP_DB_HOST_$UP")"
  printf 'ADMIN_EMAIL=%q\n' "${WP_ADMIN_EMAIL:-}"
} | rssh "umask 077; cat > ~/kompasz-deploy/$SITE/.env"

# 3. Telepítés a szerveren
rssh "bash ~/kompasz-deploy/remote-deploy.sh '$SITE' '$WPROOT' '$URL' '$SHA'"

# 4. Kívülről is: a fő oldal és egy ügynökség-profil hibamentes
fail=0
for path in / "$(rssh "cat ~/kompasz-deploy/$SITE/sample-path 2>/dev/null" || echo /)"; do
  code=$(curl -s -L -o /tmp/page.html -w '%{http_code}' -A 'KompaszDeployCheck' "$URL$path")
  if [ "$code" != 200 ] || grep -qiE 'critical error|kritikus hiba|Fatal error' /tmp/page.html; then echo "::error::$URL$path → $code"; fail=1; else echo "OK $code $URL$path"; fi
done
exit $fail
