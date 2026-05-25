<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('pos');

$pageTitle = 'Point de Vente';
$storeId = currentStoreId();
$warehouseId = currentWarehouseId();

$warehouseModel = new Warehouse();
$categoryModel = new Category();
$customerModel = new Customer();

$warehouses  = $warehouseModel->getByStore($storeId);
$categories  = $categoryModel->getByStore($storeId);
$customers   = $customerModel->getByStore($storeId);

// Si pas de warehouse selectionne, prendre le premier
if (!$warehouseId && !empty($warehouses)) {
    $warehouseId = $warehouses[0]['id'];
}

$caisseRequired = isCaisseRequired();
$activeCaisse = null;
if (!$caisseRequired && currentCaisseId() > 0) {
    $caisseModel = new Caisse();
    $activeCaisse = $caisseModel->find(currentCaisseId());
}

$extraHead = '<style>
body { overflow: hidden; }
.pos-container { display: flex; height: calc(100vh - 57px); overflow: hidden; }

/* ---- CATALOGUE (droite) ---- */
.pos-catalog {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--body-bg);
    border-left: 1px solid var(--border);
    order: 2;
}

/* Barre recherche + filtres */
.pos-toolbar {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    padding: 0.6rem 1rem;
    flex-shrink: 0;
}
.pos-toolbar-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.search-field {
    flex: 1;
    position: relative;
}
.search-field i.search-ico {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.85rem;
    pointer-events: none;
    transition: color 0.15s;
}
.search-field input {
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 0.65rem 0.5rem 2rem;
    font-size: 0.85rem;
    background: var(--body-bg);
    color: var(--text);
    outline: none;
    transition: all 0.15s;
}
.search-field input:focus {
    border-color: var(--accent);
    background: var(--card-bg);
}
.search-field input:focus + i.search-ico { color: var(--accent); }

.wh-select {
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 0.5rem;
    font-size: 0.82rem;
    background: var(--body-bg);
    color: var(--text);
    outline: none;
}

/* Onglets categories */
.pos-cats {
    display: flex;
    gap: 0.35rem;
    padding: 0.45rem 1rem 0.45rem;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.pos-cats::-webkit-scrollbar { display: none; }
.cat-tab {
    padding: 0.3rem 0.75rem;
    border-radius: 6px;
    font-size: 0.76rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    background: transparent;
    color: var(--text-muted);
    transition: all 0.15s;
}
.cat-tab:hover { background: rgba(99,102,241,0.06); color: var(--text); }
.cat-tab.active {
    background: var(--accent);
    color: #fff;
}

/* ---- LISTE PRODUITS (table ergonomique) ---- */
.pos-products {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.pos-products::-webkit-scrollbar { width: 5px; }
.pos-products::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
.pos-products::-webkit-scrollbar-track { background: transparent; }

/* Table header */
.pt-header {
    position: sticky;
    top: 0;
    z-index: 10;
    display: grid;
    grid-template-columns: 48px 1fr 100px 100px 100px 80px;
    gap: 0;
    background: var(--body-bg);
    border-bottom: 2px solid var(--border);
    padding: 0 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
}
.pt-header span {
    padding: 0.45rem 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Table row */
.p-row {
    display: grid;
    grid-template-columns: 48px 1fr 100px 100px 100px 80px;
    gap: 0;
    align-items: center;
    padding: 0 0.5rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.12s, box-shadow 0.12s;
    user-select: none;
    -webkit-user-select: none;
    -webkit-tap-highlight-color: transparent;
    min-height: 44px;
}
.p-row:hover { background: rgba(99,102,241,0.04); }
.p-row:active { background: rgba(99,102,241,0.1); }
.p-row.flash {
    background: rgba(16,185,129,0.12) !important;
    box-shadow: inset 4px 0 0 var(--accent2);
    transition: none;
}
.p-row.oos { opacity: 0.38; cursor: not-allowed; filter: grayscale(0.3); }
.p-row.oos:hover, .p-row.oos:active { background: transparent; }

/* Column cells */
.p-cell-img {
    width: 36px; height: 36px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0; margin: 4px 0;
}
.p-cell-img img { width: 100%; height: 100%; object-fit: cover; }
.p-cell-img .p-icon { font-size: 1.1rem; color: rgba(255,255,255,0.85); }
.p-cell-name { padding: 0.35rem 0.5rem; min-width: 0; overflow: hidden; }
.p-cell-name .p-name {
    font-size: 0.82rem; font-weight: 500; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3;
}
.p-cell-name .p-sku {
    font-size: 0.68rem; color: var(--text-muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.p-cell-stock { padding: 0.35rem; text-align: right; font-size: 0.78rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.p-cell-stock .stock-badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 600;
}
.p-cell-stock .stock-badge.ok  { background: #064E3B; color: #6EE7B7; }
.p-cell-stock .stock-badge.low { background: #78350F; color: #FDE68A; }
.p-cell-stock .stock-badge.out { background: #7F1D1D; color: #FECACA; }
.p-cell-price { padding: 0.35rem; text-align: right; font-size: 0.82rem; font-weight: 700; color: var(--accent); font-variant-numeric: tabular-nums; white-space: nowrap; }
.p-cell-action { padding: 0.35rem 0.35rem 0.35rem 0.5rem; text-align: center; }
.p-cell-action .btn-add {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1.5px solid var(--accent); background: transparent; color: var(--accent);
    font-size: 1.1rem; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.p-cell-action .btn-add:hover { background: var(--accent); color: #fff; }
.p-row.oos .btn-add { border-color: #CBD5E1; color: #CBD5E1; cursor: not-allowed; pointer-events: none; }

/* Responsive: collapse columns on mobile */
@media (max-width: 768px) {
    .pt-header, .p-row { grid-template-columns: 36px 1fr 75px 52px; }
    .pt-header .h-sku, .pt-header .h-stock { display: none; }
    .p-cell-stock { display: none; }
    .p-cell-action .btn-add { width: 28px; height: 28px; font-size: 1rem; border-radius: 6px; }
}

/* ---- PANIER (gauche) ---- */
.pos-cart {
    width: 380px;
    max-height: calc(100vh - 57px);
    display: flex;
    flex-direction: column;
    background: var(--card-bg);
    order: 1;
    overflow: hidden;
    border-right: 1px solid var(--border);
}
.cart-header {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.cart-header h6 { font-family: "Manrope", sans-serif; font-weight: 700; margin: 0; font-size: 0.88rem; color: var(--text); }

.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 0.4rem;
    min-height: 0;
    max-height: 40vh;
}
.cart-items::-webkit-scrollbar { width: 4px; }
.cart-items::-webkit-scrollbar-thumb { background: #BDBDBD; border-radius: 4px; }
.cart-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.35rem;
    border-bottom: 1px solid var(--border);
}
.cart-item-name { font-size: 0.78rem; font-weight: 500; flex: 1; min-width: 0; color: var(--text); }
.cart-item-price { font-size: 0.72rem; color: var(--text-muted); }
.qty-ctrl { display: flex; align-items: center; gap: 0.2rem; }
.qty-btn { width: 20px; height: 20px; border: 1px solid var(--border); border-radius: 4px; background: var(--body-bg); color: var(--text-muted); cursor: pointer; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; transition: all 0.1s; }
.qty-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
.qty-val { width: 24px; text-align: center; font-size: 0.78rem; font-weight: 600; color: var(--text); }
.cart-item-total { font-size: 0.78rem; font-weight: 600; width: 65px; text-align: right; color: var(--text); }
.cart-remove { color: #EF4444; cursor: pointer; font-size: 0.8rem; }

.cart-totals {
    padding: 0.6rem 0.75rem;
    border-top: 1px solid var(--border);
    background: var(--body-bg);
    flex-shrink: 0;
}
.total-row { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--text); line-height: 1.5; }
.total-row.grand { font-family: "Manrope", sans-serif; font-size: 0.92rem; font-weight: 700; color: var(--accent); margin-top: 0.35rem; padding-top: 0.35rem; border-top: 1px solid var(--border); }

.cart-actions { padding: 0.45rem 0.75rem; border-top: 1px solid var(--border); flex-shrink: 0; }
.btn-checkout { width: 100%; padding: 0.5rem; border: none; border-radius: 10px; background: linear-gradient(135deg, var(--accent), #8B90FA); color: #fff; font-family: "Manrope", sans-serif; font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.15s; }
.btn-checkout:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(108,114,245,0.4); }
.btn-checkout:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

/* Payment modal */
.payment-option { border: 2px solid var(--border); border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.15s; color: var(--text-muted); }
.payment-option.active { border-color: var(--accent); background: rgba(99,102,241,0.08); color: var(--text); }
.payment-option i { font-size: 1.4rem; color: var(--text-muted); }
.payment-option.active i { color: var(--accent); }
.payment-option span { display: block; font-size: 0.78rem; margin-top: 0.25rem; font-weight: 500; }
.payment-sub-option { border: 2px solid var(--border); border-radius: 10px; padding: 0.6rem 0.4rem; cursor: pointer; text-align: center; transition: all 0.15s; color: var(--text-muted); }
.payment-sub-option.active { border-color: var(--accent); background: rgba(99,102,241,0.08); color: var(--text); }
.payment-sub-option span { display: block; }

@media (max-width: 768px) {
    .pos-cart { width: 100%; position: fixed; bottom: 0; left: 0; right: 0; height: 55vh; z-index: 800; transform: translateY(calc(100% - 56px)); transition: transform 0.3s; border-top: 2px solid var(--border); order: 0; }
    .pos-cart.open { transform: translateY(0); }
    .pos-container { flex-direction: column; height: auto; overflow: auto; }
    .pos-catalog { height: calc(100vh - 57px - 56px); order: 0; }
}

</style>';

require_once __DIR__ . '/layout_top.php';
?>

<?php if ($caisseRequired): ?>
<!-- ============================================================
     CAISSE PICKER -- affiché uniquement aux caissiers sans caisse
     ============================================================ -->
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:2.5rem;max-width:460px;width:100%;text-align:center;box-shadow:var(--shadow-md)">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem">
            <i class="bi bi-cash-register" style="font-size:2rem;color:var(--accent)"></i>
        </div>
        <h5 style="font-family:Manrope,sans-serif;font-weight:700;color:var(--text);margin-bottom:.5rem">Selectionnez une caisse</h5>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.5rem">Vous devez choisir une caisse avant de pouvoir effectuer des ventes.</p>
        <div id="caissePickerList" style="display:flex;flex-direction:column;gap:.6rem">
            <div class="text-center text-muted" style="font-size:.85rem">
                <div class="spinner-border spinner-border-sm mb-2" role="status"></div><br>Chargement des caisses...
            </div>
        </div>
    </div>
</div>

<script>
function loadCaissePicker() {
    fetch(BASE_URL + '/controllers/caisse_ajax.php?action=list')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('caissePickerList');
            if (!data.success || !data.data || !data.data.length) {
                container.innerHTML = '<div class="alert alert-warning m-0" style="font-size:.85rem">Aucune caisse disponible. Contactez un administrateur.</div>';
                return;
            }
            container.innerHTML = data.data.map(c =>
                '<button class="caisse-option" data-caisse-id="' + c.id + '"' +
                ' style="background:var(--body-bg);border:2px solid var(--border);border-radius:12px;padding:1rem 1.25rem;cursor:pointer;text-align:left;transition:all 0.15s;display:flex;align-items:center;gap:.75rem"' +
                ' onmouseenter="this.style.borderColor=\'var(--accent)\';this.style.background=\'rgba(99,102,241,0.06)\'"' +
                ' onmouseleave="this.style.borderColor=\'var(--border)\';this.style.background=\'var(--body-bg)\'"' +
                '>' +
                '<i class="bi bi-cash-register" style="font-size:1.4rem;color:var(--accent)"></i>' +
                '<span style="font-weight:600;font-size:.9rem;color:var(--text)">' + escHtml(c.name) + '</span>' +
                '</button>'
            ).join('');

            container.querySelectorAll('.caisse-option').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var caisseId = this.dataset.caisseId;
                    fetch(BASE_URL + '/controllers/caisse_ajax.php?action=select&caisse_id=' + caisseId)
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert(res.message || 'Erreur lors de la selection');
                            }
                        });
                });
            });
        });
}
loadCaissePicker();
</script>

<?php else: ?>

<div class="pos-container">
    <!-- PANIER GAUCHE -->
    <div class="pos-cart" id="posCart">
<div class="cart-header">
            <h6><i class="bi bi-bag me-2 text-primary"></i>Panier <span class="badge bg-primary ms-1" id="cartCount">0</span></h6>
            <div class="d-flex gap-1">
                <select id="customerSelect" style="font-size:.75rem;border:1px solid var(--border);border-radius:6px;padding:0.25rem 0.4rem;max-width:130px;outline:none">
                    <option value="">Client anonyme</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="clearCart()" class="btn btn-sm btn-outline-danger" style="padding:.25rem .5rem;font-size:.75rem">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>

        <div class="cart-items" id="cartItems">
            <div class="text-center py-2 text-muted" style="font-size:.78rem">
                <i class="bi bi-bag" style="font-size:1.2rem;opacity:.3"></i><br>Panier vide
            </div>
        </div>

        <div class="cart-totals">
            <div class="total-row"><span>Sous-total</span><span id="subtotalDisplay">0 FCFA</span></div>
            <div class="total-row">
                <span>Remise <input type="number" id="discountPct" min="0" max="100" value="0" style="width:36px;border:1px solid var(--border);border-radius:4px;padding:0 4px;font-size:.75rem;text-align:center"> %</span>
                <span id="discountDisplay" style="color:#EF4444">-0 FCFA</span>
            </div>
            <div class="total-row"><span>TVA (<?= e($appSettings['tax_rate'] ?? '19.25') ?>%)</span><span id="taxDisplay">0 FCFA</span></div>
            <div class="total-row grand"><span>TOTAL</span><span id="totalDisplay">0 FCFA</span></div>
        </div>

        <div class="cart-actions">
            <div class="d-flex gap-2 mb-1">
                <input type="number" id="paidAmount" placeholder="Montant recu..." class="form-control form-control-sm" style="border-radius:8px;padding:.35rem .5rem;font-size:.8rem">
                <span class="badge bg-success d-flex align-items-center" id="changeDisplay" style="font-size:.7rem;white-space:nowrap">Monnaie: 0</span>
            </div>
            <button class="btn-checkout" id="checkoutBtn" disabled onclick="openPaymentModal()">
                <i class="bi bi-credit-card me-1"></i>Encaisser
            </button>
        </div>
    </div>

    <!-- CATALOGUE DROITE -->
    <div class="pos-catalog">
        <div class="pos-toolbar">
            <div class="pos-toolbar-row">
                <div class="search-field">
                    <input type="text" id="searchInput" placeholder="Rechercher ou scanner..." autocomplete="off" style="padding-right:80px">
                    <i class="bi bi-search search-ico"></i>
                    <span id="scanBadge" style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#10B981;color:#fff;font-size:.66rem;padding:2px 8px;border-radius:10px;font-weight:600">
                        <i class="bi bi-lightning-charge-fill"></i> Scan
                    </span>
                </div>
                <select id="warehouseSelect" class="wh-select">
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>" <?= $wh['id'] == $warehouseId ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($activeCaisse): ?>
                <span class="badge" style="background:rgba(99,102,241,0.12);color:var(--accent);font-size:.72rem;padding:5px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;white-space:nowrap">
                    <i class="bi bi-cash-register"></i> <?= e($activeCaisse['name']) ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="pos-cats" style="margin-top:0.45rem">
                <button class="cat-tab active" data-cat="0">Tout</button>
                <?php foreach ($categories as $cat): ?>
                <button class="cat-tab" data-cat="<?= $cat['id'] ?>"><?= e($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TABLEAU PRODUITS -->
        <div class="pt-header" id="productsHeader">
            <span></span>
            <span>Produit</span>
            <span class="h-sku">SKU / Code</span>
            <span class="h-stock">Stock</span>
            <span>Prix</span>
            <span></span>
        </div>
        <div class="pos-products" id="productsGrid">
            <div class="text-center py-4 text-muted" style="font-size:.85rem">
                <i class="bi bi-grid" style="font-size:1.6rem;opacity:.2;display:block;margin-bottom:.4rem"></i>Chargement...
            </div>
        </div>
    </div>
</div>

<!-- Cart toggle (mobile) -->
<button onclick="document.getElementById('posCart').classList.toggle('open')"
    style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:799;background:var(--accent);color:#fff;border:none;padding:1rem;font-family:Manrope,sans-serif;font-weight:700"
    id="mobileCartToggle">
    Voir panier (<span id="cartCountMobile">0</span>)
</button>

<!-- PAYMENT MODAL -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700">Finaliser la Vente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mode de paiement</label>
                    <div class="row g-2">
                        <div class="col-3">
                            <div class="payment-option active" data-method="cash" onclick="selectPayment('cash')">
                                <i class="bi bi-cash" style="color:#10B981"></i>
                                <span>Especes</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="payment-option" data-method="mobile_money" onclick="selectPayment('mobile_money')">
                                <i class="bi bi-phone" style="color:#6366F1"></i>
                                <span>Mobile</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="payment-option" data-method="card" style="opacity:.4;cursor:not-allowed;pointer-events:none">
                                <i class="bi bi-credit-card" style="color:#F59E0B"></i>
                                <span>Carte</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="payment-option" data-method="credit" onclick="selectPayment('credit')">
                                <i class="bi bi-clock" style="color:#EF4444"></i>
                                <span>Credit</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sub-options: Mobile Money -->
                <div class="mb-3 d-none" id="mobileSubOptions">
                    <label class="form-label fw-semibold" style="font-size:.82rem">Operateur Mobile Money</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="payment-sub-option active" data-mobile="orange_money" onclick="selectMobileProvider('orange_money')">
                                <div style="width:28px;height:28px;border-radius:50%;background:#FF6600;display:flex;align-items:center;justify-content:center;margin:0 auto .3rem">
                                    <i class="bi bi-phone" style="color:#fff;font-size:.85rem"></i>
                                </div>
                                <span style="font-size:.78rem;font-weight:500">Orange Money</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="payment-sub-option" data-mobile="momo" onclick="selectMobileProvider('momo')">
                                <div style="width:28px;height:28px;border-radius:50%;background:#FCC837;display:flex;align-items:center;justify-content:center;margin:0 auto .3rem">
                                    <i class="bi bi-phone" style="color:#333;font-size:.85rem"></i>
                                </div>
                                <span style="font-size:.78rem;font-weight:500">MoMo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sub-options: Credit client -->
                <div class="mb-3 d-none" id="creditSubOptions">
                    <label class="form-label fw-semibold" style="font-size:.82rem">Client pour le credit</label>
                    <select id="creditCustomerSelect" class="form-select form-select-sm" style="border-radius:8px">
                        <option value="">-- Choisir un client --</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> -- <?= e($c['phone'] ?? 'Sans tel.') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-danger mt-1 d-none" id="creditAnonError" style="font-size:.78rem">
                        <i class="bi bi-exclamation-circle me-1"></i>Un client anonyme ne peut pas payer a credit.
                    </div>
                </div>

                <div class="p-3 rounded-3 mb-3" style="background:var(--body-bg)">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.9rem"><span>Total a payer</span><strong id="modalTotal">0 FCFA</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.9rem"><span>Montant recu</span><strong id="modalPaid">0 FCFA</strong></div>
                    <div class="d-flex justify-content-between" style="font-size:.9rem;color:var(--accent2)"><span>Monnaie a rendre</span><strong id="modalChange">0 FCFA</strong></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (optionnel)</label>
                    <textarea id="saleNote" class="form-control" rows="2" style="resize:none;font-size:.875rem" placeholder="Remarques..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between">
                <label class="d-flex align-items-center gap-1" style="font-size:.78rem;color:var(--text-muted);cursor:pointer">
                    <input type="checkbox" id="modalChangeRefunded" checked style="accent-color:var(--accent);width:14px;height:14px;cursor:pointer">
                    <span>Reliquat rembourse</span>
                </label>
                <div>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary px-4" id="confirmSaleBtn" onclick="processSale()" style="border-radius:8px">
                        <i class="bi bi-check-circle me-2"></i>Confirmer la vente
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TICKET MODAL -->
<div class="modal fade" id="ticketModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:340px">
        <div class="modal-content" style="border-radius:12px;border:none;background:#fff;color:#1a2236">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700;font-size:.95rem">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>Vente validée
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="onTicketClose()"></button>
            </div>
            <div class="modal-body pt-2" id="ticketModalBody">
                <div class="text-center py-3 text-muted">Chargement du ticket...</div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button class="btn btn-primary" id="btnPrintTicket" onclick="printTicket()" style="border-radius:8px;font-size:.85rem">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
                <button class="btn btn-outline-secondary" onclick="onTicketClose()" style="border-radius:8px;font-size:.85rem">
                    <i class="bi bi-x-circle me-1"></i>Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Ticket dans le modal */
#ticketModalBody .ticket-wrapper {
    font-family: 'DM Mono', monospace;
    font-size: 0.78rem;
    background: #fff;
    padding: 0.5rem;
}
#ticketModalBody .ticket-header {
    text-align: center;
    border-bottom: 1px dashed #999;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
}
#ticketModalBody .ticket-row {
    display: flex;
    justify-content: space-between;
}
#ticketModalBody .ticket-divider {
    border-top: 1px dashed #999;
    margin: 0.4rem 0;
}
#ticketModalBody .ticket-total {
    font-weight: bold;
    font-size: 0.9rem;
}
@media print {
    body * { visibility: hidden; }
    #ticketModal { position: absolute; left: 0; top: 0; visibility: visible; }
    #ticketModal .modal-dialog { max-width: 300px; margin: 0 auto; }
    #ticketModal .modal-content { box-shadow: none; border: none; }
    #ticketModal .modal-header,
    #ticketModal .modal-footer { display: none !important; }
    #ticketModal .modal-body { padding: 0; }
    #ticketModalBody * { visibility: visible; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const BASE_URL = "<?= BASE_URL ?>";
const STORE_ID = <?= currentStoreId() ?>;
let WAREHOUSE_ID = <?= (int)$warehouseId ?>;
const TAX_RATE = <?= (float)($appSettings['tax_rate'] ?? 19.25) / 100 ?>;
let cart = [];
let products = [];
const CART_KEY = 'pos_cart_' + STORE_ID + '_' + WAREHOUSE_ID;
let selectedPayment = 'cash';
let selectedMobileProvider = 'orange_money';
let currentCategory = 0;
let searchTimeout;
let posKeystrokeTimes = [];
let posIsScanning = false;

// ---- Chargement produits ----
function loadProducts(search = '', categoryId = 0) {
    fetch(`${BASE_URL}/controllers/product_ajax.php?action=search_pos&store=${STORE_ID}&warehouse=${WAREHOUSE_ID}&search=${encodeURIComponent(search)}&category=${categoryId}`)
        .then(r => r.json())
        .then(data => {
            products = data.data || [];
            renderProducts();
        });
}

// Couleur pastel par nom de produit (pour placeholder)
function productColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
    const h = Math.abs(hash % 360);
    const h2 = (h + 25) % 360;
    return `linear-gradient(135deg, hsl(${h}, 55%, 78%), hsl(${h2}, 60%, 68%))`;
}

// ---- Rendu LISTE (tableau ergonomique) ----
function renderProducts() {
    const grid = document.getElementById('productsGrid');
    const headerEl = document.getElementById('productsHeader');
    if (!products.length) {
        grid.innerHTML = '<div class="text-center py-4 text-muted" style="font-size:.85rem"><i class="bi bi-search" style="font-size:1.6rem;opacity:.2;display:block;margin-bottom:.4rem"></i>Aucun produit</div>';
        return;
    }

    const hasAnyImage = products.some(p => p.image && p.image.trim() !== '');
    const gridCols = hasAnyImage
        ? '48px 1fr 100px 100px 100px 80px'
        : '1fr 100px 100px 100px 80px';

    headerEl.style.gridTemplateColumns = gridCols;
    headerEl.innerHTML = `${hasAnyImage ? '<span>Img</span>' : ''}
        <span>Produit</span>
        <span class="h-sku text-end">Stock</span>
        <span class="h-stock text-end">Prix</span>
        <span>Action</span>`;

    grid.innerHTML = products.map(p => {
        const qty = parseFloat(p.stock_qty);
        const inStock = qty > 0;
        let stockClass, stockLabel;
        if (qty <= 0)      { stockClass = 'out'; stockLabel = 'Rupture'; }
        else if (qty <= 5) { stockClass = 'low'; stockLabel = qty + ' u.'; }
        else               { stockClass = 'ok';  stockLabel = qty + ' u.'; }

        const imgUrl = p.image ? `${BASE_URL}${p.image.startsWith('/') ? '' : '/'}${p.image}` : '';
        const visual = imgUrl
            ? `<img src="${imgUrl}" alt="${escHtml(p.name)}" loading="lazy">`
            : `<i class="bi bi-box-seam p-icon"></i>`;
        const barcode = p.barcode || p.sku || '-';

        return `<div class="p-row${!inStock ? ' oos' : ''}" data-pid="${p.id}" style="grid-template-columns: ${gridCols}" onclick="${inStock ? `addToCart(${p.id})` : ''}">
            ${hasAnyImage ? `<div class="p-cell-img" style="${!imgUrl ? 'background:' + productColor(p.name) : ''}">${visual}</div>` : ''}
            <div class="p-cell-name">
                <div class="p-name">${escHtml(p.name)}</div>
                <div class="p-sku">${escHtml(barcode)}</div>
            </div>
            <div class="p-cell-stock"><span class="stock-badge ${stockClass}">${stockLabel}</span></div>
            <div class="p-cell-price">${formatMoney(p.selling_price)}</div>
            <div class="p-cell-action">
                <button class="btn-add" ${!inStock ? 'disabled' : ''} onclick="event.stopPropagation();${inStock ? `addToCart(${p.id})` : ''}"><i class="bi bi-plus-lg"></i></button>
            </div>
        </div>`;
    }).join('');
}

// Flash visuel quand on ajoute
function flashCard(pid) {
    const row = document.querySelector(`.p-row[data-pid="${pid}"]`);
    if (!row) return;
    row.classList.add('flash');
    setTimeout(() => row.classList.remove('flash'), 400);
}

function addToCart(productId) {
    const product = products.find(p => p.id == productId);
    if (!product) return;
    const existing = cart.find(i => i.product_id == productId);
    const maxStock = parseFloat(product.stock_qty);
    if (existing) {
        if (existing.quantity >= maxStock) { showToast('Stock insuffisant', 'warning'); return; }
        existing.quantity++;
        existing.total = existing.quantity * existing.unit_price;
    } else {
        cart.push({
            product_id: product.id,
            product_name: product.name,
            barcode: product.barcode,
            quantity: 1,
            unit_price: parseFloat(product.selling_price),
            cost_price: parseFloat(product.cost_price),
            total: parseFloat(product.selling_price),
            max_stock: maxStock
        });
    }
    flashCard(productId);
    renderCart();
}

function renderCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    const container = document.getElementById('cartItems');
    const count = cart.reduce((s, i) => s + i.quantity, 0);
    document.getElementById('cartCount').textContent = count;
    document.getElementById('cartCountMobile').textContent = count;

    if (!cart.length) {
        container.innerHTML = '<div class="text-center py-2 text-muted" style="font-size:.78rem"><i class="bi bi-bag" style="font-size:1.2rem;opacity:.3"></i><br>Panier vide</div>';
        document.getElementById('checkoutBtn').disabled = true;
        updateTotals();
        return;
    }

    container.innerHTML = cart.map((item, idx) => `
        <div class="cart-item">
            <div style="min-width:0;flex:1">
                <div class="cart-item-name text-truncate">${escHtml(item.product_name)}</div>
                <div class="cart-item-price">${formatMoney(item.unit_price)}</div>
            </div>
            <div class="qty-ctrl">
                <button class="qty-btn" onclick="updateQty(${idx}, -1)">-</button>
                <span class="qty-val">${item.quantity}</span>
                <button class="qty-btn" onclick="updateQty(${idx}, 1)">+</button>
            </div>
            <div class="cart-item-total">${formatMoney(item.total)}</div>
            <i class="bi bi-x-circle cart-remove" onclick="removeItem(${idx})"></i>
        </div>
    `).join('');
    updateTotals();
}

function updateQty(idx, delta) {
    cart[idx].quantity = Math.max(1, Math.min(cart[idx].max_stock, cart[idx].quantity + delta));
    cart[idx].total = cart[idx].quantity * cart[idx].unit_price;
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function clearCart() {
    cart = [];
    localStorage.removeItem(CART_KEY);
    document.getElementById('paidAmount').value = '';
    document.getElementById('discountPct').value = '0';
    renderCart();
}

function updateTotals() {
    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    const discPct = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * discPct / 100;
    const taxable = subtotal - discount;
    const tax = taxable * TAX_RATE;
    const total = taxable + tax;
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const change = paid - total;

    document.getElementById('subtotalDisplay').textContent = formatMoney(subtotal);
    document.getElementById('discountDisplay').textContent = `-${formatMoney(discount)}`;
    document.getElementById('taxDisplay').textContent = formatMoney(tax);
    document.getElementById('totalDisplay').textContent = formatMoney(total);
    document.getElementById('changeDisplay').textContent = `Monnaie: ${formatMoney(Math.max(0, change))}`;
    document.getElementById('changeDisplay').className = `badge ${change >= 0 ? 'bg-success' : 'bg-danger'} d-flex align-items-center`;

    const paidInput = document.getElementById('paidAmount');
    const btn = document.getElementById('checkoutBtn');
    btn.disabled = !cart.length || !paidInput.value || paid <= 0;
}

function openPaymentModal() {
    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    const discPct = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * discPct / 100;
    const taxable = subtotal - discount;
    const tax = taxable * TAX_RATE;
    const total = taxable + tax;
    const paid = parseFloat(document.getElementById('paidAmount').value) || total;

    document.getElementById('modalTotal').textContent = formatMoney(total);
    document.getElementById('modalPaid').textContent = formatMoney(paid);
    const change = Math.max(0, paid - total);
    document.getElementById('modalChange').textContent = formatMoney(change);
    document.getElementById('modalChangeRefunded').checked = true;
    document.getElementById('modalChangeRefunded').parentElement.style.display = change > 0 ? 'flex' : 'none';
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function selectPayment(method) {
    selectedPayment = method;
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-method="${method}"]`).classList.add('active');

    // Toggle sub-options
    document.getElementById('mobileSubOptions').classList.toggle('d-none', method !== 'mobile_money');
    document.getElementById('creditSubOptions').classList.toggle('d-none', method !== 'credit');

    // Credit: check if anonymous
    if (method === 'credit') {
        validateCreditCustomer();
    }
    updateConfirmBtn();
}

function selectMobileProvider(provider) {
    selectedMobileProvider = provider;
    document.querySelectorAll('.payment-sub-option').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-mobile="${provider}"]`).classList.add('active');
}

function validateCreditCustomer() {
    const creditCust = document.getElementById('creditCustomerSelect').value;
    const errEl = document.getElementById('creditAnonError');
    errEl.classList.toggle('d-none', creditCust !== '');
    updateConfirmBtn();
}

function updateConfirmBtn() {
    const btn = document.getElementById('confirmSaleBtn');
    if (selectedPayment === 'credit') {
        const creditCust = document.getElementById('creditCustomerSelect').value;
        btn.disabled = !creditCust;
    } else {
        btn.disabled = false;
    }
}

document.getElementById('creditCustomerSelect')?.addEventListener('change', validateCreditCustomer);

function processSale() {
    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    const discPct = parseFloat(document.getElementById('discountPct').value) || 0;
    const discount = subtotal * discPct / 100;
    const taxable = subtotal - discount;
    const tax = taxable * TAX_RATE;
    const total = taxable + tax;
    const paid = parseFloat(document.getElementById('paidAmount').value) || total;
    const customerId = document.getElementById('customerSelect').value;
    const note = document.getElementById('saleNote').value;

    // Credit: require a named customer
    let saleCustomerId = customerId || null;
    if (selectedPayment === 'credit') {
        const creditCust = document.getElementById('creditCustomerSelect').value;
        if (!creditCust) { showToast('Selectionnez un client pour le credit', 'danger'); return; }
        saleCustomerId = creditCust;
    }

    // Mobile Money: set payment_method with provider
    let paymentMethod = selectedPayment;
    if (selectedPayment === 'mobile_money') {
        paymentMethod = selectedMobileProvider; // 'orange_money' or 'momo'
    }

    const changeRefunded = document.getElementById('modalChangeRefunded').checked;
    const changeAmount = Math.max(0, paid - total);

    const payload = {
        store_id: STORE_ID,
        warehouse_id: WAREHOUSE_ID,
        caisse_id: <?= currentCaisseId() > 0 ? currentCaisseId() : 'null' ?>,
        customer_id: saleCustomerId,
        subtotal: subtotal.toFixed(2),
        discount_amount: discount.toFixed(2),
        tax_amount: tax.toFixed(2),
        total_amount: total.toFixed(2),
        paid_amount: paid.toFixed(2),
        change_amount: changeRefunded ? 0 : changeAmount.toFixed(2),
        change_refunded: changeRefunded,
        payment_method: paymentMethod,
        notes: note,
        items: cart.map(i => ({
            product_id: i.product_id,
            product_name: i.product_name,
            barcode: i.barcode,
            quantity: i.quantity,
            unit_price: i.unit_price,
            cost_price: i.cost_price,
            discount_percent: discPct,
            discount_amount: (i.total * discPct / 100).toFixed(2),
            total_price: i.total.toFixed(2)
        }))
    };

    fetch(`${BASE_URL}/controllers/sale_controller.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            clearCart();
            loadProducts(document.getElementById('searchInput').value, currentCategory);
            const saleId = data.data?.sale_id;
            if (saleId) {
                // Afficher le ticket en modal
                showTicketModal(saleId);
            } else {
                showToast('Vente enregistree avec succes !', 'success');
            }
        } else {
            showToast(data.message || 'Erreur lors de la vente', 'danger');
        }
    })
    .catch(() => showToast('Erreur reseau', 'danger'));
}

// ---- Ticket Modal ----
let ticketModalInstance = null;
let currentSaleId = null;

function showTicketModal(saleId) {
    currentSaleId = saleId;
    document.getElementById('ticketModalBody').innerHTML = '<div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Chargement...</div>';
    ticketModalInstance = new bootstrap.Modal(document.getElementById('ticketModal'));
    ticketModalInstance.show();

    fetch(`${BASE_URL}/controllers/invoice_ajax.php?action=ticket&id=${saleId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ticketModalBody').innerHTML = data.data.html;
            } else {
                document.getElementById('ticketModalBody').innerHTML = '<div class="alert alert-danger m-0">Erreur lors du chargement du ticket.</div>';
            }
        })
        .catch(() => {
            document.getElementById('ticketModalBody').innerHTML = '<div class="alert alert-danger m-0">Erreur réseau.</div>';
        });
}

function printTicket() {
    window.print();
}

function onTicketClose() {
    if (ticketModalInstance) {
        ticketModalInstance.hide();
    }
    searchInput.focus();
}

// After printing, close the modal and return focus to POS
window.addEventListener('afterprint', () => {
    if (ticketModalInstance) {
        ticketModalInstance.hide();
    }
    searchInput.focus();
});

// ---- Categories ----
document.querySelectorAll('.cat-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentCategory = parseInt(this.dataset.cat);
        loadProducts(document.getElementById('searchInput').value, currentCategory);
    });
});

// ---- Scanner + Recherche ----
const searchInput = document.getElementById('searchInput');
const scanBadge = document.getElementById('scanBadge');
let scanCompleteTimer = null;

function resetScanState() {
    posKeystrokeTimes = [];
    posIsScanning = false;
    scanBadge.style.display = 'none';
    searchInput.value = '';
}

function triggerScanAdd(barcode) {
    lookupAndAddToCart(barcode);
}

searchInput.addEventListener('keydown', function(e) {
    const now = Date.now();
    posKeystrokeTimes.push(now);
    if (posKeystrokeTimes.length > 20) posKeystrokeTimes = posKeystrokeTimes.slice(-20);

    if (posKeystrokeTimes.length >= 5) {
        const last5 = posKeystrokeTimes.slice(-5);
        const avgInterval = (last5[4] - last5[0]) / 4;
        posIsScanning = avgInterval < 50;
    }

    scanBadge.style.display = posIsScanning ? '' : 'none';

    if (e.key === 'Enter') {
        e.preventDefault();
        const val = this.value.trim();
        if (!val) return;
        clearTimeout(scanCompleteTimer);
        scanCompleteTimer = null;

        if (posIsScanning || /^\d{4,}$/.test(val)) {
            triggerScanAdd(val);
        } else {
            loadProducts(val, currentCategory);
        }
        return;
    }

    if (posIsScanning) {
        clearTimeout(scanCompleteTimer);
        scanCompleteTimer = setTimeout(() => {
            const val = searchInput.value.trim();
            if (val.length >= 4) {
                triggerScanAdd(val);
            }
            scanCompleteTimer = null;
        }, 80);
    }
});

searchInput.addEventListener('input', function() {
    const val = this.value.trim();
    clearTimeout(searchTimeout);

    if (posIsScanning) return;
    if (/^\d{4,}$/.test(val)) return;

    searchTimeout = setTimeout(() => loadProducts(val, currentCategory), 250);
});

searchInput.addEventListener('blur', function() {
    if (posIsScanning && scanCompleteTimer) {
        clearTimeout(scanCompleteTimer);
        scanCompleteTimer = null;
    }
});

function lookupAndAddToCart(barcode) {
    fetch(`${BASE_URL}/controllers/product_ajax.php?action=barcode&barcode=${encodeURIComponent(barcode)}&store=${STORE_ID}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const p = res.data;
                fetch(`${BASE_URL}/controllers/product_ajax.php?action=search_pos&store=${STORE_ID}&warehouse=${WAREHOUSE_ID}&search=${encodeURIComponent(p.barcode || p.name)}`)
                    .then(r2 => r2.json())
                    .then(res2 => {
                        const match = (res2.data || []).find(x => x.id == p.id);
                        const stockQty = match ? parseFloat(match.stock_qty) : 0;
                        addToCartFromScan(p, stockQty);
                        searchInput.value = '';
                        posKeystrokeTimes = [];
                        posIsScanning = false;
                        scanBadge.style.display = 'none';
                    });
            } else {
                showToast('Produit non trouve : ' + barcode, 'warning');
                searchInput.value = '';
                posKeystrokeTimes = [];
                posIsScanning = false;
                scanBadge.style.display = 'none';
            }
        })
        .catch(() => {
            showToast('Erreur reseau', 'danger');
            posKeystrokeTimes = [];
            posIsScanning = false;
            scanBadge.style.display = 'none';
        });
}

function addToCartFromScan(product, stockQty) {
    const existing = cart.find(i => i.product_id == product.id);
    if (existing) {
        if (existing.quantity >= stockQty) {
            showToast('Stock insuffisant pour ' + product.name, 'warning');
            return;
        }
        existing.quantity++;
        existing.total = existing.quantity * existing.unit_price;
    } else {
        if (stockQty <= 0) {
            showToast(product.name + ' : en rupture de stock', 'warning');
            return;
        }
        cart.push({
            product_id: product.id,
            product_name: product.name,
            barcode: product.barcode,
            quantity: 1,
            unit_price: parseFloat(product.selling_price),
            cost_price: parseFloat(product.cost_price),
            total: parseFloat(product.selling_price),
            max_stock: stockQty
        });
    }
    flashCard(product.id);
    renderCart();
    showToast(product.name + ' ajoute', 'success');
}

// ---- Warehouse ----
document.getElementById('warehouseSelect').addEventListener('change', function() {
    WAREHOUSE_ID = parseInt(this.value);
    loadProducts(document.getElementById('searchInput').value, currentCategory);
});

// ---- Discount & paid ----
document.getElementById('discountPct').addEventListener('input', updateTotals);
document.getElementById('paidAmount').addEventListener('input', updateTotals);

// ---- Helpers ----
function formatMoney(v) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(v)) + ' FCFA';
}
function escHtml(str) {
    const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}

// ---- Init ----
const savedCart = localStorage.getItem(CART_KEY);
if (savedCart) {
    try { cart = JSON.parse(savedCart); } catch(e) { cart = []; }
    if (cart.length) renderCart();
}
loadProducts();

// Mobile cart toggle visibility
if (window.innerWidth <= 768) {
    document.getElementById('mobileCartToggle').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>
<?php endif; ?>
