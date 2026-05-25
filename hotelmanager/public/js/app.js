/**
 * HotelPro Suite — app.js
 * Scripts globaux de l'application
 */

'use strict';

// ── Sidebar mobile ────────────────────────────────────────────
(function() {
  const sidebar  = document.getElementById('sidebar');
  const wrapper  = document.getElementById('mainWrapper');

  window.toggleSidebar = function() {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle('mobile-open');
    } else {
      sidebar.classList.toggle('collapsed');
      wrapper.classList.toggle('expanded');
    }
  };

  // Ferme la sidebar mobile en cliquant ailleurs
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 &&
        sidebar && sidebar.classList.contains('mobile-open') &&
        !sidebar.contains(e.target) &&
        !e.target.closest('.btn-menu-toggle')) {
      sidebar.classList.remove('mobile-open');
    }
  });
})();

// ── Auto-dismiss alerts ───────────────────────────────────────
document.querySelectorAll('.alert.fade').forEach(function(el) {
  setTimeout(function() {
    try {
      bootstrap.Alert.getOrCreateInstance(el).close();
    } catch(e) {}
  }, 5000);
});

// ── Confirmation avant soumission ─────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function(el) {
  el.addEventListener('click', function(e) {
    const msg = this.getAttribute('data-confirm') || 'Confirmer cette action ?';
    if (!confirm(msg)) {
      e.preventDefault();
      e.stopPropagation();
    }
  });
});

// ── Tooltips Bootstrap ────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
  new bootstrap.Tooltip(el);
});

// ── Table de recherche live (filtre côté client) ──────────────
const liveSearch = document.getElementById('liveSearch');
if (liveSearch) {
  liveSearch.addEventListener('input', function() {
    const q     = this.value.toLowerCase();
    const tbody = document.querySelector(this.dataset.target || 'table tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(function(tr) {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Formatage FCFA ────────────────────────────────────────────
window.formatFCFA = function(n) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n) + ' FCFA';
};

// ── Date picker: empêche date_depart < date_arrivee ───────────
(function() {
  const da = document.getElementById('date_arrivee');
  const dd = document.getElementById('date_depart');
  if (da && dd) {
    da.addEventListener('change', function() {
      const next = new Date(this.value);
      next.setDate(next.getDate() + 1);
      dd.min = next.toISOString().split('T')[0];
      if (dd.value && dd.value <= this.value) {
        dd.value = next.toISOString().split('T')[0];
        dd.dispatchEvent(new Event('change'));
      }
    });
  }
})();

// ── Compteur de caractères pour les textareas ─────────────────
document.querySelectorAll('textarea[maxlength]').forEach(function(ta) {
  const counter = document.createElement('div');
  counter.className = 'form-text text-end';
  ta.parentNode.appendChild(counter);
  function update() {
    const rem = ta.maxLength - ta.value.length;
    counter.textContent = rem + ' caractère(s) restant(s)';
    counter.style.color = rem < 20 ? '#dc2626' : '#6b7280';
  }
  ta.addEventListener('input', update);
  update();
});

// ── Imprimer une section ──────────────────────────────────────
window.printSection = function(sectionId) {
  const content = document.getElementById(sectionId);
  if (!content) return;
  const win = window.open('', '_blank');
  win.document.write('<html><head><title>Impression</title>');
  win.document.write('<link rel="stylesheet" href="' + window.location.origin + '/hotelmanager/public/css/bootstrap.min.css">');
  win.document.write('</head><body style="padding:20px;">');
  win.document.write(content.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  win.print();
};

// ── Copier dans le presse-papier ─────────────────────────────
window.copyToClipboard = function(text, btn) {
  navigator.clipboard.writeText(text).then(function() {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i>';
    btn.classList.add('btn-success');
    setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('btn-success'); }, 1500);
  });
};

// ── Confirmation de déconnexion ──────────────────────────────
const logoutBtn = document.querySelector('a.btn-logout');
if (logoutBtn) {
  logoutBtn.addEventListener('click', function(e) {
    if (!confirm('Voulez-vous vous déconnecter ?')) e.preventDefault();
  });
}
