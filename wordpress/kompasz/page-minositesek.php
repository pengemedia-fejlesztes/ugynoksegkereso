<?php
// Minősítések áttekintő (/minositesek/)
get_header();
$terms = kompasz_public_terms('minosites');
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Minősítések'), null]]); ?>
  <h1><?php echo esc_html(kompasz__('Minősített ügynökségek')); ?></h1>
  <p class="lead"><?php echo esc_html(kompasz__('Google Partner, Meta Business Partner, Shoprenter és más hivatalos partner-minősítések – minősítésenként az ügynökségek, amelyek a saját weboldaluk szerint rendelkeznek vele.')); ?></p>
  <div class="taggrid">
    <?php if (!is_wp_error($terms)) foreach ($terms as $t) printf('<a href="%s"><b>%s</b><span>%s</span></a>', esc_url(get_term_link($t)), esc_html($t->name), esc_html(sprintf(kompasz__('%d ügynökség'), count(kompasz_list($t))))); ?>
  </div>
</main>
<?php get_footer();
