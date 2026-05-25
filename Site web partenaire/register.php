<?php
require_once __DIR__ . '/auth.php';

$user = getCurrentUser();
if ($user) {
  if ($user['role'] === 'admin') {
    header('Location: admin.php');
  } elseif ($user['role'] === 'partenaire') {
    header('Location: partenaire.php');
  } else {
    header('Location: index.php');
  }
  exit;
}

$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim($_POST['login'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
  $nom = trim($_POST['nom'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if ($login === '' || $password === '') {
    $error = 'Identifiant et mot de passe requis.';
  } elseif (strlen($password) < 6) {
    $error = 'Le mot de passe doit contenir au moins 6 caractères.';
  } elseif ($password !== $password2) {
    $error = 'Les mots de passe ne correspondent pas.';
  } else {
    try {
      createUtilisateur([
        'login' => $login,
        'password' => $password,
        'nom' => $nom,
        'email' => $email,
        'role' => 'client',
      ]);
      $ok = true;
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription — ESADISS</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="login-page">
    <div class="login-box login-box--wide">
      <h1>Inscription (compte client)</h1>
      <p class="login-page__hint">Un compte client permet de vous identifier auprès d'ESADISS. Les <strong>tarifs partenaires</strong> sont réservés aux comptes partenaires (créés par l'administrateur).</p>
      <?php if ($error): ?>
        <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($ok): ?>
        <div class="login-ok">Compte créé. Vous pouvez vous connecter.</div>
        <a href="login.php" class="btn btn-primary login-page__btn-full">Aller à la connexion</a>
      <?php else: ?>
      <form method="post" action="register.php">
        <div class="form-group">
          <label for="login">Identifiant *</label>
          <input type="text" id="login" name="login" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="password">Mot de passe * (min. 6 caractères)</label>
          <input type="password" id="password" name="password" required autocomplete="new-password" minlength="6">
        </div>
        <div class="form-group">
          <label for="password2">Confirmer le mot de passe *</label>
          <input type="password" id="password2" name="password2" required autocomplete="new-password" minlength="6">
        </div>
        <div class="form-group">
          <label for="nom">Nom (optionnel)</label>
          <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="email">Email (optionnel)</label>
          <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Créer mon compte</button>
      </form>
      <?php endif; ?>
      <div class="login-links">
        <a href="login.php" class="login-page__link">Déjà un compte ? Connexion</a> · <a href="index.php" class="login-page__link">Catalogue</a>
      </div>
    </div>
  </div>
  <script src="theme-toggle.js"></script>
</body>
</html>