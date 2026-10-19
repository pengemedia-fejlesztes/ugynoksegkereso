<?php
// Kompasz téma
if (!defined('ABSPATH')) exit;

define('KOMPASZ_THEME_VERSION', '1.0.0');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
});

add_action('wp_enqueue_scripts', function () {
    $v = KOMPASZ_THEME_VERSION . '.' . filemtime(get_theme_file_path('assets/kompasz.css'));
    wp_enqueue_style('kompasz', get_theme_file_uri('assets/kompasz.css'), [], $v);
    wp_enqueue_script('kompasz', get_theme_file_uri('assets/kompasz.js'), [], $v, ['strategy' => 'defer', 'in_footer' => true]);
    // A szkript szövegei az aktuális nyelven
    wp_localize_script('kompasz', 'KOMPASZ_I18N', [
        'more' => kompasz__('Tovább olvasom'), 'less' => kompasz__('Kevesebb'), 'agencies' => kompasz__('%d ügynökség'),
        'shown' => kompasz__('%1$d / %2$d megjelenítve'), 'loadMore' => kompasz__('További %d ügynökség'), 'toc' => kompasz__('Tartalomjegyzék'),
    ]);
    // Felesleges blokk-stílusok kikapcsolása a gyorsaságért (a téma nem használ blokkszerkesztős elrendezést a nyilvános oldalon)
    if (!is_singular('post') && !is_page()) { wp_dequeue_style('wp-block-library'); wp_dequeue_style('global-styles'); wp_dequeue_style('classic-theme-styles'); }
});

add_action('wp_head', function () {
    // Betűtípusok a saját tárhelyről (latin + latin-ext): előtöltés + beágyazott @font-face, külső kérés nélkül (gyorsaság)
    $fonts = get_theme_file_uri('assets/fonts');
    foreach (['bricolage-grotesque-latin', 'geist-latin'] as $f) echo '<link rel="preload" href="' . esc_url("$fonts/$f.woff2") . '" as="font" type="font/woff2" crossorigin>' . "\n";
    $css = @file_get_contents(get_theme_file_path('assets/fonts/fonts.css'));
    if ($css) echo '<style id="kompasz-fonts">' . str_replace('{FONTS}', esc_url($fonts), $css) . '</style>' . "\n";
    echo '<link rel="icon" href="' . esc_url(get_theme_file_uri('assets/favicon.svg')) . '" type="image/svg+xml">' . "\n";
}, 1);

// Emoji-szkriptek kikapcsolása (gyorsaság)
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

require_once get_theme_file_path('inc/components.php');
require_once get_theme_file_path('inc/seo.php');
require_once get_theme_file_path('inc/toc.php');

// A téma a Kompasz bővítmény nélkül nem működik: admin figyelmeztetés + egyszerű nyilvános üzenet
add_action('admin_notices', function () {
    if (!function_exists('kompasz_agency')) echo '<div class="notice notice-error"><p>A Kompasz témához kapcsold be a <strong>Kompasz – ügynökség-toplisták</strong> bővítményt.</p></div>';
});
add_action('template_redirect', function () {
    if (!function_exists('kompasz_agency') && !is_admin()) wp_die('Az oldal karbantartás alatt áll.', get_bloginfo('name'), ['response' => 503]);
});

// Ügynökség profiloldal: az elsődleges toplista az, ahol a cég a legelőrébb áll (egyenlőségnél a fejléc sorrendje dönt)
function kompasz_primary_context($a) {
    $best = [null, [], 0];
    foreach ($a['cats'] as $cat) {
        $list = kompasz_list($cat);
        $f = kompasz_is_featured_in($cat);
        if ($f) $list = array_values(array_filter($list, function ($x) use ($f) { return $x['id'] !== $f['id']; }));
        $pos = 0;
        foreach ($list as $i => $x) if ($x['id'] === $a['id']) { $pos = $i + 1; break; }
        if (!$best[0] || ($pos && (!$best[2] || $pos < $best[2]))) $best = [$cat, $list, $pos];
    }
    return $best;
}

// Hasonló ügynökségek: a legtöbb közös szolgáltatás-címke az elsődleges toplistán belül
function kompasz_similar($a, $list, $n = 6) {
    $mine = wp_list_pluck($a['tags'], 'term_id');
    $scored = [];
    foreach ($list as $i => $x) {
        if ($x['id'] === $a['id']) continue;
        $common = count(array_intersect($mine, wp_list_pluck($x['tags'], 'term_id')));
        $scored[] = [$common, -$i, $x + ['pos' => $i + 1]];
    }
    usort($scored, function ($p, $q) { return [$q[0], $q[1]] <=> [$p[0], $p[1]]; });
    return array_column(array_slice($scored, 0, $n), 2);
}
