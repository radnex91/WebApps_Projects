// ============================================================
// ATLAS PRIME LOGISTICS — Main JS
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── SIDEBAR TOGGLE ──
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ── TABS ──
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('[data-tabs]') || btn.parentElement.parentElement;
            const target = btn.dataset.tab;
            group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => {
                if (group.contains(c) || c.id === target) c.classList.remove('active');
            });
            btn.classList.add('active');
            const content = document.getElementById(target);
            if (content) content.classList.add('active');
        });
    });

    // ── MODALS ──
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modal);
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('.modal-close, .modal-backdrop').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                el.closest('.modal-backdrop')?.classList.remove('open');
            }
        });
    });

    // ── AUTO-DISMISS FLASH ──
    document.querySelectorAll('.flash').forEach(flash => {
        setTimeout(() => { if (flash && flash.parentElement) flash.remove(); }, 5000);
    });

    // ── CONFIRM DELETE ──
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // ── SEARCH FILTER TABLE ──
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase();
            document.querySelectorAll('.data-table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ── TYPE EXPEDITION TOGGLE ──
    const typeInputs = document.querySelectorAll('input[name="type_expedition"]');
    const voyageSection = document.getElementById('voyage-section');
    if (typeInputs.length && voyageSection) {
        typeInputs.forEach(input => {
            input.addEventListener('change', () => {
                voyageSection.style.display = input.value === 'accompagne' ? 'block' : 'none';
                document.getElementById('voyage_id').required = input.value === 'accompagne';
            });
        });
    }

    // ── CALCUL MONTANT TOTAL ──
    const tarifInput  = document.getElementById('tarif');
    const remiseInput = document.getElementById('remise');
    const totalEl     = document.getElementById('montant_total_display');
    const totalInput  = document.getElementById('montant_total');
    function calcTotal() {
        if (!tarifInput) return;
        const t = parseFloat(tarifInput.value) || 0;
        const r = parseFloat(remiseInput?.value) || 0;
        const total = Math.max(0, t - r);
        if (totalInput)  totalInput.value = total;
        if (totalEl)     totalEl.textContent = total.toLocaleString('fr-FR') + ' FCFA';
    }
    tarifInput?.addEventListener('input', calcTotal);
    remiseInput?.addEventListener('input', calcTotal);
    calcTotal();

    // ── PRINT ──
    document.querySelectorAll('.btn-print').forEach(btn => {
        btn.addEventListener('click', () => window.print());
    });

    // ── NUMBER FORMAT INPUT ──
    document.querySelectorAll('input[data-format="number"]').forEach(inp => {
        inp.addEventListener('blur', () => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) inp.value = v.toFixed(2);
        });
    });
});

// AJAX helper
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    });
    return res.json();
}

function showToast(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `flash flash-${type}`;
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;min-width:280px;animation:modalIn 0.2s ease';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${msg} <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
