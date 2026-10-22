// Ügynökség adatlap (admin): telefonszám-formázás, e-mail ellenőrzés, keresős pipalisták (adatbázis-listák)
(function () {
  var box = document.querySelector('.kompasz-edit');
  if (!box) return;
  var langs = box.querySelector('.klangs');
  var CC = langs ? langs.getAttribute('data-cc') : '36';

  // = kompasz_phone_parse / kompasz_phone_format (inc/phone.php)
  function parse(raw) {
    var s = String(raw).trim();
    if (!s) return null;
    var plus = s[0] === '+', d = s.replace(/\D/g, '');
    if (!plus) {
      if (d.indexOf('00') === 0) d = d.slice(2);
      else if (CC === '36' && d.indexOf('06') === 0) d = '36' + d.slice(2);
      else if (d[0] === '0') d = CC + d.slice(1);
      else if (d.indexOf(CC) !== 0) d = CC + d;
    }
    if (d.indexOf('36') === 0) {
      var n = d.slice(2);
      if (!n) return null;
      if (n[0] === '1') return n.length === 8 ? ['36', '1', n.slice(1)] : null;
      var area = n.slice(0, 2), mobile = ['20', '30', '31', '50', '70'].indexOf(area) >= 0;
      return n.length === (mobile ? 9 : 8) && area[0] !== '0' ? ['36', area, n.slice(2)] : null;
    }
    if (d.indexOf('49') === 0) {
      var m = d.slice(2);
      if (m.length < 6 || m.length > 12 || m[0] === '0') return null;
      var len = /^1[5-7]/.test(m) ? 3 : (/^(30|40|69|89)/.test(m) ? 2 : 3);
      return ['49', m.slice(0, len), m.slice(len)];
    }
    if (d.length < 8 || d.length > 15) return null;
    var two = ['20','27','30','31','32','33','34','39','40','41','43','44','45','46','47','48','51','52','54','55','61','81','82','86','90','91'];
    var cl = (d[0] === '1' || d[0] === '7') ? 1 : (two.indexOf(d.slice(0, 2)) >= 0 ? 2 : 3);
    return [d.slice(0, cl), '', d.slice(cl)];
  }
  function format(raw) {
    var p = parse(raw);
    if (!p) return '';
    var rest = p[2];
    if (p[0] === '36') rest = rest.slice(0, 3) + ' ' + rest.slice(3);
    else if (p[0] !== '49') rest = rest.replace(/(\d{3})(?=\d{4})/g, '$1 ');
    return ('+' + p[0] + ' ' + (p[1] ? p[1] + ' ' : '') + rest).trim();
  }
  function mark(input, ok, msg) {
    input.style.borderColor = ok ? '' : '#d63638';
    var n = input.nextElementSibling;
    if (!n || !n.classList.contains('kerr')) { n = document.createElement('span'); n.className = 'kerr'; input.after(n); }
    n.textContent = ok ? '' : msg;
  }
  box.addEventListener('focusout', function (e) {
    var t = e.target;
    if (t.matches('[data-kompasz-phone]')) {
      if (!t.value.trim()) return mark(t, true);
      var f = format(t.value);
      if (f) { t.value = f; mark(t, true); } else mark(t, false, 'Hibás telefonszám. Helyes forma: +36 20 404 4280 vagy +36 1 234 5678');
    }
    if (t.matches('[data-kompasz-email]')) {
      t.value = t.value.trim();
      mark(t, !t.value || /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(t.value), 'Hibás e-mail cím');
    }
  });
  // Keresős pipalisták (adatbázis-listák): szűrés, darabszám, a sor mezője (telefonszám, link) csak bejelölve aktív
  function sync(pick) {
    var n = 0;
    pick.querySelectorAll('.kpick-row').forEach(function (row) {
      var i = row.querySelector('input[type=checkbox], input[type=radio]');
      var on = i && i.checked && i.value !== '0';
      row.classList.toggle('on', !!on);
      if (on) n++;
      var x = row.querySelector('.kpick-extra');
      if (x) x.disabled = !on;
    });
    var c = pick.querySelector('.kpick-count');
    if (c) c.textContent = n ? n + ' kiválasztva' : '';
  }
  box.querySelectorAll('.kpick').forEach(function (pick) {
    sync(pick);
    var q = pick.querySelector('.kpick-q'), none = pick.querySelector('.kpick-none');
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase(), shown = 0;
      pick.querySelectorAll('.kpick-row').forEach(function (row) {
        var hit = !v || row.textContent.toLowerCase().indexOf(v) >= 0;
        row.hidden = !hit;
        if (hit) shown++;
      });
      none.hidden = shown > 0;
    });
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    pick.addEventListener('change', function (e) {
      sync(pick);
      var x = e.target.closest('.kpick-row') && e.target.closest('.kpick-row').querySelector('.kpick-extra');
      if (x && e.target.checked && x.type === 'tel' && !x.value) x.focus();
    });
  });
  // Mentés előtt a hibás mezőkre figyelmeztet
  var form = document.getElementById('post');
  if (form) form.addEventListener('submit', function (e) {
    var bad = box.querySelector('.kerr:not(:empty)');
    if (bad && !confirm('Van hibás telefonszám vagy e-mail cím (pirossal jelölve). A hibás értéket a rendszer nem menti. Folytatod?')) { e.preventDefault(); bad.previousElementSibling.focus(); }
  });

})();
