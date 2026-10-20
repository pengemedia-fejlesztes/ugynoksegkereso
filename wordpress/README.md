# Kompasz – WordPress

Két rész:

- `kompasz-core/` – bővítmény: **Ügynökség** bejegyzéstípus, **Toplisták** és **Szolgáltatás-címkék** taxonómia,
  admin mezők, **Kompasz → Beállítások** (kiemelt ügynökség, kezdőlapi lista), **Kompasz → Importálás**.
- `kompasz/` – téma: a Kompasz v4 design (kezdőlap, toplisták szűrőkkel, ügynökség-profilok, címkeoldalak, SEO, JSON-LD).

## Telepítés (egyszer)

Követelmény: WordPress 6.4+, PHP 7.4+ (8.x ajánlott).

1. Csomagok: `npm run wp:zip` → `dist/kompasz-core.zip`, `dist/kompasz.zip`
   (vagy GitHub → Actions → „WordPress csomagok és telepítés” → utolsó futás → Artifacts).
2. WP admin → Bővítmények → Új hozzáadása → Bővítmény feltöltése → `kompasz-core.zip` → Bekapcsolás.
3. Megjelenés → Témák → Új hozzáadása → Téma feltöltése → `kompasz.zip` → Bekapcsolás.
4. Kompasz → Importálás → **Importálás indítása** (kb. fél perc; a toplistákat, címkéket, oldalakat, 176 céget és a logókat tölti be).
5. Beállítások → Közvetlen hivatkozások: „Bejegyzés neve” (az import be is állítja).

Az első import **kitiltja a keresőrobotokat** (Beállítások → Olvasás → „Keresőmotorok láthatósága”; robots.txt + noindex + X-Robots-Tag).
Élesítéskor ezt kell kikapcsolni.

## Automatikus telepítés (CI/CD)

`.github/workflows/wordpress.yml` minden `main` push-ra:

1. **test** – PHP-szintaxis, majd friss WordPressen (MySQL, HU és DE külön): Contact Form 7, import, `wp kompasz migrate`,
   füstteszt **minden ügynökség** adatlapjára (`scripts/wp/smoke.php`), HTTP-ellenőrzés. Helyben: `bash scripts/ci-wp-test.sh hu`.
2. **build** – telepíthető zip-csomagok (Artifacts).
3. **deploy** – csak sikeres teszt után, SSH-n, oldalanként (`scripts/deploy.sh` → a szerveren `scripts/wp/remote-deploy.sh`):
   adatbázis-mentés (`~/kompasz-backups`, utolsó 20) → új kód a régi mellé, csere → Contact Form 7 telepítése/bekapcsolása →
   `KOMPASZ_GOOGLE_API_KEY` a wp-config.php-ba → import (új oldalon) → migráció → `wp kompasz setup` → füstteszt → `wp kompasz doctor` →
   első Google-szinkron → külső HTTP-ellenőrzés. **Bármely hiba után az előző kód visszaáll, migrációs hibánál az adatbázis is.**
   Üres mappába (pl. a német aldomain) a WordPresst is letölti, beállítja és feltölti az adatokkal.

Titkok (GitHub → Settings → Secrets and variables → Actions → Secrets):

| Név | Tartalom |
|---|---|
| `WP_SSH_HOST`, `WP_SSH_USER` | SSH-hozzáférés a tárhelyhez |
| `WP_SSH_KEY` *vagy* `WP_SSH_PASSWORD` | privát kulcs (ajánlott) vagy jelszó; `WP_SSH_PORT` ha nem 22 |
| `WP_PATH_HU`, `WP_PATH_DE` | a két WordPress gyökérmappája a szerveren |
| `GOOGLE_PLACES_API_KEY` | Google Cloud → Places API (New) kulcs (a Places API-ra korlátozva) |
| `WP_DB_NAME_DE`, `WP_DB_USER_DE`, `WP_DB_PASSWORD_DE`, (`WP_DB_HOST_DE`) | csak a német oldal első telepítéséhez |
| `WP_ADMIN_EMAIL` | az új oldal adminisztrátorának e-mail címe |

Opcionális változók (Variables): `WP_URL_HU`, `WP_URL_DE`. A telepítések előzménye: GitHub → Environments (`production-hu`, `production-de`).
Kézi indítás: Actions → „WordPress – teszt, csomag, telepítés” → Run workflow (oldal választható).

Üzemeltetés a szerveren: `wp kompasz doctor` (állapot), `wp kompasz migrate`, `wp kompasz google-sync`, `wp kompasz setup`.
Visszaállítás mentésből: `gunzip -c ~/kompasz-backups/<fájl>.sql.gz | wp db import -`.

## Szerkesztés a WP adminban

- **Ügynökségek**: minden adat egy helyen, a szerkesztő alatti **Adatlap** dobozban (minden ügynökségnél ugyanez):
  - alapadatok; a város és a megye az adatbázisból kiegészít (új is megadható), az alapítás éve legördülő lista;
  - munkanyelvek pipálva (magyar, angol, német + az adatbázisban szereplő többi nyelv), nyelvenként egy telefonszám:
    a rendszer +36 20 404 4280 formára alakítja és ellenőrzi, az e-mail címet is (formátum + levelezőszerver);
  - **Szolgáltatások és toplisták** egy táblázatban: a toplista a hozzá tartozó szolgáltatás sorában van
    (a párosítás a toplista szerkesztőjében: „Hozzá tartozó szolgáltatás”); toplistára tett cég a szolgáltatást is megkapja;
  - minősítések és iparágak pipálva az adatbázisból, referencia-ügyfelek automatikus kiegészítéssel (link opcionális);
  - cégfiók által beírt új minősítés/iparág/referencia **jóváhagyásra vár** (Ügynökségek → Jóváhagyásra vár),
    az adminisztrátor e-mailt kap róla; addig a nyilvános oldalon nem látszik;
  - **Google Cégprofil link**: az értékelés és a vélemények száma hetente automatikusan frissül
    (Google Places API (New) kulcs: Kompasz → Beállítások, vagy `define('KOMPASZ_GOOGLE_API_KEY', '…')` a wp-config.php-ban);
  - ajánlatkérő űrlap (Contact Form 7) az adatlapon: bekapcsolt CF7 mellett a rendszer létrehoz egy űrlapot;
    alapértelmezés a Kompasz → Beállításokban, ügynökségenként felülírható.
  - Sorrend, saját értékelés, belső jegyzet: külön doboz, csak adminisztrátornak.
- **Toplisták / Szolgáltatás-címkék**: H1, bevezető, alsó SEO-blokkok, címkéknél HTML title és meta leírás.
- **Kompasz → Beállítások**: kiemelt (fizetett) ügynökség és a toplisták, ahol megjelenik; a kezdőlap „Legjobbra értékeltek” 5 cége.
- Az újraimport a kézzel szerkesztett bemutatkozásokat és a beállításokat nem írja felül.

## Helyi fejlesztés

A repó adataiból (`src/ugynoksegek.json`, `src/logos`, `src/google-ratings.json`, `src/data.mjs`, `src/tags.mjs`)
a `scripts/export-wp.mjs` készíti az importot (`kompasz-core/import/`, nincs a gitben).

## Német oldal (de.ugynoksegek.pmdev.hu)

Ugyanaz a téma és bővítmény, német nyelvvel: a nyelvet az import állítja be (`site.lang = de`), így a felület,
az URL-ek (`/agentur/`, `/leistung/`, `/branche/`, `/zertifizierung/`, `/impressum/` …), a jogi oldalak és a
strukturált adatok németek. A WP admin magyar marad.

- Adatok: `src/de/agenturen.json` (ügynökségek), `src/de/content.mjs` (ranglisták, leistung-címkék, kezdőlap szöveg),
  `src/de/logos/` (a „Logók letöltése” Action tölti le), felületszövegek: `kompasz-core/lang/de.php`.
- Csomag: `bash scripts/zip-wp.sh de` → `dist/de/kompasz-core.zip` (német importtal) + `dist/de/kompasz.zip`,
  vagy GitHub → Actions → „WordPress csomagok és telepítés” → `kompass-wordpress-de` artifact.
- Telepítés: külön WordPress a de-aldomainen; bővítmény és téma feltöltése, majd Kompasz → Importálás.
