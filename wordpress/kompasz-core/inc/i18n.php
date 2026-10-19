<?php
// Nyelvi réteg: a nyilvános felület szövegei és URL-részei magyarul (forrás) vagy németül (de.ugynoksegek.pmdev.hu).
// A nyelvet az import állítja be (data.json → site.lang), vagy a wp-config.php: define('KOMPASZ_LANG', 'de');
// Az admin felület magyar marad.
if (!defined('ABSPATH')) exit;

function kompasz_lang() {
    // Nincs külön gyorsítótár: az importálás közben átállított nyelv azonnal érvényes (a get_option maga is gyorsítótáraz)
    return defined('KOMPASZ_LANG') ? KOMPASZ_LANG : (get_option('kompasz_lang') ?: 'hu');
}

/** Fordítás: a magyar szöveg a kulcs; ha nincs fordítás, a magyar marad */
function kompasz__($hu) {
    static $dicts = [];
    $lang = kompasz_lang();
    if ($lang === 'hu') return $hu;
    if (!isset($dicts[$lang])) {
        $file = KOMPASZ_CORE_DIR . 'lang/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
        $dicts[$lang] = is_file($file) ? (array) include $file : [];
    }
    return $dicts[$lang][$hu] ?? $hu;
}

/** URL-részek nyelvenként: bejegyzéstípus, taxonómiák, rendszeroldalak */
function kompasz_slug($key) {
    $map = [
        'de' => [
            'ugynokseg' => 'agentur', 'cimke' => 'leistung', 'ugyfel' => 'referenzkunde', 'minosites' => 'zertifizierung', 'iparag' => 'branche',
            'cimkek' => 'leistungen', 'ugyfelek' => 'referenzkunden', 'minositesek' => 'zertifizierungen', 'iparagak' => 'branchen',
            'rolunk' => 'ueber-uns', 'blog' => 'blog', 'kapcsolat' => 'kontakt', 'kivalasztasi-elvek' => 'auswahlkriterien',
            'aszf' => 'agb', 'adatvedelem' => 'datenschutz', 'impresszum' => 'impressum',
        ],
    ];
    return $map[kompasz_lang()][$key] ?? $key;
}

function kompasz_page_url($key) {
    return home_url('/' . kompasz_slug($key) . '/');
}

/** Rendszeroldalak, amelyekhez saját sablon tartozik (page-<magyar kulcs>.php) – a német slugú oldal is ezt kapja */
add_filter('page_template_hierarchy', function ($templates) {
    if (kompasz_lang() === 'hu') return $templates;
    $p = get_queried_object();
    if (!$p || empty($p->post_name)) return $templates;
    foreach (['cimkek', 'ugyfelek', 'minositesek', 'iparagak', 'blog'] as $key) {
        if ($p->post_name === kompasz_slug($key)) array_unshift($templates, "page-$key.php");
    }
    return $templates;
});

/** HTML lang és schema.org nyelv/ország */
function kompasz_locale() {
    return ['hu' => ['html' => 'hu', 'schema' => 'hu-HU', 'country' => 'HU'], 'de' => ['html' => 'de', 'schema' => 'de-DE', 'country' => 'DE']][kompasz_lang()]
        ?? ['html' => 'hu', 'schema' => 'hu-HU', 'country' => 'HU'];
}
