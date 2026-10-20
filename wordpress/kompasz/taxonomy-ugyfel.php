<?php
// Referencia-ügyfél oldal (/ugyfel/<slug>/): azok az ügynökségek, amelyek ennek a cégnek dolgoztak
get_header();
$t = get_queried_object();
$n = count(kompasz_list($t));
$others = kompasz_public_terms('ugyfel', 2, ['number' => 12, 'exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Referencia-ügyfelek'), kompasz_page_url('ugyfelek')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(sprintf(kompasz__('Ügynökségek, amelyek a(z) %s cégnek dolgoztak'), $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(sprintf(kompasz__('%d hazai ügynökség nevezi meg a(z) %s céget referenciaként a saját weboldalán. Ha hasonló cégnek keresel partnert, érdemes velük kezdeni.'), $n, $t->name)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span><?php echo esc_html(kompasz__('Más ügyfelek:')); ?></span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(kompasz_page_url('ugyfelek')); ?>"><?php echo esc_html(kompasz__('Összes →')); ?></a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => sprintf(kompasz__('Szűkítsd tovább a(z) %s cégnek dolgozó ügynökségeket'), $t->name)]); ?>
  <section class="wrap more-lists">
    <h2><?php echo esc_html(kompasz__('Toplisták kategóriánként')); ?></h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php get_footer();
