# Ügynökségkereső – fejlesztés és élesítés

Statikus oldal, függőségek nélkül. A tartalom a `src/data.mjs`-ben van, a sablonok a `build.mjs`-ben.

```
npm run build   # → public/
npm run dev     # build + helyi szerver: http://localhost:8080
```

## Cloudflare Pages

- Framework preset: None
- Build command: `npm run build`
- Build output directory: `public`
- Custom domain: pl. `ugynoksegek.pmdev.hu`

## Keresőrobotok kizárása

Az oldal publikus URL-en elérhető, de nem indexelhető:

- `robots.txt`: `Disallow: /`
- `_headers`: `X-Robots-Tag: noindex, nofollow, noarchive` minden válaszon
- minden oldalon `<meta name="robots" content="noindex, nofollow, noarchive">`

Élesítéskor ezt a hármat kell eltávolítani a `build.mjs`-ből.
