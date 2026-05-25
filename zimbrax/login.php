<?php
// login.php
require_once 'includes/config.php';
if (isLoggedIn()) redirect(BASE_URL . 'index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($login && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND active=1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            // Store only safe fields in session
            unset($user['password'], $user['mail_password']);
            $_SESSION['user'] = $user;
            // Update last login
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            redirect(BASE_URL . 'index.php');
        } else {
            $error = 'Identifiants incorrects.';
        }
    } else {
        $error = 'Remplissez tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — ZimbraX</title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo"><div class="logo-pulse"></div>ZimbraX</div>
      <p>Votre espace de communication intégré</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">⚠ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Nom d'utilisateur ou e-mail</label>
        <input type="text" name="login" class="form-control" placeholder="admin ou admin@zimbrax.cm"
               value="<?= sanitize($_POST['login'] ?? '') ?>" autofocus required>
      </div>
      <div class="form-group">
        <label>Mot de passe</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);cursor:pointer;">
          <input type="checkbox" name="remember"> Se souvenir de moi
        </label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
        Se connecter →
      </button>
    </form>

    <div class="login-footer">
      Compte démo : <strong style="color:var(--text2)">admin</strong> / <strong style="color:var(--text2)">password</strong>
    </div>
  </div>
</div>
</body>
</html>
