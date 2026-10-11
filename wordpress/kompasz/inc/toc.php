<?php
// Blogcikk tartalomjegyzék: összecsukható kártya a cikk elején, széles asztali nézetben görgetéskor oldalsó, követő tartalom
if (!defined('ABSPATH')) exit;

/** A tartalom H2/H3 címsorai azonosítóval; visszaadja a módosított HTML-t és a fejezetlistát */
function kompasz_toc_prepare($html) {
    $items = []; $used = [];
    $html = preg_replace_callback('#<h([23])([^>]*)>(.*?)</h\1>#is', function ($m) use (&$items, &$used) {
        $text = trim(wp_strip_all_tags($m[3]));
        if ($text === '') return $m[0];
        if (preg_match('/\sid=["\']([^"\']+)["\']/', $m[2], $idm)) { $id = $idm[1]; $attrs = $m[2]; }
        else {
            $base = sanitize_title($text) ?: 'fejezet'; $id = $base; $i = 2;
            while (isset($used[$id])) $id = $base . '-' . $i++;
            $attrs = $m[2] . ' id="' . esc_attr($id) . '"';
        }
        $used[$id] = true;
        $items[] = ['level' => (int) $m[1], 'id' => $id, 'text' => $text];
        return '<h' . $m[1] . $attrs . '>' . $m[3] . '</h' . $m[1] . '>';
    }, $html);
    return [$html, $items];
}

/** Összecsukható tartalomjegyzék-kártya (JS nélkül is nyitható: <details>) */
function kompasz_toc_card($items) {
    $top = array_values(array_filter($items, function ($i) { return $i['level'] === 2; }));
    if (count($top) < 2) return '';
    $list = ''; $n = 0;
    foreach ($items as $i) {
        if ($i['level'] === 2) {
            if ($n) $list .= '</ul></li>';
            $n++;
            $list .= sprintf('<li class="toc-item"><a href="#%s"><span class="toc-num">%02d</span><span>%s</span></a><ul class="toc-sub">', esc_attr($i['id']), $n, esc_html($i['text']));
        } elseif ($n) {
            $list .= sprintf('<li><a href="#%s">%s</a></li>', esc_attr($i['id']), esc_html($i['text']));
        }
    }
    if ($n) $list .= '</ul></li>';
    $list = str_replace('<ul class="toc-sub"></ul>', '', $list);
    $icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" fill="currentColor"/><circle cx="4.5" cy="12" r="1" fill="currentColor"/><circle cx="4.5" cy="18" r="1" fill="currentColor"/></svg>';
    return '<details class="toc" id="toc"><summary class="toc-head"><span class="toc-icon">' . $icon . '</span><span class="toc-ttl"><span class="toc-title">Tartalomjegyzék</span><span class="toc-meta">'
        . esc_html($n . ' fejezet') . '</span></span><span class="toc-btn"><span class="toc-open">Megnyitás</span><span class="toc-close">Bezárás</span><span class="toc-chev" aria-hidden="true"></span></span></summary>'
        . '<ol class="toc-body">' . $list . '</ol></details>';
}
