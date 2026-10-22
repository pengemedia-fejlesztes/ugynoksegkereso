// Ügynökség adatlap (admin): helyszín a térképen – MapLibre GL JS + OpenFreeMap, címkeresés: Nominatim (gombnyomásra, nem gépelés közben)
(function () {
  var box = document.querySelector('.kmap-edit');
  if (!box) return;
  var cfg = JSON.parse(box.getAttribute('data-config'));
  var el = box.querySelector('.kmap'), q = box.querySelector('.kmap-q'), list = box.querySelector('.kmap-results'), status = box.querySelector('.kmap-status');
  var inLat = box.querySelector('[name="kompasz_lat"]'), inLng = box.querySelector('[name="kompasz_lng"]'), inGeo = box.querySelector('[name="kompasz_geo"]');
  var map, marker;

  function load(cb) {
    if (window.maplibregl) return cb();
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.css; document.head.appendChild(l);
    var s = document.createElement('script'); s.src = cfg.js; s.onload = cb; document.head.appendChild(s);
  }
  function set(lng, lat, geo, label) {
    inLat.value = lat.toFixed(6); inLng.value = lng.toFixed(6); inGeo.value = geo;
    if (!marker) {
      marker = new maplibregl.Marker({ draggable: true, color: '#0E6B4F' }).setLngLat([lng, lat]).addTo(map);
      marker.on('dragend', function () { var p = marker.getLngLat(); set(p.lng, p.lat, 'manual'); });
    } else marker.setLngLat([lng, lat]);
    status.textContent = 'Pozíció: ' + inLat.value + ', ' + inLng.value + (label ? ' – ' + label : '') + '. Mentéskor kerül az adatlapra.';
  }
  load(function () {
    var has = inLat.value !== '' && inLng.value !== '';
    map = new maplibregl.Map({ container: el, style: cfg.style, center: has ? [+inLng.value, +inLat.value] : cfg.center, zoom: has ? 14 : cfg.zoom, attributionControl: { compact: true } });
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }));
    if (has) map.on('load', function () { set(+inLng.value, +inLat.value, inGeo.value || 'manual'); });
    map.on('click', function (e) { set(e.lngLat.lng, e.lngLat.lat, 'manual', 'kézzel kijelölve'); });
  });

  // Nominatim: legfeljebb 1 kérés másodpercenként, gépelés közben nem (csak a mező elhagyásakor / gombnyomásra)
  function nominatim(params) {
    var u = cfg.nominatim + '?format=jsonv2&limit=5&countrycodes=' + encodeURIComponent(cfg.country) + '&accept-language=' + encodeURIComponent(cfg.lang);
    Object.keys(params).forEach(function (k) { if (params[k]) u += '&' + k + '=' + encodeURIComponent(params[k]); });
    return fetch(u, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
  }
  function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function picked(tax) { var i = document.querySelector('.kpick[data-tax="' + tax + '"] input:checked'); return i && i.value !== '0' ? i.closest('.kpick-row').querySelector('.kpick-name').textContent.trim() : ''; }
  function parts() {
    var a = document.getElementById('kompasz_address'), raw = (a ? a.value : '').trim(), city = picked('varos'), county = picked('megye');
    var pc = (raw.match(/\b\d{4,5}\b/) || [''])[0];
    var street = raw.replace(pc, '');
    if (city) street = street.replace(new RegExp(city.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), '');
    street = street.replace(/^[\s,]+|[\s,]+$/g, '').replace(/\s*,\s*,/g, ',');
    return { raw: raw, street: street, city: city, county: county, pc: pc };
  }
  // Próbák sorban: teljes cím → utca + város → irányítószám + város → város; az első találat nyer
  function locate(free) {
    var p = parts(), tries = [];
    if (free) tries.push({ q: free });
    if (p.street && p.city) tries.push({ q: [p.street, [p.pc, p.city].filter(Boolean).join(' ')].join(', ') }, { street: p.street, city: p.city, postalcode: p.pc });
    if (p.street && !p.city && p.raw) tries.push({ q: p.raw });
    if (p.pc) tries.push({ postalcode: p.pc, city: p.city });
    if (p.city) tries.push({ city: p.city, county: p.county });
    var i = 0;
    function next() {
      if (i >= tries.length) return Promise.resolve([]);
      var t = tries[i++];
      return (i > 1 ? wait(1100) : Promise.resolve()).then(function () { return nominatim(t); }).then(function (res) { return res.length ? res : next(); });
    }
    return next();
  }
  function exact(h) { return ['house', 'building', 'office', 'commercial', 'amenity'].indexOf(h.addresstype || h.category) >= 0 || h.place_rank >= 26; }
  function jump(h) {
    var lng = +h.lon, lat = +h.lat, ex = exact(h);
    set(lng, lat, ex ? 'address' : 'city', h.display_name + (ex ? '' : ' (nem pontos: húzd a jelölőt a helyére)'));
    map.flyTo({ center: [lng, lat], zoom: ex ? 16 : (h.place_rank >= 20 ? 15 : 12) });
  }
  function search() {
    list.hidden = false; list.innerHTML = '<li>Keresés…</li>';
    locate(q.value.trim()).then(function (res) {
      list.innerHTML = '';
      if (!res.length) { list.innerHTML = '<li>Nincs találat. Ellenőrizd az utca nevét, vagy kattints a térképre.</li>'; return; }
      jump(res[0]);
      if (res.length === 1) { list.hidden = true; return; }
      res.forEach(function (h) {
        var li = document.createElement('li'), b = document.createElement('button');
        b.type = 'button'; b.className = 'button-link'; b.textContent = h.display_name;
        b.addEventListener('click', function () { jump(h); list.hidden = true; });
        li.appendChild(b); list.appendChild(li);
      });
    }).catch(function () { list.innerHTML = '<li>A keresés most nem érhető el. Kattints a térképre a pozícióhoz.</li>'; });
  }
  box.querySelector('.kmap-search').addEventListener('click', search);
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
  // A cím, a város vagy a megye megadásakor a térkép magától odaugrik (a kereső mezőt is kitölti)
  var timer;
  function auto() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var p = parts();
      q.value = [p.raw, p.city && p.raw.indexOf(p.city) < 0 ? p.city : ''].filter(Boolean).join(', ');
      if (!map || (!p.raw && !p.city)) return;
      list.hidden = true;
      locate('').then(function (res) { if (res.length) jump(res[0]); });
    }, 700);
  }
  var addr = document.getElementById('kompasz_address');
  if (addr) { addr.addEventListener('change', auto); addr.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); auto(); } }); }
  document.querySelectorAll('.kpick[data-tax="varos"], .kpick[data-tax="megye"]').forEach(function (pk) { pk.addEventListener('change', auto); });
  box.querySelector('.kmap-clear').addEventListener('click', function () {
    inLat.value = inLng.value = inGeo.value = '';
    if (marker) { marker.remove(); marker = null; }
    status.textContent = 'A pozíció mentéskor törlődik; a nyilvános adatlapon nem lesz térkép.';
  });
})();
