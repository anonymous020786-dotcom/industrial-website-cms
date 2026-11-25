// Telegram helpers (admin, no inline scripts under the CSP):
//  - profile: after "Connect Telegram", wait for the user to press Start in the bot, then refresh
//  - sign-in code page: wait for the owner to tap "Approve" in Telegram, then continue automatically
(() => {
  const base = document.body.dataset.base || '';
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';
  const post = (path, body = {}) => fetch(base + path, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
    body: JSON.stringify(body),
  }).then((r) => r.json());

  const every = (ms, fn, maxMinutes = 11) => {
    const stopAt = Date.now() + maxMinutes * 60000;
    const tick = async () => {
      if (document.hidden) return setTimeout(tick, ms); // pause while the tab is in the background
      let done = false;
      try { done = await fn(); } catch { /* network blip: keep trying */ }
      if (!done && Date.now() < stopAt) setTimeout(tick, ms);
    };
    setTimeout(tick, ms);
  };

  if (document.querySelector('[data-tg-wait]')) {
    every(3000, async () => {
      const r = await post('/admin/telegram/status');
      if (typeof r.status === 'number') return true;
      if (r.linked) { location.hash = 'telegram'; location.reload(); return true; }
      return false;
    });
  }

  const approval = document.querySelector('[data-tg-approval]');
  if (approval) {
    const note = approval.querySelector('[data-tg-note]');
    let submitted = false;
    document.addEventListener('submit', () => { submitted = true; }); // typing the code wins
    every(2500, async () => {
      if (submitted) return true;
      const trust = document.querySelector('input[name="trust"]')?.checked ? 1 : 0;
      const r = await post('/admin/login/verify/status', { trust });
      if (typeof r.status === 'number') return true; // session expired etc.: stop quietly
      if (r.status === 'approved' && note) note.textContent = 'Approved in Telegram — signing you in…';
      if (r.redirect) { location.href = r.redirect; return true; }
      return r.status === 'expired';
    });
  }
})();
