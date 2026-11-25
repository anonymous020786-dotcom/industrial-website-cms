/* Passkeys (WebAuthn) for the admin: sign-in, password-manager autofill, registration; QR codes for authenticator apps. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const base = document.body.dataset.base || '';
  const u = (p) => `${base}/${p.replace(/^\//, '')}`;
  const csrf = () => $('meta[name="csrf-token"]')?.content || $('input[name="_token"]')?.value || '';
  const supported = !!(window.PublicKeyCredential && navigator.credentials);

  const b64urlToBuf = (s) => {
    const b64 = s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4);
    return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0)).buffer;
  };
  const bufToB64url = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

  async function post(path, data) {
    const res = await fetch(u(path), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify(data || {}),
      credentials: 'same-origin',
    });
    let json = null;
    try { json = await res.json(); } catch { /* HTML error page */ }
    // Sensitive action: confirm identity first, then come back here
    if (res.status === 428 && json?.stepup) { window.location.href = json.stepup; throw Object.assign(new Error(''), { name: 'AbortError' }); }
    if (!res.ok || !json || json.error) throw new Error(json?.error || (res.status === 419 ? 'Your session expired. Refresh the page and try again.' : 'Request failed. Please try again.'));
    return json;
  }

  const friendly = (err) => {
    if (err?.name === 'NotAllowedError') return 'The passkey request was cancelled or timed out.';
    if (err?.name === 'InvalidStateError') return 'This device already has a passkey for your account.';
    if (err?.name === 'SecurityError') return 'Passkeys need a secure connection (https) on the site’s real domain.';
    if (err?.name === 'AbortError') return '';
    return err?.message || 'Something went wrong.';
  };

  const credToJSON = (c) => {
    const r = c.response;
    const out = { id: c.id, rawId: bufToB64url(c.rawId), type: c.type, response: { clientDataJSON: bufToB64url(r.clientDataJSON) } };
    if (r.attestationObject) {
      out.response.attestationObject = bufToB64url(r.attestationObject);
      out.response.transports = typeof r.getTransports === 'function' ? r.getTransports() : [];
    }
    if (r.authenticatorData) {
      out.response.authenticatorData = bufToB64url(r.authenticatorData);
      out.response.signature = bufToB64url(r.signature);
      out.response.userHandle = r.userHandle ? bufToB64url(r.userHandle) : '';
    }
    return out;
  };

  /* ---------------- Sign in ---------------- */
  const loginBtn = $('[data-passkey-login]');
  const loginMsg = $('[data-passkey-error]');
  let pending = null; // AbortController of a running (autofill) request

  async function signIn(conditional) {
    if (pending) pending.abort();
    const ctrl = new AbortController();
    pending = ctrl;
    const opts = await post('admin/login/passkey/options');
    opts.challenge = b64urlToBuf(opts.challenge);
    const cred = await navigator.credentials.get({ publicKey: opts, signal: ctrl.signal, ...(conditional ? { mediation: 'conditional' } : {}) });
    if (!cred) return;
    if (loginBtn) { loginBtn.disabled = true; loginBtn.textContent = 'Signing in…'; }
    const res = await post('admin/login/passkey', { credential: credToJSON(cred), remember: !!$('[data-remember]')?.checked });
    window.location.href = res.redirect || u('admin');
  }

  if (loginBtn) {
    if (!supported) {
      loginBtn.closest('[data-passkey-box]')?.setAttribute('hidden', '');
    } else {
      loginBtn.addEventListener('click', async () => {
        if (loginMsg) loginMsg.textContent = '';
        try { await signIn(false); } catch (e) { const m = friendly(e); if (m && loginMsg) loginMsg.textContent = m; }
      });
      // Offer saved passkeys in the username field's autofill menu (Chrome, Safari, Edge)
      PublicKeyCredential.isConditionalMediationAvailable?.().then((ok) => {
        if (ok) signIn(true).catch((e) => { const m = friendly(e); if (m && e.name !== 'NotAllowedError' && loginMsg) loginMsg.textContent = m; });
      });
    }
  }

  /* ---------------- Register (My profile) ---------------- */
  const addForm = $('[data-passkey-add]');
  if (addForm) {
    const msg = $('[data-passkey-add-error]', addForm);
    if (!supported) {
      addForm.innerHTML = '<p class="text-sm text-charcoal/70">This browser does not support passkeys. Use a current version of Chrome, Edge, Safari or Firefox.</p>';
    } else {
      addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('button[type="submit"]', addForm);
        msg.textContent = '';
        btn.disabled = true;
        try {
          const opts = await post('admin/passkeys/options', { password: addForm.password.value });
          opts.challenge = b64urlToBuf(opts.challenge);
          opts.user.id = b64urlToBuf(opts.user.id);
          opts.excludeCredentials = (opts.excludeCredentials || []).map((c) => ({ ...c, id: b64urlToBuf(c.id) }));
          const cred = await navigator.credentials.create({ publicKey: opts });
          await post('admin/passkeys/register', { credential: credToJSON(cred), name: addForm.pk_name.value });
          window.location.hash = 'passkeys';
          window.location.reload();
        } catch (err) {
          msg.textContent = friendly(err) || 'Cancelled.';
          btn.disabled = false;
        }
      });
    }
  }

  /* ---------------- Confirm a sensitive action with a passkey ---------------- */
  const stepBtn = $('[data-stepup-passkey]');
  if (stepBtn) {
    const msg = $('[data-stepup-passkey-error]');
    if (!supported) {
      stepBtn.closest('[data-stepup-passkey-box]')?.setAttribute('hidden', '');
    } else {
      stepBtn.addEventListener('click', async () => {
        msg.textContent = '';
        stepBtn.disabled = true;
        try {
          const opts = await post('admin/confirm/passkey/options');
          opts.challenge = b64urlToBuf(opts.challenge);
          opts.allowCredentials = (opts.allowCredentials || []).map((c) => ({ ...c, id: b64urlToBuf(c.id) }));
          const cred = await navigator.credentials.get({ publicKey: opts });
          const res = await post('admin/confirm/passkey', { credential: credToJSON(cred) });
          window.location.href = res.redirect;
        } catch (err) {
          msg.textContent = friendly(err) || 'Cancelled.';
          stepBtn.disabled = false;
        }
      });
    }
  }

  /* ---------------- Resume a confirmed action (re-submits the original form) ---------------- */
  const resume = $('form[data-autosubmit]');
  if (resume) setTimeout(() => (resume.requestSubmit ? resume.requestSubmit() : resume.submit()), 400);

  /* ---------------- QR code for authenticator apps ---------------- */
  if (typeof window.qrcode === 'function') {
    document.querySelectorAll('[data-qr]').forEach((qrBox) => {
      const qr = window.qrcode(0, 'M');
      qr.addData(qrBox.dataset.qr);
      qr.make();
      qrBox.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
      const svg = $('svg', qrBox);
      const size = qrBox.dataset.qrSize || '200';
      if (svg) { svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', qrBox.getAttribute('aria-label') || 'QR code for your authenticator app'); svg.style.width = size + 'px'; svg.style.height = size + 'px'; }
    });
  }
})();
