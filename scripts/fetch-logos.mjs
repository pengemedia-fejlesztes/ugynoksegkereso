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

function candidates(html, base) {
  const out = [];
  // 1. "logo" kép a fejlécben (az első néhány előfordulás)
  for (const tag of (html.match(/<img\b[^>]*>/gi) || []).slice(0, 80)) {
    const blob = [attr(tag, 'src'), attr(tag, 'data-src'), attr(tag, 'data-lazy-src'), attr(tag, 'alt'), attr(tag, 'class'), attr(tag, 'id')].join(' ');
    if (!/logo/i.test(blob) || /partner|client|ugyfel|award|google-partner|footer/i.test(blob)) continue;
    const src = attr(tag, 'data-src') || attr(tag, 'data-lazy-src') || attr(tag, 'src') || (attr(tag, 'srcset') || '').split(',')[0];
    if (src && !src.startsWith('data:')) out.push({ url: abs(src, base), why: 'logo-img' });
    if (out.length >= 2) break;
  }
  // 2. ikonok
  const icons = [];
  for (const tag of html.match(/<link\b[^>]*>/gi) || []) {
    const rel = (attr(tag, 'rel') || '').toLowerCase(), href = attr(tag, 'href');
    if (!href || !/icon/.test(rel)) continue;
    const size = Math.max(0, ...((attr(tag, 'sizes') || '').match(/\d+/g) || [0]).map(Number));
    icons.push({ url: abs(href, base), why: rel.includes('apple') ? 'apple-touch-icon' : 'icon', size: rel.includes('apple') ? Math.max(size, 180) : size, svg: /\.svg/i.test(href) });
  }
  icons.sort((a, b) => (b.svg - a.svg) || b.size - a.size);
  out.push(...icons);
  out.push({ url: abs('/apple-touch-icon.png', base), why: 'apple-touch-icon' });
  return out.filter(c => c.url);
}

const pngSize = b => b.length > 24 && b.readUInt32BE(0) === 0x89504e47 ? [b.readUInt32BE(16), b.readUInt32BE(20)] : null;
const EXT = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/svg+xml': 'svg', 'image/gif': 'gif', 'image/avif': 'avif' };
function accept(res) {
  if (!res || !res.buf.length || res.buf.length > MAX) return null;
  let type = res.type;
  if (!EXT[type]) { // néhány szerver rossz content-type-ot küld
    const s = res.buf.subarray(0, 200).toString('utf8');
    if (/<svg/i.test(s)) type = 'image/svg+xml'; else if (pngSize(res.buf)) type = 'image/png'; else if (res.buf[0] === 0xff && res.buf[1] === 0xd8) type = 'image/jpeg'; else return null;
  }
  const sz = type === 'image/png' && pngSize(res.buf);
  if (sz && Math.max(...sz) < 64) return null; // túl kicsi favicon
  if (type === 'image/svg+xml' && /<script/i.test(res.buf.toString('utf8'))) return null;
  return EXT[type];
}

async function logoFor(c) {
  const tries = [];
  const old = legacy.get(domain(c.website));
  if (old) tries.push({ url: old, why: 'ugynoksegkereso.hu' });
  const page = await get(c.website);
  if (page && /html/.test(page.type)) tries.push(...candidates(page.buf.toString('utf8'), page.url));
  for (const t of tries) {
    const res = await get(t.url);
    const ext = accept(res);
    if (ext) return { ext, buf: res.buf, src: t.url, why: t.why };
  }
  return null;
}

await mkdir(DIR, { recursive: true });
const todo = db.companies.filter(c => FORCE || !manifest[c.slug]);
let ok = 0, fail = [];
const queue = [...todo];
await Promise.all(Array.from({ length: 8 }, async () => {
  for (let c; (c = queue.shift());) {
    const r = await logoFor(c);
    if (!r) { fail.push(c.name); continue; }
    const file = `${c.slug}.${r.ext}`;
    for (const e of Object.values(EXT)) if (e !== r.ext) await rm(`${DIR}/${c.slug}.${e}`, { force: true });
    await writeFile(`${DIR}/${file}`, r.buf);
    manifest[c.slug] = { file, src: r.src, via: r.why };
    ok++; console.log(`✓ ${c.name} ← ${r.why}`);
  }
}));
await writeFile(MANIFEST, JSON.stringify(Object.fromEntries(Object.entries(manifest).sort()), null, 1) + '\n');
console.log(`\nÚj logó: ${ok}, hiányzik: ${fail.length}, összesen: ${Object.keys(manifest).length}/${db.companies.length}`);
if (fail.length) console.log('Nem sikerült: ' + fail.join(', '));
