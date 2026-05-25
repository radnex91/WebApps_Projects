<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('products');

$pageTitle = 'Produits';
$productModel = new Product();
$categoryModel = new Category();
$storeId = currentStoreId();

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Requête invalide.');
        redirect(BASE_URL . '/views/products.php');
    }

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id > 0) {
        $productModel->update($id, ['is_active' => 0]);
        setFlash('success', 'Produit désactivé.');
        redirect(BASE_URL . '/views/products.php');
    }

    $data = [
        'store_id'        => $storeId,
        'category_id'     => (int)($_POST['category_id'] ?? 0) ?: null,
        'name'            => sanitize($_POST['name'] ?? ''),
        'barcode'         => sanitize($_POST['barcode'] ?? '') ?: null,
        'sku'             => sanitize($_POST['sku'] ?? '') ?: null,
        'description'     => sanitize($_POST['description'] ?? ''),
        'cost_price'      => (float)($_POST['cost_price'] ?? 0),
        'selling_price'   => (float)($_POST['selling_price'] ?? 0),
        'unit'            => sanitize($_POST['unit'] ?? 'pcs'),
        'min_stock_alert' => (int)($_POST['min_stock_alert'] ?? 5),
        'is_active'       => 1,
    ];

    // Upload image
    if (!empty($_FILES['image']['name'])) {
        $imagePath = uploadImage($_FILES['image'], 'products');
        if ($imagePath) $data['image'] = $imagePath;
    }

    if (empty($data['name'])) {
        setFlash('error', 'Le nom du produit est requis.');
    } elseif ($id > 0) {
        $productModel->update($id, $data);
        setFlash('success', 'Produit mis à jour avec succès.');
    } else {
        $productModel->insert($data);
        setFlash('success', 'Produit créé avec succès.');
    }
    redirect(BASE_URL . '/views/products.php');
}

// Lecture
$search     = sanitize($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$total      = $productModel->countByStore($storeId, $search, $categoryId);
$pagination = paginate($total, $page);
$products   = $productModel->getByStore($storeId, $page, $search, $categoryId);
$categories = $categoryModel->getByStore($storeId);

// Produit à éditer
$editProduct = null;
if (!empty($_GET['edit'])) {
    $editProduct = $productModel->find((int)$_GET['edit']);
}

require_once __DIR__ . '/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Rechercher..." style="width:200px;border-radius:8px">
            <select name="category" class="form-select form-select-sm" style="width:160px;border-radius:8px">
                <option value="0">Toutes catégories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $categoryId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:8px">Filtrer</button>
        </form>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" style="border-radius:8px">
        <i class="bi bi-plus-lg me-1"></i>Nouveau Produit
    </button>
</div>

<div class="card">
    <div class="card-header-custom">
        <h6><i class="bi bi-box-seam me-2"></i>Produits (<?= number_format($total) ?>)</h6>
        <small class="text-muted">Page <?= $pagination['page'] ?>/<?= $pagination['total_pages'] ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Produit</th>
                        <th>Catégorie</th>
                        <th>Code-barres</th>
                        <th class="text-end">Prix Achat</th>
                        <th class="text-end">Prix Vente</th>
                        <th class="text-end">Stock Total</th>
                        <th class="text-center pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <?php if ($p['image']): ?>
                                    <img src="<?= BASE_URL ?>/<?= e($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                                    <?php else: ?>
                                    <i class="bi bi-box" style="color:var(--accent)"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-size:.875rem;font-weight:500"><?= e($p['name']) ?></div>
                                    <div style="font-size:.72rem;color:var(--text-muted)"><?= e($p['sku'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.72rem"><?= e($p['category_name'] ?? 'Sans catégorie') ?></span></td>
                        <td style="font-size:.82rem;font-family:monospace"><?= e($p['barcode'] ?? '—') ?></td>
                        <td class="text-end" style="font-size:.85rem"><?= formatMoney((float)$p['cost_price']) ?></td>
                        <td class="text-end" style="font-size:.85rem;font-weight:600;color:var(--accent)"><?= formatMoney((float)$p['selling_price']) ?></td>
                        <td class="text-end">
                            <span class="badge bg-<?= stockStatusClass((int)$p['total_stock'], (int)$p['min_stock_alert']) ?>" style="font-size:.75rem">
                                <?= number_format((float)$p['total_stock']) ?> <?= e($p['unit'] ?? '') ?>
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" style="padding:.25rem .5rem;font-size:.75rem;border-radius:6px" data-bs-toggle="modal" data-bs-target="#productModal" onclick="loadEdit(<?= htmlspecialchars(json_encode($p)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Désactiver ce produit ?')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.25rem .5rem;font-size:.75rem;border-radius:6px">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Aucun produit trouvé</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagination['page']-1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryId ?>">‹</a></li>
                <?php endif; ?>
                <?php for ($i = max(1, $pagination['page']-2); $i <= min($pagination['total_pages'], $pagination['page']+2); $i++): ?>
                <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $categoryId ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagination['page']+1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryId ?>">›</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL Produit -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="productModalTitle" style="font-family:Syne,sans-serif;font-weight:700">Nouveau Produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="productId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nom du produit <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="pName" class="form-control" required style="border-radius:8px">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Catégorie</label>
                            <select name="category_id" id="pCategory" class="form-select" style="border-radius:8px">
                                <option value="">Sans catégorie</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code-barres</label>
                            <input type="text" name="barcode" id="pBarcode" class="form-control" style="border-radius:8px;font-family:monospace">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SKU / Référence</label>
                            <input type="text" name="sku" id="pSku" class="form-control" style="border-radius:8px">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unité</label>
                            <select name="unit" id="pUnit" class="form-select" style="border-radius:8px">
                                <option value="pcs">Pièce (pcs)</option>
                                <option value="kg">Kilogramme (kg)</option>
                                <option value="g">Gramme (g)</option>
                                <option value="L">Litre (L)</option>
                                <option value="ml">Millilitre (ml)</option>
                                <option value="m">Mètre (m)</option>
                                <option value="boite">Boite</option>
                                <option value="sachet">Sachet</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Prix d'achat (FCFA)</label>
                            <input type="number" name="cost_price" id="pCostPrice" class="form-control" min="0" step="1" style="border-radius:8px">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Prix de vente (FCFA) <span class="text-danger">*</span></label>
                            <input type="number" name="selling_price" id="pSellingPrice" class="form-control" min="0" step="1" required style="border-radius:8px">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Alerte stock faible</label>
                            <input type="number" name="min_stock_alert" id="pMinStock" class="form-control" min="0" value="5" style="border-radius:8px">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="pDesc" class="form-control" rows="2" style="border-radius:8px;resize:none"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Image produit</label>
                            <input type="file" name="image" class="form-control" accept="image/*" style="border-radius:8px">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius:8px">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'SCRIPT'
<script>
function loadEdit(product) {
    document.getElementById('productModalTitle').textContent = 'Modifier Produit';
    document.getElementById('productId').value = product.id;
    document.getElementById('pName').value = product.name || '';
    document.getElementById('pBarcode').value = product.barcode || '';
    document.getElementById('pSku').value = product.sku || '';
    document.getElementById('pCostPrice').value = product.cost_price || 0;
    document.getElementById('pSellingPrice').value = product.selling_price || 0;
    document.getElementById('pMinStock').value = product.min_stock_alert || 5;
    document.getElementById('pDesc').value = product.description || '';
    document.getElementById('pUnit').value = product.unit || 'pcs';
    if (product.category_id) document.getElementById('pCategory').value = product.category_id;
}

document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('productModalTitle').textContent = 'Nouveau Produit';
    document.getElementById('productId').value = 0;
    this.querySelector('form').reset();
});
</script>
SCRIPT;
require_once __DIR__ . '/layout_bottom.php';
?>
