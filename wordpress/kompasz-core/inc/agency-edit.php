<?php
// Ügynökség szerkesztése: egyetlen adatlap a szerkesztő alatt (a WP alap taxonómia-dobozai helyett).
// Minden adat egy helyen, a szerkesztő alatt (a jobb oldalon nincs lista-doboz). Minden lista adatbázisból jön (taxonómia), és
// ugyanazzal a keresős pipalistával választható ki egyesével: város, megye, munkavégzés, árazás, munkanyelv (+ telefonszám nyelvenként),
// szolgáltatás (= toplista), minősítés, iparág, referencia-ügyfél (+ nem kötelező link). Új érték: a lista alatti mezővel kerül az adatbázisba.
if (!defined('ABSPATH')) exit;

/** Egyszerű mezők: kulcs => [címke, típus, súgó] */
function kompasz_agency_fields() {
    return [
        'kompasz_website'     => ['Weboldal', 'url', 'A „Kapcsolat” gomb ide visz.'],
        'kompasz_email'       => ['E-mail cím', 'email', 'Nyilvános céges e-mail cím. Mentéskor a rendszer ellenőrzi.'],
        'kompasz_founded'     => ['Alapítás éve', 'year', ''],
        'kompasz_team'        => ['Csapatméret (fő)', 'team', 'Pipáld be a „+” jelet, ha ennél többen vannak (pl. 20+ fő).'],
        'kompasz_price_min'   => ['Árszint (minimum)', 'text', 'Az ügynökség saját adata, pl. „havi 150 000 Ft-tól”.'],
        'kompasz_address'     => ['Cím', 'text', 'Utca, házszám (a várost a listából választod). A térképen ez alapján keresheted meg a helyet.'],
        'kompasz_google_url'  => ['Google Cégprofil link', 'url', 'A Google Térképen a cég oldala → Megosztás → link másolása. Az értékelés és a vélemények száma ebből hetente automatikusan frissül.'],
        'kompasz_intro'       => ['Rövid bevezető', 'textarea', '1–2 mondat; a profil tetején jelenik meg. A részletes bemutatkozást a fenti szerkesztőben írod.'],
        // Csak adminisztrátornak (cégfiók nem látja): kompasz_partner_protected_fields()
        'kompasz_rank'        => ['Sorrend a toplistákon', 'number', 'Kisebb szám = előrébb. Üresen a lista végére kerül, ábécérendben.'],
        'kompasz_rating'      => ['Értékelés (saját)', 'decimal', '0–5. Csak akkor jelenik meg, ha nincs Google-értékelés.'],
        'kompasz_logo_dark'   => ['Világos logó (sötét háttéren jelenjen meg)', 'checkbox', ''],
        'kompasz_source_url'  => ['Leírás forrása', 'url', 'Belső jegyzet: melyik oldal alapján készült a bemutatkozás.'],
    ];
}

function kompasz_agency_admin_fields() {
    return ['kompasz_rank', 'kompasz_rating', 'kompasz_logo_dark', 'kompasz_source_url'];
}

/** Jóváhagyott kifejezések + az ügynökség saját, még jóváhagyásra váró kifejezései */
function kompasz_edit_terms($taxonomy, array $own_ids) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms)) return [];
    return array_values(array_filter($terms, function ($t) use ($own_ids) { return !kompasz_term_is_pending($t) || in_array($t->term_id, $own_ids, true); }));
}

function kompasz_post_term_ids($post_id, $taxonomy) {
    $ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
    return is_wp_error($ids) ? [] : array_map('intval', $ids);
}

// --- Meta dobozok ---
add_action('add_meta_boxes_ugynokseg', function () {
    // A WP alap taxonómia-dobozai helyett minden az adatlapon szerkeszthető
    foreach (get_object_taxonomies('ugynokseg') as $tax) {
        foreach (["{$tax}div", "tagsdiv-{$tax}"] as $box) remove_meta_box($box, 'ugynokseg', 'side');
    }
    add_meta_box('kompasz_agency', 'Adatlap', 'kompasz_agency_box', 'ugynokseg', 'normal', 'high');
    if (current_user_can('manage_options')) add_meta_box('kompasz_agency_admin', 'Sorrend, értékelés (csak adminisztrátor)', 'kompasz_agency_admin_box', 'ugynokseg', 'normal', 'default');
});

// A Rank Math doboza az adatlap alá kerüljön
add_filter('rank_math/metabox/priority', function () { return 'low'; });

function kompasz_field_html($key, $value) {
    $def = kompasz_agency_fields()[$key];
    [$label, $type, $help] = $def;
    $id = esc_attr($key);
    switch ($type) {
        case 'year':
            $out = sprintf('<select id="%1$s" name="%1$s"><option value="">–</option>', $id);
            for ($y = (int) wp_date('Y'); $y >= 1950; $y--) $out .= sprintf('<option value="%d"%s>%d</option>', $y, selected((string) $value, (string) $y, false), $y);
            $out .= '</select>';
            break;
        case 'team':
            $plus = substr((string) $value, -1) === '+';
            $n = (int) $value;
            $out = sprintf('<select id="%1$s" name="%1$s"><option value="">–</option>', $id);
            foreach (array_merge(range(1, 100), [120, 150, 200, 250, 300, 400, 500, 750, 1000]) as $v) $out .= sprintf('<option value="%1$d"%2$s>%1$d</option>', $v, selected($n, $v, false));
            $out .= sprintf('</select> fő <label style="margin-left:10px"><input type="checkbox" name="kompasz_team_plus" value="1"%s> + (ennél több)</label>', checked($plus, true, false));
            break;
        case 'email':
            $out = sprintf('<input type="email" id="%1$s" name="%1$s" value="%2$s" class="regular-text" data-kompasz-email>', $id, esc_attr($value));
            break;
        default:
            return kompasz_render_field($key, $def, $value, 'div');
    }
    return sprintf('<div class="kf"><label for="%s"><strong>%s</strong></label>%s%s</div>', $id, esc_html($label), $out, $help ? '<span class="description">' . esc_html($help) . '</span>' : '');
}

/**
 * Adatbázis-listák az adatlapon: taxonómia => [cím, egy/több, súgó]. Az új érték felvétele: adminisztrátor azonnal; cégfiók csak a
 * jóváhagyásos listákba (minősítés, iparág, ügyfél – a szerkesztőség jóváhagyja).
 */
function kompasz_edit_lists() {
    $de = kompasz_lang() === 'de';
    return [
        'megye'        => [$de ? 'Bundesland' : 'Megye', 'single', 'Először a megyét válaszd: a városlista ehhez igazodik.'],
        'varos'        => [$de ? 'Stadt' : 'Város', 'single', ''],
        'munkavegzes'  => ['Munkavégzés', 'multi', 'Hol és hogyan dolgoznak.'],
        'arazas'       => ['Árazás módja', 'multi', ''],
        'nyelv'        => ['Munkanyelvek és telefonszámok', 'multi', 'Nyelvenként egy telefonszám, nemzetközi formában (pl. +36 20 404 4280); a rendszer formázza és ellenőrzi.'],
        'szolgaltatas' => ['Szolgáltatások', 'multi', 'A „toplista” jelölésű szolgáltatásnak saját toplistája van: aki megkapja, rangsorolva szerepel rajta (sorrend: „Sorrend a toplistákon”).'],
        'minosites'    => ['Minősítések', 'multi', ''],
        'iparag'       => ['Iparági tapasztalat', 'multi', ''],
        'ugyfel'       => ['Referencia-ügyfelek', 'multi', 'Pipáld ki az ügyfeleket; a kipipált ügyfél mellé esettanulmány- vagy projektlink írható (nem kötelező).'],
    ];
}

/** Ki vehet fel új értéket az adott listába az adatlapról (adminisztrátor: azonnal; cégfiók: jóváhagyásra) */
function kompasz_can_add_term($taxonomy) {
    return current_user_can('manage_categories') || isset(kompasz_approval_taxonomies()[$taxonomy]);
}

/** Az adatlap kereső mezőjéből felvett új értékek: [taxonómia => nevek] (egyválasztósnál „new:Név” érték) */
function kompasz_posted_new_terms(array $in, $taxonomy, $single) {
    if ($single) {
        $v = (string) ($in['kompasz_one'][$taxonomy] ?? '');
        return strpos($v, 'new:') === 0 ? [sanitize_text_field(substr($v, 4))] : [];
    }
    return array_values(array_filter(array_map('sanitize_text_field', (array) ($in['kompasz_newv'][$taxonomy] ?? []))));
}

/**
 * Keresős pipalista egy adatbázis-listához (egy- vagy többválasztós). A bejelöltek elöl, utána név szerint.
 * $extra($term, $on): a sor végére kerülő mező (telefonszám, link); $lock($term): a cégfiók nem módosíthatja.
 */
function kompasz_picker($taxonomy, $name, array $selected, $extra = null, $lock = null) {
    [$label, $mode, $help] = kompasz_edit_lists()[$taxonomy];
    $single = $mode === 'single';
    $terms = kompasz_edit_terms($taxonomy, $selected);
    usort($terms, function ($a, $b) use ($selected) {
        return [!in_array($a->term_id, $selected, true), mb_strtolower($a->name)] <=> [!in_array($b->term_id, $selected, true), mb_strtolower($b->name)];
    });
    $rows = $single ? sprintf('<label class="kpick-row"><input type="radio" name="%s" value="0"%s> <span class="kpick-name">– nincs megadva –</span></label>', esc_attr($name), checked(!$selected, true, false)) : '';
    foreach ($terms as $t) {
        $on = in_array($t->term_id, $selected, true);
        $locked = $lock && $lock($t);
        $badge = $taxonomy === 'szolgaltatas' && kompasz_is_toplist($t) ? ' <em class="kbadge" title="' . esc_attr(kompasz_toplist_name($t)) . '">toplista</em>' : '';
        if (kompasz_term_is_pending($t)) $badge .= ' <em class="kpending">jóváhagyásra vár</em>';
        $parent = $taxonomy === 'varos' ? (int) get_term_meta($t->term_id, 'kompasz_megye', true) : 0;
        $rows .= sprintf('<div class="kpick-row%s"%s><label><input type="%s" name="%s" value="%d"%s%s> <span class="kpick-name">%s</span>%s</label>%s</div>',
            $on ? ' on' : '', $parent ? ' data-megye="' . $parent . '"' : '', $single ? 'radio' : 'checkbox', esc_attr($name), $t->term_id, checked($on, true, false), $locked ? ' disabled' : '',
            esc_html($t->name), $badge, $extra ? $extra($t, $on) : '');
    }
    $manage = current_user_can('manage_categories') ? sprintf(' <a href="%s">Lista szerkesztése</a>', esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomy . '&post_type=ugynokseg'))) : '';
    // Új érték: a keresőbe írva, ha nincs a listában, megjelenik a „hozzáadás” gomb (a szkript a listába teszi bejelölve; mentéskor kerül az adatbázisba)
    $add = kompasz_can_add_term($taxonomy) ? sprintf(' data-add-name="%s" data-add-note="%s"', esc_attr($single ? $name : 'kompasz_newv[' . $taxonomy . '][]'),
        esc_attr(current_user_can('manage_categories') ? 'új – mentéskor kerül a listára' : 'új – a szerkesztőség jóváhagyja')) : '';
    $out = sprintf('<div class="kpick%s" data-tax="%s"%s><div class="kpick-head"><strong>%s</strong><span class="kpick-count"></span>%s</div>', $single ? ' single' : '', esc_attr($taxonomy), $add, esc_html($label), $manage);
    if ($help) $out .= '<p class="description">' . esc_html($help) . '</p>';
    $out .= sprintf('<input type="search" class="kpick-q" placeholder="Keresés vagy új érték (%d van a listában)…" autocomplete="off">', count($terms));
    $out .= '<button type="button" class="button kpick-add" hidden></button>';
    $out .= '<div class="kpick-list">' . $rows . '<p class="kpick-none" hidden>Nincs találat.</p></div>';
    return $out . '</div>';
}

function kompasz_agency_box($post) {
    wp_nonce_field('kompasz_agency', 'kompasz_agency_nonce');
    $id = $post->ID;
    $m = function ($k) use ($id) { return get_post_meta($id, $k, true); };
    $admin_cats = current_user_can('manage_categories');
    $sel = function ($tax) use ($id) { return kompasz_post_term_ids($id, $tax); };

    echo '<div class="kompasz-edit">';
    echo '<p class="description">A bemutatkozást a fenti szerkesztőben írod, a logót a jobb oldali „Logó” dobozban állítod. Minden más itt van, egy helyen; a listák az adatbázisból jönnek, egyesével pipálhatók.</p>';

    // 1. Alapadatok: az ügynökség saját értékei (nem listák)
    echo '<h3>Alapadatok</h3><div class="kgrid">';
    foreach (['kompasz_website', 'kompasz_email', 'kompasz_founded', 'kompasz_team', 'kompasz_price_min', 'kompasz_google_url'] as $k) echo kompasz_field_html($k, $m($k));
    echo '</div>';
    echo kompasz_field_html('kompasz_intro', $m('kompasz_intro'));

    // 2. Helyszín: megye → város (a megye városai) → utca, házszám → térkép (MapLibre GL JS + OpenFreeMap, címkeresés: Nominatim)
    echo '<h3>Helyszín</h3><div class="kgrid2">';
    echo kompasz_picker('megye', 'kompasz_one[megye]', $sel('megye'));
    echo kompasz_picker('varos', 'kompasz_one[varos]', $sel('varos'));
    echo '</div>';
    echo kompasz_field_html('kompasz_address', $m('kompasz_address'));
    echo kompasz_map_admin_html($id);

    // 3. Munkavégzés, árazás
    echo '<h3>Munkavégzés és árazás</h3><div class="kgrid2">';
    echo kompasz_picker('munkavegzes', 'kompasz_terms[munkavegzes][]', $sel('munkavegzes'));
    echo kompasz_picker('arazas', 'kompasz_terms[arazas][]', $sel('arazas'));
    echo '</div>';

    // 4. Munkanyelvek + telefonszám nyelvenként
    $phones = kompasz_agency_phone_map($id);
    echo '<h3>Munkanyelvek</h3><div class="klangs" data-cc="' . esc_attr(kompasz_phone_default_cc()) . '">';
    echo kompasz_picker('nyelv', 'kompasz_terms[nyelv][]', $sel('nyelv'), function ($t, $on) use ($phones) {
        return sprintf('<input type="tel" name="kompasz_phones[%d]" value="%s" placeholder="Telefonszám ezen a nyelven" class="kpick-extra" data-kompasz-phone%s>', $t->term_id, esc_attr($phones[$t->term_id] ?? ''), $on ? '' : ' disabled');
    });
    echo '</div>';

    // 5. Szolgáltatások (= toplisták). A cégfiók a toplistás szolgáltatásokat nem módosíthatja.
    echo '<h3>Szolgáltatások és toplisták</h3>';
    echo kompasz_picker('szolgaltatas', 'kompasz_terms[szolgaltatas][]', $sel('szolgaltatas'), null, $admin_cats ? null : 'kompasz_is_toplist');

    // 6–7. Minősítések, iparágak
    echo '<h3>Minősítések és iparági tapasztalat</h3><div class="kgrid2">';
    echo kompasz_picker('minosites', 'kompasz_terms[minosites][]', $sel('minosites'));
    echo kompasz_picker('iparag', 'kompasz_terms[iparag][]', $sel('iparag'));
    echo '</div>';

    // 8. Referencia-ügyfelek: kiválasztás a listából + nem kötelező link
    $urls = json_decode((string) $m('kompasz_ref_urls'), true) ?: [];
    echo '<h3>Referencia-ügyfelek</h3>';
    echo kompasz_picker('ugyfel', 'kompasz_terms[ugyfel][]', $sel('ugyfel'), function ($t, $on) use ($urls) {
        return sprintf('<input type="url" name="kompasz_ref_url[%d]" value="%s" placeholder="https://… esettanulmány (nem kötelező)" class="kpick-extra"%s>', $t->term_id, esc_attr($urls[$t->term_id] ?? ''), $on ? '' : ' disabled');
    });

    // 9. Kapcsolati űrlap (Contact Form 7) az adatlapon
    if (function_exists('kompasz_cf7_forms')) {
        $forms = kompasz_cf7_forms();
        echo '<h3>Ajánlatkérő űrlap az adatlapon</h3>';
        if ($forms === null) {
            echo '<p class="description">A Contact Form 7 bővítmény nincs bekapcsolva. Bekapcsolás után itt választható az űrlap; alapértelmezésként a rendszer létrehoz egyet.</p>';
        } else {
            $cur = $m('kompasz_cf7');
            echo '<p><select name="kompasz_cf7"><option value="">Alapértelmezett (Kompasz → Beállítások)</option><option value="none"' . selected($cur, 'none', false) . '>Ne legyen űrlap</option>';
            foreach ($forms as $fid => $title) printf('<option value="%d"%s>%s</option>', $fid, selected((string) $cur, (string) $fid, false), esc_html($title));
            echo '</select> <span class="description">Az ajánlatkérés a webhely címére megy; előfizető ügynökségnél közvetlenül a cég e-mail címére.</span></p>';
        }
    }
    echo '</div>';
}

function kompasz_agency_admin_box($post) {
    $id = $post->ID;
    echo '<div class="kompasz-edit"><div class="kgrid">';
    foreach (kompasz_agency_admin_fields() as $k) echo kompasz_field_html($k, get_post_meta($id, $k, true));
    echo '</div>';
    if (function_exists('kompasz_google_status_html')) echo kompasz_google_status_html($id);
    echo '</div>';
}

// --- Mentés ---
add_action('save_post_ugynokseg', function ($post_id) {
    if (!isset($_POST['kompasz_agency_nonce']) || !wp_verify_nonce($_POST['kompasz_agency_nonce'], 'kompasz_agency')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $in = wp_unslash($_POST);
    $errors = [];
    $skip = function_exists('kompasz_is_partner') && kompasz_is_partner() ? kompasz_partner_protected_fields() : [];
    if (!current_user_can('manage_options')) $skip = array_merge($skip, kompasz_agency_admin_fields());

    foreach (kompasz_agency_fields() as $key => [$label, $type]) {
        if (in_array($key, $skip, true) || !array_key_exists($key, $in) && $type !== 'checkbox') continue;
        $raw = $in[$key] ?? '';
        if ($type === 'email') {
            $v = sanitize_email(trim($raw));
            if (trim($raw) !== '' && ($why = kompasz_email_check(trim($raw)))) {
                $errors[] = sprintf('Az e-mail cím (%s) nem lett mentve: %s.', esc_html(trim($raw)), $why === 'domain' ? 'a domainnek nincs levelezőszervere' : 'hibás formátum');
                continue;
            }
            update_post_meta($post_id, $key, $v);
            continue;
        }
        if ($type === 'team') { $n = absint($raw); update_post_meta($post_id, $key, $n ? $n . (!empty($in['kompasz_team_plus']) ? '+' : '') : ''); continue; }
        if ($type === 'year') { $y = (int) $raw; update_post_meta($post_id, $key, $y >= 1900 && $y <= (int) wp_date('Y') ? (string) $y : ''); continue; }
        if ($key === 'kompasz_google_url' && $raw !== get_post_meta($post_id, $key, true)) delete_post_meta($post_id, 'kompasz_google_place_id');
        update_post_meta($post_id, $key, kompasz_sanitize_field($type, $raw));
    }

    $admin_cats = current_user_can('manage_categories');

    // Helyszín: koordináták a térképről
    if (isset($in['kompasz_lat'], $in['kompasz_lng'])) kompasz_map_save($post_id, $in['kompasz_lat'], $in['kompasz_lng']);

    // Adatbázis-listák: a bejelölt kifejezések + az új érték(ek) („Új érték a listába”)
    foreach (kompasz_edit_lists() as $tax => [$label, $mode]) {
        $ids = $mode === 'single' ? [absint($in['kompasz_one'][$tax] ?? 0)] : array_map('absint', (array) ($in['kompasz_terms'][$tax] ?? []));
        $ids = array_values(array_filter($ids, function ($t) use ($tax) { return $t && term_exists($t, $tax); }));
        $names = kompasz_can_add_term($tax) ? kompasz_posted_new_terms($in, $tax, $mode === 'single') : [];
        if ($names) {
            $added = kompasz_resolve_terms($tax, $names, $post_id, null, true);
            $ids = $mode === 'single' ? (array_slice($added, 0, 1) ?: $ids) : array_merge($ids, $added);
            // Új város: a kiválasztott megyéhez tartozik
            if ($tax === 'varos' && ($m = kompasz_post_term_ids($post_id, 'megye'))) foreach ($added as $v) if (!get_term_meta($v, 'kompasz_megye', true)) update_term_meta($v, 'kompasz_megye', (string) $m[0]);
        }
        // A cégfiók a toplistás szolgáltatásokat nem módosíthatja: azok maradnak, ahogy voltak
        if ($tax === 'szolgaltatas' && !$admin_cats) {
            $ids = array_filter($ids, function ($t) { return !kompasz_is_toplist(get_term($t, 'szolgaltatas')); });
            foreach (kompasz_post_term_ids($post_id, 'szolgaltatas') as $t) if (kompasz_is_toplist(get_term($t, 'szolgaltatas'))) $ids[] = $t;
        }
        wp_set_object_terms($post_id, array_values(array_unique(array_map('intval', $ids))), $tax);
    }

    // Telefonszám nyelvenként (csak a bejelölt munkanyelvekhez)
    $old = kompasz_agency_phone_map($post_id);
    $phones = [];
    foreach (kompasz_post_term_ids($post_id, 'nyelv') as $l) {
        $raw = trim(sanitize_text_field($in['kompasz_phones'][$l] ?? ''));
        if ($raw === '') continue;
        $f = kompasz_phone_format($raw);
        if ($f === '') {
            $errors[] = sprintf('A(z) %s nyelvű telefonszám (%s) hibás, nem lett mentve. Helyes forma: +36 20 404 4280 vagy +36 1 234 5678.', esc_html(get_term($l)->name), esc_html($raw));
            if (isset($old[$l])) $phones[$l] = $old[$l];
            continue;
        }
        $phones[$l] = $f;
    }
    update_post_meta($post_id, 'kompasz_phones', wp_json_encode((object) $phones, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, 'kompasz_phone', $phones ? reset($phones) : '');

    // Referencia-linkek (csak a bejelölt ügyfelekhez)
    $urls = [];
    foreach (kompasz_post_term_ids($post_id, 'ugyfel') as $tid) {
        $u = esc_url_raw(trim((string) ($in['kompasz_ref_url'][$tid] ?? '')));
        if ($u) $urls[$tid] = $u;
    }
    update_post_meta($post_id, 'kompasz_ref_urls', wp_json_encode((object) $urls));

    if (isset($in['kompasz_cf7'])) update_post_meta($post_id, 'kompasz_cf7', $in['kompasz_cf7'] === 'none' ? 'none' : (absint($in['kompasz_cf7']) ? (string) absint($in['kompasz_cf7']) : ''));

    if ($errors) set_transient('kompasz_errors_' . get_current_user_id(), $errors, 120);
});

add_action('admin_notices', function () {
    $s = get_current_screen();
    if (!$s || $s->post_type !== 'ugynokseg') return;
    $errors = get_transient('kompasz_errors_' . get_current_user_id());
    if (!$errors) return;
    delete_transient('kompasz_errors_' . get_current_user_id());
    echo '<div class="notice notice-error"><p><strong>Néhány adat hibás, ezeket nem mentettük:</strong></p><ul style="list-style:disc;margin-left:20px">';
    foreach ($errors as $e) echo '<li>' . $e . '</li>';
    echo '</ul></div>';
});

add_action('admin_enqueue_scripts', function ($hook) {
    $s = get_current_screen();
    if (!in_array($hook, ['post.php', 'post-new.php'], true) || !$s || $s->post_type !== 'ugynokseg') return;
    $v = filemtime(KOMPASZ_CORE_DIR . 'assets/admin.js');
    wp_enqueue_style('kompasz-admin', KOMPASZ_CORE_URL . 'assets/admin.css', [], $v);
    wp_enqueue_script('kompasz-admin', KOMPASZ_CORE_URL . 'assets/admin.js', [], $v, true);
});

/** Telefonszámok nyelvenként: munkanyelv-kifejezés azonosítója => szám (a 4. migráció előtti, nyelvnévvel kulcsolt adatot is olvassa) */
function kompasz_agency_phone_map($id) {
    $p = json_decode((string) get_post_meta($id, 'kompasz_phones', true), true);
    if (!is_array($p)) {
        $one = (string) get_post_meta($id, 'kompasz_phone', true);
        $first = kompasz_post_term_ids($id, 'nyelv')[0] ?? 0;
        return $one !== '' && $first ? [$first => kompasz_phone_format($one) ?: $one] : [];
    }
    $out = [];
    foreach ($p as $k => $v) {
        if (is_int($k) || ctype_digit((string) $k)) { $out[(int) $k] = $v; continue; }
        $t = get_term_by('name', $k, 'nyelv');
        if ($t) $out[(int) $t->term_id] = $v;
    }
    return $out;
}

/** Telefonszámok megjelenítéshez: nyelv neve => szám */
function kompasz_agency_phones($id) {
    $out = [];
    foreach (kompasz_agency_phone_map($id) as $tid => $ph) {
        $t = get_term($tid, 'nyelv');
        if ($t && !is_wp_error($t)) $out[$t->name] = $ph;
    }
    return $out;
}

/** Referenciák: [name, url, link (ügyféloldal, ha több ügynökség is megnevezi), pending] */
function kompasz_agency_refs($id, $with_pending = false) {
    $terms = wp_get_object_terms($id, 'ugyfel', ['orderby' => 'term_order']);
    if (is_wp_error($terms)) return [];
    $urls = json_decode((string) get_post_meta($id, 'kompasz_ref_urls', true), true) ?: [];
    $out = [];
    foreach ($terms as $t) {
        $pending = kompasz_term_is_pending($t);
        if ($pending && !$with_pending) continue;
        $out[] = ['name' => $t->name, 'url' => $urls[$t->term_id] ?? '', 'term' => $t, 'pending' => $pending];
    }
    return $out;
}
