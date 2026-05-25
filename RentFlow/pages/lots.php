<?php
$message = '';
$per_page = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO lots (code, bailleur_id, adresse, ville, loyer_mensuel, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['code'], $_POST['bailleur_id'], $_POST['adresse'], $_POST['ville'], $_POST['loyer_mensuel'], $_POST['type']]);
            if (!empty($_POST['agence_id'])) {
                $stmt = $pdo->prepare("UPDATE lots SET agencia_id = ? WHERE id = LAST_INSERT_ID()");
                $stmt->execute([$_POST['agence_id']]);
            }
            $message = '<div class="alert alert-success">Lot ajoute avec succes.</div>';
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE lots SET code=?, bailleur_id=?, adresse=?, ville=?, loyer_mensuel=?, type=? WHERE id=?");
            $stmt->execute([$_POST['code'], $_POST['bailleur_id'], $_POST['adresse'], $_POST['ville'], $_POST['loyer_mensuel'], $_POST['type'], $_POST['id']]);
            if (isset($_POST['agence_id'])) {
                $stmt = $pdo->prepare("UPDATE lots SET agencia_id = ? WHERE id = ?");
                $stmt->execute([$_POST['agence_id'], $_POST['id']]);
            }
            $message = '<div class="alert alert-success">Lot modifie avec succes.</div>';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM lots WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Lot supprime avec succes.</div>';
        }
    }
}

$stmt = $pdo->query("SELECT COUNT(*) FROM lots");
$total = $stmt->fetchColumn();
$p = paginate($total, $per_page);

$stmt = $pdo->query("SELECT l.*, b.nom as nom_bailleur, b.prenom as prenom_bailleur, a.nom as nom_agence 
    FROM lots l 
    LEFT JOIN bailleurs b ON l.bailleur_id = b.id 
    LEFT JOIN agences a ON l.agence_id = a.id 
    ORDER BY l.adresse LIMIT " . intval($p['per_page']) . " OFFSET " . intval($p['offset']));
$lots = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom, prenom FROM bailleurs ORDER BY nom");
$bailleurs = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom FROM agences ORDER BY nom");
$agences = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Lots</h2>
    <button class="btn btn-primary" onclick="document.getElementById('lotModal').classList.add('show')">
        <i class="bi bi-plus-circle"></i> Nouveau Lot
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Adresse</th>
                    <th>Ville</th>
                    <th>Type</th>
                    <th>Loyer</th>
                    <th>Bailleur</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lots as $lot): ?>
                <tr>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($lot['code']); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($lot['adresse']); ?></strong></td>
                    <td><?php echo htmlspecialchars($lot['ville']); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($lot['type']); ?></span></td>
                    <td><?php echo formatPrix($lot['loyer_mensuel']); ?></td>
                    <td><?php echo htmlspecialchars($lot['nom_bailleur'] . ' ' . $lot['prenom_bailleur']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editLot(<?php echo $lot['id']; ?>, '<?php echo htmlspecialchars($lot['code']); ?>', <?php echo $lot['bailleur_id']; ?>, '<?php echo $lot['agence_id']; ?>', '<?php echo htmlspecialchars($lot['adresse']); ?>', '<?php echo htmlspecialchars($lot['ville']); ?>', <?php echo $lot['loyer_mensuel']; ?>, '<?php echo $lot['type']; ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $lot['id']; ?>, '<?php echo htmlspecialchars($lot['adresse']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo renderPagination($total, $per_page, 'index.php?page=lots&'); ?>

<div class="modal" id="lotModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Lot</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('lotModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="lotId">
                <div class="mb-3">
                    <label class="form-label">Code du lot</label>
                    <input type="text" name="code" id="lotCode" class="form-control" placeholder="Ex: LOT-001" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bailleur</label>
                    <select name="bailleur_id" class="form-select" required>
                        <option value="">Selectionner...</option>
                        <?php foreach ($bailleurs as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Agence</label>
                    <select name="agence_id" class="form-select">
                        <option value="">Selectionner...</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ville</label>
                    <input type="text" name="ville" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Loyer Mensuel (<?php echo getParam('devise', 'FCFA'); ?>)</label>
                    <input type="number" name="loyer_mensuel" class="form-control" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="appartement">Appartement</option>
                        <option value="maison">Maison</option>
                        <option value="local">Local</option>
                    </select>
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
                <p>Etes-vous sur de vouloir supprimer <strong id="deleteNom"></strong> ?</p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editLot(id, code, bailleur_id, age, adresse, ville, loyer, type) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('lotId').value = id;
    document.getElementById('lotCode').value = code;
    document.querySelector('#lotModal select[name="bailleur_id"]').value = bailleur_id;
    document.querySelector('#lotModal select[name="agence_id"]').value = age || '';
    document.querySelector('#lotModal input[name="adresse"]').value = adresse;
    document.querySelector('#lotModal input[name="ville"]').value = ville;
    document.querySelector('#lotModal input[name="loyer_mensuel"]').value = loyer;
    document.querySelector('#lotModal select[name="type"]').value = type;
    document.getElementById('lotModal').classList.add('show');
}

function confirmDelete(id, nom) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteModal').classList.add('show');
}
</script>