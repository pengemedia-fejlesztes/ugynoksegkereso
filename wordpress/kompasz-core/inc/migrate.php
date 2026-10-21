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

/** Egy ügynökség régi szöveges adatainak átvitele (az importer is ezt használja) */
function kompasz_migrate_agency($id) {
    $lines = function ($k) use ($id) { return kompasz_meta_lines($id, $k); };
    if ($l = $lines('kompasz_certs')) kompasz_lines_to_terms($id, 'minosites', $l);
    if ($l = $lines('kompasz_industries')) kompasz_lines_to_terms($id, 'iparag', $l);
    if ($l = $lines('kompasz_references')) kompasz_refs_to_terms($id, $l);
    foreach (['kompasz_certs', 'kompasz_industries', 'kompasz_references'] as $k) delete_post_meta($id, $k);
    if (get_post_meta($id, 'kompasz_phones', true) === '') {
        $phones = kompasz_agency_phones($id);
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
 * Verziózott adatbázis-migrációk: verzió => [leírás, függvény]. Új szerkezetváltozás = új sor a végén (soha nem írjuk át a régit).
 * A futtatás sorrendben, a tárolt verziótól (kompasz_schema) felfelé; minden lépés után a verzió mentődik, így megszakadt futás folytatható.
 */
function kompasz_migrations() {
    return [
        2 => ['Szöveges minősítés/iparág/referencia → kifejezések; nyelvenkénti telefonszám; toplista ↔ szolgáltatás; Google-link', function () {
            kompasz_guess_toplist_tags();
            foreach (get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]) as $id) kompasz_migrate_agency($id);
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
            $fn();
            update_option('kompasz_schema', $v);
            $log = (array) get_option('kompasz_migration_log', []);
            $log[] = ['version' => $v, 'label' => $label, 'at' => gmdate('c'), 'plugin' => KOMPASZ_CORE_VERSION];
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
