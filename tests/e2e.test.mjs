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
import { startFakeTelegram, FAKE_TOKEN } from './lib/fake-telegram.mjs';

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
  assert.ok(DEBUG, 'The suite needs debug mode (step-up codes are shown on screen only in debug)');
  await admin.stepUp(); // critical actions in the tests below run inside this 10-minute confirmation
});

test.after(async () => {
  for (const m of sql("SELECT id FROM media WHERE original_name LIKE 'e2e-%'")) await admin.post(`admin/media/${m.id}/delete`, { _token: csrf });
  sql("DELETE FROM redirects WHERE source LIKE '/e2e%' OR source LIKE '/projects/e2e%' OR note LIKE 'e2e%'");
  sql("DELETE FROM not_found_log WHERE path LIKE '/e2e%'");
  sql("DELETE FROM leads WHERE email LIKE 'e2e+%@example.com'");
  sql("DELETE FROM projects WHERE slug LIKE 'e2e%'");
  sql("DELETE FROM certificates WHERE name LIKE 'e2e%'");
  sql("DELETE FROM testimonials WHERE author LIKE 'e2e%'");
  setSetting('heading_style', 'industrial');
  setSetting('admin_otp_enabled', false);
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
test('home: quick-link tiles, sector strip, testimonials need permission, optional serif headings', async () => {
  const c = new Client();
  let home = await c.get('');
  assert.equal((home.text.match(/class="hero-tile"/g) || []).length, 4, 'four quick-link tiles');
  assert.match(home.text, /class="trust-strip"/);
  assert.match(home.text, /trust-track" aria-hidden="true"/, 'duplicate marquee track hidden from screen readers');
  assert.doesNotMatch(home.text, /id="testimonials"/, 'no testimonials section until one is published');

  const base = { _token: csrf, quote: 'e2e They delivered the sheaves in nine days.', author: 'e2e Client', role: 'Maintenance Manager', company: 'E2E Port', rating: '5', photo: '', project_id: '', sort_order: '0' };
  let r = await admin.post('admin/r/testimonials/new', { ...base, status: 'published', permission: '0' });
  assert.equal(r.status, 200, 'publishing without permission is refused');
  assert.match(r.text, /Confirm the client agreed/);
  r = await admin.post('admin/r/testimonials/new', { ...base, status: 'published', permission: '1' });
  assert.equal(r.status, 302);
  home = await c.get('');
  assert.match(home.text, /id="testimonials"/);
  assert.match(home.text, /e2e They delivered the sheaves/);
  assert.match(home.text, /aria-label="Rated 5 out of 5"/);

  // Editor links: javascript: and other schemes are neutralised, tel: is allowed
  const homeId = sql("SELECT id FROM pages WHERE slug = 'home'")[0].id;
  const data = JSON.parse(sql('SELECT data FROM pages WHERE id = ?', [homeId])[0].data || '{}');
  const saved = JSON.stringify(data);
  data.hero_tiles = [{ icon: 'phone', label: 'Call us', url: 'tel:+440000' }, { icon: 'x', label: 'Bad link', url: 'javascript:alert(1)' }];
  sql('UPDATE pages SET data = ? WHERE id = ?', [JSON.stringify(data), homeId]);
  home = await c.get('');
  assert.match(home.text, /href="tel:\+440000"/);
  assert.doesNotMatch(home.text, /javascript:alert/);
  sql('UPDATE pages SET data = ? WHERE id = ?', [saved, homeId]);

  setSetting('heading_style', 'editorial');
  assert.match((await c.get('')).text, /'headings-editorial'/);
  setSetting('heading_style', 'industrial');
  assert.doesNotMatch((await c.get('')).text, /'headings-editorial'/);
  const font = await c.get('assets/fonts/playfair-display-latin-700-normal.woff2');
  assert.equal(font.status, 200, 'serif font is self-hosted');
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

/* ------------------------------------------------------------------ */
const hiddenFields = (html) => Object.fromEntries([...html.matchAll(/<input type="hidden" name="([^"]+)" value="([^"]*)">/g)].map((m) => [m[1].replace(/&amp;/g, '&'), m[2].replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'")]));
const backupFiles = () => readdirSync(join(ROOT, 'app/storage/backups')).filter((f) => /^backup-.*\.sql\.gz$/.test(f));

test('step-up: critical actions need a fresh one-time code, then resume; passwords are never stored', async () => {
  const c = new Client();
  await c.login(ADMIN, PASSWORD);
  let page = await c.get('admin/backups');
  let r = await c.post('admin/backups/create', { _token: token(page.text) });
  assert.equal(r.status, 302);
  assert.match(r.location, /admin\/confirm$/, 'sent to confirmation instead of running');
  const before = backupFiles().length;

  sql("UPDATE login_otps o JOIN users u ON u.id = o.user_id SET o.created_at = o.created_at - INTERVAL 20 MINUTE WHERE u.email = ? OR u.username = ?", [ADMIN, ADMIN]);
  page = await c.get('admin/confirm');
  assert.match(page.text, /Code we emailed to/, 'code emailed automatically when email is the only method');
  assert.match(page.text, /Create, download or delete a backup/);
  r = await c.post('admin/confirm', { _token: token(page.text), method: 'email', code: '000000' });
  assert.equal(r.status, 200);
  assert.match(r.text, /Incorrect code/);
  r = await c.stepUp();
  assert.equal(r.status, 200);
  assert.match(r.text, /data-autosubmit/, 'auto-resubmit page');
  const action = (r.text.match(/<form method="post" action="([^"]+)"/) || [])[1];
  r = await c.post(action, hiddenFields(r.text));
  assert.equal(r.status, 302);
  assert.equal(backupFiles().length, before + 1, 'the original action ran after confirmation');
  assert.match((await c.get('admin/backups')).text, /adm-verified/, 'verified badge while the window is open');

  // JSON endpoints answer 428 with the confirmation URL
  const d = new Client();
  await d.login(ADMIN, PASSWORD);
  const j = await d.postJSON('admin/passkeys/options', { password: PASSWORD }, token((await d.get('admin/profile')).text));
  assert.equal(j.status, 428);
  assert.match(j.json.stepup, /admin\/confirm/);

  // A form containing a password is not stored: after confirming, the user submits it again
  const e = new Client();
  await e.login(ADMIN, PASSWORD);
  const prof = await e.get('admin/profile');
  r = await e.post('admin/profile', { _token: token(prof.text), action: 'password', current_password: PASSWORD, new_password: 'Another1Pass2', confirm_password: 'Another1Pass2' },
    { Referer: `${BASE}/admin/profile` }); // browsers send this for same-site forms
  assert.match(r.location, /admin\/confirm$/);
  r = await e.stepUp();
  assert.equal(r.status, 302);
  assert.match(r.location, /admin\/profile/);
  assert.match((await e.get('admin/profile')).text, /passwords are never stored/);
  assert.equal(sql('SELECT COUNT(*) n FROM users WHERE id = ? AND password_changed_at > NOW() - INTERVAL 1 MINUTE', [adminId()])[0].n, 0, 'password not changed without resubmitting');

  for (const f of backupFiles().sort().slice(before)) php(['-r', `@unlink('app/storage/backups/${f}');`]);
});

test('notifications: new-device sign-in alert, bell count, admin alert for security settings, mark as read', async () => {
  const uid = adminId();
  sql('UPDATE notifications SET read_at = NOW() WHERE user_id = ?', [uid]);
  const count = (ev) => Number(sql('SELECT COUNT(*) n FROM notifications WHERE user_id = ? AND event = ?', [uid, ev])[0].n);
  const before = count('login_new_device');
  await new Client().login(ADMIN, PASSWORD); // a browser this account has never used
  assert.equal(count('login_new_device'), before + 1, 'new-device alert recorded');
  assert.match((await admin.get('admin')).text, /adm-bell-count">\d/, 'bell shows unread count');

  const cur = { admin_otp_enabled: '0', otp_trust_days: String(getSetting('otp_trust_days') ?? 30), turnstile_login: getSetting('turnstile_login') === false ? '0' : '1', require_strong_2fa: '1', remember_enabled: '1', remember_days: '30' };
  const idle = Number(getSetting('admin_idle_minutes') || 240);
  const page = await admin.get('admin/settings/security');
  assert.equal(page.status, 200);
  await admin.post('admin/settings/security', { _token: csrf, ...cur, admin_idle_minutes: String(idle + 1) });
  const n = sql("SELECT severity, body FROM notifications WHERE user_id = ? AND event = 'settings_changed' ORDER BY id DESC LIMIT 1", [uid])[0];
  assert.equal(n.severity, 'warning');
  assert.match(n.body, /Sign out after inactivity/);
  await admin.post('admin/settings/security', { _token: csrf, ...cur, admin_idle_minutes: String(idle) });

  const list = await admin.get('admin/notifications');
  assert.match(list.text, /New sign-in to your account/);
  await admin.post('admin/notifications/read', { _token: csrf });
  assert.equal(Number(sql('SELECT COUNT(*) n FROM notifications WHERE user_id = ? AND read_at IS NULL', [uid])[0].n), 0);
  assert.doesNotMatch((await admin.get('admin')).text, /adm-bell-count/);
  if (DEBUG) {
    const dir = join(ROOT, 'app/storage/mail');
    const recent = readdirSync(dir).filter((f) => f.endsWith('.html')).sort().slice(-15).map((f) => readFileSync(join(dir, f), 'utf8'));
    assert.ok(recent.some((h) => /New sign-in to your account/.test(h)), 'new-device alert emailed');
  }
});

test('profile picture: cropped to a 256 px WebP without metadata, shown in the header, removable', async () => {
  const uid = adminId();
  const jpg = await sharp({ create: { width: 900, height: 600, channels: 3, background: '#174A78' } }).jpeg().withMetadata({ exif: { IFD0: { Artist: 'secret-gps-test' } } }).toBuffer();
  let fd = new FormData();
  fd.append('_token', csrf);
  fd.append('avatar', new Blob([jpg], { type: 'image/jpeg' }), 'me.jpg');
  let r = await admin.req('admin/avatar', { method: 'POST', body: fd });
  assert.equal(r.status, 302);
  const path = sql('SELECT avatar FROM users WHERE id = ?', [uid])[0].avatar;
  assert.match(path, /^uploads\/avatars\/\d+-[a-f0-9]+\.webp$/);
  const file = await new Client().req(path);
  const meta = await sharp(Buffer.from(await file.arrayBuffer())).metadata();
  assert.deepEqual([meta.format, meta.width, meta.height, meta.exif], ['webp', 256, 256, undefined]);
  assert.match((await admin.get('admin')).text, new RegExp(path.replace(/\./g, '\.')));

  fd = new FormData();
  fd.append('_token', csrf);
  fd.append('avatar', new Blob(['<?php echo 1; ?>'], { type: 'image/jpeg' }), 'evil.jpg');
  await admin.req('admin/avatar', { method: 'POST', body: fd });
  assert.equal(sql('SELECT avatar FROM users WHERE id = ?', [uid])[0].avatar, path, 'non-image rejected');

  await admin.post('admin/avatar/remove', { _token: csrf });
  assert.equal(sql('SELECT avatar FROM users WHERE id = ?', [uid])[0].avatar, null);
  assert.equal((await new Client().get(path)).status, 404, 'file deleted');
});

test('forms: password show/hide on admin screens; Turnstile sits directly above the submit button', async () => {
  const c = new Client();
  assert.match((await c.get('admin/login')).text, /js\/pwtoggle\.js/);
  assert.match((await admin.get('admin/profile')).text, /js\/pwtoggle\.js/);
  const on = Boolean(getSetting('turnstile_site_key')) && Boolean(getSetting('turnstile_secret'));
  const pages = {
    contact: ['name="fullName"', 'Send Message'],
    quote: ['name="fullName"', 'Submit Quote Request'],
    'admin/login': ['name="password"', 'Sign in <'],
    'admin/forgot': ['name="login"', 'Send reset code'],
  };
  for (const [p, [field, submit]] of Object.entries(pages)) {
    const h = (await c.get(p)).text;
    if (!on) {
      assert.doesNotMatch(h, /class="cf-turnstile"/, `${p}: no widget when Turnstile is off`);
      continue;
    }
    const iField = h.indexOf(field);
    const iTs = h.indexOf('class="cf-turnstile"');
    const iBtn = h.indexOf(submit);
    assert.ok(iField > -1 && iTs > iField && iBtn > iTs, `${p}: widget after the fields and before the submit button`);
  }
});

/* ------------------------------------------------------------------ */
test('error pages: helpful 404 with suggestions, JSON errors, 405, expired form, 500 with a reference in the error log', async () => {
  const c = new Client();
  const nf = await c.get('e2e-hydraulic-cylinder');
  assert.equal(nf.status, 404);
  assert.match(nf.text, /Page not found/i);
  assert.match(nf.text, /Were you looking for/);
  assert.match(nf.text, /href="[^"]*products\/hydraulic-cylinders"/, 'suggests the closest product');
  assert.match(nf.text, /noindex/);

  const js = await c.get('e2e-missing', { Accept: 'application/json' });
  assert.equal(js.status, 404);
  assert.match(js.type, /json/);
  assert.equal(JSON.parse(js.text).status, 404);

  const na = await c.post('', { a: '1' });
  assert.equal(na.status, 405);
  assert.match(na.res.headers.get('allow') || '', /GET/);

  // An expired admin form (bad CSRF token) explains itself and offers to sign in again
  const exp = await c.post('admin/login', { _token: 'stale', login: 'x', password: 'y' });
  assert.equal(exp.status, 419);
  assert.match(exp.text, /session expired/i);
  assert.match(exp.text, /Sign in again/);

  // A real crash: branded page with a reference that finds the stored error
  sql("DELETE FROM app_errors WHERE message LIKE 'Test error triggered%'");
  const boom = await admin.post('admin/errors/test', { _token: csrf });
  assert.equal(boom.status, 500);
  const ref = (boom.text.match(/E-[2-9A-HJ-NP-Z]{8}/) || [])[0];
  assert.ok(ref, 'error reference shown');
  assert.doesNotMatch(boom.text, /Try again/, 'no "try again" link for a form submission');
  const row = sql('SELECT id, status, trace FROM app_errors WHERE ref = ?', [ref])[0];
  assert.ok(row && Number(row.status) === 500, 'stored in the error log');
  assert.match(row.trace, /admin_errors_test\(\)/);
  assert.ok(sql("SELECT id FROM notifications WHERE event = 'server_error' AND created_at >= NOW() - INTERVAL 1 MINUTE").length >= 1, 'admins alerted');

  const find = await admin.get('admin/errors?ref=' + ref.slice(2).toLowerCase());
  assert.equal(find.status, 302);
  assert.match(find.location, new RegExp(`admin/errors/${row.id}$`));
  const detail = await admin.get(`admin/errors/${row.id}`);
  assert.match(detail.text, new RegExp(ref));
  const list = await admin.get('admin/errors');
  assert.match(list.text, /Test error triggered/);
  const fp = sql('SELECT fingerprint FROM app_errors WHERE id = ?', [row.id])[0].fingerprint;
  await admin.post('admin/errors/resolve', { _token: csrf, fingerprint: fp });
  assert.doesNotMatch((await admin.get('admin/errors')).text, /Test error triggered/, 'resolved errors leave the open list');
  sql("DELETE FROM app_errors WHERE message LIKE 'Test error triggered%'");
  sql("DELETE FROM notifications WHERE event = 'server_error'");
});

test('maintenance mode: 503 page for visitors, team and preview link see the site, IP allow-list, schedule, needs a code', async () => {
  const visitor = new Client();
  const resetMaint = () => { for (const k of ['maintenance_start', 'maintenance_end', 'maintenance_until']) setSetting(k, null); setSetting('maintenance_enabled', false); setSetting('maintenance_allow_ips', []); };
  resetMaint();
  try {
    assert.equal((await visitor.get('')).status, 200);

    // Switching it on is a critical action: a fresh session must confirm with a one-time code first
    const fresh = new Client();
    await fresh.login(ADMIN, PASSWORD);
    const fp = await fresh.get('admin/settings/maintenance');
    const first = await fresh.post('admin/maintenance', { _token: token(fp.text), action: 'on', hours: '2', return: 'admin/settings/maintenance' });
    assert.match(first.location, /admin\/confirm$/, 'asks for a code');
    assert.equal(getSetting('maintenance_enabled'), false, 'nothing changed yet');
    const resumed = await fresh.stepUp();
    assert.match(resumed.text, /data-autosubmit/, 'the switch is re-submitted after the code');
    const action = resumed.text.match(/<form[^>]+action="([^"]+)"/)[1].replace(/^\/+/, '');
    const fields = Object.fromEntries([...resumed.text.matchAll(/<input type="hidden" name="([^"]+)" value="([^"]*)"/g)].map((m) => [m[1], m[2].replace(/&amp;/g, '&')]));
    await fresh.post(action, fields);
    assert.equal(getSetting('maintenance_enabled'), true);
    assert.ok(getSetting('maintenance_until'), 'expected-back time saved');

    const page = await visitor.get('products');
    assert.equal(page.status, 503);
    assert.ok(Number(page.res.headers.get('retry-after')) > 3600, 'Retry-After until the expected time');
    assert.match(page.text, /We’ll be back shortly/);
    assert.match(page.text, /data-until="\d+"/, 'countdown');
    assert.equal((await visitor.get('robots.txt')).status, 200, 'robots.txt still served');
    assert.equal((await visitor.get('admin/login')).status, 200, 'admin sign-in still works');
    const lead = await visitor.postJSON('lead', { name: 'e2e' }, '');
    assert.equal(lead.status, 503);
    assert.match(lead.json.message, /maintenance/);

    // Signed-in team members see the real site, with a notice
    const own = await admin.get('products');
    assert.equal(own.status, 200);
    assert.match(own.text, /Maintenance mode is on/);
    assert.match((await admin.get('admin')).text, /Go live now/, 'banner in the admin');
    assert.equal((await admin.get('?maintenance_preview=1')).status, 503, 'team preview shows the real maintenance page');

    // Secret preview link: sets a cookie, then the site works for that browser only
    const tokenValue = getSetting('maintenance_bypass_token');
    const guest = new Client();
    const go = await guest.get('?maintenance_access=' + tokenValue);
    assert.equal(go.status, 302);
    assert.equal((await guest.get('products')).status, 200);
    assert.equal((await new Client().get('?maintenance_access=wrong')).status, 503);
    // A new link cancels the old one
    await admin.stepUp();
    const sp = await admin.get('admin/settings/maintenance');
    await admin.post('admin/maintenance/link', { _token: token(sp.text) });
    assert.equal((await guest.get('products')).status, 503, 'old preview access revoked');

    // IP allow-list (the test client connects from localhost)
    setSetting('maintenance_allow_ips', ['127.0.0.1', '::1']);
    assert.equal((await visitor.get('products')).status, 200);
    setSetting('maintenance_allow_ips', ['203.0.113.0/24']);
    assert.equal((await visitor.get('products')).status, 503);

    // Off again from the admin banner
    const dash = await admin.get('admin');
    await admin.post('admin/maintenance', { _token: token(dash.text), action: 'off', return: 'admin' });
    assert.equal((await visitor.get('products')).status, 200);
    assert.ok(sql("SELECT id FROM notifications WHERE event IN ('maintenance_on','maintenance_off') AND created_at >= NOW() - INTERVAL 5 MINUTE").length >= 2, 'admins told when it went off- and online');

    // Scheduled window: on inside the window, off after it
    const sqlNow = sql("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') n")[0].n;
    const shift = (sec) => sql(`SELECT DATE_FORMAT(? + INTERVAL ${Number(sec)} SECOND, '%Y-%m-%d %H:%i:%s') t`, [sqlNow])[0].t;
    setSetting('maintenance_start', shift(-60));
    setSetting('maintenance_end', shift(3600));
    assert.equal((await visitor.get('products')).status, 503, 'inside the window');
    setSetting('maintenance_end', shift(-1));
    assert.equal((await visitor.get('products')).status, 200, 'window over');

    // Settings validation
    await admin.stepUp();
    const st = await admin.get('admin/settings/maintenance');
    const bad = await admin.post('admin/settings/maintenance', { _token: token(st.text), maintenance_title: 'x', maintenance_allow_ips: 'not-an-ip', maintenance_start: '2030-01-02T10:00', maintenance_end: '2030-01-01T10:00' });
    assert.equal(bad.status, 200);
    assert.match(bad.text, /not a valid IP address/);
    assert.match(bad.text, /end must be after the start/);
  } finally {
    resetMaint();
    sql("DELETE FROM notifications WHERE event IN ('maintenance_on','maintenance_off')");
  }
});

/* ------------------------------------------------------------------ */
test('team access: footer "Team login", /login shortcut, admin toolbar with "Edit this page" for signed-in staff only', async () => {
  const visitor = new Client();
  const home = await visitor.get('');
  assert.match(home.text, /href="[^"]*admin\/login"[^>]*>[\s\S]{0,400}Team login/, 'footer link');
  assert.doesNotMatch(home.text, /site-adminbar/, 'visitors never see the toolbar');
  assert.ok(!visitor.jar.has('ivadmin'), 'no session started for visitors');
  assert.match((await visitor.get('login')).location, /\/admin\/login$/);

  const product = await admin.get('products/hydraulic-cylinders');
  assert.match(product.text, /class="site-adminbar"/);
  const pid = sql("SELECT id FROM products WHERE slug = 'hydraulic-cylinders'")[0].id;
  assert.match(product.text, new RegExp(`href="[^"]*admin/r/products/${pid}/edit"[^>]*>[\\s\\S]{0,900}Edit product`));
  const about = await admin.get('about');
  const aid = sql("SELECT id FROM pages WHERE slug = 'about'")[0].id;
  assert.match(about.text, new RegExp(`admin/pages/${aid}/edit`));
  assert.match((await admin.get('login')).location, /\/admin$/, '/login goes straight to the dashboard when signed in');

  setSetting('footer_login_link', false);
  setSetting('admin_bar', false);
  try {
    assert.doesNotMatch((await visitor.get('')).text, /Team login/);
    assert.doesNotMatch((await admin.get('')).text, /site-adminbar/);
  } finally {
    setSetting('footer_login_link', true);
    setSetting('admin_bar', true);
  }

  // Signing out from the toolbar keeps you on the public page
  const s = new Client();
  await s.login(ADMIN, PASSWORD);
  const pg = await s.get('quality');
  const out = await s.post('admin/logout', { _token: token(pg.text), return: 'quality' });
  assert.match(out.location, /\/quality$/);
  assert.doesNotMatch((await s.get('quality')).text, /site-adminbar/);
  const bad = new Client();
  await bad.login(ADMIN, PASSWORD);
  const back = await bad.post('admin/logout', { _token: token((await bad.get('admin')).text), return: 'https://evil.example/' });
  assert.match(back.location, /\/admin\/login$/, 'never redirects off-site');
});

/* ------------------------------------------------------------------ */
test('telegram: bot setup, connect, codes, one-tap approve/deny, trusted device skips codes, alerts, /stop', async () => {
  sql('DELETE FROM rate_limits'); // earlier tests deliberately trip the failed-sign-in limits
  const tg = await startFakeTelegram();
  const uid = adminId();
  const CHAT = 7770001;
  const waitFor = async (fn, what, ms = 9000) => {
    const end = Date.now() + ms;
    for (;;) { const v = await fn(); if (v) return v; if (Date.now() > end) throw new Error('timed out waiting for ' + what); await sleep(700); }
  };
  setSetting('telegram_api_base', tg.url);
  setSetting('telegram_update_offset', 0);
  setSetting('telegram_webhook', false);
  sql('UPDATE users SET telegram_chat_id = NULL, telegram_username = NULL, telegram_prefs = NULL, telegram_link_hash = NULL WHERE id = ?', [uid]);
  try {
    // 1) Admin adds the bot: the token is checked with Telegram (getMe)
    await admin.stepUp();
    let st = await admin.get('admin/settings/telegram');
    const bad = await admin.post('admin/settings/telegram', { _token: token(st.text), telegram_enabled: '1', telegram_bot_token: '123:wrongwrongwrongwrongwrong', telegram_login_approval: '1' });
    assert.match(bad.text, /rejected the bot token/);
    st = await admin.get('admin/settings/telegram');
    const ok = await admin.post('admin/settings/telegram', { _token: token(st.text), telegram_enabled: '1', telegram_bot_token: FAKE_TOKEN, telegram_login_approval: '1' });
    assert.equal(ok.status, 302);
    assert.equal(getSetting('telegram_bot_username'), 'e2e_test_bot');
    assert.doesNotMatch((await admin.get('admin/settings/telegram')).text, new RegExp(FAKE_TOKEN.split(':')[1]), 'token never shown again');

    // 2) Connect: single-use deep link, the user presses Start, the page notices by itself
    const prof0 = await admin.get('admin/profile');
    await admin.post('admin/telegram/link', { _token: token(prof0.text) });
    const prof = await admin.get('admin/profile');
    const startCode = (prof.text.match(/t\.me\/e2e_test_bot\?start=([\w-]+)/) || [])[1];
    assert.ok(startCode, 'deep link shown');
    tg.message(CHAT, '/start ' + startCode, 'e2e_owner');
    await waitFor(async () => (await admin.postJSON('admin/telegram/status', {}, token(prof.text))).json?.linked, 'link');
    assert.equal(String(sql('SELECT telegram_chat_id FROM users WHERE id = ?', [uid])[0].telegram_chat_id), String(CHAT));
    assert.match(tg.lastTo(CHAT).text, /Connected|connected to your control panel/);
    tg.message(8880002, '/start ' + startCode); // reused link from another chat
    await sleep(2100);
    await admin.postJSON('admin/telegram/status', {}, token(prof.text));
    assert.match(tg.lastTo(8880002)?.text || '', /expired or was already used/);

    // 3) Personal two-step: a Telegram code at every sign-in
    const pp = await admin.get('admin/profile');
    await admin.post('admin/telegram/prefs', { _token: token(pp.text), 'tg[codes]': '1', 'tg[approve]': '1', 'tg[require]': '1', 'tg[alerts]': '1', 'tg[leads]': '1' });
    assert.ok(JSON.parse(sql('SELECT telegram_prefs FROM users WHERE id = ?', [uid])[0].telegram_prefs).require);

    // 4) Sign in on a new device: code goes to Telegram with Approve / Deny; Approve finishes the sign-in
    const ageCodes = () => sql('UPDATE login_otps SET created_at = created_at - INTERVAL 20 MINUTE WHERE user_id = ?', [uid]);
    ageCodes();
    const a = new Client();
    const lp = await a.get('admin/login');
    const lr = await a.post('admin/login', { _token: token(lp.text), login: ADMIN, password: PASSWORD, 'cf-turnstile-response': TS_TOKEN });
    const aTo = lr.location || 'error: ' + (lr.text.match(/role="alert"[^>]*>([\s\S]{0,300}?)<\/div>/) || ['', '?'])[1].replace(/<[^>]+>/g, ' ');
    assert.match(aTo, /admin\/login\/verify$/, 'a Telegram code is required on a new device');
    const v = await a.get('admin/login/verify');
    assert.match(v.text, /Check Telegram/);
    assert.match(v.text, /data-tg-approval/);
    const codeMsg = tg.lastTo(CHAT);
    assert.match(codeMsg.text, /Sign-in code: <code>\d{6}<\/code>/);
    const approve = codeMsg.buttons.find((b) => /Approve/.test(b.text)).callback_data;
    tg.press(9990003, approve); // someone else's chat can't approve
    await sleep(2100);
    assert.equal((await a.postJSON('admin/login/verify/status', { trust: 1 }, token(v.text))).json.status, 'pending');
    tg.press(CHAT, approve);
    const done = await waitFor(async () => { const r = (await a.postJSON('admin/login/verify/status', { trust: 1 }, token(v.text))).json; return r.status === 'approved' && r; }, 'approval');
    assert.match(done.redirect, /\/admin$/);
    assert.equal((await a.get('admin')).status, 200, 'signed in by tapping Approve');
    tg.press(CHAT, approve); // pressing again does nothing
    await sleep(2100);

    // 5) Trusted device: the next sign-in on that browser skips the Telegram code
    a.jar.delete('ivadmin');
    assert.match(await a.login(ADMIN, PASSWORD), /\/admin$/, 'trusted device: no code');

    // 6) Deny blocks the sign-in and alerts the owner
    ageCodes();
    const b = new Client();
    await b.login(ADMIN, PASSWORD);
    const vb = await b.get('admin/login/verify');
    const deny = tg.lastTo(CHAT).buttons.find((x) => /Deny/.test(x.text)).callback_data;
    tg.press(CHAT, deny);
    const denied = await waitFor(async () => { const r = (await b.postJSON('admin/login/verify/status', {}, token(vb.text))).json; return r.status === 'denied' && r; }, 'deny');
    assert.match(denied.redirect, /admin\/login$/);
    assert.equal((await b.get('admin')).status, 302, 'not signed in');
    assert.ok(sql("SELECT id FROM notifications WHERE user_id = ? AND event = 'telegram_denied'", [uid]).length, 'owner alerted');

    // 7) Typing the code from Telegram also works; "email instead" falls back to email
    ageCodes();
    const c = new Client();
    await c.login(ADMIN, PASSWORD);
    const vc = await c.get('admin/login/verify');
    const typed = tg.lastTo(CHAT).text.match(/<code>(\d{6})<\/code>/)[1];
    const r7 = await c.post('admin/login/verify', { _token: token(vc.text), code: typed });
    assert.match(r7.location, /\/admin$/);
    ageCodes();
    const d = new Client();
    await d.login(ADMIN, PASSWORD);
    const vd = await d.get('admin/login/verify');
    ageCodes();
    await d.post('admin/login/resend', { _token: token(vd.text), to: 'email' });
    const ve = await d.get('admin/login/verify');
    assert.match(ve.text, /Check your email/, 'email fallback: ' + flashText(ve.text).join(' | '));

    // 8) Confirmations for sensitive actions arrive in Telegram too; trusted devices keep them longer
    setSetting('stepup_trusted_minutes', '60');
    sql('UPDATE login_otps SET created_at = created_at - INTERVAL 20 MINUTE WHERE user_id = ?', [uid]);
    const conf = await a.get('admin/confirm');
    assert.match(conf.text, /Code we sent to your Telegram/);
    assert.match(tg.lastTo(CHAT).text, /Confirmation code/);
    const code8 = conf.text.match(/Debug mode — code: <code class="font-bold">(\d{6})/)[1];
    await a.post('admin/confirm', { _token: token(conf.text), method: 'email', code: code8 });
    const mins = Number(((await a.get('admin')).text.match(/confirmed for (\d+) more minute/) || [])[1]);
    assert.ok(mins > 50, `trusted device keeps the confirmation ~60 min (got ${mins})`);

    // 9) Alerts: security notices and new leads
    sql('DELETE FROM rate_limits');
    const visitor = new Client();
    const qp = await visitor.get('contact');
    await sleep(3200);
    await visitor.post('lead', { form: 'contact', _ft: formToken(qp.text), fullName: 'E2E Telegram Lead', company: 'E2E Ltd', email: 'e2e+tg@example.com', phone: '+44 20 0000 0001', product: 'Hydraulic Cylinders', message: 'Hydraulic cylinder repair', consent: '1', 'cf-turnstile-response': TS_TOKEN }, { Accept: 'application/json' });
    assert.ok(tg.state.sent.some((s) => s.chat === String(CHAT) && /New enquiry/.test(s.text) && /E2E Telegram Lead/.test(s.text)), 'lead alert');

    // 10) /stop in Telegram disconnects
    tg.message(CHAT, '/stop');
    await waitFor(async () => { await admin.postJSON('admin/telegram/status', {}, token(prof.text)); return !sql('SELECT telegram_chat_id FROM users WHERE id = ?', [uid])[0].telegram_chat_id; }, '/stop');
  } finally {
    sql('UPDATE users SET telegram_chat_id = NULL, telegram_username = NULL, telegram_prefs = NULL, telegram_link_hash = NULL, telegram_link_expires = NULL WHERE id = ?', [uid]);
    sql("UPDATE known_devices SET trusted_until = NULL WHERE user_id = ?", [uid]);
    sql("DELETE FROM leads WHERE email LIKE 'e2e+%@example.com'");
    setSetting('telegram_enabled', false);
    setSetting('telegram_api_base', '');
    setSetting('stepup_trusted_minutes', '10');
    await tg.close();
  }
});

/* ------------------------------------------------------------------ */
test('admin theme switch and scroll buttons: applied before paint without inline scripts, follow settings', async () => {
  const dash = await admin.get('admin');
  const head = dash.text.split('</head>')[0];
  assert.match(head, /<script src="[^"]*js\/theme-init\.js[^"]*"><\/script>/, 'theme script in <head>, not deferred');
  assert.match(dash.text, /<html lang="en" data-theme-default="(light|dark|system)">/);
  assert.match(dash.text, /data-theme-toggle/);
  assert.match(dash.text, /data-scroll-up/);
  assert.match(dash.text, /data-scroll-down/);
  assert.doesNotMatch(dash.res.headers.get('content-security-policy') || '', /script-src[^;]*unsafe-inline/, 'CSP still blocks inline scripts');
  assert.doesNotMatch(head.replace(/<script src="[^"]+"[^>]*><\/script>/g, ''), /<script/, 'no inline script in the admin head');
  const js = await admin.get(head.match(/src="([^"]*theme-init\.js[^"]*)"/)[1].replace(/^\//, ''));
  assert.equal(js.status, 200);
  assert.match(js.text, /localStorage/);

  const login = await new Client().get('admin/login');
  assert.match(login.text, /theme-init\.js/);
  assert.match(login.text, /class="adm-login-theme adm-theme-toggle" data-theme-toggle/);

  setSetting('theme_default', 'dark');
  setSetting('scroll_buttons', false);
  try {
    const d2 = await admin.get('admin');
    assert.match(d2.text, /data-theme-default="dark"/, 'site default theme applies to the admin');
    assert.doesNotMatch(d2.text, /data-scroll-up/, 'scroll buttons follow the setting');
  } finally {
    setSetting('theme_default', 'light');
    setSetting('scroll_buttons', true);
  }
});

/* ------------------------------------------------------------------ */
test('keep me signed in: survives a browser restart, rotates, is device-bound, detects stolen tokens, ends on sign-out', async () => {
  setSetting('remember_enabled', true);
  setSetting('remember_days', 30);
  const c = new Client();
  assert.match(await c.login(ADMIN, PASSWORD, { remember: '1' }), /\/admin$/);
  assert.ok(c.jar.has('ivremember') && c.jar.has('ivdev'), 'remember + device cookies set');
  const first = c.jar.get('ivremember');
  c.jar.delete('ivadmin'); // browser restart: session cookie gone
  assert.equal((await c.get('admin')).status, 200, 'signed in again from the remember token');
  assert.notEqual(c.jar.get('ivremember'), first, 'token rotated after use');

  const thief = new Client();
  thief.jar.set('ivremember', c.jar.get('ivremember')); // token without the device cookie
  assert.equal((await thief.get('admin')).status, 302, 'token alone is useless');

  const replay = new Client();
  replay.jar.set('ivdev', c.jar.get('ivdev'));
  replay.jar.set('ivremember', first); // an old, already-rotated token
  assert.equal((await replay.get('admin')).status, 302);
  c.jar.delete('ivadmin');
  assert.equal((await c.get('admin')).status, 302, 'replayed old token revoked the remembered sign-in');

  const d = new Client();
  await d.login(ADMIN, PASSWORD, { remember: '1' });
  await d.post('admin/logout', { _token: token((await d.get('admin')).text) });
  d.jar.delete('ivadmin');
  assert.equal((await d.get('admin')).status, 302, 'sign-out ends "keep me signed in"');
});

test('trusted device: skips the sign-in code until trust is removed; sign out everywhere ends other sessions', async () => {
  sql('DELETE FROM rate_limits');
  const uid = adminId();
  const ageCodes = () => sql('UPDATE login_otps SET created_at = created_at - INTERVAL 20 MINUTE WHERE user_id = ?', [uid]);
  setSetting('admin_otp_enabled', true);
  try {
    const c = new Client();
    ageCodes();
    assert.match(await c.login(ADMIN, PASSWORD), /admin\/login\/verify$/, 'code required on a new device');
    const v = await c.get('admin/login/verify');
    assert.match(v.text, /Don’t ask for a code on this device/);
    const code = (v.text.match(/the code is <code class="font-bold">(\d{6})/) || [])[1];
    assert.ok(code, 'debug code shown');
    const r = await c.post('admin/login/verify', { _token: token(v.text), code, trust: '1' });
    assert.match(r.location, /\/admin$/);
    assert.ok(sql('SELECT trusted_until FROM known_devices WHERE user_id = ? AND trusted_until > NOW()', [uid]).length >= 1);

    c.jar.delete('ivadmin');
    assert.match(await c.login(ADMIN, PASSWORD), /\/admin$/, 'trusted device: password only, no code');

    const prof = await c.get('admin/profile');
    assert.match(prof.text, /Devices &amp; sessions/);
    assert.match(prof.text, /This device/);
    const devId = sql('SELECT id FROM known_devices WHERE user_id = ? AND trusted_until > NOW() ORDER BY last_seen DESC LIMIT 1', [uid])[0].id;
    await c.post(`admin/devices/${devId}`, { _token: token(prof.text), action: 'untrust' });
    c.jar.delete('ivadmin');
    ageCodes();
    assert.match(await c.login(ADMIN, PASSWORD), /admin\/login\/verify$/, 'asks for a code again after "Stop trusting"');
  } finally {
    setSetting('admin_otp_enabled', false);
  }

  const a = new Client(); const b = new Client();
  await a.login(ADMIN, PASSWORD); await b.login(ADMIN, PASSWORD, { remember: '1' });
  assert.equal((await b.get('admin')).status, 200);
  await a.post('admin/devices/signout-all', { _token: token((await a.get('admin/profile')).text) });
  assert.equal((await a.get('admin')).status, 200, 'the device that clicked stays signed in');
  assert.equal((await b.get('admin')).status, 302, 'other open session ended');
  b.jar.delete('ivadmin');
  assert.equal((await b.get('admin')).status, 302, 'and its "keep me signed in" was revoked');
  // The main test client was "another device" too — sign it back in for the clean-up hook
  await admin.login(ADMIN, PASSWORD);
  csrf = token((await admin.get('admin')).text);
});
