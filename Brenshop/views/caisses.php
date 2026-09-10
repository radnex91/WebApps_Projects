<?php
// ============================================================
// views/caisses.php — Gestion Caisses
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('caisses');

$pageTitle = 'Caisses';
$caisseModel = new Caisse();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { setFlash('error', 'Requête invalide.'); redirect(BASE_URL . '/views/caisses.php'); }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle' && $id > 0) {
        $caisse = $caisseModel->find($id);
        if ($caisse) {
            $caisseModel->update($id, ['is_active' => $caisse['is_active'] ? 0 : 1]);
            setFlash('success', $caisse['is_active'] ? 'Caisse désactivée.' : 'Caisse réactivée.');
        }
        redirect(BASE_URL . '/views/caisses.php');
    }
    $data = [
        'store_id' => currentStoreId(),
        'name'     => sanitize($_POST['name'] ?? ''),
        'is_active'=> 1,
    ];
    if (empty($data['name'])) { setFlash('error', 'Le nom est requis.'); }
    elseif ($id > 0) { $caisseModel->update($id, $data); setFlash('success', 'Caisse mise à jour.'); }
    else { $caisseModel->insert($data); setFlash('success', 'Caisse créée.'); }
    redirect(BASE_URL . '/views/caisses.php');
}

$caisses = $caisseModel->getAllByStore(currentStoreId());
require_once __DIR__ . '/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Gérez les caisses de votre point de vente. Chaque caisse peut être ouverte et fermée indépendamment.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#caisseModal" style="border-radius:8px" onclick="resetCaisseForm()">
        <i class="bi bi-plus-lg me-1"></i>Nouvelle Caisse
    </button>
</div>

<div class="row g-3">
    <?php foreach ($caisses as $c): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 <?= !$c['is_active'] ? 'opacity-50' : '' ?>">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:10px;background:<?= $c['is_active'] ? 'rgba(99,102,241,0.1)' : 'rgba(239,68,68,0.1)' ?>;display:flex;align-items:center;justify-content:center">
                            <i class="bi bi-cash-register" style="font-size:1.3rem;color:<?= $c['is_active'] ? 'var(--accent)' : 'var(--danger)' ?>"></i>
                        </div>
                        <div>
                            <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem"><?= e($c['name']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted)">ID: <?= $c['id'] ?></div>
                        </div>
                    </div>
                    <span class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-danger' ?>" style="font-size:.68rem">
                        <?= $c['is_active'] ? '<i class="bi bi-check-circle me-1"></i>Active' : '<i class="bi bi-x-circle me-1"></i>Inactive' ?>
                    </span>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-1" data-bs-toggle="modal" data-bs-target="#caisseModal" onclick='loadCaisseEdit(<?= htmlspecialchars(json_encode($c)) ?>)' style="border-radius:6px;font-size:.75rem">
                        <i class="bi bi-pencil me-1"></i>Modifier
                    </button>
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="action" value="toggle">
                        <button type="submit" class="btn btn-sm <?= $c['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> w-100" style="border-radius:6px;font-size:.75rem">
                            <i class="bi <?= $c['is_active'] ? 'bi-x-circle' : 'bi-check-circle' ?> me-1"></i>
                            <?= $c['is_active'] ? 'Désactiver' : 'Activer' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($caisses)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-cash-register" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem"></i><i class="bi bi-plus-circle" style="font-size:1.2rem;opacity:.3"></i>
    Aucune caisse. Créez votre première caisse.
</div>
<?php endif; ?>

<!-- MODAL CREATE/EDIT -->
<div class="modal fade" id="caisseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="caisseModalTitle" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-plus-circle me-2"></i>Nouvelle Caisse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="caisseId" value="0">
                <input type="hidden" name="action" value="save">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-type me-1"></i>Nom de la caisse *</label>
                        <input type="text" name="name" id="cName" class="form-control" required style="border-radius:8px" placeholder="Ex: Caisse Principale">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Annuler</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius:8px"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function loadCaisseEdit(c) {
    document.getElementById('caisseModalTitle').textContent = 'Modifier Caisse';
    document.getElementById('caisseId').value = c.id;
    document.getElementById('cName').value = c.name || '';
    new bootstrap.Modal(document.getElementById('caisseModal')).show();
}
function resetCaisseForm() {
    document.getElementById('caisseModalTitle').textContent = 'Nouvelle Caisse';
    document.getElementById('caisseId').value = 0;
    document.getElementById('cName').value = '';
}
</script>
JS;
require_once __DIR__ . '/layout_bottom.php';
?>
