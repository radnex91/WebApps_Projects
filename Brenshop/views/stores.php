<?php
// ============================================================
// views/stores.php — Gestion Boutiques
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('stores');

$pageTitle = 'Boutiques';
$storeModel = new Store();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { setFlash('error', 'Requête invalide.'); redirect(BASE_URL . '/views/stores.php'); }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id > 0) {
        $storeModel->update($id, ['is_active' => 0]);
        setFlash('success', 'Boutique désactivée.');
        redirect(BASE_URL . '/views/stores.php');
    }
    $data = [
        'name'     => sanitize($_POST['name'] ?? ''),
        'code'     => strtoupper(sanitize($_POST['code'] ?? '')),
        'address'  => sanitize($_POST['address'] ?? ''),
        'phone'    => sanitize($_POST['phone'] ?? ''),
        'email'    => sanitize($_POST['email'] ?? ''),
        'currency' => sanitize($_POST['currency'] ?? 'FCFA'),
        'tax_rate' => (float)($_POST['tax_rate'] ?? 18),
        'is_active'=> 1,
    ];
    if (empty($data['name'])) { setFlash('error', 'Le nom est requis.'); }
    elseif ($id > 0) { $storeModel->update($id, $data); setFlash('success', 'Boutique mise à jour.'); }
    else { $storeModel->insert($data); setFlash('success', 'Boutique créée.'); }
    redirect(BASE_URL . '/views/stores.php');
}

$stores = $storeModel->findAll(['is_active' => 1], 'name ASC');
require_once __DIR__ . '/layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Gérez vos points de vente.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#storeModal" style="border-radius:8px">
        <i class="bi bi-plus-lg me-1"></i>Nouvelle Boutique
    </button>
</div>
<div class="row g-3">
    <?php foreach ($stores as $s): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem"><?= e($s['name']) ?></div>
                            <?php if (!empty($s['code'])): ?><span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.65rem;font-weight:600"><?= e($s['code']) ?></span><?php endif; ?>
                        </div>
                        <div style="font-size:.78rem;color:var(--text-muted)"><?= e($s['address'] ?? '') ?></div>
                        <div style="font-size:.78rem;margin-top:.4rem"><?= e($s['phone'] ?? '') ?> <?= $s['email'] ? '• '.e($s['email']) : '' ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Devise: <strong><?= e($s['currency']) ?></strong> — TVA: <strong><?= $s['tax_rate'] ?>%</strong></div>
                    </div>
                    <span class="badge bg-success" style="font-size:.68rem">Actif</span>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-1" data-bs-toggle="modal" data-bs-target="#storeModal" onclick='loadStoreEdit(<?= htmlspecialchars(json_encode($s)) ?>)' style="border-radius:6px;font-size:.75rem">
                        <i class="bi bi-pencil me-1"></i>Modifier
                    </button>
                    <a href="<?= BASE_URL ?>/controllers/auth.php?action=switch_store&store_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary flex-1" style="border-radius:6px;font-size:.75rem">
                        <i class="bi bi-arrow-repeat me-1"></i>Basculer
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="storeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0"><h5 class="modal-title" id="storeModalTitle" style="font-family:Syne,sans-serif;font-weight:700">Nouvelle Boutique</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="storeId" value="0">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label fw-semibold">Nom *</label><input type="text" name="name" id="sName" class="form-control" required style="border-radius:8px"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Code <span class="text-muted" style="font-weight:400;font-size:.72rem">(factures)</span></label><input type="text" name="code" id="sCode" class="form-control" maxlength="10" placeholder="BA" style="border-radius:8px;text-transform:uppercase"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Adresse</label><input type="text" name="address" id="sAddr" class="form-control" style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Téléphone</label><input type="text" name="phone" id="sPhone" class="form-control" style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Email</label><input type="email" name="email" id="sEmail" class="form-control" style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Devise</label><input type="text" name="currency" id="sCurrency" class="form-control" value="FCFA" style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold">TVA (%)</label><input type="number" name="tax_rate" id="sTax" class="form-control" value="18" min="0" step="0.01" style="border-radius:8px"></div>
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
function loadStoreEdit(s) {
    document.getElementById('storeModalTitle').textContent = 'Modifier Boutique';
    document.getElementById('storeId').value = s.id;
    document.getElementById('sName').value = s.name||'';
    document.getElementById('sCode').value = s.code||'';
    document.getElementById('sAddr').value = s.address||'';
    document.getElementById('sPhone').value = s.phone||'';
    document.getElementById('sEmail').value = s.email||'';
    document.getElementById('sCurrency').value = s.currency||'FCFA';
    document.getElementById('sTax').value = s.tax_rate||18;
    new bootstrap.Modal(document.getElementById('storeModal')).show();
}
document.getElementById('storeModal').addEventListener('hidden.bs.modal',function(){
    document.getElementById('storeModalTitle').textContent='Nouvelle Boutique';
    document.getElementById('storeId').value=0;
    this.querySelector('form').reset();
    document.getElementById('sCurrency').value='FCFA';
    document.getElementById('sTax').value=18;
});
</script>
JS;
require_once __DIR__ . '/layout_bottom.php';
?>
