<?php
// Toplista oldal (4b): toplista oldallal rendelkező szolgáltatás (taxonomy-szolgaltatas.php hívja)
get_header();
$t = get_queried_object();
$name = kompasz_toplist_name($t);
$related = array_values(array_filter(kompasz_categories(), function ($c) use ($t) { return $c->term_id !== $t->term_id; }));
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Ügynökségek'), null], [$name, null]]); ?>
    <h1><?php echo esc_html(kompasz_term_meta($t, 'kompasz_h1', $name)); ?></h1>
    <p class="lead"><?php echo esc_html(kompasz_term_meta($t, 'kompasz_intro', $t->description)); ?></p>
    <div class="related"><span><?php echo esc_html(kompasz__('Kapcsolódó listák:')); ?></span><?php foreach (array_slice($related, 0, 4) as $c) printf('<a href="%s">%s%s</a>', esc_url(get_term_link($c)), kompasz_cat_icon($c, 15), esc_html(kompasz_toplist_name($c))); ?></div>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => sprintf(kompasz__('%s toplistára szabott szűrők'), $name)]); ?>
  <section class="wrap more-lists">
    <h2><?php echo esc_html(kompasz__('Nézd meg ezeket a toplistákat is')); ?></h2>
    <?php echo kompasz_cat_tiles(array_slice($related, 0, 5)); ?>
  </section>
</main>
<?php
echo kompasz_seo_block(sprintf(kompasz__('%s: amit érdemes tudni'), $name), kompasz_term_seo($t));
get_footer();
