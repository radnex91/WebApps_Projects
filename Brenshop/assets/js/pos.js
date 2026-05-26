/* ═══════════════════════════════════════════════════════════
   POS — AFRO-BRUTAL JS
   Brenshop Point de Vente
   ═══════════════════════════════════════════════════════════ */

const POS = (() => {
  /* ── STATE ── */
  let cart = {};
  let currentView = 'list';
  let currentCategory = 0;
  let receiptModal = null;
  let scanKeystrokes = [];
  let scanTimer = null;
  let dailyStatsInterval = null;

  /* ── HELPERS ── */
  function formatMoney(v) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(v)) + ' FCFA';
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function msgStyle(type) {
    if (type === 'error') return 'background:rgba(220,38,38,0.08);color:var(--danger);padding:4px 8px;font-size:.82rem;font-weight:700;border:1px solid rgba(220,38,38,0.3);border-radius:6px';
    return '';
  }

  /* ── CART ── */
  function loadCart() {
    try { cart = JSON.parse(localStorage.getItem(POS_CONFIG.CART_KEY)) || {}; } catch(e) { cart = {}; }
    renderCart();
  }

  function saveCart() {
    localStorage.setItem(POS_CONFIG.CART_KEY, JSON.stringify(cart));
  }

  function clearCart() {
    if (!confirm('Vider le panier ?')) return;
    cart = {};
    saveCart();
    renderCart();
    document.getElementById('paidAmount').value = '';
    document.getElementById('searchInput').focus();
  }

  function addToCart(id, name, price, stock) {
    if (stock <= 0) { showToast(name + ' : rupture de stock', 'warning'); return; }
    if (cart[id]) {
      if (cart[id].qty >= stock) { showToast('Stock insuffisant pour ' + name, 'warning'); return; }
      cart[id].qty++;
    } else {
      cart[id] = { id, name, price: parseFloat(price), stock: parseInt(stock), qty: 1 };
    }
    flashProduct(id);
    saveCart();
    renderCart();
    bounceCartCount();
    document.getElementById('paidAmount').focus();
  }

  function changeQty(id, delta) {
    if (!cart[id]) return;
    const newQty = cart[id].qty + delta;
    if (newQty <= 0) { removeCartItem(id); return; }
    if (newQty > cart[id].stock) { showToast('Stock insuffisant', 'warning'); return; }
    cart[id].qty = newQty;
    saveCart();
    renderCart();
  }

  function removeCartItem(id) {
    const el = document.querySelector('.cart-item[data-id="' + id + '"]');
    if (el) {
      el.classList.add('removing');
      setTimeout(() => { delete cart[id]; saveCart(); renderCart(); }, 200);
    } else {
      delete cart[id];
      saveCart();
      renderCart();
    }
  }

  function flashProduct(id) {
    document.querySelectorAll('[data-id="' + id + '"]').forEach(el => {
      if (el.classList.contains('product-row') || el.classList.contains('product-tile')) {
        el.classList.add('flash');
        setTimeout(() => el.classList.remove('flash'), 350);
      }
    });
  }

  function bounceCartCount() {
    const el = document.getElementById('cartCount');
    el.classList.remove('bounce');
    void el.offsetWidth;
    el.classList.add('bounce');
  }

  /* ── CART RENDERING ── */
  function renderCart() {
    const items = Object.values(cart);
    const count = items.reduce((s, i) => s + i.qty, 0);
    document.getElementById('cartCount').textContent = count;

    const container = document.getElementById('cartItems');
    if (!items.length) {
      container.innerHTML = '<div class="cart-empty"><i class="bi bi-bag"></i>Panier vide</div>';
      document.getElementById('subtotalDisplay').textContent = '0 FCFA';
      document.getElementById('discountDisplay').textContent = '-0 FCFA';
      document.getElementById('taxDisplay').textContent = '0 FCFA';
      document.getElementById('totalDisplay').textContent = '0 FCFA';
      document.getElementById('validateBtn').disabled = true;
      return;
    }

    container.innerHTML = items.map(item => {
      const subtotal = item.price * item.qty;
      return '<div class="cart-item" data-id="' + item.id + '">' +
        '<div class="ci-top">' +
          '<div class="ci-name" title="' + escHtml(item.name) + '">' + escHtml(item.name) + '</div>' +
          '<div class="ci-remove" onclick="POS.removeItem(' + item.id + ')"><i class="bi bi-x-circle-fill"></i></div>' +
        '</div>' +
        '<div class="ci-bottom">' +
          '<div class="ci-price">' + formatMoney(item.price) + '</div>' +
          '<div class="ci-qty">' +
            '<button onclick="POS.changeQty(' + item.id + ', -1)">-</button>' +
            '<span>' + item.qty + '</span>' +
            '<button onclick="POS.changeQty(' + item.id + ', 1)">+</button>' +
          '</div>' +
          '<div class="ci-subtotal">' + formatMoney(subtotal) + '</div>' +
        '</div>' +
      '</div>';
    }).join('');

    const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
    const disc = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * disc / 100;
    const taxable = subtotal - discount;
    const tax = taxable * POS_CONFIG.TAX_RATE;
    const total = taxable + tax;

    document.getElementById('subtotalDisplay').textContent = formatMoney(subtotal);
    document.getElementById('discountDisplay').textContent = '-' + formatMoney(discount);
    document.getElementById('taxDisplay').textContent = formatMoney(tax);
    document.getElementById('totalDisplay').textContent = formatMoney(total);

    saveCart();
    calcChange();
  }

  /* ── PAYMENT CALC ── */
  function calcChange() {
    const subtotal = Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
    const disc = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * disc / 100;
    const taxable = subtotal - discount;
    const tax = taxable * POS_CONFIG.TAX_RATE;
    const total = taxable + tax;
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const change = paid - total;
    const el = document.getElementById('changeDisplay');

    if (paid <= 0) {
      el.textContent = 'Monnaie: 0 FCFA';
      el.className = 'change-display';
    } else if (change >= 0) {
      el.textContent = 'Monnaie: +' + formatMoney(change);
      el.className = 'change-display positive';
    } else {
      el.textContent = 'Manque: ' + formatMoney(Math.abs(change));
      el.className = 'change-display negative';
    }
    updateValidateBtn();
  }

  function updateValidateBtn() {
    const items = Object.values(cart);
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const method = document.getElementById('paymentMethod').value;
    const btn = document.getElementById('validateBtn');

    if (!items.length) { btn.disabled = true; return; }
    if (method === 'credit') {
      const cc = document.getElementById('creditClientSelect').value;
      btn.disabled = !cc;
    } else {
      btn.disabled = paid <= 0;
    }
  }

  function onPaymentMethodChange() {
    const method = document.getElementById('paymentMethod').value;
    document.getElementById('paidRow').classList.toggle('d-none', method === 'credit');
    document.getElementById('creditClientRow').classList.toggle('d-none', method !== 'credit');
    const mobileGroup = document.getElementById('mobileProvider').closest('.payment-row');
    if (mobileGroup) mobileGroup.classList.toggle('d-none', method !== 'mobile_money');
    updateValidateBtn();
  }

  /* ── SALE PROCESSING ── */
  function processSale() {
    const items = Object.values(cart);
    if (!items.length) return;

    const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
    const disc = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * disc / 100;
    const taxable = subtotal - discount;
    const tax = taxable * POS_CONFIG.TAX_RATE;
    const total = taxable + tax;
    const paid = parseFloat(document.getElementById('paidAmount').value) || total;
    let paymentMethod = document.getElementById('paymentMethod').value;

    if (paymentMethod === 'mobile_money') {
      paymentMethod = document.getElementById('mobileProvider').value;
    }

    let customerId = document.getElementById('cartClientSelect').value || null;
    if (paymentMethod === 'credit') {
      customerId = document.getElementById('creditClientSelect').value;
    }

    const btn = document.getElementById('validateBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>En cours...';

    fetch(POS_CONFIG.BASE_URL + '/controllers/sale_controller.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        store_id: POS_CONFIG.STORE_ID,
        warehouse_id: POS_CONFIG.WAREHOUSE_ID,
        customer_id: customerId,
        subtotal: subtotal.toFixed(2),
        discount_amount: discount.toFixed(2),
        tax_amount: tax.toFixed(2),
        total_amount: total.toFixed(2),
        paid_amount: paid.toFixed(2),
        change_amount: Math.max(0, paid - total).toFixed(2),
        change_refunded: true,
        payment_method: paymentMethod,
        notes: '',
        items: items.map(i => ({
          product_id: i.id,
          product_name: i.name,
          barcode: '',
          quantity: i.qty,
          unit_price: i.price,
          cost_price: 0,
          discount_percent: disc,
          discount_amount: (i.price * i.qty * disc / 100).toFixed(2),
          total_price: (i.price * i.qty).toFixed(2)
        }))
      })
    })
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>VALIDER LA VENTE';
      btn.disabled = false;
      if (data.success) {
        cart = {};
        saveCart();
        renderCart();
        document.getElementById('paidAmount').value = '';
        showReceipt(data.data?.invoice || '', total, paid, paymentMethod);
        fetchDailyStats();
      } else {
        showToast(data.message || 'Erreur', 'danger');
      }
    })
    .catch(() => {
      btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>VALIDER LA VENTE';
      btn.disabled = false;
      showToast('Erreur reseau', 'danger');
    });
  }

  /* ── RECEIPT ── */
  function showReceipt(invoice, total, paid, method) {
    const now = new Date().toLocaleString('fr-FR');
    const change = Math.max(0, paid - total);
    const methodLabel = {cash:'Especes', mobile_money:'Mobile Money', orange_money:'Orange Money', momo:'MoMo', credit:'Credit'}[method] || method;

    document.getElementById('receiptModalBody').innerHTML =
      '<div class="receipt-wrapper">' +
        '<div class="receipt-header">' +
          '<div class="receipt-store-name">' + escHtml(POS_CONFIG.APP_NAME) + '</div>' +
          '<div style="font-size:.65rem">' + escHtml(POS_CONFIG.STORE_NAME) + '</div>' +
          '<div style="font-size:.65rem">' + now + '</div>' +
        '</div>' +
        '<div style="font-size:.7rem;margin-bottom:.4rem">Facture: <strong>' + escHtml(invoice) + '</strong></div>' +
        '<div class="receipt-divider"></div>' +
        '<div class="receipt-row receipt-total"><span>TOTAL</span><span>' + formatMoney(total) + '</span></div>' +
        '<div class="receipt-row" style="font-size:.7rem"><span>Recu</span><span>' + formatMoney(paid) + '</span></div>' +
        '<div class="receipt-row" style="font-size:.7rem"><span>Monnaie</span><span>' + formatMoney(change) + '</span></div>' +
        '<div class="receipt-divider"></div>' +
        '<div style="text-align:center;font-size:.65rem;margin-bottom:.3rem">Paiement: ' + methodLabel + '</div>' +
        '<div style="text-align:center;font-size:.65rem;margin-top:.4rem">' + escHtml(POS_CONFIG.RECEIPT_FOOTER) + '</div>' +
      '</div>';

    receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    receiptModal.show();
  }

  function closeReceipt() {
    if (receiptModal) { receiptModal.hide(); receiptModal = null; }
    document.getElementById('searchInput').focus();
  }

  /* ── BARCODE SCANNING ── */
  function handleScanKeydown(e) {
    const now = Date.now();
    scanKeystrokes.push(now);
    if (scanKeystrokes.length > 20) scanKeystrokes = scanKeystrokes.slice(-20);

    if (e.key === 'Enter') {
      e.preventDefault();
      const val = this.value.trim().toLowerCase();
      if (!val) { filterProducts('', currentCategory); return; }

      const match = document.querySelector('[data-ref="' + CSS.escape(val) + '"]');
      if (match && !match.classList.contains('out')) {
        addToCart(
          parseInt(match.dataset.id),
          match.dataset.name,
          parseFloat(match.dataset.price),
          parseInt(match.dataset.stock)
        );
        flashScanInput();
        this.value = '';
      } else {
        filterProducts(val, currentCategory);
      }
      return;
    }
  }

  function handleScanInput() {
    const input = this;
    clearTimeout(scanTimer);
    const val = input.value.trim().toLowerCase();

    /* Detect rapid keystrokes (< 50ms) = barcode scanner */
    const now = Date.now();
    if (scanKeystrokes.length >= 3) {
      const recent = scanKeystrokes.slice(-3);
      const avgGap = (recent[2] - recent[0]) / 2;
      if (avgGap < 50 && val.length > 3) {
        /* Scanner detected — try exact match */
        const match = document.querySelector('[data-ref="' + CSS.escape(val) + '"]');
        if (match && !match.classList.contains('out')) {
          addToCart(
            parseInt(match.dataset.id),
            match.dataset.name,
            parseFloat(match.dataset.price),
            parseInt(match.dataset.stock)
          );
          flashScanInput();
          input.value = '';
          scanKeystrokes = [];
          return;
        } else if (val.length > 5) {
          showToast('Code non trouve: ' + val, 'warning');
          input.value = '';
          scanKeystrokes = [];
          return;
        }
      }
    }

    scanTimer = setTimeout(() => filterProducts(val, currentCategory), 150);
  }

  function flashScanInput() {
    const input = document.getElementById('searchInput');
    input.classList.add('scan-flash');
    setTimeout(() => input.classList.remove('scan-flash'), 300);
  }

  /* ── FILTERING ── */
  function filterProducts(query, catId) {
    query = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.product-row');
    const tiles = document.querySelectorAll('.product-tile');
    const all = rows.length > 0 ? rows : tiles;

    let visible = 0;
    all.forEach(el => {
      const matchCat = !catId || el.dataset.cat == catId;
      const matchSearch = !query || el.dataset.search.includes(query);
      const show = matchCat && matchSearch;
      el.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (currentView === 'list') {
      document.getElementById('productsTable').style.display = visible === 0 ? 'none' : '';
    }
  }

  function filterCat(catId, btn) {
    currentCategory = parseInt(catId);
    document.querySelectorAll('.cat-chip').forEach(b => {
      b.classList.remove('active');
    });
    btn.classList.add('active');

    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    filterProducts(query, currentCategory);

    if (catId > 0) { setView('grid'); } else { setView('list'); }
  }

  function setView(view) {
    currentView = view;
    document.querySelectorAll('.view-toggle button').forEach(b => {
      b.classList.toggle('active', b.dataset.view === view);
    });
    document.getElementById('productsTable').classList.toggle('d-none', view !== 'list');
    document.getElementById('productsGrid').classList.toggle('d-none', view !== 'grid');
  }

  /* ── WAREHOUSE SWITCH ── */
  function switchWarehouse(whId) {
    window.location.href = POS_CONFIG.BASE_URL + '/controllers/auth.php?action=switch_warehouse&warehouse_id=' + whId;
  }

  /* ── DAILY STATS ── */
  function fetchDailyStats() {
    fetch(POS_CONFIG.BASE_URL + '/controllers/sale_controller.php?action=daily_stats&store_id=' + POS_CONFIG.STORE_ID)
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data) return;
        const d = data.data;
        const dashEl = document.getElementById('posDashboard');
        if (!dashEl) return;

        dashEl.innerHTML =
          '<div class="dash-card"><div class="dash-label">Ventes</div><div class="dash-value">' + (d.sales_count || '—') + '</div></div>' +
          '<div class="dash-card"><div class="dash-label">Revenu</div><div class="dash-value accent">' + (d.revenue ? formatMoney(d.revenue) : '—') + '</div></div>' +
          '<div class="dash-card"><div class="dash-label">Panier Moy</div><div class="dash-value">' + (d.avg_basket ? formatMoney(d.avg_basket) : '—') + '</div></div>' +
          '<div class="dash-card"><div class="dash-label">Articles</div><div class="dash-value">' + (d.items_sold || '—') + '</div></div>';
      })
      .catch(() => {});
  }

  /* ── CAISSE SESSION ── */
  let selectedCaisseId = 0;
  let selectedCaisseName = '';
  let caisseModal = null;
  let selectedOpType = 'deposit';
  let opsModalInstance = null;
  let closeModalInstance = null;
  let closeSessionId = null;
  let closeExpectedBalance = 0;
  let zReportInstance = null;

  function openCaisseSelection() {
    const el = document.getElementById('caisseSelectionModal');
    caisseModal = new bootstrap.Modal(el);
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=list_caisses')
      .then(r => r.json())
      .then(data => {
        const container = document.getElementById('caisseCardsContainer');
        if (!data.success || !data.data || !data.data.length) {
          container.innerHTML = '<div class="col-12 text-center py-3" style="color:var(--text-muted)"><i class="bi bi-exclamation-circle" style="font-size:1.5rem;opacity:.3;display:block;margin-bottom:.4rem"></i>Aucune caisse disponible.</div>';
        } else {
          container.innerHTML = data.data.map(c => {
            return '<div class="col-6"><div class="caisse-card" data-caisse-id="' + c.id + '" data-caisse-name="' + escHtml(c.name) + '" onclick="POS.selectCaisse(' + c.id + ', \'' + escHtml(c.name).replace(/'/g, "\\'") + '\')">' +
              '<div class="caisse-card-icon"><i class="bi bi-cash-register"></i></div>' +
              '<div style="font-weight:800;font-size:.85rem;color:var(--text)">' + escHtml(c.name) + '</div>' +
            '</div></div>';
          }).join('');
        }
      })
      .catch(() => {
        document.getElementById('caisseCardsContainer').innerHTML = '<div class="col-12 text-center py-3" style="color:var(--danger)">Erreur de chargement.</div>';
      });

    selectedCaisseId = 0;
    selectedCaisseName = '';
    document.getElementById('caisseBalanceSection').style.display = 'none';
    document.getElementById('caisseModalFooter').style.display = 'none';
    document.getElementById('openingBalance').value = '0';
    document.getElementById('openCaisseMsg').innerHTML = '';
    caisseModal.show();
  }

  function selectCaisse(id, name) {
    selectedCaisseId = id;
    selectedCaisseName = name;
    document.querySelectorAll('.caisse-card').forEach(card => {
      card.classList.toggle('selected', card.dataset.caisseId == id);
    });
    document.getElementById('caisseBalanceSection').style.display = '';
    document.getElementById('caisseModalFooter').style.display = '';
    document.getElementById('openCaisseBtn').innerHTML = '<i class="bi bi-unlock me-1"></i>Ouvrir « ' + escHtml(name) + ' »';
    document.getElementById('openingBalance').focus();
  }

  function resetCaisseSelection() {
    selectedCaisseId = 0;
    selectedCaisseName = '';
    document.getElementById('caisseBalanceSection').style.display = 'none';
    document.getElementById('caisseModalFooter').style.display = 'none';
    document.getElementById('openingBalance').value = '0';
    document.getElementById('openCaisseMsg').innerHTML = '';
    document.querySelectorAll('.caisse-card').forEach(card => card.classList.remove('selected'));
  }

  function openCaisse() {
    if (!selectedCaisseId) return;
    const openingBalance = document.getElementById('openingBalance').value;
    const msgEl = document.getElementById('openCaisseMsg');
    const btn = document.getElementById('openCaisseBtn');
    if (openingBalance === '' || parseFloat(openingBalance) < 0) {
      msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Fond de caisse invalide.</div>';
      return;
    }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ouverture...';
    msgEl.innerHTML = '';
    const formData = new FormData();
    formData.append('caisse_id', selectedCaisseId);
    formData.append('opening_balance', openingBalance);
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=open', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(res => {
        if (res.success) { window.location.reload(); }
        else {
          msgEl.innerHTML = '<div style="' + msgStyle('error') + '">' + escHtml(res.message) + '</div>';
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-unlock me-1"></i>Ouvrir « ' + escHtml(selectedCaisseName) + ' »';
        }
      })
      .catch(() => {
        msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Erreur reseau</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-unlock me-1"></i>Ouvrir « ' + escHtml(selectedCaisseName) + ' »';
      });
  }

  /* ── OPERATIONS MODAL ── */
  function selectOpType(type) {
    selectedOpType = type;
    document.querySelectorAll('.pay-option[data-optype]').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-optype="' + type + '"]').classList.add('active');
  }

  function openOperationsModal() {
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.data && data.data.summary) {
          const s = data.data.summary;
          document.getElementById('opsOpeningBalance').textContent = formatMoney(s.opening_balance);
          document.getElementById('opsCashSales').textContent = formatMoney(s.cash_sales);
          document.getElementById('opsDeposits').textContent = '+' + formatMoney(s.deposits);
          document.getElementById('opsWithdrawals').textContent = '-' + formatMoney(s.withdrawals);
          document.getElementById('opsExpected').textContent = formatMoney(s.expected_balance);
        }
      });
    selectedOpType = 'deposit';
    document.querySelectorAll('.pay-option[data-optype]').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-optype="deposit"]').classList.add('active');
    document.getElementById('opAmount').value = '';
    document.getElementById('opReason').value = '';
    document.getElementById('opsMsg').innerHTML = '';
    opsModalInstance = new bootstrap.Modal(document.getElementById('operationsModal'));
    opsModalInstance.show();
  }

  function saveOperation() {
    const amount = parseFloat(document.getElementById('opAmount').value);
    const reason = document.getElementById('opReason').value.trim();
    const msgEl = document.getElementById('opsMsg');
    if (!amount || amount <= 0) { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Montant invalide.</div>'; return; }
    if (!reason) { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Motif obligatoire.</div>'; return; }
    const btn = document.getElementById('saveOpBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data || !data.data.has_active_session) throw new Error('Pas de session');
        return fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=add_operation', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ session_id: data.data.session.id, type: selectedOpType, amount, reason })
        });
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) { opsModalInstance.hide(); showToast('Operation enregistree', 'success'); }
        else { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">' + escHtml(res.message) + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Enregistrer'; }
      })
      .catch(err => { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">' + escHtml(err.message) + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Enregistrer'; });
  }

  /* ── CLOSE CAISSE ── */
  function openCloseModal() {
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data || !data.data.has_active_session) { showToast('Aucune session active', 'warning'); return; }
        const session = data.data.session;
        const summary = data.data.summary;
        closeSessionId = session.id;
        closeExpectedBalance = summary.expected_balance;
        document.getElementById('closeCaisseInfo').textContent = escHtml(session.caisse_name) + ' — Ouverte depuis ' + new Date(session.opening_time).toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'});
        document.getElementById('closeOpeningBalance').textContent = formatMoney(summary.opening_balance);
        document.getElementById('closeCashSales').textContent = '+' + formatMoney(summary.cash_sales);
        document.getElementById('closeDeposits').textContent = '+' + formatMoney(summary.deposits);
        document.getElementById('closeWithdrawals').textContent = '-' + formatMoney(summary.withdrawals);
        document.getElementById('closeExpected').textContent = formatMoney(summary.expected_balance);
        document.getElementById('closeActualBalance').value = '';
        document.getElementById('closeNotes').value = '';
        document.getElementById('closeDiscrepancy').style.display = 'none';
        document.getElementById('closeMsg').innerHTML = '';
        document.getElementById('confirmCloseBtn').disabled = false;
        closeModalInstance = new bootstrap.Modal(document.getElementById('closeCaisseModal'));
        closeModalInstance.show();
      });
  }

  function updateCloseDiscrepancy() {
    const actual = parseFloat(document.getElementById('closeActualBalance').value);
    const el = document.getElementById('closeDiscrepancy');
    if (isNaN(actual) || document.getElementById('closeActualBalance').value === '') { el.style.display = 'none'; return; }
    const diff = actual - closeExpectedBalance;
    el.style.display = '';
    if (diff >= 0) {
      el.className = 'discrepancy-badge positive';
      el.textContent = 'Ecart : +' + formatMoney(diff);
    } else {
      el.className = 'discrepancy-badge negative';
      el.textContent = 'Ecart : ' + formatMoney(diff);
    }
  }

  function confirmCloseCaisse() {
    const actualBalance = document.getElementById('closeActualBalance').value;
    const notes = document.getElementById('closeNotes').value;
    const msgEl = document.getElementById('closeMsg');
    const btn = document.getElementById('confirmCloseBtn');
    if (actualBalance === '' || parseFloat(actualBalance) < 0) { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Montant invalide.</div>'; return; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Fermeture...';
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=close', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ caisse_id: POS_CONFIG.CAISSE_ID, closing_balance_actual: parseFloat(actualBalance), notes })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) { closeModalInstance.hide(); showZReport(res.data.session_id); }
      else { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">' + escHtml(res.message) + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Fermer et imprimer'; }
    })
    .catch(() => { msgEl.innerHTML = '<div style="' + msgStyle('error') + '">Erreur reseau</div>'; btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Fermer et imprimer'; });
  }

  /* ── Z-REPORT ── */
  function showZReport(sessionId) {
    document.getElementById('receiptModalBody').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm mb-2"></div><br>Generation...</div>';
    fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=report&session_id=' + sessionId)
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data) { document.getElementById('receiptModalBody').innerHTML = '<div style="background:rgba(220,38,38,0.08);color:var(--danger);padding:8px;font-weight:700;border-radius:6px">Erreur.</div>'; return; }
        const d = data.data;
        const session = d.session || {};
        const summary = d.summary || {};
        const operations = d.operations || [];
        const breakdown = d.payment_breakdown || [];
        let html = '<div class="receipt-wrapper" style="font-family:\'DM Mono\',monospace;font-size:.7rem">';
        html += '<div class="receipt-header"><div class="receipt-store-name">' + escHtml(session.store_name || 'BRENSHOP') + '</div><div style="font-size:.65rem">Rapport de Caisse (Z)</div></div>';
        html += '<div>Caisse: ' + escHtml(session.caisse_name || '-') + '</div>';
        html += '<div>Caissier: ' + escHtml(session.user_name || '-') + '</div>';
        html += '<div>Ouverture: ' + (session.opening_time ? new Date(session.opening_time).toLocaleString('fr-FR') : '-') + '</div>';
        html += '<div>Fermeture: ' + (session.closing_time ? new Date(session.closing_time).toLocaleString('fr-FR') : '-') + '</div>';
        html += '<div class="receipt-divider"></div>';
        html += '<div>Fond: ' + formatMoney(summary.opening_balance) + '</div>';
        html += '<div>Ventes: +' + formatMoney(summary.cash_sales) + '</div>';
        html += '<div>Apports: +' + formatMoney(summary.deposits) + '</div>';
        html += '<div>Retraits: -' + formatMoney(summary.withdrawals) + '</div>';
        html += '<div class="receipt-divider"></div>';
        html += '<div class="receipt-row receipt-total"><span>Attendu</span><span>' + formatMoney(summary.expected_balance) + '</span></div>';
        html += '<div class="receipt-row receipt-total"><span>Compte</span><span>' + formatMoney(parseFloat(session.closing_balance_actual || 0)) + '</span></div>';
        const disc = parseFloat(session.closing_discrepancy || 0);
        html += '<div class="receipt-row receipt-total"><span>Ecart</span><span style="color:' + (disc >= 0 ? 'var(--accent2)' : 'var(--danger)') + '">' + (disc >= 0 ? '+' : '') + formatMoney(disc) + '</span></div>';
        html += '<div class="receipt-divider"></div>';
        html += '<div>Nb ventes: ' + (d.sales_count || 0) + '</div>';
        if (breakdown.length > 0) { breakdown.forEach(function(p) { html += '<div style="font-size:.65rem">  ' + p.payment_method + ' (' + p.count + '): ' + formatMoney(parseFloat(p.total)) + '</div>'; }); }
        if (operations.length > 0) { html += '<div class="receipt-divider"></div>'; operations.forEach(function(op) { html += '<div style="font-size:.65rem">' + (op.type === 'deposit' ? '+' : '-') + formatMoney(parseFloat(op.amount)) + ' (' + escHtml(op.reason) + ')</div>'; }); }
        html += '</div>';
        document.getElementById('receiptModalBody').innerHTML = html;
      })
      .catch(() => { document.getElementById('receiptModalBody').innerHTML = '<div style="background:rgba(220,38,38,0.08);color:var(--danger);padding:8px;font-weight:700;border-radius:6px">Erreur.</div>'; });
    zReportInstance = new bootstrap.Modal(document.getElementById('receiptModal'));
    zReportInstance.show();
  }

  /* ── SESSION STATUS REFRESH ── */
  function startSessionRefresh() {
    setInterval(function() {
      fetch(POS_CONFIG.BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
          if (data.success && data.data) {
            if (!data.data.has_active_session) { window.location.reload(); }
            if (data.data.summary) {
              const el = document.getElementById('sessBalance');
              if (el) el.textContent = 'Attendu: ' + formatMoney(data.data.summary.expected_balance);
            }
          }
        });
    }, 30000);
  }

  /* ── INIT ── */
  function init() {
    loadCart();
    document.getElementById('searchInput').focus();

    /* Search input events */
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('keydown', handleScanKeydown);
    searchInput.addEventListener('input', handleScanInput);

    /* Payment method change */
    document.getElementById('paymentMethod').addEventListener('change', onPaymentMethodChange);
    document.getElementById('creditClientSelect').addEventListener('change', updateValidateBtn);

    /* Receipt modal close */
    document.getElementById('receiptModal').addEventListener('hidden.bs.modal', function() {
      if (zReportInstance) { window.location.reload(); }
    });

    /* Start session refresh if active */
    if (POS_CONFIG.HAS_SESSION) {
      startSessionRefresh();
      fetchDailyStats();
      dailyStatsInterval = setInterval(fetchDailyStats, 60000);
    }
  }

  /* ── PUBLIC API ── */
  return {
    init,
    addToCart,
    changeQty,
    removeCartItem,
    clearCart,
    renderCart,
    filterCat,
    setView,
    switchWarehouse,
    processSale,
    closeReceipt,
    calcChange,
    onPaymentMethodChange,
    openCaisseSelection,
    selectCaisse,
    resetCaisseSelection,
    openCaisse,
    selectOpType,
    openOperationsModal,
    saveOperation,
    openCloseModal,
    updateCloseDiscrepancy,
    confirmCloseCaisse,
    showZReport,
    fetchDailyStats
  };
})();

document.addEventListener('DOMContentLoaded', POS.init);