// Referencia-ügyfelek és minősítések egységesítése az /ugyfel/ és /minosites/ oldalakhoz
const fold = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** Ügyfélnév kulcsa: cégforma, „Magyarország” és írásjelek nélkül */
export function clientKey(name) {
  return fold(String(name).split('|')[0].trim())
    .replace(/\b(kft|zrt|nyrt|bt|ltd|gmbh|hungary|magyarorszag|group|csoport)\b\.?/g, '')
    .replace(/[^a-z0-9]+/g, '');
}

// Az ugyanazt jelentő minősítések egy néven
const CERT_ALIASES = [
  [/^meta (business )?partner$|^facebook partner$/i, 'Meta Business Partner'],
  [/^shoprenter (szakerto|expert|partner)$/i, 'Shoprenter partner'],
  [/^unas (szakerto|expert|partner|seo partner)$/i, 'UNAS partner'],
  [/^shopify (partner|expert)$/i, 'Shopify partner'],
  [/^tiktok (marketing )?partner$/i, 'TikTok partner'],
  [/^iso 9001/i, 'ISO 9001'],
  [/^mprsz/i, 'MPRSZ-tag'],
  [/^(magyar marketing szovetseg|mmsz)/i, 'Magyar Marketing Szövetség tag'],
];
export function certName(raw) {
  const s = String(raw).trim();
  const f = fold(s);
  for (const [re, name] of CERT_ALIASES) if (re.test(f)) return name;
  return s;
}

export function slugify(s) {
  return fold(String(s)).replace(/&/g, ' es ').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

/**
 * Ügyfél- és minősítés-oldalak: csak az kap oldalt, amely legalább MIN cégnél szerepel.
 * Visszaad: { clients: [{slug,name,count}], certs: [...], byAgency: {slug: {clients:[slug], certs:[slug]}} }
 */
export function buildRefPages(companies, MIN = 2) {
  const cl = new Map(), ce = new Map();
  for (const c of companies) {
    const seen = new Set();
    for (const r of c.references || []) {
      const k = clientKey(r); if (k.length < 3 || seen.has(k)) continue; seen.add(k);
      if (!cl.has(k)) cl.set(k, { name: String(r).split('|')[0].trim(), agencies: new Set() });
      cl.get(k).agencies.add(c.slug);
    }
    for (const x of new Set((c.certifications || []).map(certName))) {
      const k = slugify(x);
      if (!ce.has(k)) ce.set(k, { name: x, agencies: new Set() });
      ce.get(k).agencies.add(c.slug);
    }
  }
  const pick = (m) => [...m.entries()].filter(([, v]) => v.agencies.size >= MIN)
    .map(([k, v]) => ({ key: k, slug: slugify(v.name), name: v.name, count: v.agencies.size, agencies: [...v.agencies] }))
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'hu'));
  const clients = pick(cl), certs = pick(ce);
  const byAgency = {};
  for (const [list, field] of [[clients, 'clients'], [certs, 'certs']])
    for (const t of list) for (const s of t.agencies) ((byAgency[s] ||= { clients: [], certs: [] })[field]).push(t.slug);
  return { clients: clients.map(({ slug, name, count }) => ({ slug, name, count })), certs: certs.map(({ slug, name, count }) => ({ slug, name, count })), byAgency };
}
