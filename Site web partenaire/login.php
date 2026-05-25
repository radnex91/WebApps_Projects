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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim($_POST['login'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($login === '' || $password === '') {
    $error = 'Identifiant et mot de passe requis.';
  } else {
    $user = login($login, $password);
    if ($user) {
      $redirect = $_GET['redirect'] ?? '';
      if ($redirect && strpos($redirect, 'admin.php') !== false && $user['role'] === 'admin') {
        header('Location: ' . $redirect);
      } elseif ($redirect && strpos($redirect, 'partenaire.php') !== false && $user['role'] === 'partenaire') {
        header('Location: ' . $redirect);
      } elseif ($user['role'] === 'admin') {
        header('Location: admin.php');
      } elseif ($user['role'] === 'partenaire') {
        header('Location: partenaire.php');
      } else {
        header('Location: index.php');
      }
      exit;
    }
    $error = 'Identifiant ou mot de passe incorrect.';
  }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — Tarifs partenaires</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="login-page">
    <div class="login-box">
      <h1>Connexion</h1>
      <p class="login-page__hint">Pas encore de compte ? <a href="register.php" class="login-page__link">Inscription (client)</a> · <a href="index.php" class="login-page__link">Catalogue</a></p>
      <?php if ($error): ?>
        <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form method="post" action="login.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
        <div class="form-group">
          <label for="login">Identifiant</label>
          <input type="text" id="login" name="login" required autofocus autocomplete="username" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary">Se connecter</button>
      </form>
    </div>
  </div>
  <script src="theme-toggle.js"></script>
</body>
</html>