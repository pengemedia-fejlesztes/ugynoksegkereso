<?php
// Iparág oldal (/iparag/<slug>/): az adott iparágban tapasztalattal rendelkező ügynökségek
get_header();
$t = get_queried_object();
$n = count(kompasz_list($t));
$others = get_terms(['taxonomy' => 'iparag', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Iparágak', home_url('/iparagak/')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(sprintf('%s: ügynökségek iparági tapasztalattal', $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(sprintf('%d hazai ügynökség nevezi meg a saját weboldalán, hogy dolgozott ebben az iparágban: %s.', $n, $t->name)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span>Más iparágak:</span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(home_url('/iparagak/')); ?>">Összes →</a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => 'Szűkítsd tovább a(z) ' . $t->name . ' területen tapasztalt cégeket']); ?>
  <section class="wrap more-lists">
    <h2>Toplisták kategóriánként</h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php get_footer();
