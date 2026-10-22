<?php
// Adatszerkezet-frissítés: a soronkénti szöveges mezők (minősítések, iparágak, referenciák, egy telefonszám) → pipálható kifejezések,
// nyelvenkénti telefonszámok, toplista ↔ szolgáltatás-címke párosítás. Minden ügynökségre egyszer fut le (admin vagy: wp kompasz migrate).
if (!defined('ABSPATH')) exit;


function kompasz_fold($s) {
    $s = mb_strtolower(remove_accents((string) $s));
    return $s;
}

/** Ügyfélnév kulcsa: cégforma, „Magyarország” és írásjelek nélkül (= src/ugyfelek-minositesek.mjs clientKey) */
function kompasz_client_key($name) {
    $s = kompasz_fold(trim(explode('|', (string) $name)[0]));
    $s = preg_replace('/\b(kft|zrt|nyrt|bt|ltd|gmbh|hungary|magyarorszag|group|csoport)\b\.?/', '', $s);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

/** Az ugyanazt jelentő minősítések egy néven (= certName) */
function kompasz_cert_name($raw) {
    $s = trim((string) $raw);
    $f = kompasz_fold($s);
    $aliases = [
        '/^meta (business )?partner$|^facebook partner$/' => 'Meta Business Partner',
        '/^shoprenter (szakerto|expert|partner)$/' => 'Shoprenter partner',
        '/^unas (szakerto|expert|partner|seo partner)$/' => 'UNAS partner',
        '/^shopify (partner|expert)$/' => 'Shopify partner',
        '/^shoptet (partner|szakerto|expert)$/' => 'Shoptet partner',
        '/^tiktok (marketing )?partner$/' => 'TikTok partner',
        '/^iso 9001/' => 'ISO 9001',
        '/^mprsz/' => 'MPRSZ-tag',
        '/^(magyar marketing szovetseg|mmsz)/' => 'Magyar Marketing Szövetség tag',
    ];
    foreach ($aliases as $re => $name) if (preg_match($re, $f)) return $name;
    return $s;
}

/** Referencia-sorok („Név | https://…”) → ügyfél-kifejezések; a meglévő ügyfelet a kulcs alapján találja meg */
function kompasz_refs_to_terms($post_id, array $lines) {
    static $by_key = null;
    if ($by_key === null) {
        $by_key = [];
        foreach (get_terms(['taxonomy' => 'ugyfel', 'hide_empty' => false]) ?: [] as $t) $by_key[kompasz_client_key($t->name)] = (int) $t->term_id;
    }
    $ids = kompasz_post_term_ids($post_id, 'ugyfel');
    $urls = json_decode((string) get_post_meta($post_id, 'kompasz_ref_urls', true), true) ?: [];
    foreach ($lines as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        $k = kompasz_client_key($parts[0]);
        if (strlen($k) < 2) continue;
        $tid = $by_key[$k] ?? (kompasz_resolve_terms('ugyfel', [$parts[0]], $post_id, true)[0] ?? 0);
        if (!$tid) continue;
        $by_key[$k] = $tid;
        $ids[] = $tid;
        if (!empty($parts[1]) && wp_http_validate_url($parts[1])) $urls[$tid] = esc_url_raw($parts[1]);
    }
    wp_set_object_terms($post_id, array_values(array_unique($ids)), 'ugyfel');
    update_post_meta($post_id, 'kompasz_ref_urls', wp_json_encode($urls));
}

function kompasz_lines_to_terms($post_id, $taxonomy, array $names) {
    if ($taxonomy === 'minosites') $names = array_map('kompasz_cert_name', $names);
    $ids = array_merge(kompasz_post_term_ids($post_id, $taxonomy), kompasz_resolve_terms($taxonomy, $names, $post_id, true));
    wp_set_object_terms($post_id, array_values(array_unique($ids)), $taxonomy);
}

/** Toplista → szolgáltatás-címke: a slug elejének egyezése alapján (pl. google-ads-ugynoksegek → google-ads) */
function kompasz_guess_toplist_tags() {
    $known = ['keresooptimalizalo-ugynoksegek' => 'seo', 'tartalomgyarto-ugynoksegek' => 'tartalommarketing', 'ki-agenten-entwickler' => 'ki-automatisierung'];
    $tags = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => false]) ?: [];
    foreach (get_terms(['taxonomy' => 'toplista', 'hide_empty' => false]) ?: [] as $c) {
        if (get_term_meta($c->term_id, 'kompasz_tag', true)) continue;
        $best = null; $score = 0;
        if (isset($known[$c->slug])) { $t = get_term_by('slug', $known[$c->slug], 'szolgaltatas'); if ($t) { $best = $t; $score = 9; } }
        if (!$best) {
            $cs = explode('-', $c->slug);
            foreach ($tags as $t) {
                $ts = explode('-', $t->slug); $n = 0;
                while ($n < count($ts) && $n < count($cs) && $ts[$n] === $cs[$n]) $n++;
                if ($n === 1 && in_array($cs[0], ['ai', 'ki'], true)) $n = 0;
                if ($n > $score) { $score = $n; $best = $t; }
            }
        }
        if ($best) update_term_meta($c->term_id, 'kompasz_tag', (string) $best->term_id);
    }
}

/** Toplista → hozzá tartozó szolgáltatás-címke a 3. migráció előtti szerkezetben (term meta: kompasz_tag) */
function kompasz_toplist_tag($toplist) {
    $id = (int) get_term_meta(is_object($toplist) ? $toplist->term_id : (int) $toplist, 'kompasz_tag', true);
    return $id ?: 0;
}

/** Telefonszámok a 2. migráció szerkezetében (nyelv neve => szám) az egyetlen régi számból: az első munkanyelvhez tartozik */
function kompasz_legacy_phones($id) {
    $one = (string) get_post_meta($id, 'kompasz_phone', true);
    if ($one === '') return [];
    $langs = kompasz_meta_lines($id, 'kompasz_languages') ?: kompasz_post_term_names($id, 'nyelv');
    return [$langs[0] ?? (kompasz_lang() === 'de' ? 'Deutsch' : 'magyar') => kompasz_phone_format($one) ?: $one];
}

/** Egy ügynökség régi szöveges adatainak átvitele (az importer is ezt használja) */
function kompasz_migrate_agency($id) {
    $lines = function ($k) use ($id) { return kompasz_meta_lines($id, $k); };
    if ($l = $lines('kompasz_certs')) kompasz_lines_to_terms($id, 'minosites', $l);
    if ($l = $lines('kompasz_industries')) kompasz_lines_to_terms($id, 'iparag', $l);
    if ($l = $lines('kompasz_references')) kompasz_refs_to_terms($id, $l);
    foreach (['kompasz_certs', 'kompasz_industries', 'kompasz_references'] as $k) delete_post_meta($id, $k);
    if (get_post_meta($id, 'kompasz_phones', true) === '') {
        $phones = kompasz_legacy_phones($id);
        update_post_meta($id, 'kompasz_phones', wp_json_encode($phones, JSON_UNESCAPED_UNICODE));
        if ($phones) update_post_meta($id, 'kompasz_phone', reset($phones));
    }
    // Google Cégprofil link a korábbi kézi forrásjegyzetből (ha abban Google Térkép link van)
    if (get_post_meta($id, 'kompasz_google_url', true) === '' && preg_match('#https?://(?:www\.)?(?:google\.[a-z.]+/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|g\.page|share\.google)\S*#', (string) get_post_meta($id, 'kompasz_google_source', true), $mm)) {
        update_post_meta($id, 'kompasz_google_url', esc_url_raw(rtrim($mm[0], ').,')));
    }
    // Az ügynökség szolgáltatás-címkéi a toplistákból is (toplistán szereplő cég a hozzá tartozó szolgáltatást is nyújtja)
    $tags = kompasz_post_term_ids($id, 'szolgaltatas');
    foreach (kompasz_post_term_ids($id, 'toplista') as $t) { $tag = kompasz_toplist_tag($t); if ($tag) $tags[] = $tag; }
    wp_set_object_terms($id, array_values(array_unique($tags)), 'szolgaltatas');
}

/**
 * 3. migráció: a toplista a szolgáltatás tulajdonsága lesz. Minden régi toplista a párosított szolgáltatásba kerül (kompasz_tag), vagy –
 * ha nincs párja – új szolgáltatás lesz a rövid nevével. Átkerül: URL (kompasz_toplist_slug), név, ikon, sorrend, szövegek, SEO,
 * tagság; a kiemelt ügynökség toplistái. A szolgáltatás korábbi szövegei a kompasz_legacy_tag meta-ban maradnak.
 * Visszaad: változáslista (a naplóba és a felhasználónak).
 */
function kompasz_merge_toplists_into_services() {
    $changes = []; $map = [];
    $toplists = get_terms(['taxonomy' => 'toplista', 'hide_empty' => false]);
    if (is_wp_error($toplists)) $toplists = [];
    foreach ($toplists as $c) {
        $tag = kompasz_toplist_tag($c);
        $t = $tag ? get_term($tag, 'szolgaltatas') : null;
        if (!$t || is_wp_error($t) || kompasz_toplist_slug($t) !== '') $t = null; // két toplista nem kerülhet egy szolgáltatásba
        $before = $t ? (int) $t->count : 0;
        if (!$t) {
            $name = (string) get_term_meta($c->term_id, 'kompasz_short', true) ?: $c->name;
            $ex = get_term_by('name', $name, 'szolgaltatas');
            if ($ex && kompasz_toplist_slug($ex) === '') $t = $ex;
            else {
                $r = wp_insert_term($ex ? $c->name : $name, 'szolgaltatas');
                if (is_wp_error($r)) continue;
                $t = get_term((int) $r['term_id'], 'szolgaltatas');
            }
            $before = (int) $t->count;
        }
        $legacy = [];
        foreach (['kompasz_h1', 'kompasz_intro', 'kompasz_seo'] as $k) { $v = (string) get_term_meta($t->term_id, $k, true); if ($v !== '' && $v !== '[]') $legacy[$k] = $v; }
        if ($t->description !== '') $legacy['description'] = $t->description;
        if ($legacy) update_term_meta($t->term_id, 'kompasz_legacy_tag', wp_json_encode($legacy, JSON_UNESCAPED_UNICODE));
        foreach (['kompasz_short', 'kompasz_icon', 'kompasz_order', 'kompasz_ai', 'kompasz_card', 'kompasz_question', 'kompasz_h1', 'kompasz_intro', 'kompasz_seo'] as $k) {
            $v = (string) get_term_meta($c->term_id, $k, true);
            if ($v !== '' && $v !== '[]') update_term_meta($t->term_id, $k, $v);
        }
        update_term_meta($t->term_id, 'kompasz_toplist_name', $c->name);
        update_term_meta($t->term_id, 'kompasz_toplist_slug', $c->slug);
        if ($c->description !== '') wp_update_term($t->term_id, 'szolgaltatas', ['description' => $c->description]);
        foreach (get_objects_in_term($c->term_id, 'toplista') as $pid) wp_add_object_terms((int) $pid, [(int) $t->term_id], 'szolgaltatas');
        $map[$c->term_id] = (int) $t->term_id;
        clean_term_cache($t->term_id, 'szolgaltatas');
        $after = count(get_objects_in_term($t->term_id, 'szolgaltatas'));
        $changes[] = sprintf('Toplista „%s” (/%s/, %d cég) → szolgáltatás „%s” (%s); a toplistán ezután %d cég (a szolgáltatásnál eddig %d)%s',
            $c->name, $c->slug, (int) $c->count, $t->name, $before ? 'meglévő' : 'új', $after, $before, $before ? sprintf('; /%s/%s/ → 301', kompasz_slug('cimke'), $t->slug) : '');
    }
    $featured = array_values(array_filter(array_map(function ($x) use ($map) { return $map[(int) $x] ?? 0; }, (array) get_option('kompasz_featured_cats', []))));
    update_option('kompasz_featured_cats', $featured);
    foreach ($toplists as $c) wp_delete_term($c->term_id, 'toplista');
    delete_metadata('term', 0, 'kompasz_tag', '', true);
    delete_post_meta_by_key('kompasz_services');
    update_option('kompasz_flush_rules', 1);
    return $changes;
}

/**
 * Az első összevonási kör egységes nevei (5. migráció, egyszer). A magyar iparágak a statikus oldal INDUSTRY_MAP-je alapján
 * (src/ugyfelek-minositesek.mjs), kiegészítve; a további összevonás az adminban: Ügynökségek → Összevonás.
 */
function kompasz_merge_seed_maps() {
    if (kompasz_lang() !== 'hu') return []; // csak a magyar oldal első köre
    return [
        'iparag' => [
            '/^(webaruhaz|webshop|webshopok|e-kereskedelem|ecommerce|e-commerce)$/' => 'Webáruház és e-kereskedelem',
            '/^(egeszsegugy|egeszsegugyi|orvosi|klinika|fogaszat)/' => 'Egészségügy',
            '/^(gyogyszeripar|gyogyszer)/' => 'Gyógyszeripar',
            '/b2b/' => 'B2B szolgáltatók',
            '/^b2c$/' => 'B2C (lakossági) vállalkozások',
            '/^(oktatas|felsooktatas|edtech)/' => 'Oktatás',
            '/^(ingatlan|epitoipar|ingatlan es epitoipar|epites es lakberendezes)$/' => 'Ingatlan és építőipar',
            '/^(etterem|vendeglatas|vendeglatoipar|horeca|gasztronomia|turizmus|turisztika|utazasi iroda|utazas|szallashely|szalloda|szallas|hotel|turizmus es vendeglatas|vendeglatas es szallas)$/' => 'Vendéglátás és turizmus',
            '/^(szepsegipar|divat|szepseg es divat|kozmetika es szepsegapolas)$/' => 'Szépségipar és divat',
            '/^logisztika/' => 'Logisztika',
            '/^(it|informatika|informacio es technologia|it helpdesk|technologia)$/' => 'IT és technológia',
            '/^(penzugy|bank|penzpiac|biztositas|bankszektor|bank es penzugy|penzugyi szektor|penzugyi szolgaltatok)$/' => 'Pénzügy és bank',
            '/^(ugyvedi iroda|ugyvedi irodak|jog|jogi szolgaltatok)$/' => 'Jogi szolgáltatók',
            '/^(ipar|gyartas.*|mernoki|autoipari|autoipar|jarmuipar|energia|energiaipar|gyarto|gyartoipar|feldolgozoipar|ipari agazatok)$/' => 'Ipar és gyártás',
            '/^(fmcg|elelmiszeripar|elelmiszer|allateledel)$/' => 'FMCG és élelmiszeripar',
            '/^(mezogazdasag|kerteszet)$/' => 'Mezőgazdaság',
            '/^nonprofit/' => 'Nonprofit',
            '/^(hr|hr es toborzas)$/' => 'HR és toborzás',
            '/^(szolgaltatas|szolgaltato|szolgaltato cegek|szolgaltatoi szektor|szolgaltatasi szektor|szolgaltatoipar|uzleti szolgaltatas)$/' => 'Szolgáltató cégek',
            '/^(kereskedelem|kiskereskedelem|kis- es nagykereskedelem)$/' => 'Kereskedelem',
        ],
        'minosites' => ['/^microsoft advertising minositett szakerto$/' => 'Microsoft Advertising Certified Professional'],
    ];
}

/** Kezdőértékek a megye- és nyelvlistába (csak egyszer, a 4. migrációban; utána az adminisztrátor bővíti) */
function kompasz_seed_lists() {
    $de = kompasz_lang() === 'de';
    $seed = [
        'megye' => $de ? ['Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen']
            : ['Budapest', 'Bács-Kiskun', 'Baranya', 'Békés', 'Borsod-Abaúj-Zemplén', 'Csongrád-Csanád', 'Fejér', 'Győr-Moson-Sopron', 'Hajdú-Bihar', 'Heves', 'Jász-Nagykun-Szolnok', 'Komárom-Esztergom', 'Nógrád', 'Pest', 'Somogy', 'Szabolcs-Szatmár-Bereg', 'Tolna', 'Vas', 'Veszprém', 'Zala'],
        'nyelv' => $de ? ['Deutsch', 'Englisch', 'Ungarisch'] : ['magyar', 'angol', 'német'],
    ];
    foreach ($seed as $tax => $names) foreach ($names as $n) if (!term_exists($n, $tax)) wp_insert_term($n, $tax);
}

function kompasz_ensure_term($name, $taxonomy) {
    $name = trim((string) $name);
    if ($name === '') return 0;
    $t = term_exists($name, $taxonomy) ?: wp_insert_term($name, $taxonomy);
    return is_wp_error($t) ? 0 : (int) (is_array($t) ? $t['term_id'] : $t);
}

/** Egy ügynökség megyéje és munkanyelvei szövegből → adatbázis-lista; a telefonszámok kulcsa a nyelv azonosítója (az importer is ezt használja) */
function kompasz_migrate_agency_lists($id) {
    $county = (string) get_post_meta($id, 'kompasz_county', true);
    if ($county !== '' && ($c = kompasz_ensure_term($county, 'megye'))) wp_set_object_terms($id, [$c], 'megye');
    $names = kompasz_meta_lines($id, 'kompasz_languages');
    if ($names) wp_set_object_terms($id, array_values(array_filter(array_map(function ($n) { return kompasz_ensure_term($n, 'nyelv'); }, $names))), 'nyelv');
    $p = json_decode((string) get_post_meta($id, 'kompasz_phones', true), true);
    if (is_array($p)) {
        $out = [];
        foreach ($p as $k => $v) {
            $tid = is_int($k) || ctype_digit((string) $k) ? (int) $k : kompasz_ensure_term($k, 'nyelv');
            if (!$tid) continue;
            $out[$tid] = $v;
            wp_add_object_terms($id, [$tid], 'nyelv'); // akinek az adott nyelven van száma, az a nyelven dolgozik
        }
        update_post_meta($id, 'kompasz_phones', wp_json_encode((object) $out, JSON_UNESCAPED_UNICODE));
    }
    delete_post_meta($id, 'kompasz_county');
    delete_post_meta($id, 'kompasz_languages');
}

/** Kezdőértékek a munkavégzés- és árazáslistába (csak egyszer, a 6. migrációban) */
function kompasz_seed_lists_6() {
    $de = kompasz_lang() === 'de';
    $seed = [
        'munkavegzes' => $de ? ['Vor Ort', 'Remote', 'Auch internationale Kunden'] : ['Helyben (irodában)', 'Távolról', 'Külföldi ügyfelekkel is'],
        'arazas' => $de ? ['Monatliche Pauschale', 'Projektbasiert', 'Erfolgsbasiert'] : ['Havidíj', 'Projektalapú', 'Teljesítményalapú'],
    ];
    foreach ($seed as $tax => $names) foreach ($names as $n) if (!term_exists($n, $tax)) wp_insert_term($n, $tax);
    return $seed;
}

/** Egy ügynökség városa, munkavégzése, árazása szövegből → adatbázis-lista (6. migráció; az importer is ezt használja) */
function kompasz_migrate_agency_lists_6($id) {
    static $seed = null;
    $seed = $seed ?? kompasz_seed_lists_6();
    $city = trim((string) get_post_meta($id, 'kompasz_city', true));
    if ($city !== '' && ($c = kompasz_ensure_term($city, 'varos'))) wp_set_object_terms($id, [$c], 'varos');
    // Szövegből a lista elemei: kulcsszó szerint, ami nem illik, az saját elem lesz
    $map = function ($text, $tax, array $rules) use ($seed) {
        $f = kompasz_fold($text);
        $names = [];
        foreach ($rules as $re => $i) if (preg_match($re, $f)) foreach ((array) $i as $n) $names[] = $seed[$tax][$n];
        return $names ?: [$text];
    };
    $wm = trim((string) get_post_meta($id, 'kompasz_work_mode', true));
    if ($wm !== '') {
        $names = $map($wm, 'munkavegzes', ['/mindketto|beide|hybrid/' => [0, 1], '/helyben|iroda|vor ort|personlich/' => 0, '/tavol|remote|online/' => 1, '/kulfold|international|nemzetkozi/' => 2]);
        wp_set_object_terms($id, array_values(array_filter(array_map(function ($n) { return kompasz_ensure_term($n, 'munkavegzes'); }, array_unique($names)))), 'munkavegzes');
    }
    $pm = trim((string) get_post_meta($id, 'kompasz_price_model', true));
    if ($pm !== '') {
        $names = $map($pm, 'arazas', ['/havi|havidij|retainer|monat|pauschal/' => 0, '/projekt/' => 1, '/teljesitmeny|jutalek|siker|erfolg|provision/' => 2]);
        wp_set_object_terms($id, array_values(array_filter(array_map(function ($n) { return kompasz_ensure_term($n, 'arazas'); }, array_unique($names)))), 'arazas');
    }
    foreach (['kompasz_city', 'kompasz_work_mode', 'kompasz_price_model'] as $k) delete_post_meta($id, $k);
}

/**
 * Verziózott adatbázis-migrációk: verzió => [leírás, függvény]. Új szerkezetváltozás = új sor a végén (soha nem írjuk át a régit).
 * A futtatás sorrendben, a tárolt verziótól (kompasz_schema) felfelé; minden lépés után a verzió mentődik, így megszakadt futás folytatható.
 */
function kompasz_migrations() {
    return [
        2 => ['Szöveges minősítés/iparág/referencia → kifejezések; nyelvenkénti telefonszám; toplista ↔ szolgáltatás; Google-link', function () {
            kompasz_guess_toplist_tags();
            foreach (get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]) as $id) kompasz_migrate_agency($id);
        }],
        3 => ['Toplista = szolgáltatás: a toplisták a szolgáltatás tulajdonságai (URL, szövegek, tagság), 301 a régi címke-címekről; régi kompasz_services törlése', function () {
            return kompasz_merge_toplists_into_services();
        }],
        4 => ['Megyék és munkanyelvek adatbázis-listába (kezdőértékek egyszer); telefonszám nyelvenként a nyelv azonosítójával', function () {
            kompasz_seed_lists();
            foreach (get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]) as $id) kompasz_migrate_agency_lists($id);
            return [];
        }],
        5 => ['Duplikátumok összevonása (iparág, minősítés, referencia-ügyfél): egységes nevek és azonos normalizált név → egy kifejezés, a régi címek 301-gyel', function () {
            $changes = [];
            foreach (kompasz_merge_seed_maps() as $tax => $map) $changes = array_merge($changes, kompasz_merge_canonical($tax, $map));
            return array_merge($changes, kompasz_merge_auto());
        }],
        6 => ['Város, munkavégzés, árazás módja szövegből → adatbázis-lista (kezdőértékek egyszer); minden lista az adatlap közepén, keresős pipalistával', function () {
            kompasz_seed_lists_6();
            foreach (get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]) as $id) kompasz_migrate_agency_lists_6($id);
            $n = []; foreach (['varos', 'munkavegzes', 'arazas'] as $t) $n[] = $t . ': ' . count(get_terms(['taxonomy' => $t, 'hide_empty' => false, 'fields' => 'ids']) ?: []) . ' érték';
            return ['Adatbázis-listák: ' . implode(', ', $n)];
        }],
    ];
}

/** Függőben lévő migrációk futtatása; zárral, hogy két párhuzamos kérés ne fusson egyszerre. Visszaad: lefutott verziók */
function kompasz_run_migrations($force_from = null) {
    $current = $force_from !== null ? (int) $force_from : (int) get_option('kompasz_schema', 1);
    $pending = array_filter(kompasz_migrations(), function ($v) use ($current) { return $v > $current; }, ARRAY_FILTER_USE_KEY);
    if (!$pending) return [];
    if (!add_option('kompasz_migration_lock', time(), '', 'no')) {
        if (time() - (int) get_option('kompasz_migration_lock') < 600) return []; // fut egy másik kérésben
        update_option('kompasz_migration_lock', time());
    }
    @set_time_limit(600);
    $done = [];
    try {
        ksort($pending);
        foreach ($pending as $v => [$label, $fn]) {
            $changes = $fn();
            update_option('kompasz_schema', $v);
            $log = (array) get_option('kompasz_migration_log', []);
            $log[] = ['version' => $v, 'label' => $label, 'at' => gmdate('c'), 'plugin' => KOMPASZ_CORE_VERSION, 'changes' => is_array($changes) ? $changes : []];
            update_option('kompasz_migration_log', array_slice($log, -50), false);
            $done[] = $v;
        }
    } finally {
        delete_option('kompasz_migration_lock');
    }
    return $done;
}

function kompasz_schema_version() {
    return max(array_keys(kompasz_migrations()));
}

/** Kompatibilitás: régi hívások (import, CLI) */
function kompasz_migrate_all() {
    return kompasz_run_migrations(1);
}

// Biztonsági háló: ha a telepítés nem futtatta (wp kompasz migrate), az első kérés lefuttatja – a taxonómiák regisztrálása után
// 10 perc türelmi idő az új kód megjelenése után: a telepítő előbb adatbázis-mentést készít, és maga futtatja a migrációt.
add_action('init', function () {
    if ((int) get_option('kompasz_schema', 1) >= kompasz_schema_version() || !get_option('kompasz_imported')) return;
    $seen = (array) get_option('kompasz_code_seen', []);
    if (($seen['version'] ?? '') !== KOMPASZ_CORE_VERSION) { update_option('kompasz_code_seen', ['version' => KOMPASZ_CORE_VERSION, 'at' => time()]); return; }
    if (time() - (int) $seen['at'] >= 600) kompasz_run_migrations();
}, 20);
