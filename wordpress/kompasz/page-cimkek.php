<?php
// Szolgáltatás-címkék áttekintője (/cimkek/)
get_header();
$tags = get_terms(['taxonomy' => 'szolgaltatas', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Szolgáltatások', null]]); ?>
  <h1>Ügynökségek szolgáltatás szerint</h1>
  <p class="lead">Böngéssz a hazai ügynökségek között szolgáltatás szerint: SEO, Google Ads, Facebook hirdetés, AI SEO, weboldal készítés, AI agent fejlesztés és még sok más.</p>
  <div class="taggrid">
    <?php if (!is_wp_error($tags)) foreach ($tags as $t) printf('<a href="%s"><b>%s</b><span>%d cég</span></a>', esc_url(get_term_link($t)), esc_html($t->name), count(kompasz_list($t))); ?>
  </div>
</main>
<?php get_footer();
