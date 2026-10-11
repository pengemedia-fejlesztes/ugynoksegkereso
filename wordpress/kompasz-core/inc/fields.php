<?php
// Admin mezők: ügynökség adatai (meta box), toplista- és címke-szövegek (term meta)
if (!defined('ABSPATH')) exit;

/** Ügynökség mezői: kulcs => [címke, típus, súgó] */
function kompasz_agency_fields() {
    return [
        'kompasz_website'       => ['Weboldal', 'url', 'A „Kapcsolat” gomb ide visz.'],
        'kompasz_city'          => ['Város', 'text', ''],
        'kompasz_founded'       => ['Alapítás éve', 'number', ''],
        'kompasz_team'          => ['Csapatméret', 'text', 'Pl. „15 fő” vagy „50+ fő”.'],
        'kompasz_county'        => ['Megye', 'text', ''],
        'kompasz_phone'         => ['Telefonszám', 'text', 'Nyilvános céges telefonszám, pl. +36 1 234 5678.'],
        'kompasz_email'         => ['E-mail cím', 'text', 'Nyilvános céges e-mail cím.'],
        'kompasz_intro'         => ['Rövid bevezető', 'textarea', '1–2 mondat; a profil tetején jelenik meg. A részletes bemutatkozást a fenti szerkesztőben írod.'],
        'kompasz_industries'    => ['Iparági tapasztalat', 'lines', 'Soronként egy, pl. webáruház, egészségügy, B2B.'],
        'kompasz_price_min'     => ['Árszint (minimum)', 'text', 'Pl. „havi 150 000 Ft-tól” vagy „projekt 500 000 Ft-tól”.'],
        'kompasz_price_model'   => ['Árazás módja', 'text', 'Havidíj, projektalapú vagy teljesítményalapú.'],
        'kompasz_certs'         => ['Minősítések', 'lines', 'Soronként egy, pl. Google Partner, Meta Business Partner.'],
        'kompasz_work_mode'     => ['Munkavégzés', 'text', 'Helyben, távolról vagy mindkettő; külföldi ügyfelekkel is.'],
        'kompasz_languages'     => ['Munkanyelvek', 'lines', 'Soronként egy, pl. magyar, angol.'],
        'kompasz_references'    => ['Referenciák', 'lines', 'Soronként egy ügyfél vagy esettanulmány; linkkel így: Név | https://…'],
        'kompasz_services'      => ['Szolgáltatások', 'lines', 'Soronként egy. A profiloldalon jelenik meg; a kattintható címkéket jobb oldalt, a Szolgáltatás-címkék dobozban állítod.'],
        'kompasz_rank'          => ['Sorrend a toplistákon', 'number', 'Kisebb szám = előrébb. Üresen a lista végére kerül, ábécérendben.'],
        'kompasz_rating'        => ['Értékelés (lista)', 'decimal', '0–5. Akkor jelenik meg, ha nincs Google-értékelés.'],
        'kompasz_google_rating' => ['Google-értékelés', 'decimal', '0–5, a Google Cégprofilból.'],
        'kompasz_google_count'  => ['Google-vélemények száma', 'number', ''],
        'kompasz_google_source' => ['Google-értékelés forrása', 'text', 'Honnan és mikor néztük meg (belső jegyzet).'],
        'kompasz_logo_dark'     => ['Világos logó (sötét háttéren jelenjen meg)', 'checkbox', ''],
        'kompasz_source_url'    => ['Leírás forrása', 'url', 'Belső jegyzet: melyik oldal alapján készült a bemutatkozás.'],
    ];
}

/** Toplista és címke szövegmezői */
function kompasz_term_fields($taxonomy) {
    if ($taxonomy === 'toplista') {
        return [
            'kompasz_short'    => ['Rövid név (fejléc)', 'text', 'Pl. „Google Ads”.'],
            'kompasz_icon'     => ['Ikon', 'icon', ''],
            'kompasz_order'    => ['Sorrend a fejlécben', 'number', ''],
            'kompasz_ai'       => ['AI & online marketing csoport', 'checkbox', ''],
            'kompasz_card'     => ['Rövid leírás (csempe)', 'text', ''],
            'kompasz_h1'       => ['Oldalcím (H1)', 'text', ''],
            'kompasz_question' => ['Bevezető kérdés', 'text', 'Pl. „Mit csinál egy Google Ads ügynökség?”'],
            'kompasz_intro'    => ['Bevezető szöveg (az oldal tetején)', 'textarea', ''],
            'kompasz_seo'      => ['SEO-blokkok (az oldal alján)', 'seo', ''],
        ];
    }
    return [
        'kompasz_kw'        => ['Fókusz-kulcsszó', 'text', 'Pl. „SEO ügynökség”.'],
        'kompasz_h1'        => ['Oldalcím (H1)', 'text', ''],
        'kompasz_title'     => ['HTML title', 'text', ''],
        'kompasz_meta_desc' => ['Meta leírás', 'textarea', 'Kb. 150 karakter.'],
        'kompasz_intro'     => ['Bevezető szöveg (az oldal tetején)', 'textarea', ''],
        'kompasz_seo'       => ['SEO-blokkok (az oldal alján)', 'seo', ''],
    ];
}

function kompasz_icon_names() {
    return ['bot' => 'Robot', 'sparkle' => 'Csillogás', 'target' => 'Célkereszt', 'megaphone' => 'Megafon', 'trend' => 'Grafikon',
        'news' => 'Újság', 'search' => 'Nagyító', 'box' => 'Doboz', 'browser' => 'Böngésző', 'pen' => 'Toll'];
}

function kompasz_render_field($key, $def, $value, $wrap = 'p') {
    [$label, $type, $help] = $def;
    $id = esc_attr($key);
    $out = '';
    switch ($type) {
        case 'textarea':
            $out = sprintf('<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', $id, $id, esc_textarea($value));
            break;
        case 'lines':
            $out = sprintf('<textarea id="%s" name="%s" rows="6" class="large-text">%s</textarea>', $id, $id, esc_textarea(is_array($value) ? implode("\n", $value) : $value));
            break;
        case 'checkbox':
            $out = sprintf('<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', $id, $id, checked($value, '1', false), esc_html($label));
            $label = '';
            break;
        case 'icon':
            $out = sprintf('<select id="%s" name="%s"><option value="">–</option>', $id, $id);
            foreach (kompasz_icon_names() as $k => $n) $out .= sprintf('<option value="%s" %s>%s</option>', esc_attr($k), selected($value, $k, false), esc_html($n));
            $out .= '</select>';
            break;
        case 'seo':
            $rows = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
            for ($i = 0; $i < 3; $i++) {
                $out .= sprintf('<div style="margin:0 0 10px"><input type="text" name="%1$s[%2$d][h]" value="%3$s" class="large-text" placeholder="%4$d. alcím"><textarea name="%1$s[%2$d][p]" rows="3" class="large-text" placeholder="%4$d. bekezdés">%5$s</textarea></div>',
                    $id, $i, esc_attr($rows[$i]['h'] ?? ''), $i + 1, esc_textarea($rows[$i]['p'] ?? ''));
            }
            break;
        default:
            $step = $type === 'decimal' ? ' step="0.1" min="0" max="5"' : '';
            $t = in_array($type, ['number', 'decimal'], true) ? 'number' : ($type === 'url' ? 'url' : 'text');
            $out = sprintf('<input type="%s" id="%s" name="%s" value="%s" class="%s"%s>', $t, $id, $id, esc_attr($value), $t === 'number' ? 'small-text' : 'large-text', $step);
    }
    $help = $help ? '<br><span class="description">' . esc_html($help) . '</span>' : '';
    if ($wrap === 'tr') {
        return sprintf('<tr class="form-field"><th scope="row"><label for="%s">%s</label></th><td>%s%s</td></tr>', $id, esc_html($label), $out, $help);
    }
    return sprintf('<p>%s%s%s</p>', $label ? '<label for="' . $id . '"><strong>' . esc_html($label) . '</strong></label><br>' : '', $out, $help);
}

function kompasz_sanitize_field($type, $raw) {
    switch ($type) {
        case 'url': return esc_url_raw(trim((string) $raw));
        case 'number': return $raw === '' ? '' : (string) intval($raw);
        case 'decimal': return $raw === '' ? '' : (string) max(0, min(5, round((float) str_replace(',', '.', $raw), 1)));
        case 'checkbox': return $raw ? '1' : '';
        case 'textarea': return sanitize_textarea_field($raw);
        case 'lines': return implode("\n", array_filter(array_map('sanitize_text_field', preg_split('/\r?\n/', (string) $raw))));
        case 'seo':
            $rows = [];
            foreach ((array) $raw as $r) {
                $h = sanitize_text_field($r['h'] ?? ''); $p = sanitize_textarea_field($r['p'] ?? '');
                if ($h !== '' || $p !== '') $rows[] = ['h' => $h, 'p' => $p];
            }
            return wp_json_encode($rows, JSON_UNESCAPED_UNICODE);
        default: return sanitize_text_field($raw);
    }
}

// --- Ügynökség meta box ---
add_action('add_meta_boxes', function () {
    add_meta_box('kompasz_agency', 'Ügynökség adatai', function ($post) {
        wp_nonce_field('kompasz_agency', 'kompasz_agency_nonce');
        echo '<p class="description">A bemutatkozást a fenti szerkesztőben írod. A logó a jobb oldali „Logó” dobozban állítható.</p>';
        $skip = function_exists('kompasz_is_partner') && kompasz_is_partner() ? kompasz_partner_protected_fields() : [];
        foreach (kompasz_agency_fields() as $key => $def) { if (in_array($key, $skip, true)) continue; echo kompasz_render_field($key, $def, get_post_meta($post->ID, $key, true)); }
    }, 'ugynokseg', 'normal', 'high');
});

add_action('save_post_ugynokseg', function ($post_id) {
    if (!isset($_POST['kompasz_agency_nonce']) || !wp_verify_nonce($_POST['kompasz_agency_nonce'], 'kompasz_agency')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $skip = function_exists('kompasz_is_partner') && kompasz_is_partner() ? kompasz_partner_protected_fields() : [];
    foreach (kompasz_agency_fields() as $key => [, $type]) {
        if (in_array($key, $skip, true)) continue;
        update_post_meta($post_id, $key, kompasz_sanitize_field($type, wp_unslash($_POST[$key] ?? '')));
    }
});

// --- Term meta (toplista, szolgáltatás) ---
foreach (['toplista', 'szolgaltatas'] as $tax) {
    add_action("{$tax}_add_form_fields", function () use ($tax) {
        wp_nonce_field('kompasz_term', 'kompasz_term_nonce');
        foreach (kompasz_term_fields($tax) as $key => $def) echo '<div class="form-field">' . kompasz_render_field($key, $def, '') . '</div>';
    });
    add_action("{$tax}_edit_form_fields", function ($term) use ($tax) {
        wp_nonce_field('kompasz_term', 'kompasz_term_nonce');
        foreach (kompasz_term_fields($tax) as $key => $def) echo kompasz_render_field($key, $def, get_term_meta($term->term_id, $key, true), 'tr');
    });
    $save = function ($term_id) use ($tax) {
        if (!isset($_POST['kompasz_term_nonce']) || !wp_verify_nonce($_POST['kompasz_term_nonce'], 'kompasz_term')) return;
        if (!current_user_can('manage_categories')) return;
        foreach (kompasz_term_fields($tax) as $key => [, $type]) {
            update_term_meta($term_id, $key, kompasz_sanitize_field($type, wp_unslash($_POST[$key] ?? '')));
        }
    };
    add_action("created_{$tax}", $save);
    add_action("edited_{$tax}", $save);
}
