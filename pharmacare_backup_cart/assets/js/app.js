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
  if (confirm(msg || 'Confirmer la suppression ?')) window.location.href = url;
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
      if (cart && typeof cart === 'object') renderCart();
      else cart = {};
    }
  } catch(e) { cart = {}; }
}

function getTvaRate() {
  return (typeof POS_TVA_RATE !== 'undefined') ? POS_TVA_RATE : 0.1925;
}

function addToCart(id, name, price, stock) {
  id    = parseInt(id);
  price = parseFloat(price);
  stock = parseInt(stock);

  if (stock <= 0) return;

  if (cart[id]) {
    if (cart[id].qty >= stock) {
      showNotif('Stock insuffisant pour ce produit.', 'error');
      return;
    }
    cart[id].qty++;
  } else {
    cart[id] = { id, name, price, stock, qty: 1 };
  }

  renderCart();
  saveCart();

  // Flash visuel sur la tuile
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
  cart[id].qty += delta;
  if (cart[id].qty <= 0) {
    delete cart[id];
  }
  renderCart();
  saveCart();
}

function removeItem(id) {
  delete cart[parseInt(id)];
  renderCart();
  saveCart();
}

function clearCart() {
  if (Object.keys(cart).length === 0) return;
  if (!confirm('Vider le panier ?')) return;
  cart = {};
  renderCart();
  saveCart();
}

function renderCart() {
  const container = document.getElementById('cart-items');
  if (!container) return;

  const items = Object.values(cart);

  if (items.length === 0) {
    container.innerHTML = `
      <div class="empty" style="padding:30px 20px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.2" style="color:var(--text3);margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <div style="color:var(--text3);font-size:13px;">Panier vide</div>
      </div>`;
  } else {
    container.innerHTML = items.map(item => `
      <div class="cart-item">
        <div class="ci-info">
          <div class="ci-name">${escHtml(item.name)}</div>
          <div class="ci-price">
            ${fmtMoney(item.price)} × ${item.qty}
            = <span style="color:var(--teal2);font-weight:600;">${fmtMoney(item.price * item.qty)}</span>
          </div>
        </div>
        <div class="ci-qty">
          <button type="button" class="qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
          <span class="qty-val">${item.qty}</span>
          <button type="button" class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
        </div>
        <button type="button" onclick="removeItem(${item.id})"
                style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;padding:2px 6px;line-height:1;">
          ×
        </button>
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

  // Recalcul monnaie
  calcMonnaie();
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