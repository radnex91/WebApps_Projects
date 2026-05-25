<?php
$pageTitle = 'Utilisateurs — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!hasRole('admin', 'gestionnaire')) { header('Location: /index.php'); exit; }
require_once __DIR__ . '/../includes/layout_top.php';

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'nom'    => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'login'  => trim($_POST['login'] ?? ''),
        'role'   => $_POST['role'] ?? 'assistant',
    ];
    if ($id) {
        execute("UPDATE utilisateurs SET nom=:nom, prenom=:prenom, login=:login, role=:role WHERE id=:id", [...$data,'id'=>$id]);
        if (!empty($_POST['password'])) {
            execute("UPDATE utilisateurs SET password=? WHERE id=?", [password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
        }
        $success = "Utilisateur mis à jour.";
    } else {
        if (empty($_POST['password'])) { $error = "Mot de passe requis."; }
        else {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            execute("INSERT INTO utilisateurs (nom, prenom, login, password, role) VALUES (:nom,:prenom,:login,:password,:role)", $data);
            $success = "Utilisateur créé.";
        }
    }
}

$utilisateurs = query("SELECT * FROM utilisateurs ORDER BY nom");
?>
<div class="page-header"><h1>Gestion des Utilisateurs</h1></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="filter-bar mb-16">
  <button onclick="App.openModal('modalUser')" class="btn btn-primary ml-auto">+ Nouvel utilisateur</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Nom</th><th>Prénom</th><th>Login</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($utilisateurs as $u): ?>
    <tr>
      <td class="bold"><?= htmlspecialchars($u['nom']) ?></td>
      <td><?= htmlspecialchars($u['prenom']) ?></td>
      <td class="mono"><?= htmlspecialchars($u['login']) ?></td>
      <td><span class="badge badge-blue"><?= ROLES[$u['role']] ?? $u['role'] ?></span></td>
      <td><span class="badge <?= $u['actif']?'badge-green':'badge-red' ?>"><?= $u['actif']?'Actif':'Inactif' ?></span></td>
      <td><button onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" class="btn btn-secondary btn-sm">Modifier</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="modalUser">
  <div class="modal">
    <div class="modal-header"><span class="modal-title" id="modalUserTitle">Nouvel utilisateur</span>
      <button class="modal-close" onclick="App.closeModal('modalUser')">×</button></div>
    <form method="POST">
      <input type="hidden" name="id" id="userId" value="">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="nom" id="userNom" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Prénom</label><input type="text" name="prenom" id="userPrenom" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Login *</label><input type="text" name="login" id="userLogin" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Rôle</label>
            <select name="role" id="userRole" class="form-control">
              <?php foreach (ROLES as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Mot de passe <span class="text-muted">(laisser vide pour ne pas changer)</span></label>
          <input type="password" name="password" class="form-control" minlength="6"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="App.closeModal('modalUser')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function editUser(u) {
  document.getElementById('modalUserTitle').textContent = 'Modifier: ' + u.nom;
  document.getElementById('userId').value    = u.id;
  document.getElementById('userNom').value   = u.nom;
  document.getElementById('userPrenom').value = u.prenom || '';
  document.getElementById('userLogin').value = u.login;
  document.getElementById('userRole').value  = u.role;
  App.openModal('modalUser');
}
</script>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
