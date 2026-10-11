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

// Iparági tapasztalat egységesítése (a cégek saját megnevezéseiből) – /iparag/<slug>/ oldalakhoz
const INDUSTRY_MAP = [
  [/^(webaruhaz|webshop|webshopok|e-kereskedelem|ecommerce|e-commerce)$/, 'Webáruház és e-kereskedelem'],
  [/^(egeszsegugy|egeszsegugyi|orvosi|klinika)/, 'Egészségügy'],
  [/^(gyogyszeripar|gyogyszer)/, 'Gyógyszeripar'],
  [/b2b/, 'B2B szolgáltatók'],
  [/^b2c$/, 'B2C (lakossági) vállalkozások'],
  [/^oktatas/, 'Oktatás'],
  [/^(ingatlan|epitoipar|ingatlan es epitoipar)/, 'Ingatlan és építőipar'],
  [/^(etterem|vendeglatas|vendeglatoipar|horeca|gasztronomia|turizmus|utazasi iroda|utazas|szallashely|szalloda|szallas)$/, 'Vendéglátás és turizmus'],
  [/^(szepsegipar|divat|szepseg es divat)$/, 'Szépségipar és divat'],
  [/^logisztika/, 'Logisztika'],
  [/^(it|informatika|informacio es technologia|it helpdesk)$/, 'IT és technológia'],
  [/^(penzugy|bank|penzpiac|biztositas)$/, 'Pénzügy és bank'],
  [/^(ugyvedi iroda|jog)$/, 'Jogi szolgáltatók'],
  [/^(ipar|gyartas.*|mernoki|autoipari|energia)$/, 'Ipar és gyártás'],
  [/^(fmcg|elelmiszeripar|allateledel)$/, 'FMCG és élelmiszeripar'],
  [/^(mezogazdasag|kerteszet)$/, 'Mezőgazdaság'],
  [/^nonprofit/, 'Nonprofit'],
];
export function industryName(raw) {
  const f = fold(String(raw).trim());
  for (const [re, name] of INDUSTRY_MAP) if (re.test(f)) return name;
  return null; // túl általános vagy egyedi megnevezés: nem kap oldalt
}

/** Iparági oldalak: legalább MIN cégnél szereplő iparágak; { list: [{slug,name,count}], byAgency: {slug: [iparág-slug]} } */
export function buildIndustryPages(companies, MIN = 2) {
  const m = new Map();
  for (const c of companies) for (const n of new Set((c.industries || []).map(industryName).filter(Boolean))) {
    const k = slugify(n);
    if (!m.has(k)) m.set(k, { name: n, agencies: new Set() });
    m.get(k).agencies.add(c.slug);
  }
  const list = [...m.entries()].filter(([, v]) => v.agencies.size >= MIN)
    .map(([slug, v]) => ({ slug, name: v.name, count: v.agencies.size, agencies: [...v.agencies] }))
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'hu'));
  const byAgency = {};
  for (const t of list) for (const s of t.agencies) (byAgency[s] ||= []).push(t.slug);
  return { list: list.map(({ slug, name, count }) => ({ slug, name, count })), byAgency };
}
