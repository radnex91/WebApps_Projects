// ============================================================
//  MediCore ERP — JavaScript principal
// ============================================================

// ── Sidebar mobile (overlay + fermeture) ─────────────────────
const sidebarOverlay = document.getElementById('sidebar-overlay');
const sidebar = document.getElementById('sidebar');

// ── Sections pliables dans la sidebar ─────────────────────────
if (sidebar) {
  const nav = sidebar.querySelector('.sidebar-nav');
  if (nav) {
    // Ajouter le toggle et le comportement pliable a chaque section
    nav.querySelectorAll('.nav-section-label').forEach(function(label) {
      label.style.cursor = 'pointer';
      // Marqueur de toggle
      var tog = document.createElement('span');
      tog.className = 'nav-section-arrow';
      tog.textContent = '▾';
      label.insertBefore(tog, label.firstChild);

      // Grouper les items suivant dans un wrapper
      var wrapper = document.createElement('div');
      wrapper.className = 'nav-section-items';
      var el = label.nextElementSibling;
      while (el && !el.classList.contains('nav-section-label')) {
        var next = el.nextElementSibling;
        wrapper.appendChild(el);
        el = next;
      }
      label.parentNode.insertBefore(wrapper, label.nextElementSibling);

      // Plier/Deplier au clic
      label.addEventListener('click', function() {
        var items = label.nextElementSibling;
        if (!items || !items.classList.contains('nav-section-items')) return;
        var collapsed = items.classList.toggle('collapsed');
        tog.textContent = collapsed ? '▸' : '▾';
        try {
          localStorage.setItem('nav_section_' + label.textContent.trim(), collapsed ? '1' : '0');
        } catch(e) {}
      });

      // Restaurer l'etat depuis localStorage
      try {
        if (localStorage.getItem('nav_section_' + label.textContent.trim()) === '1') {
          if (label.nextElementSibling && label.nextElementSibling.classList.contains('nav-section-items')) {
            label.nextElementSibling.classList.add('collapsed');
            tog.textContent = '▸';
          }
        }
      } catch(e) {}

      // Si la section contient l'item actif, toujours la deplier
      if (label.nextElementSibling && label.nextElementSibling.querySelector('.nav-item.active')) {
        var items = label.nextElementSibling;
        items.classList.remove('collapsed');
        tog.textContent = '▾';
      }
    });
  }
}

function closeSidebar() {
  if (sidebar) sidebar.classList.remove('open');
  if (sidebarOverlay) sidebarOverlay.classList.remove('active');
}
if (sidebarOverlay) {
  sidebarOverlay.addEventListener('click', closeSidebar);
}
// Fermer la sidebar quand on clique sur un lien nav en mobile
if (sidebar) {
  sidebar.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeSidebar();
    });
  });
}
// Fermer la sidebar sur swipe gauche
let touchStartX = 0;
document.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
document.addEventListener('touchend', e => {
  const dx = e.changedTouches[0].clientX - touchStartX;
  if (dx < -60 && sidebar && sidebar.classList.contains('open')) closeSidebar();
}, { passive: true });

// ── Ouvrir sidebar : activer aussi l'overlay ─────────────────
document.querySelector('.menu-toggle')?.addEventListener('click', () => {
  sidebar?.classList.toggle('open');
  sidebarOverlay?.classList.toggle('active');
});

// ── Notifications panel ─────────────────────────────────────
function toggleNotifs(e) {
  e.stopPropagation();
  document.getElementById('notif-panel').classList.toggle('open');
}
document.addEventListener('click', (e) => {
  const p = document.getElementById('notif-panel');
  const btn = document.querySelector('.notif-btn');
  if (p && !p.contains(e.target) && btn && !btn.contains(e.target)) {
    p.classList.remove('open');
  }
});

// ── Polling en temps réel (toutes les 90s) ─────────────────
(function() {
  const POLL_INTERVAL = 90 * 1000; // 90 secondes
  const MAX_ERRORS = 5;
  let pollErrors = 0;

  function getCSRF() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function pollNotifications() {
    // Inclure les stats dashboard uniquement si on est sur le dashboard
    const isDashboard = document.getElementById('stat-patients-actifs') !== null;
    const url = APP_URL + '/poll' + (isDashboard ? '?dashboard=1' : '');
    fetch(url, {
      method: 'GET',
      headers: { 'X-CSRF-TOKEN': getCSRF() },
      credentials: 'same-origin'
    })
    .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then(data => {
      if (data.error) throw new Error(data.error);
      pollErrors = 0;
      updateNotifBadge(data.notifs);
      updateNotifPanel(data);
      updateLiveDot(false);
      // Si on est sur le dashboard, rafraîchir les stats
      if (data.dashboard) updateDashboardStats(data.dashboard);
    })
    .catch(err => {
      pollErrors++;
      updateLiveDot(pollErrors >= MAX_ERRORS);
      console.warn('[MediCore Poll]', err.message);
    });
  }

  function updateNotifBadge(notifs) {
    const badge = document.getElementById('notif-count');
    const dot = document.querySelector('.notif-dot');
    if (!badge) return;
    const total = notifs.total || 0;
    badge.textContent = total > 0 ? (total > 9 ? '9+' : total) : '';
    badge.dataset.count = total;
    // Afficher/masquer le point rouge
    if (dot) dot.style.display = total > 0 ? '' : 'none';
  }

  function updateNotifPanel(data) {
    const listEl = document.getElementById('notif-list');
    const actEl = document.getElementById('notif-activity');
    if (!listEl || !actEl) return;

    const n = data.notifs || {};
    const canUrg = canAccess('urgences');
    const canPharm = canAccess('pharmacie');
    const canLab = canAccess('laboratoire');

    let html = '';
    if (n.critiques > 0 && canUrg) {
      html += notifLink(APP_URL + '/urgences.php?filtre=critique', '🚨', 'rgba(239,68,68,.15)',
        '<span style="color:var(--red)">' + n.critiques + ' patient(s) critique(s)</span>', 'Intervention immédiate');
    }
    if (n.stocks > 0 && canPharm) {
      html += notifLink(APP_URL + '/pharmacie.php?tab=stock&filtre_stock=critique', '💊', 'rgba(239,68,68,.1)',
        '<span style="color:var(--red)">' + n.stocks + ' médicament(s) critique(s)</span>', 'Stock presque épuisé');
    }
    if (n.ordonnances > 0 && canPharm) {
      html += notifLink(APP_URL + '/pharmacie.php?tab=ordonnances', '📋', 'rgba(245,158,11,.12)',
        '<span style="color:var(--yellow)">' + n.ordonnances + ' ordonnance(s) en attente</span>', 'À traiter');
    }
    if (n.analyses > 0 && canLab) {
      html += notifLink(APP_URL + '/laboratoire.php?filtre=disponible', '🧪', 'rgba(16,185,129,.12)',
        '<span style="color:var(--green)">' + n.analyses + ' résultat(s) disponible(s)</span>', 'Analyses prêtes');
    }
    if (!html) html = '<div class="notif-empty">Aucune alerte pour le moment ✓</div>';
    listEl.innerHTML = html;

    // Activité récente
    const activities = data.activities || [];
    if (activities.length === 0) {
      actEl.innerHTML = '<div class="notif-empty">Aucune activité récente</div>';
    } else {
      const colors = { green: 'rgba(16,185,129,.15)', blue: 'rgba(59,130,246,.15)', yellow: 'rgba(245,158,11,.15)', red: 'rgba(239,68,68,.15)' };
      actEl.innerHTML = activities.map(a =>
        '<div class="notif-item">' +
          '<div class="notif-icon" style="background:' + (colors[a.couleur] || colors.blue) + '">' + (a.icone || '📌') + '</div>' +
          '<div><div class="notif-title">' + escapeHtml(a.action) + '</div>' +
          '<div class="notif-time">' + a.time + ' · ' + escapeHtml(a.user) + '</div></div>' +
        '</div>'
      ).join('');
    }
  }

  function notifLink(href, icon, bg, title, sub) {
    return '<a href="' + href + '" class="notif-item notif-link">' +
      '<div class="notif-icon" style="background:' + bg + '">' + icon + '</div>' +
      '<div><div class="notif-title">' + title + '</div><div class="notif-time">' + sub + '</div></div></a>';
  }

  function canAccess(page) {
    // Vérification basique — les admins voient tout
    // La vérification fine est côté serveur; ici on affiche tous les liens
    return true;
  }

  function updateLiveDot(hasError) {
    const dot = document.getElementById('live-dot');
    if (!dot) return;
    if (hasError) {
      dot.classList.add('error');
      dot.title = 'Connexion perdue';
    } else {
      dot.classList.remove('error');
      dot.title = 'En direct';
    }
  }

  function updateDashboardStats(stats) {
    if (!stats) return;
    const map = {
      'stat-patients-actifs': { val: stats.patients_actifs, fmt: v => String(v) },
      'stat-rdv-today': { val: stats.rdv_aujourd_hui, fmt: v => String(v) },
      'stat-ca-jour': { val: stats.ca_jour, fmt: v => formatMoney(v) },
    };
    for (const [id, spec] of Object.entries(map)) {
      const el = document.getElementById(id);
      if (el && spec.val !== undefined) {
        const formatted = spec.fmt(spec.val);
        if (el.textContent.trim() !== formatted) {
          el.textContent = formatted;
          el.closest('.stat-card')?.classList.add('stat-flash');
          setTimeout(() => el.closest('.stat-card')?.classList.remove('stat-flash'), 1500);
        }
      }
    }
  }

  function formatMoney(val) {
    return Number(val).toLocaleString('fr-FR') + ' FCFA';
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  // Lancer le polling après 90 secondes, puis toutes les 90s
  setTimeout(() => {
    pollNotifications();
    setInterval(pollNotifications, POLL_INTERVAL);
  }, POLL_INTERVAL);
})();

// ── Pill tabs ────────────────────────────────────────────────
document.querySelectorAll('.pill-tabs').forEach(group => {
  group.querySelectorAll('.pill-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      group.querySelectorAll('.pill-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
});

// ── Global search ────────────────────────────────────────────
const searchInput = document.getElementById('global-search');
if (searchInput) {
  searchInput.addEventListener('keyup', function(e) {
    if (e.key === 'Enter' && this.value.trim()) {
      window.location.href = APP_URL + '/patients.php?search=' + encodeURIComponent(this.value.trim());
    }
  });
}

// ── Auto-dismiss alerts ──────────────────────────────────────
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

// ── Confirm delete ───────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Confirmer cette action ?')) e.preventDefault();
  });
});

console.log('%cMediCore ERP v2.4', 'color:#3b82f6;font-size:16px;font-weight:bold');

// ── Theme picker ────────────────────────────────────────────
let themePending = { primary: null, accent: null, preset: null, bg: null, surface: null };

function _hexToRGBObj(hex) {
  return { r: parseInt(hex.substr(0,2),16), g: parseInt(hex.substr(2,2),16), b: parseInt(hex.substr(4,2),16) };
}
function _lightenHex(hex, amt) {
  return [0,2,4].map(i => Math.min(255, parseInt(hex.substr(i,2),16)+amt).toString(16).padStart(2,'0')).join('');
}
function _darkenHex(hex, amt) {
  return [0,2,4].map(i => Math.max(0, parseInt(hex.substr(i,2),16)-amt).toString(16).padStart(2,'0')).join('');
}

function selectPreset(presetId) {
  const presets = window.THEME_PRESETS || {};
  const preset = presets[presetId];
  if (!preset) return;

  themePending.primary  = preset.primary;
  themePending.accent   = preset.accent;
  themePending.preset    = presetId;
  themePending.bg        = preset.bg;
  themePending.surface   = preset.surface;

  // Update active card
  document.querySelectorAll('.theme-preset-card').forEach(card => {
    const isActive = card.dataset.preset === presetId;
    card.classList.toggle('active', isActive);
    // Update checkmark
    const check = card.querySelector('.theme-preset-check');
    if (isActive && !check) {
      const ck = document.createElement('div');
      ck.className = 'theme-preset-check';
      ck.innerHTML = '&#10003;';
      card.appendChild(ck);
    } else if (!isActive && check) {
      check.remove();
    }
  });

  applyThemeCSS();
}

function toggleCustomSection() {
  const section = document.getElementById('theme-custom-section');
  const chevron = document.getElementById('custom-chevron');
  if (!section) return;
  section.classList.toggle('collapsed');
  if (chevron) {
    chevron.classList.toggle('down', section.classList.contains('collapsed'));
    chevron.classList.toggle('up', !section.classList.contains('collapsed'));
  }
}

function setThemeColor(type, hex) {
  themePending[type] = hex;

  // Deselect all presets (user is customizing)
  themePending.preset = 'custom';
  document.querySelectorAll('.theme-preset-card').forEach(card => {
    card.classList.remove('active');
    const check = card.querySelector('.theme-preset-check');
    if (check) check.remove();
  });

  // Update swatch selection
  document.querySelectorAll('.theme-swatch').forEach(s => {
    const bg = s.style.background;
    s.classList.toggle('selected', bg === '#' + hex);
  });

  applyThemeCSS();
}

function applyThemeCSS() {
  const root = document.documentElement;
  const p = themePending.primary  || root.style.getPropertyValue('--primary')?.replace('#','')  || '1E3A5F';
  const a = themePending.accent   || root.style.getPropertyValue('--accent')?.replace('#','')    || '3b82f6';
  const bg     = themePending.bg      || root.style.getPropertyValue('--bg')?.replace('#','')         || '0a0e1a';
  const surf   = themePending.surface || root.style.getPropertyValue('--surface')?.replace('#','')    || '111827';
  const aLight = _lightenHex(a, 60);
  const aDark  = _darkenHex(a, 30);
  const pDark  = _darkenHex(p, 25);
  const aRGB   = _hexToRGBObj(a);

  root.style.setProperty('--primary', '#' + p);
  root.style.setProperty('--accent', '#' + a);
  root.style.setProperty('--accent2', '#' + aLight);
  root.style.setProperty('--bg', '#' + bg);
  root.style.setProperty('--surface', '#' + surf);

  // Sidebar gradient
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) sidebar.style.background = 'linear-gradient(180deg, #' + p + ' 0%, #' + pDark + ' 100%)';

  // Buttons
  document.querySelectorAll('.btn-blue, .btn-primary').forEach(el => el.style.background = '#' + a);
  // Badges
  document.querySelectorAll('.badge-blue').forEach(el => {
    el.style.background = 'rgba(' + aRGB.r + ',' + aRGB.g + ',' + aRGB.b + ',0.12)';
    el.style.color = '#' + a;
  });
  // Pill tabs
  document.querySelectorAll('.pill-tab.active').forEach(el => {
    el.style.background = '#' + a;
    el.style.borderColor = '#' + a;
  });
}

function saveTheme() {
  const data = new FormData();
  data.append('action', 'save_theme');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  data.append('csrf_token', csrf);
  if (themePending.preset)   data.append('theme_preset', themePending.preset);
  if (themePending.primary)  data.append('theme_primary', themePending.primary);
  if (themePending.accent)   data.append('theme_accent', themePending.accent);
  if (themePending.bg)       data.append('theme_bg', themePending.bg);
  if (themePending.surface) data.append('theme_surface', themePending.surface);
  fetch(APP_URL + '/preferences', { method: 'POST', body: data })
    .then(r => r.json())
    .then(resp => {
      if (resp.success) {
        showThemeToast('Thème sauvegardé !');
        document.getElementById('theme-popover').classList.remove('open');
        themePending = { primary: null, accent: null, preset: null, bg: null, surface: null };
      } else {
        showThemeToast(resp.error || 'Erreur lors de la sauvegarde', true);
      }
    })
    .catch(() => showThemeToast('Erreur réseau', true));
}

function resetTheme() {
  if (!confirm('Réinitialiser votre thème aux paramètres par défaut ?')) return;
  const data = new FormData();
  data.append('action', 'reset_theme');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  data.append('csrf_token', csrf);
  fetch(APP_URL + '/preferences', { method: 'POST', body: data })
    .then(r => r.json())
    .then(resp => { if (resp.success) location.reload(); });
}

function showThemeToast(msg, isError) {
  let el = document.createElement('div');
  el.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;animation:fadeIn .3s;' +
    (isError ? 'background:rgba(239,68,68,.9);color:#fff' : 'background:rgba(16,185,129,.9);color:#fff');
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(() => el.remove(), 400); }, 2500);
}

// Close theme popover on outside click
document.addEventListener('click', function(e) {
  const pop = document.getElementById('theme-popover');
  const trigger = document.getElementById('user-theme-trigger');
  if (pop && trigger && !pop.contains(e.target) && !trigger.contains(e.target)) {
    pop.classList.remove('open');
  }
});