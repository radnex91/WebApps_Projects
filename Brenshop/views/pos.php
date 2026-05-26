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

if (!$warehouseId && !empty($warehouses)) {
    $warehouseId = $warehouses[0]['id'];
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT p.id, p.name, p.barcode, p.selling_price, p.cost_price, p.image, p.unit, p.category_id, COALESCE(s.quantity,0) as stock_qty, COALESCE(c.name, '') as cat_name FROM products p LEFT JOIN stock s ON p.id=s.product_id AND s.warehouse_id=? LEFT JOIN categories c ON p.category_id=c.id WHERE p.store_id=? AND p.is_active=1 ORDER BY p.name ASC");
$stmt->execute([$warehouseId, $storeId]);
$products = $stmt->fetchAll();

$caisseSessionOpen = hasCaisseSessionOpen();
$activeSession = null;
$activeCaisse = null;
if ($caisseSessionOpen) {
    $sessionModel = new CaisseSession();
    $activeSession = $sessionModel->getActiveSession(currentCaisseId());
    $caisseModel = new Caisse();
    $activeCaisse = $caisseModel->find(currentCaisseId());
}

$catColors = [];
$colorPalette = ['#d97706','#0ea5e9','#f59e0b','#dc2626','#7c3aed','#ea580c','#16a34a','#e91e63','#8b5cf6','#0891b2','#92400e','#059669','#ca8a04','#9333ea','#0d9488'];
$ci = 0;
foreach ($categories as $cat) {
    $catColors[$cat['id']] = $colorPalette[$ci % count($colorPalette)];
    $ci++;
}
if (!isset($catColors[0])) $catColors[0] = '#d97706';

$currentStore = (new Store())->find($storeId);

$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/pos.css">';
$extraScript = '<script>document.body.classList.add("pos-page");</script><script src="' . BASE_URL . '/assets/js/pos.js"></script>';

require_once __DIR__ . '/layout_top.php';
?>
<?php if (!$caisseSessionOpen): ?>
<!-- ═══════════════════════════════════════════════════════════
     CAISSE SELECTION (no active session)
     ═══════════════════════════════════════════════════════════ -->
<div class="pos-welcome">
    <div>
        <div class="pos-welcome-icon">
            <i class="bi bi-cash-register"></i>
        </div>
        <h4>Bienvenue sur le POS</h4>
        <p>Vous devez ouvrir une caisse avant de pouvoir effectuer des ventes.</p>
        <button class="btn-open-caisse" onclick="POS.openCaisseSelection()">
            <i class="bi bi-unlock me-2"></i>Ouvrir une caisse
        </button>
    </div>
</div>

<!-- Caisse Selection Modal -->
<div class="modal fade" id="caisseSelectionModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash-register me-2"></i>Choisir une caisse</h5>
                <a href="<?= BASE_URL ?>/index.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div id="caisseCardsContainer" class="row g-2 mb-3">
                    <div class="col-12 text-center py-3" style="color:var(--text-muted)">
                        <div class="spinner-border spinner-border-sm mb-2" style="color:var(--accent)"></div>
                        <div style="font-size:.82rem">Chargement des caisses...</div>
                    </div>
                </div>
                <div id="caisseBalanceSection" style="display:none">
                    <hr style="border-top:1px solid var(--border)">
                    <label class="form-label fw-bold" style="font-size:.82rem">Fond de caisse (FCFA)</label>
                    <input type="number" id="openingBalance" class="form-control" style="font-size:1.1rem;font-weight:800" placeholder="Ex: 50000" min="0" step="1" value="0">
                    <div id="openCaisseMsg" class="mt-2" style="font-size:.82rem"></div>
                </div>
            </div>
            <div class="modal-footer" id="caisseModalFooter" style="display:none">
                <button class="btn btn-outline-secondary" onclick="POS.resetCaisseSelection()" style="font-weight:600">Changer de caisse</button>
                <button id="openCaisseBtn" class="btn btn-primary px-4" onclick="POS.openCaisse()" style="font-weight:800;background:var(--accent);border-color:var(--accent)">
                    <i class="bi bi-unlock me-1"></i>Ouvrir la caisse
                </button>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════
     POS — AFRO-BRUTAL LAYOUT
     ═══════════════════════════════════════════════════════════ -->

<!-- Mini Dashboard -->
<div class="pos-dashboard" id="posDashboard">
    <div class="dash-card"><div class="dash-label">Ventes</div><div class="dash-value">—</div></div>
    <div class="dash-card"><div class="dash-label">Revenu</div><div class="dash-value accent">—</div></div>
    <div class="dash-card"><div class="dash-label">Panier Moy</div><div class="dash-value">—</div></div>
    <div class="dash-card"><div class="dash-label">Articles</div><div class="dash-value">—</div></div>
</div>

<div class="pos-layout">

<!-- ══ CART PANEL (left) ══ -->
<div class="cart-panel" id="posCart">
    <?php if ($activeSession && $activeCaisse): ?>
    <div class="cart-session-bar">
        <div>
            <i class="bi bi-cash-register me-1"></i>
            <span class="sess-caisse"><?= e($activeCaisse['name']) ?></span>
        </div>
        <div class="sess-balance" id="sessBalance">Fond: <?= formatMoney((float)$activeSession['opening_balance']) ?></div>
        <div class="sess-actions">
            <button class="sess-btn" onclick="POS.openOperationsModal()" title="Retrait/Apport"><i class="bi bi-plus-slash-minus"></i></button>
            <button class="sess-btn danger" onclick="POS.openCloseModal()" title="Fermer caisse"><i class="bi bi-lock"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="cart-header">
        <h6><i class="bi bi-bag me-2"></i>Panier</h6>
        <span class="cart-count" id="cartCount">0</span>
    </div>

    <div class="cart-client-row">
        <select id="cartClientSelect">
            <option value="">Client anonyme</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn-clear" onclick="POS.clearCart()" title="Vider le panier">
            <i class="bi bi-trash"></i>
        </button>
    </div>

    <div class="cart-items" id="cartItems">
        <div class="cart-empty"><i class="bi bi-bag"></i>Panier vide</div>
    </div>

    <div class="cart-totals">
        <div class="total-row"><span>Sous-total</span><span id="subtotalDisplay">0 FCFA</span></div>
        <div class="discount-row">
            <span>Remise</span>
            <input type="number" id="discountPct" min="0" max="100" value="0" oninput="POS.renderCart()"> <span>%</span>
            <span class="ms-auto" id="discountDisplay" style="color:#fca5a5">-0 FCFA</span>
        </div>
        <div class="total-row"><span>TVA (<?= e($appSettings['tax_rate'] ?? '19.25') ?>%)</span><span id="taxDisplay">0 FCFA</span></div>
        <div class="total-main">
            <span>TOTAL</span>
            <span id="totalDisplay">0 FCFA</span>
        </div>
    </div>

    <div class="cart-payment" id="cartPayment">
        <div class="payment-row">
            <select id="paymentMethod" onchange="POS.onPaymentMethodChange()">
                <option value="cash">Especes</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="credit">Credit</option>
            </select>
            <select id="mobileProvider" class="d-none" style="flex:1;font-size:.75rem;padding:5px 6px;background:var(--card-bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-weight:600">
                <option value="orange_money">Orange Money</option>
                <option value="momo">MoMo</option>
            </select>
        </div>
        <div class="payment-row" id="paidRow">
            <input type="number" id="paidAmount" placeholder="Montant recu..." oninput="POS.calcChange()">
            <span class="change-display" id="changeDisplay">Monnaie: 0 FCFA</span>
        </div>
        <div class="payment-row d-none" id="creditClientRow">
            <select id="creditClientSelect" style="flex:1;font-size:.75rem;padding:5px 6px;background:var(--card-bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-weight:600">
                <option value="">-- Choisir un client --</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="cart-actions">
        <button class="btn-validate" id="validateBtn" disabled onclick="POS.processSale()">
            <i class="bi bi-check-circle me-2"></i>VALIDER LA VENTE
        </button>
    </div>
</div>

<!-- ══ CATALOG PANEL (right) ══ -->
<div class="catalog-panel">
    <div class="catalog-toolbar">
        <div class="search-row">
            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Rechercher ou scanner un produit..." autocomplete="off">
                <i class="bi bi-search"></i>
            </div>
            <select id="warehouseSelect" onchange="POS.switchWarehouse(this.value)">
                <?php foreach ($warehouses as $wh): ?>
                <option value="<?= $wh['id'] ?>" <?= $wh['id'] == $warehouseId ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="view-toggle">
                <button class="active" data-view="list" onclick="POS.setView('list')" title="Liste"><i class="bi bi-list-ul"></i></button>
                <button data-view="grid" onclick="POS.setView('grid')" title="Grille"><i class="bi bi-grid-3x3-gap"></i></button>
            </div>
        </div>
        <div class="cat-chips">
            <button class="cat-chip active" data-cat="0" onclick="POS.filterCat(0, this)">Tout</button>
            <?php foreach ($categories as $cat): ?>
            <button class="cat-chip" data-cat="<?= $cat['id'] ?>" onclick="POS.filterCat(<?= $cat['id'] ?>, this)" style="--cat-color: <?= $catColors[$cat['id']] ?>"><?= e($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="products-container" id="productsContainer">
        <!-- Product List (table) -->
        <table class="products-table" id="productsTable">
            <thead>
                <tr>
                    <th style="width:36px"></th>
                    <th>Produit</th>
                    <th style="width:100px">Stock</th>
                    <th style="width:110px">Prix</th>
                </tr>
            </thead>
            <tbody id="productsTbody">
                <?php foreach ($products as $p): $qty = (float)$p['stock_qty']; $catColor = $catColors[$p['category_id']] ?? '#d97706'; ?>
                <tr class="product-row<?= $qty<=0?' out':'' ?>" data-id="<?= $p['id'] ?>"
                    data-name="<?= e($p['name']) ?>"
                    data-price="<?= $p['selling_price'] ?>"
                    data-stock="<?= $qty ?>"
                    data-cat="<?= $p['category_id'] ?>"
                    data-search="<?= strtolower(e($p['name']).' '.e($p['barcode']??'')) ?>"
                    data-ref="<?= strtolower(e($p['barcode']??'')) ?>"
                    onclick="POS.addToCart(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>', <?= $p['selling_price'] ?>, <?= $qty ?>)">
                    <td>
                        <div class="p-img-cell" style="<?= empty($p['image']) ? 'background:linear-gradient(135deg, hsl(' . (crc32($p['name'])%360) . ',55%,78%), hsl(' . ((crc32($p['name'])+25)%360) . ',60%,68%))' : '' ?>">
                            <?php if (!empty($p['image'])): ?>
                            <img src="<?= BASE_URL ?>/<?= e($p['image']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                            <i class="bi bi-box-seam p-icon"></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="p-name-cell">
                            <div class="p-title"><?= e($p['name']) ?></div>
                            <div class="p-ref"><?= e($p['barcode'] ?? '-') ?></div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $sc = $qty<=0 ? 'out' : ($qty<=5 ? 'low' : 'ok');
                        $sl = $qty<=0 ? 'Rupture' : (round($qty).' u.');
                        ?>
                        <span class="stock-badge <?= $sc ?>"><?= $sl ?></span>
                    </td>
                    <td class="p-price-cell"><?= formatMoney((float)$p['selling_price']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Product Grid (cards) -->
        <div class="products-grid d-none" id="productsGrid">
            <?php foreach ($products as $p): $qty = (float)$p['stock_qty']; $catColor = $catColors[$p['category_id']] ?? '#d97706'; ?>
            <div class="product-tile<?= $qty<=0?' out':'' ?>" data-id="<?= $p['id'] ?>"
                 data-name="<?= e($p['name']) ?>"
                 data-price="<?= $p['selling_price'] ?>"
                 data-stock="<?= $qty ?>"
                 data-cat="<?= $p['category_id'] ?>"
                 data-search="<?= strtolower(e($p['name']).' '.e($p['barcode']??'')) ?>"
                 data-ref="<?= strtolower(e($p['barcode']??'')) ?>"
                 style="--tile-shadow: <?= $catColor ?>"
                 onclick="POS.addToCart(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>', <?= $p['selling_price'] ?>, <?= $qty ?>)">
                <div class="pt-cat" style="color:<?= $catColor ?>"><?= e($p['cat_name'] ?? '') ?></div>
                <div class="pt-name"><?= e($p['name']) ?></div>
                <div class="pt-footer">
                    <span class="pt-price"><?= formatMoney((float)$p['selling_price']) ?></span>
                    <span class="pt-stock" style="color:<?= $qty<=0 ? 'var(--danger)' : ($qty<=5 ? 'var(--accent)' : 'var(--accent2)') ?>"><?= $qty<=0 ? 'Rupture' : round($qty).' u.' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div><!-- /pos-layout -->

<!-- ═══════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════ -->

<!-- Operations Modal -->
<div class="modal fade" id="operationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-slash-minus me-2"></i>Operation sur caisse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="session-info-block mb-3" id="opsSessionInfo">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Fond de caisse</span><strong id="opsOpeningBalance">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Ventes</span><strong id="opsCashSales">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Apports</span><strong id="opsDeposits" style="color:var(--accent2)">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Retraits</span><strong id="opsWithdrawals" style="color:var(--danger)">-</strong></div>
                    <div class="d-flex justify-content-between pt-1" style="font-size:.85rem;border-top:1px solid var(--border)"><span>Solde attendu</span><strong id="opsExpected" style="color:var(--accent)">-</strong></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" style="font-size:.82rem">Type</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="pay-option active" data-optype="deposit" onclick="POS.selectOpType('deposit')">
                                <i class="bi bi-arrow-down-circle" style="color:var(--accent2);font-size:1.3rem"></i>
                                <span>Apport</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="pay-option" data-optype="withdrawal" onclick="POS.selectOpType('withdrawal')">
                                <i class="bi bi-arrow-up-circle" style="color:var(--danger);font-size:1.3rem"></i>
                                <span>Retrait</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" style="font-size:.82rem">Montant (FCFA)</label>
                    <input type="number" id="opAmount" class="form-control" style="font-size:1.1rem;font-weight:800" placeholder="0" min="1" step="1">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" style="font-size:.82rem">Motif <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="opReason" class="form-control" placeholder="Ex: Achat fournitures, Ajout monnaie...">
                </div>
                <div id="opsMsg" style="font-size:.82rem"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-weight:700">Annuler</button>
                <button class="btn btn-primary px-4" id="saveOpBtn" onclick="POS.saveOperation()" style="font-weight:800;background:var(--accent);border-color:var(--accent)">
                    <i class="bi bi-check-circle me-2"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Close Caisse Modal -->
<div class="modal fade" id="closeCaisseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-lock me-2"></i>Fermeture de Caisse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="close-summary mb-3" id="closeSummary">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem" id="closeCaisseInfo">Chargement...</div>
                    <table style="width:100%;font-size:.85rem">
                        <tr><td style="padding:2px 0">Fond de caisse</td><td class="text-end fw-bold" id="closeOpeningBalance">-</td></tr>
                        <tr><td style="padding:2px 0">Ventes</td><td class="text-end fw-bold" id="closeCashSales">-</td></tr>
                        <tr><td style="padding:2px 0">Apports</td><td class="text-end fw-bold" style="color:var(--accent2)" id="closeDeposits">-</td></tr>
                        <tr><td style="padding:2px 0">Retraits</td><td class="text-end fw-bold" style="color:var(--danger)" id="closeWithdrawals">-</td></tr>
                        <tr style="border-top:1px solid var(--border)"><td style="padding:4px 0;font-weight:900">Solde attendu</td><td class="text-end" style="font-size:.95rem;color:var(--accent);font-weight:900" id="closeExpected">-</td></tr>
                    </table>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Montant compte (FCFA) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="closeActualBalance" class="form-control" style="font-size:1.1rem;font-weight:800" placeholder="Montant physique compte" min="0" step="1" oninput="POS.updateCloseDiscrepancy()">
                </div>
                <div class="mb-3 text-center d-none" id="closeDiscrepancy"></div>
                <div class="mb-3">
                    <label class="form-label">Note (optionnel)</label>
                    <textarea id="closeNotes" class="form-control" rows="2" style="resize:none" placeholder="Remarques..."></textarea>
                </div>
                <div id="closeMsg" style="font-size:.82rem"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-weight:700">Annuler</button>
                <button class="btn btn-danger px-4" id="confirmCloseBtn" onclick="POS.confirmCloseCaisse()" style="font-weight:800">
                    <i class="bi bi-lock-fill me-2"></i>Fermer et imprimer rapport
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt / Z-Report Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:340px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalTitle"><i class="bi bi-check-circle-fill text-success me-1"></i>Vente validee</h5>
                <button type="button" class="btn-close" onclick="POS.closeReceipt()"></button>
            </div>
            <div class="modal-body pt-2" id="receiptModalBody">
                <div class="text-center py-3" style="color:var(--text-muted)">Chargement...</div>
            </div>
            <div class="modal-footer justify-content-center gap-2">
                <button class="btn btn-primary btn-sm" onclick="window.print()" style="font-weight:800">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="POS.closeReceipt()" style="font-weight:600">
                    <i class="bi bi-x-circle me-1"></i>Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ══ POS CONFIG FOR JS ══ -->
<script>
const POS_CONFIG = {
    BASE_URL: "<?= BASE_URL ?>",
    STORE_ID: <?= $storeId ?>,
    WAREHOUSE_ID: <?= (int)$warehouseId ?>,
    TAX_RATE: <?= (float)($appSettings['tax_rate'] ?? 19.25) / 100 ?>,
    CART_KEY: 'pos_cart_' + <?= $storeId ?> + '_' + <?= (int)$warehouseId ?>,
    APP_NAME: "<?= e($appSettings['app_name'] ?? 'BRENSHOP') ?>",
    STORE_NAME: "<?= e($currentStore['name'] ?? '') ?>",
    RECEIPT_FOOTER: "<?= e($appSettings['receipt_footer'] ?? 'Merci de votre confiance !') ?>",
    CAISSE_ID: <?= currentCaisseId() ?>,
    HAS_SESSION: <?= $caisseSessionOpen ? 'true' : 'false' ?>,
    CAT_COLORS: <?= json_encode($catColors) ?>
};
</script>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>