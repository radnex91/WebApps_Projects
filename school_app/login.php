<?php
// login.php
require_once 'includes/config.php';

if (isLoggedIn()) redirect(BASE_URL . 'index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE username=? AND actif=1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['role'] = $user['role'];
            redirect(BASE_URL . 'index.php');
        } else {
            $error = 'Identifiants incorrects. Vérifiez votre nom d\'utilisateur et mot de passe.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { display:flex; align-items:center; justify-content:center; min-height:100vh; background: linear-gradient(135deg,#1e3a5f 0%,#2563eb 50%,#7c3aed 100%); }
.login-box { background:#fff; border-radius:16px; padding:44px 40px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
.login-logo { text-align:center; margin-bottom:28px; }
.login-logo i { font-size:48px; color:#2563eb; }
.login-logo h1 { font-size:22px; font-weight:800; margin-top:10px; color:#1e293b; }
.login-logo p { color:#64748b; font-size:13px; }
.login-box .form-group { margin-bottom:16px; }
.login-box .form-control { padding:12px 14px; font-size:15px; }
.input-icon { position:relative; }
.input-icon i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; }
.input-icon input { padding-left:38px; }
.btn-login { width:100%; padding:13px; font-size:15px; justify-content:center; margin-top:8px; }
.login-footer { text-align:center; margin-top:20px; font-size:12px; color:#94a3b8; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <i class="fas fa-school"></i>
    <h1><?= APP_NAME ?></h1>
    <p>Gestion de l'établissement scolaire</p>
  </div>

  <?php if($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Nom d'utilisateur</label>
      <div class="input-icon">
        <i class="fas fa-user"></i>
        <input type="text" name="username" class="form-control" placeholder="Entrez votre identifiant" value="<?= sanitize($_POST['username']??'') ?>" required autofocus>
      </div>
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <div class="input-icon">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" class="form-control" placeholder="Entrez votre mot de passe" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-login">
      <i class="fas fa-sign-in-alt"></i> Se connecter
    </button>
  </form>
  <div class="login-footer">
    Compte par défaut : <strong>admin</strong> / <strong>password</strong>
  </div>
</div>
</body>
</html>
