<!doctype html>
<html lang="<?php echo esc_attr(kompasz_locale()['html']); ?>">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$kompasz_current = is_tax('szolgaltatas') && kompasz_is_toplist(get_queried_object()) ? get_queried_object() : (is_singular('ugynokseg') ? kompasz_primary_context(kompasz_agency(get_queried_object_id()))[0] : null);
?>
<header class="hdr">
  <div class="wrap hdr-top">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo kompasz_brand_mark(30); ?><span class="bn"><?php bloginfo('name'); ?></span><span class="bt"><?php echo esc_html(kompasz__('ÜGYNÖKSÉG-TOPLISTÁK')); ?></span></a>
    <div class="lang"><?php if (kompasz_lang() === 'de'): ?><a href="https://ugynoksegek.pmdev.hu/" hreflang="hu">HU</a><span class="cur">DE</span><?php else: ?><span class="cur">HU</span><a href="https://de.ugynoksegek.pmdev.hu/" hreflang="de">DE</a><?php endif; ?></div>
  </div>
  <nav class="wrap catnav" aria-label="<?php echo esc_attr(kompasz__('Toplisták')); ?>">
    <?php foreach (kompasz_categories() as $c):
      $on = $kompasz_current && $kompasz_current->term_id === $c->term_id; ?>
      <a href="<?php echo esc_url(get_term_link($c)); ?>"<?php echo $on ? ' class="on" aria-current="page"' : ''; ?>><?php echo kompasz_cat_icon($c); ?><span><?php echo esc_html(kompasz_term_meta($c, 'kompasz_short', $c->name)); ?></span></a>
    <?php endforeach; ?>
  </nav>
</header>
