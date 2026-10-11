<?php
// Szolgáltatás-címke oldal: a címkének megfelelő ügynökségek, saját bevezetővel és SEO-szöveggel
get_header();
$t = get_queried_object();
$kw = kompasz_term_meta($t, 'kompasz_kw', $t->name);
$others = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 12, 'exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Szolgáltatások', home_url('/cimkek/')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(kompasz_term_meta($t, 'kompasz_h1', $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(kompasz_term_meta($t, 'kompasz_intro', $t->description)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span>Más szolgáltatások:</span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(home_url('/cimkek/')); ?>">Összes →</a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => 'Szűkítsd tovább a(z) ' . $t->name . ' szolgáltatást kínáló cégeket']); ?>
  <section class="wrap more-lists">
    <h2>Toplisták kategóriánként</h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php
echo kompasz_seo_block(mb_strtoupper(mb_substr($kw, 0, 1)) . mb_substr($kw, 1) . ': amit érdemes tudni', kompasz_term_seo($t));
get_footer();
