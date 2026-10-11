# Agency finder navigation design

_Started 2026-10-11 04:02 UTC_

---

## User

Make UI mockups for az a feladatod, nhogy a készítsd egy ünökségkereső oldalt ahol az ai ugynokseg kereso, pnline markeitng ügynökésgek a hangsulyosoka az oldalnak gyorsnak kell lennie, es a kapcsolatfelvetel fele kell hogy terelje a latogatokat, nezd meg az ugynoksegkereso oldalt. Kezdőlap
Ügynökségek (lenyíló menü)
Online marketing ügynökségek
PR ügynökségek
Keresőoptimalizáló ügynökségek
Amazon ügynökségek
Weboldal készítés
Tartalomgyártó ügynökségek
Rólunk
Blog
Kapcsolat
nyelvváltó (angol verzió)

A láblécben van még: a listák kiválasztási elvei, ÁSZF, adatvédelmi tájékoztató.

A te oldaladon ugyanez a szerkezet működne, az Ügynökségek alatt a négy új kategóriával kiegészítve: Facebook hirdetés ügynökségek, Google Ads ügynökségek, AI SEO ügynökségek, AI agent fejlesztő cégek.

## Assistant

_[tool: list_files]_

_[tool: web_fetch]_

_[tool: read_skill_prompt]_

_[tool: web_search]_

_[tool: web_fetch]_

_[tool: dc_write]_

_[tool: ready_for_verification]_

Elkészült az első kör, egy fájlban (`Ugynoksegkereso.dc.html`):

- **1a – Kezdőlap (desktop):**
  - Nyitott Ügynökségek menü. A 4 új kategória és az Online marketing kiemelve, „AI & performance” csoportban.
  - A hero mellett gyors ajánlatkérő űrlap, kattintható szolgáltatásválasztóval.
  - Kiemelt toplisták, 3 lépéses folyamat, a toplista első 5 helyezettje „Ajánlatot kérek” gombbal.
  - Lábléc a kiválasztási elvekkel, az ÁSZF-fel és az adatvédelmi tájékoztatóval.
- **1b – Toplista oldal:** a látogató több ügynökséget is kijelölhet, és egyetlen űrlappal mindegyiktől kér ajánlatot. Az űrlap oldalt mindig látszik, alul egy sáv mutatja a kijelölteket.
- **1c – Mobil:** kinyitható menü, nagy, könnyen bökhető gombok, alul mindig látható „Ajánlatot kérek” gomb.

Megjegyzések:
- Az 1b-ben a valódi ugynoksegkereso.hu online marketing lista szerepel.
- A logók helyén egyelőre helykitöltők vannak, ide jönnek majd a valódi logók.
- A Beállítások panelen ki-be kapcsolható a nyitott menü és a mobil alsó gomb.

Következő lépés lehet a Kapcsolat oldal és a köszönő oldal, vagy az AI agent fejlesztő cégek lista saját adatokkal.

## User

nem akarok ajanlatkerest, azonban azt igen, hogy az uj cimke ne legyen lathato, minden kategoriaban legynen rovid e kattegorai seo leírás a tetjén hogy mi is ez az oldla és legyne lent a láblécben egy nagyobb seo leírás ami segíti a seot és a felasznalot

## User

Found issues — fixing…

## User

Found issues — fixing…

## Assistant

_[tool: dc_js_str_replace]_

_[tool: ready_for_verification]_

Javítottam a 2b-t: az alsó SEO-blokk címe eddig minden kategóriánál üres volt. Most a kategória nevéből áll össze, például „Online marketing ügynökségek: amit érdemes tudni”.

Az ellenőrzés még jelez egymásra csúszó szövegeket a 2a-ban. Ezek a szándékosan nyitva hagyott Ügynökségek menü elemei, amelyek a kezdőlap főcímére lógnak. Ha zavaró, a menü a Beállítások panelen becsukható.

## User

egy egy ugynokseg esetbena. logot es a rovid bevezetot is meg kell jeleniteni hosszabb es reszeltesebb lesz egy egy ugynokseg ilyen adata valamint egy oldalon lehet hogy lesz 50 ceg is

## Assistant

_[tool: dc_html_str_replace]_

_[tool: dc_js_str_replace]_

_[tool: dc_js_str_replace]_

_[tool: dc_html_str_replace]_

_[tool: ready_for_verification]_

Átalakítottam a 2b toplista oldalt úgy, hogy a logó és a hosszabb bemutatkozás is elférjen, és 50 cégnél se legyen lassú.

- **Ügynökség-kártya:**
  - Nagy, 104 px-es logó.
  - A név, az értékelés és a címkék egy sávban vannak.
  - Ha a bemutatkozás hosszú, 3 sor után levágjuk, és a „Teljes bemutatkozás” gombbal kinyitható.
  - Jobb oldalt a „Kapcsolat ↗” gomb és a weboldal címe.
- **50 cég:** a lista az ugynoksegkereso.hu valódi online marketing listájából jön, a logókat is onnan tölti be.
  - Először 10 cég látszik, a többit a „További 10 ügynökség” gomb tölti be, egy sáv mutatja, hol tartasz.
  - A lista tetején kereső szűr névre és szolgáltatásra.
  - „Részletes / Tömör” váltó: tömör nézetben kisebb a logó, és a bemutatkozás rejtve marad.
- **Gyorsaság:** a logók csak akkor töltődnek be, amikor a látogató odagörget. Ha egy logó nem jön be, a cég kezdőbetűi jelennek meg a helyén.
- A 2a kezdőlap toplista-előnézetében is a valódi logók látszanak.

Élesítésnél érdemes mind az 50 céget betenni az oldal kódjába, és csak elrejteni a 10 utániakat, hogy a Google mindet lássa.

## User

legyen kiemelt partner statusz az oldalon, legyen lathato h o fizetett - a gombok szinere tegyel javaslatot mas szint akarok

## Assistant

_[tool: run_script]_

_[tool: dc_html_str_replace]_

_[tool: dc_html_str_replace]_

_[tool: dc_html_str_replace]_

_[tool: dc_js_str_replace]_

_[tool: dc_js_str_replace]_

_[tool: ready_for_verification]_

Bekerült a kiemelt partner státusz, és átállítottam a gombok színét.

- **Kiemelt partnerek (2b):** a toplista felett külön, halványsárga sávban vannak.
  - A sávon fekete „FIZETETT MEGJELENÉS” címke és ez a mondat áll: „A kiemelés nem befolyásolja a toplista sorrendjét.”
  - Minden partnerkártyán ott van a „★ KIEMELT PARTNER” jelvény és jobb alul a „Hirdetés” felirat.
- **A rangsorban is látszik:** a partner a saját helyén marad, mellette „★ KIEMELT PARTNER · FIZETETT” jelvénnyel.
- **Gombszín:** a narancsot erdőzöldre (#0E6B4F) cseréltem mindenhol. Az oldal tetején négy javaslat van egymás mellett:
  - A · Erdőzöld – ez van most alkalmazva
  - B · Kobaltkék
  - C · Mélylila
  - D · Grafit

Ha másikat választasz, átállítom.

A két partner, a Tartalom Design és a Klikkmánia, csak példa, nem valódi partnerstátusz.

