<?php
require_once 'includes/config.php';
if(isLoggedIn()) redirect(BASE_URL.'index.php');

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $login = trim($_POST['login']??'');
    $pass  = $_POST['password']??'';
    if($login && $pass){
        $stmt = $pdo->prepare("SELECT u.*,r.code as role_code,r.nom as role_nom,r.couleur as role_color FROM users u JOIN roles r ON u.role_id=r.id WHERE (u.username=? OR u.email=?) AND u.actif=1");
        $stmt->execute([$login,$login]);
        $user = $stmt->fetch();
        if($user && password_verify($pass,$user['password'])){
            // Charger permissions
            $perms = $pdo->prepare("SELECT p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?");
            $perms->execute([$user['role_id']]);
            $permsList = array_column($perms->fetchAll(),'code');

            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user']        = $user;
            $_SESSION['role_code']   = $user['role_code'];
            $_SESSION['role_nom']    = $user['role_nom'];
            $_SESSION['role_color']  = $user['role_color'];
            $_SESSION['permissions'] = $permsList;

            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            logAction($pdo,'login','auth','Connexion réussie');
            redirect(BASE_URL.'index.php');
        } else { $error = 'Identifiants incorrects.'; }
    } else { $error = 'Veuillez remplir tous les champs.'; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;
     background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#1d4ed8 100%);}
.login-wrap{background:#fff;border-radius:20px;width:100%;max-width:420px;box-shadow:0 25px 60px rgba(0,0,0,.35);overflow:hidden;}
.login-top{background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:36px 32px 28px;text-align:center;color:#fff;}
.login-top .icon{width:60px;height:60px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:26px;}
.login-top h1{font-size:22px;font-weight:800;margin-bottom:4px;}
.login-top p{font-size:12px;opacity:.8;}
.login-form{padding:28px 32px 32px;}
.demo-accounts{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:11px;color:#64748b;}
.demo-row{display:flex;justify-content:space-between;margin-bottom:3px;}
.demo-row:last-child{margin-bottom:0;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-top">
    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
    <h1><?= APP_NAME ?></h1>
    <p>Système de Gestion d'Établissement Technique</p>
  </div>
  <div class="login-form">
    <?php if($error): ?>
    <div class="flash-alert flash-danger" style="margin-bottom:16px;border-radius:8px;">
      <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <div class="demo-accounts">
      <strong style="color:#374151;display:block;margin-bottom:5px;">Comptes démo :</strong>
      <div class="demo-row"><span>👑 Super Admin</span><span><code>superadmin</code> / <code>password</code></span></div>
      <div class="demo-row"><span>🏫 Directeur</span><span><code>directeur</code> / <code>password</code></span></div>
      <div class="demo-row"><span>📚 Enseignant</span><span><code>prof1</code> / <code>password</code></span></div>
      <div class="demo-row"><span>📋 Secrétaire</span><span><code>secretaire</code> / <code>password</code></span></div>
    </div>

    <form method="POST">
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label">Identifiant <span class="form-required">*</span></label>
        <input type="text" name="login" class="form-control" placeholder="Username ou e-mail"
               value="<?= sanitize($_POST['login']??'') ?>" autofocus required>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Mot de passe <span class="form-required">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <i class="fas fa-sign-in-alt"></i> Se connecter
      </button>
    </form>
  </div>
</div>
</body>
</html>
