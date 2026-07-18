/* PharmaCare — JavaScript principal */

// ── Modal ──────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

// ── Confirm delete ─────────────────────────────────────────
function confirmDelete(url, msg) {
  showConfirm('Confirmer la suppression', msg || 'Cette action est irréversible.', function() {
    window.location.href = url;
  });
}

// ── Confirm delete en POST + CSRF (actions destructives) ──
// Soumet un formulaire POST vers l'URL courante avec action/id/csrf.
function confirmDeletePost(action, id, msg) {
  showConfirm('Confirmer la suppression', msg || 'Cette action est irréversible.', function() {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    var addHidden = function(name, value) {
      var h = document.createElement('input');
      h.type = 'hidden';
      h.name = name;
      h.value = value;
      form.appendChild(h);
    };
    addHidden('action', action);
    if (id) addHidden('id', id);
    addHidden('csrf', window.CSRF_TOKEN || '');
    document.body.appendChild(form);
    form.submit();
  });
}

// ── Confirm modal ──────────────────────────────────────────
function showConfirm(title, message, onConfirm) {
  let overlay = document.getElementById('confirm-overlay');
  if (overlay) overlay.remove();

  overlay = document.createElement('div');
  overlay.id = 'confirm-overlay';
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="width:400px;">
      <div class="modal-header">
        <div class="modal-title">${title}</div>
      </div>
      <div class="card-pad" style="padding:20px;color:var(--text2);font-size:14px;line-height:1.6;">
        ${message}
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost btn-sm" id="confirm-cancel">Annuler</button>
        <button class="btn btn-danger btn-sm" id="confirm-ok">Confirmer</button>
      </div>
    </div>`;

  document.body.appendChild(overlay);

  document.getElementById('confirm-cancel').onclick = function() { overlay.remove(); };
  document.getElementById('confirm-ok').onclick = function() { overlay.remove(); onConfirm(); };
  overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
}

// ── Devise (injectée depuis PHP via vente.php) ─────────────
// POS_DEV_SYM, POS_DEV_POS, POS_TVA_RATE sont définies dans vente.php
function fmtMoney(n) {
  const sym = (typeof POS_DEV_SYM !== 'undefined') ? POS_DEV_SYM : 'FCFA';
  const pos = (typeof POS_DEV_POS !== 'undefined') ? POS_DEV_POS : 'after';
  const formatted = Math.round(n).toLocaleString('fr-FR');
  return pos === 'before' ? sym + ' ' + formatted : formatted + ' ' + sym;
}

// ── POS Cart ───────────────────────────────────────────────
const CART_KEY = 'pharmacare_cart';
let cart = {};

function saveCart() {
  try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch(e) {}
}

function loadCart() {
  try {
    const saved = localStorage.getItem(CART_KEY);
    if (saved) {
      cart = JSON.parse(saved);
      if (cart && typeof cart === 'object') {
        for (var k in cart) {
          if (!cart[k].stockOrig) cart[k].stockOrig = cart[k].stock || 0;
        }
        renderCart();
      } else {
        cart = {};
      }
    }
  } catch(e) { cart = {}; }
}

function getTvaRate() {
  return (typeof POS_TVA_RATE !== 'undefined') ? POS_TVA_RATE : 0.1925;
}

function addToCart(id, name, price, stockOrig) {
  id       = parseInt(id);
  price    = parseFloat(price);
  stockOrig = parseInt(stockOrig);

  if (stockOrig <= 0) return;

  const alreadyInCart = cart[id] ? cart[id].qty : 0;
  if (alreadyInCart >= stockOrig) {
    showNotif('Stock maximum atteint pour ce produit.', 'error');
    return;
  }

  if (cart[id]) {
    cart[id].qty++;
    cart[id]._order = Date.now();
  } else {
    cart[id] = { id, name, price, stockOrig, qty: 1, _order: Date.now() };
  }

  renderCart();
  saveCart();

  const tile = document.querySelector('[data-id="' + id + '"]');
  if (tile) {
    tile.style.borderColor = 'var(--teal2)';
    tile.style.background  = 'var(--teal-dim)';
    setTimeout(() => {
      tile.style.borderColor = '';
      tile.style.background  = '';
    }, 400);
  }
}

function changeQty(id, delta) {
  id = parseInt(id);
  if (!cart[id]) return;
  const item = cart[id];
  if (delta > 0 && item.qty >= (item.stockOrig || 0)) {
    showNotif('Stock maximum atteint pour ce produit.', 'error');
    return;
  }
  item.qty += delta;
  if (item.qty <= 0) {
    delete cart[id];
  }
  renderCart();
  saveCart();
}

function inCartQty(id) {
  return (cart[id] && cart[id].qty) ? cart[id].qty : 0;
}

function removeItem(id) {
  delete cart[parseInt(id)];
  renderCart();
  saveCart();
}

function clearCart() {
  if (Object.keys(cart).length === 0) return;
  showConfirm('Vider le panier ?', 'Tous les articles seront retirés du panier.', function() {
    cart = {};
    renderCart();
    saveCart();
  });
}

function renderCart() {
  const container = document.getElementById('cart-items');
  if (!container) return;

  const items = Object.values(cart).sort((a, b) => (a._order || 0) - (b._order || 0));

  if (items.length === 0) {
    container.innerHTML = `
      <div class="empty" style="padding:30px 20px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.2" style="color:var(--text3);margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <div style="color:var(--text3);font-size:13px;text-align:center;">Panier vide</div>
      </div>`;
  } else {
    container.innerHTML = items.map(item => `
      <div class="cart-item-pro">
        <div class="cip-name">${escHtml(item.name)}</div>
        <div class="cip-price">${fmtMoney(item.price)}</div>
        <div class="cip-qty">
          <button type="button" class="qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
          <span class="qty-val">${item.qty}</span>
          <button type="button" class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
        </div>
        <div class="cip-subtotal">${fmtMoney(item.price * item.qty)}</div>
        <button type="button" class="btn-remove-pro" onclick="removeItem(${item.id})">×</button>
      </div>`).join('');
  }

  // Calcul totaux
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  const tva      = subtotal * getTvaRate();
  const total    = subtotal + tva;

  const elSub   = document.getElementById('pos-subtotal');
  const elTva   = document.getElementById('pos-tva');
  const elTotal = document.getElementById('pos-total');
  if (elSub)   elSub.textContent   = fmtMoney(subtotal);
  if (elTva)   elTva.textContent   = fmtMoney(tva);
  if (elTotal) elTotal.textContent = fmtMoney(total);

  // Mise à jour du champ caché pour le POST
  const cartInput = document.getElementById('cart-data');
  if (cartInput) cartInput.value = JSON.stringify(cart);

  // Activer/désactiver le bouton de validation
  const btn = document.getElementById('btn-validate');
  if (btn) {
    const hasItems = items.length > 0;
    btn.disabled = !hasItems;
    btn.style.opacity = hasItems ? '1' : '0.5';
    btn.style.cursor  = hasItems ? 'pointer' : 'not-allowed';
  }

  // Recalcul monnaie
  calcMonnaie();

  // Mise a jour temps reel du stock affiche
  refreshStockDisplay();
}

function refreshStockDisplay() {
  const tiles = document.querySelectorAll('.product-row, .product-tile');
  tiles.forEach(el => {
    const id = parseInt(el.dataset.id);
    const stockOrig = parseInt(el.dataset.stockOrig) || 0;
    const seuil = parseInt(el.dataset.seuil) || 0;
    const inCart = inCartQty(id);
    const reste = Math.max(0, stockOrig - inCart);

    el.dataset.stock = reste;

    if (reste <= 0) {
      el.classList.add('out');
    } else {
      el.classList.remove('out');
    }

    // Colonne stock dans la vue tableau (5e td)
    const tdStock = el.querySelector('td:nth-child(5)');
    if (tdStock) {
      updateStockCell(tdStock, reste, seuil);
    }

    // Bloc stock dans la vue tuiles
    const pStock = el.querySelector('.p-stock');
    if (pStock) {
      updateStockBlock(pStock, reste, seuil);
    }
  });
}

function updateStockCell(cell, reste, seuil) {
  let badge = '';
  if (reste <= 0) {
    badge = '<span class="badge badge-red">Rupture</span>';
  } else if (reste <= seuil) {
    badge = '<span class="badge badge-gold" style="font-size:9px;">Bas</span>';
  }
  cell.innerHTML = badge ? reste + ' ' + badge : '' + reste;
}

function updateStockBlock(block, reste, seuil) {
  if (reste <= 0) {
    block.innerHTML = '<span class="badge badge-red">Rupture</span>';
  } else if (reste <= seuil) {
    block.innerHTML = 'Stk <strong>' + reste + '</strong> <span class="badge badge-gold" style="font-size:9px;">Bas</span>';
  } else {
    block.innerHTML = 'Stk <strong>' + reste + '</strong>';
  }
}

function calcMonnaie() {
  const recuEl    = document.getElementById('montant-recu');
  const monnaieEl = document.getElementById('monnaie');
  if (!recuEl || !monnaieEl) return;

  // Recalculer le total depuis le panier (plus fiable que parser du texte)
  const items    = Object.values(cart);
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  const total    = subtotal * (1 + getTvaRate());

  const recu     = parseFloat(recuEl.value) || 0;
  const monnaie  = recu - total;

  monnaieEl.textContent  = fmtMoney(monnaie > 0 ? monnaie : 0);
  monnaieEl.style.color  = monnaie >= 0 ? 'var(--teal2)' : 'var(--red)';
}

// ── Utilitaires ────────────────────────────────────────────
function settleSale(clientId, venteId, balance) {
  const clientSelect = document.getElementById('client-select');
  const venteSelect = document.querySelector('select[name="vente_id"]');
  const montantInput = document.querySelector('input[name="montant"]');

  if (clientSelect) clientSelect.value = clientId;
  if (venteSelect) venteSelect.value = venteId;
  if (montantInput) montantInput.value = balance;

  // Trigger the credit mode toggle if it's a credit sale
  const creditCheckbox = document.getElementById('credit-checkbox');
  if (creditCheckbox) {
    creditCheckbox.checked = true;
    toggleCreditMode(creditCheckbox);
  }

  montantInput?.focus();
}

function payAll(amount) {
  const montantInput = document.querySelector('input[name="montant"]');
  const venteSelect = document.querySelector('select[name="vente_id"]');

  if (montantInput) montantInput.value = amount;
  if (venteSelect) venteSelect.value = '';

  montantInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  montantInput?.focus();
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showNotif(msg, type) {
  type = type || 'info';
  var DURATION = 4000;
  var svgCheck = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  var svgX     = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  var svgInfo  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  var iconSvg = { success: svgCheck, error: svgX, info: svgInfo };

  var container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  var el = document.createElement('div');
  el.className = 'toast toast-' + type;

  var body = document.createElement('div');
  body.className = 'toast-body';

  var icon = document.createElement('div');
  icon.className = 'toast-icon';
  icon.innerHTML = iconSvg[type] || iconSvg.info;

  var msgEl = document.createElement('div');
  msgEl.className = 'toast-msg';
  msgEl.textContent = msg;

  var progress = document.createElement('div');
  progress.className = 'toast-progress';
  progress.style.transform = 'scaleX(1)';
  progress.style.transition = 'transform ' + DURATION + 'ms linear';

  body.appendChild(icon);
  body.appendChild(msgEl);
  el.appendChild(body);
  el.appendChild(progress);
  container.appendChild(el);

  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      progress.style.transform = 'scaleX(0)';
    });
  });

  setTimeout(function() {
    el.classList.add('removing');
    setTimeout(function() { el.remove(); }, 300);
  }, DURATION);
}

// ── Live search ────────────────────────────────────────────
function liveSearch(inputId, tbodyId) {
  const input = document.getElementById(inputId);
  const tbody = document.getElementById(tbodyId);
  if (!input || !tbody) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    tbody.querySelectorAll('tr').forEach(row => {
      row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss flash alerts (backup in case PHP script didn't fire)
  document.querySelectorAll('.alert[id="auto-alert"]').forEach(a => {
    if (!a.dataset.autoDismiss) {
      a.dataset.autoDismiss = '1';
      setTimeout(() => {
        a.classList.add('removing');
        setTimeout(() => a.remove(), 300);
      }, 4000);
    }
  });

  // Live search tables
  liveSearch('search-stock',    'stock-tbody');
  liveSearch('search-produits', 'produits-tbody');
  liveSearch('search-users',    'users-tbody');

  // Monnaie listener
  const mr = document.getElementById('montant-recu');
  if (mr) mr.addEventListener('input', calcMonnaie);

  // Restaurer le panier depuis localStorage
  loadCart();

  // Vider le panier si on vient de valider une vente (paramètre receipt)
  if (new URLSearchParams(window.location.search).get('receipt')) {
    cart = {};
    saveCart();
    renderCart();
    // Nettoyer l'URL pour éviter de re-vider le panier aux prochains refresh
    history.replaceState(null, '', window.location.pathname);
  }

  // ── Scan code-barres sur page stock ──
  const stockSearch = document.getElementById('search-stock');
  if (stockSearch) {
    stockSearch.addEventListener('keydown', function(e) {
      if (e.key !== 'Enter') return;
      const q = this.value.trim();
      if (!q) return;
      const rows = document.querySelectorAll('#stock-tbody tr');
      for (let i = 0; i < rows.length; i++) {
        const refCell = rows[i].querySelector('.td-mono');
        if (refCell && refCell.textContent.trim().toLowerCase() === q.toLowerCase()) {
          const link = rows[i].querySelector('a[href*="stock_ajust"]');
          if (link) {
            e.preventDefault();
            window.location.href = link.href;
            return;
          }
        }
      }
    });
  }
});

// ── Line Chart Tooltip ─────────────────────────────────────
function chartShowTip(el, cid, i, label, value) {
  const wrap = el.closest('.line-chart-wrap');
  if (!wrap) return;
  let tip = wrap.querySelector('.chart-tooltip');
  if (!tip) {
    tip = document.createElement('div');
    tip.className = 'chart-tooltip';
    wrap.appendChild(tip);
  }
  tip.innerHTML = '<div class="ct-label">' + label + '</div><div class="ct-value">' + value + '</div>';
  tip.style.display = 'block';
  // force reflow pour que la transition parte de l'état initial
  void tip.offsetWidth;
  tip.classList.add('visible');

  const rect = el.getBoundingClientRect();
  const wrapRect = wrap.getBoundingClientRect();
  let left = rect.left - wrapRect.left + rect.width / 2 - tip.offsetWidth / 2;
  let top  = rect.top - wrapRect.top - tip.offsetHeight - 8;

  // garde dans le conteneur
  left = Math.max(6, Math.min(left, wrapRect.width - tip.offsetWidth - 6));
  top  = Math.max(6, top);

  tip.style.left = left + 'px';
  tip.style.top  = top + 'px';

  // Highlight le point + halo
  const pt = document.querySelector('#' + cid + ' .chart-point[data-i="' + i + '"]');
  if (pt) { pt.setAttribute('r', '7'); pt.style.opacity = '1'; pt.style.strokeWidth = '3'; }
  const hl = document.querySelector('#' + cid + ' .chart-halo[data-i="' + i + '"]');
  if (hl) { hl.style.opacity = '1'; }
}
function chartHideTip(cid, i) {
  const wrap = document.querySelector('#' + cid).closest('.line-chart-wrap');
  if (wrap) {
    const tip = wrap.querySelector('.chart-tooltip');
    if (tip) { tip.classList.remove('visible'); }
  }
  const pt = document.querySelector('#' + cid + ' .chart-point[data-i="' + i + '"]');
  if (pt) { pt.setAttribute('r', '5'); pt.style.opacity = ''; pt.style.strokeWidth = ''; }
  const hl = document.querySelector('#' + cid + ' .chart-halo[data-i="' + i + '"]');
  if (hl) { hl.style.opacity = ''; }
}

// ── Candlestick Chart Tooltip ──────────────────────────────
function candleShowTip(el, wrapId, i, label, open, high, low, close, isBull) {
  const wrap = document.getElementById(wrapId);
  if (!wrap) return;
  let tip = wrap.querySelector('.chart-tooltip');
  if (!tip) {
    tip = document.createElement('div');
    tip.className = 'chart-tooltip';
    wrap.appendChild(tip);
  }
  const color = isBull ? '#22d3ee' : '#ef4444';
  tip.innerHTML =
    '<div class="ct-label">' + label + '</div>' +
    '<div style="display:grid;grid-template-columns:auto auto;gap:2px 10px;font-size:10px;margin-top:4px;">' +
    '<span style="color:var(--text3)">O</span><span style="color:' + color + ';font-weight:600;text-align:right;">' + open + '</span>' +
    '<span style="color:var(--text3)">H</span><span style="color:' + color + ';font-weight:600;text-align:right;">' + high + '</span>' +
    '<span style="color:var(--text3)">L</span><span style="color:' + color + ';font-weight:600;text-align:right;">' + low + '</span>' +
    '<span style="color:var(--text3)">C</span><span style="color:' + color + ';font-weight:600;text-align:right;">' + close + '</span>' +
    '</div>';
  tip.style.display = 'block';
  void tip.offsetWidth;
  tip.classList.add('visible');

  const rect = el.getBoundingClientRect();
  const wrapRect = wrap.getBoundingClientRect();
  let left = rect.left - wrapRect.left + rect.width / 2 - tip.offsetWidth / 2;
  let top  = rect.top - wrapRect.top - tip.offsetHeight - 8;

  left = Math.max(6, Math.min(left, wrapRect.width - tip.offsetWidth - 6));
  top  = Math.max(6, top);

  tip.style.left = left + 'px';
  tip.style.top  = top + 'px';

  // Highlight candle body
  const bodies = wrap.querySelectorAll('.candle-body');
  if (bodies[i]) {
    bodies[i].setAttribute('stroke-width', '2.5');
    bodies[i].style.opacity = '1';
  }
}
function candleHideTip(wrapId, i) {
  const wrap = document.getElementById(wrapId);
  if (wrap) {
    const tip = wrap.querySelector('.chart-tooltip');
    if (tip) { tip.classList.remove('visible'); }
    const bodies = wrap.querySelectorAll('.candle-body');
    if (bodies[i]) {
      bodies[i].setAttribute('stroke-width', '1.5');
      bodies[i].style.opacity = '';
    }
  }
}

// ── Bar Chart Tooltip ───────────────────────────────────────
function barShowTip(el, wrapId, label, rowsJson) {
  const wrap = document.getElementById(wrapId);
  if (!wrap) return;
  let tip = wrap.querySelector('.chart-tooltip');
  if (!tip) { tip = document.createElement('div'); tip.className = 'chart-tooltip'; wrap.appendChild(tip); }
  tip.replaceChildren();
  const lbl = document.createElement('div'); lbl.className = 'ct-label'; lbl.textContent = label;
  tip.appendChild(lbl);
  let rows = [];
  try { rows = JSON.parse(rowsJson); } catch (e) { rows = []; }
  rows.forEach(function (r) {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;justify-content:space-between;gap:14px;font-size:11px;line-height:1.5;';
    const k = document.createElement('span'); k.style.color = 'var(--text3)'; k.textContent = r[0];
    const v = document.createElement('span'); v.style.fontWeight = '600'; v.style.color = 'var(--text)'; v.textContent = r[1];
    row.appendChild(k); row.appendChild(v);
    tip.appendChild(row);
  });
  tip.style.display = 'block';
  void tip.offsetWidth;
  tip.classList.add('visible');
  const rect = el.getBoundingClientRect();
  const wrapRect = wrap.getBoundingClientRect();
  let left = rect.left - wrapRect.left + rect.width / 2 - tip.offsetWidth / 2;
  let top  = rect.top - wrapRect.top - tip.offsetHeight - 8;
  left = Math.max(6, Math.min(left, wrapRect.width - tip.offsetWidth - 6));
  top  = Math.max(6, top);
  tip.style.left = left + 'px';
  tip.style.top  = top + 'px';
}
function barHideTip(wrapId) {
  const wrap = document.getElementById(wrapId);
  if (wrap) {
    const tip = wrap.querySelector('.chart-tooltip');
    if (tip) { tip.classList.remove('visible'); }
  }
}
// ── Throttle client-side (export / impression) ──────────────
// Limite le nombre d'actions identiques par fenêtre de temps côté navigateur.
// Usage : if (!rateLimitClick('export.mvt', 10, 60000)) return;
window.rateLimitClick = function(key, max, perMs) {
  perMs = perMs || 60000;
  max = max || 10;
  var store;
  try { store = JSON.parse(sessionStorage.getItem('rl_clicks') || '{}'); }
  catch (e) { store = {}; }
  var now = Date.now();
  var arr = (store[key] || []).filter(function(t){ return t > now - perMs; });
  if (arr.length >= max) return false;
  arr.push(now);
  store[key] = arr;
  try { sessionStorage.setItem('rl_clicks', JSON.stringify(store)); } catch (e) {}
  return true;
};
window.rateLimitWarn = function(key, max, perMs) {
  var sec = Math.round(perMs / 1000);
  alert('Trop d\'actions (« ' + key + ' »). Maximum ' + max + ' par ' + sec + ' s. Patientez un instant.');
};
