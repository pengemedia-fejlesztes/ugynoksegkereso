<?php
// Title, meta leírás és strukturált adat (JSON-LD). Ha Yoast vagy Rank Math fut, azok kezelik.
if (!defined('ABSPATH')) exit;

function kompasz_seo_plugin_active() {
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION');
}

/** Az aktuális oldal SEO-adatai: [title, description] */
function kompasz_seo_context() {
    static $ctx = null;
    if ($ctx !== null) return $ctx;
    $site = get_bloginfo('name');
    $title = ''; $desc = '';
    if (is_front_page()) {
        $title = $site . ' – a legjobb magyar ügynökségek egy helyen';
        $desc = 'Rangsorolt toplisták online marketing, Google Ads, Facebook hirdetés, AI SEO, AI agent fejlesztő, PR, SEO, Amazon, weboldal készítő és tartalomgyártó cégekről.';
    } elseif (is_tax('toplista')) {
        $t = get_queried_object();
        $title = kompasz_term_meta($t, 'kompasz_h1', $t->name) . ' | ' . $site;
        $intro = kompasz_term_meta($t, 'kompasz_intro', '');
        $desc = $intro ? preg_split('/(?<=\.)\s/u', $intro)[0] : '';
    } elseif (is_tax('szolgaltatas')) {
        $t = get_queried_object();
        $title = kompasz_term_meta($t, 'kompasz_title', $t->name) . ' (' . count(kompasz_list($t)) . ' cég) | ' . $site;
        $desc = kompasz_term_meta($t, 'kompasz_meta_desc', '');
    } elseif (is_singular('ugynokseg')) {
        $a = kompasz_agency(get_queried_object_id());
        $cat = kompasz_primary_context($a)[0];
        $title = $a['name'] . ($a['city'] ? ' (' . $a['city'] . ')' : '') . ' – ' . ($cat ? $cat->name : 'ügynökség') . ', értékelés, elérhetőség | ' . $site;
        $desc = wp_html_excerpt($a['desc'], 155, '…');
    }
    return $ctx = [$title, $desc];
}

add_filter('pre_get_document_title', function ($t) {
    if (kompasz_seo_plugin_active()) return $t;
    [$title] = kompasz_seo_context();
    return $title ?: $t;
});

add_action('wp_head', function () {
    if (kompasz_seo_plugin_active()) return;
    [, $desc] = kompasz_seo_context();
    if ($desc) printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($desc));
    $ld = kompasz_jsonld();
    if ($ld) printf("<script type=\"application/ld+json\">%s</script>\n", wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
}, 5);

function kompasz_breadcrumb_ld($items) {
    $list = [];
    foreach ($items as $i => [$name, $url]) {
        $e = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name];
        if ($url) $e['item'] = $url;
        $list[] = $e;
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function kompasz_org_ld($a) {
    $o = ['@type' => 'Organization', 'name' => $a['name'], 'url' => ($a['website'] && function_exists('kompasz_agency_has') && kompasz_agency_has($a['id'], 'website')) ? $a['website'] : $a['link']];
    if ($a['desc']) $o['description'] = $a['desc'];
    if ($a['logo']) $o['logo'] = $a['logo'];
    if ($a['city']) $o['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $a['city'], 'addressCountry' => 'HU'];
    if ($a['founded']) $o['foundingDate'] = (string) $a['founded'];
    return $o;
}

function kompasz_jsonld() {
    $home = home_url('/');
    $site = ['@type' => 'WebSite', '@id' => $home . '#website', 'name' => get_bloginfo('name'), 'url' => $home, 'inLanguage' => 'hu-HU'];
    if (is_front_page()) return ['@context' => 'https://schema.org'] + $site;
    if (is_tax(['toplista', 'szolgaltatas'])) {
        $t = get_queried_object();
        $url = get_term_link($t);
        $list = kompasz_list($t);
        [$title, $desc] = kompasz_seo_context();
        $crumbs = $t->taxonomy === 'toplista'
            ? [['Kezdőlap', $home], [$t->name, $url]]
            : [['Kezdőlap', $home], ['Szolgáltatások', home_url('/cimkek/')], [$t->name, $url]];
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'CollectionPage', '@id' => $url, 'url' => $url, 'name' => kompasz_term_meta($t, 'kompasz_h1', $t->name), 'description' => $desc, 'inLanguage' => 'hu-HU', 'isPartOf' => ['@id' => $home . '#website'], 'mainEntity' => ['@id' => $url . '#lista']],
            kompasz_breadcrumb_ld($crumbs),
            ['@type' => 'ItemList', '@id' => $url . '#lista', 'numberOfItems' => count($list), 'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                'itemListElement' => array_map(function ($a, $i) { return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $a['link'], 'name' => $a['name']]; }, $list, array_keys($list))],
            $site,
        ]];
    }
    if (is_singular('ugynokseg')) {
        $a = kompasz_agency(get_queried_object_id());
        $cat = kompasz_primary_context($a)[0];
        $crumbs = [['Kezdőlap', $home]];
        if ($cat) $crumbs[] = [$cat->name, get_term_link($cat)];
        $crumbs[] = [$a['name'], $a['link']];
        $org = kompasz_org_ld($a);
        $org['@id'] = $a['link'] . '#ceg';
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'ProfilePage', '@id' => $a['link'], 'url' => $a['link'], 'name' => $a['name'], 'inLanguage' => 'hu-HU', 'mainEntity' => ['@id' => $a['link'] . '#ceg'], 'isPartOf' => ['@id' => $home . '#website']],
            $org, kompasz_breadcrumb_ld($crumbs), $site,
        ]];
    }
    return null;
}
