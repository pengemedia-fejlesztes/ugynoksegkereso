<?php
// Kezdőlap (4a): ikonos kategória-csempék, kiemelt ügynökség, Legjobbra értékeltek, SEO-szöveg
get_header();
$cats = kompasz_categories();
$total = (int) wp_count_posts('ugynokseg')->publish;
$featured = kompasz_featured();
$tabs = array_slice($cats, 0, 5);
$om = null;
$home_tab = get_option('kompasz_home_tab', 'online-marketing-ugynoksegek');
foreach ($cats as $c) if ($c->slug === $home_tab) $om = $c;
$active = $om && in_array($om, $tabs, true) ? $om : ($tabs[0] ?? null);
$home_top = array_values(array_filter(array_map(function ($id) { return get_post_status($id) === 'publish' ? kompasz_agency($id) : null; }, (array) get_option('kompasz_home_top', []))));
$seo = get_option('kompasz_home_seo', []);
?>
<main>
  <section class="wrap home-hero">
    <h1><?php echo esc_html(kompasz__('A legjobb magyar ügynökségek, egy helyen.')); ?></h1>
    <p><?php echo esc_html(sprintf(kompasz__('%1$d+ ellenőrzött hazai ügynökség %2$d toplistán. Böngéssz kategóriánként, és vedd fel a kapcsolatot közvetlenül.'), floor($total / 10) * 10, count($cats))); ?></p>
  </section>

  <section class="wrap">
    <div class="cattiles">
      <?php foreach ($cats as $i => $c): $list = kompasz_list($c); ?>
        <a class="ctile<?php echo $i === 0 ? ' dark' : ''; ?>" href="<?php echo esc_url(get_term_link($c)); ?>">
          <span class="cti"><?php echo kompasz_cat_icon($c, 18); ?></span>
          <span class="ctn"><?php echo esc_html($c->name); ?></span>
          <span class="ctf"><span class="minis"><?php foreach (array_slice($list, 0, 3) as $a) echo kompasz_logo($a, 26, 'mini'); ?></span><span class="ctc"><?php echo esc_html(sprintf(kompasz__('%d cég →'), count($list))); ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($featured): ?><section class="wrap home-feat"><?php echo kompasz_featured_strip($featured); ?></section><?php endif; ?>

  <?php if ($tabs): ?>
  <section class="wrap home-top">
    <div class="sec-h"><?php // Értékelés nélküli listánál (pl. az új német oldalon) nem „legjobbra értékelt”, hanem válogatott
    $rated = false; foreach ($tabs as $c) foreach (array_slice(kompasz_list($c), 0, 5) as $x) if ($x['rating']) $rated = true; ?>
    <h2><?php echo esc_html(kompasz__($rated ? 'Legjobbra értékeltek' : 'Válogatott ügynökségek')); ?></h2><a href="<?php echo esc_url(get_term_link($active)); ?>" class="toplink" data-tablink><?php echo esc_html(kompasz__('Teljes lista →')); ?></a></div>
    <div class="tabs" role="tablist">
      <?php foreach ($tabs as $c): ?>
        <button type="button" role="tab" data-tab="<?php echo esc_attr($c->slug); ?>" data-href="<?php echo esc_url(get_term_link($c)); ?>" aria-selected="<?php echo $c === $active ? 'true' : 'false'; ?>"><?php echo esc_html(kompasz_term_meta($c, 'kompasz_short', $c->name)); ?></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($tabs as $c):
      $items = ($c === $om && $home_top) ? $home_top : array_slice(kompasz_list($c), 0, 5); ?>
      <div class="cards" role="tabpanel" data-panel="<?php echo esc_attr($c->slug); ?>"<?php echo $c === $active ? '' : ' hidden'; ?>>
        <?php foreach ($items as $i => $a) echo kompasz_card($a, $i + 1); ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</main>
<?php
if (!empty($seo['blocks'])) echo kompasz_seo_block($seo['title'] ?? '', $seo['blocks']);
get_footer();
