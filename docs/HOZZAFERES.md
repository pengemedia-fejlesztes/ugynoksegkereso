# Hozzáférések – mit kell beállítani, hogy Claude maga telepíthessen

Claude Code felhős munkakörnyezete csak **HTTPS-t (443-as port)** enged, és csak az engedélyezett címekre. SSH (22), FTP,
cPanel 2083-as port és közvetlen MySQL-kapcsolat **nem** működik. Ezért a telepítés HTTPS-en megy:
WordPress admin + REST API (`scripts/deploy-https.sh`), a tárhely-műveletek (aldomain, adatbázis) a cPanel API-n,
a DNS a Cloudflare API-n, a Google-kulcs a Google Cloud API-n.

Jelszót, kulcsot **soha ne írj a chatbe** – csak az alábbi beállításokba.

## 1. Claude Code környezet: hálózat (egyszer)

claude.ai/code → a munkamenet címsorában a környezet neve → **Edit** → **Network access**:
„Limited” (vagy „Custom”) szint, az **Allow package managers** maradjon bejelölve, és az **Allowed domains** listába:

| Cím | Mire kell |
|---|---|
| `ugynoksegek.pmdev.hu` | a magyar oldal telepítése, ellenőrzése |
| `de.ugynoksegek.pmdev.hu` | a német oldal |
| `master.ugynoksegek.pmdev.hu` | ha az admin ezen a címen fut |
| a cPanel címe 443-as porton (pl. `cpanel.pmdev.hu`) | aldomain, adatbázis, fájlok (csak a német oldal létrehozásához) |
| `api.cloudflare.com` | DNS-rekord a német aldomainhez (ha a pmdev.hu DNS-e a Cloudflare-en van) |
| `api.wordpress.org`, `downloads.wordpress.org` | a szerver PHP-jával kompatibilis Contact Form 7 kiválasztása |
| `places.googleapis.com` | a Google-értékelések próbája |
| `serviceusage.googleapis.com`, `apikeys.googleapis.com`, `cloudresourcemanager.googleapis.com` | csak ha Claude hozza létre a Google API-kulcsot (4. pont, B) |

Leírás: https://code.claude.com/docs/en/cloud-environments#network-access

## 2. Claude Code környezet: környezeti változók (egyszer)

Ugyanott → **Environment variables** (vagy „Network secrets”, ha az van). Az új munkamenet már látja őket.

| Változó | Érték | Honnan |
|---|---|---|
| `WP_ADMIN_USER_HU` | pl. `claude-deploy` | WP admin → Felhasználók → Új hozzáadása, szerepkör: **Adminisztrátor**. Külön felhasználó legyen, hogy bármikor letiltható. |
| `WP_ADMIN_PASSWORD_HU` | a fenti felhasználó jelszava | |
| `WP_URL_HU` | `https://ugynoksegek.pmdev.hu` | ha az admin máshol fut: `https://master.ugynoksegek.pmdev.hu` |
| `GOOGLE_PLACES_API_KEY` | Places API (New) kulcs | 4. pont |
| `WP_ADMIN_USER_DE`, `WP_ADMIN_PASSWORD_DE` | a német oldal adminja | ha már létezik a német WordPress; különben Claude hozza létre |
| `CPANEL_URL` | pl. `https://cpanel.pmdev.hu` | az a cím, amin a cPanelbe belépsz – **443-as porton** (nem `:2083`) |
| `CPANEL_USER` | a cPanel felhasználónév | Rackforest ügyfélkapu → tárhely adatai |
| `CPANEL_API_TOKEN` | cPanel API-token | cPanel → Biztonság → **Manage API Tokens** → Create (lejárat nélkül vagy hosszú lejárattal) |
| `CLOUDFLARE_API_TOKEN` | „Zone → DNS → Edit” jogú token a pmdev.hu zónára | dash.cloudflare.com → My Profile → API Tokens → Create Token → „Edit zone DNS” sablon |

Ezekkel Claude maga végzi:
- **Magyar oldal:** `bash scripts/deploy-https.sh hu` – mentés, feltöltés, CF7, API-kulcs, migráció, ellenőrzés, hibánál visszaállítás.
- **Német oldal:** cPanel API: aldomain + üres adatbázis és felhasználó + WordPress-fájlok; Cloudflare: DNS-rekord; utána
  `bash scripts/deploy-https.sh de` (üres oldalon a Kompasz-adatokat is importálja).

## 3. GitHub (opcionális: automatikus telepítés minden push után)

Ha a tárhelyen van SSH, a GitHub Actions minden `main` push után maga telepít (`.github/workflows/wordpress.yml` → `scripts/deploy.sh`).
GitHub → repó → Settings → Secrets and variables → Actions → New repository secret:
`WP_SSH_HOST`, `WP_SSH_USER`, `WP_SSH_KEY` vagy `WP_SSH_PASSWORD`, (`WP_SSH_PORT`), `WP_PATH_HU`, `WP_PATH_DE`, `GOOGLE_PLACES_API_KEY`,
a német oldal első telepítéséhez `WP_DB_NAME_DE`, `WP_DB_USER_DE`, `WP_DB_PASSWORD_DE`, `WP_ADMIN_EMAIL`.
Claude ezeket nem tudja beírni (a munkakörnyezetből a GitHub titok-API nem érhető el) – ezt egyszer neked kell.

## 4. Google Places API-kulcs

**A) Te hozod létre (5 perc):** console.cloud.google.com → projekt (pl. „Kompasz”) → Számlázás bekapcsolása →
APIs & Services → Library → **Places API (New)** → Enable → Credentials → Create credentials → API key →
Edit → API restrictions: csak „Places API (New)” → mentés → a kulcs a `GOOGLE_PLACES_API_KEY` változóba.
Költség: havi kb. 260 lekérés (≈ 260 ügynökség havonta 1×, heti futásnál ≈ 1100) – a Google ingyenes havi kerete fedezi.

**B) Claude hozza létre:** egy szolgáltatásfiók-kulcs kell a projekthez („API Keys Admin” + „Service Usage Admin” szerepkörrel)
a `GOOGLE_SERVICE_ACCOUNT_JSON` környezeti változóban (a JSON teljes tartalma), és a fenti Google-címek a hálózati listán.
Ezzel Claude a `gcloud`-dal engedélyezi a Places API-t, létrehozza és korlátozza a kulcsot, és beállítja az oldalon.

## 5. Ellenőrzés

Új munkamenetben elég ennyit írni: **„telepítsd élesbe”**. Claude először ellenőrzi a hozzáféréseket
(`env`, a címek elérhetősége), és pontosan megmondja, ha valami még hiányzik.
