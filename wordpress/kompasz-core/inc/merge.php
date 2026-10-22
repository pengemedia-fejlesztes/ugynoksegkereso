<?php
// Duplikátumok összevonása (iparág, minősítés, referencia-ügyfél): ugyanaz az érték több néven ne szerepeljen.
// Normalizált név: kisbetű, ékezet, cégforma („Kft.”, „Zrt.”, „GmbH”…), „Magyarország” és írásjelek nélkül; minősítésnél az ismert
// névváltozatok egy névre (kompasz_cert_name). Azonos kulcs = biztos duplikátum (az 5. migráció ezeket vonja össze);
// hasonló kulcs (egyik a másik eleje, vagy egy betű eltérés) = javaslat, az adminisztrátor dönt (Ügynökségek → Összevonás).
if (!defined('ABSPATH')) exit;

function kompasz_merge_taxonomies() {
    return ['iparag' => 'Iparágak', 'minosites' => 'Minősítések', 'ugyfel' => 'Referencia-ügyfelek'];
}

function kompasz_norm_key($name, $taxonomy = '') {
    $name = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
    if ($taxonomy === 'minosites') $name = kompasz_cert_name($name);
    $s = kompasz_fold(trim(explode('|', $name)[0]));
    $s = preg_replace('/\b(kft|zrt|nyrt|bt|kkt|ev|ltd|gmbh|ag|se|kg|ug|inc|llc|plc|co|hungary|hungaria|magyarorszag|deutschland|germany|group|csoport|holding)\b\.?/', ' ', $s);
    // Ügyfélnév: „Magyar Telekom” = „Telekom” = „Telekom HU”, „Erste Bank” = „Erste”, „BENU Gyógyszertárak” = „BENU”
    if ($taxonomy === 'ugyfel') $s = preg_replace(['/^\s*magyar\s+/', '/\s+(bank|biztosito|gyogyszertar|gyogyszertarak|hu)\s*$/'], '', trim($s));
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

function kompasz_levenshtein1($a, $b) {
    return strlen($a) >= 6 && strlen($b) >= 6 && abs(strlen($a) - strlen($b)) <= 1 && levenshtein($a, $b) === 1;
}

/**
 * Összevonási csoportok egy taxonómiában: [['key', 'sure' => bool, 'terms' => WP_Term[] (a javasolt megtartandó elöl)]].
 * Megtartandó: amelyiknél több ügynökség szerepel; egyenlőségnél az ékezetes / hosszabb (teljesebb) név.
 */
function kompasz_merge_groups($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    if (is_wp_error($terms)) return [];
    $by = []; $words = [];
    foreach ($terms as $t) {
        $k = kompasz_norm_key($t->name, $taxonomy);
        if (strlen($k) < 2) continue;
        $by[$k][] = $t;
        $words[$k] = $words[$k] ?? preg_split('/[^a-z0-9]+/', kompasz_fold(html_entity_decode($t->name, ENT_QUOTES, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY);
    }
    $sort = function (array $ts) {
        usort($ts, function ($a, $b) {
            $acc = function ($t) { return strlen($t->name) - strlen(remove_accents($t->name)); };
            return [$b->count, $acc($b), mb_strlen($b->name), $a->term_id] <=> [$a->count, $acc($a), mb_strlen($a->name), $b->term_id];
        });
        return $ts;
    };
    $groups = [];
    foreach ($by as $k => $ts) if (count($ts) > 1) $groups[] = ['key' => $k, 'sure' => true, 'terms' => $sort($ts)];
    // Hasonló kulcsok (csak javaslat): egy betű eltérés („autoipar” ~ „autoipari”), vagy a rövidebb név szavai a hosszabb első szavai
    // („Richter” ~ „Richter Gedeon”; „Budapest Bank” ~ „Budapest Airport” nem)
    $keys = array_keys($by);
    sort($keys);
    $seen = [];
    foreach ($keys as $i => $a) {
        foreach (array_slice($keys, $i + 1) as $b) {
            [$wa, $wb] = count($words[$a]) <= count($words[$b]) ? [$words[$a], $words[$b]] : [$words[$b], $words[$a]];
            $prefix = $wa && count($wa) < count($wb) && array_slice($wb, 0, count($wa)) === $wa && strlen(implode('', $wa)) >= 4;
            if (!$prefix && !kompasz_levenshtein1($a, $b)) continue;
            if (isset($seen["$a|$b"])) continue;
            $seen["$a|$b"] = 1;
            $groups[] = ['key' => "$a ~ $b", 'sure' => false, 'terms' => $sort(array_merge($by[$a], $by[$b]))];
        }
    }
    return $groups;
}

/**
 * Összevonás: a $from kifejezések ügynökségei a $keep kifejezést kapják, a $from kifejezések törlődnek, a címük 301-gyel a megtartottra mutat.
 * Referenciánál az esettanulmány-link is átkerül. Visszaad: a változás leírása, vagy WP_Error.
 */
function kompasz_merge_terms($taxonomy, $keep_id, array $from_ids) {
    $keep = get_term((int) $keep_id, $taxonomy);
    if (!$keep || is_wp_error($keep)) return new WP_Error('merge', 'A megtartandó érték nem létezik.');
    $names = [];
    foreach (array_unique(array_map('intval', $from_ids)) as $fid) {
        if ($fid === (int) $keep->term_id) continue;
        $from = get_term($fid, $taxonomy);
        if (!$from || is_wp_error($from)) continue;
        foreach (get_objects_in_term($fid, $taxonomy) as $pid) {
            $pid = (int) $pid;
            wp_add_object_terms($pid, [(int) $keep->term_id], $taxonomy);
            if ($taxonomy === 'ugyfel') {
                $urls = json_decode((string) get_post_meta($pid, 'kompasz_ref_urls', true), true) ?: [];
                if (!empty($urls[$fid]) && empty($urls[$keep->term_id])) $urls[$keep->term_id] = $urls[$fid];
                unset($urls[$fid]);
                update_post_meta($pid, 'kompasz_ref_urls', wp_json_encode($urls));
            }
        }
        // Ha a megtartott még jóváhagyásra várt, de a másik jóvá volt hagyva, a megtartott is jóváhagyott lesz
        if (!kompasz_term_is_pending($from)) delete_term_meta($keep->term_id, KOMPASZ_PENDING_META);
        kompasz_add_redirect($from, $taxonomy, $keep->term_id);
        $aliases = (array) get_term_meta($keep->term_id, 'kompasz_aliases', true);
        $aliases[] = $from->name;
        update_term_meta($keep->term_id, 'kompasz_aliases', array_values(array_unique(array_filter($aliases))));
        wp_delete_term($fid, $taxonomy);
        $names[] = sprintf('„%s” (%d)', $from->name, (int) $from->count);
    }
    if (!$names) return new WP_Error('merge', 'Nincs összevonható érték.');
    clean_term_cache($keep->term_id, $taxonomy);
    $msg = sprintf('%s: %s → „%s” (összesen %d ügynökség)', kompasz_merge_taxonomies()[$taxonomy] ?? $taxonomy, implode(', ', $names), $keep->name, count(get_objects_in_term($keep->term_id, $taxonomy)));
    $log = (array) get_option('kompasz_merge_log', []);
    $log[] = ['at' => gmdate('c'), 'user' => get_current_user_id(), 'change' => $msg];
    update_option('kompasz_merge_log', array_slice($log, -500), false);
    return $msg;
}

/**
 * Egységes nevek: [regex (ékezet nélküli, kisbetűs névre) => egységes név]. Minden illeszkedő kifejezés az egységes nevűbe olvad
 * (ha nincs ilyen, létrejön). Visszaad: a változások listája.
 */
function kompasz_merge_canonical($taxonomy, array $map) {
    $changes = [];
    $groups = [];
    foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) ?: [] as $t) {
        $f = kompasz_fold(trim(html_entity_decode($t->name, ENT_QUOTES, 'UTF-8')));
        foreach ($map as $re => $name) if (preg_match($re, $f)) { $groups[$name][] = $t; break; }
    }
    foreach ($groups as $name => $terms) {
        $keep = null;
        foreach ($terms as $t) if (html_entity_decode($t->name, ENT_QUOTES, 'UTF-8') === $name) $keep = $t;
        // Az egységes nevű (vagy azonos kulcsú) kifejezés a listán kívül is lehet (pl. 0 ügynökséggel, egy korábbi importból)
        if (!$keep && ($id = kompasz_norm_index($taxonomy)[kompasz_norm_key($name, $taxonomy)] ?? 0) && ($t = get_term($id, $taxonomy)) && !is_wp_error($t)) $keep = $t;
        if ($keep && html_entity_decode($keep->name, ENT_QUOTES, 'UTF-8') !== $name) wp_update_term($keep->term_id, $taxonomy, ['name' => $name]);
        if (!$keep) {
            if (count($terms) === 1) { wp_update_term($terms[0]->term_id, $taxonomy, ['name' => $name]); $changes[] = sprintf('%s: „%s” → „%s” (átnevezés)', kompasz_merge_taxonomies()[$taxonomy], $terms[0]->name, $name); continue; }
            $r = wp_insert_term($name, $taxonomy);
            if (is_wp_error($r) && $r->get_error_code() === 'term_exists') $r = ['term_id' => (int) $r->get_error_data()];
            if (is_wp_error($r)) continue;
            $keep = get_term((int) $r['term_id'], $taxonomy);
        }
        $from = array_values(array_filter(wp_list_pluck($terms, 'term_id'), function ($id) use ($keep) { return (int) $id !== (int) $keep->term_id; }));
        if (!$from) continue;
        $r = kompasz_merge_terms($taxonomy, $keep->term_id, $from);
        if (!is_wp_error($r)) $changes[] = $r;
    }
    return $changes;
}

/** Az 5. migráció: minden biztos (azonos kulcsú) csoport összevonása. Visszaad: a változások listája */
function kompasz_merge_auto() {
    $changes = [];
    foreach (array_keys(kompasz_merge_taxonomies()) as $tax) {
        foreach (kompasz_merge_groups($tax) as $g) {
            if (!$g['sure']) continue;
            $keep = array_shift($g['terms']);
            $r = kompasz_merge_terms($tax, $keep->term_id, wp_list_pluck($g['terms'], 'term_id'));
            if (!is_wp_error($r)) $changes[] = $r;
        }
    }
    return $changes;
}

/** Az összevonás előtti, jóváhagyásra szánt lista (WP-CLI / REST): [taxonómia, megtartandó, összevonandók, biztos] */
function kompasz_merge_preview() {
    $out = [];
    foreach (kompasz_merge_taxonomies() as $tax => $label) {
        foreach (kompasz_merge_groups($tax) as $g) {
            $keep = $g['terms'][0];
            $out[] = ['típus' => $label, 'megmarad' => sprintf('%s (%d)', $keep->name, $keep->count),
                'beolvad' => implode('; ', array_map(function ($t) { return sprintf('%s (%d)', $t->name, $t->count); }, array_slice($g['terms'], 1))),
                'biztos' => $g['sure'] ? 'igen' : 'javaslat'];
        }
    }
    return $out;
}

/** Normalizált kulcs => kifejezés-azonosító egy taxonómiában (kérésen belül gyorsítótárazva; új kifejezésnél bővül) */
function kompasz_norm_index($taxonomy, $add = null) {
    static $idx = [];
    if (!isset($idx[$taxonomy])) {
        $idx[$taxonomy] = [];
        foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) ?: [] as $t) $idx[$taxonomy][kompasz_norm_key($t->name, $taxonomy)] ??= (int) $t->term_id;
    }
    if ($add) $idx[$taxonomy][$add[0]] = $add[1];
    return $idx[$taxonomy];
}

// Az új érték felvételekor (cégfiók, admin, import) a már meglévő, azonos kulcsú kifejezést találja meg, és nem hoz létre duplikátumot
add_filter('pre_insert_term', function ($term, $taxonomy) {
    if (!isset(kompasz_merge_taxonomies()[$taxonomy]) || !is_string($term)) return $term;
    $k = kompasz_norm_key($term, $taxonomy);
    if (strlen($k) < 2) return $term;
    $id = kompasz_norm_index($taxonomy)[$k] ?? 0;
    if ($id && ($t = get_term($id, $taxonomy)) && !is_wp_error($t)) return new WP_Error('term_exists', 'Már létezik: ' . $t->name, $id);
    return $term;
}, 10, 2);
add_action('created_term', function ($term_id, $tt_id, $taxonomy) {
    if (!isset(kompasz_merge_taxonomies()[$taxonomy])) return;
    $t = get_term($term_id, $taxonomy);
    if ($t && !is_wp_error($t)) kompasz_norm_index($taxonomy, [kompasz_norm_key($t->name, $taxonomy), (int) $term_id]);
}, 10, 3);

/** Összevonásra váró csoportok száma (a menü jelzéséhez; gyorsítótárazva, kifejezés-változáskor törlődik) */
function kompasz_merge_count() {
    $n = get_transient('kompasz_merge_count');
    if ($n === false) {
        $n = 0;
        foreach (array_keys(kompasz_merge_taxonomies()) as $tax) $n += count(kompasz_merge_groups($tax));
        set_transient('kompasz_merge_count', $n, DAY_IN_SECONDS);
    }
    return (int) $n;
}
foreach (array_keys(kompasz_merge_taxonomies()) as $tax) {
    foreach (["created_$tax", "edited_$tax", "delete_$tax"] as $hook) add_action($hook, function () { delete_transient('kompasz_merge_count'); });
}

add_action('admin_menu', function () {
    $n = kompasz_merge_count();
    add_submenu_page('edit.php?post_type=ugynokseg', 'Duplikátumok összevonása', 'Összevonás' . ($n ? ' <span class="awaiting-mod">' . $n . '</span>' : ''), 'manage_categories', 'kompasz-merge', 'kompasz_merge_page');
});

function kompasz_merge_page() {
    $tab = sanitize_key($_GET['tax'] ?? 'iparag');
    if (!isset(kompasz_merge_taxonomies()[$tab])) $tab = 'iparag';
    echo '<div class="wrap"><h1>Duplikátumok összevonása</h1>';
    echo '<p class="description">Ugyanaz az érték ne szerepeljen több néven. Csoportonként válaszd ki, melyik név maradjon (●), és pipáld be, melyek olvadjanak bele (☑): azok ügynökségei a megmaradót kapják, a régi oldalaik 301-gyel átirányítanak. A „biztos” csoportokban a név csak ékezetben, kis-nagybetűben, cégformában vagy írásjelben tér el; a „javaslat” csoportokat nézd át.</p>';
    if ($msg = get_transient('kompasz_merge_msg_' . get_current_user_id())) {
        delete_transient('kompasz_merge_msg_' . get_current_user_id());
        echo '<div class="notice notice-success"><ul style="list-style:disc;margin-left:20px">';
        foreach ((array) $msg as $m) echo '<li>' . esc_html($m) . '</li>';
        echo '</ul></div>';
    }
    echo '<nav class="nav-tab-wrapper">';
    foreach (kompasz_merge_taxonomies() as $tax => $label) {
        printf('<a class="nav-tab%s" href="%s">%s (%d)</a>', $tax === $tab ? ' nav-tab-active' : '', esc_url(admin_url('edit.php?post_type=ugynokseg&page=kompasz-merge&tax=' . $tax)), esc_html($label), count(kompasz_merge_groups($tax)));
    }
    echo '</nav>';
    $groups = kompasz_merge_groups($tab);
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('kompasz_merge');
    echo '<input type="hidden" name="action" value="kompasz_merge"><input type="hidden" name="tax" value="' . esc_attr($tab) . '">';
    if (!$groups) echo '<p><strong>Nincs összevonásra javasolt érték.</strong></p>';
    else {
        echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th style="width:90px">Csoport</th><th>Értékek (● megmarad, ☑ beleolvad) – zárójelben az ügynökségek száma</th></tr></thead><tbody>';
        foreach ($groups as $i => $g) {
            echo '<tr><td>' . ($g['sure'] ? '<strong>biztos</strong>' : '<span style="color:#996800">javaslat</span>') . '</td><td>';
            foreach ($g['terms'] as $j => $t) {
                printf('<label style="display:inline-block;margin:2px 18px 2px 0"><input type="radio" name="keep[%1$d]" value="%2$d"%3$s> <input type="checkbox" name="from[%1$d][]" value="%2$d"%4$s> %5$s <span class="description">(%6$d)</span> <a href="%7$s" target="_blank" title="Szerkesztés">✎</a></label>',
                    $i, $t->term_id, checked($j === 0, true, false), checked($j > 0 && $g['sure'], true, false), esc_html($t->name), $t->count, esc_url(get_edit_term_link($t->term_id, $tab)));
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('A bejelöltek összevonása');
    }
    echo '</form>';
    // Kézi összevonás: bármely két érték
    $all = get_terms(['taxonomy' => $tab, 'hide_empty' => false, 'orderby' => 'name']) ?: [];
    echo '<h2>Kézi összevonás</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('kompasz_merge');
    echo '<input type="hidden" name="action" value="kompasz_merge"><input type="hidden" name="tax" value="' . esc_attr($tab) . '"><input type="hidden" name="manual" value="1">';
    $opts = '';
    foreach ($all as $t) $opts .= sprintf('<option value="%d">%s (%d)</option>', $t->term_id, esc_html($t->name), $t->count);
    echo '<p><select name="from_one">' . $opts . '</select> beleolvad ebbe: <select name="keep_one">' . $opts . '</select> ';
    submit_button('Összevonás', 'secondary', 'submit', false);
    echo '</p></form>';
    $log = array_reverse((array) get_option('kompasz_merge_log', []));
    if ($log) {
        echo '<h2>Korábbi összevonások</h2><table class="widefat striped"><tbody>';
        foreach (array_slice($log, 0, 100) as $l) printf('<tr><td style="width:150px">%s</td><td>%s</td></tr>', esc_html(wp_date('Y-m-d H:i', strtotime($l['at']))), esc_html($l['change']));
        echo '</tbody></table>';
    }
    echo '</div>';
}

add_action('admin_post_kompasz_merge', function () {
    if (!current_user_can('manage_categories') || !check_admin_referer('kompasz_merge')) wp_die('Nincs jogosultság.');
    $tax = sanitize_key($_POST['tax'] ?? '');
    if (!isset(kompasz_merge_taxonomies()[$tax])) wp_die('Ismeretlen lista.');
    $done = [];
    if (!empty($_POST['manual'])) {
        $r = kompasz_merge_terms($tax, absint($_POST['keep_one'] ?? 0), [absint($_POST['from_one'] ?? 0)]);
        $done[] = is_wp_error($r) ? $r->get_error_message() : $r;
    } else {
        foreach ((array) ($_POST['keep'] ?? []) as $i => $keep) {
            $from = array_map('absint', (array) ($_POST['from'][$i] ?? []));
            if (!$from) continue;
            $r = kompasz_merge_terms($tax, absint($keep), $from);
            if (!is_wp_error($r)) $done[] = $r;
        }
    }
    set_transient('kompasz_merge_msg_' . get_current_user_id(), $done ?: ['Nem volt bejelölt összevonás.'], 120);
    wp_safe_redirect(admin_url('edit.php?post_type=ugynokseg&page=kompasz-merge&tax=' . $tax));
    exit;
});
