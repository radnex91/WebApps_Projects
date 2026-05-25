// ButcheryPOS - POS Cart & Sale Management
// Les textes sont injectés depuis le template PHP via l'objet POS.i18n
const POS = {
    // Textes internationalisés (remplis par le template)
    i18n: {
        cart_empty: 'Le panier est vide',
        no_terminal: 'Aucune session de caisse ouverte',
        sale_completed: 'Vente terminée avec succès',
        sale_failed: 'Échec de la vente',
        sale_on_hold: 'Vente mise en attente',
        terminal_opened: 'Caisse ouverte',
        terminal_closed: 'Caisse fermée',
        items_label: 'articles',
    },

    cart: {
        items: [],
        customer_id: null,
        discount_amount: 0,
        pos_session_id: null,
        pos_session_token: null,
    },
    heldSales: [],
    selectedPaymentMethod: 'cash',
    amountTendered: 0,

    // ---- Cart Management ----

    addItem(product) {
        const existing = this.cart.items.find(i => i.product_id === product.id);
        if (existing) {
            existing.quantity += 1;
            existing.subtotal = existing.quantity * existing.unit_price;
        } else {
            this.cart.items.push({
                product_id: product.id,
                name: product.name,
                sku: product.sku,
                unit_price: parseFloat(product.sale_price),
                unit_abbr: product.unit_abbr || '',
                quantity: 1,
                subtotal: parseFloat(product.sale_price),
                is_weighted: product.unit_type === 'weight',
            });
        }
        this.renderCart();
    },

    removeItem(index) {
        this.cart.items.splice(index, 1);
        this.renderCart();
    },

    updateQuantity(index, qty) {
        qty = parseFloat(qty);
        if (isNaN(qty) || qty <= 0) {
            this.removeItem(index);
            return;
        }
        const item = this.cart.items[index];
        item.quantity = qty;
        item.subtotal = item.quantity * item.unit_price;
        this.renderCart();
    },

    clearCart() {
        this.cart.items = [];
        this.cart.discount_amount = 0;
        this.cart.customer_id = null;
        this.renderCart();
    },

    getSubtotal() {
        return this.cart.items.reduce((sum, i) => sum + i.subtotal, 0);
    },

    getTotal() {
        return this.getSubtotal() - this.cart.discount_amount;
    },

    // ---- Cart Rendering ----

    renderCart() {
        const container = document.getElementById('cart-items');
        if (!container) return;

        if (this.cart.items.length === 0) {
            const emptyText = document.getElementById('cart-empty-text')?.textContent || this.i18n.cart_empty;
            container.innerHTML = `<div class="text-center text-muted py-5"><i class="bi bi-cart3" style="font-size:48px"></i><p class="mt-2">${emptyText}</p></div>`;
        } else {
            container.innerHTML = this.cart.items.map((item, i) => `
                <div class="cart-item">
                    <div class="item-info">
                        <div class="item-name">${this.escHtml(item.name)}</div>
                        <div class="item-price">${this.formatMoney(item.unit_price)}/${item.unit_abbr}</div>
                    </div>
                    <input type="number" class="form-control form-control-sm item-qty" value="${item.quantity}"
                           step="0.001" min="0.001" onchange="POS.updateQuantity(${i}, this.value)">
                    <div class="item-subtotal">${this.formatMoney(item.subtotal)}</div>
                    <i class="bi bi-x-circle item-remove" onclick="POS.removeItem(${i})"></i>
                </div>
            `).join('');
        }

        // Update totals
        const subtotal = this.getSubtotal();
        const total = this.getTotal();
        document.getElementById('cart-subtotal').textContent = this.formatMoney(subtotal);
        document.getElementById('cart-total').textContent = this.formatMoney(total);
        document.getElementById('cart-item-count').textContent = this.cart.items.length;
    },

    // ---- Payment ----

    selectPaymentMethod(method) {
        this.selectedPaymentMethod = method;
        document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`[data-method="${method}"]`)?.classList.add('active');

        // Show/hide sections
        document.getElementById('cash-tender-section')?.classList.toggle('show', method === 'cash');
        document.getElementById('mobile-money-section')?.classList.toggle('show', ['orange_money','mtn_momo','wave'].includes(method));
    },

    setAmountTendered(amount) {
        this.amountTendered = parseFloat(amount) || 0;
        const change = this.amountTendered - this.getTotal();
        const changeEl = document.getElementById('change-due');
        if (changeEl) {
            changeEl.textContent = this.formatMoney(Math.max(0, change));
            changeEl.className = change >= 0 ? 'text-success' : 'text-danger';
        }
    },

    // ---- Complete Sale ----

    async completeSale() {
        if (this.cart.items.length === 0) {
            const emptyText = document.getElementById('cart-empty-text')?.textContent || this.i18n.cart_empty;
            ButcheryPOS.toast(emptyText, 'warning');
            return;
        }

        if (!this.cart.pos_session_id) {
            ButcheryPOS.toast(this.i18n.no_terminal, 'warning');
            return;
        }

        const saleData = {
            pos_session_id: this.cart.pos_session_id,
            customer_id: this.cart.customer_id,
            items: this.cart.items.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity,
                unit_price: i.unit_price,
            })),
            payments: [{
                method: this.selectedPaymentMethod,
                amount: this.getTotal(),
            }],
            discount_amount: this.cart.discount_amount,
        };

        try {
            const result = await ButcheryPOS.ajax('/ButcheryPOS/public/api/index.php?route=api/sales', {
                method: 'POST',
                body: saleData,
            });

            if (result.success) {
                ButcheryPOS.toast(result.sale_reference || this.i18n.sale_completed, 'success');
                this.showReceipt(result.sale_id);
                this.clearCart();
            } else {
                ButcheryPOS.toast(result.error || this.i18n.sale_failed, 'danger');
            }
        } catch (e) {
            ButcheryPOS.toast(e.message, 'danger');
        }
    },

    // ---- Receipt ----

    showReceipt(saleId) {
        window.open(`/ButcheryPOS/?page=sales&action=receipt&id=${saleId}`, '_blank', 'width=320,height=600');
    },

    // ---- Hold/Resume Sale ----

    holdSale() {
        if (this.cart.items.length === 0) return;
        this.heldSales.push({
            items: [...this.cart.items],
            customer_id: this.cart.customer_id,
            discount_amount: this.cart.discount_amount,
            time: new Date().toLocaleTimeString(),
        });
        this.clearCart();
        this.renderHeldSales();
        ButcheryPOS.toast(this.i18n.sale_on_hold, 'info');
    },

    resumeSale(index) {
        const held = this.heldSales[index];
        if (!held) return;
        this.cart.items = held.items;
        this.cart.customer_id = held.customer_id;
        this.cart.discount_amount = held.discount_amount;
        this.heldSales.splice(index, 1);
        this.renderCart();
        this.renderHeldSales();
    },

    renderHeldSales() {
        const container = document.getElementById('held-sales-list');
        if (!container) return;
        container.innerHTML = this.heldSales.map((h, i) => `
            <div class="held-sale-item" onclick="POS.resumeSale(${i})">
                <span><i class="bi bi-pause-circle"></i> ${h.items.length} ${this.i18n.items_label} - ${h.time}</span>
                <span class="badge bg-secondary">${this.formatMoney(h.items.reduce((s,it)=>s+it.subtotal,0))}</span>
            </div>
        `).join('');
    },

    // ---- POS Session ----

    async openTerminal() {
        const terminalName = document.getElementById('terminal-name')?.value || 'Terminal-1';
        const openingCash = parseFloat(document.getElementById('opening-cash')?.value || 0);

        try {
            const result = await ButcheryPOS.ajax('/ButcheryPOS/public/api/index.php?route=api/pos/session', {
                method: 'POST',
                body: { terminal_name: terminalName, opening_cash: openingCash },
            });

            if (result.session) {
                this.cart.pos_session_id = result.session.id;
                this.cart.pos_session_token = result.session.session_token;
                document.getElementById('terminal-modal')?.classList.remove('show');
                document.getElementById('pos-session-status').textContent = terminalName;
                ButcheryPOS.toast(this.i18n.terminal_opened, 'success');
            }
        } catch (e) {
            ButcheryPOS.toast(e.message, 'danger');
        }
    },

    async closeTerminal() {
        if (!this.cart.pos_session_id) return;
        const closingCash = parseFloat(document.getElementById('closing-cash')?.value || 0);

        try {
            await ButcheryPOS.ajax(`/ButcheryPOS/public/api/index.php?route=api/pos/session/${this.cart.pos_session_id}/close`, {
                method: 'PUT',
                body: { closing_cash: closingCash },
            });

            this.cart.pos_session_id = null;
            this.cart.pos_session_token = null;
            ButcheryPOS.toast(this.i18n.terminal_closed, 'success');
        } catch (e) {
            ButcheryPOS.toast(e.message, 'danger');
        }
    },

    // ---- Helpers ----

    formatMoney(amount) {
        const currency = document.getElementById('app-currency')?.textContent || 'XAF';
        return new Intl.NumberFormat('fr-FR').format(Math.round(amount)) + ' ' + currency;
    },

    escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    // ---- Initialize ----

    async init() {
        // Injecter les traductions depuis le template
        const i18nEl = document.getElementById('pos-i18n');
        if (i18nEl) {
            try {
                Object.assign(this.i18n, JSON.parse(i18nEl.textContent));
            } catch(e) {}
        }

        // Vérifier si une session de caisse est ouverte
        try {
            const result = await ButcheryPOS.ajax('/ButcheryPOS/public/api/index.php?route=api/pos/session');
            if (result.session) {
                this.cart.pos_session_id = result.session.id;
                this.cart.pos_session_token = result.session.session_token;
                document.getElementById('pos-session-status').textContent = result.session.terminal_name;
            } else {
                const modal = document.getElementById('terminal-modal');
                if (modal) modal.classList.add('show');
            }
        } catch (e) {
            // Géré ailleurs
        }

        // Démarrer la balance si activée
        if (document.getElementById('scale-weight')) {
            ScaleReader.start();
        }

        // Charger les produits
        this.loadProducts();

        this.renderCart();
    },

    async loadProducts(categoryId = null) {
        try {
            let url = '/ButcheryPOS/public/api/index.php?route=api/products';
            if (categoryId) url += '&category_id=' + categoryId;
            const products = await ButcheryPOS.ajax(url);
            this.renderProducts(products);
        } catch (e) {
            // Les produits resteront vides
        }
    },

    renderProducts(products) {
        const grid = document.getElementById('product-grid');
        if (!grid) return;

        grid.innerHTML = products.map(p => `
            <div class="pos-product-card ${parseFloat(p.quantity_in_stock) <= 0 ? 'out-of-stock' : ''}"
                 onclick="${parseFloat(p.quantity_in_stock) > 0 ? `POS.addItem(${JSON.stringify(p).replace(/"/g, '&quot;')})` : ''}">
                <div class="product-name">${this.escHtml(p.name)}</div>
                <div class="product-price">${this.formatMoney(p.sale_price)}</div>
                <div class="product-stock">${p.quantity_in_stock} ${p.unit_abbr || ''}</div>
            </div>
        `).join('');
    }
};

// Initialiser le POS quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('pos-page')) {
        POS.init();
    }
});