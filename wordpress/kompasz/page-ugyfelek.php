<?php
// Referencia-ügyfelek áttekintő (/ugyfelek/)
get_header();
$terms = get_terms(['taxonomy' => 'ugyfel', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Referencia-ügyfelek'), null]]); ?>
  <h1><?php echo esc_html(kompasz__('Ügynökségek referencia-ügyfél szerint')); ?></h1>
  <p class="lead"><?php echo esc_html(kompasz__('Nagy hazai és nemzetközi cégek, amelyeknek legalább két ügynökség is dolgozott – kattints egy cégre, és megnézheted, mely ügynökségek nevezik meg referenciaként.')); ?></p>
  <div class="taggrid">
    <?php if (!is_wp_error($terms)) foreach ($terms as $t) printf('<a href="%s"><b>%s</b><span>%s</span></a>', esc_url(get_term_link($t)), esc_html($t->name), esc_html(sprintf(kompasz__('%d ügynökség'), count(kompasz_list($t))))); ?>
  </div>
</main>
<?php get_footer();
