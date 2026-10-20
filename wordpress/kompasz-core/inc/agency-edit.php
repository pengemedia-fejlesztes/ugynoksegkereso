<?php
// Ügynökség szerkesztése: egyetlen adatlap a szerkesztő alatt (a WP alap taxonómia-dobozai helyett).
// Minden adat egy helyen: alapadatok, munkanyelvek + telefonszámok, szolgáltatások és toplisták, minősítések, iparágak, referenciák.
if (!defined('ABSPATH')) exit;

/** Egyszerű mezők: kulcs => [címke, típus, súgó] */
function kompasz_agency_fields() {
    return [
        'kompasz_website'     => ['Weboldal', 'url', 'A „Kapcsolat” gomb ide visz.'],
        'kompasz_email'       => ['E-mail cím', 'email', 'Nyilvános céges e-mail cím. Mentéskor a rendszer ellenőrzi.'],
        'kompasz_city'        => ['Város', 'suggest', 'Kezdj gépelni: a már szereplő városok közül választhatsz, vagy írj be újat.'],
        'kompasz_county'      => ['Megye', 'suggest', ''],
        'kompasz_founded'     => ['Alapítás éve', 'year', ''],
        'kompasz_team'        => ['Csapatméret', 'text', 'Pl. „15 fő” vagy „11–50 fő”.'],
        'kompasz_work_mode'   => ['Munkavégzés', 'text', 'Helyben, távolról vagy mindkettő; külföldi ügyfelekkel is.'],
        'kompasz_price_min'   => ['Árszint (minimum)', 'text', 'Pl. „havi 150 000 Ft-tól”.'],
        'kompasz_price_model' => ['Árazás módja', 'text', 'Havidíj, projektalapú vagy teljesítményalapú.'],
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

/** Megyék / tartományok listája a webhely nyelve szerint, kiegészítve az adatbázisban szereplőkkel */
function kompasz_county_options() {
    $base = kompasz_lang() === 'de'
        ? ['Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen']
        : ['Budapest', 'Bács-Kiskun', 'Baranya', 'Békés', 'Borsod-Abaúj-Zemplén', 'Csongrád-Csanád', 'Fejér', 'Győr-Moson-Sopron', 'Hajdú-Bihar', 'Heves', 'Jász-Nagykun-Szolnok', 'Komárom-Esztergom', 'Nógrád', 'Pest', 'Somogy', 'Szabolcs-Szatmár-Bereg', 'Tolna', 'Vas', 'Veszprém', 'Zala'];
    $all = array_unique(array_merge($base, kompasz_meta_values('kompasz_county')));
    sort($all, SORT_LOCALE_STRING);
    return $all;
}

/** Munkanyelvek: az alapnyelvek + minden nyelv, ami az adatbázisban bármelyik ügynökségnél szerepel */
function kompasz_language_options() {
    $base = kompasz_lang() === 'de' ? ['Deutsch', 'Englisch', 'Ungarisch'] : ['magyar', 'angol', 'német'];
    $more = [];
    foreach (kompasz_meta_values('kompasz_languages') as $v) foreach (preg_split('/\r?\n/', $v) as $l) { $l = trim($l); if ($l !== '') $more[] = $l; }
    $more = array_diff(array_unique($more), $base);
    sort($more, SORT_LOCALE_STRING);
    return array_values(array_merge($base, $more));
}

/** Toplista → hozzá tartozó szolgáltatás-címke (term meta: kompasz_tag). Pl. „Google Ads ügynökségek” → „Google Ads” */
function kompasz_toplist_tag($toplist) {
    $id = (int) get_term_meta(is_object($toplist) ? $toplist->term_id : (int) $toplist, 'kompasz_tag', true);
    return $id ?: 0;
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
    echo kompasz_field_html('kompasz_county', $m('kompasz_county'), kompasz_county_options());
    echo kompasz_field_html('kompasz_founded', $m('kompasz_founded'));
    echo kompasz_field_html('kompasz_team', $m('kompasz_team'));
    echo kompasz_field_html('kompasz_work_mode', $m('kompasz_work_mode'));
    echo kompasz_field_html('kompasz_price_min', $m('kompasz_price_min'));
    echo kompasz_field_html('kompasz_price_model', $m('kompasz_price_model'));
    echo kompasz_field_html('kompasz_google_url', $m('kompasz_google_url'));
    echo '</div>';
    echo kompasz_field_html('kompasz_intro', $m('kompasz_intro'));

    // 2. Munkanyelvek és telefonszámok (nyelvenként egy szám)
    $langs = kompasz_meta_lines($id, 'kompasz_languages');
    $phones = kompasz_agency_phones($id);
    echo '<h3>Munkanyelvek és telefonszámok</h3><p class="description">Pipáld be, milyen nyelven dolgoznak. Nyelvenként egy telefonszám adható meg, nemzetközi formában (pl. +36 20 404 4280); a rendszer formázza és ellenőrzi.</p>';
    echo '<table class="klangs" data-cc="' . esc_attr(kompasz_phone_default_cc()) . '">';
    foreach (array_unique(array_merge(kompasz_language_options(), $langs)) as $i => $lang) {
        $on = in_array($lang, $langs, true);
        printf('<tr><td><label><input type="checkbox" name="kompasz_lang_on[]" value="%1$s"%2$s> %3$s</label></td><td><input type="tel" name="kompasz_phones[%1$s]" value="%4$s" placeholder="+36 …" class="regular-text" data-kompasz-phone%5$s></td></tr>',
            esc_attr($lang), checked($on, true, false), esc_html($lang), esc_attr($phones[$lang] ?? ''), $on ? '' : ' disabled');
    }
    echo '<tr><td><input type="text" name="kompasz_lang_new" placeholder="Más nyelv…" class="regular-text"></td><td><input type="tel" name="kompasz_phone_new" placeholder="Telefonszám ehhez a nyelvhez" class="regular-text" data-kompasz-phone></td></tr>';
    echo '</table>';

    // 3. Szolgáltatások és toplisták – egy lista: a toplistához tartozó szolgáltatás egy sor
    $tag_ids = kompasz_post_term_ids($id, 'szolgaltatas');
    $top_ids = kompasz_post_term_ids($id, 'toplista');
    $tags = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => false, 'orderby' => 'name']);
    $tops_by_tag = []; $tops_free = [];
    foreach (kompasz_categories() as $c) { $t = kompasz_toplist_tag($c); if ($t) $tops_by_tag[$t][] = $c; else $tops_free[] = $c; }
    echo '<h3>Szolgáltatások és toplisták</h3><p class="description">Soronként egy szolgáltatás. A pipa a szolgáltatás-címkét állítja (ettől jelenik meg a cég a címke oldalán és a profilján)' . ($admin_cats ? '; a „Toplistán” oszlop azt, hogy rangsorolva szerepel-e az adott toplistán. Toplistára tett cég a hozzá tartozó szolgáltatást is megkapja.' : '. A toplistákat a szerkesztőség állítja.') . '</p>';
    echo '<table class="widefat striped ksvc"><thead><tr><th>Szolgáltatás</th><th>Toplistán</th></tr></thead><tbody>';
    $top_cell = function ($c) use ($top_ids, $admin_cats) {
        return sprintf('<label><input type="checkbox" name="kompasz_toplists[]" value="%d"%s%s> %s</label>', $c->term_id, checked(in_array($c->term_id, $top_ids, true), true, false), $admin_cats ? '' : ' disabled', esc_html($c->name));
    };
    if (!is_wp_error($tags)) foreach ($tags as $t) {
        $tops = $tops_by_tag[$t->term_id] ?? [];
        printf('<tr><td><label><input type="checkbox" name="kompasz_tags[]" value="%d"%s> %s</label></td><td>%s</td></tr>', $t->term_id, checked(in_array($t->term_id, $tag_ids, true), true, false), esc_html($t->name), $tops ? implode('<br>', array_map($top_cell, $tops)) : '<span class="kmuted">–</span>');
    }
    foreach ($tops_free as $c) printf('<tr><td><span class="kmuted">(általános toplista)</span></td><td>%s</td></tr>', $top_cell($c));
    echo '</tbody></table>';

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

    // Munkanyelvek és telefonszámok
    $langs = array_values(array_filter(array_map('sanitize_text_field', (array) ($in['kompasz_lang_on'] ?? []))));
    $phones_in = array_map('sanitize_text_field', (array) ($in['kompasz_phones'] ?? []));
    $new = sanitize_text_field($in['kompasz_lang_new'] ?? '');
    if ($new !== '') { $langs[] = $new; $phones_in[$new] = sanitize_text_field($in['kompasz_phone_new'] ?? ''); }
    $langs = array_values(array_unique($langs));
    $old = kompasz_agency_phones($post_id);
    $phones = [];
    foreach ($langs as $l) {
        $raw = trim($phones_in[$l] ?? '');
        if ($raw === '') continue;
        $f = kompasz_phone_format($raw);
        if ($f === '') {
            $errors[] = sprintf('A(z) %s nyelvű telefonszám (%s) hibás, nem lett mentve. Helyes forma: +36 20 404 4280 vagy +36 1 234 5678.', esc_html($l), esc_html($raw));
            if (isset($old[$l])) $phones[$l] = $old[$l];
            continue;
        }
        $phones[$l] = $f;
    }
    update_post_meta($post_id, 'kompasz_languages', implode("\n", $langs));
    update_post_meta($post_id, 'kompasz_phones', wp_json_encode($phones, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, 'kompasz_phone', $phones ? reset($phones) : '');

    // Szolgáltatások és toplisták
    $tag_ids = array_map('intval', (array) ($in['kompasz_tags'] ?? []));
    if (current_user_can('manage_categories')) {
        $top_ids = array_map('intval', (array) ($in['kompasz_toplists'] ?? []));
        wp_set_object_terms($post_id, $top_ids, 'toplista');
    } else {
        $top_ids = kompasz_post_term_ids($post_id, 'toplista');
    }
    foreach ($top_ids as $t) { $tag = kompasz_toplist_tag($t); if ($tag) $tag_ids[] = $tag; }
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

/** Telefonszámok nyelvenként (régi adatnál az egy szám az első munkanyelvhez tartozik) */
function kompasz_agency_phones($id) {
    $p = json_decode((string) get_post_meta($id, 'kompasz_phones', true), true);
    if (is_array($p)) return $p;
    $one = (string) get_post_meta($id, 'kompasz_phone', true);
    if ($one === '') return [];
    $langs = kompasz_meta_lines($id, 'kompasz_languages');
    return [$langs[0] ?? kompasz_language_options()[0] => kompasz_phone_format($one) ?: $one];
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
