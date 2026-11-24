/* Show / hide toggle for every password field (admin + sign-in screens). Accessible: real <button>,
   aria-pressed + label, keeps focus and caret position; text reverts to hidden on submit. */
(() => {
  'use strict';
  const EYE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>';

  function enhance(input) {
    if (input.dataset.pwToggle || input.hasAttribute('data-no-toggle')) return;
    input.dataset.pwToggle = '1';
    const wrap = document.createElement('span');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    const set = (shown) => {
      input.type = shown ? 'text' : 'password';
      btn.innerHTML = shown ? EYE_OFF : EYE;
      btn.setAttribute('aria-pressed', String(shown));
      btn.setAttribute('aria-label', shown ? 'Hide password' : 'Show password');
      btn.title = shown ? 'Hide password' : 'Show password';
    };
    set(false);
    btn.addEventListener('click', () => {
      const pos = [input.selectionStart, input.selectionEnd];
      set(input.type === 'password');
      input.focus({ preventScroll: true });
      try { input.setSelectionRange(pos[0], pos[1]); } catch { /* not supported for this type */ }
    });
    // Never submit or leave a password visible
    input.form?.addEventListener('submit', () => set(false));
    wrap.appendChild(btn);
  }

  const scan = (root = document) => root.querySelectorAll('input[type="password"]').forEach(enhance);
  scan();
  new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) scan(n); }))).observe(document.body, { childList: true, subtree: true });
})();
