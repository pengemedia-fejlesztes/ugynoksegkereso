(function () {
  var I = window.KOMPASZ_I18N || {};
  var t = function (k, def) { return I[k] || def; };
  var fmt = function (s, a, b) { return s.replace('%1$d', a).replace('%2$d', b).replace('%d', a); };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Hibás logó helyén a monogram marad
  $$('.logo img').forEach(function (img) {
    var hide = function () { img.style.display = 'none'; };
    if (img.complete && img.naturalWidth === 0) hide();
    img.addEventListener('error', hide);
  });

  // Kezdőlap: Legjobbra értékeltek fülek
  $$('.tabs [data-tab]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('.tabs [data-tab]').forEach(function (o) { o.setAttribute('aria-selected', o === b ? 'true' : 'false'); });
      $$('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== b.dataset.tab; });
      var l = $('[data-tablink]'); if (l) l.href = b.dataset.href;
    });
  });

  // Mobil: SEO-szöveg lenyitása
  $$('.seo-more').forEach(function (b) {
    var grid = b.previousElementSibling;
    grid.classList.add('clip');
    b.addEventListener('click', function () {
      var c = grid.classList.toggle('clip');
      b.textContent = c ? t('more', 'Tovább olvasom') : t('less', 'Kevesebb');
    });
  });

  // Toplista: szűrés, rendezés, lapozás
  var rowsBox = $('#rows');
  if (!rowsBox) return;
  var rows = $$('.row', rowsBox);
  var featured = rows.filter(function (r) { return r.dataset.featured; })[0];
  var plain = rows.filter(function (r) { return !r.dataset.featured; });
  var STEP = 10, shown = STEP, sort = 'pos';
  var lm = $('.loadmore'), bar = $('.bar i', lm), lmLabel = $('.lm-label', lm), lmBtn = $('.lm-btn', lm);
  var countEl = $('.rcount'), noresult = $('.noresult', rowsBox);

  function selected(key) { return $$('[data-filter="' + key + '"]:checked').map(function (i) { return i.value; }); }
  function matches(r, f) {
    if (f.tag.length && !f.tag.some(function (t) { return (' ' + r.dataset.tags + ' ').indexOf(' ' + t + ' ') !== -1; })) return false;
    if (f.city.length && f.city.indexOf(r.dataset.city) === -1) return false;
    if (f.team.length && f.team.indexOf(r.dataset.team) === -1) return false;
    if (f.rating.length) { var min = Math.min.apply(null, f.rating.map(parseFloat)); if (parseFloat(r.dataset.rating) < min) return false; }
    return true;
  }
  var cmp = {
    pos: function (a, b) { return a.dataset.pos - b.dataset.pos; },
    rating: function (a, b) { return (b.dataset.rating - a.dataset.rating) || (a.dataset.pos - b.dataset.pos); },
    name: function (a, b) { return a.dataset.name.localeCompare(b.dataset.name, 'hu'); }
  };

  function render() {
    var f = { tag: selected('tag'), city: selected('city'), team: selected('team'), rating: selected('rating') };
    var hits = plain.filter(function (r) { return matches(r, f); }).sort(cmp[sort]);
    // A kiemelt ügynökség helyezés szerinti rendezésnél a 3. hely után áll, egyébként a többi közé sorolódik
    if (featured && matches(featured, f)) {
      if (sort === 'pos') hits.splice(Math.min(3, hits.length), 0, featured);
      else { hits.push(featured); hits.sort(cmp[sort]); }
    }
    rows.forEach(function (r) { r.hidden = true; });
    var n = Math.min(shown + (featured ? 1 : 0), hits.length);
    hits.forEach(function (r, i) { rowsBox.insertBefore(r, noresult); if (i < n) r.hidden = false; });
    countEl.textContent = fmt(t('agencies', '%d ügynökség'), hits.length);
    noresult.hidden = hits.length > 0;
    if (n < hits.length) {
      lm.hidden = false;
      bar.style.width = Math.round(n / hits.length * 100) + '%';
      lmLabel.textContent = fmt(t('shown', '%1$d / %2$d megjelenítve'), n, hits.length);
      lmBtn.textContent = fmt(t('loadMore', 'További %d ügynökség'), Math.min(STEP, hits.length - n));
    } else lm.hidden = true;
  }

  $$('[data-filter]').forEach(function (i) { i.addEventListener('change', function () { shown = STEP; render(); }); });
  $$('.fclear').forEach(function (b) { b.addEventListener('click', function () { $$('[data-filter]').forEach(function (i) { i.checked = false; }); shown = STEP; render(); }); });
  $$('.sort [data-sort]').forEach(function (b) {
    b.addEventListener('click', function () {
      sort = b.dataset.sort;
      $$('.sort [data-sort]').forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
      render();
    });
  });
  if (lmBtn) lmBtn.addEventListener('click', function () { shown += STEP; render(); });
  var ft = $('.ftoggle'), panel = $('.filters');
  if (ft && panel) ft.addEventListener('click', function () { panel.classList.toggle('open'); });
  render();
})();

// Blog: széles asztali nézetben oldalsó, követő tartalomjegyzék a kártya alapján, az aktuális fejezet kiemelésével
(function () {
  var I = window.KOMPASZ_I18N || {};
  var t = function (k, def) { return I[k] || def; };
  var fmt = function (s, a, b) { return s.replace('%1$d', a).replace('%2$d', b).replace('%d', a); };
  var toc = document.getElementById('toc'), art = document.getElementById('cikk');
  if (!toc || !art) return;
  var links = toc.querySelectorAll('.toc-item > a');
  if (links.length < 3) return;
  var side = document.createElement('nav');
  side.className = 'sidetoc';
  side.setAttribute('aria-label', (window.KOMPASZ_I18N && KOMPASZ_I18N.toc) || 'Tartalomjegyzék');
  side.innerHTML = '<div class="sidetoc-title">Tartalom</div>';
  var targets = [];
  links.forEach(function (l) {
    var a = document.createElement('a');
    a.href = l.getAttribute('href');
    a.textContent = l.textContent.replace(/^\d+/, '').trim();
    side.appendChild(a);
    var t = document.getElementById(decodeURIComponent(a.hash.slice(1)));
    if (t) targets.push([t, a]);
  });
  document.body.appendChild(side);
  // Mobilon a kártyán belüli fejezetre kattintás után a kártya bezárul
  toc.querySelectorAll('.toc-body a').forEach(function (a) { a.addEventListener('click', function () { if (window.innerWidth < 1280) toc.removeAttribute('open'); }); });
  function onScroll() {
    var r = toc.getBoundingClientRect(), end = art.getBoundingClientRect();
    side.classList.toggle('is-visible', r.bottom < 0 && end.bottom > 120 + side.offsetHeight + 24 && r.left > side.offsetLeft + side.offsetWidth + 16);
    var cur = null;
    targets.forEach(function (p) { if (p[0].getBoundingClientRect().top < 160) cur = p[1]; });
    targets.forEach(function (p) { p[1].classList.toggle('is-active', p[1] === cur); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();
})();

// Ügynökség helye a térképen (MapLibre GL JS + OpenFreeMap): a könyvtár csak akkor töltődik be, ha a térkép a képernyő közelébe ér
(function () {
  var el = document.querySelector('.kmap-pub');
  if (!el) return;
  var cfg = JSON.parse(el.getAttribute('data-map'));
  function init() {
    var map = new maplibregl.Map({ container: el, style: cfg.style, center: [cfg.lng, cfg.lat], zoom: cfg.zoom, cooperativeGestures: true, attributionControl: { compact: true } });
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }));
    new maplibregl.Marker({ color: '#0E6B4F' }).setLngLat([cfg.lng, cfg.lat]).setPopup(new maplibregl.Popup({ offset: 24 }).setText(cfg.name)).addTo(map);
  }
  function load() {
    if (window.maplibregl) return init();
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.css; document.head.appendChild(l);
    var s = document.createElement('script'); s.src = cfg.js; s.async = true; s.onload = init; document.head.appendChild(s);
  }
  if (!('IntersectionObserver' in window)) return load();
  var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { io.disconnect(); load(); } }, { rootMargin: '300px' });
  io.observe(el);
})();
