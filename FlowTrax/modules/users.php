<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('users.delete');
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash']['success'] = 'Utilisateur supprimé avec succès.';
        redirect('index.php?page=users');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'users.create' : 'users.edit');

        $user = ['username'=>'', 'email'=>'', 'nom'=>'', 'prenom'=>'', 'telephone'=>'', 'role_id'=>'', 'active'=>1];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) { $_SESSION['flash']['error'] = 'Utilisateur introuvable.'; redirect('index.php?page=users'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'username' => $_POST['username'],
                'email' => $_POST['email'],
                'nom' => $_POST['nom'],
                'prenom' => $_POST['prenom'],
                'telephone' => $_POST['telephone'],
                'role_id' => $_POST['role_id'] ?: null,
                'active' => isset($_POST['active']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, nom, prenom, telephone, role_id, active) VALUES (:username, :email, :password, :nom, :prenom, :telephone, :role_id, :active)");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Utilisateur créé avec succès.';
            } else {
                if (!empty($_POST['password'])) {
                    $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username=:username, email=:email, password=:password, nom=:nom, prenom=:prenom, telephone=:telephone, role_id=:role_id, active=:active WHERE id=$id");
                } else {
                    $stmt = $db->prepare("UPDATE users SET username=:username, email=:email, nom=:nom, prenom=:prenom, telephone=:telephone, role_id=:role_id, active=:active WHERE id=$id");
                }
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Utilisateur mis à jour avec succès.';
            }
            redirect('index.php?page=users');
        }

        $roles = $db->query("SELECT * FROM roles ORDER BY nom")->fetchAll();
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '👤 Nouvel utilisateur' : '✏️ Modifier l\'utilisateur' ?></h3>
                <a href="index.php?page=users" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>👤 Nom d'utilisateur *</label>
                        <input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>📧 Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= e($user['nom']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>📞 Téléphone</label>
                        <input type="text" name="telephone" class="form-control" value="<?= e($user['telephone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>🔐 Rôle</label>
                        <select name="role_id" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $user['role_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= $action === 'create' ? '🔑 Mot de passe *' : '🔑 Nouveau mot de passe (laisser vide pour ne pas changer)' ?></label>
                        <input type="password" name="password" class="form-control" <?= $action === 'create' ? 'required' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;margin-top:24px;">
                            <input type="checkbox" name="active" value="1" <?= $user['active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary);">
                            <span style="font-weight:600;">✅ Compte actif</span>
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="index.php?page=users" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default: // list
        requirePermission('users.view');
        $users = $db->query("
            SELECT u.*, r.nom as role_nom
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher un utilisateur...">
            </div>
            <?php if (hasPermission('users.create')): ?>
            <a href="index.php?page=users&action=create" class="btn btn-primary">➕ Nouvel utilisateur</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>
        <?php if ($error = flash('error')): ?><div class="alert alert-danger">❌ <?= e($error) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">👥 Liste des utilisateurs</h3>
                <span class="badge badge-info"><?= count($users) ?> utilisateur(s)</span>
            </div>
            <div class="table-container">
                <table>
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
                        <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400);">Aucun utilisateur. 🧑‍💻</td></tr>
                        <?php else: foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['prenom']) ?> <?= e($u['nom']) ?></strong><br>
                                <small style="color:var(--gray-400);">@<?= e($u['username']) ?></small>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge badge-primary"><?= e($u['role_nom']) ?></span></td>
                            <td><?= $u['active'] ? '<span class="badge badge-success">✅ Actif</span>' : '<span class="badge badge-secondary">❌ Inactif</span>' ?></td>
                            <td><?= $u['last_login'] ? formatDatetime($u['last_login']) : 'Jamais' ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('users.edit')): ?>
                                    <a href="index.php?page=users&action=edit&id=<?= $u['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('users.delete') && $u['id'] != $_SESSION['user_id']): ?>
                                    <a href="index.php?page=users&action=delete&id=<?= $u['id'] ?>" class="btn-icon" data-confirm="Supprimer cet utilisateur ?" title="Supprimer">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        break;
}
