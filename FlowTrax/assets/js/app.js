// DEX Transport App - Main JavaScript

document.addEventListener('DOMContentLoaded', () => {

    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        });

        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Modal system
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el || el.classList.contains('modal-close')) {
                const modal = el.closest('.modal-overlay');
                if (modal) modal.classList.remove('active');
            }
        });
    });

    // Confirm deletes
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.dataset.confirm || 'Confirmer la suppression ?')) {
                e.preventDefault();
            }
        });
    });

    // Search filter for tables
    document.querySelectorAll('.table-search').forEach(input => {
        input.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const table = this.closest('.card')?.querySelector('table tbody');
            if (!table) return;
            table.querySelectorAll('tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // Select all checkboxes
    document.querySelectorAll('[data-select-all]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const target = document.querySelectorAll(this.dataset.selectAll);
            target.forEach(cb => cb.checked = this.checked);
        });
    });

    // Calculate total passengers
    const etapeInputs = document.querySelectorAll('.etape-passagers');
    const totalField = document.getElementById('total_passagers');
    const tauxField = document.getElementById('taux_remplissage');
    const nbPlacesField = document.getElementById('nb_places_display');

    function recalcTotal() {
        if (!etapeInputs.length) return;
        let total = 0;
        etapeInputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        if (totalField) totalField.value = total;

        if (tauxField && nbPlacesField) {
            const places = parseInt(nbPlacesField.value) || parseInt(nbPlacesField.dataset.places) || 0;
            if (places > 0) {
                const taux = (total / places) * 100;
                tauxField.value = taux.toFixed(1);
            }
        }
    }

    etapeInputs.forEach(input => {
        input.addEventListener('input', recalcTotal);
    });

    // Vehicle selection auto-fill
    const vehicleSelect = document.getElementById('vehicule_id');
    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const places = selected.dataset.places || 0;
            const marque = selected.dataset.marque || '';
            const proprietaire = selected.dataset.proprietaire || '';

            const placesField = document.getElementById('nb_places');
            const marqueField = document.getElementById('marque_display');
            const proprioField = document.getElementById('proprietaire_display');

            if (placesField) {
                placesField.value = places;
                placesField.dataset.places = places;
            }
            if (marqueField) marqueField.value = marque;
            if (proprioField) proprioField.value = proprietaire;

            // Recalc fill rate
            if (nbPlacesField) nbPlacesField.dataset.places = places;
            recalcTotal();
        });
    }

});

// Toast notification system
function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    if (!container) {
        const div = document.createElement('div');
        div.className = 'toast-container';
        document.body.appendChild(div);
    }

    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `${icons[type] || 'ℹ️'} ${message}`;
    document.querySelector('.toast-container').appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
