<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['username'], $_POST['email'], $password, $_POST['role']]);
            $message = '<div class="alert alert-success">Utilisateur ajouté avec succès.</div>';
        } elseif ($_POST['action'] === 'edit') {
            if ($_POST['password']) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET username=?, email=?, password=?, role=? WHERE id=?");
                $stmt->execute([$_POST['username'], $_POST['email'], $password, $_POST['role'], $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET username=?, email=?, role=? WHERE id=?");
                $stmt->execute([$_POST['username'], $_POST['email'], $_POST['role'], $_POST['id']]);
            }
            $message = '<div class="alert alert-success">Utilisateur modifié avec succès.</div>';
        } elseif ($_POST['action'] === 'toggle') {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET active = NOT active WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Statut mis à jour.</div>';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = '<div class="alert alert-success">Utilisateur supprimé.</div>';
        }
    }
}

$stmt = $pdo->query("SELECT * FROM utilisateurs ORDER BY username");
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Utilisateurs</h2>
    <button class="btn btn-primary" onclick="document.getElementById('userModal').classList.add('show')">
        <i class="bi bi-plus-circle"></i> Nouvel Utilisateur
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <?php
                        $roleClass = match($u['role']) {
                            'admin' => 'danger',
                            'manager' => 'warning',
                            default => 'primary'
                        };
                        ?>
                        <span class="badge badge-<?php echo $roleClass; ?>"><?php echo ucfirst($u['role']); ?></span>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $u['active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $u['active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </td>
                    <td><?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-'; ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>', '<?php echo htmlspecialchars($u['email']); ?>', '<?php echo $u['role']; ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-<?php echo $u['active'] ? 'secondary' : 'success'; ?>">
                                <i class="bi bi-<?php echo $u['active'] ? 'lock' : 'unlock'; ?>"></i>
                            </button>
                        </form>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="userModal">
    <div class="modal-dialog">
        <form method="post">
            <div class="modal-header">
                <h5 class="modal-title">Utilisateur</h5>
                <button type="button" class="btn-close" onclick="document.getElementById('userModal').classList.remove('show')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="userId">
                <div class="mb-3">
                    <label class="form-label">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" id="passwordField">
                    <small class="text-muted">Laissez vide pour conserver</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rôle</label>
                    <select name="role" class="form-select">
                        <option value="user">Utilisateur</option>
                        <option value="manager">Gestionnaire</option>
                        <option value="admin">Administrateur</option>
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
                <p>Êtes-vous sûr de vouloir supprimer <strong id="deleteNom"></strong> ?</p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, username, email, role) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('userId').value = id;
    document.querySelector('#userModal input[name="username"]').value = username;
    document.querySelector('#userModal input[name="email"]').value = email;
    document.querySelector('#userModal select[name="role"]').value = role;
    document.getElementById('passwordField').required = false;
    document.getElementById('userModal').classList.add('show');
}

function confirmDelete(id, nom) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteModal').classList.add('show');
}

document.getElementById('userModal').addEventListener('show.bs.modal', function(event) {
    if (!event.relatedTarget) {
        document.getElementById('modalAction').value = 'add';
        document.getElementById('userId').value = '';
        document.querySelectorAll('#userModal .form-control').forEach(i => i.value = '');
        document.getElementById('passwordField').required = true;
    }
});
</script>