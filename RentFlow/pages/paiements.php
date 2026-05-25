<?php
$message = '';
$per_page = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $date_paiement = $_POST['date_paiement'] ?: date('Y-m-d');
            $jour = (int)date('j', strtotime($date_paiement));
            if ($jour < 5) $statut = 'avance';
            elseif ($jour <= 10) $statut = 'normal';
            else $statut = 'retard';
            
            $stmt = $pdo->prepare("INSERT INTO paiements (lot_id, mois, montant, date_paiement, statut) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE montant=?, date_paiement=?, statut=?");
            $stmt->execute([$_POST['lot_id'], $_POST['mois'], $_POST['montant'], $date_paiement, $statut, $_POST['montant'], $date_paiement, $statut]);
            $message = '<div class="alert alert-success">Paiement enregistré avec succès.</div>';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM paiements WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Paiement supprimé avec succès.</div>';
        }
    }
}

$filter_statut = $_GET['statut'] ?? '';
$filter_bailleur = $_GET['bailleur'] ?? '';

$sql = "SELECT COUNT(*) FROM paiements p JOIN lots l ON p.lot_id = l.id JOIN bailleurs b ON l.bailleur_id = b.id WHERE 1=1";
$params = [];

if ($filter_statut) {
    $sql .= " AND p.statut = ?";
    $params[] = $filter_statut;
}
if ($filter_bailleur) {
    $sql .= " AND b.id = ?";
    $params[] = $filter_bailleur;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$p = paginate($total, $per_page);

$sql = "SELECT p.*, l.adresse, l.ville, l.loyer_mensuel, b.nom as nom_bailleur, b.prenom as prenom_bailleur 
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    JOIN bailleurs b ON l.bailleur_id = b.id 
    WHERE 1=1";
$params = [];

if ($filter_statut) {
    $sql .= " AND p.statut = ?";
    $params[] = $filter_statut;
}
if ($filter_bailleur) {
    $sql .= " AND b.id = ?";
    $params[] = $filter_bailleur;
}
$sql .= " ORDER BY p.date_paiement DESC LIMIT " . intval($p['per_page']) . " OFFSET " . intval($p['offset']);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom, prenom FROM bailleurs ORDER BY nom");
$bailleurs = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, adresse, ville, loyer_mensuel FROM lots ORDER BY adresse");
$lots = $stmt->fetchAll();

$mois_courant = date('Y-m');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Paiements</h2>
    <button class="btn btn-primary" onclick="document.getElementById('paiementModal').classList.add('show')">
        <i class="bi bi-plus-circle"></i> Nouveau Paiement
    </button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="d-flex flex-wrap align-items-center gap-3">
            <select name="statut" class="form-select" style="width:auto;">
                <option value="">Tous les statuts</option>
                <option value="avance" <?php echo $filter_statut === 'avance' ? 'selected' : ''; ?>>Avance</option>
                <option value="normal" <?php echo $filter_statut === 'normal' ? 'selected' : ''; ?>>Normal</option>
                <option value="retard" <?php echo $filter_statut === 'retard' ? 'selected' : ''; ?>>Retard</option>
            </select>
            <select name="bailleur" class="form-select" style="width:auto;">
                <option value="">Tous les bailleurs</option>
                <?php foreach ($bailleurs as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $filter_bailleur == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
        </form>
    </div>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Lot</th>
                    <th>Bailleur</th>
                    <th>Montant</th>
                    <th>Date Paiement</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paiements as $p): ?>
                <?php
                $badge_class = match($p['statut']) {
                    'avance' => 'success',
                    'normal' => 'info',
                    'retard' => 'warning'
                };
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['mois']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['adresse'] . ', ' . $p['ville']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_bailleur'] . ' ' . $p['prenom_bailleur']); ?></td>
                    <td><?php echo formatPrix($p['montant']); ?></td>
                    <td><?php echo formatDate($p['date_paiement']); ?></td>
                    <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($p['statut']); ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['adresse'] . ' - ' . $p['mois']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo renderPagination($total, $per_page, 'index.php?page=paiements&statut=' . $filter_statut . '&bailleur=' . $filter_bailleur . '&'); ?>

<div class="modal" id="paiementModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Paiement</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('paiementModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="form-label">Lot</label>
                    <select name="lot_id" class="form-select" required onchange="updateMontant(this.value)">
                        <option value="">Sélectionner...</option>
                        <?php foreach ($lots as $l): ?>
                        <option value="<?php echo $l['id']; ?>" data-montant="<?php echo $l['loyer_mensuel']; ?>"><?php echo htmlspecialchars($l['adresse'] . ', ' . $l['ville']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mois</label>
                    <input type="month" name="mois" class="form-control" value="<?php echo $mois_courant; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Montant (<?php echo getParam('devise', 'FCFA'); ?>)</label>
                    <input type="number" name="montant" class="form-control" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date de Paiement</label>
                    <input type="date" name="date_paiement" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    <small class="text-muted">Avance (≤<?php echo getParam('jour_avance', 5); ?>), Normal (<?php echo getParam('jour_avance', 5)+1; ?>-<?php echo getParam('jour_retard', 10); ?>), Retard (> <?php echo getParam('jour_retard', 10); ?>)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="deleteModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Confirmation</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('deleteModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <p>Êtes-vous sûr de vouloir supprimer le paiement <strong id="deleteNom"></strong> ?</p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateMontant(lotId) {
    const select = document.querySelector('#paiementModal select[name="lot_id"]');
    const option = select.options[select.selectedIndex];
    if (option && option.dataset.montant) {
        document.querySelector('#paiementModal input[name="montant"]').value = option.dataset.montant;
    }
}

function confirmDelete(id, nom) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteModal').classList.add('show');
}
</script>