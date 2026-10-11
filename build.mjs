// Statikus oldalgenerátor: node build.mjs → public/
import { mkdir, writeFile, copyFile, cp, rm, readFile } from 'node:fs/promises';
import { CATS, AGENCIES, PARTNERS, HOME_TOP, HOME_SEO, LOGO_BASE } from './src/data.mjs';
import { TAGS } from './src/tags.mjs';

// Az ügynökségadatok forrása: src/ugynoksegek.json (export: 2026-10-11).
// A design régi listájából (data.mjs → AGENCIES) csak a sorrend, az értékelés és a logó jön.
const DB = JSON.parse(await readFile('src/ugynoksegek.json', 'utf8'));
// Letöltött logók (scripts/fetch-logos.mjs) → /logos/<fájl>
const LOGOS = JSON.parse(await readFile('src/logos.json', 'utf8').catch(() => '{}'));
// Google-értékelések (kézzel ellenőrzött, forrással): felülírják a régi lista értékelését
const GOOGLE = JSON.parse(await readFile('src/google-ratings.json', 'utf8').catch(() => '{}'));

const OUT = 'public';
const SITE = (process.env.SITE_URL || 'https://ugynoksegek.pmdev.hu').replace(/\/$/, '');
const TAG_MIN = 3; // ennél kevesebb céggel nem készül címkeoldal
const SLUGS = {
  'AI agent fejlesztő cégek': 'ai-agent-fejleszto-cegek',
  'AI SEO ügynökségek': 'ai-seo-ugynoksegek',
  'Google Ads ügynökségek': 'google-ads-ugynoksegek',
  'Facebook hirdetés ügynökségek': 'facebook-hirdetes-ugynoksegek',
  'Online marketing ügynökségek': 'online-marketing-ugynoksegek',
  'PR ügynökségek': 'pr-ugynoksegek',
  'Keresőoptimalizáló ügynökségek': 'keresooptimalizalo-ugynoksegek',
  'Amazon ügynökségek': 'amazon-ugynoksegek',
  'Weboldal készítés': 'weboldal-keszites',
  'Tartalomgyártó ügynökségek': 'tartalomgyarto-ugynoksegek',
};
const PAGES = {
  rolunk: ['Rólunk', 'Az Ügynökségkereső független magyar oldal, ahol szolgáltatás szerint bontott toplistákban találod a hazai ügynökségeket. Ez az oldal hamarosan bővül.'],
  blog: ['Blog', 'Hamarosan itt olvashatod cikkeinket ügynökségválasztásról, online marketingről és AI-megoldásokról.'],
  kapcsolat: ['Kapcsolat', 'Ügynökség vagy, és szerepelnél a listán? Kérdésed van? Írj nekünk: <a href="mailto:hello@ugynoksegkereso.hu">hello@ugynoksegkereso.hu</a>.'],
  'kivalasztasi-elvek': ['A listák kiválasztási elvei', 'A sorrend ügyfélértékeléseken, referenciákon, szakmai tapasztalaton és a nyilvános cégadatokon alapul. A kiemelt partneri megjelenés fizetett, de nem befolyásolja a toplista sorrendjét. A részletes szempontok hamarosan itt olvashatók.'],
  aszf: ['Általános szerződési feltételek', 'Az ÁSZF szövege hamarosan elérhető.'],
  adatvedelem: ['Adatvédelmi tájékoztató', 'Az adatvédelmi tájékoztató szövege hamarosan elérhető.'],
};

const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const href = c => `/${SLUGS[c.n]}/`;
const stars = r => '★'.repeat(Math.floor(r)) + (r % 1 ? '½' : '');
const ini = n => n.replace(/[^A-Za-zÁÉÍÓÖŐÚÜŰ0-9 ]/g, '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
const domain = u => u.replace(/^(https?:\/\/)?(www\.)?/i, '').split('/')[0].toLowerCase();
const legacy = new Map(AGENCIES.map(([n, r, logo, url, tags, d], i) => [domain(url), { order: i, r, logo, tags: tags.split('|'), d }]));
const companies = DB.companies.map(c => {
  const old = legacy.get(domain(c.website));
  const d = c.description || old?.d || '';
  const meta = [c.city, c.founded_year && `alapítva ${c.founded_year}`, c.team_size].filter(Boolean).join(' · ');
  return {
    n: c.name, cityName: c.city, href: c.website, url: domain(c.website), d, meta, cats: c.categories,
    ok: c.status === 'kész' || !!(old && d), order: old ? old.order : Infinity,
    tagSlugs: TAGS.filter(t => (c.services || []).some(t.m)).map(t => t.slug),
    rawTags: (c.services?.length ? c.services : old?.tags || []).slice(0, 3), services: c.services || [],
    logo: LOGOS[c.slug] ? `/logos/${LOGOS[c.slug].file}` : old ? LOGO_BASE + old.logo : null, ini: ini(c.name), logoDark: !!LOGOS[c.slug]?.dark,
    ...(() => { const g = GOOGLE[c.slug], r = g ? g.rating : old?.r;
      return { g: g ? g.count : 0, stars: r ? stars(r) : '', r: r ? r.toFixed(1).replace('.', ',') : '' }; })(),
  };
}).filter(c => c.ok);
// Kategórialisták: előbb a régi toplista sorrendje, utána ábécérendben
const LISTS = Object.fromEntries(CATS.map(cat => [cat.n, companies
  .filter(c => c.cats.includes(cat.n))
  .sort((a, b) => a.order - b.order || a.n.localeCompare(b.n, 'hu'))
  .map((c, i) => ({ ...c, rank: String(i + 1).padStart(2, '0') }))]));
const agencies = LISTS['Online marketing ügynökségek'];
// Címkelisták ugyanazzal a sorrenddel; csak a legalább TAG_MIN céges címkék kapnak oldalt
const TAG_LISTS = Object.fromEntries(TAGS.map(t => [t.slug, companies
  .filter(c => c.tagSlugs.includes(t.slug))
  .sort((a, b) => a.order - b.order || a.n.localeCompare(b.n, 'hu'))
  .map((c, i) => ({ ...c, rank: String(i + 1).padStart(2, '0') }))]));
const LIVE_TAGS = TAGS.filter(t => TAG_LISTS[t.slug].length >= TAG_MIN);
const TAG = Object.fromEntries(LIVE_TAGS.map(t => [t.slug, t]));
const tagHref = t => `/cimke/${t.slug}/`;
const tagLinks = (a, cur) => {
  const live = a.tagSlugs.filter(s => TAG[s]);
  if (!live.length) return a.rawTags.map(t => `<span>${esc(t)}</span>`).join('');
  return live.slice(0, 5).map(s => s === cur ? `<span class="cur">${esc(TAG[s].label)}</span>` : `<a href="${tagHref(TAG[s])}">${esc(TAG[s].label)}</a>`).join('');
};
const aiCats = CATS.filter(c => c.ai), otherCats = CATS.filter(c => !c.ai);
const ext = a => `href="${esc(a.href)}" target="_blank" rel="nofollow noopener"`;
const logo = (a, cls, size) => `<span class="logo ${cls}${a.logoDark && a.logo ? ' dark' : ''}"><span>${esc(a.ini)}</span>${a.logo ? `<img src="${esc(a.logo)}" alt="${esc(a.n)} logó" loading="lazy" decoding="async" width="${size}" height="${size}">` : ''}</span>`;
const rating = (a, extra = '') => a.r ? `<span class="stars">${a.stars}</span><b>${a.r}/5${extra}</b>${a.g ? `<span class="gsrc" title="Google-értékelés">Google · ${a.g} vélemény</span>` : ''}` : (extra ? `<b>${extra.replace(/^ · /, '')}</b>` : '');

const jsonld = o => `<script type="application/ld+json">${JSON.stringify(o).replace(/</g, '\\u003c')}</script>`;
const head = (title, desc, ld = '') => `<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<meta name="robots" content="noindex, nofollow, noarchive">
<script>if(location.hostname.endsWith('.pages.dev'))location.replace('https://ugynoksegek.pmdev.hu'+location.pathname+location.search+location.hash);</script>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/styles.css">
<script src="/app.js" defer></script>
${ld}
</head>
<body>`;

const header = active => `<header class="hdr"><div class="wrap">
<a class="brand" href="/"><span class="mark"><i></i></span>Ügynökségkereső</a>
<nav class="nav" aria-label="Fő menü">
<a href="/"${active === 'home' ? ' class="on"' : ''}>Kezdőlap</a>
<button id="mega-btn" type="button" aria-expanded="false" aria-controls="mega"${active === 'cat' ? ' class="on"' : ''}>Ügynökségek <span class="car">▾</span></button>
<a href="/rolunk/">Rólunk</a><a href="/blog/">Blog</a><a href="/kapcsolat/">Kapcsolat</a>
</nav>
<div class="lang"><span class="cur">HU</span><a href="#" aria-disabled="true" title="Hamarosan">EN</a></div>
<span class="mlang">HU</span>
<button id="burger" class="burger" type="button" aria-expanded="false" aria-controls="mnav" aria-label="Menü">☰</button>
<div id="mega" class="mega">
<div class="mega-ai"><div class="lbl">AI &amp; ONLINE MARKETING</div>${aiCats.map(c => `<a href="${href(c)}">${esc(c.n)}<span>→</span></a>`).join('')}</div>
<div class="mega-ot"><div class="lbl">TOVÁBBI TOPLISTÁK</div>${otherCats.map(c => `<a href="${href(c)}">${esc(c.n)}</a>`).join('')}</div>
</div>
</div></header>
<nav id="mnav" class="mnav" aria-label="Mobil menü">
<a href="/">Kezdőlap</a>
<div class="lbl">ÜGYNÖKSÉGEK</div>
${CATS.map(c => `<a class="sub" href="${href(c)}">${esc(c.n)}</a>`).join('')}
<a href="/rolunk/">Rólunk</a><a href="/blog/">Blog</a><a href="/kapcsolat/">Kapcsolat</a>
</nav>`;

const seoBlock = (title, items) => `<section class="seo"><div class="wrap">
<h2>${esc(title)}</h2>
<div class="seo-grid clip">${items.map(s => `<div><h3>${esc(s.h)}</h3><p>${s.html || esc(s.p)}</p></div>`).join('')}</div>
<button class="seo-more" type="button" aria-expanded="false">Tovább olvasom</button>
</div></section>`;

const infoLinks = '<a href="/kivalasztasi-elvek/">A listák kiválasztási elvei</a><a href="/aszf/">ÁSZF</a><a href="/adatvedelem/">Adatvédelmi tájékoztató</a>';
const footer = `<footer class="ftr"><div class="wrap">
<div class="fcols">
<div><div class="b">Ügynökségkereső</div><div style="margin-top:10px;line-height:1.5">A legjobb magyar ügynökségek egy helyen.</div><div style="margin-top:18px"><a href="mailto:hello@ugynoksegkereso.hu">hello@ugynoksegkereso.hu</a></div></div>
<div><div class="lbl">TOPLISTÁK</div><div class="two">${CATS.map(c => `<a href="${href(c)}">${esc(c.n)}</a>`).join('')}</div></div>
<div><div class="lbl">OLDALAK</div><div class="col"><a href="/">Kezdőlap</a><a href="/cimkek/">Szolgáltatások</a><a href="/rolunk/">Rólunk</a><a href="/blog/">Blog</a><a href="/kapcsolat/">Kapcsolat</a></div></div>
<div><div class="lbl">INFORMÁCIÓ</div><div class="col">${infoLinks}</div></div>
</div>
<div class="fbot"><span>© 2026 Ügynökségkereső</span><span>Magyar · <a href="#" aria-disabled="true" title="Hamarosan">English</a></span></div>
</div></footer>`;
const end = '\n</body>\n</html>\n';

// ---------- Kezdőlap ----------
function home() {
  const chip0 = CATS[0];
  const featured = CATS.slice(1, 5);
  const om = CATS.find(c => c.n === 'Online marketing ügynökségek');
  return head('Ügynökségkereső – a legjobb magyar ügynökségek egy helyen', 'Rangsorolt toplisták online marketing, Google Ads, Facebook hirdetés, AI SEO és AI agent fejlesztő cégekről.',
    jsonld({ '@context': 'https://schema.org', '@type': 'WebSite', name: 'Ügynökségkereső', url: SITE + '/', inLanguage: 'hu-HU' })) + header('home') + `
<main>
<section class="wrap hero d-only">
<div>
<div class="eyebrow">FÜGGETLEN TOPLISTÁK · 2026</div>
<h1>A legjobb magyar ügynökségek, <em>egy helyen.</em></h1>
<p>Rangsorolt toplisták online marketing, Google Ads, Facebook hirdetés, AI SEO és AI agent fejlesztő cégekről. Hasonlítsd össze őket, és vedd fel a kapcsolatot közvetlenül azzal, aki tetszik.</p>
</div>
<div class="picker">
<h2>Milyen ügynökséget keresel?</h2>
<div class="chips">${CATS.map((c, i) => `<button class="chip" type="button" aria-pressed="${i === 0}" data-name="${esc(c.n)}" data-href="${href(c)}">${esc(c.short)}</button>`).join('')}</div>
<a id="picker-go" class="go" href="${href(chip0)}">${esc(chip0.n)}: toplista megnyitása →</a>
</div>
</section>

<section class="wrap mhero">
<div class="eyebrow">FÜGGETLEN TOPLISTÁK · 2026</div>
<h1>A legjobb magyar ügynökségek, <em>egy helyen.</em></h1>
<p>Hasonlítsd össze őket, és keresd meg közvetlenül azt, aki tetszik.</p>
</section>
<section class="wrap mcats">
<a class="mbig" href="${href(chip0)}"><span class="eyebrow">TOPLISTA 2026</span><div class="t">AI agent fejlesztő cégek</div><div class="d">Ügyfélszolgálat, sales, back-office</div></a>
<div class="mlist">${CATS.slice(1).map(c => `<a href="${href(c)}">${esc(c.n)}<span>›</span></a>`).join('')}</div>
</section>

<section class="wrap feat d-only">
<div class="sec-h feat-h"><h2 class="h2">Kiemelt toplisták</h2><a href="/kivalasztasi-elvek/">A listák kiválasztási elvei →</a></div>
<div class="fgrid">
<a class="fbig" href="${href(chip0)}"><span class="eyebrow lav">TOPLISTA 2026</span><span class="t">AI agent fejlesztő cégek</span><span class="d">Ügyfélszolgálati, sales- és back-office agenteket építő hazai csapatok.</span><span class="b">Lista megnyitása →</span></a>
${featured.map(c => `<a class="fsm" href="${href(c)}"><span class="ar">↗</span><span class="t">${esc(c.n)}</span><span class="d">${esc(c.d)}</span></a>`).join('\n')}
</div>
<div class="more-lists"><span>További listák:</span>${otherCats.map(c => `<a href="${href(c)}">${esc(c.n)}</a>`).join('')}</div>
</section>

<section class="steps-sec d-only"><div class="wrap">
<h2 class="h2">Így találod meg<br>a partnered.</h2>
<div class="steps">
<div><div class="n">01</div><h3>Válassz kategóriát</h3><p>Tíz toplista, szolgáltatás szerint bontva.</p></div>
<div><div class="n">02</div><h3>Hasonlítsd össze</h3><p>Értékelés, fókuszterületek, rövid bemutatkozás.</p></div>
<div><div class="n">03</div><h3>Keresd meg őket közvetlenül</h3><p>Egy kattintás, és az ügynökség oldalán vagy.</p></div>
</div>
</div></section>

<section class="wrap top5 d-only">
<div class="sec-h"><div><div class="eyebrow">ONLINE MARKETING ÜGYNÖKSÉGEK · 2026</div><h2 class="h2">A lista eleje</h2></div><a class="btn-ghost" href="${href(om)}">Teljes lista →</a></div>
<div class="t5">
${HOME_TOP.map(n => byName[n]).filter(Boolean).map((a, i) => ({ ...a, rank: String(i + 1).padStart(2, '0') })).map(a => `<div class="t5row"><span class="rank">${a.rank}</span>${logo(a, 'l52', 52)}<span class="nm">${esc(a.n)}</span><span class="ds">${esc(a.d.length > 140 ? a.d.slice(0, a.d.lastIndexOf(' ', 140)) + '…' : a.d)}</span><span class="rate">${a.g ? rating(a) : '<span class="gsrc">Google-értékelés: nincs adat</span>'}</span><a class="btn" ${ext(a)}>Kapcsolat ↗</a></div>`).join('\n')}
</div>
</section>

<section class="wrap d-only"><div class="join">
<div style="flex:1"><div class="t">Ügynökség vagy, és még nem szerepelsz a listán?</div><div class="d">Írj nekünk, és kollégáink értékelik a cégedet.</div></div>
<a href="/kapcsolat/">Kapcsolat →</a>
</div></section>
</main>
` + seoBlock('Ügynökségkereső: így válassz online marketing és AI ügynökséget', HOME_SEO.map((s, i) =>
    i === 1 ? { h: s.h, html: esc(s.p).replace('kiválasztási elvek oldalon', '<a href="/kivalasztasi-elvek/">kiválasztási elvek</a> oldalon') } : s)) + footer + end;
}

// ---------- Listaoldal (toplista és címke) ----------
const orgLd = (a, i) => ({ '@type': 'ListItem', position: i + 1, item: { '@type': 'Organization', name: a.n, url: a.href,
  ...(a.d && { description: a.d }), ...(a.cityName && { address: { '@type': 'PostalAddress', addressLocality: a.cityName, addressCountry: 'HU' } }) } });
const pageLd = ({ url, name, desc, crumbs, list }) => jsonld({ '@context': 'https://schema.org', '@graph': [
  { '@type': 'CollectionPage', '@id': SITE + url, url: SITE + url, name, description: desc, inLanguage: 'hu-HU',
    isPartOf: { '@type': 'WebSite', name: 'Ügynökségkereső', url: SITE + '/' }, mainEntity: { '@id': SITE + url + '#lista' } },
  { '@type': 'BreadcrumbList', itemListElement: crumbs.map(([n, u], i) => ({ '@type': 'ListItem', position: i + 1, name: n, ...(u && { item: SITE + u }) })) },
  { '@type': 'ItemList', '@id': SITE + url + '#lista', name, numberOfItems: list.length, itemListOrder: 'https://schema.org/ItemListOrderAscending', itemListElement: list.map(orgLd) },
] });

const byName = Object.fromEntries(companies.map(c => [c.n, c]));
function listBody(list, curTag, curCat) {
  const pNames = Object.keys(PARTNERS).filter(n => curCat && PARTNERS[n].includes(curCat) && byName[n]);
  const partners = pNames.map(n => list.find(a => a.n === n) || byName[n]);
  list = list.map(a => ({ ...a, partner: pNames.includes(a.n) }));
  return `
${partners.length ? `<section class="partners" aria-label="Kiemelt partner">
<div class="p-h"><span class="l">KIEMELT PARTNER${partners.length > 1 ? 'EK' : ''}</span><span class="paid">FIZETETT MEGJELENÉS</span><span class="n">A kiemelés nem befolyásolja a lista sorrendjét. <a href="/kivalasztasi-elvek/">Részletek</a></span></div>
<div class="p-grid${partners.length === 1 ? ' one' : ''}">
${partners.map(a => `<article class="pcard"><div class="top">${logo(a, 'l72', 72)}<div style="min-width:0"><span class="pbadge">★ KIEMELT PARTNER</span><h3>${esc(a.n)}</h3><div class="meta">${rating(a, list.some(x => x.n === a.n) ? ` · #${a.rank} a listán` : '')}</div></div></div>
<p>${esc(a.d)}</p>
<div class="tags">${tagLinks(a, curTag)}</div>
<div class="act"><a class="btn" ${ext(a)}>Kapcsolat ↗</a><a class="u" ${ext(a)}>${esc(a.url)}</a><span class="ad">Hirdetés</span></div></article>`).join('\n')}
</div>
</section>` : ''}
<div class="tools">
<span class="tot">${list.length} ügynökség</span>
<label class="sr" for="q">Keresés</label><input id="q" type="search" placeholder="Keresés név vagy szolgáltatás alapján…" autocomplete="off">
<div class="seg" role="group" aria-label="Nézet"><button type="button" data-mode="detail" aria-pressed="true">Részletes</button><button type="button" data-mode="compact" aria-pressed="false">Tömör</button></div>
</div>
<div id="list" class="cat-main" style="gap:10px">
${list.map((a, i) => `<article class="row" data-s="${esc([a.n, a.url, a.meta, ...a.services, ...a.tagSlugs.filter(s => TAG[s]).map(s => TAG[s].label), a.d].join(' ').toLowerCase())}"${i >= 10 ? ' hidden' : ''}>
<span class="rank">${a.rank}</span>${logo(a, '', 104)}
<div style="min-width:0"><div class="hd"><h3>${esc(a.n)}</h3>${rating(a)}${a.partner ? '<span class="pbadge">★ KIEMELT PARTNER · FIZETETT</span>' : ''}</div>
${a.meta ? `<div class="meta">${esc(a.meta)}</div>` : ''}<div class="tags">${tagLinks(a, curTag)}</div>
<p>${esc(a.d)}</p>${a.d.length > 190 ? '<button class="tog" type="button" aria-expanded="false">Teljes bemutatkozás</button>' : ''}</div>
<div class="cta"><a class="btn" ${ext(a)}>Kapcsolat ↗</a><a class="u" ${ext(a)}>${esc(a.url)}</a></div>
</article>`).join('\n')}
<div id="noresult" class="empty" hidden>Nincs találat. Próbálj másik kifejezést.</div>
</div>
<div id="loadmore" class="loadmore"${list.length > 10 ? '' : ' hidden'}><div class="bar"><i id="bar" style="width:${Math.round(10 / list.length * 100)}%"></i></div><span id="lm-label">10 / ${list.length} megjelenítve</span><button id="lm-btn" type="button">További ${Math.min(10, list.length - 10)} ügynökség (11–${Math.min(20, list.length)})</button></div>`;
}

const sideNav = curCat => {
  const side = arr => arr.map(c => `<a href="${href(c)}"${c.n === curCat ? ' class="cur" aria-current="page"' : ''}>${esc(c.n)}${c.ai ? '<span>→</span>' : ''}</a>`).join('');
  return `<aside class="side">
<div class="side-ai"><div class="lbl">AI &amp; ONLINE MARKETING</div>${side(aiCats)}</div>
<div class="side-ot"><div class="lbl">TOVÁBBI TOPLISTÁK</div>${side(otherCats)}</div>
</aside>`;
};

// A lista leggyakoribb címkéi, belső linkként a bevezető alatt
const topTags = (list, skip) => {
  const n = {}; for (const a of list) for (const s of a.tagSlugs) if (TAG[s] && s !== skip) n[s] = (n[s] || 0) + 1;
  const top = Object.entries(n).sort((x, y) => y[1] - x[1]).slice(0, 10);
  return top.length ? `<div class="wrap tagbar"><span>Szűrés szolgáltatásra:</span>${top.map(([s, k]) => `<a href="${tagHref(TAG[s])}">${esc(TAG[s].label)} <i>${k}</i></a>`).join('')}<a class="all" href="/cimkek/">Összes →</a></div>` : '';
};

function listingPage({ url, title, desc, crumbs, h1, q, intro, list, seoH, seo, curCat, curTag, upd = true }) {
  const om = CATS.find(c => c.n === 'Online marketing ügynökségek');
  const body = list.length ? listBody(list, curTag, curCat)
    : `<div class="empty"><h3>A lista összeállítás alatt</h3>Hamarosan itt találod a cégeket. Addig nézd meg az <a href="${href(om)}">online marketing ügynökségek toplistáját</a>.</div>`;
  return head(title, desc, pageLd({ url, name: h1, desc, crumbs, list })) + header(curCat ? 'cat' : '') + `
<main>
<div class="wrap cat-top">
<div><nav class="crumb" aria-label="Morzsamenü">${crumbs.map(([n, u], i) => i === crumbs.length - 1 ? `<span>${esc(n)}</span>` : u ? `<a href="${u}">${esc(n)}</a>` : esc(n)).join(' / ')}</nav><h1>${esc(h1)}</h1></div>
${upd ? '<div class="upd">Frissítve: 2026. október · <a href="/kivalasztasi-elvek/">Kiválasztási elvek</a></div>' : ''}
</div>
<div class="wrap"><div class="intro"><h2>${esc(q)}</h2><p>${esc(intro)}</p></div></div>
${topTags(list, curTag)}
<div class="wrap cat-body">
<div class="cat-main">${body}</div>
${sideNav(curCat)}
</div>
</main>
` + seoBlock(seoH, seo.map(([h, p]) => ({ h, p }))) + footer + end;
}

const category = cat => listingPage({
  url: href(cat), title: `${cat.h1} | Ügynökségkereső`, desc: cat.intro.split('. ')[0] + '.',
  crumbs: [['Kezdőlap', '/'], ['Ügynökségek', null], [cat.n, href(cat)]],
  h1: cat.h1, q: cat.q, intro: cat.intro, list: LISTS[cat.n], seoH: `${cat.n}: amit érdemes tudni`, seo: cat.seo, curCat: cat.n,
});

const tagPage = t => {
  const list = TAG_LISTS[t.slug];
  return listingPage({
    url: tagHref(t), title: `${t.title} (${list.length} cég) | Ügynökségkereső`, desc: t.desc,
    crumbs: [['Kezdőlap', '/'], ['Szolgáltatások', '/cimkek/'], [t.label, tagHref(t)]],
    h1: t.h1, q: `${t.label}: kiket találsz itt?`, intro: t.intro, list,
    seoH: `${t.kw[0].toUpperCase() + t.kw.slice(1)}: amit érdemes tudni`, seo: t.seo, curTag: t.slug,
  });
};

const tagIndex = () => {
  const url = '/cimkek/', h1 = 'Ügynökségek szolgáltatás szerint', desc = 'Böngéssz a hazai ügynökségek között szolgáltatás szerint: SEO, Google Ads, Facebook hirdetés, AI SEO, weboldal készítés, AI agent fejlesztés és még sok más.';
  const sorted = [...LIVE_TAGS].sort((a, b) => TAG_LISTS[b.slug].length - TAG_LISTS[a.slug].length);
  return head(`${h1} | Ügynökségkereső`, desc, jsonld({ '@context': 'https://schema.org', '@type': 'CollectionPage', url: SITE + url, name: h1, description: desc,
    hasPart: sorted.map(t => ({ '@type': 'CollectionPage', name: t.h1, url: SITE + tagHref(t) })) })) + header('') + `
<main class="wrap page wide"><nav class="crumb"><a href="/">Kezdőlap</a> / <span>Szolgáltatások</span></nav><h1>${h1}</h1>
<p>${esc(desc)}</p>
<div class="taggrid">${sorted.map(t => `<a href="${tagHref(t)}"><b>${esc(t.label)}</b><span>${TAG_LISTS[t.slug].length} cég</span></a>`).join('')}</div>
</main>
` + footer + end;
};

// ---------- Egyszerű oldalak ----------
const simple = (title, html) => head(`${title} | Ügynökségkereső`, title) + header('') +
  `\n<main class="wrap page"><nav class="crumb"><a href="/">Kezdőlap</a> / <span>${esc(title)}</span></nav><h1>${esc(title)}</h1><p>${html}</p></main>\n` + footer + end;

const FAVICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28"><rect width="28" height="28" rx="8" fill="#111318"/><circle cx="14" cy="14" r="4.25" fill="none" stroke="#0E6B4F" stroke-width="2.5"/></svg>';
// Keresőrobotok kizárása: robots.txt + X-Robots-Tag fejléc + meta robots
const ROBOTS = 'User-agent: *\nDisallow: /\n';
const HEADERS = `/*
  X-Robots-Tag: noindex, nofollow, noarchive
  X-Content-Type-Options: nosniff
  Referrer-Policy: strict-origin-when-cross-origin

/styles.css
  Cache-Control: public, max-age=3600

/app.js
  Cache-Control: public, max-age=3600
`;

async function out(path, content) {
  const dir = `${OUT}/${path}`.replace(/\/[^/]*$/, '');
  await mkdir(dir, { recursive: true });
  await writeFile(`${OUT}/${path}`, content);
}

await rm(OUT, { recursive: true, force: true });
await out('index.html', home());
for (const c of CATS) await out(`${SLUGS[c.n]}/index.html`, category(c));
for (const t of LIVE_TAGS) await out(`cimke/${t.slug}/index.html`, tagPage(t));
await out('cimkek/index.html', tagIndex());
for (const [slug, [t, html]] of Object.entries(PAGES)) await out(`${slug}/index.html`, simple(t, html));
await out('404.html', simple('Az oldal nem található', 'Lehet, hogy elírtad a címet. <a href="/">Vissza a kezdőlapra</a>.'));
await out('robots.txt', ROBOTS);
await out('_headers', HEADERS);
await out('favicon.svg', FAVICON);
await copyFile('src/styles.css', `${OUT}/styles.css`);
await copyFile('src/app.js', `${OUT}/app.js`);
await cp('src/logos', `${OUT}/logos`, { recursive: true }).catch(() => {});
console.log(`Kész: ${OUT}/ (${CATS.length} toplista, ${companies.length} cég)`);
for (const c of CATS) console.log(`  ${String(LISTS[c.n].length).padStart(3)}  ${c.n}`);
console.log(`Címkeoldalak: ${LIVE_TAGS.length}`);
for (const t of LIVE_TAGS) console.log(`  ${String(TAG_LISTS[t.slug].length).padStart(3)}  ${t.label}`);
