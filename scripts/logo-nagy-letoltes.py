#!/usr/bin/env python3
# Logók újraletöltése nagyobb felbontásban: python3 scripts/logo-nagy-letoltes.py
# A src/logos.json forrás-URL-jéből képzett nagyobb változatokat próbálja (WordPress méret-utótag
# nélküli eredeti, elementor-thumb helyett az eredeti, 512-es manifest-ikon, apple-touch-icon),
# és ha a letöltött kép ugyanaz a logó nagyobb méretben, lecseréli vele a src/logos/ alatti fájlt.
import io, json, re, sys, urllib.request, urllib.parse
from concurrent.futures import ThreadPoolExecutor
from PIL import Image

UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36'
MAN = 'src/logos.json'
manifest = json.load(open(MAN))


def get(url, limit=8_000_000):
    try:
        req = urllib.request.Request(url, headers={'user-agent': UA, 'accept': '*/*'})
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.geturl(), r.read(limit)
    except Exception:
        return None, None


def size(buf):
    try:
        im = Image.open(io.BytesIO(buf))
        im.load()
        return im.size
    except Exception:
        return None


def variants(src, website):
    out = []
    u = urllib.parse.urlsplit(src)
    path = u.path
    # WordPress méret-utótag: logo-300x120.png -> logo.png; cropped-logo-192x192.jpg -> cropped-logo.jpg
    p2 = re.sub(r'-\d{2,4}x\d{2,4}(\.[a-z]{3,4})$', r'\1', path, flags=re.I)
    if p2 != path:
        out.append(urllib.parse.urlunsplit((u.scheme, u.netloc, p2, '', '')))
    # -scaled, -e123456789 (WP szerkesztett kép)
    p3 = re.sub(r'-(scaled|e\d{9,})(\.[a-z]{3,4})$', r'\2', p2, flags=re.I)
    if p3 != p2:
        out.append(urllib.parse.urlunsplit((u.scheme, u.netloc, p3, '', '')))
    # lekérdezés-paraméteres átméretezés (?w=, ?resize=, ?width=)
    if u.query and re.search(r'(^|&)(w|h|width|height|resize|fit|size)=', u.query):
        out.append(urllib.parse.urlunsplit((u.scheme, u.netloc, path, '', '')))
    return out


def icon_variants(website, html):
    out = []
    base = website if website.startswith('http') else 'https://' + website
    m = re.search(r'<link[^>]+rel=["\']manifest["\'][^>]*>', html or '', re.I)
    if m:
        h = re.search(r'href=["\']([^"\']+)', m.group(0))
        if h:
            _, buf = get(urllib.parse.urljoin(base, h.group(1)), 200_000)
            try:
                icons = json.loads(buf or b'{}').get('icons', [])
                icons.sort(key=lambda i: -max([int(x) for x in re.findall(r'\d+', i.get('sizes', '0'))] or [0]))
                out += [urllib.parse.urljoin(base, i['src']) for i in icons[:2] if i.get('src')]
            except Exception:
                pass
    for tag in re.findall(r'<link[^>]+>', html or '', re.I):
        if re.search(r'rel=["\'][^"\']*apple-touch-icon', tag, re.I):
            h = re.search(r'href=["\']([^"\']+)', tag)
            if h:
                out.append(urllib.parse.urljoin(base, h.group(1)))
    out.append(urllib.parse.urljoin(base, '/apple-touch-icon.png'))
    return out


def work(item):
    slug, v = item
    f, src = v.get('file'), v.get('src')
    if not f or not src or f.endswith('.svg'):
        return slug, 'kihagyva', None
    try:
        cur = Image.open('src/logos/' + f).size
    except Exception:
        cur = (0, 0)
    cands = variants(src, v.get('website', ''))
    if v.get('via') in ('icon', 'apple-touch-icon'):
        site = '{0.scheme}://{0.netloc}/'.format(urllib.parse.urlsplit(src))
        _, html = get(site, 600_000)
        cands += icon_variants(site, (html or b'').decode('utf8', 'ignore'))
    best = None
    for c in dict.fromkeys(cands):
        if c == src:
            continue
        url, buf = get(c)
        s = buf and size(buf)
        # ugyanaz a logó: azonos képarány (±4%) és nagyobb
        if s and max(s) > max(cur) and abs(s[0] / s[1] - cur[0] / max(cur[1], 1)) <= 0.04 * (cur[0] / max(cur[1], 1)) + 0.02:
            if not best or max(s) > max(best[1]):
                best = (url, s, buf)
    if not best:
        return slug, 'marad', cur
    url, s, buf = best
    fmt = Image.open(io.BytesIO(buf)).format.lower()
    ext = {'jpeg': 'jpg', 'png': 'png', 'webp': 'webp', 'gif': 'gif'}.get(fmt, 'png')
    nf = re.sub(r'\.[a-z]+$', '', f) + '.' + ext
    open('src/logos/' + nf, 'wb').write(buf)
    return slug, 'nagyobb', (cur, s, nf, url)


with ThreadPoolExecutor(8) as ex:
    res = list(ex.map(work, manifest.items()))

stat = {}
for slug, st, info in res:
    stat[st] = stat.get(st, 0) + 1
    if st == 'nagyobb':
        cur, s, nf, url = info
        old = manifest[slug]['file']
        if nf != old:
            import os
            os.remove('src/logos/' + old)
        manifest[slug].update(file=nf, src=url)
        print(f'{slug}: {cur[0]}x{cur[1]} -> {s[0]}x{s[1]}')
json.dump(manifest, open(MAN, 'w'), ensure_ascii=False, indent=1)
open(MAN, 'a').write('\n')
print(stat, file=sys.stderr)
