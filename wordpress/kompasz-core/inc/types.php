<?php
// Bejegyzéstípus és taxonómiák
if (!defined('ABSPATH')) exit;

function kompasz_register_types() {
    register_post_type('ugynokseg', [
        'labels' => [
            'name' => 'Ügynökségek', 'singular_name' => 'Ügynökség', 'add_new' => 'Új ügynökség',
            'add_new_item' => 'Új ügynökség hozzáadása', 'edit_item' => 'Ügynökség szerkesztése',
            'search_items' => 'Ügynökség keresése', 'all_items' => 'Összes ügynökség', 'menu_name' => 'Ügynökségek',
            'featured_image' => 'Logó', 'set_featured_image' => 'Logó beállítása', 'remove_featured_image' => 'Logó eltávolítása',
        ],
        'public' => true,
        'has_archive' => false,
        'menu_icon' => 'dashicons-building',
        'menu_position' => 5,
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
        'rewrite' => ['slug' => kompasz_slug('ugynokseg'), 'with_front' => false],
        'show_in_rest' => true,
    ]);

    // Szolgáltatások (pl. SEO, Google Ads) – /cimke/seo/. Ha egy szolgáltatásnak van toplista oldala, az a kifejezés tulajdonsága
    // (term meta: kompasz_toplist_slug + szövegek, lásd fields.php), és a címe a gyökérben van: /google-ads-ugynoksegek/
    register_taxonomy('szolgaltatas', ['ugynokseg'], [
        'labels' => [
            'name' => 'Szolgáltatások', 'singular_name' => 'Szolgáltatás', 'add_new_item' => 'Új szolgáltatás',
            'edit_item' => 'Szolgáltatás szerkesztése', 'all_items' => 'Összes szolgáltatás', 'menu_name' => 'Szolgáltatások és toplisták',
            'search_items' => 'Szolgáltatás keresése',
        ],
        'hierarchical' => false,
        'public' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'meta_box_cb' => false,
        'rewrite' => ['slug' => kompasz_slug('cimke'), 'with_front' => false],
    ]);

    // Régi, külön toplista-taxonómia: csak a 3. migrációhoz (átvitel a szolgáltatásokba), felületen és URL-ben nem jelenik meg
    register_taxonomy('toplista', ['ugynokseg'], [
        'label' => 'Toplisták (régi)', 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'query_var' => false, 'rewrite' => false, 'hierarchical' => true,
    ]);

    // Adatbázis-listák az adatlaphoz (nem saját oldallal): az adminisztrátor bővíti (Ügynökségek → …), vagy az adatlapon „Új érték a listába”
    $lists = ['varos' => ['Városok', 'Város', 'Új város'], 'megye' => ['Megyék', 'Megye', 'Új megye'], 'nyelv' => ['Munkanyelvek', 'Munkanyelv', 'Új munkanyelv'],
        'munkavegzes' => ['Munkavégzés', 'Munkavégzési forma', 'Új munkavégzési forma'], 'arazas' => ['Árazási módok', 'Árazási mód', 'Új árazási mód']];
    foreach ($lists as $tax => [$plural, $single, $add]) {
        register_taxonomy($tax, ['ugynokseg'], [
            'labels' => ['name' => $plural, 'singular_name' => $single, 'add_new_item' => $add, 'edit_item' => $single . ' szerkesztése', 'menu_name' => $plural],
            'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'show_in_rest' => false,
            'show_admin_column' => false, 'meta_box_cb' => false, 'rewrite' => false, 'query_var' => false,
        ]);
    }

    // Referencia-ügyfelek (pl. SPAR, Tesco) – /ugyfel/spar/: azok az ügynökségek, amelyek ennek a cégnek dolgoztak
    register_taxonomy('ugyfel', ['ugynokseg'], [
        'labels' => ['name' => 'Referencia-ügyfelek', 'singular_name' => 'Referencia-ügyfél', 'add_new_item' => 'Új ügyfél',
            'edit_item' => 'Ügyfél szerkesztése', 'all_items' => 'Összes ügyfél', 'menu_name' => 'Referencia-ügyfelek'],
        'hierarchical' => false, 'public' => true, 'show_admin_column' => false, 'show_in_rest' => true,
        'rewrite' => ['slug' => kompasz_slug('ugyfel'), 'with_front' => false],
    ]);

    // Minősítések (pl. Google Partner) – /minosites/google-partner/
    register_taxonomy('minosites', ['ugynokseg'], [
        'labels' => ['name' => 'Minősítések', 'singular_name' => 'Minősítés', 'add_new_item' => 'Új minősítés',
            'edit_item' => 'Minősítés szerkesztése', 'all_items' => 'Összes minősítés', 'menu_name' => 'Minősítések'],
        'hierarchical' => false, 'public' => true, 'show_admin_column' => false, 'show_in_rest' => true,
        'rewrite' => ['slug' => kompasz_slug('minosites'), 'with_front' => false],
    ]);

    // Iparági tapasztalat (pl. Egészségügy) – /iparag/egeszsegugy/
    register_taxonomy('iparag', ['ugynokseg'], [
        'labels' => ['name' => 'Iparágak', 'singular_name' => 'Iparág', 'add_new_item' => 'Új iparág',
            'edit_item' => 'Iparág szerkesztése', 'all_items' => 'Összes iparág', 'menu_name' => 'Iparágak'],
        'hierarchical' => false, 'public' => true, 'show_admin_column' => false, 'show_in_rest' => true,
        'rewrite' => ['slug' => kompasz_slug('iparag'), 'with_front' => false],
    ]);
}
add_action('init', 'kompasz_register_types');

// Toplista oldalak a gyökérben: /online-marketing-ugynoksegek/ (a régi statikus oldal URL-jei megmaradnak)
add_filter('term_link', function ($url, $term, $taxonomy) {
    if ($taxonomy !== 'szolgaltatas') return $url;
    $slug = kompasz_toplist_slug($term);
    return $slug !== '' ? home_url('/' . $slug . '/') : $url;
}, 10, 3);

add_action('init', function () {
    foreach (kompasz_categories() as $t) {
        add_rewrite_rule('^' . preg_quote(kompasz_toplist_slug($t), '#') . '/?$', 'index.php?szolgaltatas=' . $t->slug, 'top');
    }
}, 20);

// Új, átnevezett vagy toplistává tett szolgáltatás után frissülnek az URL-szabályok
foreach (['created_szolgaltatas', 'edited_szolgaltatas', 'delete_szolgaltatas'] as $hook) {
    add_action($hook, function () { update_option('kompasz_flush_rules', 1); });
}
add_action('init', function () {
    if (get_option('kompasz_flush_rules')) { delete_option('kompasz_flush_rules'); flush_rewrite_rules(); }
}, 99);

// 301: a toplistás szolgáltatás régi címke-címe (/cimke/google-ads/) → a toplista (/google-ads-ugynoksegek/);
// összevont vagy törölt kifejezés régi címe → az új (kompasz_redirects opció: régi útvonal => [taxonómia, kifejezés])
add_action('template_redirect', function () {
    $path = trailingslashit((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (is_tax('szolgaltatas') && !is_feed() && !is_paged()) {
        $t = get_queried_object();
        if (kompasz_toplist_slug($t) !== '') {
            $to = get_term_link($t);
            if (trailingslashit((string) wp_parse_url($to, PHP_URL_PATH)) !== $path) { wp_safe_redirect($to, 301, 'Kompasz'); exit; }
        }
        return;
    }
    if (!is_404()) return;
    $map = (array) get_option('kompasz_redirects', []);
    $home = trailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    $rel = '/' . ltrim(substr($path, strlen($home) - 1), '/');
    if (!isset($map[$rel])) return;
    [$tax, $id] = $map[$rel];
    $term = get_term((int) $id, $tax);
    if ($term && !is_wp_error($term)) { wp_safe_redirect(get_term_link($term), 301, 'Kompasz'); exit; }
}, 5);

/** Átirányítás felvétele: a kifejezés mostani címe → egy másik kifejezés (összevonás előtt hívandó) */
function kompasz_add_redirect($from_term, $to_tax, $to_id) {
    $url = get_term_link($from_term);
    if (is_wp_error($url)) return;
    $home = trailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    $rel = '/' . ltrim(substr(trailingslashit((string) wp_parse_url($url, PHP_URL_PATH)), strlen($home) - 1), '/');
    $map = (array) get_option('kompasz_redirects', []);
    $map[$rel] = [$to_tax, (int) $to_id];
    // A korábban erre a kifejezésre mutató átirányítások is az újra mutassanak
    foreach ($map as $k => $v) if ($v[0] === $from_term->taxonomy && (int) $v[1] === (int) $from_term->term_id) $map[$k] = [$to_tax, (int) $to_id];
    update_option('kompasz_redirects', $map, false);
}

// Admin lista: sorrend és weboldal oszlop
add_filter('manage_ugynokseg_posts_columns', function ($cols) {
    $new = [];
    foreach ($cols as $k => $v) {
        if ($k === 'title') { $new['kompasz_logo'] = 'Logó'; }
        $new[$k] = $v;
        if ($k === 'title') { $new['kompasz_rank'] = 'Sorrend'; $new['kompasz_rating'] = 'Értékelés'; $new['kompasz_web'] = 'Weboldal'; }
    }
    return $new;
});
add_action('manage_ugynokseg_posts_custom_column', function ($col, $id) {
    if ($col === 'kompasz_logo') echo get_the_post_thumbnail($id, [40, 40], ['style' => 'width:40px;height:40px;object-fit:contain']);
    if ($col === 'kompasz_rank') echo esc_html(get_post_meta($id, 'kompasz_rank', true) ?: '–');
    if ($col === 'kompasz_rating') {
        $a = kompasz_agency($id);
        echo $a['rating'] ? esc_html(number_format($a['rating'], 1, ',', '') . ($a['rating_src'] === 'google' ? ' (Google)' : '')) : '–';
    }
    if ($col === 'kompasz_web') { $w = get_post_meta($id, 'kompasz_website', true); if ($w) printf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($w), esc_html(kompasz_domain($w))); }
}, 10, 2);
add_filter('manage_edit-ugynokseg_sortable_columns', function ($c) { $c['kompasz_rank'] = 'kompasz_rank'; return $c; });
add_action('pre_get_posts', function ($q) {
    if (is_admin() && $q->is_main_query() && $q->get('orderby') === 'kompasz_rank') {
        $q->set('meta_key', 'kompasz_rank');
        $q->set('orderby', 'meta_value_num');
    }
});

// Az ügynökségeket a klasszikus szerkesztőben szerkesztjük (egyszerű adatlap, nincs szükség blokkokra)
add_filter('use_block_editor_for_post_type', function ($use, $type) { return $type === 'ugynokseg' ? false : $use; }, 10, 2);
