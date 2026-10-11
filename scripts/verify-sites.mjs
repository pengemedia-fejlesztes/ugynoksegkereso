// Jelölt cégek weboldalainak letöltése ellenőrzéshez: node scripts/verify-sites.mjs
// Bemenet: data/candidates.json · Kimenet: data/verify/<slug>.json (cím, meta leírás, oldalszövegek)
// A GitHub Action (.github/workflows/verify.yml) futtatja, mert a fejlesztői sandbox nem éri el a cégek oldalait.
import { readFile, writeFile, mkdir } from 'node:fs/promises';

const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
const { candidates, existing_add = [] } = JSON.parse(await readFile('data/candidates.json', 'utf8'));
const db = JSON.parse(await readFile('src/ugynoksegek.json', 'utf8'));
const bySlug = Object.fromEntries(db.companies.map(c => [c.slug, c]));

async function get(url, ms = 15000) {
  const ctl = new AbortController(), t = setTimeout(() => ctl.abort(), ms);
  try {
    const r = await fetch(url, { headers: { 'user-agent': UA, 'accept-language': 'hu-HU,hu;q=0.9' }, redirect: 'follow', signal: ctl.signal });
    const type = r.headers.get('content-type') || '';
    return { status: r.status, url: r.url, html: /html/.test(type) ? await r.text() : '' };
  } catch (e) { return { status: 0, url, html: '', error: String(e.cause?.code || e.name) }; } finally { clearTimeout(t); }
}
const decode = s => s.replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>')
  .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(+n)).replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)));
const text = html => decode(html.replace(/<(script|style|noscript|svg|iframe)[\s\S]*?<\/\1>/gi, ' ').replace(/<br\s*\/?>|<\/(p|div|li|h\d|section|tr)>/gi, '\n')
  .replace(/<[^>]+>/g, ' ')).replace(/[ \t]+/g, ' ').replace(/\n\s*\n+/g, '\n').trim();
const meta = (html, re) => decode((html.match(re) || [])[1] || '').trim();

async function check(slug, website, extra = []) {
  const home = await get(website);
  const base = home.url || website;
  const pages = [{ url: home.url, status: home.status, error: home.error, text: text(home.html).slice(0, 6000) }];
  const more = [...new Set([...extra, '/rolunk/', '/kapcsolat/'])];
  for (const p of more) {
    let u; try { u = new URL(p, base).href; } catch { continue; }
    if (new URL(u).hostname.replace(/^www\./, '') !== new URL(base).hostname.replace(/^www\./, '')) continue;
    const r = await get(u);
    if (r.status === 200 && r.html) pages.push({ url: r.url, status: r.status, text: text(r.html).slice(0, 4000) });
  }
  const out = { slug, website, final_url: home.url, status: home.status, error: home.error || null,
    title: meta(home.html, /<title[^>]*>([\s\S]*?)<\/title>/i), description: meta(home.html, /<meta[^>]+name=["']description["'][^>]+content=["']([^"']*)/i),
    checked: new Date().toISOString().slice(0, 10), pages };
  await writeFile(`data/verify/${slug}.json`, JSON.stringify(out, null, 1));
  console.log(`${home.status || home.error}  ${slug}  ${home.url || website}`);
}

await mkdir('data/verify', { recursive: true });
const jobs = [
  ...candidates.map(c => [c.slug, c.website, [c.source_url].filter(Boolean)]),
  ...existing_add.filter(e => bySlug[e.slug]).map(e => [e.slug, bySlug[e.slug].website, (e.reason.match(/https?:\/\/[^\s)]+|[a-z0-9.-]+\.hu\/[^\s)]*/gi) || []).map(u => u.startsWith('http') ? u : 'https://' + u)]),
];
const seen = new Set(), queue = jobs.filter(([s]) => !seen.has(s) && seen.add(s));
await Promise.all(Array.from({ length: 6 }, async () => { for (let j; (j = queue.shift());) await check(...j); }));
