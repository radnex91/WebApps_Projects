// js/app.js — TransportManager
'use strict';

// ── Sidebar ────────────────────────────────────────────────
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    if (!s) return;
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        s.classList.toggle('open');
        // Create/remove overlay on mobile
        let overlay = document.getElementById('sb-overlay');
        if (s.classList.contains('open')) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sb-overlay';
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99;';
                overlay.addEventListener('click', () => toggleSidebar());
                document.body.appendChild(overlay);
            }
        } else if (overlay) {
            overlay.remove();
        }
    } else {
        s.classList.toggle('collapsed');
        localStorage.setItem('sb_collapsed', s.classList.contains('collapsed') ? '1' : '0');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const s = document.getElementById('sidebar');
    if (s && localStorage.getItem('sb_collapsed') === '1') s.classList.add('collapsed');
});

// ── Modals ─────────────────────────────────────────────────
// Modal auto-fermeture sur Escape uniquement (pas sur click dehors)
function openModal(id)  { const m=document.getElementById(id); if(m){m.classList.add('open');} }
function closeModal(id) { const m=document.getElementById(id); if(m){m.classList.remove('open');} }
document.addEventListener('keydown', e => {
    if (e.key==='Escape') document.querySelectorAll('.modal-over.open').forEach(m=>m.classList.remove('open'));
});

// ── Tabs ───────────────────────────────────────────────────
function showTab(tabId, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display='none');
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    const pane = document.getElementById('tab-'+tabId);
    if (pane) pane.style.display='';
    if (el) el.classList.add('active');
}

// ── Confirm ────────────────────────────────────────────────
function confirmAction(msg, url) {
    if (confirm(msg || 'Confirmer cette action ?')) window.location = url;
}

// ── Print ──────────────────────────────────────────────────
function printDiv(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Impression</title>');
    w.document.write('<link rel="stylesheet" href="' + (window.BASE_URL||'') + 'css/app.css">');
    w.document.write('</head><body style="padding:20px;">' + el.innerHTML + '</body></html>');
    w.document.close();
    setTimeout(() => { w.print(); }, 600);
}

// ── Search / Filter ────────────────────────────────────────
function filterTable(inputId, tableId) {
    const q = document.getElementById(inputId)?.value?.toLowerCase() || '';
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(r => { r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none'; });
}

// ── Column Filters ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Auto-add filter row to every table (unless data-no-filter)
    document.querySelectorAll('table:not([data-no-filter])').forEach(table => {
        const thead = table.querySelector('thead');
        if (!thead) return;
        const headerRow = thead.querySelector('tr');
        if (!headerRow) return;
        const ths = headerRow.querySelectorAll('th');
        if (ths.length === 0) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Skip tables with no data rows
        const bodyRows = tbody.querySelectorAll('tr');
        const hasDataRows = Array.from(bodyRows).some(r => !r.querySelector('.t-empty'));
        if (!hasDataRows && bodyRows.length <= 1) return;

        // Build filter row
        const filterRow = document.createElement('tr');
        filterRow.classList.add('filter-row');

        // Status keywords for auto-detect
        const statusKeywords = ['actif','inactif','vendu','annulé','annule','utilisé','utilise','réservé','reserve','programmé','programme','en_cours','arrivé','arrive','panne','maintenance','hors_service','validé','valide','en_route','clôturé','cloture','en_attente','confirmé','confirme','rejeté','rejete','payé','paye','approuvé','approuve','transmis','saisi','généré','genere'];

        ths.forEach((th, i) => {
            const cell = document.createElement('th');
            const headerText = th.textContent.trim().toLowerCase();

            // Skip Actions column
            if (headerText === 'actions' || headerText === 'action') {
                filterRow.appendChild(cell);
                return;
            }

            // Collect unique values from this column
            const uniqueVals = new Set();
            bodyRows.forEach(r => {
                const td = r.querySelectorAll('td')[i];
                if (td) {
                    const val = td.textContent.trim();
                    if (val && val !== '—') uniqueVals.add(val);
                }
            });

            // Check if this is a status column
            let isStatusCol = headerText === 'statut' || headerText === 'status' || headerText === 'état' || headerText === 'etat';

            // Auto-detect: if all unique values match status keywords
            if (!isStatusCol && uniqueVals.size > 0 && uniqueVals.size <= 10) {
                const lowerVals = [...uniqueVals].map(v => v.toLowerCase());
                const matchCount = lowerVals.filter(v => statusKeywords.some(k => v === k || v.includes(k))).length;
                if (matchCount === lowerVals.length) isStatusCol = true;
            }

            if (isStatusCol && uniqueVals.size > 0) {
                const sel = document.createElement('select');
                sel.innerHTML = '<option value="">Tous</option>';
                [...uniqueVals].sort().forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.toLowerCase();
                    opt.textContent = v;
                    sel.appendChild(opt);
                });
                sel.addEventListener('change', () => applyColFilters(table));
                cell.appendChild(sel);
            } else {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.placeholder = 'Filtrer…';
                inp.addEventListener('input', () => applyColFilters(table));
                cell.appendChild(inp);
            }

            filterRow.appendChild(cell);
        });

        thead.appendChild(filterRow);
    });
});

function applyColFilters(table) {
    const filterRow = table.querySelector('thead tr.filter-row');
    if (!filterRow) return;
    const filters = [];
    filterRow.querySelectorAll('th').forEach((th, i) => {
        const input = th.querySelector('input');
        const select = th.querySelector('select');
        if (input) filters.push({ index: i, value: input.value.toLowerCase(), type: 'text' });
        else if (select && select.value) filters.push({ index: i, value: select.value.toLowerCase(), type: 'select' });
        else filters.push({ index: i, value: '', type: 'none' });
    });

    const hasFilter = filters.some(f => f.value);
    const rows = table.querySelectorAll('tbody tr');
    let visible = 0;
    rows.forEach(row => {
        if (row.querySelector('.t-empty')) return;
        let show = true;
        filters.forEach(f => {
            if (!f.value || !show) return;
            const tds = row.querySelectorAll('td');
            const td = tds[f.index];
            if (!td) { show = false; return; }
            const text = td.textContent.trim().toLowerCase();
            if (!text.includes(f.value)) show = false;
        });
        if (show) { row.classList.remove('filtered-out'); visible++; }
        else row.classList.add('filtered-out');
    });

    // Show/hide empty row based on filter results
    const emptyRow = table.querySelector('tbody tr.t-empty');
    if (emptyRow) {
        if (hasFilter && visible === 0) emptyRow.style.display = '';
        else if (hasFilter) emptyRow.style.display = 'none';
    }
}

// ── Number format ──────────────────────────────────────────
function formatMoney(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n));
}

// ── Toast Notifications ───────────────────────────────────
const TOAST_ICONS = {success:'fa-check-circle',danger:'fa-times-circle',warning:'fa-exclamation-triangle',info:'fa-info-circle'};
function showToast(type, msg, duration) {
    duration = duration || 5000;
    const container = document.getElementById('toast-container');
    if (!container) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.innerHTML = '<i class="fas ' + (TOAST_ICONS[type]||TOAST_ICONS.info) + '"></i><div class="toast-body"><div class="toast-msg">' + msg + '</div></div><button class="toast-close" onclick="dismissToast(this.parentElement)">&times;</button>';
    t.addEventListener('click', function(e){ if(e.target.classList.contains('toast-close')) return; dismissToast(t); });
    container.prepend(t);
    if (duration > 0) setTimeout(function(){ dismissToast(t); }, duration);
}
function dismissToast(t) {
    if (!t || t.classList.contains('removing')) return;
    t.classList.add('removing');
    setTimeout(function(){ t.remove(); }, 300);
}

