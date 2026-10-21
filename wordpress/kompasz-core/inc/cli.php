<?php
// WP-CLI parancsok a telepítéshez és üzemeltetéshez (a GitHub Actions deploy ezeket futtatja):
//   wp kompasz migrate [--from=<verzió>]   függőben lévő adatbázis-migrációk
//   wp kompasz doctor                      állapotellenőrzés; hibánál nem nulla kilépési kód (a deploy ezen bukik el)
//   wp kompasz google-sync [--id=<ID>]     Google-értékelések frissítése most
//   wp kompasz backup                      adatbázis-mentés (wp-content/kompasz-backups)
//   wp kompasz smoke                       füstteszt minden ügynökségre
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
    $d = kompasz_doctor();
    WP_CLI\Utils\format_items('table', $d['rows'], ['ellenőrzés', 'állapot', 'részlet']);
    if (!$d['ok']) WP_CLI::error(count(array_filter($d['rows'], function ($r) { return $r['állapot'] === 'HIBA'; })) . ' hiba.');
    WP_CLI::success('Minden rendben.');
});

WP_CLI::add_command('kompasz backup', function () {
    $r = kompasz_backup_db('cli');
    if (is_wp_error($r)) WP_CLI::error($r->get_error_message());
    WP_CLI::success(sprintf('Mentés: wp-content/kompasz-backups/%s (%s, %d tábla)', $r['file'], size_format($r['size']), $r['tables']));
});

WP_CLI::add_command('kompasz smoke', function () {
    $s = kompasz_smoke();
    if ($s['problems']) { foreach (array_slice($s['problems'], 0, 50) as $p) WP_CLI::warning($p); WP_CLI::error(count($s['problems']) . ' hiba ' . $s['count'] . ' ügynökségnél.'); }
    WP_CLI::success($s['count'] . ' ügynökség adatlapja rendben.');
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
