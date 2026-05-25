// ============================================================
// StockMTW - Main JavaScript
// ============================================================

const App = {
  // --- Toast notifications ---
  toast(msg, type = 'info', duration = 3500) {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const t = document.createElement('div');
    const icons = {
      success: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
      error:   '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
      info:    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    t.className = `toast ${type}`;
    t.innerHTML = `${icons[type] || icons.info}<span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), duration);
  },

  // --- Modal management ---
  openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('show');
  },
  closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('show');
  },

  // --- AJAX fetch helper ---
  async fetch(url, options = {}) {
    try {
      const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options,
      });
      return await res.json();
    } catch (e) {
      this.toast('Erreur réseau', 'error');
      return null;
    }
  },

  // --- Format numbers ---
  formatMontant(val) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(val)) + ' FCFA';
  },
  formatQte(val) {
    const n = parseFloat(val);
    return n % 1 === 0 ? n.toLocaleString('fr-FR') : n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
  },
};

// ---- Confirm delete ----
function confirmDelete(url, callback) {
  if (!confirm('Confirmer la suppression ?')) return;
  App.fetch(url, { method: 'DELETE' }).then(r => {
    if (r?.success) {
      App.toast('Supprimé avec succès', 'success');
      callback?.();
    } else {
      App.toast(r?.message || 'Erreur', 'error');
    }
  });
}

// ---- Auto-close modal on overlay click ----
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
  }
});

// ---- Mobile sidebar toggle ----
const mobileToggle = document.getElementById('mobile-toggle');
const sidebar = document.querySelector('.sidebar');
if (mobileToggle && sidebar) {
  mobileToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}

// ---- Active nav item ----
document.querySelectorAll('.nav-item').forEach(item => {
  const href = item.getAttribute('href');
  if (href && window.location.pathname.includes(href.split('?')[0]) && href !== '/') {
    item.classList.add('active');
  }
});

// ---- Sortable table headers ----
document.querySelectorAll('thead th.sortable').forEach(th => {
  th.addEventListener('click', function () {
    const table = this.closest('table');
    const tbody = table.querySelector('tbody');
    const col = Array.from(this.parentElement.children).indexOf(this);
    const asc = this.dataset.sortDir !== 'asc';
    this.dataset.sortDir = asc ? 'asc' : 'desc';

    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
      const av = a.cells[col]?.textContent.trim() || '';
      const bv = b.cells[col]?.textContent.trim() || '';
      const an = parseFloat(av.replace(/\s/g, '').replace(',', '.'));
      const bn = parseFloat(bv.replace(/\s/g, '').replace(',', '.'));
      if (!isNaN(an) && !isNaN(bn)) return asc ? an - bn : bn - an;
      return asc ? av.localeCompare(bv, 'fr') : bv.localeCompare(av, 'fr');
    });
    rows.forEach(r => tbody.appendChild(r));

    // Reset other headers
    this.parentElement.querySelectorAll('th').forEach(h => {
      if (h !== this) delete h.dataset.sortDir;
      h.classList.remove('sort-asc', 'sort-desc');
    });
    this.classList.toggle('sort-asc', asc);
    this.classList.toggle('sort-desc', !asc);
  });
});

// ---- Search in table ----
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ---- Auto-calculate montant ----
document.addEventListener('input', function (e) {
  const form = e.target.closest('form');
  if (!form) return;
  const qte = parseFloat(form.querySelector('[name="quantite"]')?.value || 0);
  const pu  = parseFloat(form.querySelector('[name="prix_unitaire"]')?.value || 0);
  const mt  = form.querySelector('[name="montant"], .calc-montant');
  if (mt && !isNaN(qte) && !isNaN(pu)) {
    const val = qte * pu;
    if (mt.tagName === 'INPUT') mt.value = val;
    else mt.textContent = App.formatMontant(val);
  }
});

// ---- Tabs ----
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', function () {
    const container = this.closest('[data-tabs]') || document.body;
    const target = this.dataset.tab;
    container.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    container.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    this.classList.add('active');
    const pane = container.querySelector(`[data-tab-pane="${target}"]`);
    if (pane) pane.style.display = '';
  });
});

// ---- Print page ----
function printPage() {
  window.print();
}

// ---- Number input formatting ----
document.querySelectorAll('input[type="number"]').forEach(inp => {
  inp.addEventListener('wheel', e => e.preventDefault());
});

console.log('%cStockMTW v1.0 — MOUTOURWA', 'color: #d4a017; font-weight: bold; font-size: 14px;');
