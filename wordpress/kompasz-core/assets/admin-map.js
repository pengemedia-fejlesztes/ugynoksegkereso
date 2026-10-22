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

  function search() {
    var v = q.value.trim();
    if (!v) return;
    list.hidden = false; list.innerHTML = '<li>Keresés…</li>';
    var url = cfg.nominatim + '?format=jsonv2&limit=5&addressdetails=0&countrycodes=' + encodeURIComponent(cfg.country) + '&accept-language=' + encodeURIComponent(cfg.lang) + '&q=' + encodeURIComponent(v);
    fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (res) {
      list.innerHTML = '';
      if (!res.length) { list.innerHTML = '<li>Nincs találat. Próbáld rövidebben (pl. csak utca és város), vagy kattints a térképre.</li>'; return; }
      res.forEach(function (h) {
        var li = document.createElement('li'), b = document.createElement('button');
        b.type = 'button'; b.className = 'button-link'; b.textContent = h.display_name;
        b.addEventListener('click', function () {
          var lng = +h.lon, lat = +h.lat, exact = ['house', 'building', 'office', 'commercial', 'amenity'].indexOf(h.addresstype || h.category) >= 0 || h.place_rank >= 26;
          set(lng, lat, exact ? 'address' : 'city', h.display_name);
          map.flyTo({ center: [lng, lat], zoom: exact ? 16 : 13 });
          list.hidden = true;
        });
        li.appendChild(b); list.appendChild(li);
      });
    }).catch(function () { list.innerHTML = '<li>A keresés most nem érhető el. Kattints a térképre a pozícióhoz.</li>'; });
  }
  box.querySelector('.kmap-search').addEventListener('click', search);
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
  box.querySelector('.kmap-clear').addEventListener('click', function () {
    inLat.value = inLng.value = inGeo.value = '';
    if (marker) { marker.remove(); marker = null; }
    status.textContent = 'A pozíció mentéskor törlődik; a nyilvános adatlapon nem lesz térkép.';
  });
  // A cím és a város mezőből a keresőbe
  ['kompasz_city', 'kompasz_address'].forEach(function (n) {
    var f = document.getElementById(n);
    if (f) f.addEventListener('change', function () {
      var c = document.getElementById('kompasz_city'), a = document.getElementById('kompasz_address');
      q.value = [a && a.value, c && c.value].filter(Boolean).join(', ');
    });
  });
})();
