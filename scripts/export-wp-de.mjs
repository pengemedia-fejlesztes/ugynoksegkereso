// Deutscher WordPress-Import (de.ugynoksegek.pmdev.hu): node scripts/export-wp-de.mjs [Ausgabeordner]
// Quelle: src/de/content.mjs (Ranglisten, Tags, Texte), src/de/agenturen.json, src/de/logos.json + src/de/logos/
// Ausgabe: <Ausgabeordner>/data.json + logos/ (Standard: build/de/kompasz-core/import)
import { readFile, writeFile, mkdir, rm, copyFile } from 'node:fs/promises';
import { SITE, CATEGORIES, TAGS, HOME_SEO } from '../src/de/content.mjs';

const OUT = process.argv[2] || 'build/de/kompasz-core/import';
const json = async (f, d) => JSON.parse(await readFile(f, 'utf8').catch(() => JSON.stringify(d)));
const db = await json('src/de/agenturen.json', { companies: [] });
const logos = await json('src/de/logos.json', {});
const google = await json('src/de/google-ratings.json', {});

let sharp = null;
try { sharp = (await import('sharp')).default; } catch { console.warn('sharp fehlt: Logos werden unverändert übernommen'); }
async function copyLogo(file) {
  const src = `src/de/logos/${file}`;
  if (!sharp || file.endsWith('.svg')) { await copyFile(src, `${OUT}/logos/${file}`); return file; }
  const out = file.replace(/\.[a-z]+$/i, '.png');
  try { await sharp(src).resize(256, 256, { fit: 'inside', withoutEnlargement: true }).png({ compressionLevel: 9, palette: true, quality: 90 }).toFile(`${OUT}/logos/${out}`); return out; }
  catch { await copyFile(src, `${OUT}/logos/${file}`); return file; }
}
const slugify = s => s.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
  .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const firstSentence = t => ((t || '').match(/^.{40,220}?[.!?](?=\s|$)/s) || [''])[0].trim();
const catSlug = Object.fromEntries(CATEGORIES.map(c => [c.name, c.slug]));

await rm(OUT, { recursive: true, force: true });
await mkdir(`${OUT}/logos`, { recursive: true });

const shown = db.companies.filter(c => c.status === 'kész' && c.description);
// Zertifizierungs- und Branchenseiten ab zwei Agenturen
const count = key => { const n = {}; for (const c of shown) for (const v of new Set((c[key] || []).map(x => x.trim()))) n[v] = (n[v] || 0) + 1; return n; };
const certCount = count('certs'), indCount = count('industries');
const certPages = Object.keys(certCount).filter(k => certCount[k] >= 2).map(name => ({ name, slug: slugify(name) }));
const indPages = Object.keys(indCount).filter(k => indCount[k] >= 2).map(name => ({ name, slug: slugify(name) }));
const certSet = new Map(certPages.map(p => [p.name, p.slug])), indSet = new Map(indPages.map(p => [p.name, p.slug]));

const agencies = [];
for (const c of shown) {
  const lg = logos[c.slug];
  const g = google[c.slug];
  agencies.push({
    slug: c.slug, name: c.name, website: c.website, city: c.city || '', founded: c.founded || null, team: c.team || null,
    services: c.services || [], description: c.description, intro: firstSentence(c.description),
    categories: (c.categories || []).map(n => catSlug[n]).filter(Boolean),
    tags: TAGS.filter(t => (c.services || []).some(t.m)).map(t => t.slug),
    rank: c.rank ?? null, rating: null,
    google: g ? { rating: g.rating, count: g.count, source: g.source, checked: g.checked } : null,
    logo: lg ? await copyLogo(lg.file) : null, logo_dark: !!lg?.dark, source_url: c.source_url || null,
    certs: c.certs || [], industries: c.industries || [], languages: [], references: [], work_mode: '', phone: '', email: '', county: '',
    cert_terms: (c.certs || []).map(x => certSet.get(x.trim())).filter(Boolean),
    industry_terms: (c.industries || []).map(x => indSet.get(x.trim())).filter(Boolean),
    cert_links: (c.certs || []).map(x => ({ name: x, slug: certSet.get(x.trim()) || '' })),
    industry_links: (c.industries || []).map(x => ({ name: x, slug: indSet.get(x.trim()) || '' })),
    client_terms: [],
  });
}

const data = {
  version: new Date().toISOString().slice(0, 10),
  site: SITE,
  ref_pages: { clients: [], certs: certPages, industries: indPages },
  settings: { featured: null, featured_categories: [], featured_pitch: '', home_top: [], home_tab: 'online-marketing-agenturen' },
  home_seo: HOME_SEO,
  categories: CATEGORIES.map((c, i) => ({ slug: c.slug, name: c.name, short: c.short, icon: c.icon, order: i + 1, ai: !!c.ai, desc: c.desc,
    h1: c.h1, question: c.question, intro: c.intro, seo: c.seo.map(([h, p]) => ({ h, p })) })),
  tags: TAGS.map(t => ({ slug: t.slug, name: t.label, kw: t.kw, h1: t.h1, title: t.title, desc: t.desc, intro: t.intro, seo: t.seo.map(([h, p]) => ({ h, p })) })),
  agencies,
};
await writeFile(`${OUT}/data.json`, JSON.stringify(data, null, 1));
const perCat = Object.fromEntries(CATEGORIES.map(c => [c.short, agencies.filter(a => a.categories.includes(c.slug)).length]));
console.log(`Fertig: ${agencies.length} Agenturen, ${Object.values(logos).length} Logos → ${OUT}/`, perCat);
