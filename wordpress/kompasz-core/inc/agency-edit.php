<?php
// Ügynökség szerkesztése: egyetlen adatlap a szerkesztő alatt (a WP alap taxonómia-dobozai helyett).
// Minden adat egy helyen: alapadatok, helyszín (térkép), munkanyelvek + telefonszámok, szolgáltatások (= toplisták), minősítések, iparágak,
// referenciák. Minden választható lista adatbázisból jön (taxonómiák: megye, nyelv, szolgaltatas, minosites, iparag, ugyfel).
if (!defined('ABSPATH')) exit;

/** Egyszerű mezők: kulcs => [címke, típus, súgó] */
function kompasz_agency_fields() {
    return [
        'kompasz_website'     => ['Weboldal', 'url', 'A „Kapcsolat” gomb ide visz.'],
        'kompasz_email'       => ['E-mail cím', 'email', 'Nyilvános céges e-mail cím. Mentéskor a rendszer ellenőrzi.'],
        'kompasz_city'        => ['Város', 'suggest', 'Kezdj gépelni: a már szereplő városok közül választhatsz, vagy írj be újat.'],
        'kompasz_founded'     => ['Alapítás éve', 'year', ''],
        'kompasz_team'        => ['Csapatméret', 'text', 'Pl. „15 fő” vagy „11–50 fő”.'],
        'kompasz_work_mode'   => ['Munkavégzés', 'text', 'Helyben, távolról vagy mindkettő; külföldi ügyfelekkel is.'],
        'kompasz_price_min'   => ['Árszint (minimum)', 'text', 'Pl. „havi 150 000 Ft-tól”.'],
        'kompasz_price_model' => ['Árazás módja', 'text', 'Havidíj, projektalapú vagy teljesítményalapú.'],
        'kompasz_address'     => ['Cím', 'text', 'Utca, házszám (a várost fent add meg). A térképen ez alapján keresheted meg a helyet.'],
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

/** Egy meta mező összes eddigi értéke az ügynökségeknél (automatikus kiegészítéshez) */
function kompasz_meta_values($key) {
    global $wpdb;
    $rows = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = 'ugynokseg' AND p.post_status <> 'trash'", $key));
    return $rows ?: [];
}

/** Választható kifejezések egy adatbázis-listából (megye, nyelv): a gyakoribbak elöl, azonos gyakoriságnál név szerint */
function kompasz_list_terms($taxonomy, $by_count = false) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms)) return [];
    if ($by_count) usort($terms, function ($a, $b) { return [$b->count, $a->name] <=> [$a->count, $b->name]; });
    return $terms;
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
    foreach (['toplistadiv', 'tagsdiv-szolgaltatas', 'tagsdiv-ugyfel', 'tagsdiv-minosites', 'tagsdiv-iparag', 'szolgaltatasdiv', 'ugyfeldiv', 'minositesdiv', 'iparagdiv'] as $box) {
        remove_meta_box($box, 'ugynokseg', 'side');
    }
    add_meta_box('kompasz_agency', 'Adatlap', 'kompasz_agency_box', 'ugynokseg', 'normal', 'high');
    if (current_user_can('manage_options')) add_meta_box('kompasz_agency_admin', 'Sorrend, értékelés (csak adminisztrátor)', 'kompasz_agency_admin_box', 'ugynokseg', 'normal', 'default');
});

function kompasz_field_html($key, $value, $extra = []) {
    $def = kompasz_agency_fields()[$key];
    [$label, $type, $help] = $def;
    $id = esc_attr($key);
    switch ($type) {
        case 'year':
            $out = sprintf('<select id="%1$s" name="%1$s"><option value="">–</option>', $id);
            for ($y = (int) wp_date('Y'); $y >= 1950; $y--) $out .= sprintf('<option value="%d"%s>%d</option>', $y, selected((string) $value, (string) $y, false), $y);
            $out .= '</select>';
            break;
        case 'suggest':
            $out = sprintf('<input type="text" id="%1$s" name="%1$s" value="%2$s" list="%1$s_list" autocomplete="off" class="regular-text"><datalist id="%1$s_list">', $id, esc_attr($value));
            foreach ($extra as $o) $out .= '<option value="' . esc_attr($o) . '">';
            $out .= '</datalist>';
            break;
        case 'email':
            $out = sprintf('<input type="email" id="%1$s" name="%1$s" value="%2$s" class="regular-text" data-kompasz-email>', $id, esc_attr($value));
            break;
        default:
            return kompasz_render_field($key, $def, $value, 'div');
    }
    return sprintf('<div class="kf"><label for="%s"><strong>%s</strong></label>%s%s</div>', $id, esc_html($label), $out, $help ? '<span class="description">' . esc_html($help) . '</span>' : '');
}

function kompasz_agency_box($post) {
    wp_nonce_field('kompasz_agency', 'kompasz_agency_nonce');
    $id = $post->ID;
    $m = function ($k) use ($id) { return get_post_meta($id, $k, true); };
    $admin_cats = current_user_can('manage_categories');

    echo '<div class="kompasz-edit">';
    echo '<p class="description">A bemutatkozást a fenti szerkesztőben írod, a logót a jobb oldali „Logó” dobozban állítod. Minden más itt van, egy helyen.</p>';

    // 1. Alapadatok
    echo '<h3>Alapadatok</h3><div class="kgrid">';
    echo kompasz_field_html('kompasz_website', $m('kompasz_website'));
    echo kompasz_field_html('kompasz_email', $m('kompasz_email'));
    $cities = kompasz_meta_values('kompasz_city'); sort($cities, SORT_LOCALE_STRING);
    echo kompasz_field_html('kompasz_city', $m('kompasz_city'), $cities);
    $county = kompasz_post_term_ids($id, 'megye');
    $out = '<select id="kompasz_megye" name="kompasz_megye"><option value="">–</option>';
    foreach (kompasz_list_terms('megye') as $t) $out .= sprintf('<option value="%d"%s>%s</option>', $t->term_id, selected(in_array($t->term_id, $county, true), true, false), esc_html($t->name));
    $out .= '</select>';
    printf('<div class="kf"><label for="kompasz_megye"><strong>%s</strong></label>%s<span class="description">%s</span></div>', esc_html(kompasz_lang() === 'de' ? 'Bundesland' : 'Megye'), $out,
        $admin_cats ? sprintf('A lista bővíthető: <a href="%s">Ügynökségek → Megyék</a>.', esc_url(admin_url('edit-tags.php?taxonomy=megye&post_type=ugynokseg'))) : '');
    echo kompasz_field_html('kompasz_founded', $m('kompasz_founded'));
    echo kompasz_field_html('kompasz_team', $m('kompasz_team'));
    echo kompasz_field_html('kompasz_work_mode', $m('kompasz_work_mode'));
    echo kompasz_field_html('kompasz_price_min', $m('kompasz_price_min'));
    echo kompasz_field_html('kompasz_price_model', $m('kompasz_price_model'));
    echo kompasz_field_html('kompasz_google_url', $m('kompasz_google_url'));
    echo '</div>';
    echo kompasz_field_html('kompasz_intro', $m('kompasz_intro'));

    // Helyszín: cím + térkép (MapLibre GL JS + OpenFreeMap, címkeresés: Nominatim – kulcs és számlázás nélkül)
    echo '<h3>Helyszín a térképen</h3>';
    echo kompasz_field_html('kompasz_address', $m('kompasz_address'));
    echo kompasz_map_admin_html($id);

    // 2. Munkanyelvek (adatbázis-lista) és telefonszámok: nyelvenként egy szám
    $langs = kompasz_post_term_ids($id, 'nyelv');
    $phones = kompasz_agency_phone_map($id);
    echo '<h3>Munkanyelvek és telefonszámok</h3><p class="description">Pipáld be, milyen nyelven dolgoznak. Nyelvenként egy telefonszám adható meg, nemzetközi formában (pl. +36 20 404 4280); a rendszer formázza és ellenőrzi.</p>';
    echo '<table class="klangs" data-cc="' . esc_attr(kompasz_phone_default_cc()) . '">';
    foreach (kompasz_list_terms('nyelv', true) as $t) {
        $on = in_array($t->term_id, $langs, true);
        printf('<tr><td><label><input type="checkbox" name="kompasz_lang_on[]" value="%1$d"%2$s> %3$s</label></td><td><input type="tel" name="kompasz_phones[%1$d]" value="%4$s" placeholder="+36 …" class="regular-text" data-kompasz-phone%5$s></td></tr>',
            $t->term_id, checked($on, true, false), esc_html($t->name), esc_attr($phones[$t->term_id] ?? ''), $on ? '' : ' disabled');
    }
    if ($admin_cats) echo '<tr><td><input type="text" name="kompasz_lang_new" placeholder="Más nyelv…" class="regular-text"></td><td><input type="tel" name="kompasz_phone_new" placeholder="Telefonszám ehhez a nyelvhez" class="regular-text" data-kompasz-phone></td></tr>';
    echo '</table>';
    if ($admin_cats) printf('<p class="description">Az új nyelv a közös listára kerül (<a href="%s">Ügynökségek → Munkanyelvek</a>).</p>', esc_url(admin_url('edit-tags.php?taxonomy=nyelv&post_type=ugynokseg')));

    // 3. Szolgáltatások – egy lista. A toplista a szolgáltatás tulajdonsága: akinél be van pipálva, az a toplistán is szerepel.
    $tag_ids = kompasz_post_term_ids($id, 'szolgaltatas');
    $main = ''; $rest = ''; $n_rest = 0;
    foreach (get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => false, 'orderby' => 'name']) ?: [] as $t) {
        $on = in_array($t->term_id, $tag_ids, true);
        $top = kompasz_is_toplist($t);
        $html = sprintf('<label%s><input type="checkbox" name="kompasz_tags[]" value="%d"%s%s> %s%s</label>', $top ? ' class="ktop"' : '', $t->term_id, checked($on, true, false),
            $top && !$admin_cats ? ' disabled' : '', esc_html($t->name), $top ? ' <em class="kbadge" title="' . esc_attr(kompasz_toplist_name($t)) . '">toplista</em>' : '');
        if ($top || $on || $t->count >= 2) $main .= $html; else { $rest .= $html; $n_rest++; }
    }
    echo '<h3>Szolgáltatások</h3><p class="description">A pipa a szolgáltatást állítja: ettől jelenik meg a cég a szolgáltatás oldalán és a profilján. A „toplista” jelölésű szolgáltatásnak saját toplistája van – aki ezt kapja, rangsorolva szerepel rajta (sorrend: „Sorrend a toplistákon”).'
        . ($admin_cats ? sprintf(' Új szolgáltatás, toplista: <a href="%s">Ügynökségek → Szolgáltatások és toplisták</a>.', esc_url(admin_url('edit-tags.php?taxonomy=szolgaltatas&post_type=ugynokseg'))) : ' A toplistás szolgáltatásokat a szerkesztőség állítja.') . '</p>';
    echo '<div class="kchecks ksvc">' . $main . '</div>';
    if ($rest) printf('<details class="kmore"><summary>További %d szolgáltatás</summary><div class="kchecks ksvc">%s</div></details>', $n_rest, $rest);

    // 4–5. Minősítések, iparági tapasztalat: pipálható lista + új érték (jóváhagyásra)
    foreach (['minosites' => ['Minősítések', 'Új minősítés (pl. Google Partner)'], 'iparag' => ['Iparági tapasztalat', 'Új iparág (pl. egészségügy)']] as $tax => [$h, $ph]) {
        $own = kompasz_post_term_ids($id, $tax);
        // Elöl a gyakoriak (legalább két ügynökségnél) és a bejelöltek, a ritkák lenyitható blokkban
        $main = ''; $rare = ''; $n_rare = 0;
        foreach (kompasz_edit_terms($tax, $own) as $t) {
            $on = in_array($t->term_id, $own, true);
            $html = sprintf('<label><input type="checkbox" name="kompasz_terms[%s][]" value="%d"%s> %s%s</label>', $tax, $t->term_id, checked($on, true, false), esc_html($t->name), kompasz_term_is_pending($t) ? ' <em class="kpending">jóváhagyásra vár</em>' : '');
            if ($on || $t->count >= 2) $main .= $html; else { $rare .= $html; $n_rare++; }
        }
        echo '<h3>' . esc_html($h) . '</h3><div class="kchecks">' . $main . '</div>';
        if ($rare) printf('<details class="kmore"><summary>További %d érték (csak egy-egy ügynökségnél szerepel)</summary><div class="kchecks">%s</div></details>', $n_rare, $rare);
        printf('<p><input type="text" name="kompasz_new[%s]" placeholder="%s" class="regular-text"> <span class="description">Ha nincs a listában. Több érték vesszővel elválasztva. %s</span></p>', $tax, esc_attr($ph),
            current_user_can('manage_categories') ? 'Azonnal felkerül a listára.' : 'Az új értéket a szerkesztőség jóváhagyja, addig nem látszik az oldalon.');
    }

    // 6. Referencia-ügyfelek: automatikus kiegészítés, opcionális esettanulmány-link
    $refs = kompasz_agency_refs($id, true);
    $all_clients = array_filter(get_terms(['taxonomy' => 'ugyfel', 'hide_empty' => false, 'orderby' => 'name']) ?: [], function ($t) { return !kompasz_term_is_pending($t); });
    echo '<h3>Referencia-ügyfelek</h3><p class="description">Kezdd el gépelni az ügyfél nevét: a már szereplő ügyfelek közül választhatsz, vagy újat adhatsz meg (' . (current_user_can('manage_categories') ? 'azonnal felkerül' : 'a szerkesztőség jóváhagyja') . '). A link nem kötelező (esettanulmány, projekt).</p>';
    echo '<div class="krefs" data-pending-label="új – jóváhagyásra vár">';
    foreach ($refs as $i => $r) {
        printf('<div class="kref"><input type="text" name="kompasz_refs[%1$d][name]" value="%2$s" list="kompasz_clients" class="regular-text"> <input type="url" name="kompasz_refs[%1$d][url]" value="%3$s" placeholder="https://… (nem kötelező)" class="regular-text">%4$s <button type="button" class="button-link kref-del" aria-label="Törlés">✕</button></div>',
            $i, esc_attr($r['name']), esc_attr($r['url']), $r['pending'] ? ' <em class="kpending">jóváhagyásra vár</em>' : '');
    }
    echo '</div><p><button type="button" class="button kref-add">+ Referencia hozzáadása</button></p><datalist id="kompasz_clients">';
    foreach ($all_clients as $t) echo '<option value="' . esc_attr($t->name) . '">';
    echo '</datalist>';

    // 7. Kapcsolati űrlap (Contact Form 7) az adatlapon
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
        if ($type === 'year') { $y = (int) $raw; update_post_meta($post_id, $key, $y >= 1900 && $y <= (int) wp_date('Y') ? (string) $y : ''); continue; }
        if ($key === 'kompasz_google_url' && $raw !== get_post_meta($post_id, $key, true)) delete_post_meta($post_id, 'kompasz_google_place_id');
        update_post_meta($post_id, $key, kompasz_sanitize_field($type === 'suggest' ? 'text' : $type, $raw));
    }

    $admin_cats = current_user_can('manage_categories');

    // Megye (adatbázis-lista)
    if (isset($in['kompasz_megye'])) {
        $c = absint($in['kompasz_megye']);
        wp_set_object_terms($post_id, $c && term_exists($c, 'megye') ? [$c] : [], 'megye');
    }

    // Helyszín: koordináták a térképről
    if (isset($in['kompasz_lat'], $in['kompasz_lng'])) kompasz_map_save($post_id, $in['kompasz_lat'], $in['kompasz_lng']);

    // Munkanyelvek (adatbázis-lista) és telefonszámok nyelvenként
    $langs = array_values(array_filter(array_map('absint', (array) ($in['kompasz_lang_on'] ?? [])), function ($t) { return term_exists($t, 'nyelv'); }));
    $phones_in = array_map('sanitize_text_field', (array) ($in['kompasz_phones'] ?? []));
    $new = $admin_cats ? sanitize_text_field($in['kompasz_lang_new'] ?? '') : '';
    if ($new !== '') {
        $t = term_exists($new, 'nyelv') ?: wp_insert_term($new, 'nyelv');
        if (!is_wp_error($t)) { $tid = (int) (is_array($t) ? $t['term_id'] : $t); $langs[] = $tid; $phones_in[$tid] = sanitize_text_field($in['kompasz_phone_new'] ?? ''); }
    }
    $langs = array_values(array_unique($langs));
    $old = kompasz_agency_phone_map($post_id);
    $phones = [];
    foreach ($langs as $l) {
        $raw = trim($phones_in[$l] ?? '');
        if ($raw === '') continue;
        $f = kompasz_phone_format($raw);
        if ($f === '') {
            $errors[] = sprintf('A(z) %s nyelvű telefonszám (%s) hibás, nem lett mentve. Helyes forma: +36 20 404 4280 vagy +36 1 234 5678.', esc_html(get_term($l)->name), esc_html($raw));
            if (isset($old[$l])) $phones[$l] = $old[$l];
            continue;
        }
        $phones[$l] = $f;
    }
    wp_set_object_terms($post_id, $langs, 'nyelv');
    update_post_meta($post_id, 'kompasz_phones', wp_json_encode((object) $phones, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, 'kompasz_phone', $phones ? reset($phones) : '');

    // Szolgáltatások (= toplista-tagság). A cégfiók a toplistás szolgáltatásokat nem módosíthatja: azok maradnak, ahogy voltak.
    $tag_ids = array_map('intval', (array) ($in['kompasz_tags'] ?? []));
    if (!$admin_cats) {
        $tag_ids = array_filter($tag_ids, function ($t) { return !kompasz_is_toplist(get_term($t, 'szolgaltatas')); });
        foreach (kompasz_post_term_ids($post_id, 'szolgaltatas') as $t) if (kompasz_is_toplist(get_term($t, 'szolgaltatas'))) $tag_ids[] = $t;
    }
    wp_set_object_terms($post_id, array_values(array_unique($tag_ids)), 'szolgaltatas');

    // Minősítések, iparágak
    foreach (['minosites', 'iparag'] as $tax) {
        $ids = array_map('intval', (array) ($in['kompasz_terms'][$tax] ?? []));
        $names = array_filter(array_map('trim', explode(',', (string) ($in['kompasz_new'][$tax] ?? ''))));
        if ($names) $ids = array_merge($ids, kompasz_resolve_terms($tax, $names, $post_id));
        wp_set_object_terms($post_id, array_values(array_unique($ids)), $tax);
    }

    // Referencia-ügyfelek
    $ids = []; $urls = [];
    foreach ((array) ($in['kompasz_refs'] ?? []) as $r) {
        $name = sanitize_text_field($r['name'] ?? '');
        if ($name === '') continue;
        $tid = kompasz_resolve_terms('ugyfel', [$name], $post_id)[0] ?? 0;
        if (!$tid) continue;
        $ids[] = $tid;
        $u = esc_url_raw(trim($r['url'] ?? ''));
        if ($u) $urls[$tid] = $u;
    }
    wp_set_object_terms($post_id, array_values(array_unique($ids)), 'ugyfel');
    update_post_meta($post_id, 'kompasz_ref_urls', wp_json_encode($urls));

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
