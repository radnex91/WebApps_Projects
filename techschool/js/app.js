// js/app.js — TechSchool
'use strict';

// Flash auto-dismiss
document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('flash-alert');
    if (f) setTimeout(() => { f.style.opacity='0'; f.style.transition='opacity .5s'; setTimeout(()=>f.remove(),500); }, 4000);

    // Sidebar toggle state
    const sidebar = document.getElementById('sidebar');
    const collapsed = localStorage.getItem('sidebar_collapsed');
    if (collapsed === '1' && sidebar) sidebar.classList.add('collapsed');

    const toggleBtn = document.getElementById('toggle-btn');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }
});

// Modal helpers
function openModal(id)  { const m = document.getElementById(id); if(m) m.classList.add('open'); }
function closeModal(id) { const m = document.getElementById(id); if(m) m.classList.remove('open'); }

// Close modal on backdrop click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Escape key closes modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// Confirm delete links
document.querySelectorAll('[data-confirm]')?.forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) e.preventDefault();
    });
});
