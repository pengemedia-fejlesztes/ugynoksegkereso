# Ügynökségkereső / Kompasz – munkautasítás Claude-nak

Nyelv: a felhasználóval magyarul. Jelszót, tokent soha ne kérj a chatben: környezeti változó vagy GitHub Secret.

## Szerkezet
- Egy lista = egy taxonómia: `szolgaltatas` (a toplista a szolgáltatás tulajdonsága: `kompasz_toplist_slug` term meta + szövegek),
  `varos`, `megye`, `munkavegzes`, `arazas`, `nyelv`, `minosites`, `iparag`, `ugyfel`. Kódba írt lista nincs, beírós lista sincs (adatlap: `kompasz_picker`); kezdőérték csak egyszeri migrációban.
- Duplikátum: `inc/merge.php` (normalizált név, Ügynökségek → Összevonás, `wp kompasz merge-preview`); térkép: `inc/map.php`
  (MapLibre GL JS + OpenFreeMap, címkeresés: Nominatim). Belépés: `/pengemedia/` (WPS Hide Login); SEO: Rank Math + `kompasz/inc/seo.php`.
- `wordpress/kompasz-core/` bővítmény (adat, admin, import, migráció, REST/CLI üzemeltetés), `wordpress/kompasz/` téma.
- Adatok: `src/` (HU), `src/de/` (DE) → `scripts/export-wp*.mjs` → `import/data.json`; csomag: `bash scripts/zip-wp.sh [de]`.
- Adatbázis-változás = új sor a `kompasz_migrations()` végén (`inc/migrate.php`), soha ne írd át a régit; verzió: `KOMPASZ_CORE_VERSION`,
  `CHANGELOG.md`.

## Élesítés („telepítsd élesbe”)
1. Hozzáférés-ellenőrzés: `env | grep -E '^(WP_|CPANEL_|CLOUDFLARE_|GOOGLE_)' | sed 's/=.*//'` és
   `curl -s -o /dev/null -w '%{http_code}' https://master.ugynoksegek.pmdev.hu/` (401 = a pmdev kapu: `PMDEV_USER`/`PMDEV_PASSWORD` kell).
   Csak a magyar oldal (master) él; a német oldallal most nem foglalkozunk. Ha hiányzik valami: `docs/HOZZAFERES.md` szerint
   pontosan megnevezni a felhasználónak (a környezet beállításaiban adja meg), nem kerülőúton dolgozni.
2. Csak commitolt + pusholt kód megy ki. Előtte: `bash scripts/ci-wp-test.sh hu|de` (helyben MariaDB-vel) vagy a GitHub CI zöld.
3. `bash scripts/deploy-https.sh hu` (és `de`): mentés → feltöltés → CF7 → API-kulcs → migráció → füstteszt → doctor → HTTP;
   hibánál magától visszaáll (kód: előző commit; DB: a mentés). Állapot: `GET /wp-json/kompasz/v1/doctor`.
4. Német oldal első létrehozása (ha `de.ugynoksegek.pmdev.hu` még nincs): cPanel UAPI (`$CPANEL_URL/execute/...`,
   fejléc: `Authorization: cpanel $CPANEL_USER:$CPANEL_API_TOKEN`): `SubDomain/addsubdomain`, `Mysql/create_database`,
   `Mysql/create_user`, `Mysql/set_privileges_on_database`; WordPress-fájlok feltöltése/kicsomagolása (Fileman), `wp-config.php`;
   Cloudflare DNS (`$CLOUDFLARE_API_TOKEN`); telepítő: `wp-admin/install.php`; majd `deploy-https.sh de` (üres oldalon importál).
5. SSH-s út (GitHub Actions, ha vannak `WP_SSH_*` titkok): minden `main` push után automatikus (`scripts/deploy.sh`).

A munkakörnyezetből: csak HTTPS/443 megy (SSH, FTP, :2083, MySQL nem); a GitHub titok-API és a tag push tiltott.
A CI-naplók letöltése tiltott → a hibákat annotációból olvasd: `gh api repos/{owner}/{repo}/check-runs/<job id>/annotations`.
Workflow indítása: `gh api -X POST repos/pengemedia-fejlesztes/ugynoksegkereso/actions/workflows/wordpress.yml/dispatches -f ref=main`.
