<?php
$pageTitle = 'Paramètres — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$user = currentUser();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $oldPw  = $_POST['old_password'] ?? '';
        $newPw  = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $current = queryOne("SELECT password FROM utilisateurs WHERE id=?", [$user['id']]);
        if (!password_verify($oldPw, $current['password'])) {
            $error = 'Ancien mot de passe incorrect.';
        } elseif ($newPw !== $confirm) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($newPw) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            execute("UPDATE utilisateurs SET password=? WHERE id=?", [password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Mot de passe modifié avec succès.';
        }
    }
}
?>
<div class="page-header"><h1>Paramètres</h1><p>Informations de compte et configuration</p></div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; max-width:800px;">
  <div class="card">
    <div class="card-header"><span class="card-title">Informations compte</span></div>
    <div class="card-body">
      <div class="form-group"><label class="form-label">Nom</label>
        <div class="form-control" style="background:var(--bg3);"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></div>
      </div>
      <div class="form-group"><label class="form-label">Login</label>
        <div class="form-control" style="background:var(--bg3); font-family:var(--font-mono);"><?= htmlspecialchars($user['login']) ?></div>
      </div>
      <div class="form-group"><label class="form-label">Rôle</label>
        <div class="form-control" style="background:var(--bg3);"><?= ROLES[$user['role']] ?? $user['role'] ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Changer le mot de passe</span></div>
    <div class="card-body">
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group"><label class="form-label">Ancien mot de passe</label>
          <input type="password" name="old_password" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Nouveau mot de passe</label>
          <input type="password" name="new_password" class="form-control" required minlength="6"></div>
        <div class="form-group"><label class="form-label">Confirmer</label>
          <input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn btn-primary">Modifier</button>
      </form>
    </div>
  </div>
</div>

<div class="card" style="margin-top:20px; max-width:800px;">
  <div class="card-header"><span class="card-title">À propos</span></div>
  <div class="card-body">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:13px; color:var(--text2);">
      <div><strong>Application :</strong> <?= APP_NAME ?> v<?= APP_VERSION ?></div>
      <div><strong>Projet :</strong> <?= NOM_PROJET ?></div>
      <div><strong>Code projet :</strong> <?= CODE_PROJET ?></div>
      <div><strong>Base de données :</strong> <?= DB_NAME ?></div>
      <div><strong>Date du serveur :</strong> <?= date('d/m/Y H:i') ?></div>
      <div><strong>PHP :</strong> <?= PHP_VERSION ?></div>
    </div>
    <hr class="divider">
    <p style="font-size:12px; color:var(--text3);">
      Cette application gère le stock de matériaux du projet de construction MOUTOURWA - MAROUA. 
      Elle implémente la méthode CMUPACE (Coût Moyen Pondéré Actualisé à chaque Entrée) pour la valorisation des stocks.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
