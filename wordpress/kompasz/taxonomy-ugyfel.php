<?php
// Referencia-ügyfél oldal (/ugyfel/<slug>/): azok az ügynökségek, amelyek ennek a cégnek dolgoztak
get_header();
$t = get_queried_object();
$n = count(kompasz_list($t));
$others = get_terms(['taxonomy' => 'ugyfel', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 12, 'exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Referencia-ügyfelek', home_url('/ugyfelek/')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(sprintf('Ügynökségek, amelyek a(z) %s cégnek dolgoztak', $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(sprintf('%d hazai ügynökség nevezi meg a(z) %s céget referenciaként a saját weboldalán. Ha hasonló cégnek keresel partnert, érdemes velük kezdeni.', $n, $t->name)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span>Más ügyfelek:</span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(home_url('/ugyfelek/')); ?>">Összes →</a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => 'Szűkítsd tovább a(z) ' . $t->name . ' cégnek dolgozó ügynökségeket']); ?>
  <section class="wrap more-lists">
    <h2>Toplisták kategóriánként</h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php get_footer();
