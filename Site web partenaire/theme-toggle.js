/**
 * Theme toggle — dark / light mode switcher.
 * Reads preference from localStorage, falls back to OS prefers-color-scheme.
 * Inserts a sun/moon button in the header.
 */
(function () {
  var KEY = 'esadiss-theme';

  var html = document.documentElement;
  var current = localStorage.getItem(KEY) ||
    (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  html.dataset.theme = current;
  localStorage.setItem(KEY, current);

  var sunSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';
  var moonSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

  function updateBtn(btn) {
    var isDark = html.dataset.theme === 'dark';
    btn.innerHTML = isDark ? sunSvg : moonSvg;
    btn.setAttribute('aria-label', isDark ? 'Passer en mode clair' : 'Passer en mode sombre');
    btn.title = isDark ? 'Mode clair' : 'Mode sombre';
  }

  function toggle() {
    var next = html.dataset.theme === 'dark' ? 'light' : 'dark';
    html.dataset.theme = next;
    localStorage.setItem(KEY, next);
    var btn = document.querySelector('.theme-toggle');
    if (btn) updateBtn(btn);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.header-right');
    if (!container) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.addEventListener('click', toggle);
    container.insertBefore(btn, container.firstChild);
    updateBtn(btn);
  });
})();