<?php
$message = '';
$per_page = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO agences (nom, adresse, telephone, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['nom'], $_POST['adresse'], $_POST['telephone'], $_POST['email']]);
            $message = '<div class="alert alert-success">Agence ajoutée avec succès.</div>';
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE agences SET nom=?, adresse=?, telephone=?, email=? WHERE id=?");
            $stmt->execute([$_POST['nom'], $_POST['adresse'], $_POST['telephone'], $_POST['email'], $_POST['id']]);
            $message = '<div class="alert alert-success">Agence modifiée avec succès.</div>';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM agences WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Agence supprimée avec succès.</div>';
        }
    }
}

$stmt = $pdo->query("SELECT COUNT(*) FROM agences");
$total = $stmt->fetchColumn();
$p = paginate($total, $per_page);

$stmt = $pdo->query("SELECT * FROM agences ORDER BY nom LIMIT " . intval($p['per_page']) . " OFFSET " . intval($p['offset']));
$agences = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Agences</h2>
    <button class="btn btn-primary" onclick="document.getElementById('agenceModal').classList.add('show')">
        <i class="bi bi-plus-circle"></i> Nouvelle Agence
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Adresse</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                    <th>Lots</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agences as $agence): ?>
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lots WHERE agence_id = ?");
                $stmt->execute([$agence['id']]);
                $nbLots = $stmt->fetchColumn();
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($agence['nom']); ?></strong></td>
                    <td><?php echo htmlspecialchars($agence['adresse']); ?></td>
                    <td><?php echo htmlspecialchars($agence['telephone']); ?></td>
                    <td><?php echo htmlspecialchars($agence['email']); ?></td>
                    <td><span class="badge badge-primary"><?php echo $nbLots; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editAgence(<?php echo $agence['id']; ?>, '<?php echo htmlspecialchars($agence['nom']); ?>', '<?php echo htmlspecialchars($agence['adresse']); ?>', '<?php echo htmlspecialchars($agence['telephone']); ?>', '<?php echo htmlspecialchars($agence['email']); ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $agence['id']; ?>, '<?php echo htmlspecialchars($agence['nom']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo renderPagination($total, $per_page, 'index.php?page=agences&'); ?>

<div class="modal" id="agenceModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Agence</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('agenceModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="agenceId">
                <div class="mb-3">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
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
                <p>Êtes-vous sûr de vouloir supprimer <strong id="deleteNom"></strong> ?</p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAgence(id, nom, adresse, telephone, email) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('agenceId').value = id;
    document.querySelector('#agenceModal input[name="nom"]').value = nom;
    document.querySelector('#agenceModal input[name="adresse"]').value = adresse;
    document.querySelector('#agenceModal input[name="telephone"]').value = telephone;
    document.querySelector('#agenceModal input[name="email"]').value = email;
    document.getElementById('agenceModal').classList.add('show');
}

function confirmDelete(id, nom) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteModal').classList.add('show');
}
</script>