#!/usr/bin/env bash
# Telepítés HTTPS-en (SSH nélkül): a WordPress adminon és a REST API-n keresztül. Claude Code munkakörnyezetből ez működik
# (ott csak a 443-as port engedélyezett), de bárhonnan futtatható, ahol van bash, curl, php, zip, node.
#   bash scripts/deploy-https.sh hu|de
#
# Környezeti változók (Claude Code: a környezet beállításai → Environment variables; lásd docs/HOZZAFERES.md):
#   WP_URL_HU / WP_URL_DE                     az oldal címe (alap: https://ugynoksegek.pmdev.hu, https://de.ugynoksegek.pmdev.hu)
#   WP_ADMIN_USER_HU, WP_ADMIN_PASSWORD_HU    adminisztrátor (német oldalra: *_DE; ha nincs, WP_ADMIN_USER / WP_ADMIN_PASSWORD)
#   GOOGLE_PLACES_API_KEY                     Places API (New) kulcs (opcionális; a Kompasz beállításai közé kerül)
#   HTTP_AUTH                                 ha az oldal HTTP Basic Auth mögött van: "felhasználó:jelszó" (opcionális)
#   PMDEV_USER, PMDEV_PASSWORD                a pmdev.hu „Fejlesztés alatt” belépési kapuja (/pmdev-belepes.php), ha az oldal mögötte van
#   WP_LOGIN_SLUG                             a WPS Hide Login belépési címe (alap: pengemedia → /pengemedia/; a wp-login.php ezután 404)
#
# Menet: belépés → állapot → adatbázis-mentés → bővítmény és téma feltöltése (felülírással) → Contact Form 7 (kompatibilis verzió) →
#        aktiválás → Rank Math, WPS Hide Login (belépés: /$WP_LOGIN_SLUG/) → API-kulcs → import (üres oldalon) → migráció → beállítás → füstteszt minden ügynökségre → doctor → HTTP-ellenőrzés.
#        Hiba esetén: az előzőleg telepített commit csomagja visszatöltődik, migrációs hibánál az adatbázis is.
set -euo pipefail
SITE="${1:?hu vagy de}"
UP=$(echo "$SITE" | tr '[:lower:]' '[:upper:]')
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
v() { local n="$1"; printf '%s' "${!n:-}"; }
URL="$(v "WP_URL_$UP")"; [ -n "$URL" ] || URL=$([ "$SITE" = de ] && echo https://de.ugynoksegek.pmdev.hu || echo https://master.ugynoksegek.pmdev.hu)
LOGIN_SLUG="${WP_LOGIN_SLUG:-pengemedia}"
URL="${URL%/}"
USER_="$(v "WP_ADMIN_USER_$UP")"; USER_="${USER_:-${WP_ADMIN_USER:-}}"
PASS_="$(v "WP_ADMIN_PASSWORD_$UP")"; PASS_="${PASS_:-${WP_ADMIN_PASSWORD:-}}"
[ -n "$USER_" ] && [ -n "$PASS_" ] || { echo "Hiányzik: WP_ADMIN_USER_$UP / WP_ADMIN_PASSWORD_$UP"; exit 1; }

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
JAR="$TMP/cookies"
CURL=(curl -sS --fail-with-body -b "$JAR" -c "$JAR" -A "KompaszDeploy/1.0" --max-time 300)
[ -n "${HTTP_AUTH:-}" ] && CURL+=(-u "$HTTP_AUTH")
log() { echo "[$SITE] $*"; }
die() { echo "::error::[$SITE] $*"; exit 1; }
json() { php -r '$d = json_decode(stream_get_contents(STDIN), true); $k = $argv[1]; foreach (explode(".", $k) as $p) $d = is_array($d) && array_key_exists($p, $d) ? $d[$p] : null; echo is_bool($d) ? ($d ? "true" : "false") : (is_scalar($d) ? $d : json_encode($d, JSON_UNESCAPED_UNICODE));' "$1"; }

# --- pmdev.hu belépési kapu (ha az oldal „Fejlesztés alatt” áll): a süti 30 napig érvényes ---
code=$(curl -s -o "$TMP/gate.html" -w '%{http_code}' -A "KompaszDeploy/1.0" ${HTTP_AUTH:+-u "$HTTP_AUTH"} "$URL/")
if [ "$code" = 401 ] && grep -q 'pmdev-belepes' "$TMP/gate.html"; then
  [ -n "${PMDEV_USER:-}" ] && [ -n "${PMDEV_PASSWORD:-}" ] || die "Az oldal a pmdev.hu belépési kapuja mögött van: hiányzik PMDEV_USER / PMDEV_PASSWORD."
  curl -sS -o /dev/null -b "$JAR" -c "$JAR" -A "KompaszDeploy/1.0" --data-urlencode "felhasznalo=$PMDEV_USER" --data-urlencode "jelszo=$PMDEV_PASSWORD" --data-urlencode "vissza=/" "$URL/pmdev-belepes.php"
  [ "$(curl -s -b "$JAR" -c "$JAR" -A "KompaszDeploy/1.0" -o /dev/null -w '%{http_code}' "$URL/")" != 401 ] || die "A pmdev.hu kapu nem engedett be ($PMDEV_USER)."
  log "pmdev.hu kapu: belépve"
fi

# --- Belépés (süti) és REST nonce: a WPS Hide Login után a /$LOGIN_SLUG/ címen, előtte a wp-login.php-n ---
LOGIN_URL="$URL/wp-login.php"
if ! curl -s -b "$JAR" -c "$JAR" -A "KompaszDeploy/1.0" ${HTTP_AUTH:+-u "$HTTP_AUTH"} "$LOGIN_URL" | grep -q 'name="log"'; then LOGIN_URL="$URL/$LOGIN_SLUG/"; fi
"${CURL[@]}" -o /dev/null "$LOGIN_URL" || die "Az oldal nem érhető el: $LOGIN_URL"
"${CURL[@]}" -o "$TMP/login.html" -b "wordpress_test_cookie=WP%20Cookie%20check" \
  --data-urlencode "log=$USER_" --data-urlencode "pwd=$PASS_" -d "wp-submit=Log+In&testcookie=1&redirect_to=$URL/wp-admin/" "$LOGIN_URL" -L
grep -q 'wordpress_logged_in' "$JAR" || die "Sikertelen belépés ($USER_)."
NONCE="$("${CURL[@]}" "$URL/wp-admin/admin-ajax.php?action=rest-nonce")"
[[ "$NONCE" =~ ^[a-f0-9]{10}$ ]] || die "Nem kaptam REST nonce-t (válasz: ${NONCE:0:80})."
rest() { # metódus útvonal [json]
  local m="$1" p="$2" body="${3:-}"
  if [ -n "$body" ]; then "${CURL[@]}" -X "$m" -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' --data "$body" "$URL/wp-json$p"
  else "${CURL[@]}" -X "$m" -H "X-WP-Nonce: $NONCE" "$URL/wp-json$p"; fi
}
log "belépve: $URL ($USER_)"

# --- Előző állapot (visszaállításhoz) ---
PREV_COMMIT=""
if DOC="$(rest GET /kompasz/v1/doctor 2>/dev/null)"; then
  PREV_COMMIT="$(echo "$DOC" | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d["rows"] ?? [] as $r) if (preg_match("/\"commit\":\"([0-9a-f]{7,40})\"/", $r["részlet"], $m)) echo $m[1];')"
  log "jelenleg telepítve: ${PREV_COMMIT:-ismeretlen commit}"
  BK="$(rest POST /kompasz/v1/backup '{"label":"pre-deploy"}')" || die "Az adatbázis-mentés nem sikerült: $BK"
  BACKUP="$(echo "$BK" | json file)"; log "adatbázis-mentés: $BACKUP ($(echo "$BK" | json size) bájt)"
else
  BACKUP=""
  log "figyelem: a jelenlegi bővítményben még nincs mentés-funkció (első telepítés az új telepítővel); a migráció 10 perc türelmi idő után futna magától, de most azonnal mentünk, mihelyt az új kód fent van"
fi

# --- Csomagok: csak commitolt és GitHubra feltöltött kód mehet élesbe (az élő oldal = egy commit; visszaállítás commit alapján) ---
SHA="$(git -C "$ROOT" rev-parse HEAD)"
if [ -z "${ALLOW_DIRTY:-}" ]; then
  [ -z "$(git -C "$ROOT" status --porcelain -- wordpress scripts src)" ] || die "Nem commitolt változás van (git status). Előbb commit + push."
  git -C "$ROOT" fetch -q origin main 2>/dev/null || true
  git -C "$ROOT" merge-base --is-ancestor "$SHA" origin/main 2>/dev/null || die "A $SHA commit nincs a GitHubon (origin/main). Előbb push."
fi
build_zips() { # commit kimeneti_mappa
  local c="$1" out="$2" src="$ROOT"
  # Új telepítésnél a munkakönyvtár (tiszta, ellenőrizve fent), visszaállításnál mindig a korábbi commit tiszta másolata
  if [ "$out" != "$TMP/new" ]; then
    src="$TMP/src-$c"; git -C "$ROOT" worktree add -f --detach "$src" "$c" >/dev/null 2>&1 || return 1
    ln -s "$ROOT/node_modules" "$src/node_modules" 2>/dev/null || true
  fi
  (cd "$src" && bash scripts/zip-wp.sh $([ "$SITE" = de ] && echo de) >/dev/null) || return 1
  local d="$src/dist$([ "$SITE" = de ] && echo /de)"
  mkdir -p "$out"
  (cd "$TMP" && rm -rf pkg && mkdir pkg && cd pkg && unzip -q "$d/kompasz-core.zip" && printf '{"site":"%s","commit":"%s","built":"%s"}\n' "$SITE" "$c" "$(date -u +%FT%TZ)" > kompasz-core/build.json && zip -qr "$out/kompasz-core.zip" kompasz-core)
  cp "$d/kompasz.zip" "$out/kompasz.zip"
}
build_zips "$SHA" "$TMP/new" || die "A csomag nem készült el."

# Feltöltés a WP adminon (Bővítmények/Témák → Feltöltés), meglévő felülírásával
upload() { # plugin|theme zip
  local kind="$1" zip="$2" page field
  if [ "$kind" = plugin ]; then page="plugin-install.php?tab=upload"; field=pluginzip; else page="theme-install.php?browse=upload"; field=themezip; fi
  local nonce; nonce="$("${CURL[@]}" "$URL/wp-admin/$page" | tr '\n' ' ' | grep -oE "update\.php\?action=upload-$kind\".{0,1500}" | grep -oE 'name="_wpnonce" value="[a-f0-9]+"' | head -1 | grep -oE '[a-f0-9]{10}' || true)"
  [ -n "$nonce" ] || die "Nem találom a feltöltő űrlapot ($kind)."
  "${CURL[@]}" -o "$TMP/up.html" -F "_wpnonce=$nonce" -F "_wp_http_referer=/wp-admin/$page" -F "$field=@$zip;type=application/zip" -F "install-$kind-submit=1" "$URL/wp-admin/update.php?action=upload-$kind"
  local over; over="$(grep -oE "update\.php\?action=upload-$kind(&amp;|&)package=[0-9]+(&amp;|&)overwrite=update-$kind(&amp;|&)_wpnonce=[a-f0-9]+" "$TMP/up.html" | head -1 | sed 's/&amp;/\&/g')"
  if [ -n "$over" ]; then "${CURL[@]}" -o "$TMP/up2.html" "$URL/wp-admin/$over"; fi
  if grep -qiE 'class="[^"]*(error|notice-error)|(installation|update) failed|telepítés sikertelen|frissítés sikertelen' "$TMP/up.html" "${over:+$TMP/up2.html}" 2>/dev/null; then
    die "A(z) $kind feltöltése nem sikerült: $(grep -oiE '<p>[^<]{0,200}' "$TMP/up.html" | tail -2 | tr '\n' ' ')"
  fi
}
deploy_code() { # mappa
  upload plugin "$1/kompasz-core.zip"
  upload theme "$1/kompasz.zip"
  rest PUT /wp/v2/plugins/kompasz-core/kompasz-core '{"status":"active"}' >/dev/null || die "A kompasz-core nem kapcsolható be."
  local act; act="$("${CURL[@]}" "$URL/wp-admin/themes.php" | grep -oE 'action=activate(&amp;|&)stylesheet=kompasz(&amp;|&)_wpnonce=[a-f0-9]+' | head -1 | sed 's/&amp;/\&/g')"
  [ -n "$act" ] && "${CURL[@]}" -o /dev/null "$URL/wp-admin/themes.php?$act"
  true
}

rollback() {
  local code=$1
  [ "$code" -eq 0 ] && return 0
  echo "::error::[$SITE] hiba ($code) – visszaállítás"
  if [ -n "$PREV_COMMIT" ] && build_zips "$PREV_COMMIT" "$TMP/prev"; then deploy_code "$TMP/prev" && log "kód visszaállítva: $PREV_COMMIT"; fi
  if [ "${MIGRATING:-0}" = 1 ] && [ -n "$BACKUP" ]; then rest POST /kompasz/v1/restore "{\"file\":\"$BACKUP\"}" >/dev/null && log "adatbázis visszaállítva: $BACKUP"; fi
  return 0
}
trap 'code=$?; trap - EXIT; rollback $code || true; rm -rf "$TMP"; git -C "$ROOT" worktree prune; exit $code' EXIT

deploy_code "$TMP/new"
VER="$(rest GET /wp/v2/plugins/kompasz-core/kompasz-core | json version)"; log "kompasz-core $VER feltöltve és bekapcsolva (commit ${SHA:0:7})"
# Első telepítés az új telepítővel: mentés most (a migráció még nem futott a türelmi idő miatt)
if [ -z "$BACKUP" ]; then BK="$(rest POST /kompasz/v1/backup '{"label":"pre-migrate"}')" && BACKUP="$(echo "$BK" | json file)" && log "adatbázis-mentés: $BACKUP"; fi

# --- Bővítmények a wordpress.org-ról, a szerver PHP-jával kompatibilis legújabb verzióban: Contact Form 7, Rank Math, WPS Hide Login ---
plugin_active() { [ "$(rest GET "/wp/v2/plugins/$1" 2>/dev/null | json status)" = active ]; }
ensure_plugin() { # slug fájl (pl. contact-form-7 wp-contact-form-7)
  local slug="$1" file="$1/$2"
  plugin_active "$file" && return 0
  rest POST /wp/v2/plugins "{\"slug\":\"$slug\",\"status\":\"active\"}" >/dev/null 2>&1 || rest PUT "/wp/v2/plugins/$file" '{"status":"active"}' >/dev/null 2>&1 || true
  if ! plugin_active "$file"; then
    for ver in $(curl -gsSf "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=$slug&request[fields][versions]=1" \
        | php -r '$v = array_keys((array) (json_decode(stream_get_contents(STDIN), true)["versions"] ?? [])); $v = array_filter($v, fn($x) => preg_match("/^\d+(\.\d+)+$/", $x)); usort($v, "version_compare"); echo implode(" ", array_slice(array_reverse($v), 0, 25));'); do
      curl -sSfo "$TMP/plugin.zip" "https://downloads.wordpress.org/plugin/$slug.$ver.zip" || continue
      upload plugin "$TMP/plugin.zip"
      rest PUT "/wp/v2/plugins/$file" '{"status":"active"}' >/dev/null 2>&1 && plugin_active "$file" && { log "$slug $ver"; break; }
    done
  fi
  plugin_active "$file" || die "A(z) $slug egyik verziója sem kapcsolható be ezen a szerveren."
}
ensure_plugin contact-form-7 wp-contact-form-7
ensure_plugin seo-by-rank-math rank-math
ensure_plugin wps-hide-login wps-hide-login
R="$(rest POST /kompasz/v1/config "{\"login_slug\":\"$LOGIN_SLUG\"}")" || die "A belépési cím nem állítható be: $R"
log "Rank Math, WPS Hide Login aktív; belépés: $URL/$LOGIN_SLUG/ (a wp-login.php és a /wp-admin/ kijelentkezve 404)"

# --- Beállítás, import, migráció ---
[ -n "${GOOGLE_PLACES_API_KEY:-}" ] && rest POST /kompasz/v1/config "{\"google_api_key\":\"$GOOGLE_PLACES_API_KEY\"}" >/dev/null && log "Google API-kulcs beállítva"
FIRST="$(rest GET '/wp/v2/ugynokseg?per_page=1&_fields=id' 2>/dev/null || echo '[]')"
if [ -z "$(echo "$FIRST" | json 0.id)" ]; then
  log "üres oldal: Kompasz-adatok importja"
  step=base; off=0
  while [ -n "$step" ]; do
    R="$(rest POST /kompasz/v1/import "{\"step\":\"$step\",\"offset\":$off}")" || die "Import hiba: $R"
    step="$(echo "$R" | json next)"; off="$(echo "$R" | json offset)"; [ "$step" = "null" ] && step=""
    [ -n "$off" ] && [ "$off" != null ] && log "import: $off / $(echo "$R" | json total)"
  done
fi
MIGRATING=1
R="$(rest POST /kompasz/v1/migrate)" || die "Migrációs hiba: $R"; log "migráció: $(echo "$R" | json done) → séma $(echo "$R" | json schema)"
MIGRATING=0
rest POST /kompasz/v1/setup >/dev/null || die "Beállítási hiba"

# --- Ellenőrzés ---
R="$(rest POST /kompasz/v1/smoke)" || die "Füstteszt hiba: $R"
[ "$(echo "$R" | json ok)" = true ] || die "Füstteszt: $(echo "$R" | json problems)"
log "füstteszt: $(echo "$R" | json count) ügynökség rendben"
D="$(rest GET /kompasz/v1/doctor)"
echo "$D" | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d["rows"] as $r) printf("  %-30s %-15s %s\n", $r["ellenőrzés"], $r["állapot"], $r["részlet"]);'
[ "$(echo "$D" | json ok)" = true ] || die "doctor: hiba"
if [ -n "${GOOGLE_PLACES_API_KEY:-}" ] && echo "$D" | grep -q 'még nem futott'; then rest POST /kompasz/v1/google-sync >/dev/null && log "első Google-szinkron elindítva (háttérben)"; fi
for path in / "$(rest GET '/wp/v2/ugynokseg?per_page=1' | json 0.link | sed "s#^$URL##")"; do
  code=$(curl -s -L -b "$JAR" -o "$TMP/page.html" -w '%{http_code}' ${HTTP_AUTH:+-u "$HTTP_AUTH"} "$URL$path")
  if [ "$code" != 200 ] || grep -qiE 'critical error|kritikus hiba|Fatal error' "$TMP/page.html"; then die "$URL$path → HTTP $code"; fi
  log "OK $code $URL$path"
done
[ -n "${GOOGLE_PLACES_API_KEY:-}" ] || log "figyelem: nincs GOOGLE_PLACES_API_KEY – a Google-értékelések nem frissülnek"
R="$(rest POST /kompasz/v1/geocode '{"limit":30}')" && log "térkép: $(echo "$R" | json found) új pozíció, még $(echo "$R" | json remaining) (a többi óránként, a háttérben)"
log "kész – commit ${SHA:0:7}"
