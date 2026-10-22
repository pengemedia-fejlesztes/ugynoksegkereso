<?php
// Közös megjelenítő elemek: ikonok, logó, értékelés, kártyák, listák
if (!defined('ABSPATH')) exit;

function kompasz_icon($key, $size = 16, $class = 'ic') {
    $paths = [
        'bot' => 'M8 9h8a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-4a3 3 0 0 1 3-3zM12 5v4M10 14h.01M14 14h.01',
        'sparkle' => 'M12 3l1.8 4.7 4.7 1.8-4.7 1.8L12 16l-1.8-4.7-4.7-1.8 4.7-1.8zM18 16l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z',
        'target' => 'M12 3a9 9 0 1 0 9 9M12 7a5 5 0 1 0 5 5M12 12l8-8M16 4h4v4',
        'megaphone' => 'M3 10v4h3l7 4V6l-7 4H3zM16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11',
        'trend' => 'M4 19h16M6 15l4-4 3 3 6-6M15 8h4v4',
        'news' => 'M5 5h11v14H6a1 1 0 0 1-1-1zM16 9h3v9a1 1 0 0 1-1 1h-2M8 9h5M8 12h5M8 15h3',
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4',
        'box' => 'M4 7l8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4M12 11v10',
        'browser' => 'M4 5h16v14H4zM4 9h16M7 7h.01M9.5 7h.01',
        'pen' => 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
        'tag' => 'M3 12V4h8l9 9-8 8zM7.5 7.5h.01',
    ];
    $d = $paths[$key] ?? $paths['tag'];
    return sprintf('<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>', esc_attr($class), $size, $size, $d);
}

function kompasz_brand_mark($size = 30) {
    return sprintf('<span class="mark" style="width:%1$dpx;height:%1$dpx"><svg width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#fff"/><path d="M15.5 8.5l-2 5-5 2 2-5z" fill="#2FBF85" stroke="#2FBF85" stroke-linejoin="round"/></svg></span>', $size, round($size * 0.6));
}

function kompasz_cat_icon($term, $size = 16) {
    return kompasz_icon(kompasz_term_meta($term, 'kompasz_icon', 'tag'), $size);
}

function kompasz_logo($a, $size, $class = '') {
    $img = $a['logo'] ? sprintf('<img src="%s" alt="%s" loading="lazy" decoding="async" width="%d" height="%d">', esc_url($a['logo']), esc_attr($a['name'] . ' ' . kompasz__('logó')), $size, $size) : '';
    return sprintf('<span class="logo %s%s" style="width:%3$dpx;height:%3$dpx"><span>%4$s</span>%5$s</span>', esc_attr($class), $a['logo'] && $a['logo_dark'] ? ' dark' : '', $size, esc_html($a['initials']), $img);
}

/** Csillagok + szám (+ Google-vélemények száma) */
function kompasz_rating_html($a, $long = false) {
    if (!$a['rating']) return '';
    $src = $a['rating_src'] === 'google' && $a['google_count']
        ? sprintf('<span class="gsrc">' . esc_html(kompasz__('Google · %d vélemény')) . '</span>', $a['google_count'])
        : ($long ? '<span class="gsrc">' . esc_html(kompasz__('a lista értékelése')) . '</span>' : '');
    return sprintf('<span class="rating"><span class="stars" aria-hidden="true">%s</span><b>%s/5</b>%s</span>', kompasz_stars($a['rating']), kompasz_num($a['rating']), $src);
}

function kompasz_meta_line($a) {
    $parts = array_filter([$a['city'], $a['team'], $a['founded'] ? kompasz__('alapítva') . ' ' . $a['founded'] : '']);
    return $parts ? '<div class="metaline">' . esc_html(implode(' · ', $parts)) . '</div>' : '';
}

function kompasz_contact_btn($a, $class = 'btn') {
    $href = function_exists('kompasz_website_href') ? kompasz_website_href($a) : $a['website'];
    if (!$href) return '';
    $direct = function_exists('kompasz_agency_has') && kompasz_agency_has($a['id'], 'website');
    return sprintf('<a class="%s" href="%s" target="_blank" rel="%s">' . esc_html(kompasz__('Kapcsolat ↗')) . '</a>', esc_attr($class), esc_url($href), $direct ? 'noopener' : 'nofollow noopener');
}

function kompasz_tag_chips($a, $max = 3, $current = null) {
    $out = '';
    foreach (array_slice($a['tags'], 0, $max) as $t) {
        $out .= $current && $current->term_id === $t->term_id
            ? sprintf('<span class="chip cur">%s</span>', esc_html($t->name))
            : sprintf('<a class="chip" href="%s">%s</a>', esc_url(get_term_link($t)), esc_html($t->name));
    }
    return $out;
}

/** „Szerepel még:” – a többi toplista, ahol a cég szerepel */
function kompasz_also_in($a, $current = null) {
    $names = [];
    foreach ($a['cats'] as $c) if (!$current || $c->term_id !== $current->term_id) $names[] = sprintf('<a href="%s">%s</a>', esc_url(get_term_link($c)), esc_html(kompasz_term_meta($c, 'kompasz_short', $c->name)));
    return $names ? '<span class="also">' . esc_html(kompasz__('Szerepel még:')) . ' ' . implode(', ', array_slice($names, 0, 3)) . '</span>' : '';
}

/** Toplista-sor (4b) */
function kompasz_row($a, $opts = []) {
    $featured = !empty($opts['featured']);
    $term = $opts['term'] ?? null;
    $hidden = !empty($opts['hidden']);
    $tags = implode(' ', wp_list_pluck($a['tags'], 'slug'));
    $long = mb_strlen($a['desc']) > 200;
    ob_start(); ?>
    <article class="row<?php echo $featured ? ' featured' : ''; ?>" data-tags="<?php echo esc_attr($tags); ?>" data-city="<?php echo esc_attr($a['city']); ?>" data-team="<?php echo esc_attr($a['team_bucket']); ?>" data-rating="<?php echo esc_attr($a['rating'] ?: 0); ?>" data-pos="<?php echo esc_attr($a['pos'] ?? 9999); ?>" data-name="<?php echo esc_attr(mb_strtolower($a['name'])); ?>"<?php echo $featured ? ' data-featured="1"' : ''; ?><?php echo $hidden ? ' hidden' : ''; ?>>
        <span class="rank"><?php echo $featured ? '★' : esc_html(str_pad($a['pos'] ?? '', 2, '0', STR_PAD_LEFT)); ?></span>
        <a href="<?php echo esc_url($a['link']); ?>" tabindex="-1" aria-hidden="true"><?php echo kompasz_logo($a, 88); ?></a>
        <div class="body">
            <div class="hd"><h3><a href="<?php echo esc_url($a['link']); ?>"><?php echo esc_html($a['name']); ?></a></h3><?php echo kompasz_rating_html($a); ?><?php if ($featured) echo '<span class="fbadge">' . esc_html(kompasz__('KIEMELT ÜGYNÖKSÉG')) . '</span>'; ?></div>
            <?php echo kompasz_meta_line($a); ?>
            <p class="desc<?php echo $long ? ' clamp' : ''; ?>"><?php echo esc_html($a['desc']); ?></p>
            <div class="foot"><?php echo kompasz_tag_chips($a, 3, $opts['tag'] ?? null); ?><?php echo kompasz_also_in($a, $term); ?></div>
        </div>
        <div class="cta"><a class="btn" href="<?php echo esc_url($a['link'] . '#kapcsolat'); ?>"><?php echo esc_html(kompasz__('Kapcsolat')); ?></a><a class="btn-line" href="<?php echo esc_url($a['link']); ?>"><?php echo esc_html(kompasz__('Profil megtekintése')); ?></a></div>
    </article>
    <?php return ob_get_clean();
}

/** Kis kártya (kezdőlap: Legjobbra értékeltek) */
function kompasz_card($a, $pos) {
    return sprintf('<a class="card" href="%s">%s<span class="pos">#%d</span><span class="nm">%s</span>%s<span class="ds">%s</span><span class="more">%s</span></a>',
        esc_url($a['link']), kompasz_logo($a, 56), $pos, esc_html($a['name']), kompasz_rating_html($a), esc_html($a['desc']), esc_html(kompasz__('Profil →')));
}

/** Kiemelt ügynökség sáv (kezdőlap, profil oldalsáv) */
function kompasz_featured_strip($f, $compact = false) {
    if (!$f) return '';
    return sprintf('<div class="fstrip%s">%s<div class="fs-body"><div class="fs-hd"><span class="fs-name">%s</span><span class="fbadge">' . esc_html(kompasz__('KIEMELT ÜGYNÖKSÉG')) . '</span></div><div class="fs-pitch">%s</div></div><div class="fs-act">%s<a class="btn-soft" href="%s">' . esc_html(kompasz__('Profil')) . '</a></div></div>',
        $compact ? ' compact' : '', kompasz_logo($f, $compact ? 44 : 52), esc_html($f['name']), esc_html($f['pitch'] ?: wp_trim_words($f['desc'], 14)), sprintf('<a class="btn sm" href="%s">' . esc_html(kompasz__('Kapcsolat')) . '</a>', esc_url($f['link'] . '#kapcsolat')), esc_url($f['link']));
}

function kompasz_seo_block($title, $rows) {
    if (!$rows) return '';
    $out = '<section class="seo"><div class="wrap"><h2>' . esc_html($title) . '</h2><div class="seo-grid">';
    foreach ($rows as $r) $out .= sprintf('<div><h3>%s</h3><p>%s</p></div>', esc_html($r['h']), esc_html($r['p']));
    return $out . '</div><button class="seo-more" type="button">' . esc_html(kompasz__('Tovább olvasom')) . '</button></div></section>';
}

function kompasz_crumbs($items) {
    $out = [];
    $last = count($items) - 1;
    foreach ($items as $i => [$name, $url]) {
        $out[] = $i === $last ? '<span>' . esc_html($name) . '</span>' : ($url ? sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($name)) : esc_html($name));
    }
    return '<nav class="crumb" aria-label="' . esc_attr(kompasz__('Morzsamenü')) . '">' . implode(' / ', $out) . '</nav>';
}

/** Kategória-csempék sora (kapcsolódó / „Nézd meg ezeket is”) */
function kompasz_cat_tiles($cats, $class = 'tiles') {
    $out = '<div class="' . esc_attr($class) . '">';
    foreach ($cats as $c) {
        $out .= sprintf('<a class="tile" href="%s"><span class="ti">%s</span><span class="tn">%s</span><span class="tc">%s</span></a>', esc_url(get_term_link($c)), kompasz_cat_icon($c, 18), esc_html(kompasz_toplist_name($c)), esc_html(sprintf(kompasz__('%d cég →'), count(kompasz_list($c)))));
    }
    return $out . '</div>';
}

/** Szűrők az adott listához: szolgáltatás, helyszín, csapatméret, értékelés (darabszámokkal) */
function kompasz_filters($list, $skip_tag = null) {
    $tags = $cities = $teams = [];
    $r4 = $r45 = 0;
    $tag_obj = [];
    foreach ($list as $a) {
        foreach ($a['tags'] as $t) { if ($skip_tag && $t->term_id === $skip_tag->term_id) continue; $tags[$t->slug] = ($tags[$t->slug] ?? 0) + 1; $tag_obj[$t->slug] = $t; }
        if ($a['city']) $cities[$a['city']] = ($cities[$a['city']] ?? 0) + 1;
        if ($a['team_bucket']) $teams[$a['team_bucket']] = ($teams[$a['team_bucket']] ?? 0) + 1;
        if ($a['rating'] >= 4) $r4++;
        if ($a['rating'] >= 4.5) $r45++;
    }
    arsort($tags); arsort($cities);
    $tags = array_slice($tags, 0, 8, true);
    $cities = array_slice(array_filter($cities, function ($n) { return $n >= 2; }), 0, 6, true);
    $order = array_map('kompasz__', ['1–10 fő', '11–50 fő', '51–200 fő', '200+ fő']);
    $teams = array_filter(array_replace(array_fill_keys($order, 0), $teams));
    $group = function ($title, $key, $items, $labels = []) {
        if (!$items) return '';
        $o = '<div class="fgroup"><div class="flabel">' . esc_html($title) . '</div>';
        foreach ($items as $val => $n) {
            $o .= sprintf('<label class="fopt"><input type="checkbox" data-filter="%s" value="%s"><span class="box"></span><span class="fl">%s</span><span class="fn">%d</span></label>', esc_attr($key), esc_attr($val), esc_html($labels[$val] ?? $val), $n);
        }
        return $o . '</div>';
    };
    $tag_labels = array_map(function ($t) { return $t->name; }, $tag_obj);
    $html = $group(kompasz__('SZOLGÁLTATÁS'), 'tag', $tags, $tag_labels) . $group(kompasz__('HELYSZÍN'), 'city', $cities) . (array_sum($teams) >= 3 ? $group(kompasz__('CSAPATMÉRET'), 'team', $teams) : '')
        . $group(kompasz__('ÉRTÉKELÉS'), 'rating', array_filter(['4' => $r4, '4.5' => $r45]), ['4' => kompasz__('4 felett'), '4.5' => kompasz__('4,5 felett')]);
    return $html;
}

/** A teljes lista-oldal törzse (toplista és címke) */
function kompasz_listing($term, $args) {
    $list = kompasz_list($term);
    $is_tag = !kompasz_is_toplist($term);
    $featured = $is_tag ? null : kompasz_is_featured_in($term);
    if ($featured) {
        $list = array_values(array_filter($list, function ($a) use ($featured) { return $a['id'] !== $featured['id']; }));
        foreach ($list as $i => &$x) { $x['pos'] = $i + 1; } unset($x);
    }
    $count = count($list) + ($featured ? 1 : 0);
    ob_start(); ?>
    <div class="wrap listing">
        <aside class="filters" aria-label="<?php echo esc_attr(kompasz__('Szűrők')); ?>">
            <div class="fhead"><span><?php echo esc_html(kompasz__('Szűrők')); ?></span><button type="button" class="fclear"><?php echo esc_html(kompasz__('Törlés')); ?></button></div>
            <div class="fsub"><?php echo esc_html($args['filter_note']); ?></div>
            <?php echo kompasz_filters($featured ? array_merge($list, [$featured]) : $list, $is_tag ? $term : null); ?>
        </aside>
        <div class="results">
            <?php if ($featured): ?><div class="adline"><?php echo esc_html(kompasz__('Hirdetés:')); ?> <a href="<?php echo esc_url($featured['link']); ?>"><?php echo esc_html($featured['name']); ?></a></div><?php endif; ?>
            <div class="rbar">
                <button type="button" class="ftoggle"><?php echo esc_html(kompasz__('Szűrők')); ?></button>
                <span class="rcount" data-total="<?php echo (int) $count; ?>"><?php echo esc_html(sprintf(kompasz__('%d ügynökség'), $count)); ?></span>
                <div class="sort" role="group" aria-label="<?php echo esc_attr(kompasz__('Rendezés')); ?>"><span><?php echo esc_html(kompasz__('Rendezés:')); ?></span><button type="button" data-sort="pos" aria-pressed="true"><?php echo esc_html(kompasz__('Helyezés')); ?></button><button type="button" data-sort="rating" aria-pressed="false"><?php echo esc_html(kompasz__('Értékelés')); ?></button><button type="button" data-sort="name" aria-pressed="false"><?php echo esc_html(kompasz__('Név')); ?></button></div>
            </div>
            <div class="rows" id="rows">
                <?php
                $i = 0;
                foreach ($list as $a) {
                    echo kompasz_row($a, ['term' => $is_tag ? null : $term, 'tag' => $is_tag ? $term : null, 'hidden' => $i >= 10]);
                    $i++;
                    if ($featured && $i === 3) echo kompasz_row($featured, ['term' => $term, 'featured' => true]);
                }
                if ($featured && $i < 3) echo kompasz_row($featured, ['term' => $term, 'featured' => true]);
                ?>
                <div class="noresult" hidden><?php echo esc_html(kompasz__('Nincs a szűrőknek megfelelő ügynökség.')); ?> <button type="button" class="fclear"><?php echo esc_html(kompasz__('Szűrők törlése')); ?></button></div>
            </div>
            <div class="loadmore"<?php echo $count > 11 ? '' : ' hidden'; ?>><div class="bar"><i></i></div><span class="lm-label"></span><button type="button" class="lm-btn"><?php echo esc_html(kompasz__('További 10 ügynökség')); ?></button></div>
        </div>
    </div>
    <?php return ob_get_clean();
}
