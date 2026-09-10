<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('customers');

$pageTitle = 'Clients';
$customerModel = new Customer();
$storeId = currentStoreId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Requête invalide.');
        redirect(BASE_URL . '/views/customers.php');
    }

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $id > 0) {
        $customerModel->delete($id);
        setFlash('success', 'Client supprimé.');
        redirect(BASE_URL . '/views/customers.php');
    }

    $data = [
        'store_id' => $storeId,
        'name'     => sanitize($_POST['name'] ?? ''),
        'phone'    => sanitize($_POST['phone'] ?? '') ?: null,
        'email'    => sanitize($_POST['email'] ?? '') ?: null,
        'address'  => sanitize($_POST['address'] ?? '') ?: null,
        'notes'    => sanitize($_POST['notes'] ?? '') ?: null,
    ];

    if (empty($data['name'])) {
        setFlash('error', 'Le nom est requis.');
    } elseif ($id > 0) {
        $customerModel->update($id, $data);
        setFlash('success', 'Client mis à jour.');
    } else {
        $customerModel->insert($data);
        setFlash('success', 'Client ajouté.');
    }
    redirect(BASE_URL . '/views/customers.php');
}

$search    = sanitize($_GET['search'] ?? '');
$customers = $customerModel->getByStore($storeId, $search);
$editId    = (int)($_GET['edit'] ?? 0);
$editCustomer = $editId ? $customerModel->find($editId) : null;

// Historique achat si vue détail
$viewId = (int)($_GET['view'] ?? 0);
$viewCustomer = null;
$purchaseHistory = [];
if ($viewId) {
    $viewCustomer = $customerModel->find($viewId);
    $purchaseHistory = $customerModel->getPurchaseHistory($viewId);
}

require_once __DIR__ . '/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <div class="input-group input-group-sm" style="width:240px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Nom, téléphone, email..." style="border-radius:0 8px 8px 0">
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:8px"><i class="bi bi-search me-1"></i>Rechercher</button>
    </form>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" style="border-radius:8px">
        <i class="bi bi-person-plus me-1"></i>Nouveau Client
    </button>
</div>

<div class="row g-3">
    <div class="col-lg-<?= $viewCustomer ? '6' : '12' ?>">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-people me-2"></i>Clients (<?= count($customers) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3"><i class="bi bi-person me-1"></i>Nom</th>
                                <th><i class="bi bi-telephone me-1"></i>Téléphone</th>
                                <th><i class="bi bi-envelope me-1"></i>Email</th>
                                <th class="text-end"><i class="bi bi-currency-dollar me-1"></i>Total Achats</th>
                                <th class="text-center pe-3"><i class="bi bi-gear me-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                            <tr class="<?= $c['id'] == $viewId ? 'table-active' : '' ?>">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#818CF8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;flex-shrink:0">
                                            <?= strtoupper(substr($c['name'], 0, 2)) ?>
                                        </div>
                                        <span style="font-size:.875rem;font-weight:500"><?= e($c['name']) ?></span>
                                    </div>
                                </td>
                                <td style="font-size:.82rem"><?= e($c['phone'] ?? '—') ?></td>
                                <td style="font-size:.82rem;color:var(--text-muted)"><?= e($c['email'] ?? '—') ?></td>
                                <td class="text-end" style="font-size:.85rem;font-weight:600;color:var(--accent)"><?= formatMoney((float)$c['total_purchases']) ?></td>
                                <td class="text-center pe-3">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="?view=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px" title="Historique">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px"
                                            data-bs-toggle="modal" data-bs-target="#customerModal"
                                            onclick='loadEdit(<?= htmlspecialchars(json_encode($c)) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce client ?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customers)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-people d-block" style="font-size:2rem;opacity:.2"></i>Aucun client trouvé</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($viewCustomer): ?>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header-custom">
                <h6><i class="bi bi-person me-2"></i><?= e($viewCustomer['name']) ?> — Historique</h6>
                <a href="?" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem"><i class="bi bi-x-lg me-1"></i>Fermer</a>
            </div>
            <div class="card-body py-2 px-3">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div style="background:var(--body-bg);border-radius:8px;padding:.6rem .8rem;text-align:center">
                            <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem;color:var(--accent)"><?= formatMoney((float)$viewCustomer['total_purchases']) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted)"><i class="bi bi-cash-stack me-1"></i>Total achats</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:var(--body-bg);border-radius:8px;padding:.6rem .8rem;text-align:center">
                            <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem"><?= count($purchaseHistory) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted)"><i class="bi bi-receipt me-1"></i>Transactions</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:var(--body-bg);border-radius:8px;padding:.6rem .8rem;text-align:center">
                            <div style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem;color:#10B981"><?= $viewCustomer['loyalty_points'] ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted)"><i class="bi bi-star me-1"></i>Points fidélité</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
                <table class="table table-sm mb-0">
                    <thead><tr><th class="ps-3"><i class="bi bi-file-earmark-text me-1"></i>Facture</th><th><i class="bi bi-calendar me-1"></i>Date</th><th><i class="bi bi-building me-1"></i>Magasin</th><th class="text-end pe-3"><i class="bi bi-currency-dollar me-1"></i>Montant</th></tr></thead>
                    <tbody>
                        <?php foreach ($purchaseHistory as $h): ?>
                        <tr>
                            <td class="ps-3">
                                <a href="<?= BASE_URL ?>/views/invoice.php?id=<?= $h['id'] ?>" target="_blank" style="font-family:monospace;font-size:.78rem;color:var(--accent);text-decoration:none"><?= e($h['invoice_number']) ?></a>
                            </td>
                            <td style="font-size:.78rem"><?= formatDate($h['sale_date'], 'd/m/Y') ?></td>
                            <td style="font-size:.78rem;color:var(--text-muted)"><?= e($h['warehouse_name']) ?></td>
                            <td class="text-end pe-3" style="font-size:.82rem;font-weight:600"><?= formatMoney((float)$h['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($purchaseHistory)): ?>
                        <tr><td colspan="4" class="text-center py-3 text-muted" style="font-size:.82rem"><i class="bi bi-bag-x d-block" style="font-size:2rem;opacity:.2"></i>Aucun achat enregistré</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL Client -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="customerModalTitle" style="font-family:Syne,sans-serif;font-weight:700"><i class="bi bi-person-plus me-2"></i>Nouveau Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="customerId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Nom complet <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="cName" class="form-control" required style="border-radius:8px">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold"><i class="bi bi-telephone me-1"></i>Téléphone</label>
                            <input type="tel" name="phone" id="cPhone" class="form-control" style="border-radius:8px" placeholder="+221 77 000 0000">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold"><i class="bi bi-envelope me-1"></i>Email</label>
                            <input type="email" name="email" id="cEmail" class="form-control" style="border-radius:8px">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-geo-alt me-1"></i>Adresse</label>
                        <input type="text" name="address" id="cAddress" class="form-control" style="border-radius:8px">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Notes</label>
                        <textarea name="notes" id="cNotes" class="form-control" rows="2" style="border-radius:8px;resize:none"></textarea>
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
function loadEdit(c) {
    document.getElementById('customerModalTitle').textContent = 'Modifier Client';
    document.getElementById('customerId').value = c.id;
    document.getElementById('cName').value = c.name || '';
    document.getElementById('cPhone').value = c.phone || '';
    document.getElementById('cEmail').value = c.email || '';
    document.getElementById('cAddress').value = c.address || '';
    document.getElementById('cNotes').value = c.notes || '';
}
document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerModalTitle').textContent = 'Nouveau Client';
    document.getElementById('customerId').value = 0;
    this.querySelector('form').reset();
});
</script>
JS;
require_once __DIR__ . '/layout_bottom.php';
?>
