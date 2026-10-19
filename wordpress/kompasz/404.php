<?php get_header(); ?>
<main class="wrap page">
  <h1><?php echo esc_html(kompasz__('Az oldal nem található')); ?></h1>
  <p class="lead"><?php echo esc_html(kompasz__('Lehet, hogy elírtad a címet. Válassz egy toplistát:')); ?></p>
  <?php echo kompasz_cat_tiles(kompasz_categories()); ?>
</main>
<?php get_footer();
