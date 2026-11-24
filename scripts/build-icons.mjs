// Generates every favicon / app icon / social image size from resources/brand/*.svg
//   npm run icons
// Replace resources/brand/logo-mark.svg with the company's logo mark (square) and re-run.
import { mkdirSync, readFileSync, writeFileSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pub = (p) => join(root, 'public', p);
const mark = readFileSync(join(root, 'resources/brand/logo-mark.svg'));
const NAVY = '#101D32';
mkdirSync(pub('assets/icons'), { recursive: true });

const png = (size, svg = mark) => sharp(svg, { density: Math.max(72, size * 4) }).resize(size, size).png({ compressionLevel: 9 }).toBuffer();

// Square PNG icons
const sizes = { 'favicon-16x16.png': 16, 'favicon-32x32.png': 32, 'favicon-48x48.png': 48, 'favicon-96x96.png': 96,
  'android-chrome-192x192.png': 192, 'android-chrome-512x512.png': 512, 'mstile-150x150.png': 150, 'logo-512.png': 512 };
for (const [name, size] of Object.entries(sizes)) writeFileSync(pub(`assets/icons/${name}`), await png(size));

// Apple touch icon (opaque, 180x180) — also at site root where iOS looks for it by default
const apple = await sharp(await png(180)).flatten({ background: NAVY }).png().toBuffer();
writeFileSync(pub('apple-touch-icon.png'), apple);
writeFileSync(pub('assets/icons/apple-touch-icon.png'), apple);

// Maskable icon: logo at 70% inside a full-bleed safe zone
const inner = await png(358);
const maskable = await sharp({ create: { width: 512, height: 512, channels: 4, background: NAVY } })
  .composite([{ input: inner, gravity: 'center' }]).png().toBuffer();
writeFileSync(pub('assets/icons/maskable-512x512.png'), maskable);

// favicon.ico with 16, 32 and 48 px PNG images embedded
const icoImgs = await Promise.all([16, 32, 48].map((s) => png(s)));
const header = Buffer.alloc(6 + 16 * icoImgs.length);
header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(icoImgs.length, 4);
let offset = header.length;
icoImgs.forEach((buf, i) => {
  const s = [16, 32, 48][i];
  const o = 6 + i * 16;
  header.writeUInt8(s, o); header.writeUInt8(s, o + 1); header.writeUInt8(0, o + 2); header.writeUInt8(0, o + 3);
  header.writeUInt16LE(1, o + 4); header.writeUInt16LE(32, o + 6);
  header.writeUInt32LE(buf.length, o + 8); header.writeUInt32LE(offset, o + 12);
  offset += buf.length;
});
writeFileSync(pub('favicon.ico'), Buffer.concat([header, ...icoImgs]));

// SVG favicon + Safari pinned tab
copyFileSync(join(root, 'resources/brand/logo-mark.svg'), pub('favicon.svg'));
copyFileSync(join(root, 'resources/brand/logo-mark.svg'), pub('assets/images/favicon.svg'));
copyFileSync(join(root, 'resources/brand/logo-mono.svg'), pub('assets/icons/safari-pinned-tab.svg'));

// Default Open Graph / social share image 1200x630
const markInner = readFileSync(join(root, 'resources/brand/logo-mark.svg'), 'utf8').replace(/<svg[^>]*>|<\/svg>/g, '');
const ogSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs><pattern id="g" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#ffffff" stroke-opacity=".05"/></pattern></defs>
  <rect width="1200" height="630" fill="${NAVY}"/><rect width="1200" height="630" fill="url(#g)"/>
  <rect x="0" y="600" width="1200" height="30" fill="#F28C28"/>
  <g transform="translate(90 150) scale(4)">${markInner}</g>
  <text x="90" y="400" font-family="Arial, Helvetica, sans-serif" font-size="62" font-weight="700" fill="#ffffff">ENGINEERING STRENGTH.</text>
  <text x="90" y="472" font-family="Arial, Helvetica, sans-serif" font-size="62" font-weight="700" fill="#F28C28">POWERING PERFORMANCE.</text>
  <text x="90" y="535" font-family="Arial, Helvetica, sans-serif" font-size="28" fill="#ffffff" fill-opacity=".7">Crane Components · Hydraulic Systems · Custom Engineering</text>
</svg>`;
writeFileSync(pub('assets/images/og-default.png'), await sharp(Buffer.from(ogSvg)).png({ compressionLevel: 9 }).toBuffer());

console.log(`Icons generated: favicon.ico (16/32/48), favicon.svg, apple-touch-icon, ${Object.keys(sizes).length} PNG sizes, maskable icon, pinned tab, OG image.`);
