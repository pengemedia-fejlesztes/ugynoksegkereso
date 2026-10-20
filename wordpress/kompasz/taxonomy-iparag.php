<?php
// Iparág oldal (/iparag/<slug>/): az adott iparágban tapasztalattal rendelkező ügynökségek
get_header();
$t = get_queried_object();
$n = count(kompasz_list($t));
$others = kompasz_public_terms('iparag', 2, ['exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Iparágak'), kompasz_page_url('iparagak')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(sprintf(kompasz__('%s: ügynökségek iparági tapasztalattal'), $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(sprintf(kompasz__('%d hazai ügynökség nevezi meg a saját weboldalán, hogy dolgozott ebben az iparágban: %s.'), $n, $t->name)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span><?php echo esc_html(kompasz__('Más iparágak:')); ?></span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(kompasz_page_url('iparagak')); ?>"><?php echo esc_html(kompasz__('Összes →')); ?></a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => sprintf(kompasz__('Szűkítsd tovább a(z) %s területen tapasztalt cégeket'), $t->name)]); ?>
  <section class="wrap more-lists">
    <h2><?php echo esc_html(kompasz__('Toplisták kategóriánként')); ?></h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php get_footer();
