// Light / dark theme for the admin (and its sign-in screens). Loaded in <head> without `defer` so the
// right theme is applied before the first paint — no white flash. The choice is stored in the same
// "theme" key as the public website, so it follows the person between the two.
(function (root) {
  var stored = null;
  try { stored = localStorage.getItem('theme'); } catch (e) { /* private mode */ }
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var pick = function () {
    var t = stored || root.getAttribute('data-theme-default') || 'light';
    return t === 'dark' || (t === 'system' && mq && mq.matches);
  };
  var sync = function (dark) {
    root.classList.toggle('dark', dark);
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].setAttribute('aria-pressed', String(dark));
      buttons[i].setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
      buttons[i].title = dark ? 'Light theme' : 'Dark theme';
    }
  };
  root.classList.toggle('dark', pick());

  document.addEventListener('DOMContentLoaded', function () { sync(root.classList.contains('dark')); });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
    if (!btn) return;
    var dark = !root.classList.contains('dark');
    root.classList.add('theme-anim');
    setTimeout(function () { root.classList.remove('theme-anim'); }, 350);
    sync(dark);
    stored = dark ? 'dark' : 'light';
    try { localStorage.setItem('theme', stored); } catch (err) { /* ignore */ }
  });
  // Follow the device when no explicit choice was made and the site default is "system"
  if (mq && mq.addEventListener) mq.addEventListener('change', function () { if (!stored) sync(pick()); });
})(document.documentElement);
