<?php
// WP-CLI parancsok a telepítéshez és üzemeltetéshez (a GitHub Actions deploy ezeket futtatja):
//   wp kompasz migrate [--from=<verzió>]   függőben lévő adatbázis-migrációk
//   wp kompasz doctor                      állapotellenőrzés; hibánál nem nulla kilépési kód (a deploy ezen bukik el)
//   wp kompasz google-sync [--id=<ID>]     Google-értékelések frissítése most
//   wp kompasz setup                       telepítés utáni beállítás (ajánlatkérő űrlap, ütemezés, URL-szabályok); többször is futtatható
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) return;

WP_CLI::add_command('kompasz migrate', function ($args, $assoc) {
    $done = kompasz_run_migrations(isset($assoc['from']) ? (int) $assoc['from'] : null);
    WP_CLI::success($done ? 'Lefutott migrációk: ' . implode(', ', $done) . '. Séma: ' . get_option('kompasz_schema') : 'Nincs függőben lévő migráció (séma: ' . get_option('kompasz_schema', 1) . ').');
});

WP_CLI::add_command('kompasz setup', function () {
    $form = kompasz_cf7_ensure_form();
    WP_CLI::log($form ? "Ajánlatkérő űrlap: #$form" : 'Ajánlatkérő űrlap: kimarad (Contact Form 7 nincs bekapcsolva, vagy szándékosan törölve).');
    if (!wp_next_scheduled(KOMPASZ_GOOGLE_HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', KOMPASZ_GOOGLE_HOOK);
    flush_rewrite_rules();
    WP_CLI::success('Beállítva.');
});

WP_CLI::add_command('kompasz doctor', function () {
    $rows = []; $fail = 0;
    $check = function ($name, $ok, $info, $required = true) use (&$rows, &$fail) {
        $rows[] = ['ellenőrzés' => $name, 'állapot' => $ok ? 'OK' : ($required ? 'HIBA' : 'figyelmeztetés'), 'részlet' => $info];
        if (!$ok && $required) $fail++;
    };
    $check('Bővítmény verzió', true, KOMPASZ_CORE_VERSION);
    $check('Adatbázis-séma', (int) get_option('kompasz_schema', 1) >= kompasz_schema_version(), get_option('kompasz_schema', 1) . ' / ' . kompasz_schema_version());
    $check('Aktív téma', get_stylesheet() === 'kompasz', get_stylesheet());
    $check('Contact Form 7', class_exists('WPCF7_ContactForm'), class_exists('WPCF7_ContactForm') ? WPCF7_VERSION : 'nincs bekapcsolva');
    $form = (int) get_option('kompasz_cf7_form');
    $check('Ajánlatkérő űrlap', !class_exists('WPCF7_ContactForm') || ($form && get_post_type($form) === 'wpcf7_contact_form'), $form ? "#$form" : 'még nincs (az első admin-oldalbetöltés vagy a deploy létrehozza)', false);
    // Figyelmeztetés (nem hiba): kulcs nélkül minden más működik, csak a heti Google-frissítés áll
    $check('Google API-kulcs', (bool) kompasz_google_key(), kompasz_google_key() ? (defined('KOMPASZ_GOOGLE_API_KEY') ? 'wp-config.php' : 'beállítások') : 'hiányzik – a heti Google-értékelés frissítés nem fut', false);
    $check('Heti Google-szinkron ütemezve', (bool) wp_next_scheduled(KOMPASZ_GOOGLE_HOOK), ($n = wp_next_scheduled(KOMPASZ_GOOGLE_HOOK)) ? wp_date('Y-m-d H:i', $n) : '–');
    $errs = get_posts(['post_type' => 'ugynokseg', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => 'kompasz_google_error']);
    $check('Google-szinkron hibák', !$errs, $errs ? count($errs) . ' ügynökség: ' . implode(', ', array_map('get_the_title', array_slice($errs, 0, 5))) : 'nincs', false);
    $check('Jóváhagyásra vár', true, (string) count(kompasz_pending_terms()));
    $check('Ügynökségek', (int) wp_count_posts('ugynokseg')->publish > 0, (string) wp_count_posts('ugynokseg')->publish);
    WP_CLI\Utils\format_items('table', $rows, ['ellenőrzés', 'állapot', 'részlet']);
    if ($fail) WP_CLI::error("$fail hiba.");
    WP_CLI::success('Minden rendben.');
});

WP_CLI::add_command('kompasz google-sync', function ($args, $assoc) {
    if (!kompasz_google_key()) WP_CLI::error('Nincs Google API-kulcs (KOMPASZ_GOOGLE_API_KEY a wp-config.php-ban).');
    $ids = !empty($assoc['id']) ? [(int) $assoc['id']] : get_posts(['post_type' => 'ugynokseg', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1,
        'meta_query' => [['key' => 'kompasz_google_url', 'value' => '', 'compare' => '!=']]]);
    $ok = 0; $bad = 0;
    foreach ($ids as $id) {
        $r = kompasz_google_sync($id);
        if (is_wp_error($r)) { $bad++; WP_CLI::warning(get_the_title($id) . ': ' . $r->get_error_message()); } else $ok++;
    }
    update_option('kompasz_google_last_run', time());
    WP_CLI::success("Frissítve: $ok, hiba: $bad.");
});
