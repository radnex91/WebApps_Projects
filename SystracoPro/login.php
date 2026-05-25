<?php
require_once 'includes/config.php';
if(isLoggedIn()) redirect(BASE_URL.'index.php');

$error='';
$reset_step = isset($_SESSION['reset_user_id']) ? 2 : (isset($_GET['reset']) ? 1 : 0);
$reset_error = '';
$reset_success = '';
$reset_login = '';

if(isset($_GET['cancel'])){ unset($_SESSION['reset_user_id'],$_SESSION['reset_user'],$_SESSION['reset_token']); }

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_request'])){
    $login = trim($_POST['login'] ?? '');
    if($login){
        $stmt = $pdo->prepare("SELECT id, username, email, nom, prenom FROM utilisateurs WHERE (username=? OR email=?) AND actif=1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if($user){
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_user'] = ['username' => $user['username'], 'nom' => $user['nom'], 'prenom' => $user['prenom']];
            $_SESSION['reset_token'] = bin2hex(random_bytes(32));
            $reset_step = 2;
            $reset_login = $user['username'];
        } else {
            $reset_error = 'Aucun compte trouvé avec cet identifiant.';
        }
    }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])){
    $uid = $_SESSION['reset_user_id'] ?? 0;
    $new_pass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if(!$uid || !isset($_SESSION['reset_token'])){
        $reset_error = 'Session expirée.';
    } elseif(strlen($new_pass) < 6){
        $reset_error = 'Mot de passe trop court (min 4 caractères).';
    } elseif($new_pass !== $confirm){
        $reset_error = 'Les mots de passe ne correspondent pas.';
    } else {
        $pdo->prepare("UPDATE utilisateurs SET password=? WHERE id=?")->execute([password_hash($new_pass, PASSWORD_DEFAULT), $uid]);
        $reset_success = 'Mot de passe modifié avec succès!';
        unset($_SESSION['reset_user_id'], $_SESSION['reset_user'], $_SESSION['reset_token']);
        $reset_step = 0;
    }
}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['reset_request']) && !isset($_POST['change_password'])){
    $login=trim($_POST['login']??''); $pass=$_POST['password']??'';
    if($login&&$pass){
        $stmt=$pdo->prepare("SELECT u.*,r.code as role_code,r.nom as role_nom,r.couleur as role_color,r.niveau FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE (u.username=? OR u.email=?) AND u.actif=1");
        $stmt->execute([$login,$login]);
        $user=$stmt->fetch();
        if($user&&password_verify($pass,$user['password'])){
            $perms=$pdo->prepare("SELECT p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?");
            $perms->execute([$user['role_id']]); $permsList=array_column($perms->fetchAll(),'code');
            $_SESSION['user_id']=$user['id'];
            $_SESSION['user']=$user;
            $_SESSION['role_code']=$user['role_code'];
            $_SESSION['role_nom']=$user['role_nom'];
            $_SESSION['role_color']=$user['role_color'];
            $_SESSION['permissions']=$permsList;
            $pdo->prepare("UPDATE utilisateurs SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            logAction($pdo,'login','auth','Connexion réussie');
            redirect(BASE_URL.'index.php');
        } else { $error='Identifiants incorrects ou compte inactif.'; }
    } else { $error='Remplissez tous les champs.'; }
}
$appName=getParam('nom_entreprise',APP_NAME);
$slogan=getParam('slogan','Votre partenaire de mobilité');
$csrf = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — <?= sanitize($appName) ?></title>
<link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(20,20,20,.92) 0%,rgba(30,30,30,.95) 50%,rgba(15,15,15,.98) 100%),url('https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');background-size:cover;background-position:center;background-attachment:fixed;}
body::before{content:'';position:fixed;inset:0;background:linear-gradient(135deg,rgba(184,134,11,.08) 0%,transparent 50%,rgba(218,165,32,.05) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;background:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><defs><linearGradient id=%22g%22 x1=%220%25%22 y1=%220%25%22 x2=%22100%25%22 y2=%22100%25%22><stop offset=%220%25%22 stop-color=%22%23daa520%22 stop-opacity=%220.03%22/><stop offset=%22100%25%22 stop-color=%22%23b8860b%22 stop-opacity=%220.06%22/></linearGradient></defs><rect fill=%22url(%23g)%22 width=%22100%25%22 height=%22100%25%22/></svg>');pointer-events:none;}
.login-wrap{background:linear-gradient(145deg,#1a1a1a,#0f0f0f);border:1px solid rgba(218,165,32,.2);border-radius:24px;width:100%;max-width:440px;box-shadow:0 30px 70px rgba(0,0,0,.6),0 0 60px rgba(218,165,32,.08),inset 0 1px 0 rgba(255,255,255,.05);overflow:hidden;position:relative;}
.login-wrap::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,rgba(218,165,32,.8),transparent);}
.login-top{background:linear-gradient(135deg,#b8860b,#daa520);padding:38px 32px 32px;text-align:center;position:relative;overflow:hidden;}
.login-top::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:conic-gradient(from 0deg,transparent,rgba(255,255,255,.1),transparent);animation:shine 4s linear infinite;}
@keyframes shine{to{transform:rotate(360deg);}}
.login-top .ic{width:70px;height:70px;background:rgba(0,0,0,.25);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:32px;border:2px solid rgba(255,255,255,.2);box-shadow:0 8px 32px rgba(0,0,0,.3);}
.login-top h1{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:#fff;margin-bottom:4px;text-shadow:0 2px 4px rgba(0,0,0,.2);}
.login-top p{font-size:12px;opacity:.9;color:#fff;}
.login-form{padding:28px 32px 32px;}
.login-form .fg{margin-bottom:16px;}
.login-form .flbl{color:#d4af37;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}
.input-group{position:relative;}
.input-group .fc{background:#0a0a0a;border:1px solid #2a2a2a;border-radius:10px;padding:14px 48px 14px 16px;font-size:14px;color:#fff;width:100%;transition:all .2s;}
.input-group .fc:focus{outline:none;border-color:#d4af37;box-shadow:0 0 0 3px rgba(212,175,55,.15);}
.input-group .fc::placeholder{color:#555;}
.input-group .toggle-pass{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#666;cursor:pointer;font-size:14px;padding:4px;}
.input-group .toggle-pass:hover{color:#daa520;}
.btn-gold{background:linear-gradient(135deg,#daa520,#b8860b);color:#000;border:none;border-radius:10px;padding:16px;font-size:14px;font-weight:600;width:100%;cursor:pointer;transition:all .2s;box-shadow:0 4px 15px rgba(218,165,32,.3);}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 6px 25px rgba(218,165,32,.4);}
.btn-gold:active{transform:translateY(0);}
.btn-outline{background:transparent;border:1px solid #daa520;color:#daa520;border-radius:10px;padding:14px;font-size:14px;font-weight:600;width:100%;cursor:pointer;transition:all .2s;}
.btn-outline:hover{background:rgba(218,165,32,.1);}
.flash-danger{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#fca5a5;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;}
.flash-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;}
.bottom-links{text-align:center;padding-top:16px;border-top:1px solid #222;}
.bottom-links a, .bottom-links button{background:none;border:none;color:#666;text-decoration:none;font-size:12px;transition:color .2s;cursor:pointer;}
.bottom-links a:hover, .bottom-links button:hover{color:#daa520;}
.reset-mode{text-align:center;}
.reset-mode h2{color:#daa520;font-size:18px;margin-bottom:16px;}
.reset-mode .user-info{background:#0d0d0d;border:1px solid #222;border-radius:10px;padding:14px;margin-bottom:16px;text-align:left;}
.reset-mode .user-info .label{color:#666;font-size:11px;}
.reset-mode .user-info .value{color:#fff;font-size:14px;font-weight:500;}
</style>
<?php if(isset($_GET['_iframe'])): ?>
<script>
if(window.parent !== window){
    window.parent.location.href = '<?= BASE_URL ?>login.php?_iframe=1';
}
</script>
<?php endif; ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-top">
    <div class="ic"><i class="fas fa-bus-alt"></i></div>
    <h1><?= sanitize($appName) ?></h1>
    <p><?= sanitize($slogan) ?></p>
  </div>
  <div class="login-form">
    <?php if($reset_success): ?>
    <div class="flash-success"><i class="fas fa-check-circle"></i> <?= sanitize($reset_success) ?></div>
    <?php endif; ?>
    
    <?php if($reset_step == 2): ?>
    <div class="reset-mode">
      <h2><i class="fas fa-key"></i> Nouveau mot de passe</h2>
      <?php if($reset_error): ?><div class="flash-danger"><?= sanitize($reset_error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="change_password" value="1">
        <div class="fg">
          <label class="flbl">Nouveau mot de passe</label>
          <div class="input-group">
            <input type="password" name="new_password" id="new_pass" class="fc" placeholder="Nouveau mot de passe" required minlength="6">
            <button type="button" class="toggle-pass" onclick="togglePassword('new_pass','new_eye')"><i class="fas fa-eye" id="new_eye"></i></button>
          </div>
        </div>
        <div class="fg" style="margin-bottom:20px;">
          <label class="flbl">Confirmer</label>
          <div class="input-group">
            <input type="password" name="confirm_password" id="conf_pass" class="fc" placeholder="Confirmer" required>
            <button type="button" class="toggle-pass" onclick="togglePassword('conf_pass','conf_eye')"><i class="fas fa-eye" id="conf_eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-gold"><i class="fas fa-save"></i> Enregistrer</button>
        <div class="bottom-links" style="margin-top:12px;">
          <a href="login.php"><i class="fas fa-arrow-left"></i> Retour à la connexion</a>
        </div>
      </form>
    </div>
    <?php elseif($reset_step == 1): ?>
    <div class="reset-mode">
      <h2><i class="fas fa-key"></i> Réinitialiser le mot de passe</h2>
      <?php if($reset_error): ?><div class="flash-danger"><?= sanitize($reset_error) ?></div><?php endif; ?>
      <div class="user-info">
        <div><span class="label">Compte trouvé:</span></div>
        <div class="value"><i class="fas fa-user"></i> <?= sanitize($reset_login) ?></div>
      </div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="reset_request" value="1">
        <input type="hidden" name="login" value="<?= sanitize($reset_login) ?>">
        <button type="submit" class="btn-gold"><i class="fas fa-arrow-right"></i> Continuer</button>
        <div class="bottom-links" style="margin-top:12px;">
          <a href="login.php"><i class="fas fa-arrow-left"></i> Annuler</a>
        </div>
      </form>
    </div>
    <?php else: ?>
    <?php if($error): ?>
    <div class="flash-danger"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div class="fg">
        <label class="flbl">Identifiant</label>
        <div class="input-group">
          <input type="text" name="login" class="fc" placeholder="Nom d'utilisateur" value="<?= sanitize($_POST['login']??'') ?>" autofocus required>
        </div>
      </div>
      <div class="fg" style="margin-bottom:20px;">
        <label class="flbl">Mot de passe</label>
        <div class="input-group">
          <input type="password" name="password" id="password" class="fc" placeholder="••••••••" required>
          <button type="button" class="toggle-pass" onclick="togglePassword('password','eye-icon')"><i class="fas fa-eye" id="eye-icon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-gold"><i class="fas fa-sign-in-alt"></i> Se connecter</button>
    </form>
    <div class="bottom-links">
      <a href="?reset=1"><i class="fas fa-lock"></i> Mot de passe oublié ?</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function togglePassword(inputId, iconId){
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if(input.type === 'password'){
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}
</script>
</body>
</html>