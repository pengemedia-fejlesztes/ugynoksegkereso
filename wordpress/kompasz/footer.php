<footer class="ftr">
  <div class="wrap">
    <div class="fcols">
      <div class="fbrand">
        <a class="brand light" href="<?php echo esc_url(home_url('/')); ?>"><?php echo kompasz_brand_mark(28); ?><span class="bn"><?php bloginfo('name'); ?></span></a>
        <p><?php bloginfo('description'); ?></p>
        <?php if ($email = get_option('kompasz_email')): ?><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><?php endif; ?>
      </div>
      <div>
        <div class="flbl"><?php echo esc_html(kompasz__('TOPLISTÁK')); ?></div>
        <div class="fcats">
          <?php foreach (kompasz_categories() as $c): ?>
            <a href="<?php echo esc_url(get_term_link($c)); ?>"><span class="fi"><?php echo kompasz_cat_icon($c, 14); ?></span><?php echo esc_html($c->name); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div class="flbl"><?php echo esc_html(kompasz__('OLDALAK')); ?></div>
        <div class="fcol"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(kompasz__('Kezdőlap')); ?></a><a href="<?php echo esc_url(kompasz_page_url('cimkek')); ?>"><?php echo esc_html(kompasz__('Szolgáltatások')); ?></a><?php if ((int) wp_count_terms(['taxonomy' => 'ugyfel', 'hide_empty' => true]) > 0): ?><a href="<?php echo esc_url(kompasz_page_url('ugyfelek')); ?>"><?php echo esc_html(kompasz__('Referencia-ügyfelek')); ?></a><?php endif; ?><?php if ((int) wp_count_terms(['taxonomy' => 'minosites', 'hide_empty' => true]) > 0): ?><a href="<?php echo esc_url(kompasz_page_url('minositesek')); ?>"><?php echo esc_html(kompasz__('Minősítések')); ?></a><?php endif; ?><?php if ((int) wp_count_terms(['taxonomy' => 'iparag', 'hide_empty' => true]) > 0): ?><a href="<?php echo esc_url(kompasz_page_url('iparagak')); ?>"><?php echo esc_html(kompasz__('Iparágak')); ?></a><?php endif; ?><a href="<?php echo esc_url(kompasz_page_url('rolunk')); ?>"><?php echo esc_html(kompasz__('Rólunk')); ?></a><a href="<?php echo esc_url(kompasz_page_url('blog')); ?>">Blog</a><a href="<?php echo esc_url(kompasz_page_url('kapcsolat')); ?>"><?php echo esc_html(kompasz__('Kapcsolat')); ?></a></div>
      </div>
      <div>
        <div class="flbl"><?php echo esc_html(kompasz__('INFORMÁCIÓ')); ?></div>
        <div class="fcol"><a href="<?php echo esc_url(kompasz_page_url('kivalasztasi-elvek')); ?>"><?php echo esc_html(kompasz__('A listák kiválasztási elvei')); ?></a><a href="<?php echo esc_url(kompasz_page_url('aszf')); ?>"><?php echo esc_html(kompasz__('ÁSZF')); ?></a><a href="<?php echo esc_url(kompasz_page_url('adatvedelem')); ?>"><?php echo esc_html(kompasz__('Adatvédelmi tájékoztató')); ?></a><?php if (kompasz_lang() !== 'hu'): ?><a href="<?php echo esc_url(kompasz_page_url('impresszum')); ?>"><?php echo esc_html(kompasz__('Impresszum')); ?></a><?php endif; ?></div>
      </div>
    </div>
    <div class="fbot"><span>© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span><span><?php if (kompasz_lang() === 'de'): ?><a href="https://ugynoksegek.pmdev.hu/" hreflang="hu">Magyar</a> · Deutsch<?php else: ?>Magyar · <a href="https://de.ugynoksegek.pmdev.hu/" hreflang="de">Deutsch</a><?php endif; ?></span></div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
