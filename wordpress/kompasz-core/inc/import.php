<?php
// Adatimport az import/data.json fájlból (a repó scripts/export-wp.mjs állítja elő).
// Újrafuttatható: a meglévő elemeket slug alapján frissíti. Admin: Kompasz → Importálás. WP-CLI: wp kompasz import
if (!defined('ABSPATH')) exit;

const KOMPASZ_IMPORT_BATCH = 20;

function kompasz_import_data() {
    $file = KOMPASZ_CORE_DIR . 'import/data.json';
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

/** 1. lépés: toplisták, címkék, oldalak, beállítások */
function kompasz_import_base(array $data) {
    foreach ($data['categories'] as $c) {
        $t = term_exists($c['slug'], 'toplista') ?: wp_insert_term($c['name'], 'toplista', ['slug' => $c['slug']]);
        if (is_wp_error($t)) continue;
        $id = (int) (is_array($t) ? $t['term_id'] : $t);
        wp_update_term($id, 'toplista', ['name' => $c['name'], 'description' => $c['intro']]);
        $meta = ['kompasz_short' => $c['short'], 'kompasz_icon' => $c['icon'], 'kompasz_order' => $c['order'], 'kompasz_ai' => $c['ai'] ? '1' : '',
            'kompasz_card' => $c['desc'], 'kompasz_h1' => $c['h1'], 'kompasz_question' => $c['question'], 'kompasz_intro' => $c['intro'],
            'kompasz_seo' => wp_json_encode($c['seo'], JSON_UNESCAPED_UNICODE)];
        foreach ($meta as $k => $v) update_term_meta($id, $k, $v);
    }
    foreach ($data['tags'] as $g) {
        $t = term_exists($g['slug'], 'szolgaltatas') ?: wp_insert_term($g['name'], 'szolgaltatas', ['slug' => $g['slug']]);
        if (is_wp_error($t)) continue;
        $id = (int) (is_array($t) ? $t['term_id'] : $t);
        wp_update_term($id, 'szolgaltatas', ['name' => $g['name'], 'description' => $g['intro']]);
        $meta = ['kompasz_kw' => $g['kw'], 'kompasz_h1' => $g['h1'], 'kompasz_title' => $g['title'], 'kompasz_meta_desc' => $g['desc'],
            'kompasz_intro' => $g['intro'], 'kompasz_seo' => wp_json_encode($g['seo'], JSON_UNESCAPED_UNICODE)];
        foreach ($meta as $k => $v) update_term_meta($id, $k, $v);
    }

    // Referencia-ügyfél és minősítés oldalak
    foreach (['clients' => 'ugyfel', 'certs' => 'minosites', 'industries' => 'iparag'] as $key => $tax) {
        foreach ($data['ref_pages'][$key] ?? [] as $t) {
            $ex = get_term_by('slug', $t['slug'], $tax);
            if ($ex) wp_update_term($ex->term_id, $tax, ['name' => $t['name']]);
            else wp_insert_term($t['name'], $tax, ['slug' => $t['slug']]);
        }
    }

    // A nyelv az importfájlból (a slugok és a felület szövegei ehhez igazodnak)
    update_option('kompasz_lang', $data['site']['lang'] ?? 'hu');
    $email = '<a href="mailto:' . esc_attr($data['site']['email']) . '">' . esc_html($data['site']['email']) . '</a>';
    $pages = [
        'rolunk' => [kompasz__('Rólunk'), ''],
        'blog' => ['Blog', ''],
        'kapcsolat' => [kompasz__('Kapcsolat'), '<p>' . sprintf(kompasz__('Ügynökség vagy, és szerepelnél a listán? Kérdésed van? Írj nekünk: %s.'), $email) . '</p>'],
        'kivalasztasi-elvek' => [kompasz__('A listák kiválasztási elvei'), '<p>' . esc_html(kompasz__('A sorrend ügyfélértékeléseken, referenciákon, szakmai tapasztalaton és nyilvános cégadatokon alapul. A fizetett megjelenés nem változtat a sorrenden, és minden ilyen helyet „Hirdetés” felirattal jelölünk.')) . '</p>'],
        'aszf' => [kompasz__('Általános szerződési feltételek'), ''],
        'adatvedelem' => [kompasz__('Adatvédelmi tájékoztató'), ''],
        'cimkek' => [kompasz__('Ügynökségek szolgáltatás szerint'), ''],
        'ugyfelek' => [kompasz__('Ügynökségek referencia-ügyfél szerint'), ''],
        'minositesek' => [kompasz__('Minősített ügynökségek'), ''],
        'iparagak' => [kompasz__('Ügynökségek iparági tapasztalat szerint'), ''],
    ];
    if (kompasz_lang() !== 'hu') $pages['impresszum'] = [kompasz__('Impresszum'), ''];
    foreach ($pages as $key => [$title, $content]) {
        $slug = kompasz_slug($key);
        if (!get_page_by_path($slug)) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content]);
    }
    if ($blog = get_page_by_path(kompasz_slug('blog'))) update_option('page_for_posts', $blog->ID);
    kompasz_apply_page_contents();

    update_option('blogname', $data['site']['name']);
    update_option('blogdescription', $data['site']['tagline']);
    if (!get_option('kompasz_email')) update_option('kompasz_email', $data['site']['email']);
    update_option('kompasz_featured_pitch', get_option('kompasz_featured_pitch') ?: $data['settings']['featured_pitch']);
    update_option('kompasz_home_seo', $data['home_seo']);
    update_option('kompasz_home_tab', $data['settings']['home_tab'] ?? 'online-marketing-ugynoksegek');
    if (!get_option('permalink_structure')) update_option('permalink_structure', '/%postname%/');
    // Fejlesztés alatt a keresőrobotok kitiltva (Beállítások → Olvasás)
    if (!get_option('kompasz_imported')) update_option('blog_public', 0);
    kompasz_guess_toplist_tags();
}

/** Logó felvétele a médiatárba (egyszer, a fájlnév alapján) */
function kompasz_import_logo($file, $post_id, $name) {
    $src = KOMPASZ_CORE_DIR . 'import/logos/' . $file;
    if (!file_exists($src)) return 0;
    $md5 = md5_file($src);
    $existing = get_posts(['post_type' => 'attachment', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [['key' => 'kompasz_logo_file', 'value' => $file], ['key' => 'kompasz_logo_md5', 'value' => $md5]]]);
    if ($existing) return (int) $existing[0];
    $upload = wp_upload_bits('kompasz-' . $file, null, file_get_contents($src));
    if (!empty($upload['error'])) return 0;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    // JPG/PNG logó WebP-re (lásd inc/webp.php)
    if (function_exists('kompasz_to_webp') && ($webp = kompasz_to_webp($upload['file']))) { @unlink($upload['file']); $upload['file'] = $webp; }
    $type = wp_check_filetype($upload['file']);
    $att = wp_insert_attachment(['post_mime_type' => $type['type'] ?: 'image/png', 'post_title' => $name . ' logó', 'post_status' => 'inherit'], $upload['file'], $post_id);
    if (is_wp_error($att) || !$att) return 0;
    if (strpos((string) $type['type'], 'svg') === false) wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $upload['file']));
    update_post_meta($att, 'kompasz_logo_file', $file);
    update_post_meta($att, 'kompasz_logo_md5', $md5);
    update_post_meta($att, '_wp_attachment_image_alt', $name . ' logó');
    return (int) $att;
}

/** 2. lépés: ügynökségek kötegekben */
function kompasz_import_agencies(array $data, $offset, $limit) {
    $slice = array_slice($data['agencies'], $offset, $limit);
    foreach ($slice as $a) {
        $existing = get_page_by_path($a['slug'], OBJECT, 'ugynokseg');
        $post = ['post_type' => 'ugynokseg', 'post_status' => 'publish', 'post_title' => $a['name'], 'post_name' => $a['slug'], 'post_content' => $a['description']];
        if ($existing) {
            $post['ID'] = $existing->ID;
            // A WP adminban már szerkesztett bemutatkozást nem írjuk felül
            if (get_post_meta($existing->ID, 'kompasz_edited', true)) unset($post['post_content'], $post['post_title']);
        }
        $id = $existing ? wp_update_post($post, true) : wp_insert_post($post, true);
        if (is_wp_error($id)) continue;
        // Rendszeradatok: mindig a repó az igazság (sorrend, értékelés, belső jegyzet)
        delete_post_meta($id, 'kompasz_term_links');
        $meta = ['kompasz_rank' => $a['rank'] ?? '', 'kompasz_rating' => $a['rating'] ?? '', 'kompasz_logo_dark' => $a['logo_dark'] ? '1' : '',
            'kompasz_source_url' => $a['source_url'] ?? ''];
        // Profiladatok: csak üres mezőbe írunk, hogy a WP adminban vagy a cégfiókban szerkesztett adat megmaradjon
        $join = function ($v) { return is_array($v) ? implode("\n", $v) : (string) ($v ?? ''); };
        $profile = ['kompasz_website' => $a['website'], 'kompasz_city' => $a['city'] ?? '', 'kompasz_county' => $a['county'] ?? '',
            'kompasz_founded' => $a['founded'] ?? '', 'kompasz_team' => $a['team'] ?? '',
            'kompasz_phone' => $a['phone'] ?? '', 'kompasz_email' => $a['email'] ?? '', 'kompasz_intro' => $a['intro'] ?? '',
            'kompasz_languages' => $join($a['languages'] ?? []), 'kompasz_work_mode' => $a['work_mode'] ?? ''];
        foreach ($profile as $k => $v) { if ((string) $v !== '' && (string) get_post_meta($id, $k, true) === '') update_post_meta($id, $k, (string) $v); }
        if ($a['google']) {
            $meta += ['kompasz_google_rating' => $a['google']['rating'], 'kompasz_google_count' => $a['google']['count'],
                'kompasz_google_source' => trim(($a['google']['source'] ?? '') . ' (' . ($a['google']['checked'] ?? '') . ')')];
        }
        foreach ($meta as $k => $v) { if ($v === null) $v = ''; update_post_meta($id, $k, (string) $v); }
        // Toplisták, szolgáltatások, minősítések, iparágak, referenciák: csak új vagy még üres adatlapra (az adminban szerkesztett marad)
        if (!$existing || !kompasz_post_term_ids($id, 'toplista')) wp_set_object_terms($id, $a['categories'], 'toplista');
        if (!$existing || !kompasz_post_term_ids($id, 'szolgaltatas')) wp_set_object_terms($id, $a['tags'], 'szolgaltatas');
        foreach (['kompasz_certs' => ['certs', 'minosites'], 'kompasz_industries' => ['industries', 'iparag'], 'kompasz_references' => ['references', 'ugyfel']] as $k => [$field, $tax]) {
            if (!$existing || !kompasz_post_term_ids($id, $tax)) update_post_meta($id, $k, $join($a[$field] ?? []));
        }
        kompasz_migrate_agency($id);
        if ($a['logo']) {
            // Új logó, vagy az importált logó tartalma változott (md5): csere; kézzel beállított kiemelt képhez nem nyúl
            $cur = (int) get_post_thumbnail_id($id);
            $src = KOMPASZ_CORE_DIR . 'import/logos/' . $a['logo'];
            $md5 = is_file($src) ? md5_file($src) : '';
            $imported = $cur && get_post_meta($cur, 'kompasz_logo_file', true);
            if (!$cur || ($imported && $md5 && get_post_meta($cur, 'kompasz_logo_md5', true) !== $md5)) {
                $att = kompasz_import_logo($a['logo'], $id, $a['name']);
                if ($att && $att !== $cur) {
                    set_post_thumbnail($id, $att);
                    if ($imported) wp_delete_attachment($cur, true);
                }
            }
        }
    }
    return count($slice);
}

/** 3. lépés: beállítások, amelyek ügynökség-azonosítókra hivatkoznak */
function kompasz_import_finish(array $data) {
    $id = function ($slug) { $p = get_page_by_path($slug, OBJECT, 'ugynokseg'); return $p ? $p->ID : 0; };
    if (!get_option('kompasz_featured') && $data['settings']['featured']) update_option('kompasz_featured', $id($data['settings']['featured']));
    if (!get_option('kompasz_featured_cats')) {
        $cats = [];
        foreach ($data['settings']['featured_categories'] as $slug) { $t = get_term_by('slug', $slug, 'toplista'); if ($t) $cats[] = $t->term_id; }
        update_option('kompasz_featured_cats', $cats);
    }
    if (!get_option('kompasz_home_top')) update_option('kompasz_home_top', array_values(array_filter(array_map($id, $data['settings']['home_top']))));
    update_option('kompasz_imported', $data['version']);
    update_option('kompasz_schema', KOMPASZ_SCHEMA);
    // A nyelvfüggő URL-részek (pl. /agentur/) a következő kérésben, az új nyelvvel regisztrálva frissülnek
    update_option('kompasz_flush_rules', 1);
    flush_rewrite_rules();
}

// A WP adminban kézzel mentett bemutatkozást az újraimport nem írja felül
add_action('save_post_ugynokseg', function ($post_id) {
    if (isset($_POST['kompasz_agency_nonce'])) update_post_meta($post_id, 'kompasz_edited', '1');
}, 20);

// --- Admin oldal ---
add_action('admin_menu', function () {
    add_submenu_page('kompasz', 'Importálás', 'Importálás', 'manage_options', 'kompasz-import', 'kompasz_import_page');
});

function kompasz_import_page() {
    $data = kompasz_import_data();
    echo '<div class="wrap"><h1>Kompasz – adatimport</h1>';
    if (!$data) { echo '<p>Nem található az import/data.json fájl a bővítmény mappájában.</p></div>'; return; }
    $total = count($data['agencies']);
    $step = sanitize_key($_GET['step'] ?? '');
    // Nyers (nem HTML-escape-elt) URL, mert JavaScriptből is erre navigálunk
    $step_url = function ($args) { return add_query_arg($args + ['page' => 'kompasz-import', '_wpnonce' => wp_create_nonce('kompasz_import')], admin_url('admin.php')); };
    if ($step && check_admin_referer('kompasz_import')) {
        @set_time_limit(300);
        if ($step === 'base') {
            kompasz_import_base($data);
            $next = $step_url(['step' => 'agencies', 'offset' => 0]);
            echo '<p>Toplisták, címkék és oldalak kész. Ügynökségek importálása…</p>';
        } elseif ($step === 'agencies') {
            $offset = absint($_GET['offset'] ?? 0);
            $done = kompasz_import_agencies($data, $offset, KOMPASZ_IMPORT_BATCH);
            $offset += $done;
            printf('<p>Ügynökségek: %d / %d</p>', min($offset, $total), $total);
            $next = $offset < $total ? $step_url(['step' => 'agencies', 'offset' => $offset]) : $step_url(['step' => 'finish']);
        } elseif ($step === 'finish') {
            kompasz_import_finish($data);
            echo '<div class="notice notice-success"><p><strong>Az import kész.</strong> <a href="' . esc_url(home_url('/')) . '">Oldal megnyitása</a></p></div>';
        }
        if (!empty($next)) {
            printf('<p><a class="button button-primary" href="%s">Folytatás</a></p><script>setTimeout(function(){location.href=%s},400)</script>', esc_url($next), wp_json_encode($next));
        }
        echo '</div>';
        return;
    }
    $url = $step_url(['step' => 'base']);
    printf('<p>Az import fájl verziója: <strong>%s</strong>. Tartalma: %d toplista, %d szolgáltatás-címke, %d ügynökség.</p>', esc_html($data['version']), count($data['categories']), count($data['tags']), $total);
    echo '<p>Az import újrafuttatható: a meglévő elemeket slug alapján frissíti. A WP adminban kézzel átírt bemutatkozásokat és a beállításokat nem írja felül.</p>';
    if ($last = get_option('kompasz_imported')) printf('<p>Utolsó import: %s</p>', esc_html($last));
    printf('<p><a class="button button-primary button-hero" href="%s">Importálás indítása</a></p></div>', esc_url($url));
}

// --- WP-CLI ---
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('kompasz import', function () {
        $data = kompasz_import_data();
        if (!$data) WP_CLI::error('Nincs import/data.json');
        kompasz_import_base($data);
        $total = count($data['agencies']);
        for ($o = 0; $o < $total; $o += KOMPASZ_IMPORT_BATCH) { kompasz_import_agencies($data, $o, KOMPASZ_IMPORT_BATCH); WP_CLI::log(min($o + KOMPASZ_IMPORT_BATCH, $total) . " / $total"); }
        kompasz_import_finish($data);
        WP_CLI::success("Import kész: $total ügynökség.");
    });
}
