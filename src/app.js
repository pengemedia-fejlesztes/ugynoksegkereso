(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Logó hiba esetén a monogram marad látható
  $$('.logo img').forEach(function (img) {
    var hide = function () { img.style.display = 'none'; };
    if (img.complete && img.naturalWidth === 0) hide();
    img.addEventListener('error', hide);
  });

  // Ügynökségek lenyíló menü
  var megaBtn = $('#mega-btn'), mega = $('#mega');
  if (megaBtn && mega) {
    var setMega = function (open) { mega.classList.toggle('open', open); megaBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    var t;
    megaBtn.addEventListener('click', function (e) { e.stopPropagation(); setMega(!mega.classList.contains('open')); });
    megaBtn.addEventListener('mouseenter', function () { clearTimeout(t); setMega(true); });
    mega.addEventListener('mouseenter', function () { clearTimeout(t); });
    [megaBtn, mega].forEach(function (el) { el.addEventListener('mouseleave', function () { t = setTimeout(function () { setMega(false); }, 180); }); });
    document.addEventListener('click', function (e) { if (!mega.contains(e.target)) setMega(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMega(false); });
  }

  // Mobil menü
  var burger = $('#burger'), mnav = $('#mnav');
  if (burger && mnav) burger.addEventListener('click', function () {
    var open = !mnav.classList.contains('open');
    mnav.classList.toggle('open', open);
    burger.textContent = open ? '×' : '☰';
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Kezdőlap: kategóriaválasztó
  var go = $('#picker-go');
  $$('.chip').forEach(function (c) {
    c.addEventListener('click', function () {
      $$('.chip').forEach(function (o) { o.setAttribute('aria-pressed', o === c ? 'true' : 'false'); });
      go.href = c.dataset.href;
      go.textContent = c.dataset.name + ': toplista megnyitása →';
    });
  });

  // Mobil SEO-szöveg lenyitása
  $$('.seo-more').forEach(function (b) {
    var grid = b.previousElementSibling;
    b.addEventListener('click', function () {
      var clipped = grid.classList.toggle('clip');
      b.textContent = clipped ? 'Tovább olvasom' : 'Kevesebb';
      b.setAttribute('aria-expanded', clipped ? 'false' : 'true');
    });
  });

  // Toplista: keresés, lapozás, nézetváltás, bemutatkozás kinyitása
  var list = $('#list');
  if (!list) return;
  var rows = $$('.row', list), STEP = 10, shown = STEP;
  var q = $('#q'), lm = $('#loadmore'), bar = $('#bar'), lmLabel = $('#lm-label'), lmBtn = $('#lm-btn');
  var norm = function (s) { return s.toLowerCase(); };
  function render() {
    var term = norm(q.value.trim());
    var hits = rows.filter(function (r) { return !term || r.dataset.s.indexOf(term) !== -1; });
    rows.forEach(function (r) { r.hidden = true; });
    var n = Math.min(shown, hits.length);
    hits.slice(0, n).forEach(function (r) { r.hidden = false; });
    if (n < hits.length) {
      lm.hidden = false;
      bar.style.width = Math.round(n / Math.max(hits.length, 1) * 100) + '%';
      lmLabel.textContent = n + ' / ' + hits.length + ' megjelenítve';
      lmBtn.textContent = 'További ' + Math.min(STEP, hits.length - n) + ' ügynökség (' + (n + 1) + '–' + Math.min(n + STEP, hits.length) + ')';
    } else lm.hidden = true;
    $('#noresult').hidden = hits.length > 0;
  }
  q.addEventListener('input', function () { shown = STEP; render(); });
  lmBtn.addEventListener('click', function () { shown += STEP; render(); });
  $$('.seg button').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('.seg button').forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
      list.classList.toggle('compact', b.dataset.mode === 'compact');
    });
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest('.tog'); if (!b) return;
    var r = b.closest('.row'), exp = r.classList.toggle('exp');
    b.textContent = exp ? 'Kevesebb' : 'Teljes bemutatkozás';
    b.setAttribute('aria-expanded', exp ? 'true' : 'false');
  });
  render();
})();
