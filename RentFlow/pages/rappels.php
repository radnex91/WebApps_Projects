<?php
$message = '';
$per_page = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'send_rappel') {
            $stmt = $pdo->prepare("INSERT INTO rappels (paiement_id, lot_id, agencia_id, date_rappel, type, message) VALUES (?, ?, ?, NOW(), 'retard', ?)");
            $stmt->execute([$_POST['paiement_id'], $_POST['lot_id'], $_POST['agence_id'], $_POST['message']]);
            $message = '<div class="alert alert-success">Rappel envoyé avec succès.</div>';
        } elseif ($_POST['action'] === 'mark_resolved') {
            $stmt = $pdo->prepare("UPDATE rappels SET statut='resolved', date_envoi=NOW() WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Rappel marqué comme résolu.</div>';
        }
    }
}

$filter_statut = $_GET['statut'] ?? '';

$sql_count = "SELECT COUNT(*) FROM rappels r WHERE 1=1";
if ($filter_statut) {
    $sql_count .= " AND r.statut = ?";
}
$stmt = $pdo->prepare($sql_count);
if ($filter_statut) $stmt->execute([$filter_statut]);
else $stmt->execute();
$total = $stmt->fetchColumn();
$p = paginate($total, $per_page);

$sql = "SELECT r.*, p.mois, p.montant, p.statut as paiement_statut, l.adresse, l.ville, a.nom as nom_agence 
    FROM rappels r 
    JOIN paiements p ON r.paiement_id = p.id 
    JOIN lots l ON r.lot_id = l.id 
    JOIN agences a ON r.agencia_id = a.id 
    WHERE 1=1";
if ($filter_statut) {
    $sql .= " AND r.statut = ?";
}
$sql .= " ORDER BY r.date_rappel DESC LIMIT " . intval($p['per_page']) . " OFFSET " . intval($p['offset']);

$stmt = $pdo->prepare($sql);
if ($filter_statut) $stmt->execute([$filter_statut]);
else $stmt->execute();
$rappels = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT p.id as paiement_id, p.mois, p.montant, p.statut, l.id as lot_id, l.adresse, l.ville, l.agence_id, a.nom as nom_agence 
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    LEFT JOIN agences a ON l.agence_id = a.id 
    WHERE p.statut = 'retard' 
    AND NOT EXISTS (SELECT 1 FROM rappels WHERE paiement_id = p.id AND statut != 'resolved')
    ORDER BY p.date_paiement ASC
");
$paiements_retard = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Rappels Automatiques</h2>
    <?php if (getParam('rappels_auto', '1') === '1'): ?>
    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Automatiques activés</span>
    <?php else: ?>
    <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Automatiques désactivés</span>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo count($paiements_retard); ?></h3>
                <p class="text-muted">Paiements en retard</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger"><?php echo count(array_filter($rappels, fn($r) => $r['statut'] === 'pending')); ?></h3>
                <p class="text-muted">Rappels en attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo count(array_filter($rappels, fn($r) => $r['statut'] === 'resolved')); ?></h3>
                <p class="text-muted">Rappels résolus</p>
            </div>
        </div>
    </div>
</div>

<?php echo $message; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="d-flex flex-wrap align-items-center gap-3">
            <select name="statut" class="form-select" style="width:auto;">
                <option value="">Tous les statuts</option>
                <option value="pending" <?php echo $filter_statut === 'pending' ? 'selected' : ''; ?>>En attente</option>
                <option value="sent" <?php echo $filter_statut === 'sent' ? 'selected' : ''; ?>>Envoyé</option>
                <option value="viewed" <?php echo $filter_statut === 'viewed' ? 'selected' : ''; ?>>Vu</option>
                <option value="resolved" <?php echo $filter_statut === 'resolved' ? 'selected' : ''; ?>>Résolu</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
        </form>
    </div>
</div>

<?php if (count($paiements_retard) > 0): ?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Paiements en retard à rappeler</h5>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Lot</th>
                    <th>Agence</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paiements_retard as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['mois']); ?></td>
                    <td><?php echo htmlspecialchars($p['adresse'] . ', ' . $p['ville']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_agence'] ?? '-'); ?></td>
                    <td><?php echo formatPrix($p['montant']); ?></td>
                    <td><span class="badge badge-danger">Retard</span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="openRappelModal(<?php echo $p['paiement_id']; ?>, <?php echo $p['lot_id']; ?>, '<?php echo htmlspecialchars($p['adresse']); ?>', '<?php echo htmlspecialchars($p['nom_agence'] ?? ''); ?>', '<?php echo $p['agence_id']; ?>')">
                            <i class="bi bi-bell"></i> Envoyer
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Agence</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rappels as $r): ?>
                <?php
                $badge_class = match($r['statut']) {
                    'pending' => 'secondary',
                    'sent' => 'info',
                    'viewed' => 'warning',
                    'resolved' => 'success'
                };
                ?>
                <tr>
                    <td><?php echo formatDate($r['date_rappel']); ?></td>
                    <td><?php echo htmlspecialchars($r['adresse'] . ', ' . $r['ville']); ?></td>
                    <td><?php echo htmlspecialchars($r['nom_agence']); ?></td>
                    <td><span class="badge badge-<?php echo $r['type'] === 'retard' ? 'warning' : 'danger'; ?>"><?php echo ucfirst($r['type']); ?></span></td>
                    <td><?php echo htmlspecialchars(mb_strimwidth($r['message'], 0, 50, '...')); ?></td>
                    <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($r['statut']); ?></span></td>
                    <td>
                        <?php if ($r['statut'] !== 'resolved'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="mark_resolved">
                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($rappels) === 0): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun rappel</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo renderPagination($total, $per_page, 'index.php?page=rappels&statut=' . $filter_statut . '&'); ?>

<div class="modal" id="rappelModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Envoyer un rappel</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('rappelModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="send_rappel">
                <input type="hidden" name="paiement_id" id="rappelPaiementId">
                <input type="hidden" name="lot_id" id="rappelLotId">
                <input type="hidden" name="agence_id" id="rappelAgenceId">
                <div class="mb-3">
                    <label class="form-label">Lot</label>
                    <input type="text" id="rappelLot" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Agence</label>
                    <input type="text" id="rappelAgence" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="4" required>Madame, Monsieur,

Nous vous informons qu'un paiement est en retard pour le lot susmentionné.

Merci de regulariser cette situation dans les plus brefs délais.

Cordialement,
<?php echo getParam('nom_entreprise', 'RentFlow'); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Envoyer le rappel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRappelModal(paiementId, lotId, lotAdresse, agenceNom, agenciaId) {
    document.getElementById('rappelPaiementId').value = paiementId;
    document.getElementById('rappelLotId').value = lotId;
    document.getElementById('rappelAgenceId').value = agenciaId;
    document.getElementById('rappelLot').value = lotAdresse;
    document.getElementById('rappelAgence').value = agenciaNom || 'Non assignee';
    document.getElementById('rappelModal').classList.add('show');
}
</script>