<?php
$message = '';
$per_page = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO bailleurs (nom, prenom, email, telephone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['nom'], $_POST['prenom'], $_POST['email'], $_POST['telephone']]);
            $message = '<div class="alert alert-success">Bailleur ajouté avec succès.</div>';
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE bailleurs SET nom=?, prenom=?, email=?, telephone=? WHERE id=?");
            $stmt->execute([$_POST['nom'], $_POST['prenom'], $_POST['email'], $_POST['telephone'], $_POST['id']]);
            $message = '<div class="alert alert-success">Bailleur modifié avec succès.</div>';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM bailleurs WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Bailleur supprimé avec succès.</div>';
        }
    }
}

$stmt = $pdo->query("SELECT COUNT(*) FROM bailleurs");
$total = $stmt->fetchColumn();
$p = paginate($total, $per_page);

$stmt = $pdo->prepare("SELECT * FROM bailleurs ORDER BY nom LIMIT " . intval($p['per_page']) . " OFFSET " . intval($p['offset']));
$bailleurs = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Bailleurs</h2>
    <button class="btn btn-primary" onclick="document.getElementById('bailleurModal').classList.add('show')">
        <i class="bi bi-plus-circle"></i> Nouveau Bailleur
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Lots</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bailleurs as $bailleur): ?>
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lots WHERE bailleur_id = ?");
                $stmt->execute([$bailleur['id']]);
                $nbLots = $stmt->fetchColumn();
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($bailleur['nom']); ?></strong></td>
                    <td><?php echo htmlspecialchars($bailleur['prenom']); ?></td>
                    <td><?php echo htmlspecialchars($bailleur['email']); ?></td>
                    <td><?php echo htmlspecialchars($bailleur['telephone']); ?></td>
                    <td><span class="badge badge-primary"><?php echo $nbLots; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editBailleur(<?php echo $bailleur['id']; ?>, '<?php echo htmlspecialchars($bailleur['nom']); ?>', '<?php echo htmlspecialchars($bailleur['prenom']); ?>', '<?php echo htmlspecialchars($bailleur['email']); ?>', '<?php echo htmlspecialchars($bailleur['telephone']); ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $bailleur['id']; ?>, '<?php echo htmlspecialchars($bailleur['nom'] . ' ' . $bailleur['prenom']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo renderPagination($total, $per_page, 'index.php?page=bailleurs&'); ?>

<div class="modal" id="bailleurModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Bailleur</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('bailleurModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="bailleurId">
                <div class="mb-3">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control">
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
function editBailleur(id, nom, prenom, email, telephone) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('bailleurId').value = id;
    document.querySelector('#bailleurModal input[name="nom"]').value = nom;
    document.querySelector('#bailleurModal input[name="prenom"]').value = prenom;
    document.querySelector('#bailleurModal input[name="email"]').value = email;
    document.querySelector('#bailleurModal input[name="telephone"]').value = telephone;
    document.getElementById('bailleurModal').classList.add('show');
}

function confirmDelete(id, nom) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteModal').classList.add('show');
}
</script>