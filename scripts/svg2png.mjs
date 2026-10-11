// SVG -> PNG (900 px széles, átlátszó háttér): node scripts/svg2png.mjs <be.svg> <ki.png>
// Függőség: @resvg/resvg-js (npm i --no-save @resvg/resvg-js, vagy NODE_PATH-on elérhető példány)
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
const { Resvg } = createRequire(import.meta.url)('@resvg/resvg-js');
const [src, dst] = process.argv.slice(2);
const r = new Resvg(readFileSync(src), { fitTo: { mode: 'width', value: 900 }, background: 'rgba(0,0,0,0)', font: { loadSystemFonts: true } });
writeFileSync(dst, r.render().asPng());
