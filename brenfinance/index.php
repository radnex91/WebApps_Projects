<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, r.nom as role_nom, r.permissions FROM utilisateurs u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.statut = 'actif'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $user['permissions'] = json_decode($user['permissions'], true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            // Update last login
            $db->prepare("UPDATE utilisateurs SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            auditLog('login', 'auth');

            // Remember me
            if (!empty($_POST['remember'])) {
                createRememberToken($user['id']);
            }

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0f3060">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  /* Extra login responsive */
  @media (max-width: 480px) {
    .login-card { border-radius: 10px; }
  }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-top">
      <div class="login-logo">₣</div>
      <div class="login-title"><?= APP_NAME ?></div>
      <div class="login-sub">Gestion Caisse · Trésorerie · Finance</div>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom:16px">
        <?= sanitize($error) ?>
        <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label class="form-label">Identifiant <span class="req">*</span></label>
          <input type="text" name="username" class="form-control" placeholder="votre identifiant" value="<?= sanitize($_POST['username']??'') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe <span class="req">*</span></label>
          <div style="position:relative">
            <input type="password" name="password" id="login-password" class="form-control" placeholder="mot de passe" required>
            <button type="button" onclick="togglePwd()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:2px 4px" id="btn-eye" aria-label="Afficher le mot de passe">&#128065;</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
          Se connecter
        </button>
        <label style="display:flex;align-items:center;gap:7px;margin-top:12px;cursor:pointer;font-size:12.5px;color:var(--text2)">
          <input type="checkbox" name="remember" value="1" style="margin:0"> Se souvenir de moi
        </label>
      </form>
      <p style="margin-top:16px;text-align:center;font-size:12px;color:var(--text3)">
        
      </p>
      <script>
      function togglePwd() {
        const inp = document.getElementById('login-password');
        const btn = document.getElementById('btn-eye');
        if (inp.type === 'password') {
          inp.type = 'text';
          btn.textContent = '\u{1F648}';
        } else {
          inp.type = 'password';
          btn.textContent = '\u{1F441}';
        }
      }
      </script>
    </div>
  </div>
</div>
</body>
</html>
