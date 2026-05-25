<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-people"></i> Utilisateurs</h1>
    </div>
    <div class="page-header-right">
        <button class="btn-material btn-material-primary" onclick="openAddUser()">
            <i class="bi bi-plus"></i> Nouveau
        </button>
    </div>
</div>

<?php if (empty($users)): ?>
<div class="empty-state" style="margin-top:20px">
    <i class="bi bi-people" style="font-size:48px;color:#9e9e9e"></i>
    <p>Aucun utilisateur trouvé</p>
</div>
<?php else: ?>
<div class="table-container" style="margin-top:20px">
    <table class="table-material">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Rôle</th>
                <th style="width:110px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar initials"><?= strtoupper(substr($u->nom, 0, 1)) ?></div>
                        <?= htmlspecialchars($u->nom) ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($u->email) ?></td>
                <td>
                    <?php
                    $badgeClass = match($u->role) {
                        'admin' => 'badge-status badge-critical',
                        'technicien' => 'badge-status badge-medium',
                        'client' => 'badge-status badge-low',
                        default => 'badge-status',
                    };
                    ?>
                    <span class="<?= $badgeClass ?>"><?= ucfirst($u->role) ?></span>
                </td>
                <td class="actions-cell">
                    <button class="btn-material btn-material-small btn-material-primary" title="Modifier" onclick="openEditUser(<?= $u->id ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/admin/users/delete/<?= $u->id ?>" style="display:inline" onsubmit="return confirm('Confirmer la suppression de cet utilisateur ?')">
                        <button class="btn-material btn-material-small btn-material-danger" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal-backdrop" id="addUserModal" style="display:none" onclick="if(event.target===this)closeAddUser()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i class="bi bi-person-plus"></i> Nouvel utilisateur</h3>
            <button class="modal-close" onclick="closeAddUser()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users/add" class="form-material">
            <div class="modal-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="add_name">Nom</label>
                    <input type="text" name="name" id="add_name" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_email">Email</label>
                    <input type="email" name="email" id="add_email" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_password">Mot de passe</label>
                    <input type="password" name="password" id="add_password" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_role">Rôle</label>
                    <select name="role" id="add_role" class="form-input-material" required>
                        <option value="client">Client</option>
                        <option value="technicien">Technicien</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeAddUser()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="editUserModal" style="display:none" onclick="if(event.target===this)closeEditUser()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i class="bi bi-pencil"></i> Modifier utilisateur</h3>
            <button class="modal-close" onclick="closeEditUser()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users/edit/" id="editUserForm" class="form-material">
            <div class="modal-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_name">Nom</label>
                    <input type="text" name="name" id="edit_name" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_email">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_password">Nouveau mot de passe (laisser vide pour conserver)</label>
                    <input type="password" name="password" id="edit_password" class="form-input-material">
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_role">Rôle</label>
                    <select name="role" id="edit_role" class="form-input-material" required>
                        <option value="client">Client</option>
                        <option value="technicien">Technicien</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeEditUser()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
const usersData = <?= json_encode($users) ?>;

function openAddUser() {
    document.getElementById('add_name').value = '';
    document.getElementById('add_email').value = '';
    document.getElementById('add_password').value = '';
    document.getElementById('add_role').value = 'client';
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUser() {
    document.getElementById('addUserModal').style.display = 'none';
}

function openEditUser(id) {
    const u = usersData.find(user => user.id === id);
    if (!u) return;
    document.getElementById('editUserForm').action = '<?= BASE_URL ?>/admin/users/edit/' + id;
    document.getElementById('edit_name').value = u.nom;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = u.role;
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUser() {
    document.getElementById('editUserModal').style.display = 'none';
}
</script>
