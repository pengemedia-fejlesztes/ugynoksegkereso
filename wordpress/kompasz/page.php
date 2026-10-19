<?php
get_header();
while (have_posts()): the_post(); ?>
<main class="wrap page">
  <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], [get_the_title(), null]]); ?>
  <h1><?php the_title(); ?></h1>
  <div class="prose"><?php the_content(); ?></div>
</main>
<?php endwhile;
get_footer();
