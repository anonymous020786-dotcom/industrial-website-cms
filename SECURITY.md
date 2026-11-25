# Security

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Use GitHub's
**Security → Report a vulnerability** (private advisory) on this repository instead.
Include steps to reproduce and the affected version. You will get a reply within a few days.

## Built-in protections

- CSRF tokens on every admin form; signed time tokens, honeypot and rate limits on public forms
- Optional Cloudflare Turnstile on login, password reset, contact and quote forms
- Passkeys (WebAuthn): user verification required, origin and RP-ID checked, single-use challenges,
  signature-counter clone detection; public keys only are stored
- Authenticator-app codes (TOTP, RFC 6238): secrets encrypted with AES-256-GCM, replay protection,
  rate limiting, ten hashed single-use recovery codes
- Passwords hashed with `password_hash()`; login rate limiting and an audit log
- Optional email one-time-code (OTP) sign-in. Password reset and email changes are verified by single-use
  codes.
- Trusted devices are recorded per browser (a random device cookie, stored as an HMAC) and can be
  revoked from My profile.
- "Keep me signed in" tokens are random, stored as SHA-256, and rotated on every use. They are bound to
  the device cookie. Replaying an old token revokes them, and signing out, changing the password or
  "sign out all other devices" ends them (a per-user session salt is rotated).
- Step-up confirmation ("confirm it's you") is needed for sensitive actions: a passkey, TOTP or emailed
  code, valid 10 minutes. Interrupted forms are replayed only when they hold no password or secret
  fields.
- Security alerts are shown in the admin and emailed; account-security alerts can't be turned off.
- Profile pictures are re-encoded to WebP, which removes metadata.
- Telegram: the bot token is a write-only secret; the webhook is authenticated with a random secret
  token; connect links are single-use, stored hashed and expire after 10 minutes. Approve / Deny
  buttons carry a per-code random nonce and are accepted only from the account owner's chat, once,
  before the code expires. Connecting or disconnecting Telegram, or weakening its sign-in options,
  needs a one-time code and sends a security alert. Codes fall back to email if Telegram is down.
- Error pages never show internal details outside debug mode. Unexpected errors show only a short
  reference. The stored stack trace has no argument values, and the admin Error log needs the
  Settings permission.
- Maintenance mode: switching it needs a one-time code and alerts every administrator. The preview
  link is a random token, and the cookie it sets is an HMAC that stops working when the link is
  replaced.
- Strict Content-Security-Policy on the admin; frame, base-uri, object and form-action limits on the site
- HTML from the editor is sanitised; uploads are type-checked and PHP execution is blocked in `uploads/`
- Uploaded images are re-encoded (metadata/GPS removed); PDFs are verified by file signature
- Backups are stored outside the web root and downloads require re-entering the password
- `app/`, `database/` and dotfiles are not web-accessible

## Before going live

- Set `debug` to `false` in `app/config.php` (debug shows one-time codes on screen)
- Replace the Cloudflare Turnstile **test** keys with real keys
- Configure SMTP, send a test email, then enable email two-step sign-in
- Serve the site over HTTPS only
