// NexRide - Application JavaScript
(function() {
    'use strict';

    const App = {
        init() {
            this.setupOfflineIndicator();
            this.setupFormValidation();
            this.setupConfirmDialogs();
            this.setupAutoFill();
            console.log('NexRide initialized');
        },

        setupOfflineIndicator() {
            const indicator = document.getElementById('offline-indicator');
            if (!indicator) return;

            function updateStatus() {
                if (navigator.onLine) {
                    indicator.textContent = 'En ligne';
                    indicator.classList.remove('offline');
                } else {
                    indicator.textContent = 'Hors ligne';
                    indicator.classList.add('offline');
                }
            }

            window.addEventListener('online', updateStatus);
            window.addEventListener('offline', updateStatus);
            updateStatus();
        },

        setupFormValidation() {
            document.querySelectorAll('form[data-validate]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    form.querySelectorAll('[required]').forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('error');
                            valid = false;
                        } else {
                            field.classList.remove('error');
                        }
                    });
                    if (!valid) {
                        e.preventDefault();
                        const firstError = form.querySelector('.error');
                        if (firstError) firstError.focus();
                    }
                });
            });
        },

        setupConfirmDialogs() {
            document.querySelectorAll('[data-confirm]').forEach(el => {
                el.addEventListener('click', function(e) {
                    if (!confirm(this.dataset.confirm || 'Etes-vous sur?')) {
                        e.preventDefault();
                    }
                });
            });
        },

        setupAutoFill() {
            const montantInput = document.getElementById('montant');
            const remiseInput = document.getElementById('remise');
            if (montantInput) {
                const calcTotal = () => {
                    const montant = parseFloat(montantInput.value) || 0;
                    const remise = parseFloat(remiseInput?.value) || 0;
                    const taxe = Math.round(montant * 0.18);
                    const total = montant + taxe - remise;
                    const totalEl = document.getElementById('total-preview');
                    if (totalEl) {
                        totalEl.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
                    }
                };
                montantInput.addEventListener('input', calcTotal);
                if (remiseInput) remiseInput.addEventListener('input', calcTotal);
            }
        },

        formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('fr-FR');
        }
    };

    document.addEventListener('DOMContentLoaded', () => App.init());
    window.NexRideApp = App;
})();