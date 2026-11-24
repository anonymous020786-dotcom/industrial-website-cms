/**
 * Mirror every Unsplash photo the site uses into public/assets/stock as optimised WebP,
 * so pages never depend on images.unsplash.com at runtime.
 *
 *   npm run images            download anything missing
 *   npm run images -- --force re-download everything
 *
 * Photo ids come from the code, seed files, blog sources and (if MySQL is reachable) the
 * database via `php app/cli.php stock-ids`. img_tag() serves the local copy automatically
 * and falls back to Unsplash for any photo not mirrored yet.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const out = join(root, 'public/assets/stock');
const WIDTHS = [480, 800, 1200, 1600, 2400]; // keep in sync with STOCK_WIDTHS in app/lib/helpers.php
const force = process.argv.includes('--force');
const ID = /images\.unsplash\.com\/(photo-[\w-]+)/g;

const ids = new Set();
const scan = (dir) => {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (name === 'node_modules' || name === 'storage' || name === 'stock') continue;
    if (statSync(p).isDirectory()) scan(p);
    else if (/\.(php|json|md|mjs)$/.test(name)) for (const m of readFileSync(p, 'utf8').matchAll(ID)) ids.add(m[1]);
  }
};
['app', 'database', 'content'].forEach((d) => existsSync(join(root, d)) && scan(join(root, d)));
try {
  const fromDb = execFileSync('php', [join(root, 'app/cli.php'), 'stock-ids'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  fromDb.split(/\s+/).filter((s) => s.startsWith('photo-')).forEach((id) => ids.add(id));
} catch {
  console.warn('! Database not reachable — mirroring photos referenced in files only.');
}

mkdirSync(out, { recursive: true });
const todo = [...ids].filter((id) => force || WIDTHS.some((w) => !existsSync(join(out, `${id}-${w}.webp`))));
console.log(`${ids.size} stock photos referenced, ${todo.length} to mirror.`);

async function fetchPhoto(id) {
  const url = `https://images.unsplash.com/${id}?fm=jpg&q=90&w=2400&fit=max`;
  for (let attempt = 1; attempt <= 4; attempt++) {
    try {
      const res = await fetch(url, { signal: AbortSignal.timeout(60_000) });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return Buffer.from(await res.arrayBuffer());
    } catch (err) {
      if (attempt === 4) throw err;
      await new Promise((r) => setTimeout(r, attempt * 2000));
    }
  }
}

let done = 0;
const failed = [];
async function worker(queue) {
  for (let id = queue.shift(); id; id = queue.shift()) {
    try {
      const buf = await fetchPhoto(id);
      // withoutEnlargement: small originals keep their size but every width file still exists
      await Promise.all(WIDTHS.map((w) => sharp(buf).rotate().resize({ width: w, withoutEnlargement: true })
        .webp({ quality: w <= 800 ? 72 : 76, effort: 5 }).toFile(join(out, `${id}-${w}.webp`))));
      console.log(`  ✓ ${id} (${++done}/${todo.length})`);
    } catch (err) {
      failed.push(id);
      console.warn(`  ✗ ${id}: ${err.message}`);
    }
  }
}
const queue = [...todo];
await Promise.all(Array.from({ length: 4 }, () => worker(queue)));

const bytes = readdirSync(out).reduce((n, f) => n + statSync(join(out, f)).size, 0);
console.log(`Stock photos: ${ids.size - failed.length}/${ids.size} mirrored (${(bytes / 1048576).toFixed(1)} MB in public/assets/stock).`);
if (failed.length) {
  console.log('Failed (still served from Unsplash; re-run to retry):', failed.join(', '));
  process.exitCode = 1;
}
