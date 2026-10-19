<?php
// Szolgáltatás-címkék áttekintője (/cimkek/)
get_header();
$tags = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [kompasz__('Szolgáltatások'), null]]); ?>
  <h1><?php echo esc_html(kompasz__('Ügynökségek szolgáltatás szerint')); ?></h1>
  <p class="lead"><?php echo esc_html(kompasz__('Böngéssz a hazai ügynökségek között szolgáltatás szerint: SEO, Google Ads, Facebook hirdetés, AI SEO, weboldal készítés, AI agent fejlesztés és még sok más.')); ?></p>
  <div class="taggrid">
    <?php if (!is_wp_error($tags)) foreach ($tags as $t) printf('<a href="%s"><b>%s</b><span>%s</span></a>', esc_url(get_term_link($t)), esc_html($t->name), esc_html(sprintf(kompasz__('%d cég'), count(kompasz_list($t))))); ?>
  </div>
</main>
<?php get_footer();
