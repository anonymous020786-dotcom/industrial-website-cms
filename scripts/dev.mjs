// Local development: PHP built-in server + Tailwind watcher + JS/CSS minifier.
//   npm run dev   →  http://localhost:8000  (admin: /admin)
import { spawn, spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const port = process.env.PORT || 8000;
const win = process.platform === 'win32';
// Fixed command strings (no user input), run through the shell so php/npx resolve on Windows.
const run = (cmd) => spawn(cmd, { cwd: root, stdio: 'inherit', shell: true });

const children = [
  run(`php -S localhost:${port} -t public public/router.php`),
  run('npx tailwindcss -c tailwind.config.js -i resources/tailwind.css -o public/assets/css/tailwind.css --watch'),
  run('node scripts/build-assets.mjs --watch-code'), // re-minifies resources/css + resources/js on save
];
console.log(`\n  Website: http://localhost:${port}\n  Admin:   http://localhost:${port}/admin\n`);

// Stop the whole process tree. On Windows, killing the shell leaves php.exe running (it would keep
// the port and its OPcache memory), so use taskkill /T on each child's tree.
let stopping = false;
const stop = () => {
  if (stopping) return;
  stopping = true;
  for (const c of children) {
    if (!c.pid) continue;
    if (win) spawnSync('taskkill', ['/pid', String(c.pid), '/T', '/F'], { stdio: 'ignore' });
    else try { c.kill('SIGTERM'); } catch { /* already exited */ }
  }
  process.exit(0);
};
['SIGINT', 'SIGTERM', 'SIGHUP', 'SIGBREAK'].forEach((s) => process.on(s, stop));
process.on('exit', () => { if (!stopping && win) for (const c of children) if (c.pid) spawnSync('taskkill', ['/pid', String(c.pid), '/T', '/F'], { stdio: 'ignore' }); });
