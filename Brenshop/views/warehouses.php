<?php
// ============================================================
// views/warehouses.php — Gestion Magasins / Entrepôts
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('warehouses');

$pageTitle = 'Magasins & Entrepôts';
$warehouseModel = new Warehouse();
$storeModel = new Store();
$storeId = currentStoreId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { setFlash('error', 'Requête invalide.'); redirect(BASE_URL . '/views/warehouses.php'); }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id > 0) {
        $warehouseModel->update($id, ['is_active' => 0]);
        setFlash('success', 'Magasin désactivé.');
        redirect(BASE_URL . '/views/warehouses.php');
    }
    $data = [
        'store_id'   => (int)($_POST['store_id'] ?? $storeId),
        'name'       => sanitize($_POST['name'] ?? ''),
        'address'    => sanitize($_POST['address'] ?? ''),
        'phone'      => sanitize($_POST['phone'] ?? ''),
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'is_active'  => 1,
    ];
    if (empty($data['name'])) { setFlash('error', 'Le nom est requis.'); }
    elseif ($id > 0) { $warehouseModel->update($id, $data); setFlash('success', 'Magasin mis à jour.'); }
    else { $warehouseModel->insert($data); setFlash('success', 'Magasin créé.'); }
    redirect(BASE_URL . '/views/warehouses.php');
}

$warehouses = $warehouseModel->getAllWithStore();
$stores = $storeModel->getActive();
require_once __DIR__ . '/layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Gérez vos entrepôts et points de stockage.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#whModal" style="border-radius:8px">
        <i class="bi bi-plus-lg me-1"></i>Nouveau Magasin
    </button>
</div>
<div class="card">
    <div class="card-header-custom"><h6><i class="bi bi-building me-2"></i>Magasins (<?= count($warehouses) ?>)</h6></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-3">Nom</th><th>Boutique</th><th>Adresse</th><th>Téléphone</th><th class="text-center">Défaut</th><th class="text-center pe-3">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($warehouses as $wh): ?>
                <tr>
                    <td class="ps-3" style="font-size:.875rem;font-weight:500"><?= e($wh['name']) ?></td>
                    <td><span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.72rem"><?= e($wh['store_name']) ?></span></td>
                    <td style="font-size:.82rem;color:var(--text-muted)"><?= e($wh['address'] ?? '—') ?></td>
                    <td style="font-size:.82rem"><?= e($wh['phone'] ?? '—') ?></td>
                    <td class="text-center"><?= $wh['is_default'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-circle text-muted"></i>' ?></td>
                    <td class="text-center pe-3">
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-primary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px"
                                data-bs-toggle="modal" data-bs-target="#whModal" onclick='loadWhEdit(<?= htmlspecialchars(json_encode($wh)) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Désactiver ce magasin ?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $wh['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="whModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0"><h5 class="modal-title" id="whModalTitle" style="font-family:Syne,sans-serif;font-weight:700">Nouveau Magasin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="whId" value="0">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label fw-semibold">Boutique *</label>
                            <select name="store_id" id="whStoreId" class="form-select" required style="border-radius:8px">
                                <?php foreach ($stores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $storeId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold">Nom *</label><input type="text" name="name" id="whName" class="form-control" required style="border-radius:8px"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Adresse</label><input type="text" name="address" id="whAddr" class="form-control" style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Téléphone</label><input type="text" name="phone" id="whPhone" class="form-control" style="border-radius:8px"></div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="whDefault" value="1">
                                <label class="form-check-label" for="whDefault" style="font-size:.875rem">Magasin par défaut</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius:8px">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$extraScript = <<<'JS'
<script>
function loadWhEdit(w) {
    document.getElementById('whModalTitle').textContent = 'Modifier Magasin';
    document.getElementById('whId').value = w.id;
    document.getElementById('whName').value = w.name||'';
    document.getElementById('whAddr').value = w.address||'';
    document.getElementById('whPhone').value = w.phone||'';
    document.getElementById('whStoreId').value = w.store_id;
    document.getElementById('whDefault').checked = w.is_default == 1;
    new bootstrap.Modal(document.getElementById('whModal')).show();
}
document.getElementById('whModal').addEventListener('hidden.bs.modal',function(){
    document.getElementById('whModalTitle').textContent='Nouveau Magasin';
    document.getElementById('whId').value=0;
    this.querySelector('form').reset();
});
</script>
JS;
require_once __DIR__ . '/layout_bottom.php';
?>
