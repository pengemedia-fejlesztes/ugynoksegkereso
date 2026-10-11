<footer class="ftr">
  <div class="wrap">
    <div class="fcols">
      <div class="fbrand">
        <a class="brand light" href="<?php echo esc_url(home_url('/')); ?>"><?php echo kompasz_brand_mark(28); ?><span class="bn"><?php bloginfo('name'); ?></span></a>
        <p><?php bloginfo('description'); ?></p>
        <?php if ($email = get_option('kompasz_email')): ?><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><?php endif; ?>
      </div>
      <div>
        <div class="flbl">TOPLISTÁK</div>
        <div class="fcats">
          <?php foreach (kompasz_categories() as $c): ?>
            <a href="<?php echo esc_url(get_term_link($c)); ?>"><span class="fi"><?php echo kompasz_cat_icon($c, 14); ?></span><?php echo esc_html($c->name); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div class="flbl">OLDALAK</div>
        <div class="fcol"><a href="<?php echo esc_url(home_url('/')); ?>">Kezdőlap</a><a href="<?php echo esc_url(home_url('/cimkek/')); ?>">Szolgáltatások</a><a href="<?php echo esc_url(home_url('/ugyfelek/')); ?>">Referencia-ügyfelek</a><a href="<?php echo esc_url(home_url('/minositesek/')); ?>">Minősítések</a><a href="<?php echo esc_url(home_url('/rolunk/')); ?>">Rólunk</a><a href="<?php echo esc_url(home_url('/blog/')); ?>">Blog</a><a href="<?php echo esc_url(home_url('/kapcsolat/')); ?>">Kapcsolat</a></div>
      </div>
      <div>
        <div class="flbl">INFORMÁCIÓ</div>
        <div class="fcol"><a href="<?php echo esc_url(home_url('/kivalasztasi-elvek/')); ?>">A listák kiválasztási elvei</a><a href="<?php echo esc_url(home_url('/aszf/')); ?>">ÁSZF</a><a href="<?php echo esc_url(home_url('/adatvedelem/')); ?>">Adatvédelmi tájékoztató</a></div>
      </div>
    </div>
    <div class="fbot"><span>© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span><span>Magyar · <a href="#" aria-disabled="true" title="Hamarosan">English</a></span></div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
