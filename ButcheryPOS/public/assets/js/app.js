// ButcheryPOS - Global JavaScript

const ButcheryPOS = {
    // CSRF token for AJAX requests
    csrfToken: null,

    init() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.setupToasts();
        this.setupSidebarToggle();
    },

    // AJAX helper with CSRF
    async ajax(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
            },
            credentials: 'same-origin',
        };

        // Merge headers
        if (options.headers) {
            options.headers = { ...defaults.headers, ...options.headers };
        }

        const config = { ...defaults, ...options };

        // Stringify body if object
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            return data;
        } catch (error) {
            this.toast(error.message, 'danger');
            throw error;
        }
    },

    // Toast notifications
    toast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const icons = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill',
        };

        const toast = document.createElement('div');
        toast.className = `alert-flash alert alert-${type} alert-dismissible fade show d-flex align-items-center`;
        toast.innerHTML = `
            <i class="bi ${icons[type] || icons.info} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close btn-close-sm ms-auto" data-bs-dismiss="alert"></button>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    setupToasts() {
        if (!document.getElementById('toast-container')) {
            const div = document.createElement('div');
            div.id = 'toast-container';
            div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
            document.body.appendChild(div);
        }
    },

    // Mobile sidebar toggle
    setupSidebarToggle() {
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.app-sidebar');
        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
        }
    },

    // Confirm dialog
    confirm(message) {
        return window.confirm(message);
    },

    // Format currency
    formatMoney(amount, currency = 'XAF') {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' ' + currency;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    ButcheryPOS.init();
});