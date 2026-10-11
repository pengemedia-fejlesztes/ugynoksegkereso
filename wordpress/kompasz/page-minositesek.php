<?php
// Minősítések áttekintő (/minositesek/)
get_header();
$terms = get_terms(['taxonomy' => 'minosites', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Minősítések', null]]); ?>
  <h1>Minősített ügynökségek</h1>
  <p class="lead">Google Partner, Meta Business Partner, Shoprenter és más hivatalos partner-minősítések – minősítésenként az ügynökségek, amelyek a saját weboldaluk szerint rendelkeznek vele.</p>
  <div class="taggrid">
    <?php if (!is_wp_error($terms)) foreach ($terms as $t) printf('<a href="%s"><b>%s</b><span>%d ügynökség</span></a>', esc_url(get_term_link($t)), esc_html($t->name), count(kompasz_list($t))); ?>
  </div>
</main>
<?php get_footer();
