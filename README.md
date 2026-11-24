# Industrial Website + CMS

Corporate website for a crane-components and hydraulics manufacturer, with a built-in
PHP/MySQL content management system, a technical blog, lead (CRM-lite) management and
marketing integrations. It runs on any standard PHP host with no framework and no build step on the server.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL / MariaDB](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-06B6D4?logo=tailwindcss&logoColor=white)
![Vanilla JS](https://img.shields.io/badge/JavaScript-vanilla-F7DF1E?logo=javascript&logoColor=black)
![Lighthouse](https://img.shields.io/badge/Lighthouse-100%20a11y%20%C2%B7%20SEO%20%C2%B7%20best%20practices-0CCE6B?logo=lighthouse&logoColor=white)

> **Demo content.** The company name (Ironvale Engineering), contact details, statistics and
> specifications are placeholders. Stock photos are from [Unsplash](https://unsplash.com/license).
> Replace them all before using the site for a real business.

| Website (light) | Website (dark theme) |
|---|---|
| ![Home page](docs/screenshots/home-light.jpg) | ![Products in dark theme](docs/screenshots/products-dark.jpg) |
| **Product page** | **Admin — pages** |
| ![Product page](docs/screenshots/product-page.jpg) | ![Admin pages list](docs/screenshots/admin-pages.jpg) |

## Highlights

- **Public site.** Pages:
  - Home, About, Products (with detail pages), Industries, Manufacturing, Quality
  - Blog with categories, search, pagination and RSS
  - Contact, a multi-step Quote form with file upload, legal pages, and HTML and XML sitemaps
- **Blog.** 72 long-form technical articles ship as drafts, with a drip-publishing scheduler.
- **Admin panel (`/admin`).**
  - Content: dashboard, leads pipeline, pages, posts, categories, products, industries, media library,
    menus and settings.
  - Users and roles; trash and restore, revision history, duplication and bulk actions.
- **Integrations.** GA4, Google Tag Manager, Meta Pixel, Microsoft Clarity, Tawk.to chat, WhatsApp button,
  Cloudflare Turnstile and Tunnel, SMTP, Search Console and Bing verification. Tracking loads only after
  cookie consent.
- **Security.** Email or username sign-in, optional email one-time codes (OTP), password reset, backup
  email, rate limiting, an audit log and CSP. See [SECURITY.md](SECURITY.md).
- **Quality.**
  - Light and dark themes; WCAG 2.2 AA contrast; schema.org JSON-LD.
  - Self-hosted fonts, icons and WebP photos; minified HTML, CSS and JS.
  - Lighthouse 100 for accessibility, best practices and SEO.
- **No frameworks.** Plain PHP 8.1+, MySQL/MariaDB, pre-compiled Tailwind and vanilla JS. Node.js is
  needed only in development, to rebuild assets.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `dom`, `fileinfo`, `openssl`
- MySQL 5.7+ / MariaDB 10.4+
- Apache with `mod_rewrite` (or Nginx / PHP built-in server)

## Run locally

```bash
npm install                 # first time only (dev tools)
npm run build               # import blog drafts + build CSS, icons, fonts, editor
npm run dev                 # PHP server on http://localhost:8000 + Tailwind watcher
```

Open `http://localhost:8000/install` the first time, or install from the command line:

```bash
php app/cli.php install --db-name=industrial_cms --db-user=root --db-pass= \
  --admin-email=you@company.com --admin-pass="StrongPass123" --site-url=http://localhost:8000
```

## Deploy (Hostinger, cPanel or any PHP host)

1. Run `npm run build` locally (the compiled CSS/JS are committed in `public/assets`).
2. Create a MySQL database + user in your hosting panel.
3. Upload the project. **Best:** point the domain's document root at `/public`.
   If you cannot change the document root, upload everything into `public_html` — the root
   `.htaccess` routes requests into `/public` and blocks `app/`, `database/`, etc.
4. Visit `https://your-domain/install`, enter the database details and create the admin account.
   The installer locks itself afterwards (`app/storage/installed.lock`).
5. Make `public/uploads` and `app/storage` writable. Enable SSL, then uncomment the HTTPS
   redirect in `public/.htaccess`.
6. Admin → Settings: company details, **SEO → site URL**, **Email** (SMTP + lead alerts),
   **Integrations**, and turn off the legal template notice once policies are reviewed.

`app/config.php` (created by the installer) holds the DB credentials and app key — never commit it.
Set `'debug' => false` in production.

## Content management (pages, posts, products)

- **Create, edit, publish, draft and schedule.** Posts also support drip-publishing (Bulk → Schedule).
- **Trash, not instant delete.** Deleting a post or page moves it to the **Trash** tab and unpublishes it
  at once. You can restore it (as a draft) for 30 days, or delete it permanently or empty the Trash.
  System pages (Home, About…) can only be set to Draft, never trashed.
- **Revision history.** Every save keeps the previous version (the last 30 per item) in the editor sidebar,
  which shows which fields differ. **Restore** brings back the content and keeps the current publish
  status. The version you replace is kept too.
- **Duplicate.** Posts, custom/legal pages, products, industries and categories can be copied. A copy
  always starts as a draft with a `-copy` URL.
- **Bulk actions.**
  - Posts: publish, draft, schedule, duplicate, set category, trash, restore, delete permanently.
  - Pages: publish, draft, show or hide from search engines, trash, restore, delete permanently.
  - Pages also have search and filters (Published / Drafts / System / Custom / Trash).

## Website experience

- **Light / dark theme.** A toggle sits in the header, and each visitor's choice is remembered. In
  Settings → Company & Branding you can set the default (Light, Dark or Follow device) or hide the toggle.
- **Scroll controls.** Floating up/down buttons with a reading-progress ring can be switched off in the
  same place.
- **Accessibility.** Every text colour meets WCAG 2.2 AA (4.5:1) in both themes, so the primary button
  uses navy text on the brand orange.
- **Integration status.** Settings → Integrations opens with a status panel for Analytics, GTM, Pixel,
  Clarity, Tawk.to, WhatsApp, Turnstile (it warns about test keys), Cloudflare proxy, Search Console,
  Bing, SMTP and cookie consent.

## Stock photos

Demo photos are Unsplash images. `npm run images` downloads every photo the site references
(code, seed data and database) into `public/assets/stock/` as WebP (480–2400 px), and
`img_tag()` serves those local copies automatically. Pages then never wait on images.unsplash.com.
Re-run it after pasting a new Unsplash URL in the admin; until then that photo loads from Unsplash.
Replace the stock photos with your own photography before launch.

## Admin roles

| Role | Access |
|---|---|
| Administrator | Everything, incl. settings, integrations and users |
| Editor | Pages, blog, categories, products, industries, media |
| Sales | Dashboard and leads |

## Admin sign-in & account security

- **Sign in with email or username.** Wrong passwords are rate limited (5 per account+IP and
  20 per IP per 15 minutes), and every sign-in event is written to an audit log
  (Admin → Settings → Security, and *My profile → Recent security activity*).
- **Two-step email codes (OTP).** Turn on in Settings → Security once SMTP has passed a test
  email. The 6-digit codes expire after 10 minutes, allow 5 attempts, can be resent after 60 s, and are
  limited to 5 per 15 minutes. Codes are stored hashed. Users can trust a browser for N days, and can
  have the code sent to their verified backup email instead.
- **Forgot / reset password** (`/admin/forgot`). A reset code is emailed to the primary address and the verified backup
  email. The response is identical whether or not the account exists. Codes are single-use, and a
  reset signs out every other session and trusted browser.
- **My profile** (`/admin/profile`):
  - name and username
  - change password (needs the current password)
  - change sign-in email: a code goes to the *new* address and a notice to the old one
  - add, change or remove a backup email: verified by a code, and removal needs the current password
- Every sensitive change emails a security notice. Re-entering the current password is rate limited.
- **Cloudflare Turnstile** protects the login, forgot-password, contact and quote forms when keys are
  set (Settings → Integrations). For local testing, use Cloudflare’s test keys: site key
  `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA`. **Replace them with real
  keys in production.**
- **Locked out?** `php app/cli.php reset-password --email=… --pass=…` or `php app/cli.php otp --off`.
- **Debug mode** (`'debug' => true` in `app/config.php`):
  - every email is saved to `app/storage/mail` (Settings → System → Dev mailbox)
  - codes are shown on screen
  - Never enable debug mode on a live site.

## Leads (CRM-lite)

Contact and quote forms save to the database (with attachments stored privately in
`app/storage/leads`). Each lead has status pipeline (New → Contacted → Qualified → Quoted →
Negotiation → Won/Lost/Spam), priority, owner, follow-up date, estimated value, tags and an
activity timeline (notes, calls, emails, automatic status history). Filters, search, bulk
actions and CSV export are included. UTM source/medium/campaign and landing page are captured.
Spam protection: signed time token, honeypot, rate limiting and optional Cloudflare Turnstile.
Email alerts and optional customer auto-reply use the SMTP settings.

## Blog workflow

- 72 technical articles (8 categories, ~59,000 words, each with an FAQ section and FAQPage
  schema) ship as **drafts** in `content/posts/*.md`.
- Have an engineer review each article, then in Admin → Blog Posts select drafts and use
  **Schedule (drip-publish)** — e.g. one every 3 days. Steady publishing is better for SEO
  than releasing everything at once.
- Editing source files: change `content/posts/*.md` (+ FAQs in `_faq-*.md`), then
  `npm run posts && php app/cli.php import-posts --refresh` (only updates posts still in draft).

## Integrations (Admin → Settings → Integrations)

Google Analytics 4, Google Tag Manager, Meta Pixel, Microsoft Clarity, Tawk.to live chat,
WhatsApp floating button + per-page WhatsApp links, Cloudflare Turnstile, Cloudflare proxy /
Tunnel real-IP support, Google/Bing verification and custom head/body code.
Tracking scripts only load after the visitor accepts cookies, and the Cookie Policy table
updates automatically from the enabled integrations.

**Cloudflare Tunnel (expose local/private server):**
`cloudflared tunnel --url http://localhost:8000` — then enable “behind Cloudflare proxy or
Tunnel” in Integrations and set the public URL in Settings → SEO.

## Project structure

```
app/            PHP application (not web-accessible)
  handlers/     request handlers (site, admin, leads, content, settings, installer)
  lib/          helpers, db, auth, fields (form builder), templates, mailer, integrations
  views/        site/ and admin/ templates
  storage/      logs, lead attachments, install lock (keep private)
  config.php    generated by installer
content/posts/  blog drafts (Markdown) + FAQ add-ons
database/       schema.sql, seed/site.json, seed/posts.json
public/         web root: index.php, assets/, uploads/
resources/      Tailwind entry file
scripts/        dev tooling (asset build, post import, dev server)
```

## CLI

```bash
php app/cli.php migrate                 # apply schema upgrades (safe to re-run)
php app/cli.php otp --on|--off          # toggle email two-step sign-in
php app/cli.php import-posts [--refresh]
php app/cli.php create-admin --email=… --pass=… [--name=…]
php app/cli.php reset-password --email=… --pass=…
```

## Before launch

- Replace demo company details, statistics, specifications, equipment list and images.
- Have the legal pages reviewed by a lawyer (they are templates), then turn off the notice.
- Configure SMTP and test it, set lead notification recipients, add real Turnstile keys.
- Set `debug` to false, enable email two-step sign-in, and add a backup email to each admin.
- Review and schedule blog drafts; verify every technical claim with your engineers.
