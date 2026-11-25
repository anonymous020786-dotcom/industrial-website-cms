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
  const later = await admin.get('admin/profile');
  assert.doesNotMatch(later.text, new RegExp(codes[0]), 'not shown again');
  assert.match(later.text, /can still be downloaded for \d+ more minute/, 'download reminder while the window is open');

  // Download the codes as TXT, CSV (Excel-safe, UTF-8 BOM) and a real .xlsx
  const dl = async (format) => admin.req('admin/2fa/recovery/download', { method: 'POST', body: new URLSearchParams({ _token: csrf, format }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  const txt = await dl('txt');
  assert.equal(txt.status, 200);
  assert.match(txt.headers.get('content-disposition') || '', /attachment; filename="recovery-codes-.+\.txt"/);
  assert.match(txt.headers.get('cache-control') || '', /no-store/);
  const txtBody = await txt.text();
  for (const code of codes) assert.ok(txtBody.includes(code), 'txt has ' + code);
  assert.match(txtBody, new RegExp(` 1\\. ${codes[0]}`));
  const csvRes = await dl('csv');
  assert.match(csvRes.headers.get('content-type') || '', /text\/csv/);
  const csvBytes = Buffer.from(await csvRes.arrayBuffer());
  assert.deepEqual([...csvBytes.subarray(0, 3)], [0xef, 0xbb, 0xbf], 'UTF-8 BOM for Excel');
  const csvLines = csvBytes.toString('utf8').slice(1).trim().split(/\r?\n/);
  assert.equal(csvLines[0], '#,"Recovery code",Account,Site,Created');
  assert.equal(csvLines.length, 11);
  assert.ok(csvLines[1].startsWith(`1,${codes[0]},`));
  const xlsx = await dl('xlsx');
  assert.match(xlsx.headers.get('content-type') || '', /spreadsheetml\.sheet/);
  const xbuf = Buffer.from(await xlsx.arrayBuffer());
  assert.equal(xbuf.subarray(0, 2).toString(), 'PK', 'xlsx is a zip package');
  const sheet = php(['-r', '$z=new ZipArchive;$f=tempnam(sys_get_temp_dir(),"x");file_put_contents($f,base64_decode($argv[1]));$z->open($f);echo $z->getFromName("xl/worksheets/sheet1.xml") ? "SHEET:" . $z->getFromName("xl/worksheets/sheet1.xml") : "NO SHEET";', xbuf.toString('base64')]);
  for (const code of codes) assert.ok(sheet.includes(`<t xml:space="preserve">${code}</t>`), 'xlsx has ' + code);
  assert.ok(sql("SELECT id FROM auth_events WHERE event = 'recovery_codes_downloaded' AND user_id = ?", [adminId()]).length >= 3, 'downloads are logged');
  // "Done, I've saved them" ends the download window; other sessions never had one
  const stranger = await new Client().post('admin/2fa/recovery/download', { _token: 'x', format: 'txt' });
  assert.equal(stranger.status, 302, 'signed-out browsers are sent to sign in');
  assert.match(stranger.location, /admin\/login/);
  await admin.post('admin/2fa/recovery/forget', { _token: csrf });
  const gone = await dl('txt');
  assert.equal(gone.status, 302, 'no download after "Done"');

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
  // Collapsible sidebar: desktop toggle (state applied early by theme-init.js), phone close button, labels kept for screen readers
  assert.match(dash.text, /data-sidebar-toggle aria-controls="adm-sidebar" aria-expanded="true"/);
  assert.match(dash.text, /id="adm-sidebar"[\s\S]*data-sidebar-close aria-label="Close menu"/);
  assert.match(dash.text, /class="adm-nav-link[^"]*"[^>]*title="Dashboard"[\s\S]{0,700}<span class="adm-nav-text flex-1">Dashboard<\/span>/);
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
test('quotations: from a lead, server-side totals, PDF, email with attachment, customer accepts online, lead won', async () => {
  sql("DELETE FROM quotes WHERE email LIKE 'e2e+%@example.com'");
  sql("INSERT INTO leads (type, status, name, company, email, phone, product, items, consent) VALUES ('quote', 'new', 'E2E Quote Buyer', 'E2E Cranes Ltd', 'e2e+qb@example.com', '+91 90000 00000', 'Hydraulic Cylinders', ?, 1)",
    [JSON.stringify([{ slug: 'hydraulic-cylinders', name: 'Hydraulic Cylinders', part: 'HC-80', qty: 4, notes: '' }])]);
  const leadId = Number(sql("SELECT id FROM leads WHERE email = 'e2e+qb@example.com' ORDER BY id DESC LIMIT 1")[0].id);
  try {
    // Prefilled from the lead
    const nw = await admin.get(`admin/quotes/new?lead=${leadId}`);
    assert.equal(nw.status, 200);
    assert.match(nw.text, /value="E2E Quote Buyer"/);
    assert.match(nw.text, /value="Hydraulic Cylinders"/, 'requested item becomes a line');
    // Totals are computed on the server (a forged total is ignored)
    const saved = await admin.post('admin/quotes/new', { _token: token(nw.text), lead_id: String(leadId), customer_name: 'E2E Quote Buyer', company: 'E2E Cranes Ltd', email: 'e2e+qb@example.com',
      currency: 'INR', tax_label: 'GST', tax_rate: '18', valid_until: '2099-12-31', total: '1',
      'items[0][description]': 'Hydraulic cylinder 80/45 × 500', 'items[0][part]': 'HC-80', 'items[0][qty]': '4', 'items[0][unit]': 'pcs', 'items[0][price]': '1000', 'items[0][discount]': '10',
      'items[1][description]': 'Seal kit', 'items[1][qty]': '2', 'items[1][unit]': 'sets', 'items[1][price]': '250', 'items[1][discount]': '0',
      'items[2][description]': '', 'items[2][price]': '999' });
    assert.equal(saved.status, 302);
    const q = sql("SELECT * FROM quotes WHERE email = 'e2e+qb@example.com' ORDER BY id DESC LIMIT 1")[0];
    assert.equal(Number(q.subtotal), 4100, '4×1000×0.9 + 2×250');
    assert.equal(Number(q.tax), 738);
    assert.equal(Number(q.total), 4838);
    assert.equal(JSON.parse(q.items).length, 2, 'empty lines dropped');
    assert.match(q.number, /^Q-\d{4}-\d{4}$/);
    assert.equal(q.status, 'draft');

    // Drafts are private: the customer link only works once sent
    assert.equal((await new Client().get(`q/${q.token}`)).status, 404);
    const pdf = await admin.req(`admin/quotes/${q.id}/pdf`);
    assert.match(pdf.headers.get('content-type') || '', /application\/pdf/);
    assert.equal(Buffer.from(await pdf.arrayBuffer()).subarray(0, 5).toString(), '%PDF-');

    // Send: email with the PDF attached, lead moves to "quoted" with the value
    const ed = await admin.get(`admin/quotes/${q.id}/edit`);
    const sent = await admin.post(`admin/quotes/${q.id}/send`, { _token: token(ed.text), message: 'Please find our offer.' });
    assert.equal(sent.status, 302);
    assert.equal(sql('SELECT status FROM quotes WHERE id = ?', [q.id])[0].status, 'sent');
    const lead = sql('SELECT status, value FROM leads WHERE id = ?', [leadId])[0];
    assert.equal(lead.status, 'quoted');
    assert.equal(Number(lead.value), 4838);
    // The dev mailbox keeps the newest 200 emails, so look for this quote's email among the latest
    const mail = readdirSync(join(ROOT, 'app/storage/mail')).sort().slice(-5).reverse().map((f) => readFileSync(join(ROOT, 'app/storage/mail', f), 'utf8')).find((m) => m.includes(q.token)) || '';
    assert.match(mail, new RegExp(`attachments: Quotation-${q.number}\\.pdf`));
    assert.match(mail, new RegExp(`/q/${q.token}`), 'private link in the email');

    // Customer view: opened, accept needs a name + confirmation, then the lead is won
    const cust = new Client();
    const view = await cust.get(`q/${q.token}`);
    assert.equal(view.status, 200);
    assert.match(view.text, new RegExp(q.number));
    assert.match(view.text, /noindex/);
    assert.ok(sql('SELECT viewed_at FROM quotes WHERE id = ?', [q.id])[0].viewed_at, 'first view recorded');
    assert.equal((await cust.get(`q/${q.token}/pdf`)).status, 200);
    const noName = await cust.post(`q/${q.token}`, { decision: 'accept', name: '', agree: '1' });
    assert.match(noName.text, /type your full name/);
    const ok = await cust.post(`q/${q.token}`, { decision: 'accept', name: 'Ravi Kumar', agree: '1' });
    assert.match(ok.location, /\?done=accepted$/);
    const after = sql('SELECT status, decided_name FROM quotes WHERE id = ?', [q.id])[0];
    assert.equal(after.status, 'accepted');
    assert.equal(after.decided_name, 'Ravi Kumar');
    assert.equal(sql('SELECT status FROM leads WHERE id = ?', [leadId])[0].status, 'won');
    assert.ok(sql("SELECT id FROM notifications WHERE event = 'quote_accepted' AND created_at >= NOW() - INTERVAL 2 MINUTE").length, 'team alerted');
    assert.match((await cust.post(`q/${q.token}`, { decision: 'decline', name: 'x' })).text, /already been accepted/, 'decided only once');

    // Accepted quotes are locked; duplicating starts a new draft with the next number
    const locked = await admin.post(`admin/quotes/${q.id}/edit`, { _token: token(ed.text), customer_name: 'Changed' });
    assert.equal(sql('SELECT customer_name FROM quotes WHERE id = ?', [q.id])[0].customer_name, 'E2E Quote Buyer', 'locked');
    assert.equal(locked.status, 302);
    await admin.post(`admin/quotes/${q.id}/duplicate`, { _token: token(ed.text) });
    const copy = sql("SELECT number, status FROM quotes WHERE email = 'e2e+qb@example.com' ORDER BY id DESC LIMIT 1")[0];
    assert.equal(copy.status, 'draft');
    assert.notEqual(copy.number, q.number);
    assert.match((await admin.get('admin/quotes')).text, new RegExp(q.number));
    assert.match((await admin.get(`admin/leads/${leadId}`)).text, new RegExp(`${q.number}[\\s\\S]{0,400}Accepted`), 'quotes listed on the lead');
    assert.ok(sql("SELECT id FROM content_log WHERE entity = 'quote' AND entity_id = ? AND action = 'accepted'", [q.id]).length, 'history recorded');
  } finally {
    sql("DELETE FROM quotes WHERE email LIKE 'e2e+%@example.com'");
    sql('DELETE FROM leads WHERE id = ?', [leadId]);
    sql("DELETE FROM notifications WHERE event LIKE 'quote_%'");
  }
});

/* ------------------------------------------------------------------ */
test('service requests: urgent breakdown alerts the team, customer gets a reference and a tracking page with updates', async () => {
  sql('DELETE FROM rate_limits');
  const v = new Client();
  const page = await v.get('service-request');
  assert.equal(page.status, 200);
  assert.match(page.text, /name="form" value="service"/);
  await sleep(3200);
  const base = { form: 'service', _ft: formToken(page.text), urgency: 'urgent', equipment: 'E2E 50 t EOT crane hoist', fullName: 'E2E Plant Engineer', company: 'E2E Steel',
    phone: '+91 90000 11111', email: 'e2e+svc@example.com', message: 'Hoist brake slipping under load, crane stopped.', consent: '1', 'cf-turnstile-response': TS_TOKEN };
  const bad = await v.post('lead', { ...base, location: '' }, { Accept: 'application/json' });
  assert.equal(bad.status, 422);
  assert.ok(JSON.parse(bad.text).errors.location, 'site location required');
  const r = await v.post('lead', { ...base, location: 'E2E Works, Pune' }, { Accept: 'application/json' });
  const json = JSON.parse(r.text);
  assert.equal(r.status, 200, r.text);
  assert.match(json.ref, /^SR-\d{5}$/);
  assert.match(json.track, /\/service-request\/track\/[a-f0-9]{32}$/);
  const lead = sql("SELECT * FROM leads WHERE email = 'e2e+svc@example.com' ORDER BY id DESC LIMIT 1")[0];
  try {
    assert.equal(lead.type, 'service');
    assert.equal(lead.priority, 'high', 'urgent breakdown is high priority');
    assert.equal(lead.service_status, 'received');
    assert.ok(sql("SELECT id FROM notifications WHERE event = 'service_urgent' AND link = ?", [`admin/leads/${lead.id}`]).length, 'team alerted');

    const trackPath = new URL(json.track).pathname.slice(1);
    const tr = await new Client().get(trackPath);
    assert.equal(tr.status, 200);
    assert.match(tr.text, new RegExp(json.ref));
    assert.match(tr.text, /noindex/);
    assert.equal((await new Client().get('service-request/track/' + 'f'.repeat(32))).status, 404);

    // Admin posts an update: visible to the customer, internal notes are not
    const show = await admin.get(`admin/leads/${lead.id}`);
    assert.match(show.text, new RegExp(`Service ${json.ref}`));
    await admin.post(`admin/leads/${lead.id}/note`, { _token: token(show.text), body: 'E2E internal: customer is difficult', kind: 'note' });
    await admin.post(`admin/leads/${lead.id}/service`, { _token: token(show.text), service_status: 'scheduled', message: 'E2E engineer visit Thursday 10:00' });
    assert.equal(sql('SELECT service_status FROM leads WHERE id = ?', [lead.id])[0].service_status, 'scheduled');
    const tr2 = await new Client().get(trackPath);
    assert.match(tr2.text, /E2E engineer visit Thursday 10:00/);
    assert.doesNotMatch(tr2.text, /customer is difficult/, 'internal notes stay private');
    assert.match((await admin.get('admin/leads?type=service')).text, new RegExp(`${json.ref} · Visit scheduled`));
  } finally {
    sql('DELETE FROM leads WHERE id = ?', [lead.id]);
    sql("DELETE FROM notifications WHERE event = 'service_urgent'");
  }
});

/* ------------------------------------------------------------------ */
test('calculators: correct hydraulic results, works without JavaScript, prefills a quote request', async () => {
  const c = new Client();
  const page = await c.get('calculators');
  assert.equal(page.status, 200);
  for (const id of ['force', 'speed', 'power', 'sizing']) assert.match(page.text, new RegExp(`data-calc="${id}"`));
  const j = async (q) => JSON.parse((await c.get('calculators?' + q, { Accept: 'application/json' })).text);
  assert.deepEqual((await j('c=force&bore=80&rod=45&pressure=160')).results[0], ['Push force', '80.4 kN (8.20 t)']);
  assert.deepEqual((await j('c=speed&bore=80&rod=45&flow=20&stroke=500')).results[0], ['Extend speed', '66.3 mm/s']);
  assert.match((await j('c=power&flow=40&pressure=200&efficiency=85')).results[0][1], /^15\.69 kW/);
  const sizing = await j('c=sizing&load=10&pressure=160&factor=1.25');
  assert.deepEqual(sizing.results[1], ['Recommended standard bore', '100 mm']);
  assert.deepEqual((await j('c=force&bore=40&rod=50&pressure=160')).results, [], 'rod larger than bore is rejected');
  // Without JavaScript the GET form shows the result; the quote link carries the numbers into the quote form
  const nojs = await c.get('calculators?c=force&bore=100&rod=56&pressure=200');
  assert.match(nojs.text, /157\.1 kN/);
  const q = await c.get(sizing.quote.replace(/^\//, ''));
  assert.match(q.text, /Cylinder sizing calculator — load to lift 10 tonnes/);
  assert.match(q.text, /<option value="Hydraulic Cylinders" selected>/);
});

/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
const leadForm = async (c, path, form, extra) => {
  const page = await c.get(path);
  assert.equal(page.status, 200, path);
  assert.match(page.text, new RegExp(`name="form" value="${form}"`));
  await sleep(3200); // the form token is only valid a few seconds after the page loads
  return { form, _ft: formToken(page.text), consent: '1', 'cf-turnstile-response': TS_TOKEN, ...extra, _page: path, page };
};
const cleanupMore = () => {
  sql("DELETE FROM part_refs WHERE ref_number LIKE 'e2e%'");
  sql("DELETE FROM downloads WHERE title LIKE 'e2e%'");
  sql("DELETE FROM distributors WHERE name LIKE 'e2e%'");
  sql("DELETE FROM leads WHERE email LIKE 'e2e+%@example.com'");
  sql("DELETE FROM jobs WHERE slug LIKE 'e2e%'");
  sql("DELETE FROM content_log WHERE title LIKE 'e2e%'");
};

test('part finder: cross-reference ignores punctuation, links our product; unknown numbers become "find me this part" requests', async () => {
  cleanupMore();
  sql('DELETE FROM rate_limits');
  const prod = sql("SELECT id, name FROM products WHERE slug = 'hydraulic-cylinders'")[0];
  let r = await admin.post('admin/r/part_refs/new', { _token: csrf, ref_number: 'e2e AB-12.345/c', brand: 'E2E Crane Co', product_id: prod.id, our_part: 'HC-E2E-63', notes: 'Direct replacement', status: 'published' });
  assert.equal(r.status, 302);
  const row = sql("SELECT * FROM part_refs WHERE ref_number = 'e2e AB-12.345/c'")[0];
  assert.equal(row.ref_norm, 'E2EAB12345C');
  assert.ok(sql("SELECT id FROM content_log WHERE entity = 'part_refs' AND entity_id = ? AND action = 'created'", [row.id]).length, 'change history records who added it');
  await admin.post('admin/r/part_refs/new', { _token: csrf, ref_number: 'e2e-hidden-999', brand: '', product_id: '', our_part: '', notes: '', status: 'draft' });

  const c = new Client();
  r = await c.get('parts?q=' + encodeURIComponent('E2EAB 12345 C'));
  assert.equal(r.status, 200);
  assert.match(r.text, /data-part-match/);
  assert.match(r.text, /Exact match/);
  assert.match(r.text, new RegExp(`Our equivalent: <strong class="text-navy">${prod.name}`));
  assert.match(r.text, /href="[^"]*quote\?product=Hydraulic\+Cylinders&amp;specs=Part\+number\+e2e\+AB-12\.345%2Fc/);
  assert.match((await c.get('parts?q=hc-e2e-63')).text, /data-part-match/, 'our own part number works too');
  assert.doesNotMatch((await c.get('parts?q=e2e-hidden-999')).text, /data-part-match/, 'drafts are hidden');

  const missing = await c.get('parts?q=e2e-zz-404');
  assert.match(missing.text, /data-part-none/);
  assert.match(missing.text, /id="partNumber" name="partNumber" type="text" required[^>]*value="e2e-zz-404"/, 'request form is prefilled');
  const base = await leadForm(c, 'parts?q=e2e-zz-404', 'part', { fullName: 'E2E Buyer', email: 'e2e+part@example.com', message: 'Need 2 pcs for an old crane.' });
  delete base.page;
  r = await c.post('lead', { ...base, partNumber: '' }, { Accept: 'application/json' });
  assert.equal(r.status, 422);
  assert.ok(JSON.parse(r.text).errors.partNumber);
  r = await c.post('lead', { ...base, partNumber: 'e2e-zz-404' }, { Accept: 'application/json' });
  assert.equal(r.status, 200, r.text);
  const lead = sql("SELECT * FROM leads WHERE email = 'e2e+part@example.com'")[0];
  assert.equal(lead.type, 'part');
  assert.equal(lead.part_number, 'e2e-zz-404');
  assert.ok(sql("SELECT id FROM events WHERE type = 'part_missing' AND page = 'e2e-zz-404'").length, 'missing searches are logged to spot demand');
});

test('downloads centre: grouped by category, instant or gated (lead first, once per device), counts downloads, hides drafts', async () => {
  assert.ok(pdfPath, 'needs the PDF from the media test');
  sql('DELETE FROM rate_limits');
  const mk = (title, category, gated, status = 'published') => admin.post('admin/r/downloads/new', { _token: csrf, title, category, file: pdfPath, description: 'e2e doc', gated: gated ? '1' : '0', status, sort_order: '0' });
  await mk('e2e Installation manual', 'Manuals', false);
  await mk('e2e Cylinder CAD pack', 'CAD drawings', true);
  await mk('e2e Draft brochure', 'Brochures', false, 'draft');
  const [manual, cad, draft] = ['e2e Installation manual', 'e2e Cylinder CAD pack', 'e2e Draft brochure'].map((t) => sql('SELECT * FROM downloads WHERE title = ?', [t])[0]);
  assert.equal(Number(cad.gated), 1);

  const c = new Client();
  const list = await c.get('downloads');
  assert.equal(list.status, 200);
  assert.match(list.text, /data-download-group="Manuals"[\s\S]*e2e Installation manual/);
  assert.match(list.text, /data-download-group="CAD drawings"[\s\S]*e2e Cylinder CAD pack[\s\S]*Quick form first/);
  assert.doesNotMatch(list.text, /e2e Draft brochure/);
  assert.equal((await c.get(`download/file/${draft.id}`)).status, 404);

  let r = await c.get(`download/file/${manual.id}`);
  assert.equal(r.status, 200);
  assert.match(r.type, /application\/pdf/);
  assert.equal(Number(sql('SELECT downloads FROM downloads WHERE id = ?', [manual.id])[0].downloads), 1);

  r = await c.get(`download/file/${cad.id}`);
  assert.equal(r.status, 302);
  const gate = new URL(r.location, BASE);
  assert.equal(gate.pathname + gate.search, `/catalogue?next=download%2Ffile%2F${cad.id}`);
  const form = await c.get(gate.pathname.slice(1) + gate.search);
  assert.match(form.text, /Download e2e Cylinder CAD pack/);
  assert.match(form.text, new RegExp(`name="next" value="download/file/${cad.id}"`));
  await sleep(3200);
  r = await c.post('catalogue', { _ft: formToken(form.text), form: 'download', next: `download/file/${cad.id}`, fullName: 'E2E Designer', company: 'E2E Ltd', email: 'e2e+cad@example.com', consent: '1', 'cf-turnstile-response': TS_TOKEN });
  assert.equal(r.status, 302);
  assert.equal(new URL(r.location, BASE).pathname, `/download/file/${cad.id}`);
  r = await c.get(`download/file/${cad.id}`);
  assert.match(r.type, /application\/pdf/);
  const lead = sql("SELECT * FROM leads WHERE email = 'e2e+cad@example.com'")[0];
  assert.equal(lead.type, 'download');
  assert.equal(lead.product, 'CAD drawings: e2e Cylinder CAD pack');
  assert.equal((await c.get('catalogue?next=' + encodeURIComponent('download/file/999999'))).status, 404);
  const adm = await admin.get('admin/r/downloads');
  assert.match(adm.text, /Form first/);
});

test('distributors: grouped by region with filter and map links; "become a distributor" applications are leads', async () => {
  sql('DELETE FROM rate_limits');
  const mk = (name, region, city) => admin.post('admin/r/distributors/new', { _token: csrf, name, region, city, country: 'India', address: '12 E2E Industrial Estate', phone: '+91 90000 22222', email: 'e2e@example.com', website: 'https://example.com', products: 'Hydraulic cylinders', status: 'published', sort_order: '0' });
  await mk('e2e West Hydraulics', 'E2E West', 'Pune');
  await mk('e2e North Cranes', 'E2E North', 'Delhi');
  const c = new Client();
  let r = await c.get('distributors');
  assert.equal(r.status, 200);
  assert.match(r.text, /E2E North[\s\S]*e2e North Cranes/);
  assert.match(r.text, /google\.com\/maps\/search\/\?api=1&amp;query=e2e%20West%20Hydraulics%2C%2012%20E2E%20Industrial%20Estate%2C%20Pune%2C%20India/);
  r = await c.get('distributors?region=' + encodeURIComponent('E2E West'));
  assert.match(r.text, /e2e West Hydraulics/);
  assert.doesNotMatch(r.text, /e2e North Cranes/);

  const base = await leadForm(c, 'distributors', 'distributor', { fullName: 'E2E Dealer', company: 'E2E Traders', phone: '+91 90000 33333', email: 'e2e+dealer@example.com', message: 'We sell crane parts in Nagpur since 2010.' });
  delete base.page;
  r = await c.post('lead', base, { Accept: 'application/json' });
  assert.equal(r.status, 422);
  assert.ok(JSON.parse(r.text).errors.location, 'region required');
  r = await c.post('lead', { ...base, location: 'Nagpur, Vidarbha' }, { Accept: 'application/json' });
  assert.equal(r.status, 200, r.text);
  const lead = sql("SELECT * FROM leads WHERE email = 'e2e+dealer@example.com'")[0];
  assert.equal(lead.type, 'distributor');
  assert.equal(lead.location, 'Nagpur, Vidarbha');
});

test('careers: openings with JobPosting data, CV required, applications admin-only and kept out of sales leads, closed jobs answer 410', async () => {
  sql('DELETE FROM rate_limits');
  const future = new Date(Date.now() + 30 * 86400e3).toISOString().slice(0, 10);
  const mk = (title, slug, status) => admin.post('admin/r/jobs/new', { _token: csrf, title, slug, department: 'Service', location: 'Pune', job_type: 'Full-time', closes_at: future, summary: 'Field service for cranes.', description: '<p>Fix cranes.</p><script>alert(1)</script>', status, sort_order: '0' });
  await mk('e2e Service Engineer', 'e2e-service-engineer', 'published');
  await mk('e2e Secret Role', 'e2e-secret-role', 'draft');
  const job = sql("SELECT * FROM jobs WHERE slug = 'e2e-service-engineer'")[0];
  assert.doesNotMatch(job.description, /<script/i, 'job description is sanitised');

  const c = new Client();
  let r = await c.get('careers');
  assert.match(r.text, /e2e Service Engineer/);
  assert.doesNotMatch(r.text, /e2e Secret Role/);
  assert.equal((await c.get('careers/e2e-secret-role')).status, 404);
  assert.equal((await admin.get('careers/e2e-secret-role?preview=1')).status, 200, 'team can preview drafts');
  const page = await c.get('careers/e2e-service-engineer');
  assert.equal(page.status, 200);
  assert.match(page.text, /"@type":"JobPosting"/);
  assert.match(page.text, new RegExp(`"validThrough":"${future}T23:59:59"`));

  await sleep(3200);
  const fields = { form: 'job', _ft: formToken(page.text), job_id: String(job.id), fullName: 'E2E Applicant', email: 'e2e+cv@example.com', phone: '+91 90000 44444', location: 'Pune', message: '', consent: '1', 'cf-turnstile-response': TS_TOKEN };
  const send = (withCv, name = 'e2e-cv.pdf') => {
    const fd = new FormData();
    for (const [k, v] of Object.entries(fields)) fd.append(k, v);
    if (withCv) fd.append('attachments[]', new Blob(['%PDF-1.4\n% e2e cv\n'], { type: 'application/pdf' }), name);
    return c.req('lead', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
  };
  r = await send(false);
  assert.equal(r.status, 422);
  assert.ok((await r.json()).errors.attachments, 'CV required');
  r = await send(true, 'e2e-cv.exe');
  assert.equal(r.status, 422, 'only PDF / Word');
  r = await send(true);
  assert.equal(r.status, 200, await r.clone().text());
  const lead = sql("SELECT * FROM leads WHERE email = 'e2e+cv@example.com'")[0];
  assert.equal(lead.type, 'job');
  assert.equal(Number(lead.job_id), Number(job.id));

  assert.doesNotMatch((await admin.get('admin/leads?q=E2E+Applicant')).text, new RegExp(`admin/leads/${lead.id}"`), 'not in the sales leads list');
  const apps = await admin.get('admin/careers/applications?job=' + job.id);
  assert.equal(apps.status, 200);
  assert.match(apps.text, /data-application>E2E Applicant/);
  r = await admin.get(`admin/careers/applications/${lead.id}`);
  assert.equal(new URL(r.location, BASE).pathname, `/admin/leads/${lead.id}`);
  assert.match((await admin.get(`admin/leads/${lead.id}`)).text, /e2e-cv\.pdf/);

  sql('UPDATE jobs SET closes_at = CURDATE() - INTERVAL 1 DAY WHERE id = ?', [job.id]);
  r = await c.get('careers/e2e-service-engineer');
  assert.equal(r.status, 410);
  assert.match(r.text, /data-job-closed/);
  assert.doesNotMatch((await c.get('careers')).text, /e2e Service Engineer/);
  cleanupMore();
});

test('site search: ranks products first, highlights words, finds part numbers, jobs and downloads, suggests spelling fixes, escapes input', async () => {
  cleanupMore();
  const c = new Client();
  let r = await c.get('search?q=' + encodeURIComponent('hydraulic cylinders'));
  assert.equal(r.status, 200);
  assert.match(r.text, /<meta name="robots" content="noindex/);
  const first = r.text.match(/data-search-hit="([^"]+)"[\s\S]*?href="([^"]+)"/);
  assert.deepEqual([first[1], first[2]], ['Product', '/products/hydraulic-cylinders']);
  assert.match(r.text, /<mark>Hydraulic<\/mark> <mark>Cylinders<\/mark>/);

  r = await c.get('search?q=' + encodeURIComponent('hydralic cilinder'));
  assert.match(r.text, /data-search-suggestion>Did you mean <a[^>]*href="\/search\?q=hydraulic%20cylinders?"/);

  await admin.post('admin/r/part_refs/new', { _token: csrf, ref_number: 'e2e-QZ-7781', brand: 'E2E OEM', product_id: '', our_part: '', notes: '', status: 'published' });
  await admin.post('admin/r/jobs/new', { _token: csrf, title: 'e2e CNC Machinist', slug: 'e2e-cnc-machinist', department: 'Production', location: 'Pune', job_type: 'Full-time', closes_at: '', summary: 'Operate e2equartz lathes.', description: '', status: 'published', sort_order: '0' });
  await admin.post('admin/r/downloads/new', { _token: csrf, title: 'e2e Quartz lathe manual', category: 'Manuals', file: '/uploads/none.pdf', description: '', gated: '0', status: 'published', sort_order: '0' });
  r = await c.get('search?q=e2eqz7781');
  assert.match(r.text, /data-search-parts[\s\S]*e2e-QZ-7781/);
  r = await c.get('search?q=e2equartz');
  assert.match(r.text, /data-search-hit="Job opening"[\s\S]*careers\/e2e-cnc-machinist/);
  r = await c.get('search?q=' + encodeURIComponent('e2e quartz manual'));
  assert.match(r.text, /data-search-hit="Download · Manuals"/);

  r = await c.get('search?q=' + encodeURIComponent('<script>alert(1)</script> zzqx'));
  assert.doesNotMatch(r.text, /<script>alert\(1\)/);
  assert.match(r.text, /data-search-empty/);
  assert.match((await c.get('')).text, /class="header-search"[^>]*aria-label="Search"/, 'search button in the header');
  cleanupMore();
});

test('content approval: editors\' changes to live posts wait for an administrator; diff, send back, approve; activity log', async () => {
  const EDITOR = 'e2e-editor@example.com';
  const pw = 'E2e-Editor-Pass-91!';
  const cleanup = () => {
    sql("DELETE FROM content_reviews WHERE title LIKE 'e2e%'");
    sql("DELETE FROM content_log WHERE title LIKE 'e2e%'");
    sql("DELETE FROM revisions WHERE title LIKE 'e2e%'");
    sql("DELETE FROM posts WHERE slug LIKE 'e2e-approval%'");
    sql('DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email = ?)', [EDITOR]);
    sql('DELETE FROM users WHERE email = ?', [EDITOR]);
    setSetting('content_approval', false);
  };
  cleanup();
  const hash = php(['-r', `echo password_hash('${pw}', PASSWORD_DEFAULT);`]);
  sql("INSERT INTO users (name, email, username, password_hash, role, active) VALUES ('E2E Editor', ?, 'e2e-editor', ?, 'editor', 1)", [EDITOR, hash]);
  const editorId = Number(sql('SELECT id FROM users WHERE email = ?', [EDITOR])[0].id);
  const post = (over) => ({ _token: csrf, title: 'e2e Approval post', slug: 'e2e-approval-post', content: '<p>Original live text about crane hooks.</p>', excerpt: 'Live excerpt',
    status: 'published', published_at: '', category_id: '', tags: '', author_id: '', featured_image: '', featured_alt: '', focus_keyword: '', meta_title: '', meta_description: '', canonical_url: '', noindex: '0', ...over });
  let r = await admin.post('admin/posts/new', post({}));
  const pid = Number(new URL(r.location, BASE).pathname.match(/posts\/(\d+)/)[1]);
  setSetting('content_approval', true);
  try {
    sql('DELETE FROM rate_limits');
    const ed = new Client();
    assert.match(await ed.login(EDITOR, pw), /\/admin$/);
    let page = await ed.get(`admin/posts/${pid}/edit`);
    const et = token(page.text);
    r = await ed.post(`admin/posts/${pid}/edit`, post({ _token: et, content: '<p>Updated text about forged crane hooks.</p>' }));
    page = await ed.get(new URL(r.location, BASE).pathname.slice(1));
    assert.ok(flashText(page.text).some((f) => /sent to an administrator for approval/.test(f)), flashText(page.text).join('|'));
    assert.match(page.text, /data-review-pending[\s\S]*Your changes are waiting for approval/);
    assert.match(page.text, /Updated text about forged crane hooks/, 'editor keeps editing their pending version');
    assert.match(sql('SELECT content FROM posts WHERE id = ?', [pid])[0].content, /Original live text/, 'live post unchanged');
    assert.match((await new Client().get('blog/e2e-approval-post')).text, /Original live text/);
    const rev = sql("SELECT * FROM content_reviews WHERE entity = 'post' AND entity_id = ? AND status = 'pending'", [pid])[0];
    assert.ok(rev, 'pending review stored');
    assert.ok(sql("SELECT id FROM notifications WHERE event = 'content_review' AND link = ?", [`admin/reviews/${rev.id}`]).length, 'administrators notified');
    // Editors cannot skip approval through bulk publish or restoring a revision
    r = await ed.post('admin/posts/bulk', { _token: et, bulk_action: 'publish', 'ids[]': String(pid) });
    assert.ok(flashText((await ed.get('admin/posts')).text).some((f) => /needs an administrator/.test(f)));
    assert.equal((await ed.get('admin/reviews')).status, 403, 'editors cannot open the review queue');

    // Administrator: queue, diff, send back (note required), then approve a resubmission
    assert.match((await admin.get('admin/reviews')).text, /data-review-row[\s\S]*e2e Approval post/);
    const diff = await admin.get(`admin/reviews/${rev.id}`);
    assert.match(diff.text, /data-review-change="Content"[\s\S]*<del>Original<\/del><ins>Updated<\/ins> <del>live <\/del>text about<ins> forged<\/ins> crane hooks/);
    r = await admin.post(`admin/reviews/${rev.id}/decide`, { _token: csrf, decision: 'reject', note: '' });
    assert.equal(sql('SELECT status FROM content_reviews WHERE id = ?', [rev.id])[0].status, 'pending', 'note required to send back');
    await admin.post(`admin/reviews/${rev.id}/decide`, { _token: csrf, decision: 'reject', note: 'Say which standard the hooks meet.' });
    assert.equal(sql('SELECT status FROM content_reviews WHERE id = ?', [rev.id])[0].status, 'rejected');
    assert.ok(sql("SELECT id FROM notifications WHERE user_id = ? AND event = 'content_review_rejected'", [editorId]).length, 'editor told why');

    page = await ed.get(`admin/posts/${pid}/edit`);
    await ed.post(`admin/posts/${pid}/edit`, post({ _token: token(page.text), content: '<p>Updated text about forged crane hooks to DIN 15400.</p>' }));
    const rev2 = sql("SELECT id FROM content_reviews WHERE entity = 'post' AND entity_id = ? AND status = 'pending'", [pid])[0];
    await admin.post(`admin/reviews/${rev2.id}/decide`, { _token: csrf, decision: 'approve', note: '' });
    assert.match(sql('SELECT content FROM posts WHERE id = ?', [pid])[0].content, /DIN 15400/, 'approved change is live');
    assert.match((await new Client().get('blog/e2e-approval-post')).text, /DIN 15400/);
    assert.ok(sql("SELECT id FROM notifications WHERE user_id = ? AND event = 'content_review_approved'", [editorId]).length);

    // New posts an editor wants to publish start as drafts until approved
    page = await ed.get('admin/posts/new');
    r = await ed.post('admin/posts/new', post({ _token: token(page.text), title: 'e2e Approval new', slug: 'e2e-approval-new' }));
    assert.equal(sql("SELECT status FROM posts WHERE slug = 'e2e-approval-new'")[0].status, 'draft');
    assert.equal((await new Client().get('blog/e2e-approval-new')).status, 404);

    const act = await admin.get('admin/activity?user=' + editorId);
    assert.match(act.text, /data-activity="post:submitted"/);
    assert.match((await admin.get('admin/activity?entity=post&action=approved')).text, /data-activity="post:approved"[\s\S]*e2e Approval post/);

    // Approval off: editors publish directly
    setSetting('content_approval', false);
    page = await ed.get(`admin/posts/${pid}/edit`);
    await ed.post(`admin/posts/${pid}/edit`, post({ _token: token(page.text), content: '<p>Direct edit by editor.</p>' }));
    assert.match(sql('SELECT content FROM posts WHERE id = ?', [pid])[0].content, /Direct edit by editor/);
  } finally {
    cleanup();
  }
});

test('weekly owner report: figures, needs-attention list, preview, send now, once a week from cron', async () => {
  const keep = ['report_recipients', 'report_day', 'report_hour', 'report_weekly'].map((k) => [k, getSetting(k)]);
  sql("INSERT INTO leads (type, status, priority, name, email, product, message, source_page, created_at) VALUES ('quote', 'new', 'high', 'E2E Weekly', 'e2e+weekly@example.com', 'Hydraulic Cylinders', 'x', 'http://localhost/products/e2e-weekly-page', NOW() - INTERVAL 30 HOUR)");
  sql("INSERT INTO events (type, page) VALUES ('part_missing', 'e2e-miss-7781'), ('part_missing', 'e2e-miss-7781'), ('search_empty', 'e2e nothing here')");
  try {
    const pv = await admin.get('admin/reports/weekly');
    assert.equal(pv.status, 200);
    assert.match(pv.text, /data-weekly-preview/);
    assert.match(pv.text, /New enquiries/);
    assert.match(pv.text, /not been contacted for more than 24 hours/);
    assert.match(pv.text, /Pages bringing enquiries[\s\S]*\/products\/e2e-weekly-page/);
    assert.match(pv.text, /could not find[\s\S]*e2e-miss-7781[\s\S]*>2</);
    assert.match(pv.text, /Searches with no results[\s\S]*e2e nothing here/);

    setSetting('report_recipients', 'e2e+owner@example.com, not-an-email');
    const before = Date.now();
    await admin.post('admin/reports/weekly/send', { _token: csrf });
    if (DEBUG) {
      const dir = join(ROOT, 'app/storage/mail');
      const newest = readdirSync(dir).filter((f) => f.endsWith('.html')).sort().slice(-5).map((f) => readFileSync(join(dir, f), 'utf8'));
      assert.ok(newest.some((h) => /Weekly report: \d+ enquiries/.test(h) && /e2e\+owner@example\.com/.test(h) && /e2e-miss-7781/.test(h)), 'report emailed to the chosen recipients');
    }
    assert.ok(sql("SELECT id FROM content_log WHERE entity = 'report' AND created_at >= NOW() - INTERVAL 1 MINUTE").length);

    // Cron: due on the chosen day/hour, then not again the same week
    const run = () => php(['-r', "require 'app/bootstrap.php'; echo cron_weekly_report(false) ?? 'not due';"]).trim();
    const dow = Number(php(['-r', "require 'app/bootstrap.php'; echo date('N');"]));
    setSetting('report_weekly', true);
    setSetting('report_day', dow === 7 ? 1 : dow + 1);
    setSetting('report_hour', 0);
    setSetting('cron_last_weekly', 0);
    assert.equal(run(), 'not due', 'wrong weekday');
    setSetting('report_day', dow);
    assert.match(run(), /^weekly report sent \(1 email recipient/);
    setSetting('cron_last_weekly', Math.floor(before / 1000));
    assert.equal(run(), 'not due', 'only once a week');
  } finally {
    for (const [k, v] of keep) setSetting(k, v);
    setSetting('cron_last_weekly', 0);
    sql("DELETE FROM events WHERE page LIKE 'e2e%'");
    sql("DELETE FROM leads WHERE email = 'e2e+weekly@example.com'");
    sql("DELETE FROM content_log WHERE entity = 'report'");
  }
});

test('deploy safety: the site finishes an unfinished database upgrade itself; cache refresh needs a one-time token', async () => {
  const target = Number(getSetting('schema_version'));
  setSetting('schema_version', target - 1);
  const r = await new Client().get('');
  assert.equal(r.status, 200);
  assert.equal(Number(getSetting('schema_version')), target, 'migrations ran on the first request');
  assert.equal((await new Client().get('refresh-cache.php?t=' + 'a'.repeat(64))).status, 404);
  const out = php(['-r', "require 'app/bootstrap.php'; echo updater_refresh_web_cache(), '|', is_file(STORAGE_DIR . '/refresh.token') ? 'left' : 'used';"]).trim();
  assert.match(out, /^(cache cleared|no code cache)\|used$/, out);
});

/* ------------------------------------------------------------------ */
const BROWSER = { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0) e2e-human' };
const mailFiles = () => {
  const dir = join(ROOT, 'app/storage/mail');
  return readdirSync(dir).filter((f) => f.endsWith('.html')).sort().slice(-40).reverse().map((f) => readFileSync(join(dir, f), 'utf8'));
};
const findMail = (re) => mailFiles().find((h) => re.test(h));
/** A published article the test owns (fresh installs ship every article as a draft). */
const e2ePost = (slug) => {
  sql('DELETE FROM posts WHERE slug = ?', [slug]);
  sql("INSERT INTO posts (title, slug, excerpt, content, status, published_at, allow_comments) VALUES (?, ?, 'e2e excerpt', '<p>e2e article body.</p>', 'published', NOW() - INTERVAL 1 HOUR, 1)", ['e2e Article ' + slug, slug]);
  return sql('SELECT id, slug, title FROM posts WHERE slug = ?', [slug])[0];
};

test('sharing: share bars with all networks and a short link, /s/ redirects and counts people (not preview bots), admin short links', async () => {
  sql("DELETE FROM short_links WHERE code LIKE 'e2e%' OR target LIKE '%e2e%'");
  const c = new Client();
  const page = await c.get('products/hydraulic-cylinders');
  for (const net of ['whatsapp', 'linkedin', 'facebook', 'x', 'telegram', 'reddit', 'email']) assert.match(page.text, new RegExp(`data-share-net="${net}"`), net);
  assert.match(page.text, /href="https:\/\/wa\.me\/\?text=Hydraulic%20Cylinders%20http[^"]*utm_source%3Dwhatsapp/);
  const code = page.text.match(/data-copy="http[^"]*\/s\/([a-z0-9]{6})"/)[1];
  const before = Number(sql('SELECT clicks FROM short_links WHERE code = ?', [code])[0].clicks);
  sql('DELETE FROM rate_limits');
  let r = await c.get('s/' + code, { 'User-Agent': 'WhatsApp/2.23 preview' });
  assert.equal(r.status, 301);
  assert.equal(new URL(r.location).pathname, '/products/hydraulic-cylinders');
  assert.equal(Number(sql('SELECT clicks FROM short_links WHERE code = ?', [code])[0].clicks), before, 'link-preview robots are not counted');
  await c.get('s/' + code, BROWSER);
  assert.equal(Number(sql('SELECT clicks FROM short_links WHERE code = ?', [code])[0].clicks), before + 1);
  assert.equal((await c.get('s/nope-not-here')).status, 404);

  r = await admin.post('admin/links/save', { _token: csrf, target: 'javascript:alert(1)', code: 'e2e-bad', title: '' });
  assert.equal(sql("SELECT COUNT(*) n FROM short_links WHERE code = 'e2e-bad'")[0].n, 0, 'unsafe targets refused');
  await admin.post('admin/links/save', { _token: csrf, target: BASE + '/quote?product=Hydraulic+Cylinders', code: 'e2e-expo', title: 'e2e Expo flyer' });
  const link = sql("SELECT * FROM short_links WHERE code = 'e2e-expo'")[0];
  assert.equal(link.target, '/quote?product=Hydraulic+Cylinders', 'own-site URLs stored as paths');
  await admin.post('admin/links/save', { _token: csrf, target: '/contact', code: 'e2e-expo', title: '' });
  assert.equal(sql("SELECT COUNT(*) n FROM short_links WHERE code = 'e2e-expo'")[0].n, 1, 'codes are unique');
  r = await c.get('s/e2e-expo', BROWSER);
  assert.equal(new URL(r.location).pathname + new URL(r.location).search, '/quote?product=Hydraulic+Cylinders');
  const list = await admin.get('admin/links?kind=manual');
  assert.match(list.text, /data-short-link="e2e-expo"/);
  await c.post('track', { type: 'share', page: 'linkedin|/products/hydraulic-cylinders' });
  assert.match((await admin.get('admin/links')).text, /LinkedIn<\/span><strong>\d+/);
  await admin.post(`admin/links/${link.id}/delete`, { _token: csrf });
  assert.equal((await c.get('s/e2e-expo')).status, 404);
  sql("DELETE FROM events WHERE type = 'share' AND page LIKE '%hydraulic-cylinders'");
});

test('comments: moderated, trusted after first approval, spam filter, team replies with email, per-post switch, escaped', async () => {
  const post = e2ePost('e2e-comments-article');
  const cleanup = () => { sql("DELETE FROM comments WHERE email LIKE 'e2e+%' OR name LIKE 'E2E%' OR body LIKE '%e2e-%' OR post_id = ?", [post.id]); sql('UPDATE posts SET allow_comments = 1 WHERE id = ?', [post.id]); };
  cleanup();
  setSetting('comments_enabled', true);
  setSetting('comments_moderation', 'first');
  sql('DELETE FROM rate_limits');
  const v = new Client();
  const submit = async (fields) => {
    const page = await v.get('blog/' + post.slug);
    await sleep(3200);
    return v.post(`blog/${post.slug}/comment`, { form: 'comment', _ft: formToken(page.text), consent: '1', 'cf-turnstile-response': TS_TOKEN, parent_id: '', fullName: 'E2E Reader', email: 'e2e+reader@example.com', ...fields });
  };
  try {
    let r = await submit({ comment: 'Great article e2e-one <script>alert(1)</script>, what grease do you use?' });
    assert.equal(r.status, 302);
    assert.match(r.location, /\?comment=pending#comments$/);
    const c1 = sql("SELECT * FROM comments WHERE email = 'e2e+reader@example.com' ORDER BY id DESC LIMIT 1")[0];
    assert.equal(c1.status, 'pending');
    assert.doesNotMatch((await v.get('blog/' + post.slug)).text, /e2e-one/, 'pending comments are hidden');
    assert.ok(sql("SELECT id FROM notifications WHERE event = 'comment_pending'").length, 'moderators alerted');

    const mod = await admin.get('admin/comments?status=pending');
    assert.match(mod.text, new RegExp(`data-mod-comment="${c1.id}"`));
    await admin.post(`admin/comments/${c1.id}/action`, { _token: csrf, action: 'approve' });
    let page = await v.get('blog/' + post.slug);
    assert.match(page.text, /e2e-one &lt;script&gt;alert\(1\)&lt;\/script&gt;/, 'shown and escaped');
    assert.match(page.text, /"commentCount":\d+/);

    r = await submit({ comment: 'Follow-up from the same reader e2e-two.' });
    assert.match(r.location, /\?comment=posted#comment-\d+$/, 'trusted after the first approved comment');

    r = await submit({ email: 'e2e+spam@example.com', fullName: 'E2E Spammer', comment: 'e2e-spam http://a.example http://b.example http://c.example' });
    assert.equal(sql("SELECT status FROM comments WHERE email = 'e2e+spam@example.com'")[0].status, 'spam');

    // Team reply from the moderation screen: published, threaded, the reader gets an email
    await admin.post(`admin/comments/${c1.id}/reply`, { _token: csrf, reply: 'Thanks! We use lithium EP2 grease. e2e-reply' });
    page = await v.get('blog/' + post.slug);
    assert.match(page.text, /comment comment-reply[\s\S]*team<\/span>[\s\S]*e2e-reply/);
    if (DEBUG) assert.ok(findMail(/Reply to your comment/), 'reply email');

    // Bulk actions and the per-post switch
    const spamId = sql("SELECT id FROM comments WHERE email = 'e2e+spam@example.com'")[0].id;
    await admin.post('admin/comments/bulk', { _token: csrf, bulk_action: 'delete', 'ids[]': String(spamId) });
    assert.equal(sql('SELECT COUNT(*) n FROM comments WHERE id = ?', [spamId])[0].n, 0);
    sql('UPDATE posts SET allow_comments = 0 WHERE id = ?', [post.id]);
    page = await v.get('blog/' + post.slug);
    assert.doesNotMatch(page.text, /id="comment-form"/);
    assert.doesNotMatch(page.text, /Leave a comment/);
    r = await v.post(`blog/${post.slug}/comment`, { form: 'comment', comment: 'x' });
    assert.equal(r.status, 422);
  } finally {
    cleanup();
    sql('DELETE FROM posts WHERE id = ?', [post.id]);
  }
});

test('newsletter: double opt-in, welcome, campaign with test, batch send, open/click tracking, signed links, one-click unsubscribe, import/export', async () => {
  const clean = () => {
    sql("DELETE cs FROM campaign_sends cs JOIN subscribers s ON s.id = cs.subscriber_id WHERE s.email LIKE 'e2e+%'");
    sql("DELETE FROM campaigns WHERE subject LIKE 'e2e%'");
    sql("DELETE FROM subscribers WHERE email LIKE 'e2e+%'");
    sql("DELETE FROM posts WHERE slug = 'e2e-newsletter-article'");
  };
  clean();
  e2ePost('e2e-newsletter-article');
  setSetting('newsletter_enabled', true);
  setSetting('newsletter_double_optin', true);
  sql('DELETE FROM rate_limits');
  try {
    const v = new Client();
    const home = await v.get('');
    assert.match(home.text, /data-newsletter/);
    await sleep(3200);
    const ft = formToken(home.text);
    let r = await v.post('newsletter/subscribe', { _ft: ft, email: 'not-an-email', source: 'footer' }, { Accept: 'application/json' });
    assert.equal(r.status, 422);
    r = await v.post('newsletter/subscribe', { _ft: ft, email: 'E2E+NL@example.com', source: 'footer' }, { Accept: 'application/json' });
    assert.match(JSON.parse(r.text).message, /check your inbox/);
    let sub = sql("SELECT * FROM subscribers WHERE email = 'e2e+nl@example.com'")[0];
    assert.equal(sub.status, 'pending');
    if (DEBUG) assert.ok(findMail(new RegExp(`newsletter/confirm/${sub.token}`)), 'confirmation email with link');
    r = await v.get(`newsletter/confirm/${sub.token}`);
    assert.match(r.text, /You are subscribed/);
    assert.equal(sql("SELECT status FROM subscribers WHERE email = 'e2e+nl@example.com'")[0].status, 'subscribed');
    r = await v.post('newsletter/subscribe', { _ft: ft, email: 'e2e+nl@example.com' }, { Accept: 'application/json' });
    assert.match(JSON.parse(r.text).message, /check your inbox/, 'same answer for existing addresses (no list disclosure)');

    // Campaign: draft, test, send
    r = await admin.post('admin/newsletter/new', { _token: csrf, subject: 'e2e Spring update', preheader: 'New sheaves', body: '<p>Read our <a href="https://example.com/e2e-landing">offer</a> or see <a href="/products">products</a>.</p>', latest_posts: '3' });
    const cid = Number(new URL(r.location, BASE).pathname.match(/newsletter\/(\d+)/)[1]);
    const edit = await admin.get(`admin/newsletter/${cid}/edit`);
    assert.match(edit.text, /srcdoc="[^"]*Latest articles/);
    await admin.post(`admin/newsletter/${cid}/test`, { _token: csrf });
    if (DEBUG) assert.ok(findMail(/\[Test\] e2e Spring update/));
    await admin.post(`admin/newsletter/${cid}/send`, { _token: csrf, mode: 'now' });
    sub = sql("SELECT * FROM subscribers WHERE email = 'e2e+nl@example.com'")[0];
    const send = sql('SELECT * FROM campaign_sends WHERE campaign_id = ? AND subscriber_id = ?', [cid, sub.id])[0];
    assert.ok(send && send.sent_at && !send.error, 'sent to the subscriber');
    if (DEBUG) {
      const mail = findMail(new RegExp(`subject: e2e Spring update[^\\n]*List-Unsubscribe: &lt;[^&]*newsletter/unsubscribe/${sub.token}&gt; \\| List-Unsubscribe-Post: List-Unsubscribe=One-Click`));
      assert.ok(mail, 'campaign email with one-click unsubscribe headers');
      assert.match(mail, new RegExp(`newsletter/o/${cid}/${sub.token}`), 'open pixel');
      const tracked = mail.match(new RegExp(`href="([^"]*newsletter/c/${cid}/${sub.token}\\?u=[^"]+)"`))[1].replace(/&amp;/g, '&');
      r = await v.get(tracked.replace(BASE + '/', ''));
      assert.equal(r.status, 302);
      assert.equal(r.location, 'https://example.com/e2e-landing');
      assert.match(mail, /href="http[^"]*\/products"|newsletter\/c\/[^"]*u=[^"]+/, 'site links absolute (and tracked)');
      r = await v.get(tracked.replace(BASE + '/', '').replace(/s=[^&]+/, 's=000000000000000000000000'));
      assert.equal(r.status, 404, 'unsigned redirects refused');
    }
    await v.get(`newsletter/o/${cid}/${sub.token}`);
    const st = sql('SELECT opened_at, clicked_at FROM campaign_sends WHERE campaign_id = ? AND subscriber_id = ?', [cid, sub.id])[0];
    assert.ok(st.opened_at, 'open recorded');
    if (DEBUG) assert.ok(st.clicked_at, 'click recorded');
    assert.equal(sql('SELECT status FROM campaigns WHERE id = ?', [cid])[0].status, 'sent');
    assert.match((await admin.get(`admin/newsletter/${cid}/edit`)).text, /data-campaign-stats/);

    // Unsubscribe: GET asks, one-click POST (mail apps) does it
    r = await v.get(`newsletter/unsubscribe/${sub.token}`);
    assert.match(r.text, /Yes, unsubscribe me/);
    assert.equal(sql('SELECT status FROM subscribers WHERE id = ?', [sub.id])[0].status, 'subscribed', 'GET alone does not unsubscribe');
    r = await new Client().post(`newsletter/unsubscribe/${sub.token}`, { 'List-Unsubscribe': 'One-Click' });
    assert.equal(r.status, 200);
    assert.equal(sql('SELECT status FROM subscribers WHERE id = ?', [sub.id])[0].status, 'unsubscribed');

    // Admin: add needs consent, CSV import, export (administrators)
    await admin.post('admin/subscribers/add', { _token: csrf, email: 'e2e+manual@example.com', name: '' });
    assert.equal(sql("SELECT COUNT(*) n FROM subscribers WHERE email = 'e2e+manual@example.com'")[0].n, 0, 'consent required');
    await admin.post('admin/subscribers/add', { _token: csrf, email: 'e2e+manual@example.com', name: 'E2E Manual', consent: '1' });
    assert.equal(sql("SELECT status FROM subscribers WHERE email = 'e2e+manual@example.com'")[0].status, 'subscribed');
    const fd = new FormData();
    fd.append('_token', csrf); fd.append('consent', '1');
    fd.append('csv', new Blob(['email,name\ne2e+imp1@example.com,One\nnot-valid,Bad\ne2e+imp2@example.com,Two\n'], { type: 'text/csv' }), 'list.csv');
    await admin.req('admin/subscribers/import', { method: 'POST', body: fd });
    assert.equal(sql("SELECT COUNT(*) n FROM subscribers WHERE email IN ('e2e+imp1@example.com', 'e2e+imp2@example.com') AND status = 'subscribed'")[0].n, 2);
    const csv = await admin.get('admin/subscribers/export');
    assert.match(csv.type, /text\/csv/);
    assert.match(csv.text, /e2e\+imp1@example\.com,One,subscribed,import/);
    assert.match((await admin.get('admin/subscribers?q=e2e%2Bimp')).text, /data-subscriber="e2e\+imp1@example\.com"/);
  } finally {
    clean();
  }
});

test('hiring: job details on the page, pipeline stages with candidate emails from templates, applicant counts', async () => {
  sql("DELETE FROM leads WHERE email = 'e2e+hire@example.com'");
  sql("DELETE FROM jobs WHERE slug = 'e2e-hydraulic-fitter'");
  await admin.post('admin/r/jobs/new', { _token: csrf, title: 'e2e Hydraulic Fitter', slug: 'e2e-hydraulic-fitter', department: 'Service', location: 'Pune', job_type: 'Full-time',
    experience: '2+ years with hydraulic systems', salary: '₹3.6–4.8 lakh / year', openings: '2', remote: 'field', closes_at: '', summary: 'Fit and test cylinders.', description: '<p>Hands-on role.</p>', status: 'published', sort_order: '0' });
  const job = sql("SELECT * FROM jobs WHERE slug = 'e2e-hydraulic-fitter'")[0];
  try {
    const c = new Client();
    const page = await c.get('careers/e2e-hydraulic-fitter');
    assert.match(page.text, /Salary<\/dt><dd[^>]*>₹3\.6–4\.8 lakh \/ year/);
    assert.match(page.text, /Field \/ travelling/);
    assert.match(page.text, /"totalJobOpenings":2/);
    assert.match(page.text, /data-share-net="linkedin"/, 'job can be shared');
    sql('DELETE FROM rate_limits');
    await sleep(3200);
    const fd = new FormData();
    for (const [k, v] of Object.entries({ form: 'job', _ft: formToken(page.text), job_id: String(job.id), fullName: 'E2E Candidate Rao', email: 'e2e+hire@example.com', phone: '+91 90000 55555', location: 'Pune', message: '', consent: '1', 'cf-turnstile-response': TS_TOKEN })) fd.append(k, v);
    fd.append('attachments[]', new Blob(['%PDF-1.4\n% cv\n'], { type: 'application/pdf' }), 'e2e-rao-cv.pdf');
    const r = await c.req('lead', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
    assert.equal(r.status, 200, await r.clone().text());
    const lead = sql("SELECT * FROM leads WHERE email = 'e2e+hire@example.com'")[0];

    const list = await admin.get('admin/r/jobs');
    assert.match(list.text, new RegExp(`careers/applications\\?job=${job.id}">1</a> <span class="adm-badge[^"]*">1 new`), 'applicant count with new badge');
    let show = await admin.get(`admin/leads/${lead.id}`);
    assert.match(show.text, /data-hiring-form/);
    assert.match(show.text, /data-template="Dear E2E,[^"]*invite you to an interview/);
    await admin.post(`admin/leads/${lead.id}/hiring`, { _token: csrf, stage: 'interview', email_candidate: '1', message: 'Dear E2E,\n\nPlease come for an interview on Monday 10:00. e2e-invite' });
    const after = sql('SELECT stage, status FROM leads WHERE id = ?', [lead.id])[0];
    assert.deepEqual([after.stage, after.status], ['interview', 'qualified']);
    assert.ok(sql("SELECT id FROM lead_notes WHERE lead_id = ? AND kind = 'email' AND body LIKE '%e2e-invite%'", [lead.id]).length, 'email kept in the timeline');
    if (DEBUG) assert.ok(findMail(/to: e2e\+hire@example\.com[\s\S]*e2e-invite/), 'candidate emailed');
    const apps = await admin.get(`admin/careers/applications?job=${job.id}&stage=interview`);
    assert.match(apps.text, /data-application>E2E Candidate Rao/);
    assert.match(apps.text, /data-hiring-pipeline[\s\S]*Interview<\/span><span[^>]*>1</);
    await admin.post(`admin/leads/${lead.id}/hiring`, { _token: csrf, stage: 'hired' });
    assert.equal(sql('SELECT status FROM leads WHERE id = ?', [lead.id])[0].status, 'won');
  } finally {
    sql("DELETE FROM leads WHERE email = 'e2e+hire@example.com'");
    sql("DELETE FROM jobs WHERE slug = 'e2e-hydraulic-fitter'");
  }
});

test('ui: admin command palette searches records by permission; phone contact bar and toast on the site', async () => {
  sql("DELETE FROM leads WHERE email = 'e2e+palette@example.com'");
  sql("INSERT INTO leads (type, status, name, company, email, product, message) VALUES ('quote', 'new', 'E2E Palette Person', 'E2E Palette Works', 'e2e+palette@example.com', 'Hydraulic Cylinders', 'x')");
  try {
    const dash = await admin.get('admin');
    assert.match(dash.text, /data-palette-open/);
    assert.match(dash.text, /<dialog class="adm-palette"/);
    const r = await admin.get('admin/palette.json?q=E2E+Palette', { Accept: 'application/json' });
    const j = JSON.parse(r.text);
    assert.ok(j.results.some((x) => x.group === 'Leads' && x.title === 'E2E Palette Person' && /\/admin\/leads\/\d+$/.test(x.url)));
    assert.equal((await new Client().get('admin/palette.json?q=E2E', { Accept: 'application/json' })).status, 401, 'signed-in staff only');
    const home = await new Client().get('');
    assert.match(home.text, /data-mobile-bar[\s\S]*Get a quote/);
    assert.match(home.text, /data-toast/);
  } finally {
    sql("DELETE FROM leads WHERE email = 'e2e+palette@example.com'");
  }
});

test('analytics: cookie-free page views and visitors, sources, devices, skips robots/team/Do-Not-Track, admin dashboard', async () => {
  sql("DELETE FROM page_hits WHERE path LIKE '/e2e%'");
  sql('DELETE FROM rate_limits');
  const hit = (c, p, ua, extra = {}, r = '') => c.post('hit', { p, r, w: /iPhone/.test(ua) ? '390' : '1440' }, { 'User-Agent': ua, ...extra });
  const phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
  const pc = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130';
  const a = new Client();
  assert.match((await a.get('')).text, /data-analytics="1"/, 'beacon on public pages');
  assert.doesNotMatch((await admin.get('')).text, /data-analytics="1"/, 'not for the team');
  assert.equal((await hit(a, '/e2e-page?utm_source=linkedin', phone)).status, 204);
  await hit(a, '/e2e-page', phone);
  await hit(new Client(), '/e2e-other', pc, {}, 'https://www.google.com/search?q=x');
  await hit(a, '/e2e-page', 'Googlebot/2.1 (+http://www.google.com/bot.html)');
  await hit(a, '/e2e-page', pc, { DNT: '1' });
  await hit(admin, '/e2e-page', pc);
  await hit(a, '/admin/leads', pc);
  const rows = sql("SELECT path, source, device FROM page_hits WHERE path LIKE '/e2e%' ORDER BY id");
  assert.deepEqual(rows.map((r) => [r.path, r.source, r.device]), [['/e2e-page', 'linkedin', 'mobile'], ['/e2e-page', null, 'mobile'], ['/e2e-other', 'Google', 'desktop']]);
  assert.equal(sql("SELECT COUNT(DISTINCT visitor) n FROM page_hits WHERE path = '/e2e-page'")[0].n, 1, 'same visitor counted once');
  const dash = await admin.get('admin/analytics?range=7');
  assert.equal(dash.status, 200);
  assert.match(dash.text, /data-top-page="\/e2e-page"/);
  assert.match(dash.text, /linkedin<\/span><strong>/);
  sql("DELETE FROM page_hits WHERE path LIKE '/e2e%'");
});

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
