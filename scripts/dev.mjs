// Local development: PHP built-in server + Tailwind watcher.
//   npm run dev   →  http://localhost:8000  (admin: /admin)
import { spawn } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const port = process.env.PORT || 8000;
// Fixed command strings (no user input), run through the shell so php/npx resolve on Windows.
const run = (cmd) => spawn(cmd, { cwd: root, stdio: 'inherit', shell: true });

const php = run(`php -S localhost:${port} -t public public/router.php`);
const css = run('npx tailwindcss -c tailwind.config.js -i resources/tailwind.css -o public/assets/css/tailwind.css --watch');
const code = run('node scripts/build-assets.mjs --watch-code'); // re-minifies resources/css + resources/js on save
console.log(`\n  Website: http://localhost:${port}\n  Admin:   http://localhost:${port}/admin\n`);

const stop = () => { php.kill(); css.kill(); code.kill(); process.exit(0); };
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
