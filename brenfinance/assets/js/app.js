/* ═══════════════════════════════════════════════════════════════
   BrenFinance Suite — JavaScript Responsive
   ═══════════════════════════════════════════════════════════════ */

/* ─── THEME ENGINE v2 ──────────────────────────────────────────── */
const THEME_KEY = 'brenfinance_theme';

// Live theme preview
function previewTheme(themeName) {
  document.documentElement.setAttribute('data-theme', themeName);
  localStorage.setItem(THEME_KEY, themeName);
  updateThemePreview(themeName);
  updateMetaThemeColor(themeName);
}

function updateThemePreview(activeTheme) {
  document.querySelectorAll('.theme-swatch').forEach(swatch => {
    const isActive = swatch.dataset.theme === activeTheme;
    swatch.style.borderColor = isActive ? 'var(--accent)' : 'transparent';
    swatch.style.transform = isActive ? 'scale(1.05)' : 'scale(1)';
    swatch.style.boxShadow = isActive ? '0 4px 12px rgba(0,0,0,.2)' : 'none';
  });
}

function updateMetaThemeColor(theme) {
  const colors = {
    default:'#0f3060', wallstreet:'#0a0a0a', cyberpunk:'#0d001a', aurora:'#020024',
    executive:'#0a0a0f', solar:'#1a0f00', ocean:'#03045e', royal:'#1a0033', emerald:'#041e15'
  };
  let meta = document.querySelector('meta[name="theme-color"]');
  if (!meta) { meta = document.createElement('meta'); meta.name = 'theme-color'; document.head.appendChild(meta); }
  meta.content = colors[theme] || colors.default;
}

// Initialize theme from localStorage
function initTheme() {
  const saved = localStorage.getItem(THEME_KEY);
  if (saved) {
    document.documentElement.setAttribute('data-theme', saved);
    updateThemePreview(saved);
  }
}

/* ─── SIDEBAR MANAGEMENT ─────────────────────────────────────── */
const SIDEBAR_COLLAPSED_KEY = 'brenfinance_sidebar_collapsed';
const isMobile = () => window.innerWidth <= 768;

function getSidebar()   { return document.getElementById('sidebar'); }
function getOverlay()   { return document.getElementById('sidebar-overlay'); }
function getToggleIcon(){ return document.getElementById('toggle-icon'); }
function getToggleBtn() { return document.getElementById('sidebar-toggle'); }

// Desktop: toggle collapsed
function toggleDesktopSidebar() {
  const sidebar = getSidebar();
  const collapsed = sidebar.classList.toggle('collapsed');
  localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
  getToggleIcon().textContent = collapsed ? '☰' : '☰';
}

// Mobile: open/close drawer
function openMobileSidebar() {
  getSidebar().classList.add('mobile-open');
  getOverlay().classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeMobileSidebar() {
  getSidebar().classList.remove('mobile-open');
  getOverlay().classList.remove('active');
  document.body.style.overflow = '';
}
function toggleMobileSidebar() {
  const open = getSidebar().classList.contains('mobile-open');
  open ? closeMobileSidebar() : openMobileSidebar();
}

// Unified toggle (called by the hamburger button)
function toggleSidebar() {
  if (isMobile()) {
    toggleMobileSidebar();
  } else {
    toggleDesktopSidebar();
  }
}

// Init sidebar state on load
function initSidebar() {
  if (!isMobile()) {
    const collapsed = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    if (collapsed) getSidebar().classList.add('collapsed');
  }
}

// Close mobile sidebar on resize to desktop
window.addEventListener('resize', () => {
  if (!isMobile()) {
    closeMobileSidebar();
  }
});

// Swipe to close mobile sidebar
(function() {
  let startX = 0, startY = 0;
  document.addEventListener('touchstart', e => {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
  }, { passive: true });
  document.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    const dy = Math.abs(e.changedTouches[0].clientY - startY);
    if (dx < -60 && dy < 60 && getSidebar().classList.contains('mobile-open')) {
      closeMobileSidebar();
    }
    if (dx > 60 && dy < 60 && startX < 40 && !getSidebar().classList.contains('mobile-open') && isMobile()) {
      openMobileSidebar();
    }
  }, { passive: true });
})();

/* ─── LIVE CLOCK ─────────────────────────────────────────────── */
function updateClock() {
  const el = document.getElementById('live-clock');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleDateString('fr-CM', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
    + ' ' + now.toLocaleTimeString('fr-CM', { hour: '2-digit', minute: '2-digit' });
}

/* ─── MODAL MANAGEMENT WITH FOCUS TRAP ─────────────────────────── */
const modalStack = [];

function getFocusableElements(container) {
  return Array.from(container.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter(el => el.offsetParent !== null); // only visible
}

function trapFocus(container, event) {
  const focusable = getFocusableElements(container);
  if (focusable.length === 0) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.key === 'Tab') {
    if (event.shiftKey) {
      if (document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
    } else {
      if (document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  }
}

function openModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
  // Store the previously focused element
  modalStack.push({ id, trigger: document.activeElement });
  // Set ARIA
  const modal = m.querySelector('.modal');
  if (modal) {
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
  }
  // Focus first input after transition
  setTimeout(() => {
    const input = m.querySelector('input:not([type=hidden]), select, textarea');
    if (input) input.focus();
  }, 220);
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('open');
  document.body.style.overflow = '';
  // Remove from stack and restore focus
  const idx = modalStack.findIndex(s => s.id === id);
  if (idx !== -1) {
    const { trigger } = modalStack.splice(idx, 1)[0];
    if (trigger && typeof trigger.focus === 'function') {
      setTimeout(() => trigger.focus(), 100);
    }
  }
  // If other modals are still open, keep body overflow hidden
  if (document.querySelectorAll('.modal-overlay.open').length > 0) {
    document.body.style.overflow = 'hidden';
  }
}

// Global keyboard handlers
document.addEventListener('keydown', e => {
  // Escape: close topmost modal or notification dropdown
  if (e.key === 'Escape') {
    const openModals = document.querySelectorAll('.modal-overlay.open');
    if (openModals.length > 0) {
      const topModal = openModals[openModals.length - 1];
      closeModal(topModal.id || '');
      return;
    }
    // Close notification dropdown
    const dd = document.getElementById('notif-dropdown');
    if (dd && dd.classList.contains('open')) {
      dd.classList.remove('open');
      const btn = document.querySelector('.notif-bell');
      if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    }
  }
});

// Focus trap for open modals
document.addEventListener('keydown', e => {
  if (e.key !== 'Tab') return;
  const openModals = document.querySelectorAll('.modal-overlay.open');
  if (openModals.length === 0) return;
  const topModal = openModals[openModals.length - 1];
  trapFocus(topModal, e);
});

/* ─── TABS ───────────────────────────────────────────────────── */
document.addEventListener('click', e => {
  const tab = e.target.closest('.tab');
  if (!tab || !tab.dataset.tab) return;
  const tabsEl = tab.closest('.tabs');
  if (!tabsEl) return;
  tabsEl.querySelectorAll('.tab').forEach(t => {
    t.classList.remove('active');
    t.setAttribute('aria-selected', 'false');
  });
  tab.classList.add('active');
  tab.setAttribute('aria-selected', 'true');
  const targetId = tab.dataset.tab;
  const wrapper  = tabsEl.closest('.tab-wrapper') || tabsEl.parentElement;
  wrapper.querySelectorAll('.tab-content').forEach(tc => {
    tc.classList.toggle('active', tc.id === targetId);
    tc.setAttribute('aria-hidden', tc.id !== targetId ? 'true' : 'false');
  });
});

/* ─── TABLE SEARCH ───────────────────────────────────────────── */
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ─── FORMAT HELPERS ─────────────────────────────────────────── */
function formatMontant(amount, devise = 'FCFA') {
  return new Intl.NumberFormat('fr-CM').format(Math.round(amount)) + ' ' + devise;
}

/* ─── TOAST SYSTEM ─────────────────────────────────────────────── */
function dismissToast(el) {
  if (!el || el.classList.contains('removing')) return;
  el.classList.add('removing');
  setTimeout(() => el.remove(), 300);
}

function initAlerts() {
  document.querySelectorAll('.toast').forEach(t => {
    // Auto-dismiss after progress bar completes (5s)
    setTimeout(() => dismissToast(t), 5200);
  });
}

// Push a toast dynamically (for AJAX responses)
function pushToast(type, message) {
  const icons = { success: '✓', danger: '✕', warning: '!', info: 'i' };
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = 'toast toast-' + type;
  toast.setAttribute('role', 'alert');

  const icon = document.createElement('span');
  icon.className = 'toast-icon';
  icon.textContent = icons[type] || 'i';

  const msg = document.createElement('span');
  msg.className = 'toast-msg';
  msg.textContent = message;

  const btn = document.createElement('button');
  btn.className = 'toast-close';
  btn.setAttribute('aria-label', 'Fermer');
  btn.innerHTML = '&times;';
  btn.addEventListener('click', function() { dismissToast(toast); });

  const progress = document.createElement('div');
  progress.className = 'toast-progress';

  toast.appendChild(icon);
  toast.appendChild(msg);
  toast.appendChild(btn);
  toast.appendChild(progress);
  container.appendChild(toast);
  setTimeout(() => dismissToast(toast), 5200);
}

/* ─── AMOUNT INPUT FORMATTER ─────────────────────────────────── */
document.addEventListener('blur', e => {
  if (e.target.classList.contains('amount-input')) {
    const v = parseFloat(e.target.value.replace(/\s/g, '').replace(',', '.'));
    if (!isNaN(v) && v >= 0) e.target.value = v.toFixed(0);
  }
}, true);

/* ─── TODAY'S DATE HELPER ────────────────────────────────────── */
function today() { return new Date().toISOString().split('T')[0]; }

/* ─── PRINT ──────────────────────────────────────────────────── */
function printPage() { window.print(); }

/* ─── AJAX POST HELPER ───────────────────────────────────────── */
async function postJSON(url, data) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return resp.json();
}

/* ─── CONFIRM ACTION ─────────────────────────────────────────── */
function confirmAction(msg, callback) {
  if (window.confirm(msg)) callback();
}

/* ─── INITIALIZE ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initSidebar();
  initAlerts();
  updateClock();
  setInterval(updateClock, 30000);

  // Set today's date on empty date inputs
  document.querySelectorAll('input[type=date]:not([value])').forEach(i => {
    if (!i.value) i.value = today();
  });

  // Activate first tab in each tab group
  document.querySelectorAll('.tabs').forEach(tabs => {
    if (!tabs.querySelector('.tab.active')) {
      const first = tabs.querySelector('.tab');
      if (first) {
        first.classList.add('active');
        first.setAttribute('aria-selected', 'true');
        const target = first.dataset.tab;
        const wrapper = tabs.closest('.tab-wrapper') || tabs.parentElement;
        if (target) {
          const firstContent = wrapper.querySelector('#' + target);
          if (firstContent) {
            firstContent.classList.add('active');
            firstContent.setAttribute('aria-hidden', 'false');
          }
        }
      }
    }
  });

  // Close mobile sidebar when a nav link is clicked
  document.querySelectorAll('.sidebar .nav-item').forEach(link => {
    link.addEventListener('click', () => {
      if (isMobile()) closeMobileSidebar();
    });
  });

  // Update sidebar toggle aria-expanded
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      const expanded = !getSidebar().classList.contains('collapsed');
      sidebarToggle.setAttribute('aria-expanded', isMobile() ? getSidebar().classList.contains('mobile-open') : expanded);
    });
  }
});