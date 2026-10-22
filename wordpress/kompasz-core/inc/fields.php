<?php
// Admin mezők: toplista- és címke-szövegek (term meta), közös mező-megjelenítés. Az ügynökség adatlapja: agency-edit.php
if (!defined('ABSPATH')) exit;

/** Szolgáltatás szövegmezői: [címke, típus, súgó, csoport]. A „toplista” csoport csak akkor számít, ha van toplista oldal (URL). */
function kompasz_term_fields($taxonomy = 'szolgaltatas') {
    return [
        'kompasz_toplist_slug' => ['Toplista oldal címe (URL)', 'slug', 'Ha kitöltöd, a szolgáltatásnak saját toplista oldala lesz a gyökérben, pl. „google-ads-ugynoksegek” → /google-ads-ugynoksegek/. Üresen csak szolgáltatás-oldal van (/' . kompasz_slug('cimke') . '/…/). A toplistán minden ügynökség szerepel, akinél ez a szolgáltatás be van pipálva, a „Sorrend” szerint.', 'toplista'],
        'kompasz_toplist_name' => ['Toplista neve', 'text', 'Pl. „Google Ads ügynökségek” (fejléc, morzsamenü, csempék).', 'toplista'],
        'kompasz_short'    => ['Rövid név (fejléc)', 'text', 'Pl. „Google Ads”.', 'toplista'],
        'kompasz_icon'     => ['Ikon', 'icon', '', 'toplista'],
        'kompasz_order'    => ['Sorrend a fejlécben', 'number', 'Kisebb szám = előrébb.', 'toplista'],
        'kompasz_ai'       => ['AI & online marketing csoport', 'checkbox', '', 'toplista'],
        'kompasz_card'     => ['Rövid leírás (csempe)', 'text', '', 'toplista'],
        'kompasz_question' => ['Bevezető kérdés', 'text', 'Pl. „Mit csinál egy Google Ads ügynökség?”', 'toplista'],
        'kompasz_h1'       => ['Oldalcím (H1)', 'text', '', 'oldal'],
        'kompasz_intro'    => ['Bevezető szöveg (az oldal tetején)', 'textarea', '', 'oldal'],
        'kompasz_kw'       => ['Fókusz-kulcsszó', 'text', 'Pl. „SEO ügynökség”.', 'seo'],
        'kompasz_title'    => ['HTML title', 'text', 'Üresen: az oldalcím.', 'seo'],
        'kompasz_meta_desc'=> ['Meta leírás', 'textarea', 'Kb. 150 karakter. Üresen: a bevezető első mondata.', 'seo'],
        'kompasz_seo'      => ['SEO-blokkok (az oldal alján)', 'seo', '', 'seo'],
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
    if ($type === 'slug') $type = 'text';
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
    if ($wrap === 'div') {
        return sprintf('<div class="kf">%s%s%s</div>', $label ? '<label for="' . $id . '"><strong>' . esc_html($label) . '</strong></label>' : '', $out, $help ? '<span class="description">' . esc_html($help) . '</span>' : '');
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
        case 'slug': return sanitize_title((string) $raw);
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

// --- Szolgáltatás term meta: egy űrlap, a toplista-mezők csoportosítva ---
add_action('szolgaltatas_add_form_fields', function () {
    wp_nonce_field('kompasz_term', 'kompasz_term_nonce');
    foreach (kompasz_term_fields() as $key => $def) echo '<div class="form-field">' . kompasz_render_field($key, $def, '') . '</div>';
});
add_action('szolgaltatas_edit_form_fields', function ($term) {
    wp_nonce_field('kompasz_term', 'kompasz_term_nonce');
    $groups = ['toplista' => 'Toplista oldal', 'oldal' => 'Oldal szövegei', 'seo' => 'Keresőoptimalizálás'];
    $cur = '';
    foreach (kompasz_term_fields() as $key => $def) {
        if ($def[3] !== $cur) { $cur = $def[3]; echo '<tr><th colspan="2"><h2 style="margin:16px 0 0">' . esc_html($groups[$cur]) . '</h2></th></tr>'; }
        echo kompasz_render_field($key, $def, get_term_meta($term->term_id, $key, true), 'tr');
    }
    $n = count(get_objects_in_term($term->term_id, 'szolgaltatas'));
    $url = get_term_link($term);
    printf('<tr><th>Nyilvános oldal</th><td><a href="%s" target="_blank">%s</a> · %d ügynökség</td></tr>', esc_url($url), esc_html($url), $n);
});
$kompasz_term_save = function ($term_id) {
    if (!isset($_POST['kompasz_term_nonce']) || !wp_verify_nonce($_POST['kompasz_term_nonce'], 'kompasz_term')) return;
    if (!current_user_can('manage_categories')) return;
    foreach (kompasz_term_fields() as $key => [, $type]) {
        $v = kompasz_sanitize_field($type, wp_unslash($_POST[$key] ?? ''));
        // A toplista címe nem ütközhet másik toplistával vagy oldallal
        if ($key === 'kompasz_toplist_slug' && $v !== '') {
            $taken = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => false, 'fields' => 'ids', 'exclude' => [$term_id], 'meta_query' => [['key' => $key, 'value' => $v]]]);
            if ($taken || get_page_by_path($v)) { set_transient('kompasz_term_error', 'A toplista címe („' . $v . '”) már foglalt, nem lett mentve.', 60); continue; }
        }
        update_term_meta($term_id, $key, $v);
    }
};
add_action('created_szolgaltatas', $kompasz_term_save);
add_action('edited_szolgaltatas', $kompasz_term_save);
add_action('admin_notices', function () {
    if ($e = get_transient('kompasz_term_error')) { delete_transient('kompasz_term_error'); echo '<div class="notice notice-error"><p>' . esc_html($e) . '</p></div>'; }
});

// A szolgáltatások listájában látszik, melyiknek van toplistája
add_filter('manage_edit-szolgaltatas_columns', function ($cols) {
    $new = [];
    foreach ($cols as $k => $v) { $new[$k] = $v; if ($k === 'name') $new['kompasz_toplist'] = 'Toplista oldal'; }
    return $new;
});
add_filter('manage_szolgaltatas_custom_column', function ($out, $col, $term_id) {
    if ($col !== 'kompasz_toplist') return $out;
    $slug = kompasz_toplist_slug($term_id);
    return $slug !== '' ? sprintf('<a href="%s" target="_blank">/%s/</a> · %d.', esc_url(home_url('/' . $slug . '/')), esc_html($slug), (int) get_term_meta($term_id, 'kompasz_order', true)) : '–';
}, 10, 3);
