// Maintenance page: live countdown to the expected return time, and an automatic reload as soon as
// the site answers normally again (checked once a minute with a lightweight HEAD request).
(() => {
  const body = document.body;
  if (!body || !body.hasAttribute('data-maintenance')) return;
  const until = Number(body.dataset.until || 0);
  const box = document.querySelector('[data-countdown]');
  const pad = (n) => String(n).padStart(2, '0');

  if (until && box) {
    box.hidden = false;
    const units = {};
    box.querySelectorAll('[data-unit]').forEach((el) => { units[el.dataset.unit] = el; });
    const days = box.querySelector('[data-days]');
    const tick = () => {
      let s = Math.max(0, Math.round((until - Date.now()) / 1000));
      const d = Math.floor(s / 86400); s -= d * 86400;
      const h = Math.floor(s / 3600); s -= h * 3600;
      const m = Math.floor(s / 60); s -= m * 60;
      if (days) days.hidden = d === 0;
      units.d && (units.d.textContent = pad(d));
      units.h && (units.h.textContent = pad(h));
      units.m && (units.m.textContent = pad(m));
      units.s && (units.s.textContent = pad(s));
      if (until - Date.now() <= 0) { clearInterval(timer); check(); }
    };
    const timer = setInterval(tick, 1000);
    tick();
  }

  // Team previews always get 200, so only real visitors poll
  if (body.dataset.preview === '1') return;
  async function check() {
    try {
      const res = await fetch(location.href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' });
      if (res.ok) location.reload();
    } catch { /* offline: try again later */ }
  }
  setInterval(check, 60000);
})();
