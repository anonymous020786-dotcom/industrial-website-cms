/* Admin panel interactions (vanilla JS). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const base = document.body.dataset.base || '';
  const csrf = $('meta[name="csrf-token"]')?.content || '';
  const u = (p) => `${base}/${p.replace(/^\//, '')}`;
  const mediaUrl = (p) => (/^https?:\/\//.test(p) ? p : u(p));

  async function uploadImage(file) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', csrf);
    const res = await fetch(u('admin/media/upload'), { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } });
    const json = await res.json().catch(() => ({ error: 'Upload failed.' }));
    if (!res.ok || json.error) throw new Error(json.error || 'Upload failed.');
    return json;
  }

  /* ---------- Layout ---------- */
  const sidebar = $('#adm-sidebar');
  $$('[data-sidebar-open]').forEach((b) => b.addEventListener('click', () => sidebar?.classList.add('is-open')));
  $$('[data-sidebar-close]').forEach((b) => b.addEventListener('click', () => sidebar?.classList.remove('is-open')));
  document.addEventListener('click', (e) => {
    const d = e.target.closest('[data-dismiss]');
    if (d) d.parentElement.remove();
    const c = e.target.closest('[data-confirm]');
    if (c && !confirm(c.dataset.confirm)) { e.preventDefault(); e.stopPropagation(); }
    const cp = e.target.closest('[data-copy]');
    if (cp) { navigator.clipboard?.writeText(cp.dataset.copy); const t = cp.textContent; cp.textContent = 'Copied!'; setTimeout(() => (cp.textContent = t), 1200); }
    const sd = e.target.closest('[data-set-date]');
    if (sd) { const i = $(sd.dataset.setDate); if (i) i.value = sd.dataset.date; }
  }, true);
  // Close the user menu when clicking elsewhere
  document.addEventListener('click', (e) => { const m = $('.adm-user[open]'); if (m && !m.contains(e.target)) m.removeAttribute('open'); });

  /* ---------- Bulk actions ---------- */
  $$('[data-bulk-form]').forEach((form) => {
    const all = $('[data-check-all]', form);
    const count = $('[data-bulk-count]', form);
    const update = () => { const n = $$('[data-check]:checked', form).length; if (count) count.textContent = `${n} selected`; };
    all?.addEventListener('change', () => { $$('[data-check]', form).forEach((c) => (c.checked = all.checked)); update(); });
    form.addEventListener('change', (e) => { if (e.target.matches('[data-check]')) update(); });
    const act = $('[data-bulk-action]', form);
    const sched = $('[data-schedule-fields]', form);
    act?.addEventListener('change', () => { if (sched) { sched.classList.toggle('hidden', act.value !== 'schedule'); sched.classList.toggle('flex', act.value === 'schedule'); } });
  });

  /* ---------- Profile picture: upload as soon as a file is chosen ---------- */
  $$('[data-avatar-input]').forEach((inp) => inp.addEventListener('change', () => { if (inp.files.length) inp.form.requestSubmit(); }));

  /* ---------- Reports: custom date range ---------- */
  $$('[data-range-select]').forEach((sel) => sel.addEventListener('change', () => {
    const box = $('[data-custom]', sel.form);
    if (box) box.hidden = sel.value !== 'custom';
  }));

  /* ---------- Slugs & counters ---------- */
  const slugify = (s) => s.toLowerCase().replace(/&/g, ' and ').normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 150);
  $$('form').forEach((form) => {
    const src = $('[data-slug-source]', form);
    const slug = $('[data-slug-input]', form);
    if (src && slug && !slug.value) {
      let touched = false;
      slug.addEventListener('input', () => (touched = true));
      src.addEventListener('input', () => { if (!touched) slug.value = slugify(src.value); });
    }
  });
  $$('.adm-field[data-counter]').forEach((f) => {
    const input = $('input, textarea', f);
    const max = Number(f.dataset.counter);
    const out = document.createElement('span');
    out.className = 'adm-counter';
    input.after(out);
    const upd = () => { const n = input.value.length; out.textContent = `${n} / ${max}`; out.classList.toggle('is-over', n > max); };
    input.addEventListener('input', upd);
    upd();
  });

  /* ---------- Rich text (Quill) ---------- */
  function initEditors(root = document) {
    if (!window.Quill) return;
    $$('textarea.js-editor', root).forEach((ta) => {
      if (ta.dataset.ready || ta.closest('template')) return;
      ta.dataset.ready = '1';
      const basic = ta.dataset.editor === 'basic';
      const toolbar = basic
        ? [['bold', 'italic', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']]
        : [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'code-block'], ['image', 'video'], [{ align: [] }], ['clean']];
      const holder = document.createElement('div');
      ta.style.display = 'none';
      ta.after(holder);
      const q = new window.Quill(holder, { theme: 'snow', modules: { toolbar: { container: toolbar, handlers: {
        image() {
          const input = document.createElement('input');
          input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp,image/gif';
          input.onchange = async () => {
            try {
              const res = await uploadImage(input.files[0]);
              const range = q.getSelection(true);
              q.insertEmbed(range.index, 'image', res.url, 'user');
            } catch (err) { alert(err.message); }
          };
          input.click();
        },
      } } } });
      if (basic) q.root.classList.add('ql-basic');
      const original = ta.value || '';
      q.clipboard.dangerouslyPasteHTML(original);

      // HTML source toggle
      const tb = holder.previousElementSibling;
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'ql-html-toggle'; btn.textContent = '</> HTML'; btn.title = 'Edit HTML source';
      const span = document.createElement('span'); span.className = 'ql-formats'; span.append(btn); tb?.append(span);
      const src = document.createElement('textarea'); src.className = 'adm-html-source'; src.hidden = true; holder.after(src);
      btn.addEventListener('click', () => {
        if (src.hidden) { src.value = q.getSemanticHTML(); src.hidden = false; holder.style.display = 'none'; }
        else { q.clipboard.dangerouslyPasteHTML(src.value); src.hidden = true; holder.style.display = ''; }
      });
      const sync = () => { ta.value = src.hidden ? (q.getLength() > 1 ? q.getSemanticHTML().replace(/&nbsp;/g, ' ') : '') : src.value; ta.dispatchEvent(new Event('input', { bubbles: true })); };
      q.on('text-change', sync);
      src.addEventListener('input', sync);
      ta.form?.addEventListener('submit', sync);

      // The visual editor cannot represent tables, figures or embedded HTML — open such
      // content in HTML mode so nothing is lost when saving.
      ta.value = original; // undo any normalisation from the initial paste
      if (/<(table|figure|iframe|div|address)\b/i.test(original)) {
        src.value = original;
        src.hidden = false;
        holder.style.display = 'none';
        const note = document.createElement('p');
        note.className = 'adm-help mt-2';
        note.textContent = 'This content contains tables or advanced HTML, so it opened in HTML mode to keep them intact. Switching to the visual editor will remove tables.';
        src.after(note);
      }
    });
  }
  window.addEventListener('load', () => initEditors());

  /* ---------- Image fields + media picker ---------- */
  const dialog = $('#media-dialog');
  let pickTarget = null;
  const setImage = (field, path) => {
    const input = $('[data-image-input]', field);
    input.value = path;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  document.addEventListener('input', (e) => {
    if (!e.target.matches('[data-image-input]')) return;
    const field = e.target.closest('[data-image-field]');
    const prev = $('[data-preview]', field);
    const v = e.target.value.trim();
    if (field.dataset.kind === 'pdf') {
      prev.innerHTML = v ? `<a href="${esc(mediaUrl(v))}" target="_blank" class="text-center text-[11px] font-bold text-red-700">PDF</a>` : '';
      return;
    }
    prev.innerHTML = v ? `<img src="${esc(mediaUrl(v))}" alt="">` : '';
  });
  document.addEventListener('click', async (e) => {
    const field = e.target.closest('[data-image-field]');
    if (e.target.closest('[data-image-clear]') && field) setImage(field, '');
    if (e.target.closest('[data-media-pick]') && field && dialog) {
      pickTarget = field;
      await loadLibrary(field.dataset.kind === 'pdf' ? 'pdf' : 'image');
      dialog.showModal();
    }
    const item = e.target.closest('[data-pick-path]');
    if (item && pickTarget) { setImage(pickTarget, item.dataset.pickPath); dialog.close(); }
    if (e.target.closest('[data-dialog-close]')) dialog?.close();
  });
  document.addEventListener('change', async (e) => {
    if (e.target.matches('[data-media-upload]')) {
      const field = e.target.closest('[data-image-field]');
      try { const res = await uploadImage(e.target.files[0]); setImage(field, res.path); } catch (err) { alert(err.message); }
      e.target.value = '';
    }
    if (e.target.matches('[data-dialog-upload]')) {
      try { const res = await uploadImage(e.target.files[0]); if (pickTarget) { setImage(pickTarget, res.path); dialog.close(); } } catch (err) { alert(err.message); }
      e.target.value = '';
    }
  });
  async function loadLibrary(type = 'image') {
    const grid = $('[data-media-grid]');
    const res = await fetch(u(`admin/media.json?type=${type}`), { headers: { Accept: 'application/json' } });
    const items = await res.json();
    grid.innerHTML = '';
    $('[data-media-empty]').hidden = items.length > 0;
    const up = $('[data-dialog-upload]');
    if (up) up.accept = type === 'pdf' ? 'application/pdf' : 'image/jpeg,image/png,image/webp,image/gif';
    items.forEach((m) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'adm-media-item'; b.dataset.pickPath = m.path; b.title = m.original_name;
      if (m.mime === 'application/pdf') {
        const t = document.createElement('span');
        t.className = 'grid h-full place-items-center p-2 text-center text-[11px] font-semibold text-red-700';
        t.textContent = `PDF · ${m.original_name}`;
        b.append(t);
      } else {
        const img = document.createElement('img'); img.src = m.url; img.alt = m.alt || ''; img.loading = 'lazy';
        b.append(img);
      }
      grid.append(b);
    });
  }

  /* Media library page: multi-upload + drag & drop */
  const libInput = $('[data-library-upload]');
  const drop = $('[data-library-drop]');
  const status = $('[data-upload-status]');
  async function uploadMany(files) {
    const list = [...files];
    let ok = 0;
    for (const [i, f] of list.entries()) {
      if (status) status.textContent = `Uploading ${i + 1} of ${list.length}…`;
      try { await uploadImage(f); ok++; } catch (err) { alert(`${f.name}: ${err.message}`); }
    }
    if (ok) location.reload();
    else if (status) status.textContent = '';
  }
  libInput?.addEventListener('change', () => uploadMany(libInput.files));
  if (drop) {
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', (e) => uploadMany(e.dataTransfer.files));
  }

  /* ---------- Icon fields ---------- */
  let iconsLoaded = false;
  const iconList = $('#lucide-icons');
  document.addEventListener('focusin', async (e) => {
    if (!e.target.matches('[data-icon-input]') || iconsLoaded || !iconList) return;
    iconsLoaded = true;
    const names = await (await fetch(u('admin/icon.svg?name=__list'))).json();
    iconList.innerHTML = names.map((n) => `<option value="${n}">`).join('');
  });
  let iconTimer;
  document.addEventListener('input', (e) => {
    if (!e.target.matches('[data-icon-input]')) return;
    clearTimeout(iconTimer);
    const input = e.target;
    iconTimer = setTimeout(async () => {
      const svg = await (await fetch(u(`admin/icon.svg?name=${encodeURIComponent(input.value.trim())}`))).text();
      const prev = input.parentElement.querySelector('[data-icon-preview]');
      if (prev && svg.startsWith('<svg')) prev.innerHTML = svg;
    }, 250);
  });

  /* ---------- Repeaters ---------- */
  let rowSeq = Date.now();
  document.addEventListener('click', (e) => {
    const rep = e.target.closest('[data-repeater]');
    if (!rep) return;
    const rows = $('[data-rows]', rep);
    if (e.target.closest('[data-row-add]')) {
      const tpl = $(':scope > template[data-row-template]', rep);
      const html = tpl.innerHTML.replaceAll('__INDEX__', String(rowSeq++));
      rows.insertAdjacentHTML('beforeend', html);
      const row = rows.lastElementChild;
      initEditors(row);
      $('input, textarea, select', row)?.focus();
      return;
    }
    const row = e.target.closest('[data-row]');
    if (!row || row.parentElement !== rows) return;
    if (e.target.closest('[data-row-remove]') && confirm('Remove this item?')) row.remove();
    if (e.target.closest('[data-row-up]') && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
    if (e.target.closest('[data-row-down]') && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
  });

  /* ---------- SEO preview & checks ---------- */
  $$('[data-seo-form]').forEach((form) => {
    const titleSrc = $('[data-seo-title-source]', form);
    const metaTitle = $('[data-seo-title]', form);
    const metaDesc = $('[data-seo-desc]', form);
    const keyword = $('[data-seo-keyword]', form);
    const slug = $('[data-slug-input]', form) || $('input[name="slug"]', form);
    const content = $('textarea[name="content"]', form);
    const excerpt = $('textarea[name="excerpt"]', form);
    const featured = $('input[name="featured_image"]', form);
    const featuredAlt = $('input[name="featured_alt"]', form);
    const site = form.dataset.siteName || '';
    const baseUrl = (form.dataset.seoUrl || '').replace(/[^/]+$/, '');
    const checks = $('[data-seo-checks]', form);
    const strip = (h) => { const d = document.createElement('div'); d.innerHTML = h || ''; return d.textContent || ''; };
    const update = () => {
      const t = (metaTitle?.value || titleSrc?.value || '').trim();
      const full = t ? `${t}${site ? ' | ' + site : ''}` : 'Page title';
      const descText = (metaDesc?.value || excerpt?.value || strip(content?.value).slice(0, 160)).trim();
      $('[data-serp-title]', form) && ($('[data-serp-title]', form).textContent = full.length > 62 ? full.slice(0, 60) + '…' : full);
      $('[data-serp-desc]', form) && ($('[data-serp-desc]', form).textContent = descText.length > 162 ? descText.slice(0, 160) + '…' : descText || 'Add a meta description to control how this page appears in search results.');
      $('[data-serp-url]', form) && ($('[data-serp-url]', form).textContent = baseUrl + (slug?.value || ''));
      if (!checks) return;
      const kw = (keyword?.value || '').trim().toLowerCase();
      const text = strip(content?.value);
      const words = text.split(/\s+/).filter(Boolean).length;
      const first = text.slice(0, 600).toLowerCase();
      const md = (metaDesc?.value || '').trim();
      const list = [
        [t.length >= 30 && t.length <= 60, `SEO title length: ${t.length} characters (aim for 30–60)`],
        [md.length >= 120 && md.length <= 160, `Meta description length: ${md.length} (aim for 120–160)`],
        [words >= 600, `Content length: ${words} words (600+ recommended for guides)`],
        [/<h2/i.test(content?.value || ''), 'Uses Heading 2 sections'],
        [!featured || !!featured.value, 'Has a featured image'],
        [!featuredAlt || !featured?.value || !!featuredAlt.value, 'Featured image has alt text'],
      ];
      if (kw) {
        list.unshift(
          [t.toLowerCase().includes(kw), 'Focus keyword in SEO title'],
          [md.toLowerCase().includes(kw), 'Focus keyword in meta description'],
          [(slug?.value || '').includes(slugify(kw).split('-')[0]), 'Focus keyword in URL'],
          [first.includes(kw), 'Focus keyword in the introduction'],
        );
      } else if (keyword) list.unshift([false, 'Set a focus keyword']);
      checks.innerHTML = list.map(([ok, label]) => `<li class="${ok ? 'ok' : 'warn'}">${label}</li>`).join('');
    };
    form.addEventListener('input', update);
    setTimeout(update, 300);
  });

  /* ---------------- Scroll up / down buttons with reading progress ---------------- */
  const toTop = $('[data-scroll-up]');
  const down = $('[data-scroll-down]');
  if (toTop || down) {
    const ring = $('.adm-scroll-ring .ring-fg');
    const RING = 2 * Math.PI * 22;
    const smooth = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    const topbar = $('.adm-topbar');
    let ticking = false;
    const update = () => {
      const y = window.scrollY;
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const pct = max > 0 ? Math.min(1, Math.max(0, y / max)) : 0;
      toTop?.classList.toggle('is-visible', y > 320);
      // Offered on long pages until the last screen
      down?.classList.toggle('is-visible', max > 600 && max - y > 400);
      if (ring) ring.style.strokeDashoffset = String(RING * (1 - pct));
      toTop?.setAttribute('aria-label', `Back to top (${Math.round(pct * 100)}% of the page)`);
      ticking = false;
    };
    window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    new ResizeObserver(update).observe(document.body); // pages that grow (repeaters, tables) update the buttons
    update();
    toTop?.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: smooth });
      $('.adm-main h1')?.setAttribute('tabindex', '-1');
      $('.adm-main h1')?.focus({ preventScroll: true });
    });
    // Next card or section below the sticky top bar, or most of a screen if the next one is far
    down?.addEventListener('click', () => {
      const offset = (topbar?.offsetHeight || 64) + 12;
      const next = $$('.adm-content > *, .adm-content .adm-card, .adm-content section')
        .map((el) => el.getBoundingClientRect().top - offset)
        .filter((t) => t > 24)
        .sort((a, b) => a - b)[0];
      const by = next === undefined ? window.innerHeight * 0.85 : Math.min(next, window.innerHeight * 0.9);
      window.scrollBy({ top: by, behavior: smooth });
    });
  }
})();
