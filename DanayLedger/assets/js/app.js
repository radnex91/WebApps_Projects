// DanayLedger v2 - Application JavaScript

document.addEventListener('DOMContentLoaded', function() {

    // --- Sidebar Toggle ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // --- Auto-hide alerts ---
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // --- Confirm Delete ---
    document.querySelectorAll('[data-confirm]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir continuer ?';
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // --- Number formatting ---
    document.querySelectorAll('[data-format-money]').forEach(function(el) {
        const value = parseFloat(el.textContent.replace(/[^\d.-]/g, ''));
        if (!isNaN(value)) {
            el.textContent = new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
        }
    });

    // --- Toggle select all checkboxes ---
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // --- Date range filter ---
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            dateTo.setAttribute('min', this.value);
        });
        dateTo.addEventListener('change', function() {
            dateFrom.setAttribute('max', this.value);
        });
    }

    // --- Print ---
    document.querySelectorAll('[data-print]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            window.print();
        });
    });

    // --- Export ---
    document.querySelectorAll('[data-export-csv]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tableId = this.getAttribute('data-export-csv');
            const table = document.getElementById(tableId);
            if (!table) return;
            let csv = '';
            const rows = table.querySelectorAll('tr');
            rows.forEach(function(row) {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach(function(col) {
                    rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                });
                csv += rowData.join(';') + '\n';
            });
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = this.getAttribute('data-filename') || 'export.csv';
            link.click();
        });
    });

    // --- Chart.js defaults ---
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Segoe UI', system-ui, -apple-system, sans-serif";
        Chart.defaults.color = '#6c757d';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 20;
    }

    // --- Tooltip init ---
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(el) {
        new bootstrap.Tooltip(el);
    });

    // --- Form validation ---
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            form.querySelectorAll('[required]').forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    valid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            if (!valid) {
                e.preventDefault();
            }
        });
    });

    // --- Animate numbers ---
    function animateValue(el, start, end, duration) {
        const range = end - start;
        const startTime = performance.now();
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(start + range * progress);
            el.textContent = new Intl.NumberFormat('fr-FR').format(current);
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    document.querySelectorAll('[data-animate]').forEach(function(el) {
        const endValue = parseInt(el.getAttribute('data-animate'));
        if (!isNaN(endValue)) {
            animateValue(el, 0, endValue, 1000);
        }
    });
});