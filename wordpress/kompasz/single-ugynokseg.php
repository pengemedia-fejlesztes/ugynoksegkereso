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
    <?php echo kompasz_crumbs(array_filter([[kompasz__('Kezdőlap'), home_url('/')], $cat ? [kompasz_toplist_name($cat), get_term_link($cat)] : null, [$a['name'], null]])); ?>
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
            if ($is_featured) echo '<span class="fbadge">' . esc_html(kompasz__('KIEMELT ÜGYNÖKSÉG')) . '</span>';
            elseif ($cat && $pos) echo esc_html(sprintf('#%02d · %s %s', $pos, kompasz_toplist_name($cat), wp_date('Y')));
          ?></div>
          <h1><?php echo esc_html($a['name']); ?></h1>
          <?php echo kompasz_rating_html($a, true); ?>
          <?php if ($a['intro']) printf('<p class="ph-intro">%s</p>', esc_html($a['intro'])); ?>
          <div class="ph-act"><?php echo kompasz_contact_btn($a, 'btn lg'); ?><?php if ($a['domain'] && function_exists('kompasz_agency_has') && kompasz_agency_has($a['id'], 'website')) printf('<a class="ph-web" href="%s" target="_blank" rel="nofollow noopener">%s</a>', esc_url($a['website']), esc_html($a['domain'])); ?></div>
        </div>
      </section>

      <section class="pbox">
        <h2><?php echo esc_html(kompasz__('Bemutatkozás')); ?></h2>
        <div class="pdesc"><?php echo wpautop(esc_html($a['desc'])); ?></div>
        <div class="facts" id="kapcsolat">
          <?php
          $place = trim(implode(', ', array_unique(array_filter([$a['city'], $a['county'] !== $a['city'] ? $a['county'] : '']))));
          $has = function ($f) use ($a) { return function_exists('kompasz_agency_has') && kompasz_agency_has($a['id'], $f); };
          $facts = ['HELYSZÍN' => esc_html($place), 'CSAPAT' => esc_html($a['team']), 'ALAPÍTVA' => esc_html($a['founded']), 'ÁRSZINT' => esc_html($a['price_min']),
              'ÁRAZÁS' => esc_html($a['price_model']), 'MUNKAVÉGZÉS' => esc_html($a['work_mode'])];
          // Telefonszám nyelvenként; ha csak egy van, nyelv nélkül
          if ($has('phone') && $a['phones']) {
              $multi = count($a['phones']) > 1;
              $facts['TELEFON'] = implode('<br>', array_map(function ($lang, $ph) use ($multi) {
                  return sprintf('<a href="%s">%s</a>%s', esc_attr(kompasz_phone_href($ph)), esc_html($ph), $multi ? ' <small>(' . esc_html($lang) . ')</small>' : '');
              }, array_keys($a['phones']), $a['phones']));
          }
          if ($has('email') && $a['email']) $facts['E-MAIL'] = sprintf('<a href="mailto:%1$s">%1$s</a>', esc_html($a['email']));
          foreach ($facts as $l => $v): if ($v === '') continue; ?>
            <div class="fact"><span><?php echo esc_html(kompasz__($l)); ?></span><b><?php echo $v; ?></b></div>
          <?php endforeach; ?>
        </div>
        <?php
        // Szolgáltatások – egy lista: a toplistás szolgáltatás a toplistára visz, és mutatja a helyezést; a többi a szolgáltatás oldalára
        if ($a['tags']):
          $svc = $a['tags'];
          usort($svc, function ($x, $y) { return [kompasz_is_toplist($y), $x->name] <=> [kompasz_is_toplist($x), $y->name]; }); ?>
          <h3><?php echo esc_html(kompasz__('Szolgáltatások')); ?></h3>
          <div class="svc links pills"><?php foreach ($svc as $t) {
            if (kompasz_is_toplist($t)) {
              $p = 0; foreach (kompasz_list($t) as $x) if ($x['id'] === $a['id']) { $p = $x['pos']; break; }
              printf('<a href="%s" title="%s">%s%s%s →</a>', esc_url(get_term_link($t)), esc_attr(kompasz_toplist_name($t)), kompasz_cat_icon($t, 15), esc_html($t->name), $p ? ' <small>#' . (int) $p . '</small>' : '');
            } else printf('<a href="%s">%s →</a>', esc_url(get_term_link($t)), esc_html($t->name));
          } ?></div>
        <?php endif; ?>
        <?php
        // Minősítés, iparág: saját oldala akkor van, ha legalább két ügynökség megnevezi
        foreach (['Minősítések' => $a['certs'], 'Iparági tapasztalat' => $a['industries']] as $h => $items): if (!$items) continue; ?>
          <h3><?php echo esc_html(kompasz__($h)); ?></h3>
          <div class="svc links"><?php foreach ($items as $t) echo $t->count >= 2 ? sprintf('<a href="%s">%s →</a>', esc_url(get_term_link($t)), esc_html($t->name)) : '<span>' . esc_html($t->name) . '</span>'; ?></div>
        <?php endforeach; ?>
        <?php if ($a['languages']): ?>
          <h3><?php echo esc_html(kompasz__('Munkanyelvek')); ?></h3>
          <div class="svc"><?php foreach ($a['languages'] as $l) echo '<span>' . esc_html($l) . '</span>'; ?></div>
        <?php endif; ?>
        <?php if ($a['references']): ?>
          <h3><?php echo esc_html(kompasz__('Referencia-ügyfelek')); ?></h3>
          <div class="svc links"><?php foreach ($a['references'] as $r) {
            if ($r['term']->count >= 2) printf('<a href="%s" title="%s">%s →</a>', esc_url(get_term_link($r['term'])), esc_attr(kompasz__('Akiknek még más ügynökség is dolgozott')), esc_html($r['name']));
            elseif ($r['url'] && wp_http_validate_url($r['url'])) printf('<a href="%s" target="_blank" rel="nofollow noopener">%s ↗</a>', esc_url($r['url']), esc_html($r['name']));
            else echo '<span>' . esc_html($r['name']) . '</span>';
          } ?></div>
        <?php endif; ?>
      </section>

      <?php $map = function_exists('kompasz_map_html') ? kompasz_map_html($a) : ''; if ($map): ?>
      <section class="pbox" id="helyszin">
        <h2><?php echo esc_html(kompasz__('Helyszín')); ?></h2>
        <?php echo $map; ?>
      </section>
      <?php endif; ?>

      <?php $cf7 = function_exists('kompasz_cf7_html') ? kompasz_cf7_html($a['id']) : ''; if ($cf7): ?>
      <section class="pbox pform" id="ajanlatkeres">
        <h2><?php echo esc_html(sprintf(kompasz__('Ajánlatkérés: %s'), $a['name'])); ?></h2>
        <p class="note"><?php echo esc_html(kompasz__('Írd le röviden, miben kérsz segítséget – az ügynökség a megadott elérhetőségen keres.')); ?></p>
        <?php echo $cf7; ?>
      </section>
      <?php endif; ?>

      <?php if ($similar || ($featured && !$is_featured)): ?>
      <section class="similar">
        <div class="sec-h"><h2><?php echo esc_html(kompasz__('Hasonló ügynökségek')); ?></h2><span class="note"><?php echo esc_html(kompasz__('azonos szolgáltatások alapján')); ?></span></div>
        <div class="sgrid">
          <?php if ($featured && !$is_featured): ?>
            <a class="scard featured" href="<?php echo esc_url($featured['link']); ?>"><?php echo kompasz_logo($featured, 52); ?><span><span class="sk fbadge"><?php echo esc_html(kompasz__('KIEMELT ÜGYNÖKSÉG')); ?></span><span class="sn"><?php echo esc_html($featured['name']); ?></span><span class="smeta"><?php echo esc_html(trim(($featured['rating'] ? kompasz_num($featured['rating']) . '/5 · ' : '') . implode(', ', array_slice(wp_list_pluck($featured['tags'], 'name'), 0, 2)), ' ·')); ?></span></span></a>
          <?php endif; ?>
          <?php foreach ($similar as $s): ?>
            <a class="scard" href="<?php echo esc_url($s['link']); ?>"><?php echo kompasz_logo($s, 52); ?><span><span class="sk">#<?php echo (int) $s['pos']; ?> <?php echo esc_html(kompasz__('A LISTÁN')); ?></span><span class="sn"><?php echo esc_html($s['name']); ?></span><span class="smeta"><?php echo esc_html(trim(($s['rating'] ? kompasz_num($s['rating']) . '/5 · ' : '') . implode(', ', array_slice(wp_list_pluck($s['tags'], 'name'), 0, 2)), ' ·')); ?></span></span></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <aside class="pside">
      <?php if ($featured && !$is_featured) echo '<div class="fside">' . kompasz_featured_strip($featured, true) . '</div>'; ?>
      <?php if ($around): ?>
        <div class="around">
          <div class="flabel"><?php echo esc_html(kompasz__('A LISTÁN KÖRÜLÖTTE')); ?></div>
          <?php foreach ($around as $i => $x) printf('<a href="%s"%s><span class="ap">%02d</span><span class="an">%s</span><span class="ar">%s</span></a>', esc_url($x['link']), $x['id'] === $a['id'] ? ' class="me"' : '', $i + 1, esc_html($x['name']), $x['rating'] ? esc_html(kompasz_num($x['rating'])) : ''); ?>
          <a class="full" href="<?php echo esc_url(get_term_link($cat)); ?>"><?php echo esc_html(kompasz__('Teljes toplista →')); ?></a>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php get_footer();
