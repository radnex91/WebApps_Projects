<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login'] ?? '');
    $password   = $_POST['password'] ?? '';
    if (login($loginInput, $password)) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Identifiant ou mot de passe incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — <?= APP_TITLE ?></title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-bg"></div>
  <div class="login-card">
    <div class="login-logo">
      <div class="login-logo-icon">SM</div>
      <div class="login-logo-text">
        <strong>STOCK MTW</strong>
        <span>Projet <?= NOM_PROJET ?></span>
      </div>
    </div>

    <h2>Connexion</h2>
    <p>Accès réservé au personnel autorisé</p>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Identifiant <span class="required">*</span></label>
        <input type="text" name="login" class="form-control" placeholder="Votre identifiant" 
               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Mot de passe <span class="required">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
        Se connecter
      </button>
    </form>

    <p style="text-align:center; margin-top:20px; font-size:12px; color:var(--text3);">
      <?= APP_NAME ?> v<?= APP_VERSION ?> — Gestion de Stock Matériaux
    </p>
  </div>
</div>
<div id="toast-container"></div>
<script src="/js/app.js" defer></script>
</body>
</html>
