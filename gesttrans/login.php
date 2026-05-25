<?php
require_once 'includes/config.php';
if(isLoggedIn()) redirect(BASE_URL.'index.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $login=trim($_POST['login']??''); $pass=$_POST['password']??'';
    if($login&&$pass){
        $s=$pdo->prepare("SELECT u.*,r.code as role_code,r.nom as role_nom,r.couleur as role_color FROM users u JOIN roles r ON u.role_id=r.id WHERE (u.username=? OR u.email=?) AND u.actif=1");
        $s->execute([$login,$login]); $u=$s->fetch();
        if($u&&password_verify($pass,$u['password'])){
            $p=$pdo->prepare("SELECT p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?"); $p->execute([$u['role_id']]);
            $_SESSION['user_id']=$u['id']; $_SESSION['user']=$u;
            $_SESSION['role_code']=$u['role_code']; $_SESSION['role_nom']=$u['role_nom']; $_SESSION['role_color']=$u['role_color'];
            $_SESSION['permissions']=array_column($p->fetchAll(),'code');
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
            logAction($pdo,'login','auth','');
            redirect(BASE_URL.'index.php');
        } else { $error='Identifiants incorrects ou compte désactivé.'; }
    } else { $error='Veuillez remplir tous les champs.'; }
}
$appName=getParam('nom_entreprise',APP_NAME);
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — <?= h($appName) ?></title>
<link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#0c1e3d 0%,#1e3a8a 55%,#1d4ed8 100%);}
.login-wrap{background:#fff;border-radius:18px;width:100%;max-width:430px;box-shadow:0 30px 60px rgba(0,0,0,.4);overflow:hidden;}
.login-top{background:linear-gradient(135deg,#0c1e3d,#1e40af);padding:32px;text-align:center;color:#fff;}
.login-top .ic{width:64px;height:64px;background:rgba(255,255,255,.12);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:28px;}
.login-form{padding:28px 32px 32px;}
.demo{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:11px;color:#64748b;}
.demo-r{display:flex;justify-content:space-between;padding:2px 0;font-size:11px;}
</style>
</head><body>
<div class="login-wrap">
  <div class="login-top">
    <div class="ic"><i class="fas fa-bus-alt"></i></div>
    <h1 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?= h($appName) ?></h1>
    <p style="font-size:11px;opacity:.75;">Système de Gestion des Transports — DANAY EXPRESS SARL</p>
    <p style="font-size:10px;opacity:.6;margin-top:4px;">GestTrans Pro v<?= APP_VER ?></p>
  </div>
  <div class="login-form">
    <?php if($error): ?><div class="flash flash-danger" style="border-radius:8px;margin-bottom:14px;"><i class="fas fa-exclamation-circle"></i><?= h($error) ?></div><?php endif; ?>
    <div class="demo">
      <strong style="display:block;margin-bottom:5px;color:#374151;">Comptes démo (mot de passe : <code>password</code>)</strong>
      <?php $demos=[['superadmin','Super Admin'],['directeur','Directeur'],['chef_gb','Chef Agence'],['comptable1','Comptable'],['operateur1','Opérateur']];
      foreach($demos as $d): ?><div class="demo-r"><span><?= $d[1] ?></span><code><?= $d[0] ?></code></div><?php endforeach; ?>
    </div>
    <form method="POST">
      <div class="fg" style="margin-bottom:14px;">
        <label class="flbl">Identifiant <span class="freq">*</span></label>
        <input type="text" name="login" class="fc" value="<?= h($_POST['login']??'') ?>" autofocus required placeholder="Username ou email">
      </div>
      <div class="fg" style="margin-bottom:20px;">
        <label class="flbl">Mot de passe <span class="freq">*</span></label>
        <input type="password" name="password" class="fc" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fas fa-sign-in-alt"></i> Se connecter</button>
    </form>
  </div>
</div>
</body></html>
