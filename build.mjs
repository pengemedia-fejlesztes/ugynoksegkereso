// Statikus oldalgenerátor: node build.mjs → public/
import { mkdir, writeFile, copyFile, rm } from 'node:fs/promises';
import { CATS, AGENCIES, PARTNERS, HOME_SEO, LOGO_BASE } from './src/data.mjs';

const OUT = 'public';
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
// Melyik kategóriához van már valódi ügynökséglista
const LISTS = { 'Online marketing ügynökségek': AGENCIES };
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
const agencies = AGENCIES.map(([n, r, logo, url, tags, d], i) => ({
  n, url, d, logo: LOGO_BASE + logo, tags: tags.split('|'), partner: PARTNERS.includes(n),
  rank: String(i + 1).padStart(2, '0'), ini: ini(n), stars: stars(r), r: r.toFixed(1).replace('.', ','),
}));
LISTS['Online marketing ügynökségek'] = agencies;
const aiCats = CATS.filter(c => c.ai), otherCats = CATS.filter(c => !c.ai);
const ext = a => `href="https://${esc(a.url)}" target="_blank" rel="nofollow noopener"`;
const logo = (a, cls, size) => `<span class="logo ${cls}"><span>${esc(a.ini)}</span><img src="${esc(a.logo)}" alt="${esc(a.n)} logó" loading="lazy" decoding="async" width="${size}" height="${size}"></span>`;

const head = (title, desc) => `<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<meta name="robots" content="noindex, nofollow, noarchive">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/styles.css">
<script src="/app.js" defer></script>
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
<div><div class="lbl">OLDALAK</div><div class="col"><a href="/">Kezdőlap</a><a href="/rolunk/">Rólunk</a><a href="/blog/">Blog</a><a href="/kapcsolat/">Kapcsolat</a></div></div>
<div><div class="lbl">INFORMÁCIÓ</div><div class="col">${infoLinks}</div></div>
</div>
<div class="fbot"><span>© 2026 Ügynökségkereső</span><span>Magyar · <a href="#" aria-disabled="true" title="Hamarosan">English</a></span></div>
</div></footer>`;
const footerSmall = `<footer class="ftr ftr-s"><div class="wrap"><span class="b">Ügynökségkereső</span><a class="first" href="/kivalasztasi-elvek/">A listák kiválasztási elvei</a><a href="/aszf/">ÁSZF</a><a href="/adatvedelem/">Adatvédelmi tájékoztató</a><span class="c">© 2026</span></div></footer>`;
const end = '\n</body>\n</html>\n';

// ---------- Kezdőlap ----------
function home() {
  const chip0 = CATS[0];
  const featured = CATS.slice(1, 5);
  const om = CATS.find(c => c.n === 'Online marketing ügynökségek');
  return head('Ügynökségkereső – a legjobb magyar ügynökségek egy helyen', 'Rangsorolt toplisták online marketing, Google Ads, Facebook hirdetés, AI SEO és AI agent fejlesztő cégekről.') + header('home') + `
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
${agencies.slice(0, 5).map(a => `<div class="t5row"><span class="rank">${a.rank}</span>${logo(a, 'l52', 52)}<span class="nm">${esc(a.n)}</span><span class="ds">${esc(a.d.length > 140 ? a.d.slice(0, a.d.lastIndexOf(' ', 140)) + '…' : a.d)}</span><span class="rate"><span class="stars">${a.stars}</span><b>${a.r}/5</b></span><a class="btn" ${ext(a)}>Kapcsolat ↗</a></div>`).join('\n')}
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

// ---------- Toplista oldal ----------
function category(cat) {
  const list = LISTS[cat.n] || [];
  const partners = list.filter(a => a.partner);
  const side = arr => arr.map(c => `<a href="${href(c)}"${c.n === cat.n ? ' class="cur" aria-current="page"' : ''}>${esc(c.n)}${c.ai ? '<span>→</span>' : ''}</a>`).join('');
  const om = CATS.find(c => c.n === 'Online marketing ügynökségek');
  const body = list.length ? `
${partners.length ? `<section class="partners" aria-label="Kiemelt partnerek">
<div class="p-h"><span class="l">KIEMELT PARTNEREK</span><span class="paid">FIZETETT MEGJELENÉS</span><span class="n">A kiemelés nem befolyásolja a toplista sorrendjét. <a href="/kivalasztasi-elvek/">Részletek</a></span></div>
<div class="p-grid">
${partners.map(a => `<article class="pcard"><div class="top">${logo(a, 'l72', 72)}<div style="min-width:0"><span class="pbadge">★ KIEMELT PARTNER</span><h3>${esc(a.n)}</h3><div class="meta"><span class="stars">${a.stars}</span><b>${a.r}/5 · #${a.rank} a listán</b></div></div></div>
<p>${esc(a.d)}</p>
<div class="act"><a class="btn" ${ext(a)}>Kapcsolat ↗</a><a class="u" ${ext(a)}>${esc(a.url)}</a><span class="ad">Hirdetés</span></div></article>`).join('\n')}
</div>
</section>` : ''}
<div class="tools">
<span class="tot">${list.length} ügynökség</span>
<label class="sr" for="q">Keresés</label><input id="q" type="search" placeholder="Keresés név vagy szolgáltatás alapján…" autocomplete="off">
<div class="seg" role="group" aria-label="Nézet"><button type="button" data-mode="detail" aria-pressed="true">Részletes</button><button type="button" data-mode="compact" aria-pressed="false">Tömör</button></div>
</div>
<div id="list" class="cat-main" style="gap:10px">
${list.map((a, i) => `<article class="row" data-s="${esc((a.n + ' ' + a.tags.join(' ') + ' ' + a.d).toLowerCase())}"${i >= 10 ? ' hidden' : ''}>
<span class="rank">${a.rank}</span>${logo(a, '', 104)}
<div style="min-width:0"><div class="hd"><h3>${esc(a.n)}</h3><span class="stars">${a.stars}</span><b>${a.r}/5</b>${a.partner ? '<span class="pbadge">★ KIEMELT PARTNER · FIZETETT</span>' : ''}</div>
<div class="tags">${a.tags.map(t => `<span>${esc(t)}</span>`).join('')}</div>
<p>${esc(a.d)}</p>${a.d.length > 190 ? '<button class="tog" type="button" aria-expanded="false">Teljes bemutatkozás</button>' : ''}</div>
<div class="cta"><a class="btn" ${ext(a)}>Kapcsolat ↗</a><a class="u" ${ext(a)}>${esc(a.url)}</a></div>
</article>`).join('\n')}
<div id="noresult" class="empty" hidden>Nincs találat. Próbálj másik kifejezést.</div>
</div>
<div id="loadmore" class="loadmore"${list.length > 10 ? '' : ' hidden'}><div class="bar"><i id="bar" style="width:${Math.round(10 / list.length * 100)}%"></i></div><span id="lm-label">10 / ${list.length} megjelenítve</span><button id="lm-btn" type="button">További 10 ügynökség (11–${Math.min(20, list.length)})</button></div>`
    : `<div class="empty"><h3>A toplista összeállítás alatt</h3>Hamarosan itt találod a legjobb hazai ${esc(cat.n.toLowerCase())} rangsorát. Addig nézd meg az <a href="${href(om)}">online marketing ügynökségek toplistáját</a>.</div>`;

  return head(`${cat.h1} | Ügynökségkereső`, cat.intro.split('. ')[0] + '.') + header('cat') + `
<main>
<div class="wrap cat-top">
<div><nav class="crumb" aria-label="Morzsamenü"><a href="/">Kezdőlap</a> / Ügynökségek / <span>${esc(cat.n)}</span></nav><h1>${esc(cat.h1)}</h1></div>
<div class="upd">Frissítve: 2026. október · <a href="/kivalasztasi-elvek/">Kiválasztási elvek</a></div>
</div>
<div class="wrap"><div class="intro"><h2>${esc(cat.q)}</h2><p>${esc(cat.intro)}</p></div></div>
<div class="wrap cat-body">
<div class="cat-main">${body}</div>
<aside class="side">
<div class="side-ai"><div class="lbl">AI &amp; ONLINE MARKETING</div>${side(aiCats)}</div>
<div class="side-ot"><div class="lbl">TOVÁBBI TOPLISTÁK</div>${side(otherCats)}</div>
</aside>
</div>
</main>
` + seoBlock(`${cat.n}: amit érdemes tudni`, cat.seo.map(([h, p]) => ({ h, p }))) + footerSmall + end;
}

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
for (const [slug, [t, html]] of Object.entries(PAGES)) await out(`${slug}/index.html`, simple(t, html));
await out('404.html', simple('Az oldal nem található', 'Lehet, hogy elírtad a címet. <a href="/">Vissza a kezdőlapra</a>.'));
await out('robots.txt', ROBOTS);
await out('_headers', HEADERS);
await out('favicon.svg', FAVICON);
await copyFile('src/styles.css', `${OUT}/styles.css`);
await copyFile('src/app.js', `${OUT}/app.js`);
console.log(`Kész: ${OUT}/ (${CATS.length} toplista, ${agencies.length} ügynökség)`);
