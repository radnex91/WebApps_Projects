<?php
/**
 * Génère la requête SQL INSERT pour ajouter un utilisateur (admin ou partenaire).
 * Ouvrir ce fichier dans le navigateur, remplir le formulaire, puis copier la requête
 * et l'exécuter dans phpMyAdmin (onglet SQL).
 *
 * À protéger ou supprimer en production (contient un formulaire sans mot de passe).
 */
header('Content-Type: text/html; charset=utf-8');

function escSql($s) {
  return str_replace(["\\", "'"], ["\\\\", "''"], (string) $s);
}

$sql = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim((string) ($_POST['login'] ?? ''));
  $password = (string) ($_POST['password'] ?? '');
  $role = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'partenaire';
  $nom = trim((string) ($_POST['nom'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));

  if ($login === '') {
    $error = 'Identifiant requis.';
  } elseif ($password === '') {
    $error = 'Mot de passe requis.';
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $login_esc = escSql($login);
    $nom_esc = escSql($nom);
    $email_esc = escSql($email);
    $hash_esc = escSql($hash);

    $sql = "INSERT INTO utilisateurs (login, password_hash, nom, email, role, actif) VALUES "
         . "('$login_esc', '$hash_esc', " . ($nom_esc === '' ? "NULL" : "'$nom_esc'") . ", "
         . ($email_esc === '' ? "NULL" : "'$email_esc'") . ", '$role', 1);";
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Générer requête utilisateur</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 560px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-size: 1.25rem; margin-bottom: 1rem; }
    .form-group { margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.35rem; font-weight: 500; }
    input, select { width: 100%; padding: 0.5rem; font-size: 1rem; }
    button { padding: 0.6rem 1.2rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
    button:hover { background: #1d4ed8; }
    .error { color: #dc2626; margin-bottom: 1rem; }
    .sql-box { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 8px; margin-top: 1rem; font-family: monospace; font-size: 0.9rem; white-space: pre-wrap; word-break: break-all; }
    .hint { font-size: 0.9rem; color: #64748b; margin-top: 0.5rem; }
  </style>
</head>
<body>
  <h1>Générer une requête pour ajouter un utilisateur</h1>
  <p class="hint">Remplissez le formulaire puis copiez la requête SQL affichée et exécutez-la dans phpMyAdmin (onglet SQL).</p>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>

  <form method="post" action="">
    <div class="form-group">
      <label for="login">Identifiant (login) *</label>
      <input type="text" id="login" name="login" required autocomplete="off" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label for="password">Mot de passe *</label>
      <input type="password" id="password" name="password" required autocomplete="off">
    </div>
    <div class="form-group">
      <label for="role">Rôle</label>
      <select id="role" name="role">
        <option value="partenaire" <?php echo (isset($_POST['role']) && $_POST['role'] === 'partenaire') ? 'selected' : ''; ?>>Partenaire</option>
        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur</option>
      </select>
    </div>
    <div class="form-group">
      <label for="nom">Nom (optionnel)</label>
      <input type="text" id="nom" name="nom" autocomplete="off" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label for="email">Email (optionnel)</label>
      <input type="email" id="email" name="email" autocomplete="off" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
    </div>
    <button type="submit">Générer la requête SQL</button>
  </form>

  <?php if ($sql !== ''): ?>
    <p style="margin-top:1.5rem; font-weight:500;">Requête à copier dans phpMyAdmin :</p>
    <div class="sql-box" id="sql-box"><?php echo htmlspecialchars($sql); ?></div>
    <p class="hint">Sélectionnez la requête ci-dessus, copiez (Ctrl+C), puis dans phpMyAdmin → onglet SQL → collez et exécutez.</p>
  <?php endif; ?>
</body>
</html>
