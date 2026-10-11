<?php
// Előfizetői csomagok: a csomagot az admin a felhasználó adatlapján állítja be; a csomag a felhasználóhoz rendelt ügynökségre érvényes.
// Előfizetőnél a weboldal közvetlen linkkel és a telefonszám látszik; nem előfizetőnél a telefonszám rejtett,
// a weboldal pedig csak a saját átirányító címünkön (/x/<azonosító>-<aláírás>/) keresztül érhető el, az Index dex.hu/x mintájára; a cél-cím nem szerepel az oldal kódjában.
if (!defined('ABSPATH')) exit;

const KOMPASZ_PACKAGE_META = 'kompasz_package';

/** A csomagok és a bennük lévő funkciók – új csomag ide vehető fel */
function kompasz_packages() {
    return [
        'alap' => ['label' => 'Előfizetés', 'features' => ['phone', 'website']],
    ];
}

/** Az ügynökséghez rendelt felhasználó csomagja ('' = nem előfizető) */
function kompasz_agency_package($agency_id) {
    $uid = (int) get_post_meta($agency_id, 'kompasz_owner_user', true);
    if (!$uid) return '';
    $p = (string) get_user_meta($uid, KOMPASZ_PACKAGE_META, true);
    return isset(kompasz_packages()[$p]) ? $p : '';
}

function kompasz_agency_has($agency_id, $feature) {
    $p = kompasz_agency_package($agency_id);
    return $p !== '' && in_array($feature, kompasz_packages()[$p]['features'], true);
}

// --- Felhasználói adatlap: csomag beállítása (csak adminisztrátor) ---
$kompasz_package_field = function ($user) {
    if (!current_user_can('manage_options')) return;
    $cur = (string) get_user_meta($user->ID, KOMPASZ_PACKAGE_META, true);
    $agencies = function_exists('kompasz_partner_agency_ids') ? kompasz_partner_agency_ids($user->ID) : [];
    echo '<h2>Előfizetés</h2><table class="form-table" role="presentation"><tr><th><label for="kompasz_package">Csomag</label></th><td>';
    echo '<select name="kompasz_package" id="kompasz_package"><option value="">Nem előfizető</option>';
    foreach (kompasz_packages() as $k => $p) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($cur, $k, false), esc_html($p['label']));
    echo '</select><p class="description">Előfizetőnél a cégadatlapon látszik a telefonszám és a weboldal közvetlen linkje. ';
    echo $agencies ? 'Hozzárendelt cégadatlap: ' . implode(', ', array_map(function ($id) { return '<a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html(get_the_title($id)) . '</a>'; }, $agencies)) . '.' : 'A felhasználóhoz még nincs cégadatlap rendelve (az ügynökség szerkesztőjében, a „Cégfiók” dobozban rendelhető hozzá).';
    echo '</p></td></tr></table>';
};
add_action('show_user_profile', $kompasz_package_field);
add_action('edit_user_profile', $kompasz_package_field);
$kompasz_package_save = function ($user_id) {
    if (!current_user_can('manage_options') || !isset($_POST['kompasz_package'])) return;
    $p = sanitize_key(wp_unslash($_POST['kompasz_package']));
    if ($p === '' || !isset(kompasz_packages()[$p])) delete_user_meta($user_id, KOMPASZ_PACKAGE_META);
    else update_user_meta($user_id, KOMPASZ_PACKAGE_META, $p);
};
add_action('personal_options_update', $kompasz_package_save);
add_action('edit_user_profile_update', $kompasz_package_save);

// Felhasználói lista: csomag oszlop
add_filter('manage_users_columns', function ($cols) { $cols['kompasz_package'] = 'Előfizetés'; return $cols; });
add_filter('manage_users_custom_column', function ($out, $col, $user_id) {
    if ($col !== 'kompasz_package') return $out;
    $p = (string) get_user_meta($user_id, KOMPASZ_PACKAGE_META, true);
    return isset(kompasz_packages()[$p]) ? esc_html(kompasz_packages()[$p]['label']) : '—';
}, 10, 3);

// --- Kimenő link nem előfizetőknek: /x/<ügynökség-azonosító>-<aláírás>/ ---
function kompasz_out_sig($url, $agency_id) {
    return substr(hash_hmac('sha256', $agency_id . '|' . $url, wp_salt('auth')), 0, 16);
}

/** A weboldal linkje: előfizetőnél közvetlen, egyébként az átirányítón át */
function kompasz_website_href($a) {
    if (empty($a['website'])) return '';
    if (kompasz_agency_has($a['id'], 'website')) return $a['website'];
    // A linkben csak az ügynökség azonosítója és egy aláírás van – a cél-cím nem szerepel az oldal kódjában
    return home_url('/x/' . (int) $a['id'] . '-' . kompasz_out_sig($a['website'], (int) $a['id']) . '/');
}

add_action('init', function () { add_rewrite_rule('^x/([0-9]+)-([0-9a-f]{16})/?$', 'index.php?kompasz_out=$matches[1]&kompasz_sig=$matches[2]', 'top'); });
add_filter('query_vars', function ($v) { $v[] = 'kompasz_out'; $v[] = 'kompasz_sig'; return $v; });
add_filter('robots_txt', function ($txt) { return $txt . "\nDisallow: /x/\n"; });

add_action('template_redirect', function () {
    if (!get_query_var('kompasz_out')) return;
    $aid = (int) get_query_var('kompasz_out');
    $sig = (string) get_query_var('kompasz_sig');
    $url = $aid && get_post_type($aid) === 'ugynokseg' ? (string) get_post_meta($aid, 'kompasz_website', true) : '';
    header('X-Robots-Tag: noindex, nofollow');
    nocache_headers();
    if (!$url || !hash_equals(kompasz_out_sig($url, $aid), $sig)) {
        status_header(404);
        wp_die('Ez a hivatkozás nem érvényes.', 'Érvénytelen hivatkozás', ['response' => 404]);
    }
    update_post_meta($aid, 'kompasz_out_clicks', (int) get_post_meta($aid, 'kompasz_out_clicks', true) + 1);
    // Saját oldalról érkezett kattintás: azonnali átirányítás; máshonnan (pl. kimásolt link): köztes oldal
    $ref = wp_get_referer() ?: ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref && wp_parse_url($ref, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST)) {
        wp_redirect($url, 302, 'Kompasz');
        exit;
    }
    status_header(200);
    $name = get_the_title($aid);
    $host = wp_parse_url($url, PHP_URL_HOST);
    printf('<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Továbblépés – %1$s</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F6F4EF;font:16px/1.5 system-ui,sans-serif;color:#111318}.c{max-width:420px;margin:16px;background:#fff;border:1px solid #E2DED5;border-radius:18px;padding:28px}h1{font-size:22px;margin:0 0 8px}p{color:#5C5F66;margin:0 0 18px}a.b{display:inline-block;background:#0E6B4F;color:#fff;text-decoration:none;border-radius:12px;padding:12px 18px;font-weight:600}</style></head><body><div class="c"><h1>Továbblépés a(z) %1$s weboldalára</h1><p>A Kompasz oldaláról egy külső weboldalra lépsz: %2$s</p><a class="b" href="%3$s" rel="nofollow noopener">Tovább a weboldalra →</a></div></body></html>',
        esc_html($name), esc_html($host), esc_url($url));
    exit;
});

// Admin: kimenő kattintások száma az ügynökség-listában
add_filter('manage_ugynokseg_posts_columns', function ($cols) { $cols['kompasz_clicks'] = 'Kattintás a weboldalra'; $cols['kompasz_pkg'] = 'Előfizetés'; return $cols; }, 20);
add_action('manage_ugynokseg_posts_custom_column', function ($col, $id) {
    if ($col === 'kompasz_clicks') echo (int) get_post_meta($id, 'kompasz_out_clicks', true);
    if ($col === 'kompasz_pkg') { $p = kompasz_agency_package($id); echo $p ? esc_html(kompasz_packages()[$p]['label']) : '—'; }
}, 10, 2);
