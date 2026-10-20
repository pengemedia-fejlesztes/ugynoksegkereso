<?php
// Cégfiókok: az ügynökséghez hozzárendelt felhasználó csak a saját adatlapját látja és szerkeszti
if (!defined('ABSPATH')) exit;

const KOMPASZ_PARTNER_ROLE = 'ugynokseg_partner';
const KOMPASZ_OWNER_META = 'kompasz_owner_user';

/** A szerepkör létrehozása (bővítmény bekapcsolásakor és verzióváltáskor) */
function kompasz_register_partner_role() {
    if (get_role(KOMPASZ_PARTNER_ROLE)) return;
    add_role(KOMPASZ_PARTNER_ROLE, 'Ügynökség (cégfiók)', [
        'read' => true,
        'upload_files' => true,
        'edit_posts' => true,
        'edit_published_posts' => true,
    ]);
}
add_action('init', function () {
    if (get_option('kompasz_partner_role_v') !== '1') { kompasz_register_partner_role(); update_option('kompasz_partner_role_v', '1'); }
});

function kompasz_is_partner($user_id = null) {
    $u = $user_id ? get_userdata($user_id) : wp_get_current_user();
    // allcaps közvetlenül: a user_can() itt a map_meta_cap szűrőn át újra ezt hívná (végtelen ciklus)
    return $u && $u->exists() && in_array(KOMPASZ_PARTNER_ROLE, (array) $u->roles, true) && empty($u->allcaps['manage_options']);
}

/** A felhasználóhoz rendelt ügynökség(ek) azonosítói */
function kompasz_partner_agency_ids($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) return [];
    return get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1,
        'meta_key' => KOMPASZ_OWNER_META, 'meta_value' => (string) $user_id]);
}

/** Azok a mezők, amelyeket a cégfiók nem láthat és nem módosíthat (sorrend, értékelések, belső jegyzetek) */
function kompasz_partner_protected_fields() {
    return ['kompasz_rank', 'kompasz_rating', 'kompasz_google_rating', 'kompasz_google_count', 'kompasz_google_source', 'kompasz_source_url'];
}

// Jogosultság: a cégfiók csak a saját ügynökségét szerkesztheti; új ügynökséget, bejegyzést, oldalt nem hozhat létre
add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if (!kompasz_is_partner($user_id)) return $caps;
    if (in_array($cap, ['edit_post', 'delete_post', 'publish_post'], true)) {
        $post = get_post($args[0] ?? 0);
        if (!$post) return $caps;
        if ($cap === 'delete_post') return ['do_not_allow'];
        if ($post->post_type === 'attachment') return (int) $post->post_author === (int) $user_id ? ['read'] : ['do_not_allow'];
        if ($post->post_type !== 'ugynokseg' || (int) get_post_meta($post->ID, KOMPASZ_OWNER_META, true) !== (int) $user_id) return ['do_not_allow'];
        return ['read'];
    }
    // A saját adatlap mentése: a WP ellenőrzi, hogy más szerzőjű bejegyzést szerkeszthet-e (edit_others_posts) – a saját ügynökségénél igen
    if ($cap === 'edit_others_posts' && !empty($_POST['post_ID']) && get_post_type((int) $_POST['post_ID']) === 'ugynokseg'
        && (int) get_post_meta((int) $_POST['post_ID'], KOMPASZ_OWNER_META, true) === (int) $user_id) return ['read'];
    if (in_array($cap, ['delete_posts', 'delete_published_posts', 'publish_posts', 'edit_others_posts', 'manage_categories', 'edit_pages'], true)) return ['do_not_allow'];
    return $caps;
}, 10, 4);

// A toplista-hozzárendelést a cégfiók nem módosíthatja
add_filter('register_taxonomy_args', function ($args, $taxonomy) {
    if ($taxonomy === 'toplista') $args['capabilities'] = ['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'manage_categories'];
    return $args;
}, 10, 2);

// Admin felület szűkítése: csak a saját ügynökség, a profil és a médiatár
add_action('admin_menu', function () {
    if (!kompasz_is_partner()) return;
    foreach (['index.php', 'edit.php', 'edit.php?post_type=page', 'edit-comments.php', 'tools.php', 'edit.php?post_type=ugynokseg'] as $slug) remove_menu_page($slug);
    $ids = kompasz_partner_agency_ids();
    if ($ids) add_menu_page('Cégadatlapom', 'Cégadatlapom', 'read', 'post.php?post=' . $ids[0] . '&action=edit', '', 'dashicons-building', 2);
}, 999);

add_action('admin_init', function () {
    if (!kompasz_is_partner() || wp_doing_ajax()) return;
    global $pagenow;
    $allowed = ['post.php', 'profile.php', 'upload.php', 'media-new.php', 'async-upload.php', 'admin-ajax.php'];
    if (!in_array($pagenow, $allowed, true)) {
        $ids = kompasz_partner_agency_ids();
        wp_safe_redirect($ids ? admin_url('post.php?post=' . $ids[0] . '&action=edit') : admin_url('profile.php'));
        exit;
    }
});

// A médiatárban csak a saját feltöltések látszanak
add_filter('ajax_query_attachments_args', function ($q) {
    if (kompasz_is_partner()) $q['author'] = get_current_user_id();
    return $q;
});

// Belépés után egyből a saját adatlapra
add_filter('login_redirect', function ($to, $requested, $user) {
    if ($user instanceof WP_User && kompasz_is_partner($user->ID)) {
        $ids = kompasz_partner_agency_ids($user->ID);
        return $ids ? admin_url('post.php?post=' . $ids[0] . '&action=edit') : admin_url('profile.php');
    }
    return $to;
}, 10, 3);

// A cégfiók nem látja a felső admin sávban az új tartalom menüt
add_action('admin_bar_menu', function ($bar) {
    if (kompasz_is_partner()) { $bar->remove_node('new-content'); $bar->remove_node('comments'); }
}, 999);

// --- Hozzárendelés: az ügynökség szerkesztőjében (csak adminisztrátornak) ---
add_action('add_meta_boxes', function () {
    if (!current_user_can('manage_options')) return;
    add_meta_box('kompasz_owner', 'Cégfiók', function ($post) {
        wp_nonce_field('kompasz_owner', 'kompasz_owner_nonce');
        $cur = (int) get_post_meta($post->ID, KOMPASZ_OWNER_META, true);
        $users = get_users(['role' => KOMPASZ_PARTNER_ROLE, 'orderby' => 'display_name']);
        echo '<p><label for="kompasz_owner_user">Hozzárendelt felhasználó</label><br><select name="kompasz_owner_user" id="kompasz_owner_user" style="width:100%"><option value="">— nincs —</option>';
        foreach ($users as $u) printf('<option value="%d"%s>%s (%s)</option>', $u->ID, selected($cur, $u->ID, false), esc_html($u->display_name), esc_html($u->user_email));
        echo '</select></p>';
        printf('<p class="description">A felhasználó „Ügynökség (cégfiók)” szerepkörrel jön létre a <a href="%s">Felhasználók → Új hozzáadása</a> oldalon. Csak ezt az adatlapot látja és szerkeszti; a sorrendet, az értékeléseket és a toplistákat nem.</p>', esc_url(admin_url('user-new.php')));
    }, 'ugynokseg', 'side');
});

add_action('save_post_ugynokseg', function ($post_id) {
    if (!isset($_POST['kompasz_owner_nonce']) || !wp_verify_nonce($_POST['kompasz_owner_nonce'], 'kompasz_owner')) return;
    if (!current_user_can('manage_options') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    $uid = (int) ($_POST['kompasz_owner_user'] ?? 0);
    if ($uid && kompasz_is_partner($uid)) update_post_meta($post_id, KOMPASZ_OWNER_META, (string) $uid);
    else delete_post_meta($post_id, KOMPASZ_OWNER_META);
});

// Felhasználói lista: melyik céghez tartozik
add_filter('manage_users_columns', function ($cols) { $cols['kompasz_agency'] = 'Cégadatlap'; return $cols; });
add_filter('manage_users_custom_column', function ($out, $col, $user_id) {
    if ($col !== 'kompasz_agency') return $out;
    $links = array_map(function ($id) { return sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($id)), esc_html(get_the_title($id))); }, kompasz_partner_agency_ids($user_id));
    return $links ? implode(', ', $links) : '—';
}, 10, 3);
