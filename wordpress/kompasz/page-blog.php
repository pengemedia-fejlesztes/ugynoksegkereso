<?php
// Blog: a /blog/ oldal a cikkek listája akkor is, ha a „Bejegyzések oldala” nincs beállítva
get_header();
$paged = max(1, (int) get_query_var('paged'));
$q = new WP_Query(['post_type' => 'post', 'post_status' => 'publish', 'paged' => $paged, 'posts_per_page' => 12]); ?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Blog', null]]); ?>
  <h1>Blog</h1>
  <?php if ($q->have_posts()): ?>
    <div class="posts">
      <?php while ($q->have_posts()): $q->the_post(); ?>
        <a class="post-card" href="<?php the_permalink(); ?>"><span class="pd"><?php echo esc_html(get_the_date()); ?></span><b><?php the_title(); ?></b><span><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28)); ?></span></a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php echo paginate_links(['total' => $q->max_num_pages, 'current' => $paged, 'prev_text' => '← Újabbak', 'next_text' => 'Régebbiek →']); ?>
  <?php else: ?>
    <p class="lead">Hamarosan itt olvashatod cikkeinket ügynökségválasztásról, online marketingről és AI-megoldásokról.</p>
  <?php endif; ?>
</main>
<?php get_footer();
