<?php
// Minősítés oldal (/minosites/<slug>/): az adott minősítéssel rendelkező ügynökségek
get_header();
$t = get_queried_object();
$n = count(kompasz_list($t));
$others = get_terms(['taxonomy' => 'minosites', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'exclude' => [$t->term_id]]);
?>
<main>
  <div class="wrap pagehead">
    <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Minősítések', home_url('/minositesek/')], [$t->name, null]]); ?>
    <h1><?php echo esc_html(sprintf('%s minősítésű ügynökségek', $t->name)); ?></h1>
    <p class="lead"><?php echo esc_html(sprintf('%d hazai ügynökség rendelkezik %s minősítéssel a saját weboldala szerint.', $n, $t->name)); ?></p>
    <?php if (!is_wp_error($others) && $others): ?>
      <div class="related"><span>Más minősítések:</span><?php foreach ($others as $o) printf('<a href="%s">%s</a>', esc_url(get_term_link($o)), esc_html($o->name)); ?><a class="all" href="<?php echo esc_url(home_url('/minositesek/')); ?>">Összes →</a></div>
    <?php endif; ?>
  </div>
  <?php echo kompasz_listing($t, ['filter_note' => 'Szűkítsd tovább a(z) ' . $t->name . ' minősítésű cégeket']); ?>
  <section class="wrap more-lists">
    <h2>Toplisták kategóriánként</h2>
    <?php echo kompasz_cat_tiles(array_slice(kompasz_categories(), 0, 5)); ?>
  </section>
</main>
<?php get_footer();
