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
        'rewrite' => ['slug' => 'ugynokseg', 'with_front' => false],
        'show_in_rest' => true,
    ]);

    // Toplisták (pl. Online marketing ügynökségek) – az URL a toplista slugja: /online-marketing-ugynoksegek/
    register_taxonomy('toplista', ['ugynokseg'], [
        'labels' => [
            'name' => 'Toplisták', 'singular_name' => 'Toplista', 'add_new_item' => 'Új toplista',
            'edit_item' => 'Toplista szerkesztése', 'all_items' => 'Összes toplista', 'menu_name' => 'Toplisták',
        ],
        'hierarchical' => true,
        'public' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'toplista', 'with_front' => false],
    ]);

    // Szolgáltatás-címkék (pl. SEO, Google Ads) – /cimke/seo/
    register_taxonomy('szolgaltatas', ['ugynokseg'], [
        'labels' => [
            'name' => 'Szolgáltatás-címkék', 'singular_name' => 'Szolgáltatás-címke', 'add_new_item' => 'Új címke',
            'edit_item' => 'Címke szerkesztése', 'all_items' => 'Összes címke', 'menu_name' => 'Szolgáltatás-címkék',
        ],
        'hierarchical' => false,
        'public' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'cimke', 'with_front' => false],
    ]);

    // Referencia-ügyfelek (pl. SPAR, Tesco) – /ugyfel/spar/: azok az ügynökségek, amelyek ennek a cégnek dolgoztak
    register_taxonomy('ugyfel', ['ugynokseg'], [
        'labels' => ['name' => 'Referencia-ügyfelek', 'singular_name' => 'Referencia-ügyfél', 'add_new_item' => 'Új ügyfél',
            'edit_item' => 'Ügyfél szerkesztése', 'all_items' => 'Összes ügyfél', 'menu_name' => 'Referencia-ügyfelek'],
        'hierarchical' => false, 'public' => true, 'show_admin_column' => false, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'ugyfel', 'with_front' => false],
    ]);

    // Minősítések (pl. Google Partner) – /minosites/google-partner/
    register_taxonomy('minosites', ['ugynokseg'], [
        'labels' => ['name' => 'Minősítések', 'singular_name' => 'Minősítés', 'add_new_item' => 'Új minősítés',
            'edit_item' => 'Minősítés szerkesztése', 'all_items' => 'Összes minősítés', 'menu_name' => 'Minősítések'],
        'hierarchical' => false, 'public' => true, 'show_admin_column' => false, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'minosites', 'with_front' => false],
    ]);
}
add_action('init', 'kompasz_register_types');

// Toplisták a gyökérben: /online-marketing-ugynoksegek/ (a régi statikus oldal URL-jei megmaradnak)
add_filter('term_link', function ($url, $term, $taxonomy) {
    return $taxonomy === 'toplista' ? home_url('/' . $term->slug . '/') : $url;
}, 10, 3);

add_action('init', function () {
    $terms = get_terms(['taxonomy' => 'toplista', 'hide_empty' => false, 'fields' => 'slugs']);
    if (is_wp_error($terms)) return;
    foreach ($terms as $slug) {
        add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?toplista=' . $slug, 'top');
    }
}, 20);

// Új vagy átnevezett toplista után frissülnek az URL-szabályok
foreach (['created_toplista', 'edited_toplista', 'delete_toplista'] as $hook) {
    add_action($hook, function () { update_option('kompasz_flush_rules', 1); });
}
add_action('init', function () {
    if (get_option('kompasz_flush_rules')) { delete_option('kompasz_flush_rules'); flush_rewrite_rules(); }
}, 99);

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
