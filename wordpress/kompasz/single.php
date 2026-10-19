<?php
get_header();
while (have_posts()): the_post();
    [$content, $toc] = kompasz_toc_prepare(apply_filters('the_content', get_the_content())); ?>
<main class="wrap page">
  <?php echo kompasz_crumbs([[kompasz__('Kezdőlap'), home_url('/')], ['Blog', kompasz_page_url('blog')], [get_the_title(), null]]); ?>
  <h1><?php the_title(); ?></h1>
  <div class="pd"><?php echo esc_html(get_the_date()); ?></div>
  <article class="prose" id="cikk"><?php echo kompasz_toc_card($toc); ?><?php echo $content; ?></article>
</main>
<?php endwhile;
get_footer();
