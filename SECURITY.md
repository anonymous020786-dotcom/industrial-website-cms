# Security

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Use GitHub's
**Security → Report a vulnerability** (private advisory) on this repository instead.
Include steps to reproduce and the affected version. You will get a reply within a few days.

## Built-in protections

- CSRF tokens on every admin form; signed time tokens, honeypot and rate limits on public forms
- Optional Cloudflare Turnstile on login, password reset, contact and quote forms
- Passwords hashed with `password_hash()`; login rate limiting and an audit log
- Optional email one-time-code (OTP) sign-in with trusted devices; password reset and email changes
  verified by single-use codes; security notices emailed on sensitive changes
- Strict Content-Security-Policy on the admin; frame, base-uri, object and form-action limits on the site
- HTML from the editor is sanitised; uploads are type-checked and PHP execution is blocked in `uploads/`
- `app/`, `database/` and dotfiles are not web-accessible

## Before going live

- Set `debug` to `false` in `app/config.php` (debug shows one-time codes on screen)
- Replace the Cloudflare Turnstile **test** keys with real keys
- Configure SMTP, send a test email, then enable email two-step sign-in
- Serve the site over HTTPS only
