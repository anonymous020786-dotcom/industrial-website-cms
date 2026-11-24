// Minimal HTTP client with a cookie jar, CSRF helpers and DB access for the end-to-end tests.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
export const BASE = (process.env.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
export const PHP = process.env.PHP_BIN || 'php';
export const TS_TOKEN = 'XXXX.DUMMY.TOKEN.XXXX'; // accepted by Cloudflare's test secret; ignored when Turnstile is off

export class Client {
  constructor() { this.jar = new Map(); }

  cookieHeader() { return [...this.jar].map(([k, v]) => `${k}=${v}`).join('; '); }

  store(res) {
    for (const c of res.headers.getSetCookie?.() ?? []) {
      const [pair] = c.split(';');
      const i = pair.indexOf('=');
      const k = pair.slice(0, i).trim(); const v = pair.slice(i + 1).trim();
      if (/expires=Thu, 01 Jan 1970/i.test(c) || v === '' || v === 'deleted') this.jar.delete(k); else this.jar.set(k, v);
    }
  }

  async req(path, opts = {}) {
    const headers = { 'User-Agent': 'Mozilla/5.0 (e2e tests)', ...(opts.headers || {}) };
    if (this.jar.size) headers.Cookie = this.cookieHeader();
    const res = await fetch(path.startsWith('http') ? path : BASE + '/' + path.replace(/^\//, ''), { redirect: 'manual', ...opts, headers });
    this.store(res);
    return res;
  }

  async get(path, headers = {}) {
    const res = await this.req(path, { headers });
    return { res, status: res.status, location: res.headers.get('location') || '', type: res.headers.get('content-type') || '', text: await res.text() };
  }

  /** POST url-encoded form data (arrays as name[]=…). */
  async post(path, fields, headers = {}) {
    const body = new URLSearchParams();
    for (const [k, v] of Object.entries(fields)) (Array.isArray(v) ? v : [v]).forEach((x) => body.append(k, String(x)));
    const res = await this.req(path, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...headers } });
    return { res, status: res.status, location: res.headers.get('location') || '', type: res.headers.get('content-type') || '', text: await res.text() };
  }

  async postJSON(path, data, csrf) {
    const res = await this.req(path, { method: 'POST', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf } });
    const text = await res.text();
    let json = null; try { json = JSON.parse(text); } catch { /* not JSON */ }
    return { status: res.status, json, text };
  }

  async postMultipart(path, form, csrf) {
    const res = await this.req(path, { method: 'POST', body: form, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } });
    const text = await res.text();
    let json = null; try { json = JSON.parse(text); } catch { /* not JSON */ }
    return { status: res.status, json, text };
  }

  /**
   * Complete "confirm it's you" with the emailed code (shown on screen in debug mode only).
   * Returns the response of the confirmation POST (a redirect, or the auto-resubmit page).
   */
  async stepUp(identity = process.env.ADMIN_LOGIN || 'admin@example.com') {
    // Test-only: age earlier codes so the per-user resend limits don't block back-to-back confirmations
    sql("UPDATE login_otps o JOIN users u ON u.id = o.user_id SET o.created_at = o.created_at - INTERVAL 20 MINUTE WHERE u.email = ? OR u.username = ?", [identity, identity]);
    let page = await this.get('admin/confirm');
    if (!/Debug mode — code/.test(page.text)) {
      await this.post('admin/confirm', { _token: token(page.text), method: 'send' });
      page = await this.get('admin/confirm');
    }
    const code = (page.text.match(/Debug mode — code: <code class="font-bold">(\d{6})<\/code>/) || [])[1];
    if (!code) throw new Error('No step-up code on the page — tests need debug mode');
    return this.post('admin/confirm', { _token: token(page.text), method: 'email', code });
  }

  /** Sign in with password (+ dummy Turnstile token). Returns the redirect target. */
  async login(login, password, extra = {}) {
    const page = await this.get('admin/login');
    const r = await this.post('admin/login', { _token: token(page.text), login, password, 'cf-turnstile-response': TS_TOKEN, ...extra });
    return r.location;
  }
}

export const token = (html) => (html.match(/name="_token" value="([^"]+)"/) || html.match(/name="csrf-token" content="([^"]+)"/) || [])[1] || '';
export const formToken = (html) => (html.match(/name="_ft" value="([^"]+)"/) || [])[1] || '';
export const flashText = (html) => [...html.matchAll(/adm-flash adm-flash-(\w+)"[^>]*>(?:<svg[\s\S]*?<\/svg>)?<span>([\s\S]*?)<\/span>/g)].map((m) => `${m[1]}: ${m[2].replace(/<[^>]+>/g, '')}`);

/** Run SQL / settings through the app (tests/lib/db.php). */
export function db(cmd) {
  const out = execFileSync(PHP, [join(ROOT, 'tests/lib/db.php'), JSON.stringify(cmd)], { encoding: 'utf8' });
  return JSON.parse(out || 'null');
}
export const sql = (q, params = []) => db({ sql: q, params });
export const setSetting = (k, v) => db({ setting: [k, v] });
export const getSetting = (k) => db({ get_setting: k });

export function php(args) {
  return execFileSync(PHP, args, { encoding: 'utf8', cwd: ROOT });
}

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
