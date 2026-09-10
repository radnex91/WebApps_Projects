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
            overlay.setAttribute('aria-hidden', sidebar.classList.contains('show') ? 'false' : 'true');
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        });
        // Escape key dismisses sidebar on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
                sidebarToggle.focus();
            }
        });
    }

    // --- Auto-hide alerts ---
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // --- Confirm (Modal personnalisé au lieu de confirm() navigateur) ---
    let confirmCallback = null;
    const confirmModal = document.getElementById('confirmModal');
    const confirmBsModal = confirmModal ? new bootstrap.Modal(confirmModal) : null;
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmOk = document.getElementById('confirmOk');
    const confirmCancel = document.getElementById('confirmCancel');
    const confirmBody = confirmModal ? confirmModal.querySelector('.confirm-modal-body') : null;

    if (confirmOk) {
        confirmOk.addEventListener('click', function () {
            if (confirmCallback) {
                confirmCallback();
                confirmCallback = null;
            }
            if (confirmBsModal) confirmBsModal.hide();
        });
    }

    if (confirmModal) {
        confirmModal.addEventListener('hidden.bs.modal', function () {
            confirmCallback = null;
            if (confirmBody) confirmBody.classList.remove('confirm-warning');
        });
    }

    // --- Focus trap for ALL modals ---
    function createFocusTrap(modalEl) {
        function trapHandler(e) {
            if (e.key !== 'Tab') return;
            var focusable = modalEl.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        }
        return trapHandler;
    }

    document.querySelectorAll('.modal').forEach(function(modal) {
        var handler = null;
        modal.addEventListener('shown.bs.modal', function () {
            var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable.length) focusable[0].focus();
            handler = createFocusTrap(modal);
            modal.addEventListener('keydown', handler);
        });
        modal.addEventListener('hidden.bs.modal', function () {
            if (handler) {
                modal.removeEventListener('keydown', handler);
                handler = null;
            }
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir continuer ?';
            const isDelete = message.toLowerCase().includes('supprimer') || this.closest('form')?.querySelector('[name="action"][value="delete"]');
            const isRestore = message.toLowerCase().includes('restauration') || message.toLowerCase().includes('remplacera');

            if (confirmTitle) {
                confirmTitle.textContent = isDelete ? 'Suppression' : (isRestore ? '⚠️ Attention' : 'Confirmation');
            }
            if (confirmMessage) confirmMessage.textContent = message;
            if (confirmBody) {
                confirmBody.classList.toggle('confirm-warning', isRestore);
            }
            if (confirmOk) {
                confirmOk.textContent = isDelete ? 'Supprimer' : 'Confirmer';
                confirmOk.classList.toggle('btn-danger', isDelete || isRestore);
                confirmOk.classList.toggle('btn-warning', isRestore && !isDelete);
            }

            const form = this.closest('form');
            confirmCallback = function () {
                if (form) {
                    // Nettoyage des champs montant formatés avant soumission
                    form.querySelectorAll('[data-amount-formatted]').forEach(function(inp) {
                        inp.value = inp.value.replace(/[\s\u00A0\u2000-\u200A\u202F\u205F\u3000]/g, '');
                    });
                    form.submit();
                }
            };

            if (confirmBsModal) confirmBsModal.show();
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