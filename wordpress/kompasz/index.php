<?php
// Blog és általános archívum
get_header(); ?>
<main class="wrap page wide">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], [is_home() ? 'Blog' : wp_strip_all_tags(get_the_archive_title()), null]]); ?>
  <h1><?php echo is_home() ? 'Blog' : wp_kses_post(get_the_archive_title()); ?></h1>
  <?php if (have_posts()): ?>
    <div class="posts">
      <?php while (have_posts()): the_post(); ?>
        <a class="post-card" href="<?php the_permalink(); ?>"><span class="pd"><?php echo esc_html(get_the_date()); ?></span><b><?php the_title(); ?></b><span><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28)); ?></span></a>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(['prev_text' => '← Újabbak', 'next_text' => 'Régebbiek →']); ?>
  <?php else: ?>
    <p class="lead">Hamarosan itt olvashatod cikkeinket ügynökségválasztásról, online marketingről és AI-megoldásokról.</p>
  <?php endif; ?>
</main>
<?php get_footer();
