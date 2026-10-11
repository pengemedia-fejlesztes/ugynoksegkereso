// Logók letöltése a repóba: node scripts/fetch-logos.mjs [--force]
// Forrás sorrendben: régi ugynoksegkereso.hu logó → a cég oldalán "logo" nevű kép →
// apple-touch-icon → legnagyobb PNG/SVG ikon. Eredmény: src/logos/<slug>.<ext> + src/logos.json
// A GitHub Action (.github/workflows/logos.yml) futtatja, mert a fejlesztői sandbox nem éri el a cégek oldalait.
import { readFile, writeFile, mkdir, rm } from 'node:fs/promises';
import { AGENCIES, LOGO_BASE } from '../src/data.mjs';

const FORCE = process.argv.includes('--force');
const DIR = 'src/logos', MANIFEST = 'src/logos.json';
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
const MAX = 600 * 1024;
const db = JSON.parse(await readFile('src/ugynoksegek.json', 'utf8'));
const manifest = FORCE ? {} : JSON.parse(await readFile(MANIFEST, 'utf8').catch(() => '{}'));
const domain = u => u.replace(/^(https?:\/\/)?(www\.)?/i, '').split('/')[0].toLowerCase();
const legacy = new Map(AGENCIES.map(a => [domain(a[3]), LOGO_BASE + a[2]]));
// Kézi javítások: { slug: { url: 'kép URL' } } vagy { slug: { skipImg: true } } (csak ikon), { slug: { none: true } }
const OVERRIDES = JSON.parse(await readFile('src/logo-overrides.json', 'utf8').catch(() => '{}'));

async function get(url, ms = 12000) {
  const ctl = new AbortController(), t = setTimeout(() => ctl.abort(), ms);
  try {
    const r = await fetch(url, { headers: { 'user-agent': UA, accept: '*/*' }, redirect: 'follow', signal: ctl.signal });
    if (!r.ok) return null;
    return { url: r.url, type: (r.headers.get('content-type') || '').split(';')[0].trim(), buf: Buffer.from(await r.arrayBuffer()) };
  } catch { return null; } finally { clearTimeout(t); }
}

const attr = (tag, name) => { const m = tag.match(new RegExp(`\\s${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i')); return m ? (m[1] ?? m[2] ?? m[3]) : undefined; };
const abs = (u, base) => { try { return new URL(u.trim().split(/\s+/)[0], base).href; } catch { return null; } };

const imgSrc = tag => attr(tag, 'data-src') || attr(tag, 'data-lazy-src') || attr(tag, 'src') || (attr(tag, 'srcset') || '').split(',')[0];
const imgBlob = tag => [imgSrc(tag), attr(tag, 'alt'), attr(tag, 'class'), attr(tag, 'id'), attr(tag, 'title')].filter(Boolean).join(' ');
const THIRD = /partner|client|ugyfel|ügyfél|referenci|award|badge|cert|google|meta|facebook|instagram|tiktok|linkedin|youtube|shoprenter|unas|trustindex|review/i;

function candidates(html, base, c) {
  const out = [], seen = new Set();
  const push = (src, why) => { const u = src && !src.startsWith('data:') && abs(src, base); if (u && !seen.has(u)) { seen.add(u); out.push({ url: u, why }); } };
  // a cég nevéből/domainjéből képzett kulcsszavak (pl. "drive", "exaline")
  const stems = [domain(c.website).split('.')[0], ...c.name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').split(/[^a-z0-9]+/)]
    .filter(w => w.length >= 4 && !/marketing|online|digital|ugynokseg|agency|design|media|studio|creative|consulting/.test(w));
  const own = blob => stems.some(w => blob.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(w));
  const header = (html.match(/<header\b[\s\S]*?<\/header>/i) || [''])[0];
  const hImgs = header.match(/<img\b[^>]*>/gi) || [];
  const allImgs = html.match(/<img\b[^>]*>/gi) || [];
  // 1. fejlécben: a cég nevét viselő vagy "logo" kép, különben az első fejléckép
  for (const t of hImgs) if (own(imgBlob(t)) && !THIRD.test(imgBlob(t))) push(imgSrc(t), 'header-own');
  for (const t of hImgs) if (/logo/i.test(imgBlob(t)) && !THIRD.test(imgBlob(t))) push(imgSrc(t), 'header-logo');
  if (hImgs[0] && !THIRD.test(imgBlob(hImgs[0]))) push(imgSrc(hImgs[0]), 'header-first');
  // 2. bárhol: "logo" + a cég neve
  for (const t of allImgs) { const bl = imgBlob(t); if (/logo/i.test(bl) && own(bl) && !THIRD.test(bl)) push(imgSrc(t), 'logo-own'); }
  // 3. "logo" kép csak az oldal legelején (később jellemzően ügyfél-/partnerlogók jönnek)
  for (const t of allImgs.slice(0, 4)) { const bl = imgBlob(t); if (/logo/i.test(bl) && !THIRD.test(bl)) push(imgSrc(t), 'logo-top'); }
  const imgs = out.length;
  // 4. ikonok
  const icons = [];
  for (const tag of html.match(/<link\b[^>]*>/gi) || []) {
    const rel = (attr(tag, 'rel') || '').toLowerCase(), href = attr(tag, 'href');
    if (!href || !/icon/.test(rel)) continue;
    const size = Math.max(0, ...((attr(tag, 'sizes') || '').match(/\d+/g) || [0]).map(Number));
    icons.push({ href, why: rel.includes('apple') ? 'apple-touch-icon' : 'icon', size: rel.includes('apple') ? Math.max(size, 180) : size, svg: /\.svg/i.test(href) });
  }
  icons.sort((a, b) => (b.svg - a.svg) || b.size - a.size);
  for (const i of icons) push(i.href, i.why);
  push('/apple-touch-icon.png', 'apple-touch-icon');
  return { list: out, imgs };
}

const pngSize = b => b.length > 24 && b.readUInt32BE(0) === 0x89504e47 ? [b.readUInt32BE(16), b.readUInt32BE(20)] : null;
const EXT = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/svg+xml': 'svg', 'image/gif': 'gif', 'image/avif': 'avif' };
let sharp = null;
try { sharp = (await import('sharp')).default; } catch { console.warn('sharp nincs telepítve: világosság-ellenőrzés kimarad'); }

// Kép elemzése: dekódolható-e, nem üres-e, és világos-e (fehér logó → sötét háttér kell)
async function analyse(buf) {
  if (!sharp) return { ok: true, dark: false };
  try {
    const { data, info } = await sharp(buf, { density: 72 }).resize(64, 64, { fit: 'inside' }).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    let n = 0, lum = 0;
    for (let i = 0; i < data.length; i += 4) {
      if (data[i + 3] < 128) continue;
      n++; lum += (0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) / 255;
    }
    if (n < info.width * info.height * 0.02) return { ok: false }; // szinte teljesen átlátszó
    const mean = lum / n;
    const opaque = n / (info.width * info.height);
    // világos logó átlátszó háttéren → sötét kártyán kell megjeleníteni
    return { ok: !(mean > 0.97 && opaque > 0.9), dark: mean > 0.82 && opaque < 0.9 };
  } catch { return { ok: false }; }
}

async function accept(res) {
  if (!res || !res.buf.length || res.buf.length > MAX) return null;
  let type = res.type;
  if (!EXT[type]) { // néhány szerver rossz content-type-ot küld
    const s = res.buf.subarray(0, 200).toString('utf8');
    if (/<svg/i.test(s)) type = 'image/svg+xml'; else if (pngSize(res.buf)) type = 'image/png'; else if (res.buf[0] === 0xff && res.buf[1] === 0xd8) type = 'image/jpeg'; else return null;
  }
  const sz = type === 'image/png' && pngSize(res.buf);
  if (sz && Math.max(...sz) < 64) return null; // túl kicsi favicon
  if (type === 'image/svg+xml' && /<script|onload=/i.test(res.buf.toString('utf8'))) return null;
  const a = await analyse(res.buf);
  return a.ok ? { ext: EXT[type], dark: !!a.dark } : null;
}

async function logoFor(c) {
  const ov = OVERRIDES[c.slug] || {};
  const tries = [];
  if (ov.url) tries.push({ url: ov.url, why: 'override' });
  const old = legacy.get(domain(c.website));
  if (old) tries.push({ url: old, why: 'ugynoksegkereso.hu' });
  const page = await get(c.website);
  if (page && /html/.test(page.type)) {
    const { list, imgs } = candidates(page.buf.toString('utf8'), page.url, c);
    tries.push(...(ov.skipImg ? list.slice(imgs) : list));
  }
  for (const t of tries) {
    const res = await get(t.url);
    const ok = await accept(res);
    if (ok) return { ...ok, buf: res.buf, src: t.url, why: t.why };
  }
  return null;
}

await mkdir(DIR, { recursive: true });
for (const [slug, o] of Object.entries(OVERRIDES)) if (o.none) delete manifest[slug];
const todo = db.companies.filter(c => !OVERRIDES[c.slug]?.none && (FORCE || !manifest[c.slug] || OVERRIDES[c.slug]));
let ok = 0, fail = [];
const queue = [...todo];
await Promise.all(Array.from({ length: 8 }, async () => {
  for (let c; (c = queue.shift());) {
    const r = await logoFor(c);
    if (!r) { fail.push(c.name); continue; }
    const file = `${c.slug}.${r.ext}`;
    for (const e of Object.values(EXT)) if (e !== r.ext) await rm(`${DIR}/${c.slug}.${e}`, { force: true });
    await writeFile(`${DIR}/${file}`, r.buf);
    manifest[c.slug] = { file, src: r.src, via: r.why, ...(r.dark && { dark: true }) };
    ok++; console.log(`✓ ${c.name} ← ${r.why}`);
  }
}));
await writeFile(MANIFEST, JSON.stringify(Object.fromEntries(Object.entries(manifest).sort()), null, 1) + '\n');
console.log(`\nÚj logó: ${ok}, hiányzik: ${fail.length}, összesen: ${Object.keys(manifest).length}/${db.companies.length}`);
if (fail.length) console.log('Nem sikerült: ' + fail.join(', '));
