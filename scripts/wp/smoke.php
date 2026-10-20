<?php
// Füstteszt minden ügynökségre: wp eval-file scripts/wp/smoke.php
// – az admin „Adatlap” doboz és a nyilvános profil adatai minden ügynökségnél hiba (notice/warning) nélkül állnak elő
// – az adatlap mezői az új szerkezetből jönnek (nincs régi soronkénti minősítés/iparág/referencia mező)
// Hiba esetén nem nulla kilépési kóddal áll le (CI és deploy ezen bukik el).
if (!defined('WP_CLI')) exit(1);
$problems = [];
set_error_handler(function ($no, $msg, $file, $line) use (&$problems, &$current) {
    if (strpos($file, 'kompasz') === false) return false; // csak a saját kódunk hibái
    $problems[] = sprintf('#%d %s: %s (%s:%d)', $current, get_the_title($current), $msg, basename($file), $line);
    return true;
});
$admin = get_users(['role' => 'administrator', 'number' => 1]);
if ($admin) wp_set_current_user($admin[0]->ID);
require_once ABSPATH . 'wp-admin/includes/template.php';
$ids = get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]);
foreach ($ids as $current) {
    ob_start();
    kompasz_agency_box(get_post($current));
    $html = ob_get_clean();
    foreach (['name="kompasz_tags[]"', 'name="kompasz_lang_on[]"', 'kompasz_terms[minosites]', 'kompasz_terms[iparag]', 'krefs', 'kompasz_founded'] as $needle) {
        if (strpos($html, $needle) === false) $problems[] = "#$current " . get_the_title($current) . ": hiányzik az adatlapról: $needle";
    }
    foreach (['kompasz_certs', 'kompasz_industries', 'kompasz_references'] as $old) {
        if (get_post_meta($current, $old, true) !== '') $problems[] = "#$current " . get_the_title($current) . ": régi mező maradt: $old";
    }
    $a = kompasz_agency($current);
    foreach (['certs', 'industries', 'references', 'phones', 'tags', 'cats'] as $k) if (!is_array($a[$k])) $problems[] = "#$current: $k nem tömb";
}
restore_error_handler();
if ($problems) {
    foreach (array_slice($problems, 0, 50) as $p) WP_CLI::warning($p);
    WP_CLI::error(count($problems) . ' hiba ' . count($ids) . ' ügynökségnél.');
}
WP_CLI::success(count($ids) . ' ügynökség adatlapja rendben.');
