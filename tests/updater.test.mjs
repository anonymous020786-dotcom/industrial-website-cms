// Automatic updates (app/lib/updater.php) against a stand-in for GitHub, installed into a temporary
// copy of the site that uses the Hostinger layout (code next to public_html). The real site's files
// are never touched; only the shared database gets the deployed_* settings, removed afterwards.
import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { mkdtempSync, mkdirSync, cpSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync, execFile } from 'node:child_process';
import { promisify } from 'node:util';
const execFileAsync = promisify(execFile);
import { ROOT, PHP, setSetting, getSetting } from './lib/client.mjs';

const REPO = 'acme/industrial-site';
const shaA = 'a'.repeat(40);
const shaB = 'b'.repeat(40);

/** Build a GitHub-style source zip ("<repo>-<sha>/…") from a folder, using PHP's ZipArchive. */
function zipRelease(dir, sha, out) {
  const code = `$z=new ZipArchive;$z->open($argv[2],ZipArchive::CREATE|ZipArchive::OVERWRITE);$base=realpath($argv[1]);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
    foreach($it as $f){$rel=str_replace('\\\\','/',substr($f->getPathname(),strlen($base)+1));$z->addFile($f->getPathname(),$argv[3].'/'.$rel);}$z->close();`;
  execFileSync(PHP, ['-r', code, dir, out, `industrial-site-${sha}`]);
}

test('automatic updates: installs tested releases in the public_html layout, keeps server files, removes deleted files', async () => {
  const work = mkdtempSync(join(tmpdir(), 'upd-'));
  const site = join(work, 'site');          // the "server": code here, web root in site/public_html
  const src = join(work, 'src');            // the "repository" at release A, then B
  const zips = {};
  let head = shaA;
  const server = http.createServer((req, res) => {
    if (req.url === `/${REPO}.git/info/refs?service=git-upload-pack`) {
      res.writeHead(200, { 'Content-Type': 'application/x-git-upload-pack-advertisement' });
      return res.end(`001e# service=git-upload-pack\n0000${'0000'}${head} HEAD\u0000multi_ack\n003f${head} refs/heads/main\n0042${head} refs/heads/release\n0000`);
    }
    const m = req.url.match(new RegExp(`^/${REPO}/zip/([0-9a-f]{40})$`));
    if (m && zips[m[1]]) { res.writeHead(200, { 'Content-Type': 'application/zip' }); return res.end(readFileSync(zips[m[1]])); }
    res.writeHead(404); res.end();
  });
  await new Promise((r) => server.listen(18082, '127.0.0.1', r));
  try {
    // Repository content at release A (current code + a file that release B removes + things never deployed)
    for (const d of ['app', 'content', 'database', 'public']) cpSync(join(ROOT, d), join(src, d), { recursive: true, filter: (p) => !/[\\/](config\.php|storage[\\/].+|uploads[\\/].+)$/.test(p) });
    mkdirSync(join(src, 'tests'), { recursive: true });
    writeFileSync(join(src, 'tests', 'secret-test.php'), '<?php // never deployed');
    writeFileSync(join(src, 'public', 'old-only.txt'), 'only in release A');
    zips[shaA] = join(work, 'a.zip'); zipRelease(src, shaA, zips[shaA]);
    // Release B: old-only.txt removed, a new file added
    rmSync(join(src, 'public', 'old-only.txt'));
    writeFileSync(join(src, 'public', 'release-b.txt'), 'new in release B');
    zips[shaB] = join(work, 'b.zip'); zipRelease(src, shaB, zips[shaB]);

    // The "server" before the first automatic update: an older install with server-only files
    cpSync(join(ROOT, 'app'), join(site, 'app'), { recursive: true, filter: (p) => !/[\\/]storage[\\/].+$/.test(p) });
    mkdirSync(join(site, 'app', 'storage', 'backups'), { recursive: true });
    mkdirSync(join(site, 'public_html', 'uploads'), { recursive: true });
    mkdirSync(join(site, 'public_html', '.well-known'), { recursive: true });
    writeFileSync(join(site, 'app', 'storage', 'backups', 'backup.sql.gz'), 'keep');
    writeFileSync(join(site, 'app', 'storage', 'installed.lock'), 'keep');
    writeFileSync(join(site, 'public_html', 'uploads', 'photo.webp'), 'keep');
    writeFileSync(join(site, 'public_html', '.well-known', 'acme'), 'keep');
    const cfg = readFileSync(join(ROOT, 'app', 'config.php'), 'utf8').replace(/\);\s*$/, `  'auto_update' => ['repo' => '${REPO}', 'branch' => 'release', 'github' => 'http://127.0.0.1:18082', 'codeload' => 'http://127.0.0.1:18082'],\n);\n`);
    writeFileSync(join(site, 'app', 'config.php'), cfg);
    const cfgBefore = readFileSync(join(site, 'app', 'config.php'), 'utf8');
    setSetting('deployed_sha', '');
    // Async: the fake GitHub server runs in this process, so the event loop must stay free
    const cli = async (...a) => {
      try { return (await execFileAsync(PHP, [join(site, 'app', 'cli.php'), ...a], { encoding: 'utf8', timeout: 120000 })).stdout.trim(); }
      catch (e) { return String(e.stdout || e.message).trim(); }
    };

    assert.match(await cli('update', '--check'), /update available/);
    const r1 = await cli('update');
    assert.match(r1, new RegExp(`^updated to ${shaA.slice(0, 7)} `), r1);
    assert.ok(existsSync(join(site, 'public_html', 'index.php')), 'web files in public_html');
    assert.ok(existsSync(join(site, 'public_html', 'old-only.txt')));
    assert.ok(!existsSync(join(site, 'public')), 'no public/ folder created');
    assert.ok(!existsSync(join(site, 'public_html', 'router.php')), 'dev router not published');
    assert.ok(!existsSync(join(site, 'tests')), 'tests never deployed');
    assert.ok(!existsSync(join(site, 'public_html', 'app')), 'code stays outside the web root');
    for (const keep of ['app/storage/backups/backup.sql.gz', 'public_html/uploads/photo.webp', 'public_html/.well-known/acme']) assert.ok(existsSync(join(site, keep)), keep + ' kept');
    assert.equal(readFileSync(join(site, 'app', 'config.php'), 'utf8'), cfgBefore, 'config untouched');
    assert.equal(getSetting('deployed_sha'), shaA);

    assert.match(await cli('update'), /^up to date/);

    head = shaB; // CI promoted a new commit
    const r2 = await cli('update');
    assert.match(r2, new RegExp(`updated ${shaA.slice(0, 7)} → ${shaB.slice(0, 7)} \\(\\d+ file\\(s\\) changed, 1 removed`), r2);
    assert.ok(existsSync(join(site, 'public_html', 'release-b.txt')));
    assert.ok(!existsSync(join(site, 'public_html', 'old-only.txt')), 'file removed from the repo is removed on the server');
    assert.ok(existsSync(join(site, 'public_html', 'uploads', 'photo.webp')), 'uploads still kept');

    head = 'c'.repeat(40); // a release that cannot be downloaded: nothing changes
    assert.match(await cli('update'), /^ERROR: GitHub answered HTTP 404/);
    assert.equal(getSetting('deployed_sha'), shaB);
    assert.ok(existsSync(join(site, 'public_html', 'index.php')));
  } finally {
    server.close();
    for (const k of ['deployed_sha', 'deployed_at', 'update_checked_at', 'update_error']) setSetting(k, null);
    rmSync(work, { recursive: true, force: true });
  }
});
