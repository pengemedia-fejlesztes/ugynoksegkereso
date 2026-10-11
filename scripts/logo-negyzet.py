#!/usr/bin/env python3
# Logók négyzetes, keretkitöltő változata: python3 scripts/logo-negyzet.py
# src/logos/<fájl> -> src/logos-negyzet/<slug>.webp (300x300, a megjelenítés 88 px-es keretének 3x-osa).
# - Egyszínű hátterű / átlátszó logó: a háttér körbevágva, a logó a négyzet közepén, 12% margóval,
#   a négyzet a logó saját háttérszínével kitöltve (átlátszónál fehér, világos logónál sötét).
# SVG-hez: scripts/svg2png.mjs (@resvg/resvg-js).
# - Teljes felületű (nem egyszínű keretű) négyzetes kép: kitölti a négyzetet.
# A lekerekítést a CSS végzi (.logo overflow:hidden + border-radius).
import json, os, statistics, subprocess, sys, tempfile
from PIL import Image, ImageChops, ImageDraw

SIZE, MARGIN, DARK = 300, 0.12, (17, 19, 24)
OUT = 'src/logos-negyzet'
os.makedirs(OUT, exist_ok=True)
manifest = json.load(open('src/logos.json'))


def load(path):
    if path.endswith('.svg'):
        # SVG raszterizálása resvg-vel (scripts/svg2png.mjs)
        png = os.path.join(tempfile.mkdtemp(), 'logo.png')
        subprocess.run(['node', 'scripts/svg2png.mjs', path, png], check=True, capture_output=True, timeout=60)
        return Image.open(png).convert('RGBA')
    im = Image.open(path)
    im.seek(0)
    return im.convert('RGBA')


def border(im):
    w, h = im.size
    px = im.load()
    pts = [(x, y) for x in range(0, w, max(1, w // 60)) for y in (0, h - 1)] + \
          [(x, y) for y in range(0, h, max(1, h // 60)) for x in (0, w - 1)]
    return [px[p] for p in pts]


def bbox(im, bg):
    # zajtűrő körbevágás: csak az a sor/oszlop számít tartalomnak, ahol érdemi számú eltérő pixel van
    d = ImageChops.difference(im, Image.new('RGB', im.size, bg)).convert('L').point(lambda x: 255 if x > 40 else 0)
    w, h = d.size
    px = d.load()
    step = max(1, max(w, h) // 600)
    cols = [x for x in range(0, w, step) if sum(px[x, y] > 0 for y in range(0, h, step)) >= max(2, h // step // 300)]
    rows = [y for y in range(0, h, step) if sum(px[x, y] > 0 for x in range(0, w, step)) >= max(2, w // step // 300)]
    if not cols or not rows:
        return None
    return (max(0, cols[0] - step), max(0, rows[0] - step), min(w, cols[-1] + step + 1), min(h, rows[-1] + step + 1))


def icon_colour(im, bg):
    # a körbevágott kép széle mentén (8%-kal beljebb) egyszínű, a háttértől eltérő szín -> ikon
    w, h = im.size
    pts = [(int(w * fx), int(h * fy)) for fx, fy in ((.5, .08), (.5, .92), (.08, .5), (.92, .5), (.25, .08), (.75, .92), (.08, .75), (.92, .25))]
    px = im.load()
    cs = [px[p] for p in pts]
    med = tuple(int(statistics.median(c)) for c in zip(*cs))
    if max(abs(a - b) for c in cs for a, b in zip(c, med)) > 30:
        return None
    if sum(abs(a - b) for a, b in zip(med, bg)) < 60:
        return None
    return med


def make(slug, v):
    im = load('src/logos/' + v['file'])
    b = border(im)
    transparent = sum(p[3] < 200 for p in b) > len(b) * 0.3
    if transparent:
        # világos (fehér/halvány) logó átlátszó háttéren: sötét háttér kell, különben eltűnik
        ops = [p for p in im.resize((64, 64)).getdata() if p[3] > 128]
        lum = sum(0.299 * p[0] + 0.587 * p[1] + 0.114 * p[2] for p in ops) / max(1, len(ops))
        bg = DARK if v.get('dark') or lum > 190 else (255, 255, 255)
        flat = Image.new('RGBA', im.size, bg + (255,))
        flat.alpha_composite(im)
        im, uniform = flat.convert('RGB'), True
    else:
        im = im.convert('RGB')
        b = [p[:3] for p in b]
        bg = tuple(int(statistics.median(c)) for c in zip(*b))
        spread = max(statistics.pstdev(c) for c in zip(*b))
        uniform = spread < 12
    w, h = im.size
    if not uniform and 0.8 <= w / h <= 1.25:
        # teljes felületű, közel négyzetes kép: kitölti a keretet
        s = min(w, h)
        im = im.crop(((w - s) // 2, (h - s) // 2, (w - s) // 2 + s, (h - s) // 2 + s))
        return im.resize((SIZE, SIZE), Image.LANCZOS), 'kitolt'
    # háttér körbevágása, majd középre, margóval
    box = bbox(im, bg)
    if box:
        im = im.crop(box)
    w, h = im.size
    edge = icon_colour(im, bg)
    if edge and 0.85 <= w / h <= 1.18:
        # app-ikon jellegű logó (színes, lekerekített négyzet): kitölti a keretet, a sarokmaradék az ikon színét kapja
        im = im.resize((SIZE, SIZE), Image.LANCZOS)
        for c in ((0, 0), (SIZE - 1, 0), (0, SIZE - 1), (SIZE - 1, SIZE - 1)):
            ImageDraw.floodfill(im, c, edge, thresh=60)
        return im, 'ikon'
    inner = int(SIZE * (1 - 2 * MARGIN))
    k = inner / max(w, h)
    im = im.resize((max(1, round(w * k)), max(1, round(h * k))), Image.LANCZOS)
    can = Image.new('RGB', (SIZE, SIZE), bg)
    can.paste(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2))
    return can, 'margoval' + ('' if k <= 1.5 else ' (nagyitva %.1fx)' % k)


stat = {}
for slug, v in manifest.items():
    if not v.get('file'):
        continue
    try:
        img, mode = make(slug, v)
        img.save(f'{OUT}/{slug}.webp', 'WEBP', quality=90, method=6)
        stat[mode.split(' ')[0]] = stat.get(mode.split(' ')[0], 0) + 1
        if 'nagyitva' in mode:
            print(slug, mode)
    except Exception as e:
        stat['hiba'] = stat.get('hiba', 0) + 1
        print(slug, 'HIBA', e)
print(stat, file=sys.stderr)
