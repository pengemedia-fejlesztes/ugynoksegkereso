# Változásnapló

A verziószámok a `kompasz-core` bővítményé és a `kompasz` témáé (szemantikus verziózás). Az adatbázis-séma külön számozott
(`kompasz_schema` opció, `wordpress/kompasz-core/inc/migrate.php` → `kompasz_migrations()`); a migrációk telepítéskor
automatikusan futnak (`wp kompasz migrate`), előtte a deploy adatbázis-mentést készít.

## 1.1.0 – 2026-10-20 (séma: 2)

### Admin – ügynökség adatlap (minden ügynökségnél egységesen)
- Egy „Adatlap” doboz a szerkesztő alatt; a WP alap taxonómia-dobozai megszűntek (nincs több adat több helyen).
- Szolgáltatások és toplisták egy táblázatban; a toplista a hozzá tartozó szolgáltatás-címkéhez kötve (`kompasz_tag` term meta).
- Minősítések, iparágak pipálva; referencia-ügyfelek automatikus kiegészítéssel, opcionális esettanulmány-linkkel.
- Cégfiók által beírt új érték jóváhagyásra vár, az adminisztrátor e-mailt kap (Ügynökségek → Jóváhagyásra vár).
- Munkanyelvek pipálva, nyelvenként egy telefonszám (formázás + ellenőrzés), e-mail ellenőrzés (formátum + MX).
- Alapítás éve legördülő lista; város és megye kiegészítés az adatbázisból.
- Google-értékelés: a Cégprofil linkből hetente automatikusan (Places API (New)), kézi „Szinkronizálás most”.
- Contact Form 7 ajánlatkérő űrlap az ügynökség oldalán (alapértelmezett űrlap automatikusan létrejön).

### Üzemeltetés
- WP-CLI: `wp kompasz migrate | setup | doctor | google-sync`.
- CI: integrációs teszt friss WordPressen (HU, DE) – import, migráció, füstteszt minden ügynökségre, HTTP-ellenőrzés.
- CD: SSH-s telepítés mentéssel, kódcserével, automatikus visszaállítással (`scripts/deploy.sh`).

### Javítva
- Cégfiók: végtelen ciklus a jogosultság-ellenőrzésben (belépés lehetetlen volt); a saját adatlap mentése.

### Adat-migráció (séma 2)
- Soronkénti minősítés/iparág/referencia szövegek → kifejezések; egy telefonszám → nyelvenkénti; Google-link a régi forrásjegyzetből.
