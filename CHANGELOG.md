# Változásnapló

A verziószámok a `kompasz-core` bővítményé és a `kompasz` témáé (szemantikus verziózás). Az adatbázis-séma külön számozott
(`kompasz_schema` opció, `wordpress/kompasz-core/inc/migrate.php` → `kompasz_migrations()`); a migrációk telepítéskor
automatikusan futnak (`wp kompasz migrate`), előtte a deploy adatbázis-mentést készít.

## 1.4.0 – 2026-10-22 (séma: 6)

### Minden lista adatbázisból, az adatlap közepén
- Egységes keresős pipalista minden listához, egyesével választható: város, megye, munkavégzés, árazás módja, munkanyelv
  (+ telefonszám nyelvenként), szolgáltatás/toplista, minősítés, iparág, referencia-ügyfél (+ nem kötelező link). Beírós lista nincs;
  új érték a lista alatti mezővel kerül az adatbázisba. A jobb oldalon nincs lista-doboz (egyik taxonómiáé sem).
- Új adatbázis-listák: Városok, Munkavégzés, Árazási módok (Ügynökségek menü).
- 6. migráció: város, munkavégzés, árazás szövegből → lista; a régi szöveges mezők törlése.
- CI: az admin adatlapon mind a 9 lista középen, jobb oldali lista-doboz nincs.

## 1.3.0 – 2026-10-22 (séma: 5)

### Egy adat – egy hely
- **Szolgáltatás = toplista = címke:** egy taxonómia (Ügynökségek → Szolgáltatások és toplisták). Ha egy szolgáltatásnak van toplistája,
  az a kifejezés tulajdonsága (toplista oldal címe, neve, H1, bevezető, SEO, ikon, sorrend). A toplistán mindenki szerepel, akinél
  a szolgáltatás be van pipálva (sorrend: „Sorrend a toplistákon”). Az adatlapon egy pipalista, a nyilvános profilon egy
  „Szolgáltatások” szakasz (a toplistás szolgáltatásnál helyezéssel). A toplista-URL-ek maradnak (/online-marketing-ugynoksegek/ …);
  a toplistás szolgáltatás régi /cimke/… címe 301-gyel a toplistára visz.
- **Megyék, munkanyelvek:** adatbázis-listák (Ügynökségek → Megyék / Munkanyelvek), az adatlapon legördülő / pipálható;
  telefonszám nyelvenként. Kódba írt lista nincs; a kezdőértékek a 4. migrációban kerültek be.
- **Duplikátumok:** normalizált név (ékezet, kis-nagybetű, cégforma, írásjel nélkül) – új érték felvételekor a meglévőt találja meg;
  Ügynökségek → Összevonás: javaslatok, melyik maradjon, kézi összevonás, napló; a régi címek 301-gyel. Első kör: 5. migráció.

### Új
- Térkép az adatlapon: MapLibre GL JS + OpenFreeMap, címkeresés Nominatimmal (kulcs és számlázás nélkül). Admin: cím, keresés,
  kattintás, húzható jelölő. Pozíció nélküli ügynökség: háttérkeresés a város (és cím) alapján, óránként legfeljebb 30.
- Rank Math SEO és WPS Hide Login (belépés: /pengemedia/) – a telepítő maga telepíti és beállítja; a cím és a meta leírás
  Rank Math mellett is a Kompasz adataiból jön.
- WP-CLI: `wp kompasz merge-preview | geocode`; REST: `merge-preview`, `migration-log`, `geocode`; doctor: Rank Math, belépési cím, térkép.
- Telepítő: a pmdev.hu belépési kapu (`PMDEV_USER`/`PMDEV_PASSWORD`), alapcím: master.ugynoksegek.pmdev.hu.

### Adat-migráció
- 3: régi toplisták → szolgáltatások (tagság, URL, szövegek, kiemelt ügynökség toplistái); a régi `kompasz_services` meta törlése.
- 4: megye és munkanyelv szövegből → lista; telefonszám kulcsa a nyelv azonosítója.
- 5: duplikátumok első köre (iparág: a statikus oldal INDUSTRY_MAP-je alapján, kiegészítve; minősítés; referencia-ügyfél).
  A változáslista: `GET /wp-json/kompasz/v1/migration-log`.

 – 2026-10-21 (séma: 2)

### Üzemeltetés
- Telepítés HTTPS-en, SSH nélkül (`scripts/deploy-https.sh`): WP admin + REST; mentés, feltöltés, kompatibilis CF7, API-kulcs,
  import (üres oldalon), migráció, füstteszt, doctor, HTTP-ellenőrzés; hibánál az előző commit kódja és a mentett adatbázis visszaáll.
  Csak commitolt, GitHubra feltöltött kód telepíthető.
- REST üzemeltetési végpontok (`/wp-json/kompasz/v1/…`: doctor, backup, backups, restore, migrate, setup, smoke, config, import,
  google-sync), csak adminisztrátornak; WP-CLI: `wp kompasz backup | smoke`.
- Adatbázis-mentés PHP-ból (mysqldump nélkül), `wp-content/kompasz-backups`, kívülről nem elérhető, az utolsó 20 marad.
- Migráció biztonsági hálója 10 perc türelmi idővel: a telepítő előbb ment, és maga futtatja a migrációt.
- `docs/HOZZAFERES.md`: a szükséges hozzáférések; `CLAUDE.md`: élesítési menet.

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
