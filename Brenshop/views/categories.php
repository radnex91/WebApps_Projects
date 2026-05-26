<?php
// views/categories.php — Gestion Catégories
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('categories');

$pageTitle = 'Catégories';
$categoryModel = new Category();
$storeId = currentStoreId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { setFlash('error', 'Requête invalide.'); redirect(BASE_URL . '/views/categories.php'); }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id > 0) {
        $categoryModel->update($id, ['is_active' => 0]);
        setFlash('success', 'Catégorie désactivée.');
        redirect(BASE_URL . '/views/categories.php');
    }
    $data = [
        'store_id'    => $storeId,
        'name'        => sanitize($_POST['name'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'color'       => sanitize($_POST['color'] ?? '#3B82F6'),
        'icon'        => sanitize($_POST['icon'] ?? 'box'),
        'is_active'   => 1,
    ];
    if (empty($data['name'])) { setFlash('error', 'Nom requis.'); }
    elseif ($id > 0) { $categoryModel->update($id, $data); setFlash('success', 'Catégorie mise à jour.'); }
    else { $categoryModel->insert($data); setFlash('success', 'Catégorie créée.'); }
    redirect(BASE_URL . '/views/categories.php');
}

$categories = $categoryModel->getByStore($storeId);
require_once __DIR__ . '/layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Organisez vos produits par catégories.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" style="border-radius:8px">
        <i class="bi bi-plus-lg me-1"></i>Nouvelle Catégorie
    </button>
</div>

<div class="row g-3">
    <?php foreach ($categories as $cat): ?>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:36px;height:36px;border-radius:8px;background:<?= e($cat['color']) ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-<?= e($cat['icon']) ?>" style="color:<?= e($cat['color']) ?>;font-size:1rem"></i>
                    </div>
                    <div style="font-weight:600;font-size:.9rem"><?= e($cat['name']) ?></div>
                </div>
                <?php if ($cat['description']): ?>
                <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.75rem"><?= e($cat['description']) ?></div>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-1" data-bs-toggle="modal" data-bs-target="#catModal"
                        onclick='loadCatEdit(<?= htmlspecialchars(json_encode($cat)) ?>)' style="border-radius:6px;font-size:.72rem">
                        <i class="bi bi-pencil me-1"></i>Modifier
                    </button>
                    <form method="POST" onsubmit="return confirm('Désactiver ?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:.72rem;padding:.3rem .5rem">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($categories)): ?>
    <div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-tags" style="font-size:2.5rem;opacity:.3"></i><p class="mt-2">Aucune catégorie créée</p></div></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0"><h5 class="modal-title" id="catModalTitle" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-plus-circle me-2"></i>Nouvelle Catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="catId" value="0">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label fw-semibold"><i class="bi bi-type me-1"></i>Nom *</label><input type="text" name="name" id="catName" class="form-control" required style="border-radius:8px"></div>
                        <div class="col-4">
                            <label class="form-label fw-semibold"><i class="bi bi-palette me-1"></i>Couleur</label>
                            <input type="color" name="color" id="catColor" class="form-control form-control-color w-100" value="#3B82F6" style="border-radius:8px;height:38px">
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold"><i class="bi bi-code-slash me-1"></i>Icône Bootstrap</label>
                            <input type="text" name="icon" id="catIcon" class="form-control" value="box" style="border-radius:8px" placeholder="box, tag, cpu, droplet...">
                            <div class="form-text">Nom d'icône <a href="https://icons.getbootstrap.com" target="_blank">Bootstrap Icons</a></div>
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Description</label><textarea name="description" id="catDesc" class="form-control" rows="2" style="border-radius:8px;resize:none"></textarea></div>
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
function loadCatEdit(c) {
    document.getElementById('catModalTitle').textContent = 'Modifier Catégorie';
    document.getElementById('catId').value = c.id;
    document.getElementById('catName').value = c.name||'';
    document.getElementById('catColor').value = c.color||'#3B82F6';
    document.getElementById('catIcon').value = c.icon||'box';
    document.getElementById('catDesc').value = c.description||'';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
document.getElementById('catModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('catModalTitle').textContent='Nouvelle Catégorie';
    document.getElementById('catId').value=0;
    document.getElementById('catColor').value='#3B82F6';
    this.querySelector('form').reset();
    document.getElementById('catColor').value='#3B82F6';
    document.getElementById('catIcon').value='box';
});
</script>
JS;
require_once __DIR__ . '/layout_bottom.php';
?>
