<?php
// Térkép: MapLibre GL JS + OpenFreeMap csempék, címkeresés: Nominatim (OpenStreetMap) – kulcs és számlázás nélkül.
// Adat: kompasz_address (cím), kompasz_lat / kompasz_lng (koordináta), kompasz_geo (pontosság: address | city | manual).
// Az adminban a címet a térkép keresőjével lehet megtalálni (vagy a térképre kattintva / a jelölőt húzva); akinek nincs koordinátája,
// annak a háttérben a városa (és címe) alapján keres egyet a Nominatim (óránként legfeljebb 30, 1 kérés/mp – a Nominatim szabályai szerint).
if (!defined('ABSPATH')) exit;

const KOMPASZ_MAPLIBRE = 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl';
const KOMPASZ_GEO_HOOK = 'kompasz_geocode';

function kompasz_map_style() {
    return get_option('kompasz_map_style') ?: 'https://tiles.openfreemap.org/styles/liberty';
}

/** A térkép beállításai a böngészőnek (admin és nyilvános oldal) */
function kompasz_map_config() {
    $loc = kompasz_locale();
    return [
        'js' => KOMPASZ_MAPLIBRE . '.js', 'css' => KOMPASZ_MAPLIBRE . '.css', 'style' => kompasz_map_style(),
        'nominatim' => 'https://nominatim.openstreetmap.org/search', 'country' => strtolower($loc['country']), 'lang' => $loc['html'],
        // Alapnézet: az ország közepe
        'center' => $loc['country'] === 'DE' ? [10.45, 51.16] : [19.5, 47.16], 'zoom' => $loc['country'] === 'DE' ? 5 : 6.3,
    ];
}

function kompasz_map_admin_html($id) {
    $lat = get_post_meta($id, 'kompasz_lat', true);
    $lng = get_post_meta($id, 'kompasz_lng', true);
    $geo = get_post_meta($id, 'kompasz_geo', true);
    $labels = ['address' => 'cím alapján', 'city' => 'csak a város alapján (pontosítsd!)', 'manual' => 'kézzel beállítva'];
    ob_start(); ?>
    <div class="kmap-edit" data-config="<?php echo esc_attr(wp_json_encode(kompasz_map_config())); ?>">
        <p><input type="search" class="regular-text kmap-q" placeholder="Cím keresése, pl. Budapest, Váci út 1." value="<?php echo esc_attr(implode(', ', array_filter([get_post_meta($id, 'kompasz_address', true), get_post_meta($id, 'kompasz_city', true)]))); ?>">
            <button type="button" class="button kmap-search">Keresés a térképen</button>
            <button type="button" class="button-link kmap-clear">Pozíció törlése</button></p>
        <ul class="kmap-results" hidden></ul>
        <div class="kmap" style="height:300px"></div>
        <input type="hidden" name="kompasz_lat" value="<?php echo esc_attr($lat); ?>"><input type="hidden" name="kompasz_lng" value="<?php echo esc_attr($lng); ?>">
        <input type="hidden" name="kompasz_geo" value="<?php echo esc_attr($geo); ?>">
        <p class="description kmap-status"><?php echo $lat !== '' ? esc_html(sprintf('Pozíció: %s, %s – %s. ', $lat, $lng, $labels[$geo] ?? '')) : 'Még nincs pozíció. '; ?>Keress rá a címre, vagy kattints a térképre; a jelölő húzható. A nyilvános adatlapon ez a térkép jelenik meg.</p>
    </div>
    <?php return ob_get_clean();
}

function kompasz_map_save($post_id, $lat, $lng) {
    $lat = trim((string) $lat); $lng = trim((string) $lng);
    if ($lat === '' || $lng === '') {
        foreach (['kompasz_lat', 'kompasz_lng', 'kompasz_geo'] as $k) delete_post_meta($post_id, $k);
        update_post_meta($post_id, 'kompasz_geo_skip', '1'); // kézzel törölve: a háttérkeresés ne töltse vissza
        return;
    }
    $la = max(-90, min(90, (float) $lat)); $lo = max(-180, min(180, (float) $lng));
    if ((string) round($la, 6) === (string) round((float) get_post_meta($post_id, 'kompasz_lat', true), 6) && (string) round($lo, 6) === (string) round((float) get_post_meta($post_id, 'kompasz_lng', true), 6)) return;
    update_post_meta($post_id, 'kompasz_lat', (string) round($la, 6));
    update_post_meta($post_id, 'kompasz_lng', (string) round($lo, 6));
    $geo = sanitize_key($_POST['kompasz_geo'] ?? 'manual');
    update_post_meta($post_id, 'kompasz_geo', in_array($geo, ['address', 'city', 'manual'], true) ? $geo : 'manual');
    delete_post_meta($post_id, 'kompasz_geo_skip');
}

add_action('admin_enqueue_scripts', function ($hook) {
    $s = get_current_screen();
    if (!in_array($hook, ['post.php', 'post-new.php'], true) || !$s || $s->post_type !== 'ugynokseg') return;
    wp_enqueue_script('kompasz-admin-map', KOMPASZ_CORE_URL . 'assets/admin-map.js', [], filemtime(KOMPASZ_CORE_DIR . 'assets/admin-map.js'), true);
});

// --- Háttér-helymeghatározás (Nominatim): koordináta nélküli ügynökségek a város (+ cím) alapján ---
function kompasz_nominatim($q) {
    $cache = (array) get_option('kompasz_geo_cache', []);
    $key = md5(mb_strtolower($q));
    if (isset($cache[$key])) return $cache[$key];
    $cfg = kompasz_map_config();
    $url = add_query_arg(['q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => $cfg['country'], 'accept-language' => $cfg['lang']], $cfg['nominatim']);
    // A Nominatim szabálya: azonosítható User-Agent, legfeljebb 1 kérés másodpercenként
    $r = wp_remote_get($url, ['timeout' => 15, 'headers' => ['Referer' => home_url('/')],
        'user-agent' => 'Kompasz/' . KOMPASZ_CORE_VERSION . ' (' . home_url('/') . '; ' . (get_option('kompasz_email') ?: get_option('admin_email')) . ')']);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
    $d = json_decode(wp_remote_retrieve_body($r), true);
    $hit = !empty($d[0]['lat']) ? [round((float) $d[0]['lat'], 6), round((float) $d[0]['lon'], 6)] : false;
    $cache[$key] = $hit;
    update_option('kompasz_geo_cache', array_slice($cache, -2000, null, true), false);
    return $hit;
}

/** Legfeljebb $limit ügynökség koordinátája. Visszaad: [found, missing, remaining] */
function kompasz_geocode_batch($limit = 30) {
    $ids = get_posts(['post_type' => 'ugynokseg', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'meta_query' => [
        'relation' => 'AND',
        ['key' => 'kompasz_lat', 'compare' => 'NOT EXISTS'], ['key' => 'kompasz_geo_skip', 'compare' => 'NOT EXISTS'],
        ['key' => 'kompasz_city', 'value' => '', 'compare' => '!='],
    ]]);
    $found = 0; $missing = 0; $calls = 0;
    foreach (array_slice($ids, 0, $limit) as $id) {
        $city = trim((string) get_post_meta($id, 'kompasz_city', true));
        $addr = trim((string) get_post_meta($id, 'kompasz_address', true));
        $hit = null; $geo = 'city';
        foreach (array_filter([$addr !== '' ? "$addr, $city" : '', $city]) as $q) {
            $cached = array_key_exists(md5(mb_strtolower($q)), (array) get_option('kompasz_geo_cache', []));
            if (!$cached && $calls++) sleep(1);
            $hit = kompasz_nominatim($q);
            if ($hit === null) return [$found, $missing, count($ids) - $found - $missing]; // hálózati hiba: később újra
            if ($hit) { $geo = $q === $city ? 'city' : 'address'; break; }
        }
        if ($hit) {
            update_post_meta($id, 'kompasz_lat', (string) $hit[0]);
            update_post_meta($id, 'kompasz_lng', (string) $hit[1]);
            update_post_meta($id, 'kompasz_geo', $geo);
            $found++;
        } else { update_post_meta($id, 'kompasz_geo_skip', 'notfound'); $missing++; }
    }
    return [$found, $missing, max(0, count($ids) - $found - $missing)];
}

add_action('init', function () {
    if (!wp_next_scheduled(KOMPASZ_GEO_HOOK)) wp_schedule_event(time() + 300, 'hourly', KOMPASZ_GEO_HOOK);
});
add_action(KOMPASZ_GEO_HOOK, function () { kompasz_geocode_batch(30); });
register_deactivation_hook(KOMPASZ_CORE_FILE, function () { wp_clear_scheduled_hook(KOMPASZ_GEO_HOOK); });

// A cím vagy a város változásakor (és ha nincs kézi pozíció) a háttérkeresés újra próbálkozhat
add_action('updated_post_meta', function ($mid, $post_id, $key) {
    if (!in_array($key, ['kompasz_city', 'kompasz_address'], true) || get_post_type($post_id) !== 'ugynokseg') return;
    if (get_post_meta($post_id, 'kompasz_geo_skip', true) === 'notfound') delete_post_meta($post_id, 'kompasz_geo_skip');
}, 10, 3);

/** Nyilvános adatlap: térkép-tároló (a téma szkriptje csak akkor tölti be a MapLibre-t, ha a térkép a képernyőre ér) */
function kompasz_map_html($a) {
    if ($a['lat'] === null || $a['lng'] === null) return '';
    $geo = get_post_meta($a['id'], 'kompasz_geo', true);
    $cfg = array_merge(kompasz_map_config(), ['lat' => $a['lat'], 'lng' => $a['lng'], 'zoom' => $geo === 'city' ? 11 : 15, 'name' => $a['name']]);
    $place = trim(implode(', ', array_filter([$a['address'], $a['city']])));
    $osm = sprintf('https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$d/%1$s/%2$s', $a['lat'], $a['lng'], $cfg['zoom']);
    return sprintf('<div class="pmap"><div class="kmap-pub" data-map="%s" role="img" aria-label="%s"></div><p class="pmap-cap">%s <a href="%s" target="_blank" rel="noopener">%s ↗</a></p></div>',
        esc_attr(wp_json_encode($cfg)), esc_attr(sprintf(kompasz__('%s helye a térképen'), $a['name'])), esc_html($place),
        esc_url($osm), esc_html(kompasz__('Nagyobb térkép')));
}
