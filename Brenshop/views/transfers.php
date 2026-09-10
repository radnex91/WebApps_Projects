<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('transfers');

$pageTitle = 'Transferts de Stock';
$transferModel = new Transfer();
$warehouseModel = new Warehouse();
$stockModel = new Stock();
$storeId = currentStoreId();

// Traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Requête invalide.');
        redirect(BASE_URL . '/views/transfers.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fromWh  = (int)($_POST['from_warehouse_id'] ?? 0);
        $toWh    = (int)($_POST['to_warehouse_id'] ?? 0);
        $note    = sanitize($_POST['notes'] ?? '');
        $itemIds = $_POST['product_id'] ?? [];
        $itemQty = $_POST['quantity'] ?? [];

        if ($fromWh === $toWh) {
            setFlash('error', 'Les magasins source et destination doivent être différents.');
            redirect(BASE_URL . '/views/transfers.php');
        }

        $items = [];
        foreach ($itemIds as $i => $pid) {
            $pid = (int)$pid;
            $qty = (float)($itemQty[$i] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $items[] = ['product_id' => $pid, 'quantity' => $qty];
            }
        }

        if (empty($items)) {
            setFlash('error', 'Ajoutez au moins un produit au transfert.');
            redirect(BASE_URL . '/views/transfers.php');
        }

        $transferData = [
            'reference'          => generateTransferReference(),
            'from_warehouse_id'  => $fromWh,
            'to_warehouse_id'    => $toWh,
            'user_id'            => (int)$_SESSION['user_id'],
            'status'             => 'pending',
            'notes'              => $note,
        ];

        try {
            $transferModel->createTransfer($transferData, $items);
            setFlash('success', 'Transfert créé avec succès.');
        } catch (Exception $e) {
            setFlash('error', 'Erreur : ' . $e->getMessage());
        }
        redirect(BASE_URL . '/views/transfers.php');
    }

    if ($action === 'complete') {
        $tid = (int)($_POST['transfer_id'] ?? 0);
        try {
            if ($transferModel->completeTransfer($tid, (int)$_SESSION['user_id'])) {
                setFlash('success', 'Transfert exécuté avec succès. Les stocks ont été mis à jour.');
            } else {
                setFlash('error', 'Impossible d\'exécuter ce transfert.');
            }
        } catch (Exception $e) {
            setFlash('error', 'Erreur stock insuffisant : ' . $e->getMessage());
        }
        redirect(BASE_URL . '/views/transfers.php');
    }

    if ($action === 'cancel') {
        $tid = (int)($_POST['transfer_id'] ?? 0);
        $transferModel->update($tid, ['status' => 'cancelled']);
        setFlash('success', 'Transfert annulé.');
        redirect(BASE_URL . '/views/transfers.php');
    }
}

$transfers  = $transferModel->getWithDetails(60);
$allWh      = $warehouseModel->getAllWithStore();
$myStock    = [];

// Stock du magasin courant pour sélection produits
$currentWh = currentWarehouseId();
if ($currentWh) {
    $myStock = $stockModel->getWarehouseStock($currentWh);
}

require_once __DIR__ . '/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0" style="font-size:.875rem">Gérez les transferts de marchandises entre vos magasins et boutiques.</p>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newTransferModal" style="border-radius:8px">
        <i class="bi bi-arrow-left-right me-1"></i>Nouveau Transfert
    </button>
</div>

<!-- Transferts -->
<div class="card">
    <div class="card-header-custom">
        <h6><i class="bi bi-arrow-left-right me-2"></i>Historique des Transferts</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3"><i class="bi bi-hash me-1"></i>Référence</th>
                        <th><i class="bi bi-box-arrow-right me-1"></i>De</th>
                        <th><i class="bi bi-box-arrow-in-right me-1"></i>Vers</th>
                        <th><i class="bi bi-person me-1"></i>Initiateur</th>
                        <th><i class="bi bi-calendar me-1"></i>Date</th>
                        <th class="text-center"><i class="bi bi-flag me-1"></i>Statut</th>
                        <th class="text-center pe-3"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                    <?php
                    $statusColors = [
                        'pending'    => ['bg' => 'rgba(245,158,11,0.12)', 'color' => '#F59E0B', 'label' => 'En attente'],
                        'in_transit' => ['bg' => 'rgba(99,102,241,0.12)', 'color' => '#6366F1', 'label' => 'En transit'],
                        'completed'  => ['bg' => 'rgba(16,185,129,0.12)', 'color' => '#10B981', 'label' => 'Complété'],
                        'cancelled'  => ['bg' => 'rgba(100,116,139,0.12)','color' => '#64748B', 'label' => 'Annulé'],
                    ];
                    $sc = $statusColors[$t['status']] ?? $statusColors['pending'];
                    ?>
                    <tr>
                        <td class="ps-3">
                            <span style="font-family:monospace;font-size:.82rem;font-weight:600;color:var(--accent)"><?= e($t['reference']) ?></span>
                        </td>
                        <td>
                            <div style="font-size:.82rem;font-weight:500"><?= e($t['from_warehouse']) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted)"><?= e($t['from_store']) ?></div>
                        </td>
                        <td>
                            <div style="font-size:.82rem;font-weight:500"><?= e($t['to_warehouse']) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted)"><?= e($t['to_store']) ?></div>
                        </td>
                        <td style="font-size:.82rem"><?= e($t['user_name']) ?></td>
                        <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($t['created_at']) ?></td>
                        <td class="text-center">
                            <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-size:.72rem">
                                <?= $sc['label'] ?>
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <?php if ($t['status'] === 'pending'): ?>
                            <div class="d-flex gap-1 justify-content-center">
                                <form method="POST" onsubmit="return confirm('Exécuter ce transfert ? Les stocks seront modifiés.')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px">
                                        <i class="bi bi-check-lg me-1"></i>Exécuter
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Annuler ce transfert ?')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.25rem .5rem;font-size:.72rem;border-radius:6px">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                            <?php elseif ($t['status'] === 'completed'): ?>
                            <span style="font-size:.72rem;color:#10B981"><i class="bi bi-check-circle-fill me-1"></i><?= formatDate($t['completed_at'], 'd/m/Y') ?></span>
                            <?php else: ?>
                            <span style="font-size:.72rem;color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transfers)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-arrow-left-right d-block" style="font-size:2rem;opacity:.2"></i>Aucun transfert enregistré</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL Nouveau Transfert -->
<div class="modal fade" id="newTransferModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family:Syne,sans-serif;font-weight:700">
                    <i class="bi bi-arrow-left-right me-2 text-primary"></i>Nouveau Transfert
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Magasin Source <span class="text-danger">*</span></label>
                            <select name="from_warehouse_id" id="fromWh" class="form-select" required style="border-radius:8px" onchange="loadSourceStock(this.value)">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($allWh as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= $wh['id'] == $currentWh ? 'selected' : '' ?>>
                                    <?= e($wh['name']) ?> — <?= e($wh['store_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Magasin Destination <span class="text-danger">*</span></label>
                            <select name="to_warehouse_id" class="form-select" required style="border-radius:8px">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($allWh as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?> — <?= e($wh['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-box-seam me-1"></i>Produits à transférer</label>
                        <div id="transferItems">
                            <div class="transfer-item-row row g-2 mb-2 align-items-center">
                                <div class="col-7">
                                    <select name="product_id[]" class="form-select form-select-sm product-select" style="border-radius:8px">
                                        <option value="">Sélectionner un produit...</option>
                                        <?php foreach ($myStock as $s): ?>
                                        <option value="<?= $s['product_id'] ?>" data-stock="<?= (float)$s['quantity'] ?>">
                                            <?= e($s['name']) ?> (Dispo: <?= number_format((float)$s['quantity']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="quantity[]" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="Qté" style="border-radius:8px">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeTransferRow(this)" style="border-radius:8px">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTransferRow()" style="border-radius:8px">
                            <i class="bi bi-plus-lg me-1"></i>Ajouter un produit
                        </button>
                    </div>

                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" style="border-radius:8px;resize:none" placeholder="Motif du transfert, instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Annuler</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius:8px">
                        <i class="bi bi-send me-1"></i>Créer le Transfert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>

<script>
const allWarehouses = <?= json_encode($allWh) ?>;
const BASE_URL = '<?= BASE_URL ?>';

function addTransferRow() {
    const container = document.getElementById('transferItems');
    const first = container.querySelector('.transfer-item-row');
    const clone = first.cloneNode(true);
    clone.querySelectorAll('input,select').forEach(el => el.value = '');
    container.appendChild(clone);
}

function removeTransferRow(btn) {
    const rows = document.querySelectorAll('.transfer-item-row');
    if (rows.length > 1) btn.closest('.transfer-item-row').remove();
}

function loadSourceStock(warehouseId) {
    if (!warehouseId) return;
    fetch(`${BASE_URL}/controllers/stock_ajax.php?action=warehouse_stock&warehouse_id=${warehouseId}`)
        .then(r => r.json())
        .then(data => {
            const opts = '<option value="">Sélectionner un produit...</option>' +
                (data.data || []).map(s =>
                    `<option value="${s.product_id}" data-stock="${s.quantity}">` +
                    `${escHtml(s.name)} (Dispo: ${Math.floor(s.quantity)})</option>`
                ).join('');
            document.querySelectorAll('.product-select').forEach(sel => sel.innerHTML = opts);
        });
}

function escHtml(str) {
    const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}

const fromWh = document.getElementById('fromWh');
if (fromWh.value) loadSourceStock(fromWh.value);
</script>
