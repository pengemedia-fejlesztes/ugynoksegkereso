<?php
// Referencia-ügyfelek, minősítések, iparágak: új érték jóváhagyása (amíg az adminisztrátor nem engedélyezi, a nyilvános oldalon nem látszik)
if (!defined('ABSPATH')) exit;

const KOMPASZ_PENDING_META = 'kompasz_pending';

function kompasz_approval_taxonomies() {
    return ['ugyfel' => 'Referencia-ügyfél', 'minosites' => 'Minősítés', 'iparag' => 'Iparág'];
}

function kompasz_term_is_pending($term) {
    $id = is_object($term) ? $term->term_id : (int) $term;
    return (bool) get_term_meta($id, KOMPASZ_PENDING_META, true);
}

/**
 * Nevek → kifejezés-azonosítók. A meglévőt név vagy slug alapján megtalálja; az újat létrehozza.
 * Adminisztrátor által felvett érték azonnal él; másé (cégfiók) jóváhagyásra vár, és az adminisztrátor e-mailt kap róla.
 */
function kompasz_resolve_terms($taxonomy, array $names, $post_id = 0, $approved = null) {
    $approved = $approved ?? current_user_can('manage_categories');
    $ids = [];
    foreach ($names as $name) {
        $name = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $name)));
        if ($name === '' || mb_strlen($name) > 80) continue;
        $term = get_term_by('name', $name, $taxonomy) ?: get_term_by('slug', sanitize_title($name), $taxonomy);
        if (!$term) {
            foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $name]) ?: [] as $t) {
                if (mb_strtolower($t->name) === mb_strtolower($name)) { $term = $t; break; }
            }
        }
        if ($term) { $ids[] = (int) $term->term_id; continue; }
        $new = wp_insert_term($name, $taxonomy);
        if (is_wp_error($new)) continue;
        $id = (int) $new['term_id'];
        if (!$approved) {
            update_term_meta($id, KOMPASZ_PENDING_META, '1');
            update_term_meta($id, 'kompasz_pending_by', get_current_user_id());
            update_term_meta($id, 'kompasz_pending_post', (int) $post_id);
            kompasz_notify_pending($id, $taxonomy, $name, $post_id);
        }
        $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function kompasz_notify_pending($term_id, $taxonomy, $name, $post_id) {
    $u = wp_get_current_user();
    $labels = kompasz_approval_taxonomies();
    $to = get_option('admin_email');
    $subject = sprintf('[%s] Jóváhagyásra vár: %s – %s', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $labels[$taxonomy] ?? $taxonomy, $name);
    $body = sprintf("Új %s érkezett jóváhagyásra.\n\nÉrték: %s\nÜgynökség: %s\nBeküldte: %s (%s)\n\nJóváhagyás vagy elutasítás:\n%s\n",
        mb_strtolower($labels[$taxonomy] ?? $taxonomy), $name, $post_id ? get_the_title($post_id) : '–', $u->display_name, $u->user_email,
        admin_url('edit.php?post_type=ugynokseg&page=kompasz-pending'));
    wp_mail($to, $subject, $body);
}

/** Jóváhagyásra váró kifejezések */
function kompasz_pending_terms() {
    $terms = get_terms(['taxonomy' => array_keys(kompasz_approval_taxonomies()), 'hide_empty' => false,
        'meta_query' => [['key' => KOMPASZ_PENDING_META, 'value' => '1']]]);
    return is_wp_error($terms) ? [] : $terms;
}

/** Nyilvános lista: jóváhagyott, legalább $min ügynökségnél szereplő kifejezések (ennyitől van saját oldaluk) */
function kompasz_public_terms($taxonomy, $min = 2, array $args = []) {
    $terms = get_terms(array_merge(['taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'], $args));
    if (is_wp_error($terms)) return [];
    return array_values(array_filter($terms, function ($t) use ($min) { return $t->count >= $min && !kompasz_term_is_pending($t); }));
}

// A nyilvános oldalon a jóváhagyásra váró kifejezés nem jelenik meg, és nincs saját oldala
add_filter('get_the_terms', function ($terms, $post_id, $taxonomy) {
    if (is_admin() || !is_array($terms) || !isset(kompasz_approval_taxonomies()[$taxonomy])) return $terms;
    return array_values(array_filter($terms, function ($t) { return !kompasz_term_is_pending($t); }));
}, 10, 3);
add_action('template_redirect', function () {
    if (is_tax(array_keys(kompasz_approval_taxonomies())) && kompasz_term_is_pending(get_queried_object())) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
    }
});

// --- Admin: Ügynökségek → Jóváhagyásra vár ---
add_action('admin_menu', function () {
    $n = count(kompasz_pending_terms());
    add_submenu_page('edit.php?post_type=ugynokseg', 'Jóváhagyásra vár', 'Jóváhagyásra vár' . ($n ? ' <span class="awaiting-mod">' . $n . '</span>' : ''),
        'manage_categories', 'kompasz-pending', 'kompasz_pending_page');
});

function kompasz_pending_page() {
    $labels = kompasz_approval_taxonomies();
    $terms = kompasz_pending_terms();
    echo '<div class="wrap"><h1>Jóváhagyásra váró értékek</h1>';
    echo '<p class="description">Cégfiókok által beírt új referencia-ügyfelek, minősítések és iparágak. Jóváhagyás után megjelennek az adatlapon és a választható listákban; elutasításkor törlődnek.</p>';
    if (isset($_GET['done'])) echo '<div class="notice notice-success"><p>Kész.</p></div>';
    if (!$terms) { echo '<p><strong>Nincs jóváhagyásra váró érték.</strong></p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Érték</th><th>Típus</th><th>Ügynökség</th><th>Beküldte</th><th></th></tr></thead><tbody>';
    foreach ($terms as $t) {
        $post = (int) get_term_meta($t->term_id, 'kompasz_pending_post', true);
        $user = get_userdata((int) get_term_meta($t->term_id, 'kompasz_pending_by', true));
        $url = function ($do) use ($t) { return wp_nonce_url(admin_url('admin-post.php?action=kompasz_pending&do=' . $do . '&term=' . $t->term_id), 'kompasz_pending_' . $t->term_id); };
        printf('<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td><td><a class="button button-primary" href="%s">Jóváhagy</a> <a class="button" href="%s" onclick="return confirm(\'Biztosan elutasítod és törlöd?\')">Elutasít</a> <a href="%s">Szerkesztés</a></td></tr>',
            esc_html($t->name), esc_html($labels[$t->taxonomy] ?? $t->taxonomy),
            $post ? '<a href="' . esc_url(get_edit_post_link($post)) . '">' . esc_html(get_the_title($post)) . '</a>' : '–',
            $user ? esc_html($user->display_name) : '–', esc_url($url('approve')), esc_url($url('reject')), esc_url(get_edit_term_link($t->term_id, $t->taxonomy)));
    }
    echo '</tbody></table></div>';
}

add_action('admin_post_kompasz_pending', function () {
    $id = (int) ($_GET['term'] ?? 0);
    if (!current_user_can('manage_categories') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'kompasz_pending_' . $id)) wp_die('Nincs jogosultság.');
    $term = get_term($id);
    if ($term && !is_wp_error($term)) {
        if (($_GET['do'] ?? '') === 'approve') {
            delete_term_meta($id, KOMPASZ_PENDING_META);
        } else {
            wp_delete_term($id, $term->taxonomy);
        }
    }
    wp_safe_redirect(admin_url('edit.php?post_type=ugynokseg&page=kompasz-pending&done=1'));
    exit;
});

// A kifejezések listájában (pl. Ügynökségek → Minősítések) is látszik, ha jóváhagyásra vár
foreach (array_keys(kompasz_approval_taxonomies()) as $tax) {
    add_filter("manage_edit-{$tax}_columns", function ($cols) { $cols['kompasz_pending'] = 'Állapot'; return $cols; });
    add_filter("manage_{$tax}_custom_column", function ($out, $col, $term_id) {
        return $col === 'kompasz_pending' ? (kompasz_term_is_pending($term_id) ? '<span style="color:#b32d2e">Jóváhagyásra vár</span>' : 'Jóváhagyva') : $out;
    }, 10, 3);
}
