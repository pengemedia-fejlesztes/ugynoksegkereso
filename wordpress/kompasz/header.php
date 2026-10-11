<!doctype html>
<html lang="hu">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$kompasz_current = is_tax('toplista') ? get_queried_object() : (is_singular('ugynokseg') ? kompasz_primary_context(kompasz_agency(get_queried_object_id()))[0] : null);
?>
<header class="hdr">
  <div class="wrap hdr-top">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo kompasz_brand_mark(30); ?><span class="bn"><?php bloginfo('name'); ?></span><span class="bt">ÜGYNÖKSÉG-TOPLISTÁK</span></a>
    <div class="lang"><span class="cur">HU</span><a href="#" aria-disabled="true" title="Hamarosan">EN</a></div>
  </div>
  <nav class="wrap catnav" aria-label="Toplisták">
    <?php foreach (kompasz_categories() as $c):
      $on = $kompasz_current && $kompasz_current->term_id === $c->term_id; ?>
      <a href="<?php echo esc_url(get_term_link($c)); ?>"<?php echo $on ? ' class="on" aria-current="page"' : ''; ?>><?php echo kompasz_cat_icon($c); ?><span><?php echo esc_html(kompasz_term_meta($c, 'kompasz_short', $c->name)); ?></span></a>
    <?php endforeach; ?>
  </nav>
</header>
