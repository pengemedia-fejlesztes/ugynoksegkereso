<?php
get_header();
while (have_posts()): the_post(); ?>
<main class="wrap page">
  <?php echo kompasz_crumbs([['Kezdőlap', home_url('/')], ['Blog', home_url('/blog/')], [get_the_title(), null]]); ?>
  <h1><?php the_title(); ?></h1>
  <div class="pd"><?php echo esc_html(get_the_date()); ?></div>
  <div class="prose"><?php the_content(); ?></div>
</main>
<?php endwhile;
get_footer();
