// WordPress-import előállítása a repó adataiból: node scripts/export-wp.mjs
// Kimenet: wordpress/kompasz-core/import/data.json + import/logos/*
// Az importálást a WP adminban a Kompasz → Importálás oldal (vagy `wp kompasz import`) végzi.
import { readFile, writeFile, mkdir, rm, copyFile, stat } from 'node:fs/promises';
import { CATS, AGENCIES, PARTNERS, HOME_TOP } from '../src/data.mjs';
import { MEGYE } from '../src/megyek.mjs';
import { buildRefPages, buildIndustryPages, industryName, certName, slugify } from '../src/ugyfelek-minositesek.mjs';
import { TAGS } from '../src/tags.mjs';

const OUT = 'wordpress/kompasz-core/import';
const json = async f => JSON.parse(await readFile(f, 'utf8'));
const db = await json('src/ugynoksegek.json');
const logos = await json('src/logos.json').catch(() => ({}));
const google = await json('src/google-ratings.json').catch(() => ({}));

const SLUGS = {
  'AI agent fejlesztő cégek': 'ai-agent-fejleszto-cegek', 'AI SEO ügynökségek': 'ai-seo-ugynoksegek',
  'Google Ads ügynökségek': 'google-ads-ugynoksegek', 'Facebook hirdetés ügynökségek': 'facebook-hirdetes-ugynoksegek',
  'Online marketing ügynökségek': 'online-marketing-ugynoksegek', 'PR ügynökségek': 'pr-ugynoksegek',
  'Keresőoptimalizáló ügynökségek': 'keresooptimalizalo-ugynoksegek', 'Amazon ügynökségek': 'amazon-ugynoksegek',
  'Weboldal készítés': 'weboldal-keszites', 'Tartalomgyártó ügynökségek': 'tartalomgyarto-ugynoksegek', 'Bérelhető webshop szakértők': 'berelheto-webshop-szakertok',
};
// Ikonkulcsok a téma ikonkészletéhez (Kompasz v4 design)
const ICONS = {
  'AI agent fejlesztő cégek': 'bot', 'AI SEO ügynökségek': 'sparkle', 'Google Ads ügynökségek': 'target',
  'Facebook hirdetés ügynökségek': 'megaphone', 'Online marketing ügynökségek': 'trend', 'PR ügynökségek': 'news',
  'Keresőoptimalizáló ügynökségek': 'search', 'Amazon ügynökségek': 'box', 'Weboldal készítés': 'browser', 'Bérelhető webshop szakértők': 'box',
  'Tartalomgyártó ügynökségek': 'pen',
};
// Kategóriák sorrendje a fejlécben (design: AI agent → Tartalomgyártás)
const NAV_ORDER = ['AI agent fejlesztő cégek', 'AI SEO ügynökségek', 'Google Ads ügynökségek', 'Facebook hirdetés ügynökségek',
  'Online marketing ügynökségek', 'PR ügynökségek', 'Keresőoptimalizáló ügynökségek', 'Amazon ügynökségek', 'Weboldal készítés', 'Bérelhető webshop szakértők',
  'Tartalomgyártó ügynökségek'];
// Toplista → szolgáltatás (a WordPressben a toplista a szolgáltatás tulajdonsága; pár nélkül új szolgáltatás lesz a rövid névvel)
const TOPLIST_TAG = {
  'AI agent fejlesztő cégek': 'ai-agent-fejlesztes', 'AI SEO ügynökségek': 'ai-seo', 'Google Ads ügynökségek': 'google-ads',
  'Facebook hirdetés ügynökségek': 'facebook-instagram-hirdetes', 'PR ügynökségek': 'pr', 'Keresőoptimalizáló ügynökségek': 'seo',
  'Weboldal készítés': 'weboldal-keszites', 'Tartalomgyártó ügynökségek': 'tartalommarketing',
};
const NAV_SHORT = { 'Bérelhető webshop szakértők': 'Bérelhető webshop', 'Keresőoptimalizáló ügynökségek': 'SEO', 'Weboldal készítés': 'Weboldal', 'Tartalomgyártó ügynökségek': 'Tartalomgyártás', 'AI agent fejlesztő cégek': 'AI agent fejlesztés' };

// Logók kicsinyítése 256 px-re (ha a sharp telepítve van), hogy a bővítmény zip-je feltölthető maradjon
let sharp = null;
try { sharp = (await import('sharp')).default; } catch { console.warn('sharp nincs telepítve: a logók eredeti méretben kerülnek az importba'); }
async function copyLogo(file, slug) {
  // Négyzetes, keretkitöltő változat (scripts/logo-negyzet.py), ha van
  const sq = `src/logos-negyzet/${slug}.webp`;
  if (await stat(sq).catch(() => null)) { await copyFile(sq, `${OUT}/logos/${slug}.webp`); return `${slug}.webp`; }
  const src = `src/logos/${file}`;
  if (!sharp || file.endsWith('.svg')) { await copyFile(src, `${OUT}/logos/${file}`); return file; }
  const out = file.replace(/\.[a-z]+$/i, '.png');
  try {
    await sharp(src).resize(256, 256, { fit: 'inside', withoutEnlargement: true }).png({ compressionLevel: 9, palette: true, quality: 90 }).toFile(`${OUT}/logos/${out}`);
    return out;
  } catch { await copyFile(src, `${OUT}/logos/${file}`); return file; }
}

const domain = u => u.replace(/^(https?:\/\/)?(www\.)?/i, '').split('/')[0].toLowerCase();
const legacy = new Map(AGENCIES.map(([n, r, logo, url, tags, d], i) => [domain(url), { rank: i + 1, r, d }]));

const categories = NAV_ORDER.map((n, i) => {
  const c = CATS.find(x => x.n === n);
  return { slug: SLUGS[n], name: n, tag: TOPLIST_TAG[n] || null, short: NAV_SHORT[n] || c.short, icon: ICONS[n], order: i + 1, ai: !!c.ai, desc: c.d || '',
    h1: c.h1, question: c.q, intro: c.intro, seo: c.seo.map(([h, p]) => ({ h, p })) };
});
const tags = TAGS.map(t => ({ slug: t.slug, name: t.label, kw: t.kw, h1: t.h1, title: t.title, desc: t.desc, intro: t.intro, seo: t.seo.map(([h, p]) => ({ h, p })) }));

await rm(OUT, { recursive: true, force: true });
// Rövid bevezető: a bemutatkozás első mondata (legfeljebb ~220 karakter)
function firstSentence(t) {
  const m = (t || '').match(/^.{40,220}?[.!?](?=\s|$)/s);
  return m ? m[0].trim() : '';
}
await mkdir(`${OUT}/logos`, { recursive: true });
const agencies = [];
for (const c of db.companies) {
  const old = legacy.get(domain(c.website));
  const desc = c.description || old?.d || '';
  if (!(c.status === 'kész' || (old && desc))) continue; // a nyilvános oldalon is csak ezek látszanak
  const lg = logos[c.slug];
  const logoFile = lg ? await copyLogo(lg.file, c.slug) : null;
  const g = google[c.slug];
  agencies.push({
    slug: c.slug, name: c.name, website: c.website, city: c.city, founded: c.founded_year, team: c.team_size,
    services: c.services || [], description: desc, categories: c.categories.map(n => SLUGS[n]).filter(Boolean),
    tags: TAGS.filter(t => (c.services || []).some(t.m)).map(t => t.slug),
    rank: old ? old.rank : null, rating: old ? old.r : null,
    google: g ? { rating: g.rating, count: g.count, source: g.source, checked: g.checked } : null,
    logo: logoFile, logo_dark: !!lg?.dark, source_url: c.source_url || null,
    county: MEGYE[c.city] || '', intro: firstSentence(desc),
    phone: c.phone || '', email: c.email || '', certs: c.certifications || [], industries: c.industries || [],
    languages: c.languages || [], work_mode: c.work_mode || '', references: c.references || [],
  });
}
// Ügyfél- és minősítés-oldalak (legalább 2 cégnél szereplő ügyfelek és minősítések)
const shownSlugs = new Set(agencies.map(a => a.slug));
const refPages = buildRefPages(db.companies.filter(c => shownSlugs.has(c.slug)));
for (const a of agencies) { const r = refPages.byAgency[a.slug] || {}; a.client_terms = r.clients || []; a.cert_terms = r.certs || []; }
const indPages = buildIndustryPages(db.companies.filter(c => shownSlugs.has(c.slug)));
for (const a of agencies) a.industry_terms = indPages.byAgency[a.slug] || [];
// Az adatlapon megjelenő iparág- és minősítésnevek: ha van hozzájuk saját oldal, a slugjukkal (a sablon linkké teszi)
const indSlugs = new Set(indPages.list.map(t => t.slug)), certSlugs = new Set(refPages.certs.map(t => t.slug));
for (const a of agencies) {
  const n = (s) => { const m = industryName(s); const k = m ? slugify(m) : ''; return { name: s, slug: indSlugs.has(k) ? k : '' }; };
  const c = (s) => { const k = slugify(certName(s)); return { name: s, slug: certSlugs.has(k) ? k : '' }; };
  a.industry_links = (a.industries || []).map(n);
  a.cert_links = (a.certs || []).map(c);
}
const bySlugName = Object.fromEntries(agencies.map(a => [a.name, a.slug]));
const featured = Object.keys(PARTNERS)[0];
const data = {
  version: new Date().toISOString().slice(0, 10),
  site: { name: 'Kompasz', tagline: 'A legjobb magyar ügynökségek, kategóriánként rangsorolva.', email: 'hello@kompasz.hu' },
  ref_pages: { clients: refPages.clients, certs: refPages.certs, industries: indPages.list },
  settings: {
    featured: bySlugName[featured] || null,
    featured_categories: (PARTNERS[featured] || []).map(n => SLUGS[n]),
    featured_pitch: 'SEO, PPC és AI-megoldások egy csapattól, stratégiától a megvalósításig.',
    home_top: HOME_TOP.map(n => bySlugName[n]).filter(Boolean),
  },
  // Kezdőlap alsó SEO-szövege a Kompasz v4 designból
  home_seo: {
    title: 'Kompasz: így válassz online marketing és AI ügynökséget',
    blocks: [
      { h: 'Mi a Kompasz?', p: 'Független magyar oldal, ahol szolgáltatás szerint bontott toplistákban találod a hazai online marketing, Google Ads, Facebook hirdetés, AI SEO, AI agent fejlesztő, PR, SEO, Amazon, weboldal készítő és tartalomgyártó cégeket.' },
      { h: 'Hogyan rangsorolunk?', p: 'A sorrend ügyfélértékeléseken, referenciákon, szakmai tapasztalaton és nyilvános cégadatokon alapul. A fizetett megjelenés nem változtat a sorrenden, és minden ilyen helyet „Hirdetés” felirattal jelölünk.' },
      { h: 'Online marketing ügynökség vagy specialista?', p: 'Ha több csatornára van szükséged, a teljes körű ügynökség a kényelmesebb. Ha egy csatornát akarsz kihozni a maximumra, válassz Google Ads vagy Facebook hirdetés specialistát.' },
      { h: 'AI SEO és AI agentek: miért most?', p: 'A vásárlók egyre gyakrabban ChatGPT-ben és Geminiben keresnek. Az AI SEO ügynökségek ott tesznek láthatóvá, az AI agent fejlesztők pedig automatizálják az ügyfélszolgálatot és az értékesítést.' },
      { h: 'Hogyan szűkítsd a listát?', p: 'A toplistákon szolgáltatás, helyszín, értékelés és csapatméret szerint szűrhetsz. Minden cégnek saját profiloldala van, ahonnan hasonló ügynökségekhez is továbbléphetsz.' },
      { h: 'Hogyan veszed fel velük a kapcsolatot?', p: 'Minden profil és toplista közvetlenül az ügynökség oldalára visz. Nem vagyunk közvetítők, a megállapodás köztetek születik.' },
    ],
  },
  categories, tags, agencies,
};
await writeFile(`${OUT}/data.json`, JSON.stringify(data, null, 1));
console.log(`Kész: ${agencies.length} ügynökség, ${categories.length} toplista, ${tags.length} címke → ${OUT}/`);
