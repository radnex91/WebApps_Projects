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
// Clé namespacée par utilisateur : chaque compte a son propre panier, même
// sur un navigateur partagé. Aucune migration de l'ancienne clé globale
// 'pharmacare_cart' (évite toute fuite du panier d'un autre utilisateur) ;
// on se contente de l'effacer une fois pour nettoyer l'orphelin.
const CART_KEY = 'pharmacare_cart_' + (typeof PC_USER_ID !== 'undefined' && PC_USER_ID ? PC_USER_ID : 'anon');
let cart = {};

function saveCart() {
  try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch(e) {}
}

function loadCart() {
  try {
    // Nettoyage unique de l'ancienne clé globale (pré-namespacing) pour éviter
    // qu'un panier d'un autre compte ne traîne sur un navigateur partagé.
    if (localStorage.getItem('pharmacare_cart') !== null) {
      localStorage.removeItem('pharmacare_cart');
    }
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

// Remise % appliquée au panier (0 si champ absent → ventes hors POS).
function getRemisePct() {
  const el = document.getElementById('remise-pct');
  if (!el) return 0;
  let v = parseFloat(el.value) || 0;
  if (v < 0) v = 0;
  const max = parseFloat(el.max) || 100;
  if (v > max) v = max;
  return v;
}

// Calcule les totaux du panier (méthode brute : remise rendue en espèces).
//   gross      = HT brut (Σ prix×qty) — inchangé par la remise
//   remise     = gross × pct/100            (montant HT de la remise)
//   netHt      = gross − remise
//   tva        = netHt × taux               (TVA sur HT net — légal)
//   netTtc     = netHt + tva                (net encaissé / dû)
//   grossTva   = gross × taux               (TVA sur HT brut)
//   total      = gross + grossTva           (TTC brut FACTURÉ)
//   remiseTtc  = remise × (1 + taux)        (remise rendue en espèces, TTC)
function computeTotals(items) {
  const rate     = getTvaRate();
  const gross    = items.reduce((s, i) => s + i.price * i.qty, 0);
  const remise   = gross * getRemisePct() / 100;
  const netHt    = gross - remise;
  const tva      = netHt * rate;            // TVA sur HT net
  const netTtc   = netHt + tva;
  const grossTva = gross * rate;
  const total    = gross + grossTva;        // TTC brut facturé
  const remiseTtc = remise * (1 + rate);    // rendu en espèces
  return { gross, remise, netHt, tva, netTtc, grossTva, total, remiseTtc };
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
  // On ne descend jamais en dessous de 1 via le bouton « − ».
  // Pour retirer un article, il faut utiliser le bouton × de la ligne.
  if (delta < 0 && item.qty <= 1) {
    return;
  }
  item.qty += delta;
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
    resetPaymentInputs();
  });
}

// Remet à zéro les champs du POS après vidage du panier SANS rechargement
// (vente hors ligne mise en file, bouton « Vider »). Sinon l'état de la vente
// précédente fuit sur la suivante : montant reçu réaffiché (recalculé en
// « reliquat » contre un panier vide), montant_recu obsolète en POST, nom du
// client précédent, et mode_paiement resté sur « crédit » si la vente l'était.
function resetPaymentInputs() {
  const recuEl = document.getElementById('montant-recu');
  if (recuEl) recuEl.value = '';
  const codeEl = document.getElementById('code-remise');
  if (codeEl) {
    codeEl.value = '';
    codeEl.dispatchEvent(new Event('input'));
  }
  const pctDisplay = document.getElementById('remise-pct-display');
  if (pctDisplay) pctDisplay.value = '—';
  const pctInput = document.getElementById('remise-pct');
  if (pctInput) pctInput.value = '0';
  const block = document.getElementById('remise-block');
  if (block) block.style.display = 'none';
  const icon = document.getElementById('remise-toggle-icon');
  if (icon) icon.textContent = '▸';
  // Retour à l'état « vente libre » d'une page fraîche : nom du client effacé,
  // sélecteur client vidé, mode espèces restauré (une vente à crédit précédente
  // ne doit pas fuiter sur la suivante).
  const clientEl = document.getElementById('client-nom-saisie');
  if (clientEl) clientEl.value = '';
  if (typeof chooseClientMode === 'function') {
    try { chooseClientMode('simple'); } catch (e) {}
  } else {
    const selectExist = document.getElementById('client-select');
    if (selectExist) selectExist.value = '';
    const creditCb = document.getElementById('credit-checkbox');
    if (creditCb && creditCb.checked) {
      creditCb.checked = false;
      if (typeof toggleCreditMode === 'function') toggleCreditMode(creditCb);
      else { const pm = document.getElementById('mode-paiement'); if (pm) pm.value = 'espèces'; }
    }
  }
  calcMonnaie();
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

  // Calcul totaux (méthode brute : remise rendue en espèces)
  const t = computeTotals(items);
  const { gross: subtotal, remise, tva, total, netTtc, remiseTtc } = t;

  const elSub    = document.getElementById('pos-subtotal');
  const elTva    = document.getElementById('pos-tva');
  const elTotal  = document.getElementById('pos-total');
  const elRemise = document.getElementById('pos-remise');
  const elRemiseTtc = document.getElementById('pos-remise-ttc');
  const elNet    = document.getElementById('pos-net');
  const boxRemise    = document.getElementById('remise-display');
  const boxRemiseTtc = document.getElementById('remise-ttc-display');
  const boxNet       = document.getElementById('net-display');
  // Affichage tout-TTC (bloc TVA masqué côté UI) :
  //  - Sous-total TTC = TTC brut avant remise
  //  - Total TTC      = TTC net après remise (= net à encaisser)
  if (elSub)   elSub.textContent   = fmtMoney(total);   // TTC brut
  if (elTva)   elTva.textContent   = fmtMoney(tva);      // TVA masquée côté UI
  if (elTotal) elTotal.textContent = fmtMoney(netTtc);   // TTC net
  if (elRemise)    elRemise.textContent    = '-' + fmtMoney(remise);
  if (elRemiseTtc) elRemiseTtc.textContent = '-' + fmtMoney(remiseTtc);
  if (elNet)       elNet.textContent       = fmtMoney(netTtc);
  const showRemise = remise > 0;
  if (boxRemise)    boxRemise.style.display    = 'none';               // Remise HT masquée (on affiche le TTC)
  if (boxRemiseTtc) boxRemiseTtc.style.display = showRemise ? '' : 'none';
  if (boxNet)       boxNet.style.display       = showRemise ? '' : 'none';

  // Compteur d'articles (somme des quantités)
  const countEl = document.getElementById('cart-item-count');
  if (countEl) {
    const n = items.reduce((s, it) => s + (parseInt(it.qty) || 0), 0);
    countEl.textContent = n + (n > 1 ? ' articles' : ' article');
  }

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
  const items   = Object.values(cart);
  const t       = computeTotals(items);
  // Monnaie = (reçu − total brut TTC) + remise TTC rendue = reçu − net TTC.
  // Le client paie le brut et reçoit la remise (TTC) en espèces.
  const monnaie = parseFloat(recuEl.value || 0) - t.netTtc;

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

  // Live search tables (client-side filtering for pages without server-side search)
  liveSearch('search-produits', 'produits-tbody');
  liveSearch('search-users',    'users-tbody');

  // Monnaie listener
  const mr = document.getElementById('montant-recu');
  if (mr) mr.addEventListener('input', calcMonnaie);

  // Nom client toujours en MAJUSCULE (saisie libre POS)
  const cn = document.getElementById('client-nom-saisie');
  if (cn) cn.addEventListener('input', function() {
    const s = this.selectionStart, e = this.selectionEnd;
    this.value = this.value.toUpperCase();
    try { this.setSelectionRange(s, e); } catch (_) {}
  });

  // ── Remise % : recalcul dynamique + garde-fou à la soumission ──
  ['remise-pct', 'autorise-par', 'code-remise'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', renderCart);
  });

  // ── Auto-remplissage du % ET de l'auteur quand le caissier saisit un code ──
  // Le code de remise est lié à son générateur (created_by) : la saisie du code
  // pose automatiquement le % ET le nom de l'autorité, qui est alors verrouillé
  // (non modifiable). Si le code est effacé ou invalide, on déverrouille.
  const codeInput = document.getElementById('code-remise');
  if (codeInput) {
    // Verrouille l'auteur sur auteurId (venu du code). Gère les 2 rendus :
    //  - caissier : <select id="autorise-par"> -> on le désactive et on porte
    //    la valeur via un hidden (un select disabled n'est pas soumis en POST).
    //  - approbateur : <input type=hidden id="autorise-par"> + affichage texte.
    function lockAuteur(auteurId, auteurNom) {
      const auteurEl = document.getElementById('autorise-par');
      if (!auteurEl) return;
      if (auteurEl.tagName === 'SELECT') {
        auteurEl.value = auteurId;
        auteurEl.disabled = true;
        auteurEl.style.opacity = '0.6';
        auteurEl.style.cursor = 'not-allowed';
        let hid = document.getElementById('autorise-par-hidden');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.id = 'autorise-par-hidden';
          hid.name = 'autorise_par';
          auteurEl.parentNode.appendChild(hid);
        }
        hid.value = auteurId;
      } else {
        auteurEl.value = auteurId;
        const nomEl = document.getElementById('autorise-par-nom');
        if (nomEl) nomEl.value = auteurNom || '';
      }
    }
    // Réinitialise l'auteur (code effacé / invalide). Le select reste désactivé :
    // le caissier ne choisit jamais l'auteur manuellement, il vient du code.
    function unlockAuteur() {
      const auteurEl = document.getElementById('autorise-par');
      if (!auteurEl) return;
      if (auteurEl.tagName === 'SELECT') {
        auteurEl.disabled = true;
        auteurEl.style.opacity = '';
        auteurEl.style.cursor = 'not-allowed';
        auteurEl.value = '';
        const hid = document.getElementById('autorise-par-hidden');
        if (hid) hid.remove();
      }
      // approbateur : l'auteur reste « moi », rien à réinitialiser.
    }

    // Vérifie le code auprès du serveur et applique % + auteur (verrouillé).
    let codeTimer = null, codeInFlight = false, lastCheckedCode = null;
    function verifierCode() {
      const code = codeInput.value.trim().toUpperCase();
      const dispEl = document.getElementById('remise-pct-display');
      const hidEl  = document.getElementById('remise-pct');
      if (!code) {
        if (hidEl)  hidEl.value = 0;
        if (dispEl) dispEl.value = '—';
        unlockAuteur();
        lastCheckedCode = null;
        renderCart();
        return;
      }
      if (code === lastCheckedCode || codeInFlight) return; // déjà vérifié / en cours
      codeInFlight = true;
      lastCheckedCode = code;
      fetch(APP_URL + '/modules/vente.php?ajax_remise=1&code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(d => {
          if (d.ok) {
            if (hidEl)  hidEl.value = d.pct;
            if (dispEl) dispEl.value = d.pct + '%';
            // L'auteur fait foi : celui qui a généré le code. Verrouillé.
            if (d.auteur_id) lockAuteur(d.auteur_id, d.auteur);
            showNotif('Remise ' + d.pct + '% — autorisée par ' + (d.auteur || 'l\'autorité') + ' (code ' + code + ')', 'success');
          } else {
            if (hidEl)  hidEl.value = 0;
            if (dispEl) dispEl.value = '—';
            unlockAuteur();
            lastCheckedCode = null; // code refusé : on retryera si on re-saisit
            showNotif(d.error || 'Code de remise invalide', 'error');
          }
          renderCart();
        })
        .catch(() => {
          if (hidEl)  hidEl.value = 0;
          if (dispEl) dispEl.value = '—';
          unlockAuteur();
          lastCheckedCode = null;
          showNotif('Impossible de vérifier le code (réseau).', 'error');
          renderCart();
        })
        .finally(() => { codeInFlight = false; });
    }
    // Déclenchement : immédiat sur Entrée, sinon peu après la dernière frappe
    // (debounce) — pas besoin d'attendre la perte de focus.
    codeInput.addEventListener('input', function() {
      clearTimeout(codeTimer);
      lastCheckedCode = null; // la valeur change : on devra revérifier
      codeTimer = setTimeout(verifierCode, 450);
    });
    codeInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); clearTimeout(codeTimer); verifierCode(); }
    });
    codeInput.addEventListener('change', verifierCode); // fallback blur
  }

  const posForm = document.getElementById('pos-form');
  if (posForm) {
    posForm.addEventListener('submit', function(e) {
      const pct = getRemisePct();
      if (pct > 0) {
        const auteur = document.getElementById('autorise-par');
        const code   = document.getElementById('code-remise');
        if (!auteur || !auteur.value || !code || !code.value.trim()) {
          e.preventDefault();
          showNotif('Remise : indiquez l\'auteur et le code d\'autorisation.', 'error');
          return false;
        }
      }
    });
  }

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

// ── Assistant PharmaCare intégré (déterministe, sans serveur IA) ──
// Panneau latéral (slide-over) + FAB injectés sur toutes les pages par layout_foot().
// Envoie les messages à /assistant?action=chat (POST, CSRF dans le corps).
(function(){
  var fab    = document.getElementById('assistant-fab');
  var panel  = document.getElementById('assistant-panel');
  var closeB = document.getElementById('assistant-close');
  var form   = document.getElementById('assistant-form');
  var input  = document.getElementById('assistant-text');
  var body   = document.getElementById('assistant-body');
  var quick  = document.getElementById('assistant-quick');
  if (!fab || !panel || !form) return;

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function nl2br(s){ return esc(s).replace(/\n/g,'<br>'); }

  function open(){
    panel.classList.add('open');
    panel.setAttribute('aria-hidden','false');
    setTimeout(function(){ if(input) input.focus(); }, 120);
  }
  function closeP(){
    panel.classList.remove('open');
    panel.setAttribute('aria-hidden','true');
  }
  fab.addEventListener('click', open);
  fab.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); open(); } });
  if (closeB) closeB.addEventListener('click', closeP);
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && panel.classList.contains('open')) closeP(); });
  // Fermer au clic en dehors du panneau (et hors du FAB)
  document.addEventListener('click', function(e){
    if (!panel.classList.contains('open')) return;
    if (panel.contains(e.target) || fab.contains(e.target)) return;
    closeP();
  });

  function addBubble(text, who){
    var div = document.createElement('div');
    div.className = 'assistant-bubble ' + (who==='user' ? 'assistant-bubble-user' : 'assistant-bubble-bot');
    div.innerHTML = nl2br(text);
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    return div;
  }

  function renderLinks(links){
    if (!links || !links.length) return;
    var wrap = document.createElement('div');
    wrap.className = 'assistant-links';
    links.forEach(function(l){
      var a = document.createElement('a');
      a.className = 'assistant-link';
      a.href = l.url;
      a.textContent = l.label;
      wrap.appendChild(a);
    });
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
  }

  function renderQuick(items){
    if (!quick) return;
    quick.innerHTML = '';
    if (!items || !items.length) { quick.style.display = 'none'; return; }
    quick.style.display = 'flex';
    items.forEach(function(q){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'assistant-chip';
      b.textContent = q;
      b.addEventListener('click', function(){ send(q); });
      quick.appendChild(b);
    });
  }

  function typingOn(){
    var d = document.createElement('div');
    d.className = 'assistant-bubble assistant-bubble-bot assistant-typing';
    d.innerHTML = '<span class="assistant-dot"></span><span class="assistant-dot"></span><span class="assistant-dot"></span>';
    d.id = 'assistant-typing';
    body.appendChild(d);
    body.scrollTop = body.scrollHeight;
  }
  function typingOff(){
    var d = document.getElementById('assistant-typing');
    if (d) d.remove();
  }

  function send(q){
    var msg = (q==null ? (input ? input.value : '') : q);
    msg = (msg||'').trim();
    if (!msg) return;
    if (!rateLimitClick('assistant.chat', 30, 60000)) { rateLimitWarn('assistant.chat', 30, 60000); return; }
    addBubble(msg, 'user');
    if (input) input.value = '';
    renderQuick([]); // on cache les suggestions pendant le traitement
    typingOn();
    var body_ = 'csrf=' + encodeURIComponent(window.CSRF_TOKEN || '')
              + '&message=' + encodeURIComponent(msg)
              + '&page=' + encodeURIComponent(window.PHARMCARE_PAGE || '');
    fetch((window.APP_URL||'') + '/assistant?action=chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body_,
      credentials: 'same-origin'
    }).then(function(r){
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function(data){
      typingOff();
      addBubble((data && data.reply) || 'Désolé, je n\'ai pas de réponse pour le moment.', 'bot');
      renderLinks(data && data.links);
      renderQuick(data && data.quick);
    }).catch(function(err){
      typingOff();
      addBubble('Une erreur réseau est survenue (' + esc(err.message) + '). Réessayez.', 'bot');
      renderQuick(['Chercher un médicament', 'Chiffre du jour']);
    });
  }

  form.addEventListener('submit', function(e){ e.preventDefault(); send(); });
  // Suggestions initiales
  if (quick) {
    Array.prototype.forEach.call(quick.querySelectorAll('.assistant-chip'), function(b){
      b.addEventListener('click', function(){ send(b.getAttribute('data-q') || b.textContent); });
    });
  }
})();
