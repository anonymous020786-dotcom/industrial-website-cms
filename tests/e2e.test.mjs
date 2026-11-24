// End-to-end tests against a running site (php -S or Apache) with its database.
//   BASE_URL=http://localhost:8000 ADMIN_LOGIN=… ADMIN_PASSWORD=… node --test tests/
// Everything a test creates is prefixed "e2e" and removed afterwards.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { gunzipSync } from 'node:zlib';
import sharp from 'sharp';
import { Client, BASE, ROOT, TS_TOKEN, token, formToken, flashText, sql, setSetting, getSetting, db, php, sleep } from './lib/client.mjs';
import { SoftAuthenticator } from './lib/authenticator.mjs';
import { totp } from './lib/totp.mjs';

const ADMIN = process.env.ADMIN_LOGIN || 'admin@example.com';
const PASSWORD = process.env.ADMIN_PASSWORD || (existsSync(join(ROOT, 'app/storage/.dev-admin-password')) ? readFileSync(join(ROOT, 'app/storage/.dev-admin-password'), 'utf8').trim() : '');
const ORIGIN = new URL(BASE).origin;
const DEBUG = db({ debug: true });
const adminId = () => Number(sql('SELECT id FROM users WHERE email = ? OR username = ?', [ADMIN, ADMIN])[0].id);

const admin = new Client();
let csrf = '';

test.before(async () => {
  assert.ok(PASSWORD, 'Set ADMIN_PASSWORD');
  sql('DELETE FROM rate_limits');
  sql('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL WHERE id = ?', [adminId()]);
  sql('DELETE FROM passkeys WHERE name LIKE ?', ['e2e%']);
  setSetting('admin_otp_enabled', false);
  const to = await admin.login(ADMIN, PASSWORD);
  assert.match(to, /\/admin$/, `login should land on the dashboard, got "${to}"`);
  csrf = token((await admin.get('admin')).text);
  assert.ok(csrf, 'CSRF token on dashboard');
});

test.after(async () => {
  for (const m of sql("SELECT id FROM media WHERE original_name LIKE 'e2e-%'")) await admin.post(`admin/media/${m.id}/delete`, { _token: csrf });
  sql("DELETE FROM redirects WHERE source LIKE '/e2e%' OR source LIKE '/projects/e2e%' OR note LIKE 'e2e%'");
  sql("DELETE FROM not_found_log WHERE path LIKE '/e2e%'");
  sql("DELETE FROM leads WHERE email LIKE 'e2e+%@example.com'");
  sql("DELETE FROM projects WHERE slug LIKE 'e2e%'");
  sql("DELETE FROM certificates WHERE name LIKE 'e2e%'");
  sql("DELETE FROM passkeys WHERE name LIKE 'e2e%'");
  sql('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL WHERE id = ?', [adminId()]);
  sql('DELETE FROM recovery_codes WHERE user_id = ?', [adminId()]);
  sql("UPDATE products SET datasheet = NULL WHERE datasheet LIKE '%e2e%'");
  setSetting('catalogue_pdf', '');
  sql('DELETE FROM rate_limits');
});

/* ------------------------------------------------------------------ */
test('public pages, sitemap and security headers', async () => {
  const c = new Client();
  for (const p of ['', 'products', 'products/hydraulic-cylinders', 'industries', 'quality', 'quote', 'contact', 'blog', 'sitemap.xml', 'robots.txt', 'site.webmanifest']) {
    const r = await c.get(p);
    assert.equal(r.status, 200, `/${p} → ${r.status}`);
  }
  const home = await c.get('');
  assert.match(home.res.headers.get('content-security-policy') || '', /frame-ancestors 'self'/);
  assert.equal(home.res.headers.get('x-content-type-options'), 'nosniff');
  const adm = await c.get('admin/reports');
  assert.equal(adm.status, 302, 'tools require login');
  assert.match(adm.location, /admin\/login/);
});

/* ------------------------------------------------------------------ */
test('redirects: exact, wildcard, loop protection, CSV import, 404 log', async () => {
  let r = await admin.post('admin/redirects/save', { _token: csrf, id: 0, source: '/e2e-old-page.html', target: '/products', code: 301, note: 'e2e' });
  assert.equal(r.status, 302);
  const c = new Client();
  r = await c.get('e2e-old-page.html?utm_source=x');
  assert.equal(r.status, 301);
  assert.equal(new URL(r.location, BASE).pathname, '/products');
  assert.match(r.location, /utm_source=x/, 'query string is kept');
  assert.equal(Number(sql("SELECT hits FROM redirects WHERE source = '/e2e-old-page.html'")[0].hits), 1);

  await admin.post('admin/redirects/save', { _token: csrf, id: 0, source: '/e2e-old-blog/*', target: '/blog/*', code: 301, note: 'e2e' });
  r = await c.get('e2e-old-blog/how-to-choose');
  assert.equal(r.status, 301);
  assert.equal(new URL(r.location, BASE).pathname, '/blog/how-to-choose');

  await admin.post('admin/redirects/save', { _token: csrf, id: 0, source: '/e2e-a', target: '/e2e-b', code: 301, note: 'e2e' });
  r = await admin.post('admin/redirects/save', { _token: csrf, id: 0, source: '/e2e-b', target: '/e2e-a', code: 301, note: 'e2e' });
  const page = await admin.get(r.location.replace(BASE, ''));
  assert.ok(flashText(page.text).some((f) => /loop/i.test(f)), 'loop rejected');
  assert.equal(sql("SELECT COUNT(*) n FROM redirects WHERE source = '/e2e-b'")[0].n, 0);

  const fd = new FormData();
  fd.append('_token', csrf);
  fd.append('csv', new Blob(['source,target,code,note\n/e2e-csv-1,/contact,301,e2e import\n/e2e-csv-2,https://example.com/x,302,e2e import\n,/nowhere\n'], { type: 'text/csv' }), 'r.csv');
  await admin.req('admin/redirects/import', { method: 'POST', body: fd });
  assert.equal(sql("SELECT COUNT(*) n FROM redirects WHERE source LIKE '/e2e-csv-%'")[0].n, 2);
  r = await c.get('e2e-csv-2');
  assert.equal(r.status, 302);
  assert.equal(r.location, 'https://example.com/x');

  r = await c.get('e2e-missing-page-xyz');
  assert.equal(r.status, 404);
  assert.equal(sql("SELECT COUNT(*) n FROM not_found_log WHERE path = '/e2e-missing-page-xyz'")[0].n, 1);
  const log = await admin.get('admin/redirects?tab=404');
  assert.match(log.text, /e2e-missing-page-xyz/);
  const csv = await admin.get('admin/redirects/export');
  assert.match(csv.type, /text\/csv/);
  assert.match(csv.text, /e2e-old-page\.html/);
});

/* ------------------------------------------------------------------ */
test('quote list: several items in one request, validated server-side', async () => {
  sql('DELETE FROM rate_limits');
  const c = new Client();
  const page = await c.get('quote');
  assert.match(page.text, /data-rfq-picker/);
  const ft = formToken(page.text);
  await sleep(3200); // forms submitted in under 3 s are treated as bots
  const base = { form: 'quote', _ft: ft, fullName: 'E2E Buyer', company: 'E2E Ltd', email: 'e2e+quote@example.com', phone: '+44 20 0000 0000',
    product: '', message: 'Please quote the items in the list.', consent: '1', 'cf-turnstile-response': TS_TOKEN };
  let r = await c.post('lead', { ...base, 'items[0][slug]': 'hydraulic-cylinders', 'items[0][name]': 'SPOOFED NAME', 'items[0][part]': 'HC-100', 'items[0][qty]': '4',
    'items[1][name]': 'Custom pin', 'items[1][part]': 'P-22', 'items[1][qty]': '0' }, { Accept: 'application/json' });
  assert.equal(r.status, 422, 'zero quantity rejected');
  assert.match(JSON.parse(r.text).errors.items, /quantity/i);
  r = await c.post('lead', { ...base, 'items[0][slug]': 'hydraulic-cylinders', 'items[0][name]': 'SPOOFED NAME', 'items[0][part]': 'HC-100', 'items[0][qty]': '4',
    'items[1][name]': 'Custom pin', 'items[1][part]': 'P-22', 'items[1][qty]': '10', 'items[1][notes]': 'EN8 steel' }, { Accept: 'application/json' });
  assert.equal(r.status, 200, r.text);
  const lead = sql("SELECT product, items FROM leads WHERE email = 'e2e+quote@example.com' ORDER BY id DESC LIMIT 1")[0];
  assert.equal(lead.product, 'Multiple items (2)');
  const items = JSON.parse(lead.items);
  assert.equal(items.length, 2);
  assert.equal(items[0].name, 'Hydraulic Cylinders', 'product name comes from the catalogue, not the browser');
  assert.deepEqual([items[0].qty, items[1].qty, items[1].part], [4, 10, 'P-22']);
  const show = await admin.get(`admin/leads/${sql("SELECT id FROM leads WHERE email = 'e2e+quote@example.com' ORDER BY id DESC LIMIT 1")[0].id}`);
  assert.match(show.text, /Items requested \(2\)/);
});

/* ------------------------------------------------------------------ */
let pdfPath = '';
test('media: image optimisation to WebP sizes, PDF uploads, rejects fake PDFs', async () => {
  const big = await sharp({ create: { width: 3200, height: 2000, channels: 3, background: '#c0392b' } }).jpeg({ quality: 95 }).toBuffer();
  let fd = new FormData();
  fd.append('_token', csrf);
  fd.append('file', new Blob([big], { type: 'image/jpeg' }), 'e2e-photo.jpg');
  let r = await admin.postMultipart('admin/media/upload', fd, csrf);
  assert.equal(r.status, 200, r.text);
  assert.deepEqual(r.json.optimized.sizes, [480, 800, 1200, 1600]);
  const m = sql('SELECT * FROM media WHERE path = ?', [r.json.path])[0];
  assert.equal(Number(m.width), 2560, 'capped at 2560 px');
  const webp = await new Client().get(r.json.path.replace(/\.jpg$/, '-800.webp'));
  assert.equal(webp.status, 200);
  assert.match(webp.type, /image\/webp/);
  const imgId = m.id;

  fd = new FormData();
  fd.append('_token', csrf);
  fd.append('file', new Blob([Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n')], { type: 'application/pdf' }), 'e2e-datasheet.pdf');
  r = await admin.postMultipart('admin/media/upload', fd, csrf);
  assert.equal(r.status, 200, r.text);
  assert.match(r.json.path, /^uploads\/docs\/.+\.pdf$/);
  pdfPath = r.json.path;

  fd = new FormData();
  fd.append('_token', csrf);
  fd.append('file', new Blob(['<?php echo 1; ?>'], { type: 'application/pdf' }), 'e2e-evil.pdf');
  r = await admin.postMultipart('admin/media/upload', fd, csrf);
  assert.equal(r.status, 422, 'non-PDF content rejected');

  await admin.post(`admin/media/${imgId}/delete`, { _token: csrf });
  const gone = await new Client().get(m.path.replace(/\.jpg$/, '-800.webp'));
  assert.equal(gone.status, 404, 'variants deleted with the image');
});

/* ------------------------------------------------------------------ */
test('datasheets and gated catalogue downloads create leads and count downloads', async () => {
  assert.ok(pdfPath, 'needs the PDF from the media test');
  sql("UPDATE products SET datasheet = ? WHERE slug = 'hydraulic-cylinders'", [pdfPath]);
  const c = new Client();
  const prod = await c.get('products/hydraulic-cylinders');
  assert.match(prod.text, /download\/datasheet\/hydraulic-cylinders/);
  assert.match(prod.text, /data-rfq-add/);
  const before = Number(sql("SELECT COUNT(*) n FROM events WHERE type = 'datasheet'")[0].n);
  const ds = await c.get('download/datasheet/hydraulic-cylinders');
  assert.equal(ds.status, 200);
  assert.match(ds.type, /application\/pdf/);
  assert.equal(Number(sql("SELECT COUNT(*) n FROM events WHERE type = 'datasheet'")[0].n), before + 1);

  setSetting('catalogue_pdf', pdfPath);
  setSetting('catalogue_gated', true);
  sql('DELETE FROM rate_limits');
  const form = await c.get('catalogue');
  assert.equal(form.status, 200);
  assert.match(form.text, /Get instant access/);
  await sleep(3200);
  let r = await c.post('catalogue', { _ft: formToken(form.text), form: 'download', fullName: 'E2E Reader', company: 'E2E Ltd', email: 'not-an-email', consent: '1', 'cf-turnstile-response': TS_TOKEN });
  assert.equal(r.status, 422);
  r = await c.post('catalogue', { _ft: formToken(form.text), form: 'download', fullName: 'E2E Reader', company: 'E2E Ltd', email: 'e2e+cat@example.com', consent: '1', 'cf-turnstile-response': TS_TOKEN });
  assert.equal(r.status, 302);
  assert.ok(c.jar.has('dl_access'), 'access cookie set');
  const file = await c.get('catalogue');
  assert.match(file.type, /application\/pdf/);
  assert.equal(sql("SELECT COUNT(*) n FROM leads WHERE email = 'e2e+cat@example.com' AND type = 'download'")[0].n, 1);
});

/* ------------------------------------------------------------------ */
test('projects: draft hidden, publish, auto-redirect on URL change, linked from product page', async () => {
  const ind = sql('SELECT id FROM industries ORDER BY sort_order LIMIT 1')[0].id;
  const prod = sql("SELECT id FROM products WHERE slug = 'hydraulic-cylinders'")[0].id;
  const fields = (status, slug) => ({ _token: csrf, title: 'E2E Port crane sheave replacement', slug, status, sort_order: '0', client: 'European port operator', location: 'Rotterdam',
    industry_id: ind, completed_at: '2026-03-01', image: '', image_alt: '', summary: 'Replacement sheaves delivered in 9 days to keep a 50 t crane working.',
    challenge: '<p>A cracked sheave stopped a ship-to-shore crane.</p>', solution: '<p>Reverse-engineered and machined in 9 days.</p>',
    'results[0][value]': '9 days', 'results[0][label]': 'drawing to delivery', 'product_ids[]': prod, testimonial: '', testimonial_by: '', meta_title: '', meta_description: '' });
  let r = await admin.post('admin/r/projects/new', fields('draft', 'e2e-port-crane'));
  assert.equal(r.status, 302, r.text.slice(0, 300));
  const id = r.location.match(/projects\/(\d+)\/edit/)[1];
  const c = new Client();
  assert.equal((await c.get('projects/e2e-port-crane')).status, 404, 'draft not public');
  await admin.post(`admin/r/projects/${id}/edit`, fields('published', 'e2e-port-crane'));
  let p = await c.get('projects/e2e-port-crane');
  assert.equal(p.status, 200);
  assert.match(p.text, /9 days/);
  assert.match(p.text, /"@type":"Article"/);
  assert.match((await c.get('projects')).text, /E2E Port crane/);
  assert.match((await c.get('sitemap.xml')).text, /projects\/e2e-port-crane/);
  assert.match((await c.get('products/hydraulic-cylinders')).text, /Projects Using/);
  await admin.post(`admin/r/projects/${id}/edit`, fields('published', 'e2e-port-crane-sheaves'));
  r = await c.get('projects/e2e-port-crane');
  assert.equal(r.status, 301, 'old URL redirects');
  assert.equal(new URL(r.location, BASE).pathname, '/projects/e2e-port-crane-sheaves');
});

/* ------------------------------------------------------------------ */
test('certificates: valid ones shown on Quality page, expired ones hidden', async () => {
  const mk = (name, exp) => admin.post('admin/r/certificates/new', { _token: csrf, name, standard: 'ISO 9001:2015', issuer: 'E2E Cert Body', number: 'Q-1', issued_at: '2025-01-01', expires_at: exp, status: 'published', sort_order: '0', scope: 'Design and manufacture', file: '' });
  await mk('e2e Valid certificate', '2030-12-31');
  await mk('e2e Expired certificate', '2024-01-01');
  const q = await new Client().get('quality');
  assert.match(q.text, /e2e Valid certificate/);
  assert.doesNotMatch(q.text, /e2e Expired certificate/);
  const list = await admin.get('admin/r/certificates');
  assert.match(list.text, /Expired/);
});

/* ------------------------------------------------------------------ */
test('reports: attribution table and CSV export', async () => {
  const r = await admin.get('admin/reports?by=source&range=90');
  assert.equal(r.status, 200);
  assert.match(r.text, /Win rate/);
  const csv = await admin.get('admin/reports?by=product&range=365&export=csv');
  assert.match(csv.type, /text\/csv/);
  assert.match(csv.text.split('\n')[0], /^"Product \/ service",Leads,Qualified\+/);
});

/* ------------------------------------------------------------------ */
test('backups: create, password-protected download, verified restore into a temp database', async () => {
  let r = await admin.post('admin/backups/create', { _token: csrf });
  assert.equal(r.status, 302);
  const list = await admin.get('admin/backups');
  const file = (list.text.match(/backup-\d{8}-\d{6}-[a-f0-9]{6}\.sql\.gz/) || [])[0];
  assert.ok(file, 'backup listed');
  r = await admin.post('admin/backups/download', { _token: csrf, file, password: 'wrong-password-1' });
  assert.equal(r.status, 302, 'wrong password redirects back');
  assert.doesNotMatch(r.type, /gzip/);
  r = await admin.req('admin/backups/download', { method: 'POST', body: new URLSearchParams({ _token: csrf, file, password: PASSWORD }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  assert.equal(r.status, 200);
  const sqlText = gunzipSync(Buffer.from(await r.arrayBuffer())).toString('utf8');
  assert.match(sqlText, /CREATE TABLE `posts`/);
  const check = JSON.parse(php(['tests/lib/restore-check.php', file]));
  assert.ok(check.ok, JSON.stringify(check.mismatches));
  assert.ok(check.statements > 20);
  await admin.post('admin/backups/delete', { _token: csrf, file });
});

/* ------------------------------------------------------------------ */
test('cron: CLI runs all tasks; daily digest emails overdue follow-ups', async () => {
  const leadId = sql("INSERT INTO leads (type, status, name, email, product, follow_up_at, assigned_to) VALUES ('contact', 'contacted', 'E2E Overdue', 'e2e+digest@example.com', 'Hydraulic Cylinders', CURDATE() - INTERVAL 2 DAY, NULL)");
  assert.ok(leadId.affected === 1);
  const out = php(['app/cli.php', 'cron', '--force']);
  assert.match(out, /digest\s+digest emailed/);
  assert.match(out, /backup\s+backup-/);
  if (DEBUG) {
    const dir = join(ROOT, 'app/storage/mail');
    const newest = readdirSync(dir).filter((f) => f.endsWith('.html')).sort().slice(-5).map((f) => readFileSync(join(dir, f), 'utf8'));
    assert.ok(newest.some((h) => /Lead follow-ups/.test(h) && /E2E Overdue/.test(h)), 'digest saved in dev mailbox');
  }
  const bad = await new Client().get('cron/not-the-key');
  assert.equal(bad.status, 404, 'URL cron needs the secret key');
  // Remove the backup the forced run created
  for (const f of readdirSync(join(ROOT, 'app/storage/backups')).filter((x) => x.startsWith('backup-')).sort().slice(-1)) php(['-r', `@unlink('app/storage/backups/${f}');`]);
});

/* ------------------------------------------------------------------ */
test('passkeys: register, sign in without password, reject replays, wrong site and cloned keys', async () => {
  const auth = new SoftAuthenticator();
  let r = await admin.postJSON('admin/passkeys/options', { password: 'wrong-password-1' }, csrf);
  assert.equal(r.status, 422, 'password required to add a passkey');
  r = await admin.postJSON('admin/passkeys/options', { password: PASSWORD }, csrf);
  assert.equal(r.status, 200, r.text);
  assert.equal(r.json.authenticatorSelection.userVerification, 'required');
  // A response for the wrong website is rejected
  let bad = await admin.postJSON('admin/passkeys/register', { credential: auth.create(r.json, ORIGIN, { rpIdOverride: 'evil.example' }), name: 'e2e bad' }, csrf);
  assert.equal(bad.status, 422);
  r = await admin.postJSON('admin/passkeys/options', { password: PASSWORD }, csrf);
  const reg = await admin.postJSON('admin/passkeys/register', { credential: auth.create(r.json, ORIGIN), name: 'e2e laptop' }, csrf);
  assert.equal(reg.status, 200, reg.text);
  assert.equal(sql("SELECT COUNT(*) n FROM passkeys WHERE name = 'e2e laptop'")[0].n, 1);

  // Fresh browser: passwordless sign-in
  const visitor = new Client();
  const loginPage = await visitor.get('admin/login');
  assert.match(loginPage.text, /Sign in with a passkey/);
  const vcsrf = token(loginPage.text);
  let opts = await visitor.postJSON('admin/login/passkey/options', {}, vcsrf);
  assert.equal(opts.status, 200);
  const assertion = auth.get(opts.json, ORIGIN);
  let login = await visitor.postJSON('admin/login/passkey', { credential: assertion }, vcsrf);
  assert.equal(login.status, 200, login.text);
  assert.equal((await visitor.get('admin')).status, 200, 'signed in');

  // Replaying the same signed response fails (challenge is single-use)
  const other = new Client();
  const ocsrf = token((await other.get('admin/login')).text);
  await other.postJSON('admin/login/passkey/options', {}, ocsrf);
  login = await other.postJSON('admin/login/passkey', { credential: assertion }, ocsrf);
  assert.equal(login.status, 422);
  // Wrong origin (phishing site) fails
  opts = await other.postJSON('admin/login/passkey/options', {}, ocsrf);
  login = await other.postJSON('admin/login/passkey', { credential: auth.get(opts.json, ORIGIN, { originOverride: 'https://evil.example' }) }, ocsrf);
  assert.equal(login.status, 422);
  // Signature counter going backwards (cloned key) fails
  opts = await other.postJSON('admin/login/passkey/options', {}, ocsrf);
  login = await other.postJSON('admin/login/passkey', { credential: auth.get(opts.json, ORIGIN, { counter: 1 }) }, ocsrf);
  assert.equal(login.status, 422);
  assert.match(login.json.error, /copy/i);
  // Tampered signature fails
  opts = await other.postJSON('admin/login/passkey/options', {}, ocsrf);
  const tampered = auth.get(opts.json, ORIGIN);
  tampered.response.signature = tampered.response.signature.slice(0, -4) + 'AAAA';
  login = await other.postJSON('admin/login/passkey', { credential: tampered }, ocsrf);
  assert.equal(login.status, 422);

  const pk = sql("SELECT id FROM passkeys WHERE name = 'e2e laptop'")[0].id;
  await admin.post(`admin/passkeys/${pk}/delete`, { _token: csrf, password: PASSWORD });
  assert.equal(sql("SELECT COUNT(*) n FROM passkeys WHERE name = 'e2e laptop'")[0].n, 0);
});

/* ------------------------------------------------------------------ */
test('authenticator app: setup with QR secret, code required at sign-in, recovery codes are single-use', async () => {
  let r = await admin.post('admin/2fa/setup', { _token: csrf, password: PASSWORD });
  assert.equal(r.status, 302);
  let prof = await admin.get('admin/profile');
  const uri = (prof.text.match(/data-qr="([^"]+)"/) || [])[1]?.replace(/&amp;/g, '&');
  assert.ok(uri?.startsWith('otpauth://totp/'), 'otpauth URI for the QR code');
  const secret = new URL(uri).searchParams.get('secret');
  await admin.post('admin/2fa/confirm', { _token: csrf, code: '000000' });
  assert.equal(sql('SELECT totp_enabled_at FROM users WHERE id = ?', [adminId()])[0].totp_enabled_at, null, 'wrong code does not enable');
  await admin.post('admin/2fa/confirm', { _token: csrf, code: totp(secret) });
  prof = await admin.get('admin/profile');
  const codes = [...prof.text.matchAll(/<li class="rounded bg-white px-2 py-1 text-center">([a-z0-9]{5}-[a-z0-9]{5})<\/li>/g)].map((m) => m[1]);
  assert.equal(codes.length, 10, 'ten recovery codes shown once');
  assert.doesNotMatch((await admin.get('admin/profile')).text, new RegExp(codes[0]), 'not shown again');

  // Password alone is no longer enough
  const c = new Client();
  let to = await c.login(ADMIN, PASSWORD);
  assert.match(to, /admin\/login\/2fa$/);
  let page = await c.get('admin/login/2fa');
  r = await c.post('admin/login/2fa', { _token: token(page.text), mode: 'app', code: '123456' });
  assert.equal(r.status, 200, 'wrong code stays on the form');
  const step = Math.floor(Date.now() / 30000) + 1; // next step (still inside the ±1 window, not yet used)
  r = await c.post('admin/login/2fa', { _token: token(page.text), mode: 'app', code: totp(secret, step) });
  assert.match(r.location, /\/admin$/);
  // Same code again is a replay
  const c2 = new Client();
  await c2.login(ADMIN, PASSWORD);
  page = await c2.get('admin/login/2fa');
  r = await c2.post('admin/login/2fa', { _token: token(page.text), mode: 'app', code: totp(secret, step) });
  assert.equal(r.status, 200);
  assert.match(r.text, /already used/);
  // Recovery code works once
  r = await c2.post('admin/login/2fa', { _token: token(page.text), mode: 'recovery', code: codes[0].toUpperCase() });
  assert.match(r.location, /\/admin$/);
  const c3 = new Client();
  await c3.login(ADMIN, PASSWORD);
  page = await c3.get('admin/login/2fa?mode=recovery');
  r = await c3.post('admin/login/2fa', { _token: token(page.text), mode: 'recovery', code: codes[0] });
  assert.equal(r.status, 200);
  assert.match(r.text, /not valid or was already used/);

  await admin.post('admin/2fa/disable', { _token: csrf, password: PASSWORD });
  assert.equal(sql('SELECT totp_enabled_at FROM users WHERE id = ?', [adminId()])[0].totp_enabled_at, null);
  assert.match(await new Client().login(ADMIN, PASSWORD), /\/admin$/, 'password-only sign-in again after turning it off');
});
