<?php
// Referencia-ügyfelek áttekintő (/ugyfelek/)
get_header();
$terms = get_terms(['taxonomy' => 'ugyfel', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Referencia-ügyfelek', null]]); ?>
  <h1>Ügynökségek referencia-ügyfél szerint</h1>
  <p class="lead">Nagy hazai és nemzetközi cégek, amelyeknek legalább két ügynökség is dolgozott – kattints egy cégre, és megnézheted, mely ügynökségek nevezik meg referenciaként.</p>
  <div class="taggrid">
    <?php if (!is_wp_error($terms)) foreach ($terms as $t) printf('<a href="%s"><b>%s</b><span>%d ügynökség</span></a>', esc_url(get_term_link($t)), esc_html($t->name), count(kompasz_list($t))); ?>
  </div>
</main>
<?php get_footer();
