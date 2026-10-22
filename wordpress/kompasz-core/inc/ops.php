<?php
// Üzemeltetés: állapotellenőrzés, füstteszt, adatbázis-mentés – a WP-CLI (cli.php) és a REST API közös magja.
// REST (csak adminisztrátornak, alkalmazásjelszóval: Felhasználók → Profil → Alkalmazásjelszavak):
//   GET  /wp-json/kompasz/v1/doctor        állapot
//   POST /wp-json/kompasz/v1/backup        adatbázis-mentés (wp-content/kompasz-backups, kívülről nem elérhető)
//   POST /wp-json/kompasz/v1/migrate       függőben lévő migrációk
//   POST /wp-json/kompasz/v1/setup         ajánlatkérő űrlap, ütemezés, URL-szabályok
//   POST /wp-json/kompasz/v1/smoke         füstteszt minden ügynökségre
//   POST /wp-json/kompasz/v1/config        {"google_api_key": "…", "login_slug": "pengemedia"}
//   POST /wp-json/kompasz/v1/google-sync   Google-értékelések frissítése (háttérben, kötegekben)
//   POST /wp-json/kompasz/v1/import        {"step": "base" | "agencies" (+ "offset") | "finish"} – adatimport lépésenként
//   GET  /wp-json/kompasz/v1/merge-preview összevonásra javasolt duplikátumok
//   GET  /wp-json/kompasz/v1/migration-log a migrációk naplója (változáslistával)
//   POST /wp-json/kompasz/v1/geocode       {"limit": 30} hiányzó térképpozíciók (Nominatim)
//   POST /wp-json/kompasz/v1/restore       {"file": "<mentés neve>"} – adatbázis visszaállítása egy korábbi mentésből
// A HTTPS-es telepítő (scripts/deploy-https.sh) ezeket hívja; SSH nem kell.
if (!defined('ABSPATH')) exit;

/** Állapot: [ok => bool, rows => [[név, állapot, részlet]]]; állapot: OK | HIBA | figyelmeztetés */
function kompasz_doctor() {
    $rows = []; $fail = 0;
    $check = function ($name, $ok, $info, $required = true) use (&$rows, &$fail) {
        $rows[] = ['ellenőrzés' => $name, 'állapot' => $ok ? 'OK' : ($required ? 'HIBA' : 'figyelmeztetés'), 'részlet' => (string) $info];
        if (!$ok && $required) $fail++;
    };
    $build = is_file(KOMPASZ_CORE_DIR . 'build.json') ? ' ' . trim(file_get_contents(KOMPASZ_CORE_DIR . 'build.json')) : '';
    $check('Bővítmény verzió', true, KOMPASZ_CORE_VERSION . $build);
    $check('PHP / WordPress', version_compare(PHP_VERSION, '7.4', '>='), 'PHP ' . PHP_VERSION . ', WordPress ' . get_bloginfo('version'));
    $check('Adatbázis-séma', (int) get_option('kompasz_schema', 1) >= kompasz_schema_version(), get_option('kompasz_schema', 1) . ' / ' . kompasz_schema_version());
    $check('Aktív téma', get_stylesheet() === 'kompasz', get_stylesheet());
    $check('Contact Form 7', class_exists('WPCF7_ContactForm'), class_exists('WPCF7_ContactForm') ? WPCF7_VERSION : 'nincs bekapcsolva');
    $form = (int) get_option('kompasz_cf7_form');
    $check('Ajánlatkérő űrlap', !class_exists('WPCF7_ContactForm') || ($form && get_post_type($form) === 'wpcf7_contact_form'), $form ? "#$form" : 'még nincs', false);
    // Figyelmeztetés (nem hiba): kulcs nélkül minden más működik, csak a heti Google-frissítés áll
    $check('Google API-kulcs', (bool) kompasz_google_key(), kompasz_google_key() ? (defined('KOMPASZ_GOOGLE_API_KEY') ? 'wp-config.php' : 'beállítások') : 'hiányzik – a heti Google-értékelés frissítés nem fut', false);
    $n = wp_next_scheduled(KOMPASZ_GOOGLE_HOOK);
    $check('Heti Google-szinkron ütemezve', (bool) $n, $n ? wp_date('Y-m-d H:i', $n) : '–');
    $last = (int) get_option('kompasz_google_last_run');
    $check('Utolsó Google-szinkron', true, $last ? wp_date('Y-m-d H:i', $last) : 'még nem futott');
    $errs = get_posts(['post_type' => 'ugynokseg', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => 'kompasz_google_error']);
    $check('Google-szinkron hibák', !$errs, $errs ? count($errs) . ' ügynökség: ' . implode(', ', array_map('get_the_title', array_slice($errs, 0, 5))) : 'nincs', false);
    $check('Jóváhagyásra vár', true, (string) count(kompasz_pending_terms()));
    $check('Ügynökségek', (int) wp_count_posts('ugynokseg')->publish > 0, (string) wp_count_posts('ugynokseg')->publish);
    $check('Rank Math SEO', defined('RANK_MATH_VERSION'), defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : 'nincs bekapcsolva', false);
    $whl = in_array('wps-hide-login/wps-hide-login.php', (array) get_option('active_plugins', []), true);
    $check('Belépési cím (WPS Hide Login)', $whl && get_option('whl_page'), $whl ? home_url('/' . get_option('whl_page', 'login') . '/') : 'nincs bekapcsolva', false);
    $geo = count(get_posts(['post_type' => 'ugynokseg', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => 'kompasz_lat']));
    $check('Térkép-pozíció', true, $geo . ' / ' . wp_count_posts('ugynokseg')->publish . ' ügynökségnél');
    $check('Keresőrobotok', true, get_option('blog_public') ? 'indexelhető' : 'kitiltva (noindex)');
    return ['ok' => $fail === 0, 'rows' => $rows];
}

/** Telepítés utáni beállítás (WP-CLI és REST közös): ajánlatkérő űrlap, ütemezés, Rank Math, URL-szabályok. Visszaad: az űrlap azonosítója */
function kompasz_setup() {
    $form = kompasz_cf7_ensure_form();
    if (!wp_next_scheduled(KOMPASZ_GOOGLE_HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', KOMPASZ_GOOGLE_HOOK);
    // Rank Math: fiók-összekapcsolás nélkül csak a regisztráció kihagyásával ír a fejlécbe (= a beállító varázsló „Skip” lépése)
    if (defined('RANK_MATH_VERSION') && !get_option('rank_math_registration_skip')) update_option('rank_math_registration_skip', true);
    flush_rewrite_rules();
    return $form;
}

/** Füstteszt minden ügynökségre: az admin adatlap és a profiladatok hiba nélkül állnak elő. Visszaad: [count, problems[]] */
function kompasz_smoke() {
    $problems = []; $current = 0;
    set_error_handler(function ($no, $msg, $file, $line) use (&$problems, &$current) {
        if (strpos($file, 'kompasz') === false) return false; // csak a saját kódunk hibái
        $problems[] = sprintf('#%d %s: %s (%s:%d)', $current, get_the_title($current), $msg, basename($file), $line);
        return true;
    });
    $prev_user = get_current_user_id();
    if (!current_user_can('manage_options')) { $admin = get_users(['role' => 'administrator', 'number' => 1]); if ($admin) wp_set_current_user($admin[0]->ID); }
    require_once ABSPATH . 'wp-admin/includes/template.php';
    $ids = get_posts(['post_type' => 'ugynokseg', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]);
    foreach ($ids as $current) {
        ob_start();
        kompasz_agency_box(get_post($current));
        $html = ob_get_clean();
        foreach (['name="kompasz_tags[]"', 'name="kompasz_lang_on[]"', 'name="kompasz_megye"', 'kmap-edit', 'kompasz_terms[minosites]', 'kompasz_terms[iparag]', 'krefs', 'kompasz_founded'] as $needle) {
            if (strpos($html, $needle) === false) $problems[] = "#$current " . get_the_title($current) . ": hiányzik az adatlapról: $needle";
        }
        if (strpos($html, 'kompasz_toplists') !== false) $problems[] = "#$current " . get_the_title($current) . ': külön toplista-lista maradt az adatlapon';
        foreach (['kompasz_certs', 'kompasz_industries', 'kompasz_references', 'kompasz_county', 'kompasz_languages', 'kompasz_services'] as $old) {
            if (get_post_meta($current, $old, true) !== '') $problems[] = "#$current " . get_the_title($current) . ": régi mező maradt: $old";
        }
        $a = kompasz_agency($current);
        foreach (['certs', 'industries', 'references', 'phones', 'tags', 'cats'] as $k) if (!is_array($a[$k])) $problems[] = "#$current: $k nem tömb";
    }
    // Szerkezet: nincs külön toplista-taxonómia, minden toplistának van címe és neve, a telefonszám kulcsa munkanyelv
    $old = get_terms(['taxonomy' => 'toplista', 'hide_empty' => false, 'fields' => 'ids']);
    if (!is_wp_error($old) && $old) $problems[] = count($old) . ' régi toplista-kifejezés maradt (toplista taxonómia)';
    if (!kompasz_categories()) $problems[] = 'nincs egy toplista oldallal rendelkező szolgáltatás sem';
    foreach ($ids as $current) {
        foreach (array_keys(kompasz_agency_phone_map($current)) as $tid) if (!term_exists((int) $tid, 'nyelv')) $problems[] = "#$current: telefonszám ismeretlen nyelvhez ($tid)";
    }
    restore_error_handler();
    wp_set_current_user($prev_user);
    return ['count' => count($ids), 'problems' => $problems];
}

function kompasz_backup_dir() {
    $dir = WP_CONTENT_DIR . '/kompasz-backups';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    // Kívülről nem tölthető le (Apache), és nem listázható
    if (!is_file("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
    if (!is_file("$dir/index.php")) file_put_contents("$dir/index.php", "<?php // Silence\n");
    return $dir;
}

/** Teljes adatbázis-mentés SQL-ként, gzip-pel (mysqldump nélkül, tárhelyen is működik). Visszaad: [file, size, tables] */
function kompasz_backup_db($label = '') {
    global $wpdb;
    @set_time_limit(600);
    $dir = kompasz_backup_dir();
    $file = sprintf('%s/%s-%s%s.sql.gz', $dir, sanitize_key(kompasz_lang()), gmdate('Ymd-His'), $label !== '' ? '-' . sanitize_key($label) : '');
    $gz = gzopen($file, 'wb6');
    if (!$gz) return new WP_Error('backup', 'A mentés fájlja nem hozható létre: ' . $file);
    gzwrite($gz, "-- Kompasz adatbázis-mentés " . gmdate('c') . "\nSET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n");
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'));
    foreach ($tables as $t) {
        $create = $wpdb->get_row("SHOW CREATE TABLE `$t`", ARRAY_N);
        gzwrite($gz, "DROP TABLE IF EXISTS `$t`;\n{$create[1]};\n\n");
        for ($off = 0; ; $off += 500) {
            $rows = $wpdb->get_results("SELECT * FROM `$t` LIMIT $off, 500", ARRAY_A);
            if (!$rows) break;
            $vals = [];
            foreach ($rows as $r) {
                $vals[] = '(' . implode(',', array_map(function ($v) use ($wpdb) { return $v === null ? 'NULL' : "'" . $wpdb->_real_escape($v) . "'"; }, $r)) . ')';
            }
            gzwrite($gz, "INSERT INTO `$t` VALUES\n" . implode(",\n", $vals) . ";\n");
        }
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET foreign_key_checks = 1;\n");
    gzclose($gz);
    // Az utolsó 20 mentés marad
    $all = glob("$dir/*.sql.gz") ?: [];
    usort($all, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    foreach (array_slice($all, 20) as $old) @unlink($old);
    return ['file' => basename($file), 'size' => filesize($file), 'tables' => count($tables)];
}


/** Mentés visszatöltése (csak a saját, kompasz_backup_db() által írt formátum: utasítások sor végi „;”-vel) */
function kompasz_restore_db($name) {
    global $wpdb;
    @set_time_limit(600);
    $file = kompasz_backup_dir() . '/' . basename((string) $name);
    if (!preg_match('/\.sql\.gz$/', $file) || !is_file($file)) return new WP_Error('restore', 'Nincs ilyen mentés: ' . basename((string) $name), ['status' => 404]);
    $gz = gzopen($file, 'rb');
    $stmt = ''; $n = 0;
    while (($line = gzgets($gz)) !== false) {
        if ($stmt === '' && (strpos($line, '--') === 0 || trim($line) === '')) continue;
        $stmt .= $line;
        if (substr(rtrim($line, "\r\n"), -1) === ';') {
            if ($wpdb->query($stmt) === false) { gzclose($gz); return new WP_Error('restore', 'Hiba a visszaállításnál: ' . $wpdb->last_error, ['status' => 500]); }
            $stmt = ''; $n++;
        }
    }
    gzclose($gz);
    wp_cache_flush();
    return ['file' => basename($file), 'statements' => $n];
}

// --- REST ---
add_action('rest_api_init', function () {
    $admin = function () { return current_user_can('manage_options'); };
    $route = function ($path, $method, $cb) use ($admin) {
        register_rest_route('kompasz/v1', $path, ['methods' => $method, 'callback' => $cb, 'permission_callback' => $admin]);
    };
    $route('/doctor', 'GET', function () { return kompasz_doctor(); });
    $route('/backup', 'POST', function (WP_REST_Request $r) {
        $res = kompasz_backup_db((string) $r->get_param('label'));
        return is_wp_error($res) ? $res : $res + ['ok' => true];
    });
    $route('/migrate', 'POST', function () {
        return ['ok' => true, 'done' => kompasz_run_migrations(), 'schema' => (int) get_option('kompasz_schema', 1)];
    });
    $route('/setup', 'POST', function () {
        return ['ok' => true, 'cf7_form' => kompasz_setup()];
    });
    $route('/smoke', 'POST', function () { $s = kompasz_smoke(); return $s + ['ok' => !$s['problems']]; });
    $route('/config', 'POST', function (WP_REST_Request $r) {
        $set = [];
        if ($r->has_param('google_api_key')) {
            update_option('kompasz_google_api_key', sanitize_text_field((string) $r->get_param('google_api_key')), false);
            $set[] = 'google_api_key';
        }
        // WPS Hide Login: a belépési oldal címe (pl. pengemedia → /pengemedia/; a wp-login.php és kijelentkezve a /wp-admin/ 404)
        if ($r->has_param('login_slug')) {
            $slug = sanitize_title((string) $r->get_param('login_slug'));
            if ($slug === '' || in_array($slug, ['wp-admin', 'wp-login', 'login', 'admin'], true)) return new WP_Error('config', 'Érvénytelen belépési cím.', ['status' => 400]);
            update_option('whl_page', $slug);
            $set[] = 'login_slug';
        }
        return ['ok' => true, 'set' => $set];
    });
    $route('/restore', 'POST', function (WP_REST_Request $r) {
        $res = kompasz_restore_db((string) $r->get_param('file'));
        return is_wp_error($res) ? $res : $res + ['ok' => true];
    });
    $route('/backups', 'GET', function () {
        $out = [];
        foreach (glob(kompasz_backup_dir() . '/*.sql.gz') ?: [] as $f) $out[] = ['file' => basename($f), 'size' => filesize($f), 'time' => gmdate('c', filemtime($f))];
        usort($out, function ($a, $b) { return strcmp($b['time'], $a['time']); });
        return $out;
    });
    $route('/import', 'POST', function (WP_REST_Request $r) {
        $data = kompasz_import_data();
        if (!$data) return new WP_Error('import', 'Nincs import/data.json', ['status' => 400]);
        @set_time_limit(300);
        $total = count($data['agencies']);
        switch ($r->get_param('step')) {
            case 'base': kompasz_import_base($data); return ['ok' => true, 'next' => 'agencies', 'offset' => 0, 'total' => $total];
            case 'agencies':
                $o = absint($r->get_param('offset'));
                $o += kompasz_import_agencies($data, $o, KOMPASZ_IMPORT_BATCH);
                return ['ok' => true, 'next' => $o < $total ? 'agencies' : 'finish', 'offset' => $o, 'total' => $total];
            case 'finish': kompasz_import_finish($data); return ['ok' => true, 'next' => null, 'total' => $total];
        }
        return new WP_Error('import', 'Ismeretlen lépés', ['status' => 400]);
    });
    $route('/merge-preview', 'GET', function () { return kompasz_merge_preview(); });
    $route('/migration-log', 'GET', function () { return (array) get_option('kompasz_migration_log', []); });
    $route('/geocode', 'POST', function (WP_REST_Request $r) {
        [$found, $missing, $left] = kompasz_geocode_batch(min(60, absint($r->get_param('limit')) ?: 30));
        return ['ok' => true, 'found' => $found, 'missing' => $missing, 'remaining' => $left];
    });
    $route('/google-sync', 'POST', function () {
        if (!kompasz_google_key()) return new WP_Error('google', 'Nincs Google API-kulcs.', ['status' => 400]);
        wp_schedule_single_event(time(), KOMPASZ_GOOGLE_BATCH_HOOK, [0]);
        spawn_cron();
        return ['ok' => true, 'started' => true];
    });
});
