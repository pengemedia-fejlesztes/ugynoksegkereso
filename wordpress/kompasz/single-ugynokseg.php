<?php
// Ügynökség profiloldal (4c)
get_header();
$a = kompasz_agency(get_queried_object_id());
[$cat, $list, $pos] = kompasz_primary_context($a);
$featured = $cat ? kompasz_is_featured_in($cat) : kompasz_featured();
$is_featured = $featured && $featured['id'] === $a['id'];
$prev = $pos > 1 ? $list[$pos - 2] : null;
$next = $pos && $pos < count($list) ? $list[$pos] : null;
$similar = $cat ? kompasz_similar($a, $list, ($featured && !$is_featured) ? 5 : 6) : [];
$around = $list ? array_slice($list, max(0, min($pos - 3, count($list) - 5)), 5, true) : [];
?>
<main>
  <div class="wrap profile-top">
    <?php echo kompasz_crumbs(array_filter([['Kezdőlap', home_url('/')], $cat ? [$cat->name, get_term_link($cat)] : null, [$a['name'], null]])); ?>
    <div class="pn">
      <?php if ($prev): ?><a href="<?php echo esc_url($prev['link']); ?>" rel="prev">← <?php echo esc_html($prev['name']); ?></a><?php endif; ?>
      <?php if ($next): ?><a href="<?php echo esc_url($next['link']); ?>" rel="next"><?php echo esc_html($next['name']); ?> →</a><?php endif; ?>
    </div>
  </div>
  <div class="wrap profile">
    <div class="pmain">
      <section class="pcard-hero">
        <?php echo kompasz_logo($a, 120); ?>
        <div class="ph-body">
          <div class="ph-kicker"><?php
            if ($is_featured) echo '<span class="fbadge">KIEMELT ÜGYNÖKSÉG</span>';
            elseif ($cat && $pos) echo esc_html(sprintf('#%02d · %s %s', $pos, $cat->name, wp_date('Y')));
          ?></div>
          <h1><?php echo esc_html($a['name']); ?></h1>
          <?php echo kompasz_rating_html($a, true); ?>
          <div class="ph-act"><?php echo kompasz_contact_btn($a, 'btn lg'); ?><?php if ($a['domain']) printf('<a class="ph-web" href="%s" target="_blank" rel="nofollow noopener">%s</a>', esc_url($a['website']), esc_html($a['domain'])); ?></div>
        </div>
      </section>

      <section class="pbox">
        <h2>Bemutatkozás</h2>
        <div class="pdesc"><?php echo wpautop(esc_html($a['desc'])); ?></div>
        <div class="facts">
          <?php foreach (['HELYSZÍN' => $a['city'], 'CSAPAT' => $a['team'], 'ALAPÍTVA' => $a['founded']] as $l => $v): if (!$v) continue; ?>
            <div class="fact"><span><?php echo esc_html($l); ?></span><b><?php echo esc_html($v); ?></b></div>
          <?php endforeach; ?>
        </div>
        <?php if ($a['services']): ?>
          <h3>Szolgáltatások</h3>
          <div class="svc"><?php foreach ($a['services'] as $s) echo '<span>' . esc_html($s) . '</span>'; ?></div>
        <?php endif; ?>
        <?php if ($a['tags']): ?>
          <h3>Szolgáltatás szerinti listák</h3>
          <div class="svc links"><?php foreach ($a['tags'] as $t) printf('<a href="%s">%s →</a>', esc_url(get_term_link($t)), esc_html($t->name)); ?></div>
        <?php endif; ?>
        <?php if ($a['cats']): ?>
          <h3>Ezeken a toplistákon szerepel</h3>
          <div class="svc links pills"><?php foreach ($a['cats'] as $c) printf('<a href="%s">%s%s →</a>', esc_url(get_term_link($c)), kompasz_cat_icon($c, 15), esc_html($c->name)); ?></div>
        <?php endif; ?>
      </section>

      <?php if ($similar || ($featured && !$is_featured)): ?>
      <section class="similar">
        <div class="sec-h"><h2>Hasonló ügynökségek</h2><span class="note">azonos szolgáltatások alapján</span></div>
        <div class="sgrid">
          <?php if ($featured && !$is_featured): ?>
            <a class="scard featured" href="<?php echo esc_url($featured['link']); ?>"><?php echo kompasz_logo($featured, 52); ?><span><span class="sk fbadge">KIEMELT ÜGYNÖKSÉG</span><span class="sn"><?php echo esc_html($featured['name']); ?></span><span class="smeta"><?php echo esc_html(trim(($featured['rating'] ? kompasz_num($featured['rating']) . '/5 · ' : '') . implode(', ', array_slice(wp_list_pluck($featured['tags'], 'name'), 0, 2)), ' ·')); ?></span></span></a>
          <?php endif; ?>
          <?php foreach ($similar as $s): ?>
            <a class="scard" href="<?php echo esc_url($s['link']); ?>"><?php echo kompasz_logo($s, 52); ?><span><span class="sk">#<?php echo (int) $s['pos']; ?> A LISTÁN</span><span class="sn"><?php echo esc_html($s['name']); ?></span><span class="smeta"><?php echo esc_html(trim(($s['rating'] ? kompasz_num($s['rating']) . '/5 · ' : '') . implode(', ', array_slice(wp_list_pluck($s['tags'], 'name'), 0, 2)), ' ·')); ?></span></span></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <aside class="pside">
      <?php if ($featured && !$is_featured) echo '<div class="fside">' . kompasz_featured_strip($featured, true) . '</div>'; ?>
      <?php if ($around): ?>
        <div class="around">
          <div class="flabel">A LISTÁN KÖRÜLÖTTE</div>
          <?php foreach ($around as $i => $x) printf('<a href="%s"%s><span class="ap">%02d</span><span class="an">%s</span><span class="ar">%s</span></a>', esc_url($x['link']), $x['id'] === $a['id'] ? ' class="me"' : '', $i + 1, esc_html($x['name']), $x['rating'] ? esc_html(kompasz_num($x['rating'])) : ''); ?>
          <a class="full" href="<?php echo esc_url(get_term_link($cat)); ?>">Teljes toplista →</a>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php get_footer();
