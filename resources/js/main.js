/* ==========================================================================
   Public website interactions (vanilla JS, no dependencies)
   ========================================================================== */
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const base = document.body.dataset.base || '';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const scrollBehavior = reduceMotion ? 'auto' : 'smooth';
  const store = {
    get(k, s = localStorage) { try { return JSON.parse(s.getItem(k)); } catch { return null; } },
    set(k, v, s = localStorage) { try { s.setItem(k, JSON.stringify(v)); } catch { /* storage blocked */ } },
  };

  /* ---------------- Header: shadow, back-to-top, reading progress ---------------- */
  function initScrollUI() {
    const header = $('#site-header');
    const toTop = $('#back-to-top');
    const down = $('#scroll-down');
    const ring = $('.scroll-ring .ring-fg');
    const progress = $('.reading-progress span');
    const RING = 2 * Math.PI * 22; // circumference of r=22
    let ticking = false;
    const update = () => {
      const y = window.scrollY;
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const pct = max > 0 ? Math.min(1, Math.max(0, y / max)) : 0;
      header?.classList.toggle('is-scrolled', y > 10);
      toTop?.classList.toggle('is-visible', y > 400);
      // "Down" is offered once the visitor has started scrolling and hidden near the end
      down?.classList.toggle('is-visible', y > 200 && max - y > 600);
      if (ring) ring.style.strokeDashoffset = String(RING * (1 - pct));
      if (toTop) toTop.setAttribute('aria-label', `Back to top (${Math.round(pct * 100)}% read)`);
      if (progress) progress.style.width = `${pct * 100}%`;
      ticking = false;
    };
    window.addEventListener('scroll', () => { if (!ticking) { ticking = true; setTimeout(update, 16); } }, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
    toTop?.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: scrollBehavior }); $('.logo')?.focus({ preventScroll: true }); });
    // Jump to the next section below the sticky header (or one screen if sections are tall)
    down?.addEventListener('click', () => {
      const offset = (header?.offsetHeight || 72) + 8;
      const next = $$('main section, main > div > section, footer').map((s) => s.getBoundingClientRect().top - offset).find((t) => t > 24);
      const by = next === undefined ? window.innerHeight * 0.85 : Math.min(next, window.innerHeight * 1.5);
      window.scrollBy({ top: by, behavior: scrollBehavior });
    });
  }

  /* ---------------- Engineering calculators: live results from the server (one set of formulas) ---------------- */
  $$('form[data-calc]').forEach((form) => {
    const out = $('[data-out]', form);
    const quote = $('[data-quote-link]', form);
    let timer;
    const run = async () => {
      const url = form.action.split('#')[0] + '?' + new URLSearchParams(new FormData(form)).toString();
      try {
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        const j = await r.json();
        out.innerHTML = '';
        if (!j.results.length) { const p = document.createElement('p'); p.className = 'py-3 text-sm text-red-700'; p.textContent = 'Check the values (the rod must be smaller than the bore).'; out.append(p); }
        j.results.forEach(([label, value]) => {
          const row = document.createElement('div'); row.className = 'flex items-baseline justify-between gap-4 py-2.5';
          const dt = document.createElement('dt'); dt.className = 'text-sm text-charcoal/75'; dt.textContent = label;
          const dd = document.createElement('dd'); dd.className = 'text-right font-display text-xl font-bold text-navy'; dd.textContent = value;
          row.append(dt, dd); out.append(row);
        });
        if (quote && j.quote) quote.href = j.quote;
      } catch { /* keep the last result */ }
    };
    form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 250); });
    form.addEventListener('submit', (e) => { e.preventDefault(); run(); });
  });

  /* ---------------- Light / dark theme ---------------- */
  function initTheme() {
    const btn = $('#theme-toggle');
    const root = document.documentElement;
    const meta = $('meta[name="theme-color"]');
    const apply = (dark, animate) => {
      if (animate) { root.classList.add('theme-anim'); setTimeout(() => root.classList.remove('theme-anim'), 400); }
      root.classList.toggle('dark', dark);
      meta?.setAttribute('content', dark ? '#0B1322' : '#101D32');
      if (btn) {
        btn.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
        btn.setAttribute('aria-pressed', String(dark));
      }
    };
    apply(root.classList.contains('dark'), false);
    btn?.addEventListener('click', () => {
      const dark = !root.classList.contains('dark');
      apply(dark, true);
      try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch { /* private mode */ }
    });
    // Follow OS changes while the visitor has not chosen explicitly and the site default is "system"
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    mq.addEventListener?.('change', (e) => {
      let chosen = null;
      try { chosen = localStorage.getItem('theme'); } catch { /* ignore */ }
      if (!chosen && document.body.dataset.themeDefault === 'system') apply(e.matches, true);
    });
  }

  /* ---------------- Multi-item quote list (RFQ basket) ---------------- */
  // Stored in localStorage on the visitor's device only; sent as items[] with the quote form.
  function initQuoteList() {
    const KEY = 'rfq-v1';
    const MAX = 30;
    const read = () => { try { const v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.slice(0, MAX) : []; } catch { return []; } };
    const write = (items) => { try { localStorage.setItem(KEY, JSON.stringify(items.slice(0, MAX))); } catch { /* private mode */ } refreshBadge(); };
    const count = () => read().length;

    function refreshBadge() {
      const n = count();
      document.querySelectorAll('[data-rfq-link]').forEach((a) => {
        a.hidden = n === 0;
        a.setAttribute('aria-label', `Quote list: ${n} item${n === 1 ? '' : 's'}`);
        const c = $('[data-rfq-count]', a); if (c) c.textContent = String(n);
      });
    }

    function toast(msg, href) {
      let t = $('#rfq-toast');
      if (!t) {
        t = document.createElement('div');
        t.id = 'rfq-toast'; t.className = 'rfq-toast'; t.setAttribute('role', 'status'); t.setAttribute('aria-live', 'polite');
        document.body.append(t);
      }
      t.replaceChildren(document.createTextNode(msg + ' '));
      if (href) { const a = document.createElement('a'); a.href = href; a.textContent = 'View quote list →'; t.append(a); }
      t.classList.add('is-visible');
      clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('is-visible'), 4500);
    }

    // "Add to quote list" buttons (product pages)
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-rfq-add]');
      if (!btn) return;
      const items = read();
      const slug = btn.dataset.slug || '';
      const existing = items.find((i) => slug && i.slug === slug);
      if (existing) existing.qty = Math.min(1000000, (Number(existing.qty) || 1) + 1);
      else if (items.length >= MAX) { toast(`Your quote list is full (${MAX} items).`); return; }
      else items.push({ slug, name: btn.dataset.name || 'Item', part: '', qty: 1, notes: '' });
      write(items);
      btn.querySelector('[data-rfq-label]')?.replaceChildren(document.createTextNode('Added to quote list'));
      toast(existing ? `${btn.dataset.name}: quantity increased.` : `${btn.dataset.name} added to your quote list.`, btn.dataset.listUrl);
    });

    // Quote page editor
    const box = $('[data-rfq]');
    if (box) {
      const form = box.closest('form');
      const productField = form?.elements.product?.closest('.field');
      const products = JSON.parse(box.dataset.products || '[]');
      const list = $('[data-rfq-rows]', box);
      const empty = $('[data-rfq-empty]', box);
      const picker = $('[data-rfq-picker]', box);

      const render = () => {
        const items = read();
        list.replaceChildren();
        items.forEach((it, i) => {
          const row = document.createElement('div');
          row.className = 'rfq-row';
          const mk = (tag, attrs) => { const el = document.createElement(tag); Object.entries(attrs).forEach(([k, v]) => { if (v !== undefined) el.setAttribute(k, v); }); return el; };
          const name = it.slug
            ? Object.assign(mk('input', { type: 'hidden', name: `items[${i}][name]` }), { value: it.name })
            : Object.assign(mk('input', { name: `items[${i}][name]`, class: 'rfq-input', placeholder: 'Item description', 'aria-label': 'Item description', maxlength: '190', required: '' }), { value: it.name || '' });
          const label = mk('div', { class: 'rfq-name' });
          if (it.slug) { label.textContent = it.name; label.append(Object.assign(mk('input', { type: 'hidden', name: `items[${i}][slug]` }), { value: it.slug })); }
          label.append(name);
          const part = Object.assign(mk('input', { name: `items[${i}][part]`, class: 'rfq-input', placeholder: 'Part / drawing no.', 'aria-label': `Part number for ${it.name || 'item'}`, maxlength: '120' }), { value: it.part || '' });
          const qty = Object.assign(mk('input', { name: `items[${i}][qty]`, type: 'number', min: '1', max: '1000000', step: '1', class: 'rfq-input rfq-qty', 'aria-label': `Quantity for ${it.name || 'item'}` }), { value: it.qty || 1 });
          const notes = Object.assign(mk('input', { name: `items[${i}][notes]`, class: 'rfq-input', placeholder: 'Size, material, notes (optional)', 'aria-label': `Notes for ${it.name || 'item'}`, maxlength: '300' }), { value: it.notes || '' });
          const rm = mk('button', { type: 'button', class: 'rfq-remove', 'aria-label': `Remove ${it.name || 'item'}` });
          rm.textContent = '×';
          const sync = () => { const all = read(); if (!all[i]) return; Object.assign(all[i], { part: part.value, qty: Math.max(1, Math.min(1000000, parseInt(qty.value, 10) || 1)), notes: notes.value }); if (!it.slug) all[i].name = name.value; write(all); };
          [part, qty, notes, name].forEach((el) => el.addEventListener('change', sync));
          rm.addEventListener('click', () => { const all = read(); all.splice(i, 1); write(all); render(); });
          row.append(label, part, qty, notes, rm);
          list.append(row);
        });
        const has = items.length > 0;
        empty.hidden = has;
        list.hidden = !has;
        if (productField) {
          // With a list, the single product / quantity / part-number fields are replaced by the rows
          ['product', 'quantity', 'partNumber'].forEach((n) => { const f = form.elements[n]?.closest('.field'); if (f) f.hidden = has; });
          const sel = form.elements.product;
          sel.required = !has;
          if (has) { sel.value = ''; if (form.elements.quantity) form.elements.quantity.value = ''; }
        }
      };

      picker?.addEventListener('change', () => {
        const p = products.find((x) => x.slug === picker.value);
        picker.value = '';
        if (!p) return;
        const items = read();
        if (items.some((i) => i.slug === p.slug)) { toast(`${p.name} is already in the list.`); return; }
        if (items.length >= MAX) return;
        items.push({ slug: p.slug, name: p.name, part: '', qty: 1, notes: '' });
        write(items); render();
      });
      $('[data-rfq-custom]', box)?.addEventListener('click', () => {
        const items = read();
        if (items.length >= MAX) return;
        items.push({ slug: '', name: '', part: '', qty: 1, notes: '' });
        write(items); render();
        list.querySelector('.rfq-row:last-child input[name$="[name]"]')?.focus();
      });
      window.addEventListener('rfq:sent', () => { write([]); render(); });
      // Single product from ?product= also lands in the list if the visitor already has one
      render();
    }
    refreshBadge();
    window.addEventListener('storage', (e) => { if (e.key === KEY) refreshBadge(); });
  }

  /* ---------------- Mobile menu ---------------- */
  function initMobileMenu() {
    const toggle = $('#menu-toggle');
    const menu = $('#mobile-menu');
    if (!toggle || !menu) return;
    const setOpen = (open) => {
      menu.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      $('.icon-open', toggle).classList.toggle('hidden', open);
      $('.icon-close', toggle).classList.toggle('hidden', !open);
      document.body.style.overflow = open ? 'hidden' : '';
    };
    toggle.addEventListener('click', () => setOpen(menu.hidden));
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { setOpen(false); toggle.focus(); } });
    window.matchMedia('(min-width: 1280px)').addEventListener('change', (e) => { if (e.matches) setOpen(false); });
    $$('.mobile-sub-toggle', menu).forEach((btn) => btn.addEventListener('click', () => {
      const sub = document.getElementById(btn.getAttribute('aria-controls'));
      const open = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(open));
      sub.hidden = !open;
    }));
  }

  /* ---------------- Mega menu ---------------- */
  function initMegaMenu() {
    $$('.has-mega').forEach((item) => {
      const btn = $('.mega-toggle', item);
      const close = () => { item.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); };
      btn.addEventListener('click', () => {
        const open = !item.classList.contains('is-open');
        item.classList.toggle('is-open', open);
        item.classList.remove('is-closed');
        btn.setAttribute('aria-expanded', String(open));
        if (open) $('.mega-link', item)?.focus();
      });
      item.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); item.classList.add('is-closed'); btn.focus(); } });
      item.addEventListener('mouseleave', () => item.classList.remove('is-closed'));
      item.addEventListener('focusout', (e) => { if (!item.contains(e.relatedTarget)) close(); });
      document.addEventListener('click', (e) => { if (!item.contains(e.target)) close(); });
    });
  }

  /* ---------------- Reveal + counters ---------------- */
  function initReveal() {
    const items = $$('[data-reveal]');
    if (reduceMotion || !('IntersectionObserver' in window)) { items.forEach((el) => el.classList.add('is-visible')); return; }
    let pending = items.slice();
    const show = (el) => { el.classList.add('is-visible'); io.unobserve(el); };
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) show(en.target);
    }), { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    items.forEach((el) => io.observe(el));

    // Safety net: never leave content invisible. The observer can miss elements after fast
    // scrolling, #anchor jumps, back/forward cache restores or throttled background tabs, so
    // anything at or above the viewport bottom is revealed on scroll/resize/hash change.
    let ticking = false;
    const sweep = () => {
      ticking = false;
      const limit = window.innerHeight;
      pending = pending.filter((el) => {
        if (el.classList.contains('is-visible')) return false;
        if (el.getBoundingClientRect().top < limit) { show(el); return false; }
        return true;
      });
      if (!pending.length) ['scroll', 'resize'].forEach((e) => window.removeEventListener(e, onScroll));
    };
    // setTimeout rather than requestAnimationFrame: rAF is paused in background/occluded tabs
    const onScroll = () => { if (!ticking) { ticking = true; setTimeout(sweep, 100); } };
    ['scroll', 'resize'].forEach((e) => window.addEventListener(e, onScroll, { passive: true }));
    window.addEventListener('hashchange', sweep);
    window.addEventListener('pageshow', sweep);
    window.addEventListener('load', sweep);
    window.addEventListener('beforeprint', () => items.forEach(show));
    sweep();
  }

  function initCounters() {
    const counters = $$('[data-count]');
    if (!counters.length || reduceMotion || !('IntersectionObserver' in window)) return;
    const run = (el) => {
      const target = Number(el.dataset.count);
      const start = performance.now();
      const step = (now) => {
        const t = Math.min((now - start) / 1600, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3))).toLocaleString();
        if (t < 1) requestAnimationFrame(step);
      };
      el.textContent = '0';
      requestAnimationFrame(step);
    };
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
    }), { threshold: 0.5 });
    counters.forEach((el) => io.observe(el));
  }

  /* ---------------- Products catalogue: filter + search ---------------- */
  function initCatalog() {
    const grid = $('#product-grid');
    const search = $('#product-search');
    if (!grid || !search) return;
    const cards = $$('.product-card', grid);
    let filter = 'all';
    const apply = () => {
      const q = search.value.trim().toLowerCase();
      let shown = 0;
      cards.forEach((c) => {
        const ok = (filter === 'all' || c.dataset.category === filter) && (!q || c.dataset.search.includes(q));
        c.hidden = !ok;
        if (ok) { shown++; c.classList.add('is-visible'); }
      });
      $('#product-count').textContent = `Showing ${shown} product${shown === 1 ? '' : 's'}`;
      $('#product-empty').hidden = shown > 0;
    };
    $$('[data-filter]').forEach((btn) => btn.addEventListener('click', () => {
      filter = btn.dataset.filter;
      $$('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
      apply();
    }));
    search.addEventListener('input', apply);
  }

  /* ---------------- Scroll-spy (tabs + table of contents) ---------------- */
  function initScrollSpy() {
    const links = $$('[data-tabs-nav] .tab-link, [data-toc-link]');
    if (!links.length || !('IntersectionObserver' in window)) return;
    const ids = [...new Set(links.map((l) => decodeURIComponent(l.getAttribute('href').slice(1))))];
    const setActive = (id) => {
      links.forEach((l) => l.classList.toggle('is-active', decodeURIComponent(l.getAttribute('href').slice(1)) === id));
      const tab = links.find((l) => l.classList.contains('is-active') && l.closest('[data-tabs-nav]'));
      tab?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    };
    const io = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) setActive(en.target.id); }), { rootMargin: '-30% 0px -60% 0px' });
    ids.forEach((id) => { const el = document.getElementById(id); if (el) io.observe(el); });
  }

  /* ---------------- Attribution: landing page + referrer (session only) ---------------- */
  function initAttribution() {
    if (!store.get('landing', sessionStorage)) {
      store.set('landing', { url: location.href, ref: document.referrer }, sessionStorage);
    }
  }

  /* ---------------- Lead forms (contact + quote) ---------------- */
  const MAX_FILE = 10 * 1024 * 1024;
  const FILE_TYPES = /\.(pdf|dwg|dxf|jpe?g|png|docx?)$/i;

  function initForms() {
    $$('form.lead-form').forEach((form) => {
      const success = document.getElementById(form.dataset.success);
      const alertBox = $('[data-form-alert]', form);
      const fileInput = $('input[type="file"]', form);
      const submitBtn = $('button[type="submit"]', form);
      let files = [];

      const landing = store.get('landing', sessionStorage) || {};
      form.elements._page.value = location.href;
      form.elements._landing.value = landing.url || location.href;
      form.elements._referrer.value = landing.ref || document.referrer;

      const today = new Date(); today.setHours(0, 0, 0, 0);
      const rule = (input) => {
        const v = input.type === 'checkbox' ? input.checked : input.value.trim();
        const label = input.closest('.field')?.querySelector('label')?.textContent.replace('*', '').trim() || 'This field';
        if (input.required && !v) {
          if (input.type === 'checkbox') return 'Please confirm to continue.';
          return input.tagName === 'SELECT' ? 'Please select an option.' : `Please enter your ${label.toLowerCase()}.`;
        }
        if (!v || input.type === 'checkbox') return '';
        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Please enter a valid email address.';
        if (input.type === 'tel' && !/^\+?[\d\s\-()]{7,20}$/.test(v)) return 'Please enter a valid phone number.';
        if (input.type === 'number' && (!/^\d+$/.test(v) || Number(v) < 1)) return 'Please enter a whole number of 1 or more.';
        if (input.type === 'date' && new Date(`${v}T00:00:00`) < today) return 'Please choose a date in the future.';
        if (input.name === 'fullName' && v.length < 2) return 'Name looks too short.';
        if (input.tagName === 'TEXTAREA' && v.length < 10) return 'Please add a little more detail (at least 10 characters).';
        return '';
      };
      const showError = (name, msg) => {
        const input = form.elements[name];
        const field = (input?.closest ? input : input?.[0])?.closest('.field');
        field?.classList.toggle('has-error', Boolean(msg));
        if (input?.setAttribute) input.setAttribute('aria-invalid', msg ? 'true' : 'false');
        const err = document.getElementById(`err-${name}`);
        if (err) err.textContent = msg;
      };
      const validate = (input) => { const msg = rule(input); showError(input.name, msg); return !msg; };

      const inputs = $$('.field input:not([type="file"]), .field select, .field textarea', form);
      inputs.forEach((input) => {
        input.addEventListener('blur', () => { if (input.value || input.closest('.has-error')) validate(input); });
        input.addEventListener('input', () => { if (input.closest('.has-error')) validate(input); });
        input.addEventListener('change', () => { if (input.closest('.has-error') || input.type === 'checkbox') validate(input); });
      });

      // Attachments (quote form)
      if (fileInput) {
        const drop = fileInput.closest('.file-drop');
        const list = $('[data-file-list]', form);
        const err = $('#err-attachments');
        const render = () => {
          list.replaceChildren();
          files.forEach((f, i) => {
            const li = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = `${f.name} (${(f.size / 1024 / 1024).toFixed(1)} MB)`;
            const rm = document.createElement('button');
            rm.type = 'button'; rm.textContent = 'Remove'; rm.setAttribute('aria-label', `Remove ${f.name}`);
            rm.addEventListener('click', () => { files.splice(i, 1); render(); });
            li.append(name, rm); list.append(li);
          });
        };
        const add = (incoming) => {
          const rejected = [];
          [...incoming].forEach((f) => {
            if (files.length >= 5) rejected.push(`${f.name}: maximum 5 files`);
            else if (!FILE_TYPES.test(f.name)) rejected.push(`${f.name}: unsupported type`);
            else if (f.size > MAX_FILE) rejected.push(`${f.name}: larger than 10 MB`);
            else if (!files.some((x) => x.name === f.name && x.size === f.size)) files.push(f);
          });
          err.textContent = rejected.join(' · ');
          render();
        };
        fileInput.addEventListener('change', () => { add(fileInput.files); fileInput.value = ''; });
        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('is-dragover'); }));
        drop.addEventListener('drop', (e) => add(e.dataTransfer.files));
      }

      const setAlert = (msg) => { if (!alertBox) return; alertBox.textContent = msg || ''; alertBox.hidden = !msg; };

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        setAlert('');
        const invalid = inputs.filter((i) => !validate(i));
        if (invalid.length) { invalid[0].focus(); return; }

        const data = new FormData(form);
        data.delete('attachments[]');
        files.forEach((f) => data.append('attachments[]', f));
        submitBtn.disabled = true;
        const label = submitBtn.innerHTML;
        submitBtn.textContent = 'Sending…';
        try {
          const res = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
          const json = await res.json().catch(() => ({ ok: false, message: 'Unexpected server response. Please try again or call us.' }));
          if (!json.ok) {
            Object.entries(json.errors || {}).forEach(([k, msg]) => showError(k, msg));
            setAlert(json.message || 'Please check the form.');
            alertBox?.scrollIntoView({ behavior: scrollBehavior, block: 'center' });
            window.turnstile?.reset?.();
            return;
          }
          $('[data-success-name]', success).textContent = form.elements.fullName.value.trim();
          // Service requests: show the ticket number and the private tracking link
          const track = $('[data-success-track]', success);
          if (track && json.track) { track.href = json.track; track.hidden = false; }
          const ref = $('[data-success-ref]', success);
          if (ref && json.ref) { ref.textContent = json.ref; ref.closest('[data-success-refwrap]')?.removeAttribute('hidden'); }
          form.hidden = true;
          success.hidden = false;
          success.focus();
          success.scrollIntoView({ behavior: scrollBehavior, block: 'center' });
          if (form.elements.form.value === 'quote') window.dispatchEvent(new Event('rfq:sent'));
          if (typeof window.gtag === 'function') window.gtag('event', 'generate_lead', { form_type: form.elements.form.value });
          if (typeof window.fbq === 'function') window.fbq('track', 'Lead');
        } catch {
          setAlert('Network error — please check your connection and try again, or contact us by phone.');
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = label;
        }
      });

      $('[data-form-reset]', success)?.addEventListener('click', () => {
        form.reset();
        files = [];
        $('[data-file-list]', form)?.replaceChildren();
        $$('.field', form).forEach((f) => f.classList.remove('has-error'));
        $$('.field-error', form).forEach((p) => (p.textContent = ''));
        // Refresh the anti-spam timestamp by reloading the token from the page is not needed: tokens last 2 hours.
        success.hidden = true;
        form.hidden = false;
        form.elements.fullName.focus();
      });
    });
  }

  /* ---------------- Click tracking (anonymous, no cookies) ---------------- */
  function initTracking() {
    document.addEventListener('click', (e) => {
      const a = e.target.closest('[data-track]');
      if (!a || !navigator.sendBeacon) return;
      const fd = new FormData();
      fd.append('type', a.dataset.track);
      fd.append('page', location.pathname);
      navigator.sendBeacon(`${base}/track`, fd);
    });
  }

  /* ---------------- Cookie consent + deferred integrations ---------------- */
  function activateConsentScripts() {
    $$('script[type="text/plain"][data-consent]').forEach((old) => {
      const s = document.createElement('script');
      if (old.dataset.src) { s.src = old.dataset.src; s.async = true; } else s.text = old.textContent;
      old.replaceWith(s);
    });
    $$('template[data-consent-html]').forEach((tpl) => {
      const frag = tpl.content.cloneNode(true);
      // Scripts inside templates must be re-created to execute
      $$('script', frag).forEach((old) => {
        const s = document.createElement('script');
        [...old.attributes].forEach((a) => s.setAttribute(a.name, a.value));
        s.text = old.textContent;
        old.replaceWith(s);
      });
      document.head.append(frag);
      tpl.remove();
    });
  }

  function initCookies() {
    const banner = $('#cookie-banner');
    const YEAR = 365 * 24 * 60 * 60 * 1000;
    const saved = store.get('cookie-consent');
    const valid = saved && Date.now() - saved.at < YEAR;
    if (valid && saved.choice === 'accepted') activateConsentScripts();
    if (!banner) return;
    if (!valid) banner.hidden = false;
    $$('[data-cookie-choice]', banner).forEach((b) => b.addEventListener('click', () => {
      const choice = b.dataset.cookieChoice;
      const prev = store.get('cookie-consent');
      store.set('cookie-consent', { choice, at: Date.now() });
      banner.hidden = true;
      if (choice === 'accepted') activateConsentScripts();
      else if (prev?.choice === 'accepted') location.reload(); // stop already-loaded trackers
    }));
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-cookie-settings]')) { banner.hidden = false; $('[data-cookie-choice]', banner).focus(); }
    });
  }

  /* ---------------- Misc ---------------- */
  function initImageFallback() {
    const apply = (img) => {
      const frame = img.closest('.img-frame, .industry-tile') || img.parentElement;
      if (!frame) return;
      frame.classList.add('img-fallback');
      if (!frame.dataset.fallbackLabel) frame.dataset.fallbackLabel = img.alt ? 'Image placeholder' : '';
      if (getComputedStyle(frame).position === 'static') frame.style.position = 'relative';
    };
    document.addEventListener('error', (e) => { if (e.target.tagName === 'IMG') apply(e.target); }, true);
    $$('img').forEach((img) => { if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) apply(img); });
  }

  function initMisc() {
    $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
    $$('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(b.dataset.copy); b.classList.add('is-copied'); setTimeout(() => b.classList.remove('is-copied'), 1500); } catch { /* ignore */ }
    }));
  }

  function init() {
    initImageFallback();
    initTheme();
    initScrollUI();
    initMobileMenu();
    initMegaMenu();
    initReveal();
    initCounters();
    initCatalog();
    initScrollSpy();
    initAttribution();
    initQuoteList();
    initForms();
    initTracking();
    initCookies();
    initMisc();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
