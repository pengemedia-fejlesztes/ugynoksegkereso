<?php
// Az alapoldalak (Rólunk, ÁSZF, Adatvédelem) és az induló blogcikkek szövege
if (!defined('ABSPATH')) exit;

function kompasz_operator() {
    return [
        'name' => 'Penge Média Kft.',
        'address' => '1139 Budapest, Teve utca 1/D/2. 6. emelet 61. ajtó',
        'tax' => '26522713-2-41',
        'email' => get_option('kompasz_email', 'hello@kompasz.hu'),
        'host' => 'RackForest Zrt. (rackforest.com)',
    ];
}

function kompasz_page_contents() {
    $o = kompasz_operator();
    $mail = sprintf('<a href="mailto:%1$s">%1$s</a>', esc_html($o['email']));
    $op = sprintf('%s (székhely: %s; adószám: %s; e-mail: %s)', esc_html($o['name']), esc_html($o['address']), esc_html($o['tax']), $mail);
    $elvek = esc_url(kompasz_page_url('kivalasztasi-elvek'));

    $rolunk = <<<HTML
<p>A Kompasz független magyar oldal, ahol szolgáltatás szerint bontott toplistákban találod a hazai online marketing, Google Ads, Facebook-hirdetés, SEO és AI SEO ügynökségeket, AI agent fejlesztő cégeket, PR-ügynökségeket, weboldal-készítőket és tartalomgyártókat.</p>
<h2>Miért készült?</h2>
<p>Ügynökséget választani nehéz: a weboldalak hasonlóan hangzanak, az árak nem nyilvánosak, a valódi tapasztalat pedig csak később derül ki. A Kompasz egy helyre gyűjti, amit a döntéshez tudni érdemes: ki mivel foglalkozik, hol dolgozik, mióta működik, és mit mondanak róla az ügyfelei.</p>
<h2>Hogyan rangsorolunk?</h2>
<p>A sorrend ügyfélértékeléseken, referenciákon, szakmai tapasztalaton és nyilvános cégadatokon alapul. A fizetett megjelenés nem változtat a sorrenden, és minden ilyen helyet „Kiemelt” vagy „Hirdetés” felirattal jelölünk. A részleteket <a href="{$elvek}">a kiválasztási elvek oldalon</a> írjuk le.</p>
<h2>Ügynökség vagy?</h2>
<p>Ha a céged szerepel a listán, igényelheted a cégadatlapodat, és a saját fiókodban frissítheted az adataidat. Ha még nem szerepelsz, írj nekünk: {$mail}.</p>
<h2>Ki üzemelteti?</h2>
<p>Az oldalt a {$op} üzemelteti.</p>
HTML;

    $aszf = <<<HTML
<p>Hatályos: 2026. október 11-től.</p>
<h2>1. A szolgáltató</h2>
<p>A Kompasz weboldalt (a továbbiakban: Oldal) a {$op} üzemelteti (a továbbiakban: Szolgáltató). A tárhelyszolgáltató: {$o['host']}.</p>
<h2>2. A szolgáltatás</h2>
<p>Az Oldal magyar ügynökségekről és szolgáltatókról készült, szolgáltatás szerint bontott toplistákat, cégadatlapokat és tájékoztató cikkeket tesz közzé. Az Oldal böngészése ingyenes és regisztráció nélkül használható.</p>
<h2>3. Cégadatlapok és cégfiókok</h2>
<p>A listán szereplő cég kérésre cégfiókot kaphat, amellyel a saját adatlapját szerkesztheti. A cég felel az általa megadott adatok valódiságáért és jogszerűségéért (például a logó és a leírás felhasználási jogáért). A Szolgáltató a nyilvánvalóan valótlan, megtévesztő vagy jogsértő tartalmat előzetes értesítés nélkül módosíthatja vagy eltávolíthatja, a cégfiókot pedig felfüggesztheti.</p>
<h2>4. Rangsorolás és fizetett megjelenés</h2>
<p>A toplisták sorrendjét a Szolgáltató a <a href="{$elvek}">kiválasztási elvekben</a> leírt szempontok szerint állítja össze. A sorrend véleménynyilvánítás, nem minősül ajánlásnak vagy garanciának. Fizetett (kiemelt) megjelenés külön megállapodás alapján lehetséges; a fizetett helyeket az Oldal egyértelműen jelöli, és a fizetés nem befolyásolja a rangsort.</p>
<h2>5. Felelősség</h2>
<p>Az Oldalon közölt cégadatok nyilvános forrásokból és a cégek saját közléséből származnak. A Szolgáltató gondosan jár el, de nem felel azért, ha egy adat időközben megváltozott vagy hiányos. A felhasználó és a listán szereplő cég között létrejövő szerződésnek a Szolgáltató nem részese, az abból eredő vitákért nem felel.</p>
<h2>6. Szellemi tulajdon</h2>
<p>Az Oldal szövegei, toplistái és arculati elemei a Szolgáltató szellemi tulajdonát képezik; a cégek logói és védjegyei a jogosultjukat illetik. Az Oldal tartalmának üzleti célú másolása, gépi gyűjtése a Szolgáltató írásbeli engedélye nélkül tilos.</p>
<h2>7. Panaszkezelés</h2>
<p>Ha egy adat hibás, vagy kifogásod van a megjelenéssel kapcsolatban, írj nekünk: {$mail}. A megkeresésekre legkésőbb 15 munkanapon belül válaszolunk.</p>
<h2>8. Egyéb</h2>
<p>A jelen feltételekre a magyar jog az irányadó. A Szolgáltató a feltételeket egyoldalúan módosíthatja; a módosítás az Oldalon való közzététellel lép hatályba.</p>
HTML;

    $adatvedelem = <<<HTML
<p>Hatályos: 2026. október 11-től.</p>
<h2>1. Az adatkezelő</h2>
<p>{$op}. Adatvédelmi kérdésekben ezen a címen érsz el minket: {$mail}.</p>
<h2>2. Milyen adatokat kezelünk?</h2>
<p><strong>Kapcsolatfelvétel.</strong> Ha e-mailben vagy űrlapon írsz nekünk, a neved, az e-mail-címed és az üzeneted tartalmát kezeljük, a megkeresés megválaszolása céljából. Jogalap: a jogos érdekünk és a te kérésed (GDPR 6. cikk (1) b) és f) pont). Megőrzés: a megkeresés lezárását követő 1 évig.</p>
<p><strong>Cégfiók.</strong> Ha a céged cégfiókot kap, a kapcsolattartó nevét, e-mail-címét és a fiókhoz tartozó belépési adatokat kezeljük, a fiók működtetése céljából. Jogalap: szerződés teljesítése (GDPR 6. cikk (1) b) pont). Megőrzés: a fiók megszűnéséig.</p>
<p><strong>Cégadatlapok.</strong> A listán szereplő cégekről nyilvános üzleti adatokat közlünk (cégnév, weboldal, város, szolgáltatások, nyilvános értékelések). Ha egy adatlapon természetes személy adata szerepel, azt a jogos érdekünk alapján kezeljük; kérésre töröljük vagy javítjuk.</p>
<p><strong>Sütik.</strong> Az Oldal működéséhez szükséges sütiket használunk. Statisztikai vagy marketing célú sütit csak a hozzájárulásoddal helyezünk el.</p>
<h2>3. Adatfeldolgozó</h2>
<p>Tárhelyszolgáltató: {$o['host']}. Személyes adatot harmadik félnek nem adunk el, és nem adunk át, kivéve, ha jogszabály kötelez rá.</p>
<h2>4. A jogaid</h2>
<p>Kérheted, hogy tájékoztassunk a rólad kezelt adatokról, javítsuk vagy töröljük őket, korlátozzuk a kezelésüket, illetve tiltakozhatsz az adatkezelés ellen. Kérésedet a fenti e-mail-címen jelezheted; 30 napon belül válaszolunk.</p>
<h2>5. Jogorvoslat</h2>
<p>Panasszal a Nemzeti Adatvédelmi és Információszabadság Hatósághoz fordulhatsz (1055 Budapest, Falk Miksa utca 9–11.; postacím: 1363 Budapest, Pf. 9.; ugyfelszolgalat@naih.hu; www.naih.hu), vagy bírósághoz is.</p>
HTML;

    return ['rolunk' => $rolunk, 'aszf' => $aszf, 'adatvedelem' => $adatvedelem];
}

function kompasz_blog_posts() {
    return [
        'hogyan-valassz-online-marketing-ugynokseget' => ['Hogyan válassz online marketing ügynökséget? 7 kérdés az első egyeztetés előtt', <<<HTML
<p>Egy rossz ügynökségválasztás nemcsak pénzbe kerül, hanem hónapokat is elvisz, amíg kiderül, hogy nem működik. Az alábbi hét kérdéssel az első beszélgetésen kiszűrheted a legtöbb kockázatot.</p>
<h2>1. Milyen hasonló cégeknek dolgoztatok?</h2>
<p>Kérj konkrét példát a te iparágadból vagy méretkategóriádból. Egy webáruházaknak dolgozó csapat más tapasztalattal érkezik, mint egy B2B szolgáltatókra szakosodott.</p>
<h2>2. Ki fog ténylegesen dolgozni a fiókomon?</h2>
<p>Az első tárgyaláson gyakran az ügyvezető ül, a munkát viszont egy kolléga végzi. Kérdezd meg, ki lesz a napi kapcsolattartód, és mennyi tapasztalata van.</p>
<h2>3. Mit mértek, és milyen gyakran kapok riportot?</h2>
<p>A jó ügynökség az üzleti eredményt méri (érdeklődő, vásárlás, árbevétel), nem csak a megjelenéseket. Kérj mintariportot.</p>
<h2>4. Kié a hirdetési fiók és a mérés?</h2>
<p>A Google Ads-, Meta- és Analytics-fiók legyen a te tulajdonodban, az ügynökség csak hozzáférést kapjon. Így váltáskor sem vesznek el az adatok.</p>
<h2>5. Hogyan áll össze az ár?</h2>
<p>Kérdezd meg, mi van a havidíjban, mi számít külön munkának, és a hirdetési keret hogyan aránylik a kezelési díjhoz.</p>
<h2>6. Mennyi a felmondási idő?</h2>
<p>Az első hónapokban derül ki, hogy működik-e az együttműködés. A rövid felmondási idő mindkét félnek biztonságot ad.</p>
<h2>7. Mit kértek tőlem?</h2>
<p>A jó ügynökség tudja, milyen anyagokra, döntésekre és válaszidőre van szüksége tőled. Ha erre nincs kész válasza, az figyelmeztető jel.</p>
<p>A Kompasz toplistáin szolgáltatás szerint szűrheted az ügynökségeket, és megnézheted az értékeléseiket, mielőtt felveszed velük a kapcsolatot.</p>
HTML],
        'mennyibe-kerul-egy-google-ads-ugynokseg' => ['Mennyibe kerül egy Google Ads ügynökség?', <<<HTML
<p>A Google Ads kezelésének ára két részből áll: a hirdetési keretből, amelyet közvetlenül a Google számláz, és az ügynökség díjából. A kettőt érdemes külön kezelni.</p>
<h2>A díjazás leggyakoribb formái</h2>
<p><strong>Fix havidíj.</strong> Kiszámítható, és jól működik, ha a hirdetési keret nagyjából állandó.</p>
<p><strong>A hirdetési keret százaléka.</strong> Nagyobb keretnél gyakori; a díj a költéssel együtt nő.</p>
<p><strong>Teljesítményalapú díj.</strong> Az ügynökség díja részben az eredménytől (például érdeklődők vagy árbevétel) függ. Ehhez pontos mérés és előre rögzített alapérték kell.</p>
<h2>Mi befolyásolja az árat?</h2>
<ul>
<li>a kampányok száma és típusa (keresési, display, YouTube, Performance Max, Shopping);</li>
<li>hány országban és nyelven futnak a hirdetések;</li>
<li>kell-e konverziómérést, Tag Managert vagy céloldalt is építeni;</li>
<li>milyen gyakran kérsz riportot és egyeztetést.</li>
</ul>
<h2>Mire figyelj?</h2>
<p>Az induló beállítás (fiókstruktúra, mérés, kulcsszókutatás) gyakran külön tétel. Kérdezd meg, mi tartozik bele, és mennyi idő után várható érdemi eredmény. A Google Ads fiók maradjon a te tulajdonodban.</p>
<p>A Kompasz Google Ads toplistáján összehasonlíthatod a hazai ügynökségeket értékelés, helyszín és szolgáltatások szerint.</p>
HTML],
        'mi-az-ai-seo' => ['Mi az AI SEO, és miért kezd vele foglalkozni egyre több cég?', <<<HTML
<p>Egyre többen kérdeznek ChatGPT-től, Geminitől vagy a Google AI-összefoglalóitól, mielőtt vásárolnak vagy szolgáltatót választanak. Az AI SEO arról szól, hogy ezekben a válaszokban is megjelenjen a céged.</p>
<h2>Miben más, mint a hagyományos SEO?</h2>
<p>A klasszikus keresőoptimalizálás célja, hogy a weboldalad előre kerüljön a találati listán. Az AI-alapú keresők viszont nem listát adnak, hanem összefoglalt választ, és ebben néhány forrást idéznek. Az AI SEO azt erősíti, hogy a te oldaladat idézzék.</p>
<h2>Mi segít a megjelenésben?</h2>
<ul>
<li>egyértelmű, kérdésekre válaszoló tartalom (például GYIK, összehasonlítások, árak);</li>
<li>pontos, következetes cégadatok mindenhol (weboldal, Google Cégprofil, iparági listák);</li>
<li>strukturált adatok a weboldalon, hogy a gépek is értsék, mit kínálsz;</li>
<li>független említések és értékelések más oldalakon.</li>
</ul>
<h2>Kinek érdemes most elkezdeni?</h2>
<p>Annak, akinek az ügyfelei összetett döntést hoznak (szolgáltatás, B2B beszerzés, drágább termék), és sokat tájékozódnak vásárlás előtt. Itt a korai jelenlét tartós előnyt adhat.</p>
<p>A Kompasz AI SEO toplistáján az ezzel foglalkozó hazai ügynökségeket találod.</p>
HTML],
    ];
}

/** Az alapoldalak szövegének frissítése: csak ha még az eredeti „hamarosan” vagy rövid induló szöveg áll benne */
function kompasz_apply_page_contents($force = false) {
    $done = [];
    $de = kompasz_lang() === 'de';
    if ($de) require_once __DIR__ . '/pages-content-de.php';
    foreach (($de ? kompasz_page_contents_de() : kompasz_page_contents()) as $key => $html) {
        $p = get_page_by_path(kompasz_slug($key));
        if (!$p) continue;
        $placeholder = stripos($p->post_content, 'hamarosan') !== false || strlen(wp_strip_all_tags($p->post_content)) < 200;
        if ($force || $placeholder) { wp_update_post(['ID' => $p->ID, 'post_content' => $html]); $done[] = $key; }
    }
    // Az induló blogcikkek magyarok – a német oldalon nincsenek
    if ($de) return $done;
    foreach (kompasz_blog_posts() as $slug => [$title, $html]) {
        if (get_page_by_path($slug, OBJECT, 'post')) continue;
        wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $html]);
        $done[] = $slug;
    }
    return $done;
}
