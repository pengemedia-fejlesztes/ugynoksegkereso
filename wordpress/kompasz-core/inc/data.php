<?php
// Adatelérés a téma számára: ügynökség-adatok, toplisták, kiemelt ügynökség
if (!defined('ABSPATH')) exit;

function kompasz_domain($url) {
    $h = wp_parse_url($url, PHP_URL_HOST) ?: $url;
    return preg_replace('/^www\./i', '', strtolower($h));
}

function kompasz_initials($name) {
    $clean = preg_replace('/[^\p{L}\p{N} ]/u', '', $name);
    $words = preg_split('/\s+/u', trim($clean));
    $ini = '';
    foreach ($words as $w) { if ($w !== '') $ini .= mb_substr($w, 0, 1); }
    return mb_strtoupper(mb_substr($ini, 0, 2));
}

/** Soronkénti meta mező tömbként */
function kompasz_meta_lines($id, $key) {
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) get_post_meta($id, $key, true)))));
}

/** Csapatméret → szűrőkategória */
function kompasz_team_bucket($team) {
    // Sávok a LinkedIn céges létszámkategóriáihoz igazítva; tartománynál („11–50”) a felső érték dönt
    if (!$team || !preg_match_all('/\d+/', $team, $m)) return '';
    $n = (int) end($m[0]);
    if ($n <= 10) return '1–10 fő';
    if ($n <= 50) return '11–50 fő';
    if ($n <= 200) return '51–200 fő';
    return '200+ fő';
}

function kompasz_stars($r) {
    if (!$r) return '';
    $full = (int) floor($r);
    $half = ($r - $full) >= 0.25 && ($r - $full) < 0.75;
    if (($r - $full) >= 0.75) $full++;
    return str_repeat('★', $full) . ($half ? '½' : '');
}

function kompasz_num($r) { return number_format((float) $r, 1, ',', ''); }

/** Egy ügynökség minden megjelenítéshez szükséges adata (kérésenként gyorsítótárazva) */
function kompasz_agency($post) {
    static $cache = [];
    $post = get_post($post);
    if (!$post) return null;
    if (isset($cache[$post->ID])) return $cache[$post->ID];
    $id = $post->ID;
    $m = function ($k) use ($id) { return get_post_meta($id, $k, true); };
    $google = $m('kompasz_google_rating');
    $list = $m('kompasz_rating');
    $rating = $google !== '' ? (float) $google : ($list !== '' ? (float) $list : null);
    $cats = get_the_terms($id, 'toplista') ?: [];
    usort($cats, function ($a, $b) { return (int) get_term_meta($a->term_id, 'kompasz_order', true) <=> (int) get_term_meta($b->term_id, 'kompasz_order', true); });
    $tags = get_the_terms($id, 'szolgaltatas') ?: [];
    $logo_id = get_post_thumbnail_id($id);
    $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
    $website = $m('kompasz_website');
    $rank = $m('kompasz_rank');
    return $cache[$id] = [
        'id' => $id,
        'name' => get_the_title($id),
        'slug' => $post->post_name,
        'link' => get_permalink($id),
        'website' => $website,
        'domain' => $website ? kompasz_domain($website) : '',
        'city' => $m('kompasz_city'),
        'founded' => $m('kompasz_founded'),
        'team' => $m('kompasz_team'),
        'team_bucket' => kompasz_team_bucket($m('kompasz_team')),
        'services' => array_values(array_filter(preg_split('/\r?\n/', (string) $m('kompasz_services')))),
        'county' => $m('kompasz_county'),
        'phone' => $m('kompasz_phone'),
        'email' => $m('kompasz_email'),
        'intro' => $m('kompasz_intro'),
        'industries' => kompasz_meta_lines($id, 'kompasz_industries'),
        'price_min' => $m('kompasz_price_min'),
        'price_model' => $m('kompasz_price_model'),
        'certs' => kompasz_meta_lines($id, 'kompasz_certs'),
        'work_mode' => $m('kompasz_work_mode'),
        'languages' => kompasz_meta_lines($id, 'kompasz_languages'),
        'references' => kompasz_meta_lines($id, 'kompasz_references'),
        'rank' => $rank === '' ? PHP_INT_MAX : (int) $rank,
        'rating' => $rating,
        'rating_src' => $google !== '' ? 'google' : ($list !== '' ? 'list' : ''),
        'google_count' => (int) $m('kompasz_google_count'),
        'desc' => trim($content),
        'logo' => $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '',
        'logo_dark' => $m('kompasz_logo_dark') === '1',
        'initials' => kompasz_initials(get_the_title($id)),
        'cats' => $cats,
        'tags' => $tags,
    ];
}

function kompasz_sort_agencies(array $list) {
    usort($list, function ($a, $b) { return [$a['rank'], mb_strtolower($a['name'])] <=> [$b['rank'], mb_strtolower($b['name'])]; });
    return $list;
}

/** Egy toplista vagy címke ügynökségei sorrendben (rank → név), kiemelt ügynökség nélkül is lekérhető */
function kompasz_list($term) {
    static $cache = [];
    $term = is_object($term) ? $term : null;
    if (!$term) return [];
    $key = $term->taxonomy . ':' . $term->term_id;
    if (isset($cache[$key])) return $cache[$key];
    $ids = get_posts(['post_type' => 'ugynokseg', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true,
        'tax_query' => [['taxonomy' => $term->taxonomy, 'terms' => $term->term_id]]]);
    $list = kompasz_sort_agencies(array_map('kompasz_agency', $ids));
    foreach ($list as $i => &$a) { $a['pos'] = $i + 1; }
    return $cache[$key] = $list;
}

/** Toplisták a fejléc sorrendjében */
function kompasz_categories() {
    static $cats = null;
    if ($cats !== null) return $cats;
    $cats = get_terms(['taxonomy' => 'toplista', 'hide_empty' => false]);
    if (is_wp_error($cats)) return $cats = [];
    usort($cats, function ($a, $b) { return (int) get_term_meta($a->term_id, 'kompasz_order', true) <=> (int) get_term_meta($b->term_id, 'kompasz_order', true); });
    return $cats;
}

function kompasz_term_meta($term, $key, $default = '') {
    $v = get_term_meta($term->term_id, $key, true);
    return $v === '' ? $default : $v;
}

function kompasz_term_seo($term) {
    $rows = json_decode((string) get_term_meta($term->term_id, 'kompasz_seo', true), true);
    return is_array($rows) ? $rows : [];
}

/** Kiemelt (fizetett) ügynökség és a toplisták, ahol megjelenik */
function kompasz_featured() {
    $id = (int) get_option('kompasz_featured');
    if (!$id || get_post_status($id) !== 'publish') return null;
    $a = kompasz_agency($id);
    $a['pitch'] = get_option('kompasz_featured_pitch', '');
    $a['featured_in'] = array_map('intval', (array) get_option('kompasz_featured_cats', []));
    return $a;
}

function kompasz_is_featured_in($term) {
    $f = kompasz_featured();
    // Csak ott jelenik meg kiemeltként, ahol maga is szerepel a toplistán – különben nincs helyezése
    return $f && $term && in_array((int) $term->term_id, $f['featured_in'], true) && has_term((int) $term->term_id, 'toplista', $f['id']) ? $f : null;
}
