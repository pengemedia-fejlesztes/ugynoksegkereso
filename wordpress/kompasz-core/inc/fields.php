<?php
// Admin mezők: toplista- és címke-szövegek (term meta), közös mező-megjelenítés. Az ügynökség adatlapja: agency-edit.php
if (!defined('ABSPATH')) exit;

/** Toplista és címke szövegmezői */
function kompasz_term_fields($taxonomy) {
    if ($taxonomy === 'toplista') {
        return [
            'kompasz_short'    => ['Rövid név (fejléc)', 'text', 'Pl. „Google Ads”.'],
            'kompasz_icon'     => ['Ikon', 'icon', ''],
            'kompasz_order'    => ['Sorrend a fejlécben', 'number', ''],
            'kompasz_ai'       => ['AI & online marketing csoport', 'checkbox', ''],
            'kompasz_tag'      => ['Hozzá tartozó szolgáltatás', 'tagselect', 'Az ügynökség adatlapján egy sorban szerepel a szolgáltatással; aki a toplistán van, ezt a szolgáltatás-címkét is megkapja.'],
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
        case 'tagselect':
            $out = sprintf('<select id="%s" name="%s"><option value="">– nincs –</option>', $id, $id);
            foreach (get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => false, 'orderby' => 'name']) ?: [] as $t) $out .= sprintf('<option value="%d" %s>%s</option>', $t->term_id, selected((int) $value, $t->term_id, false), esc_html($t->name));
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
        case 'tagselect': return (string) absint($raw) ?: '';
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
