# Ügynökségkereső – fejlesztés és élesítés

Statikus oldal, függőségek nélkül. Az ügynökségadatok forrása a `src/ugynoksegek.json` (165 cég, 2026-10-11-i export). Csak a `kész` státuszú cégek jelennek meg, illetve az `ellenőrizendő` státuszúak közül azok, amelyekhez a régi listából van leírás. A kategóriaszövegek a `src/data.mjs`-ben vannak; onnan jön a régi toplista sorrendje, az értékelések és a logók is. A sablonok a `build.mjs`-ben vannak.

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
