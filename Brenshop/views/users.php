<?php
// ============================================================
// views/users.php — Gestion Utilisateurs
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('users');

$pageTitle = 'Utilisateurs';
$userModel = new User();
$storeModel = new Store();
$warehouseModel = new Warehouse();
$storeId = currentStoreId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { setFlash('error', 'Requête invalide.'); redirect(BASE_URL . '/views/users.php'); }
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle' && $id > 0) {
        $u = $userModel->find($id);
        $userModel->update($id, ['is_active' => $u['is_active'] ? 0 : 1]);
        setFlash('success', 'Statut utilisateur modifié.');
        redirect(BASE_URL . '/views/users.php');
    }

    $data = [
        'store_id'     => (int)($_POST['store_id'] ?? $storeId),
        'warehouse_id' => (int)($_POST['warehouse_id'] ?? 0) ?: null,
        'name'         => sanitize($_POST['name'] ?? ''),
        'email'        => sanitize($_POST['email'] ?? ''),
        'role'         => in_array($_POST['role'] ?? '', ['admin','manager','cashier']) ? $_POST['role'] : 'cashier',
        'phone'        => sanitize($_POST['phone'] ?? '') ?: null,
        'is_active'    => 1,
    ];

    $selectedStores     = $_POST['store_ids'] ?? [];
    $selectedWarehouses = $_POST['warehouse_ids'] ?? [];

    if (empty($data['name']) || empty($data['email'])) { setFlash('error', 'Nom et email requis.'); redirect(BASE_URL . '/views/users.php'); }
    if (empty($selectedStores)) { setFlash('error', 'Sélectionnez au moins une boutique.'); redirect(BASE_URL . '/views/users.php'); }

    // La boutique principale est la première cochée
    $data['store_id'] = (int)$selectedStores[0];

    // Le magasin par défaut doit être parmi les magasins sélectionnés
    $defaultWh = (int)($_POST['warehouse_id'] ?? 0);
    if ($defaultWh > 0 && !in_array($defaultWh, $selectedWarehouses)) {
        $selectedWarehouses[] = $defaultWh;
    }
    $data['warehouse_id'] = $defaultWh > 0 ? $defaultWh : null;

    // Permissions
    $permissions = $_POST['permissions'] ?? [];
    if ($data['role'] === 'admin') {
        $data['permissions'] = null;
    } else {
        $data['permissions'] = $userModel->encodePermissions($permissions);
    }

    if ($id > 0) {
        $userModel->update($id, $data);
        if (!empty($_POST['password'])) {
            $userModel->updatePassword($id, $_POST['password']);
        }
        $userModel->syncStores($id, $selectedStores);
        $userModel->syncWarehouses($id, $selectedWarehouses);
        setFlash('success', 'Utilisateur mis à jour.');
    } else {
        if (empty($_POST['password'])) { setFlash('error', 'Le mot de passe est requis.'); redirect(BASE_URL . '/views/users.php'); }
        $data['password'] = $_POST['password'];
        $newId = $userModel->createUser($data);
        $userModel->syncStores($newId, $selectedStores);
        $userModel->syncWarehouses($newId, $selectedWarehouses);
        setFlash('success', 'Utilisateur créé.');
    }
    redirect(BASE_URL . '/views/users.php');
}

$users = $userModel->getAllWithDetails();
$stores = $storeModel->getActive();
$allWh  = $warehouseModel->getAllWithStore();

require_once __DIR__ . '/layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Gérez les accès et rôles des utilisateurs.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" style="border-radius:8px">
        <i class="bi bi-person-plus me-1"></i>Nouvel Utilisateur
    </button>
</div>
<div class="card">
    <div class="card-header-custom"><h6><i class="bi bi-person-gear me-2"></i>Utilisateurs (<?= count($users) ?>)</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th class="ps-3"><i class="bi bi-person me-1"></i>Utilisateur</th>
                    <th><i class="bi bi-shield me-1"></i>Rôle</th>
                    <th><i class="bi bi-shop me-1"></i>Boutiques</th>
                    <th><i class="bi bi-building me-1"></i>Magasins</th>
                    <th><i class="bi bi-clock me-1"></i>Dernière connexion</th>
                    <th class="text-center"><i class="bi bi-toggle-on me-1"></i>Statut</th>
                    <th class="text-center pe-3"><i class="bi bi-gear me-1"></i>Actions</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php $roleColors = ['admin' => '#6366F1', 'manager' => '#F59E0B', 'cashier' => '#10B981']; ?>
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#818CF8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;flex-shrink:0">
                                    <?= strtoupper(substr($u['name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <div style="font-size:.875rem;font-weight:500"><?= e($u['name']) ?></div>
                                    <div style="font-size:.72rem;color:var(--text-muted)"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= ($roleColors[$u['role']] ?? '#64748B') ?>22;color:<?= $roleColors[$u['role']] ?? '#64748B' ?>;font-size:.72rem">
                                <?php if ($u['role'] === 'admin'): ?><i class="bi bi-shield-fill me-1"></i><?php elseif ($u['role'] === 'manager'): ?><i class="bi bi-shield me-1"></i><?php else: ?><i class="bi bi-person-check me-1"></i><?php endif; ?><?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td style="font-size:.82rem">
                            <?php if (!empty($u['stores'])): ?>
                                <?php foreach (array_slice($u['stores'], 0, 3) as $s): ?>
                                    <span class="badge me-1" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.7rem"><?= e($s['store_name']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($u['stores']) > 3): ?>
                                    <span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent);font-size:.7rem">+<?= count($u['stores']) - 3 ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.78rem">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted)">
                            <?php if (!empty($u['warehouses'])): ?>
                                <?php foreach (array_slice($u['warehouses'], 0, 3) as $w): ?>
                                    <span class="d-block" style="line-height:1.3"><?= e($w['warehouse_name']) ?> <small style="color:var(--text-muted)">(<?= e($w['store_name']) ?>)</small></span>
                                <?php endforeach; ?>
                                <?php if (count($u['warehouses']) > 3): ?>
                                    <span class="d-block" style="color:var(--accent)">+<?= count($u['warehouses']) - 3 ?> autres</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted)"><?= $u['last_login'] ? formatDate($u['last_login']) : 'Jamais' ?></td>
                        <td class="text-center">
                            <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.7rem">
                                <i class="bi bi-<?= $u['is_active'] ? 'check-circle' : 'x-circle' ?> me-1"></i><?= $u['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="d-flex gap-1 justify-content-center">
                                <button class="btn btn-sm btn-outline-primary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px"
                                    onclick="loadUserEditById(<?= $u['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px">
                                        <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0"><h5 class="modal-title" id="userModalTitle" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-person-plus me-2"></i>Nouvel Utilisateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" id="userForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="userId" value="0">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Nom complet *</label><input type="text" name="name" id="uName" class="form-control" required style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold"><i class="bi bi-envelope me-1"></i>Email *</label><input type="email" name="email" id="uEmail" class="form-control" required style="border-radius:8px"></div>
                        <div class="col-6"><label class="form-label fw-semibold"><i class="bi bi-telephone me-1"></i>Téléphone</label><input type="text" name="phone" id="uPhone" class="form-control" style="border-radius:8px"></div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><i class="bi bi-lock me-1"></i>Mot de passe <span id="pwdHint" class="text-muted" style="font-size:.75rem">(laisser vide pour ne pas changer)</span></label>
                            <input type="password" name="password" id="uPassword" class="form-control" style="border-radius:8px">
                        </div>
                        <div class="col-4"><label class="form-label fw-semibold"><i class="bi bi-shield me-1"></i>Rôle *</label>
                            <select name="role" id="uRole" class="form-select" required style="border-radius:8px">
                                <option value="cashier">Caissier</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-semibold"><i class="bi bi-building me-1"></i>Magasin par défaut</label>
                            <select name="warehouse_id" id="uWhId" class="form-select" style="border-radius:8px">
                                <option value="">Aucun</option>
                                <?php foreach ($allWh as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= e($w['name']) ?> — <?= e($w['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Boutiques (cases à cocher) -->
                        <div class="col-12 mt-2">
                            <label class="form-label fw-semibold"><i class="bi bi-shop me-1"></i>Boutiques *</label>
                            <div class="d-flex flex-wrap gap-2" id="storeCheckboxes">
                                <?php foreach ($stores as $s): ?>
                                <div class="form-check">
                                    <input class="form-check-input store-cb" type="checkbox" name="store_ids[]" value="<?= $s['id'] ?>" id="store_<?= $s['id'] ?>" data-store-id="<?= $s['id'] ?>">
                                    <label class="form-check-label" for="store_<?= $s['id'] ?>" style="font-size:.85rem"><?= e($s['name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Magasins (cases à cocher, filtrés par boutiques sélectionnées) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold"><i class="bi bi-building me-1"></i>Magasins</label>
                            <div class="d-flex flex-wrap gap-2" id="warehouseCheckboxes">
                                <?php foreach ($allWh as $w): ?>
                                <div class="form-check wh-check-item" data-store-id="<?= $w['store_id'] ?>">
                                    <input class="form-check-input warehouse-cb" type="checkbox" name="warehouse_ids[]" value="<?= $w['id'] ?>" id="wh_<?= $w['id'] ?>">
                                    <label class="form-check-label" for="wh_<?= $w['id'] ?>" style="font-size:.85rem"><?= e($w['name']) ?> <small style="color:var(--text-muted)">(<?= e($w['store_name']) ?>)</small></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="whEmptyMsg" class="text-muted mt-1" style="font-size:.78rem;display:none">
                                <i class="bi bi-info-circle me-1"></i>Sélectionnez d'abord une boutique pour voir ses magasins.
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div class="col-12 mt-2" id="permissionsSection">
                            <label class="form-label fw-semibold"><i class="bi bi-shield-lock me-1"></i>Permissions</label>
                            <div id="permAdminNotice" class="alert alert-info py-2 small d-none" style="font-size:.78rem;border-radius:8px">
                                <i class="bi bi-info-circle me-1"></i>Les administrateurs ont accès à toutes les fonctionnalités automatiquement.
                            </div>
                            <div id="permCheckboxes">
                                <?php
                                $permMap = getPermissionMap();
                                $currentGroup = '';
                                foreach ($permMap as $key => [$label, $group]):
                                    if ($group !== $currentGroup):
                                        if ($currentGroup !== '') echo '</div>';
                                        $currentGroup = $group;
                                        echo '<div class="mb-1 mt-2" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted);font-weight:600">' . e($group) . '</div>';
                                        echo '<div class="d-flex flex-wrap gap-2 mb-1">';
                                    endif;
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input perm-cb" type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="perm_<?= e($key) ?>">
                                    <label class="form-check-label" for="perm_<?= e($key) ?>" style="font-size:.82rem"><?= e($label) ?></label>
                                </div>
                                <?php endforeach; ?>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;border-radius:6px" onclick="document.querySelectorAll('.perm-cb').forEach(cb=>cb.checked=true)"><i class="bi bi-check-all me-1"></i>Tout sélectionner</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;border-radius:6px" onclick="document.querySelectorAll('.perm-cb').forEach(cb=>cb.checked=false)"><i class="bi bi-x-circle me-1"></i>Tout désélectionner</button>
                                </div>
                            </div>
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
$extraScript = '<script>' . "\n"
    . 'const allWarehouses = ' . json_encode($allWh) . ';' . "\n"
    . 'const usersData = ' . json_encode(array_values($users)) . ';' . "\n"
    . <<<JS

// Filtrer les magasins selon les boutiques cochées
function updateWarehouseVisibility() {
    const checkedStores = [...document.querySelectorAll('.store-cb:checked')].map(cb => parseInt(cb.dataset.storeId));
    document.querySelectorAll('.wh-check-item').forEach(el => {
        const storeId = parseInt(el.dataset.storeId);
        el.style.display = checkedStores.includes(storeId) ? '' : 'none';
        const cb = el.querySelector('.warehouse-cb');
        if (!checkedStores.includes(storeId)) cb.checked = false;
    });
    const whEmpty = document.getElementById('whEmptyMsg');
    whEmpty.style.display = checkedStores.length === 0 ? '' : 'none';
}

document.querySelectorAll('.store-cb').forEach(cb => {
    cb.addEventListener('change', updateWarehouseVisibility);
});

function loadUserEditById(userId) {
    const u = usersData.find(x => x.id == userId);
    if (!u) return;
    loadUserEdit(u);
}

function loadUserEdit(u) {
    document.getElementById('userModalTitle').textContent = 'Modifier Utilisateur';
    document.getElementById('userId').value = u.id;
    document.getElementById('uName').value = u.name || '';
    document.getElementById('uEmail').value = u.email || '';
    document.getElementById('uPhone').value = u.phone || '';
    document.getElementById('uRole').value = u.role || 'cashier';
    document.getElementById('uWhId').value = u.warehouse_id || '';
    document.getElementById('pwdHint').style.display = 'inline';

    // Cocher les boutiques de l'utilisateur
    const userStoreIds = (u.stores || []).map(s => parseInt(s.store_id));
    document.querySelectorAll('.store-cb').forEach(cb => {
        cb.checked = userStoreIds.includes(parseInt(cb.value));
    });

    // Mettre à jour la visibilité des magasins
    updateWarehouseVisibility();

    // Cocher les magasins de l'utilisateur
    const userWhIds = (u.warehouses || []).map(w => parseInt(w.warehouse_id));
    document.querySelectorAll('.warehouse-cb').forEach(cb => {
        cb.checked = userWhIds.includes(parseInt(cb.value));
    });

    // Cocher les permissions de l'utilisateur
    const userPerms = u.permissions ? JSON.parse(u.permissions) : [];
    document.querySelectorAll('.perm-cb').forEach(cb => {
        cb.checked = userPerms.includes(cb.value);
    });
    handleRoleChangeForPerms();

    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function handleRoleChangeForPerms() {
    const role = document.getElementById('uRole').value;
    const permCheckboxes = document.getElementById('permCheckboxes');
    const permNotice = document.getElementById('permAdminNotice');
    if (role === 'admin') {
        permCheckboxes.style.display = 'none';
        permNotice.classList.remove('d-none');
        document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = true);
    } else {
        permCheckboxes.style.display = '';
        permNotice.classList.add('d-none');
    }
}

document.getElementById('uRole').addEventListener('change', handleRoleChangeForPerms);

document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('userModalTitle').textContent = 'Nouvel Utilisateur';
    document.getElementById('userId').value = 0;
    document.getElementById('pwdHint').style.display = 'none';
    this.querySelector('form').reset();
    document.querySelectorAll('.store-cb').forEach(cb => cb.checked = false);
    document.querySelectorAll('.warehouse-cb').forEach(cb => cb.checked = false);
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
    updateWarehouseVisibility();
    handleRoleChangeForPerms();
});

// Initialiser la visibilité des magasins
updateWarehouseVisibility();
JS
    . '</script>';
require_once __DIR__ . '/layout_bottom.php';
?>