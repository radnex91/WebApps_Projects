<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('stock_view');

$pageTitle = 'Gestion du Stock';
$stockModel = new Stock();
$warehouseModel = new Warehouse();
$productModel = new Product();
$storeId = currentStoreId();
$warehouseId = (int)($_GET['wh'] ?? currentWarehouseId());

// Traitement ajout/retrait stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Requête invalide.');
        redirect(BASE_URL . '/views/stock.php');
    }
    $action     = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'remove') { requirePermission('stock_add'); }
    if ($action === 'adjust') { requirePermission('stock_adjust'); }
    $productId  = (int)($_POST['product_id'] ?? 0);
    $qty        = (float)($_POST['quantity'] ?? 0);
    $note       = sanitize($_POST['note'] ?? '');
    $unitCost   = (float)($_POST['unit_cost'] ?? 0);
    $wh         = (int)($_POST['warehouse_id'] ?? $warehouseId);
    $userId     = (int)$_SESSION['user_id'];

    if ($qty <= 0) { setFlash('error', 'Quantité invalide.'); redirect(BASE_URL . '/views/stock.php?wh=' . $wh); }

    if ($action === 'add') {
        $stockModel->addStock($productId, $wh, $qty, $userId, 'in', $note, $unitCost);
        setFlash('success', 'Stock ajouté avec succès.');
    } elseif ($action === 'remove') {
        if (!$stockModel->removeStock($productId, $wh, $qty, $userId, 'out', $note)) {
            setFlash('error', 'Stock insuffisant pour cette opération.');
        } else {
            setFlash('success', 'Stock retiré avec succès.');
        }
    } elseif ($action === 'adjust') {
        $current = $stockModel->getProductStock($productId, $wh);
        $diff = $qty - $current;
        if ($diff > 0) {
            $stockModel->addStock($productId, $wh, $diff, $userId, 'adjustment', 'Ajustement: ' . $note, $unitCost);
        } elseif ($diff < 0) {
            $stockModel->removeStock($productId, $wh, abs($diff), $userId, 'adjustment', 'Ajustement: ' . $note);
        }
        setFlash('success', 'Stock ajusté avec succès.');
    }
    redirect(BASE_URL . '/views/stock.php?wh=' . $wh);
}

$warehouses  = $warehouseModel->getByStore($storeId);
$allWh       = $warehouseModel->getAllWithStore();
$stockItems  = $stockModel->getWarehouseStock($warehouseId);
$movements   = $stockModel->getMovements($warehouseId, 30);
$lowStock    = $productModel->getLowStock($storeId);
$products    = $productModel->getByStore($storeId, 1, '', 0);
$filterLow   = isset($_GET['filter']) && $_GET['filter'] === 'low';

require_once __DIR__ . '/layout_top.php';
?>

<!-- Warehouse Tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap align-items-center justify-content-between">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach ($warehouses as $wh): ?>
        <a href="?wh=<?= $wh['id'] ?>" class="btn btn-sm <?= $wh['id'] == $warehouseId ? 'btn-primary' : 'btn-outline-secondary' ?>" style="border-radius:8px">
            <i class="bi bi-building me-1"></i><?= e($wh['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('stock_add')): ?>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addStockModal" style="border-radius:8px">
            <i class="bi bi-plus-circle me-1"></i>Entrée Stock
        </button>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#removeStockModal" style="border-radius:8px">
            <i class="bi bi-dash-circle me-1"></i>Sortie Stock
        </button>
        <?php endif; ?>
        <?php if (hasPermission('stock_adjust')): ?>
        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#adjustStockModal" style="border-radius:8px">
            <i class="bi bi-sliders me-1"></i>Ajuster
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($filterLow || !empty($lowStock)): ?>
<!-- Alertes -->
<div class="card mb-3 border-danger" style="border-color: rgba(239,68,68,0.3) !important">
    <div class="card-header-custom" style="background:rgba(239,68,68,0.05)">
        <h6 style="color:#EF4444"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alertes Stock Faible (<?= count($lowStock) ?>)</h6>
        <?php if ($filterLow): ?><a href="?wh=<?= $warehouseId ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">Voir tout</a><?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th class="ps-3"><i class="bi bi-box me-1"></i>Produit</th><th><i class="bi bi-building me-1"></i>Magasin</th><th class="text-end"><i class="bi bi-archive me-1"></i>Stock Actuel</th><th class="text-end pe-3"><i class="bi bi-exclamation-triangle me-1"></i>Seuil Alerte</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($lowStock, 0, $filterLow ? 100 : 5) as $a): ?>
                    <tr>
                        <td class="ps-3" style="font-size:.85rem"><?= e($a['name']) ?></td>
                        <td style="font-size:.82rem;color:var(--text-muted)"><?= e($a['warehouse_name'] ?? '') ?></td>
                        <td class="text-end"><span class="badge <?= (float)$a['stock_qty'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= number_format((float)$a['stock_qty']) ?></span></td>
                        <td class="text-end pe-3" style="font-size:.82rem;color:var(--text-muted)"><?= $a['min_stock_alert'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Stock actuel -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-bar-chart-steps me-2"></i>Stock actuel — <?= e($warehouses[array_search($warehouseId, array_column($warehouses, 'id'))]['name'] ?? 'Magasin') ?></h6>
                <span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent)"><?= count($stockItems) ?> produits</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:500px;overflow-y:auto">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top" style="background:#fff">
                            <tr>
                                <th class="ps-3"><i class="bi bi-box me-1"></i>Produit</th>
                                <th><i class="bi bi-tags me-1"></i>Catégorie</th>
                                <th><i class="bi bi-upc-scan me-1"></i>Code</th>
                                <th class="text-end"><i class="bi bi-stack me-1"></i>Quantité</th>
                                <th class="text-end pe-3"><i class="bi bi-currency-dollar me-1"></i>Valeur Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockItems as $s): ?>
                            <?php $statusClass = stockStatusClass((int)$s['quantity'], (int)$s['min_stock_alert']); ?>
                            <tr>
                                <td class="ps-3" style="font-size:.875rem;font-weight:500"><?= e($s['name']) ?></td>
                                <td><span class="badge" style="background:rgba(99,102,241,0.08);color:var(--accent);font-size:.7rem"><?= e($s['category_name'] ?? '—') ?></span></td>
                                <td style="font-size:.78rem;font-family:monospace;color:var(--text-muted)"><?= e($s['barcode'] ?? '—') ?></td>
                                <td class="text-end">
                                    <span class="badge bg-<?= $statusClass ?>" style="font-size:.75rem">
                                        <?= number_format((float)$s['quantity']) ?> <?= e($s['unit'] ?? '') ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3" style="font-size:.82rem"><?= formatMoney((float)$s['quantity'] * (float)$s['selling_price']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stockItems)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox d-block" style="font-size:2rem;opacity:.2"></i>Aucun stock enregistré</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mouvements récents -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-clock-history me-2"></i>Mouvements Récents</h6>
            </div>
            <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
                <?php foreach ($movements as $m): ?>
                <?php
                $typeConfig = [
                    'in'           => ['icon' => 'bi-arrow-down-circle-fill', 'color' => '#10B981', 'label' => 'Entrée'],
                    'out'          => ['icon' => 'bi-arrow-up-circle-fill',   'color' => '#EF4444', 'label' => 'Sortie'],
                    'sale'         => ['icon' => 'bi-bag-check-fill',         'color' => '#6366F1', 'label' => 'Vente'],
                    'transfer_in'  => ['icon' => 'bi-arrow-left-circle-fill', 'color' => '#F59E0B', 'label' => 'Transfert entrant'],
                    'transfer_out' => ['icon' => 'bi-arrow-right-circle-fill','color' => '#F59E0B', 'label' => 'Transfert sortant'],
                    'adjustment'   => ['icon' => 'bi-sliders',                'color' => '#8B5CF6', 'label' => 'Ajustement'],
                ];
                $tc = $typeConfig[$m['type']] ?? $typeConfig['in'];
                ?>
                <div class="d-flex align-items-start gap-2 px-3 py-2" style="border-bottom:1px solid var(--border)">
                    <i class="bi <?= $tc['icon'] ?>" style="color:<?= $tc['color'] ?>;font-size:1rem;margin-top:2px;flex-shrink:0"></i>
                    <div style="min-width:0;flex:1">
                        <div style="font-size:.78rem;font-weight:500" class="text-truncate"><?= e($m['product_name']) ?></div>
                        <div style="font-size:.7rem;color:var(--text-muted)"><?= $tc['label'] ?> • <?= e($m['user_name']) ?></div>
                        <div style="font-size:.68rem;color:var(--text-muted)"><?= formatDate($m['created_at']) ?></div>
                    </div>
                    <span style="font-size:.82rem;font-weight:600;color:<?= in_array($m['type'], ['in','transfer_in']) ? '#10B981' : '#EF4444' ?>;flex-shrink:0">
                        <?= in_array($m['type'], ['in','transfer_in','adjustment']) ? '+' : '-' ?><?= number_format((float)$m['quantity']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($movements)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.85rem"><i class="bi bi-clock-history d-block" style="font-size:2rem;opacity:.2"></i>Aucun mouvement</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL Entrée Stock (Scanner + Manuel) -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-plus-circle me-2 text-success"></i>Entrée de Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addStockForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="warehouse_id" value="<?= $warehouseId ?>">
                <input type="hidden" name="product_id" id="addStockProductId" value="">
                <div class="modal-body">
                    <!-- Zone Scanner / Recherche -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-upc-scan me-1"></i>Scanner ou Rechercher</label>
                        <div class="input-group">
                            <span class="input-group-text" style="border-radius:8px 0 0 8px;background:var(--body-bg);border-right:0">
                                <i class="bi bi-upc-scan" id="scanIcon" style="font-size:1.1rem;color:var(--accent)"></i>
                            </span>
                            <input type="text" id="stockScanInput" class="form-control" placeholder="Scanner code-barres ou taper le nom..." autocomplete="off" autofocus style="border-radius:0 8px 8px 0;border-left:0;font-size:.95rem">
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" id="scanHint">Scannez un code-barres ou tapez pour rechercher</small>
                            <small class="text-muted" id="scanMode" style="display:none"><i class="bi bi-lightning-charge-fill text-warning"></i> Scan détecté</small>
                        </div>
                    </div>

                    <!-- Produit trouvé (affiché après scan/recherche) -->
                    <div id="productInfo" style="display:none" class="mb-3">
                        <div class="d-flex align-items-center gap-3 p-3" style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:10px">
                            <div class="flex-shrink-0" style="width:44px;height:44px;background:rgba(16,185,129,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
                                <i class="bi bi-box-seam text-success" style="font-size:1.2rem"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="fw-semibold text-truncate" id="foundProductName" style="font-size:.9rem"></div>
                                <div class="d-flex gap-2 mt-1" style="font-size:.78rem;color:var(--text-muted)">
                                    <span id="foundProductBarcode"></span>
                                    <span class="d-none" id="foundProductSku"></span>
                                </div>
                                <div class="mt-1" style="font-size:.78rem">
                                    Stock actuel : <strong id="foundProductStock">0</strong>
                                    <span class="ms-2">Prix vente : <strong id="foundProductPrice">0</strong> FCFA</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="clearProductSelection()" style="border-radius:8px" title="Changer de produit">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Résultats recherche -->
                    <div id="searchResults" style="display:none" class="mb-3">
                        <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px">
                            <!-- Rempli dynamiquement par JS -->
                        </div>
                    </div>

                    <!-- Champ produit non trouvé -->
                    <div id="productNotFound" style="display:none" class="mb-3">
                        <div class="alert alert-warning py-2 mb-0" style="font-size:.85rem;border-radius:8px">
                            <i class="bi bi-exclamation-triangle me-1"></i>Aucun produit trouvé. Vérifiez le code-barres ou <a href="<?= BASE_URL ?>/views/products.php" class="fw-semibold">créez le produit</a> d'abord.
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold"><i class="bi bi-hash me-1"></i>Quantité</label>
                            <input type="number" name="quantity" id="addStockQty" class="form-control" min="0.01" step="0.01" required style="border-radius:8px">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold"><i class="bi bi-cash-coin me-1"></i>Prix unitaire (coût)</label>
                            <input type="number" name="unit_cost" id="addStockCost" class="form-control" min="0" step="1" style="border-radius:8px" placeholder="0">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Note</label>
                        <input type="text" name="note" class="form-control" style="border-radius:8px" placeholder="Fournisseur, référence bon de livraison...">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Annuler</button>
                    <button type="submit" class="btn btn-success px-4" style="border-radius:8px" id="addStockSubmit" disabled><i class="bi bi-plus-circle me-1"></i>Ajouter au stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const scanInput    = document.getElementById('stockScanInput');
    const scanIcon     = document.getElementById('scanIcon');
    const scanHint     = document.getElementById('scanHint');
    const scanMode     = document.getElementById('scanMode');
    const productInfo  = document.getElementById('productInfo');
    const searchResults= document.getElementById('searchResults');
    const notFound     = document.getElementById('productNotFound');
    const productId    = document.getElementById('addStockProductId');
    const submitBtn    = document.getElementById('addStockSubmit');
    const costInput    = document.getElementById('addStockCost');

    let searchTimer = null;
    let keystrokeTimes = [];
    let isScanning = false;

    // Détection scanneur : les scanneurs envoient les caractères très vite (< 50ms entre touches)
    function detectScanner(e) {
        const now = Date.now();
        keystrokeTimes.push(now);
        // Garder seulement les 20 derniers keystrokes
        if (keystrokeTimes.length > 20) keystrokeTimes = keystrokeTimes.slice(-20);

        if (keystrokeTimes.length >= 5) {
            const last5 = keystrokeTimes.slice(-5);
            const avgInterval = (last5[4] - last5[0]) / 4;
            isScanning = avgInterval < 50;
        }

        if (isScanning) {
            scanIcon.className = 'bi bi-upc-scan';
            scanIcon.style.color = '#10B981';
            scanMode.style.display = '';
            scanHint.style.display = 'none';
        } else {
            scanIcon.className = 'bi bi-search';
            scanIcon.style.color = 'var(--accent)';
            scanMode.style.display = 'none';
            scanHint.style.display = '';
        }
    }

    scanInput.addEventListener('keydown', function(e) {
        detectScanner(e);

        // Entrée = chercher immédiatement (scan ou manuel)
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = this.value.trim();
            if (!val) return;

            // Si scan détecté ou ressemble à un code-barres (chiffres uniquement, long), lookup exact
            if (isScanning || /^\d{4,}$/.test(val)) {
                lookupBarcode(val);
            } else {
                searchProducts(val);
            }
        }
    });

    // Recherche avec debounce pour la saisie manuelle
    scanInput.addEventListener('input', function() {
        const val = this.value.trim();
        clearTimeout(searchTimer);

        if (!val) {
            searchResults.style.display = 'none';
            notFound.style.display = 'none';
            return;
        }

        // Si ça ressemble à un code-barres, ne pas debouncer (attendre Entrée)
        if (/^\d{4,}$/.test(val)) return;

        searchTimer = setTimeout(() => searchProducts(val), 300);
    });

    function lookupBarcode(barcode) {
        fetch('<?= BASE_URL ?>/controllers/stock_ajax.php?action=barcode_lookup&barcode=' + encodeURIComponent(barcode) + '&warehouse=<?= $warehouseId ?>')
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    selectProduct(res.data);
                } else {
                    productInfo.style.display = 'none';
                    searchResults.style.display = 'none';
                    notFound.style.display = '';
                    productId.value = '';
                    submitBtn.disabled = true;
                }
            })
            .catch(() => {
                notFound.style.display = '';
            });
    }

    function searchProducts(term) {
        fetch('<?= BASE_URL ?>/controllers/stock_ajax.php?action=search_product&search=' + encodeURIComponent(term) + '&warehouse=<?= $warehouseId ?>')
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    showSearchResults(res.data);
                } else {
                    productInfo.style.display = 'none';
                    searchResults.style.display = 'none';
                    notFound.style.display = '';
                    productId.value = '';
                    submitBtn.disabled = true;
                }
            })
            .catch(() => {
                notFound.style.display = '';
            });
    }

    function showSearchResults(products) {
        notFound.style.display = 'none';
        productInfo.style.display = 'none';
        searchResults.style.display = '';

        const container = searchResults.querySelector('div');
        container.innerHTML = products.map(p => `
            <div class="d-flex align-items-center gap-2 px-3 py-2" style="border-bottom:1px solid var(--border);cursor:pointer;font-size:.85rem"
                 onclick="selectProductById(${p.id}, '${(p.name||'').replace(/'/g, "\\'")}', '${(p.barcode||'').replace(/'/g, "\\'")}', '${(p.sku||'').replace(/'/g, "\\'")}', ${p.current_stock || 0}, ${p.selling_price || 0}, ${p.cost_price || 0})"
                 onmouseover="this.style.background='rgba(99,102,241,0.05)'" onmouseout="this.style.background=''">
                <i class="bi bi-box-seam flex-shrink-0" style="color:var(--text-muted)"></i>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="fw-semibold text-truncate">${p.name || '—'}</div>
                    <div style="font-size:.75rem;color:var(--text-muted)">${p.barcode || '—'} ${p.sku ? ' • SKU: ' + p.sku : ''}</div>
                </div>
                <div class="text-end flex-shrink-0" style="font-size:.75rem;color:var(--text-muted)">
                    Stock: <strong>${p.current_stock || 0}</strong>
                </div>
            </div>
        `).join('');
    }

    // Fonction globale pour le clic sur résultat de recherche
    window.selectProductById = function(id, name, barcode, sku, stock, price, cost) {
        selectProduct({ id, name, barcode, sku, current_stock: stock, selling_price: price, cost_price: cost });
    };

    function selectProduct(p) {
        productId.value = p.id;
        productInfo.style.display = '';
        searchResults.style.display = 'none';
        notFound.style.display = 'none';
        submitBtn.disabled = false;

        document.getElementById('foundProductName').textContent = p.name || '—';
        document.getElementById('foundProductBarcode').textContent = p.barcode || '—';
        document.getElementById('foundProductStock').textContent = p.current_stock || 0;
        document.getElementById('foundProductPrice').textContent = Number(p.selling_price || 0).toLocaleString('fr-FR');

        const skuEl = document.getElementById('foundProductSku');
        if (p.sku) {
            skuEl.textContent = 'SKU: ' + p.sku;
            skuEl.classList.remove('d-none');
        } else {
            skuEl.classList.add('d-none');
        }

        // Pré-remplir le coût avec le cost_price du produit
        if (p.cost_price && p.cost_price > 0) {
            costInput.value = p.cost_price;
        }

        // Focus sur la quantité
        document.getElementById('addStockQty').focus();

        // Reset scan input
        scanInput.value = '';
        keystrokeTimes = [];
        isScanning = false;
        scanIcon.className = 'bi bi-upc-scan';
        scanIcon.style.color = 'var(--accent)';
        scanMode.style.display = 'none';
        scanHint.style.display = '';
    }

    window.clearProductSelection = function() {
        productId.value = '';
        productInfo.style.display = 'none';
        searchResults.style.display = 'none';
        notFound.style.display = 'none';
        submitBtn.disabled = true;
        costInput.value = '';
        scanInput.value = '';
        scanInput.focus();
    };

    // Validation formulaire : produit doit être sélectionné
    document.getElementById('addStockForm').addEventListener('submit', function(e) {
        if (!productId.value) {
            e.preventDefault();
            alert('Veuillez d\'abord scanner ou sélectionner un produit.');
            scanInput.focus();
        }
    });

    // Reset à l'ouverture de la modale
    document.getElementById('addStockModal').addEventListener('shown.bs.modal', function() {
        clearProductSelection();
        document.getElementById('addStockQty').value = '';
    });
})();
</script>

<!-- MODAL Sortie Stock -->
<div class="modal fade" id="removeStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-dash-circle me-2 text-danger"></i>Sortie de Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="warehouse_id" value="<?= $warehouseId ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-box me-1"></i>Produit</label>
                        <select name="product_id" class="form-select" required style="border-radius:8px">
                            <option value="">Sélectionner un produit...</option>
                            <?php foreach ($stockItems as $s): ?>
                            <option value="<?= $s['product_id'] ?>"><?= e($s['name']) ?> (Dispo: <?= number_format((float)$s['quantity']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold"><i class="bi bi-hash me-1"></i>Quantité à retirer</label>
                        <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" required style="border-radius:8px">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Motif</label>
                        <input type="text" name="note" class="form-control" style="border-radius:8px" placeholder="Casse, perte, usage interne...">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Annuler</button>
                    <button type="submit" class="btn btn-danger px-4" style="border-radius:8px"><i class="bi bi-dash-circle me-1"></i>Retirer du stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL Ajustement -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-sliders me-2 text-warning"></i>Ajustement de Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="warehouse_id" value="<?= $warehouseId ?>">
                <div class="modal-body">
                    <p class="text-muted small">Définissez la nouvelle quantité réelle (après inventaire physique).</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-box me-1"></i>Produit</label>
                        <select name="product_id" class="form-select" required style="border-radius:8px">
                            <option value="">Sélectionner un produit...</option>
                            <?php foreach ($stockItems as $s): ?>
                            <option value="<?= $s['product_id'] ?>"><?= e($s['name']) ?> (Système: <?= number_format((float)$s['quantity']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold"><i class="bi bi-hash me-1"></i>Nouvelle quantité réelle</label>
                        <input type="number" name="quantity" class="form-control" min="0" step="0.01" required style="border-radius:8px">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Motif de l'ajustement</label>
                        <input type="text" name="note" class="form-control" style="border-radius:8px" placeholder="Inventaire physique du XX/XX/XXXX...">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Annuler</button>
                    <button type="submit" class="btn btn-warning px-4" style="border-radius:8px"><i class="bi bi-sliders me-1"></i>Ajuster le stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>
