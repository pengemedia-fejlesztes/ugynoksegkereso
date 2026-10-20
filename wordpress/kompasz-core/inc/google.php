<?php
// Google-értékelések: az ügynökség Google Cégprofil linkjéből a Places API (New) hetente frissíti az értékelést és a vélemények számát.
// API-kulcs: Kompasz → Beállítások (Google Cloud: Places API (New) engedélyezve), vagy wp-config.php: define('KOMPASZ_GOOGLE_API_KEY', '…');
if (!defined('ABSPATH')) exit;

const KOMPASZ_GOOGLE_HOOK = 'kompasz_google_weekly';
const KOMPASZ_GOOGLE_BATCH_HOOK = 'kompasz_google_batch';

function kompasz_google_key() {
    return defined('KOMPASZ_GOOGLE_API_KEY') ? KOMPASZ_GOOGLE_API_KEY : trim((string) get_option('kompasz_google_api_key'));
}

function kompasz_google_request($method, $url, $mask, $body = null) {
    $args = ['method' => $method, 'timeout' => 20, 'headers' => ['X-Goog-Api-Key' => kompasz_google_key(), 'X-Goog-FieldMask' => $mask, 'Content-Type' => 'application/json']];
    if ($body !== null) $args['body'] = wp_json_encode($body);
    $r = wp_remote_request($url, $args);
    if (is_wp_error($r)) return $r;
    $data = json_decode(wp_remote_retrieve_body($r), true);
    if (wp_remote_retrieve_response_code($r) !== 200) return new WP_Error('google', $data['error']['message'] ?? ('HTTP ' . wp_remote_retrieve_response_code($r)));
    return $data ?: [];
}

/** Rövid linkek (maps.app.goo.gl, g.page, share.google) kibontása a végső Google Térkép URL-re */
function kompasz_google_expand($url) {
    for ($i = 0; $i < 6; $i++) {
        $r = wp_remote_head($url, ['redirection' => 0, 'timeout' => 10]);
        if (is_wp_error($r)) $r = wp_remote_get($url, ['redirection' => 0, 'timeout' => 10]);
        if (is_wp_error($r)) break;
        $loc = wp_remote_retrieve_header($r, 'location');
        if (!$loc) break;
        $url = WP_Http::make_absolute_url($loc, $url);
    }
    return $url;
}

/** A Cégprofil link → Google Place ID (egyszer kell kikeresni, utána tároljuk) */
function kompasz_google_place_id($post_id) {
    $cached = get_post_meta($post_id, 'kompasz_google_place_id', true);
    if ($cached) return $cached;
    $link = trim((string) get_post_meta($post_id, 'kompasz_google_url', true));
    if ($link === '') return new WP_Error('google', 'Nincs megadva Google Cégprofil link.');
    $url = kompasz_google_expand($link);
    $dec = rawurldecode($url);
    if (preg_match('/(?:place_id[:=]|query_place_id=|placeid=)([A-Za-z0-9_-]{20,})/', $dec, $mm)) {
        update_post_meta($post_id, 'kompasz_google_place_id', $mm[1]);
        return $mm[1];
    }
    // Név és koordináta a Térkép URL-ből: /maps/place/<Név>/@47.49,19.04,17z ; ügyfél-azonosító (CID): ?cid= vagy !1s0x…:0x<hex>
    $name = preg_match('#/maps/place/([^/@?]+)#', $url, $mm) ? trim(str_replace('+', ' ', rawurldecode($mm[1]))) : '';
    $cid = '';
    if (preg_match('/[?&]cid=(\d+)/', $dec, $mm)) $cid = $mm[1];
    elseif (preg_match('/!1s0x[0-9a-f]+:0x([0-9a-f]+)/i', $dec, $mm) && function_exists('gmp_init')) $cid = gmp_strval(gmp_init($mm[1], 16));
    elseif (preg_match('/!1s0x[0-9a-f]+:0x([0-9a-f]+)/i', $dec, $mm)) $cid = base_convert($mm[1], 16, 10);
    $body = ['textQuery' => $name ?: trim(get_the_title($post_id) . ' ' . get_post_meta($post_id, 'kompasz_city', true)), 'languageCode' => kompasz_lang(), 'pageSize' => 5];
    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $mm)) $body['locationBias'] = ['circle' => ['center' => ['latitude' => (float) $mm[1], 'longitude' => (float) $mm[2]], 'radius' => 2000.0]];
    $res = kompasz_google_request('POST', 'https://places.googleapis.com/v1/places:searchText', 'places.id,places.displayName,places.googleMapsUri', $body);
    if (is_wp_error($res)) return $res;
    $places = $res['places'] ?? [];
    if (!$places) return new WP_Error('google', 'A Google nem talált céget ehhez a linkhez.');
    $pick = $places[0];
    if ($cid) foreach ($places as $p) if (strpos($p['googleMapsUri'] ?? '', 'cid=' . $cid) !== false) { $pick = $p; break; }
    update_post_meta($post_id, 'kompasz_google_place_id', $pick['id']);
    return $pick['id'];
}

/** Egy ügynökség frissítése; true vagy WP_Error */
function kompasz_google_sync($post_id) {
    if (!kompasz_google_key()) $err = new WP_Error('google', 'Nincs Google API-kulcs (Kompasz → Beállítások).');
    else {
        $place = kompasz_google_place_id($post_id);
        $err = is_wp_error($place) ? $place : null;
        if (!$err) {
            $res = kompasz_google_request('GET', 'https://places.googleapis.com/v1/places/' . rawurlencode($place) . '?languageCode=' . kompasz_lang(), 'id,displayName,rating,userRatingCount,googleMapsUri');
            if (is_wp_error($res)) $err = $res;
            else {
                update_post_meta($post_id, 'kompasz_google_rating', isset($res['rating']) ? (string) round((float) $res['rating'], 1) : '');
                update_post_meta($post_id, 'kompasz_google_count', isset($res['userRatingCount']) ? (string) (int) $res['userRatingCount'] : '');
                update_post_meta($post_id, 'kompasz_google_source', 'Google Places API: ' . ($res['displayName']['text'] ?? '') . ' (' . wp_date('Y-m-d') . ')');
                update_post_meta($post_id, 'kompasz_google_synced', time());
                delete_post_meta($post_id, 'kompasz_google_error');
                return true;
            }
        }
    }
    update_post_meta($post_id, 'kompasz_google_error', $err->get_error_message() . ' (' . wp_date('Y-m-d H:i') . ')');
    return $err;
}

// Heti ütemezés: kötegekben (20 ügynökség / futás), hogy egy kérés se fusson túl sokáig
add_action('init', function () {
    if (!wp_next_scheduled(KOMPASZ_GOOGLE_HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', KOMPASZ_GOOGLE_HOOK);
});
add_action(KOMPASZ_GOOGLE_HOOK, function () {
    if (kompasz_google_key()) wp_schedule_single_event(time(), KOMPASZ_GOOGLE_BATCH_HOOK, [0]);
});
add_action(KOMPASZ_GOOGLE_BATCH_HOOK, function ($offset) {
    $ids = get_posts(['post_type' => 'ugynokseg', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 20, 'offset' => (int) $offset, 'orderby' => 'ID', 'order' => 'ASC',
        'meta_query' => [['key' => 'kompasz_google_url', 'value' => '', 'compare' => '!=']]]);
    foreach ($ids as $id) kompasz_google_sync($id);
    if (count($ids) === 20) wp_schedule_single_event(time() + 30, KOMPASZ_GOOGLE_BATCH_HOOK, [(int) $offset + 20]);
    else update_option('kompasz_google_last_run', time());
});
register_deactivation_hook(KOMPASZ_CORE_FILE, function () { wp_clear_scheduled_hook(KOMPASZ_GOOGLE_HOOK); });

// Kézi frissítés: egy ügynökség (adatlap) vagy mind (beállítások)
add_action('admin_post_kompasz_google_sync', function () {
    if (!current_user_can('manage_options') || !check_admin_referer('kompasz_google_sync')) wp_die('Nincs jogosultság.');
    $post = (int) ($_GET['post'] ?? 0);
    if ($post) {
        $r = kompasz_google_sync($post);
        wp_safe_redirect(add_query_arg('kompasz_google', is_wp_error($r) ? 'error' : 'ok', get_edit_post_link($post, 'raw')));
    } else {
        wp_schedule_single_event(time(), KOMPASZ_GOOGLE_BATCH_HOOK, [0]);
        spawn_cron();
        wp_safe_redirect(admin_url('admin.php?page=kompasz&kompasz_google=started'));
    }
    exit;
});

function kompasz_google_status_html($post_id) {
    $m = function ($k) use ($post_id) { return get_post_meta($post_id, $k, true); };
    $rating = $m('kompasz_google_rating');
    $synced = (int) $m('kompasz_google_synced');
    $out = '<h3>Google-értékelés</h3><p>';
    $out .= $rating !== '' ? sprintf('<strong>%s ★</strong> (%s vélemény)', esc_html(kompasz_num($rating)), esc_html($m('kompasz_google_count') ?: '0')) : 'Még nincs Google-értékelés.';
    $out .= $synced ? ' · utolsó frissítés: ' . esc_html(wp_date('Y-m-d H:i', $synced)) : '';
    $out .= $m('kompasz_google_source') ? '<br><span class="description">' . esc_html($m('kompasz_google_source')) . '</span>' : '';
    if ($m('kompasz_google_error')) $out .= '<br><span style="color:#b32d2e">Hiba: ' . esc_html($m('kompasz_google_error')) . '</span>';
    $out .= '</p>';
    if (isset($_GET['kompasz_google'])) $out .= $_GET['kompasz_google'] === 'ok' ? '<p style="color:#00a32a">Frissítve.</p>' : '';
    $out .= sprintf('<p><a class="button" href="%s">Szinkronizálás most</a> <span class="description">A „Google Cégprofil link” mezőből; automatikusan hetente fut.%s</span></p>',
        esc_url(wp_nonce_url(admin_url('admin-post.php?action=kompasz_google_sync&post=' . $post_id), 'kompasz_google_sync')), kompasz_google_key() ? '' : ' <strong>Előbb add meg a Google API-kulcsot a Kompasz → Beállításokban.</strong>');
    return $out;
}
