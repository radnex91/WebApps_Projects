<?php
/**
 * BrenFinance Suite — Script de configuration initiale
 * Exécutez ce script UNE SEULE FOIS après l'import du schéma SQL
 * Puis supprimez-le impérativement !
 *
 * URL : http://localhost/brenfinance/setup.php
 */

$step = $_POST['step'] ?? $_GET['step'] ?? 'config';
$message = '';
$messageType = '';

// ── Étape 1 : test connexion + création admin ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {
    $host   = trim($_POST['db_host'] ?? 'localhost');
    $name   = trim($_POST['db_name'] ?? 'brenfinance');
    $user   = trim($_POST['db_user'] ?? 'root');
    $pass   = $_POST['db_pass'] ?? '';
    $email  = trim($_POST['admin_email'] ?? 'admin@brenfinance.cm');
    $pwd    = $_POST['admin_password'] ?? '';
    $pwd2   = $_POST['admin_password2'] ?? '';

    $errors = [];
    if (!$email)         $errors[] = 'Email administrateur requis.';
    if (strlen($pwd) < 6) $errors[] = 'Mot de passe minimum 6 caractères.';
    if ($pwd !== $pwd2)  $errors[] = 'Les deux mots de passe ne correspondent pas.';

    if (empty($errors)) {
        // Test connexion
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Générer le hash bcrypt SUR CE SERVEUR (garantit la compatibilité PHP)
            $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);

            // Vérifier si l'utilisateur admin existe déjà
            $existing = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $existing->execute([$email]);
            $existingUser = $existing->fetch();

            if ($existingUser) {
                // Mettre à jour le mot de passe existant
                $pdo->prepare("UPDATE utilisateurs SET password_hash = ?, statut = 'actif' WHERE email = ?")
                    ->execute([$hash, $email]);
                $action = 'mis à jour';
            } else {
                // Créer un nouvel admin
                $pdo->prepare("INSERT INTO utilisateurs (agence_id, service_id, role_id, nom, prenom, matricule, email, password_hash, statut) VALUES (1, 1, 1, 'Administrateur', 'Système', 'ADM001', ?, ?, 'actif')")
                    ->execute([$email, $hash]);
                $action = 'créé';
            }

            // Mettre à jour config/database.php
            $configContent = "<?php\ndefine('DB_HOST', " . var_export($host, true) . ");\ndefine('DB_NAME', " . var_export($name, true) . ");\ndefine('DB_USER', " . var_export($user, true) . ");\ndefine('DB_PASS', " . var_export($pass, true) . ");\ndefine('DB_CHARSET', 'utf8mb4');\n\nfunction getDB(): PDO {\n    static \$pdo = null;\n    if (\$pdo === null) {\n        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;\n        \$options = [\n            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n            PDO::ATTR_EMULATE_PREPARES   => false,\n        ];\n        try {\n            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);\n        } catch (PDOException \$e) {\n            die(json_encode(['error' => 'Connexion base de données impossible: ' . \$e->getMessage()]));\n        }\n    }\n    return \$pdo;\n}\n";

            file_put_contents(__DIR__ . '/config/database.php', $configContent);

            $message = "<i class='fa-solid fa-circle-check'></i> Installation réussie ! Compte administrateur $action avec l'email <strong>$email</strong>. <br>Vous pouvez maintenant <a href='index.php' style='color:#0ea87e;font-weight:700'>vous connecter</a>.<br><br><strong style='color:#d63547'><i class='fa-solid fa-triangle-exclamation'></i> Supprimez le fichier setup.php maintenant !</strong>";
            $messageType = 'success';

        } catch (PDOException $e) {
            $errors[] = 'Connexion MySQL impossible : ' . $e->getMessage();
        }
    }
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

// ── Étape 2 : changement de mot de passe uniquement ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset_pwd') {
    $email = trim($_POST['email'] ?? '');
    $pwd   = $_POST['new_password'] ?? '';
    $pwd2  = $_POST['new_password2'] ?? '';

    $errors = [];
    if (!$email)          $errors[] = 'Email requis.';
    if (strlen($pwd) < 6) $errors[] = 'Mot de passe minimum 6 caractères.';
    if ($pwd !== $pwd2)   $errors[] = 'Les deux mots de passe ne correspondent pas.';

    if (empty($errors)) {
        try {
            require_once __DIR__ . '/config/database.php';
            $db = getDB();
            $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE utilisateurs SET password_hash = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            if ($stmt->rowCount() > 0) {
                $message = "<i class='fa-solid fa-circle-check'></i> Mot de passe mis à jour pour <strong>$email</strong>. <a href='index.php' style='color:#0ea87e;font-weight:700'>Se connecter →</a><br><br><strong style='color:#d63547'><i class='fa-solid fa-triangle-exclamation'></i> Supprimez setup.php immédiatement !</strong>";
                $messageType = 'success';
            } else {
                $message = "Aucun utilisateur trouvé avec cet email.";
                $messageType = 'warning';
            }
        } catch (Exception $e) {
            $message = 'Erreur : ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BrenFinance — Configuration initiale</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#0f3060,#1a4f8a 60%,#0ea87e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:12px;width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.25);overflow:hidden}
.top{background:#0f3060;padding:28px 32px;text-align:center}
.logo{font-size:36px;color:#0ea87e;font-weight:900;margin-bottom:6px}
h1{color:#fff;font-size:18px;margin-bottom:4px}
.sub{color:#7a92b5;font-size:13px}
.body{padding:28px 32px}
.tabs{display:flex;border-bottom:2px solid #eee;margin-bottom:24px}
.tab{flex:1;padding:10px;text-align:center;font-size:13px;font-weight:600;color:#8892a4;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px}
.tab.active{color:#1a4f8a;border-bottom-color:#1a4f8a}
.panel{display:none}.panel.active{display:block}
.form-group{margin-bottom:14px}
label{display:block;font-size:12.5px;font-weight:600;color:#4b5671;margin-bottom:4px}
input{width:100%;padding:9px 12px;border:1px solid #dde2ea;border-radius:6px;font-size:13.5px;outline:none;transition:border-color .15s}
input:focus{border-color:#1a4f8a;box-shadow:0 0 0 3px rgba(26,79,138,.1)}
.btn{width:100%;padding:11px;background:#1a4f8a;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s;margin-top:6px}
.btn:hover{background:#2563b0}
.btn-green{background:#0ea87e}.btn-green:hover{background:#12c98f}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13.5px;line-height:1.5}
.alert-success{background:#e8f9f1;border:1px solid #b3e8cf;color:#0c6b3a}
.alert-danger{background:#fdeaec;border:1px solid #f5b8be;color:#8c1422}
.alert-warning{background:#fef8ec;border:1px solid #fad99a;color:#7a4a00}
.alert-info{background:#e7f3ff;border:1px solid #b3d4f8;color:#0c3d7a}
.divider{text-align:center;color:#8892a4;font-size:12px;margin:14px 0;position:relative}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:#eee}
.divider::before{left:0}.divider::after{right:0}
small{font-size:11.5px;color:#8892a4;display:block;margin-top:4px}
.warning-box{background:#fff8e7;border:1px solid #f5d77a;border-radius:8px;padding:12px 14px;font-size:12.5px;color:#7a4a00;margin-bottom:18px}
.warning-box strong{display:block;margin-bottom:4px}
</style>
</head>
<body>
<div class="card">
  <div class="top">
    <div class="logo">₣</div>
    <h1>BrenFinance Suite</h1>
    <div class="sub">Configuration &amp; Initialisation</div>
  </div>
  <div class="body">

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="warning-box">
      <strong><i class="fa-solid fa-triangle-exclamation"></i> Sécurité</strong>
      Ce fichier doit être supprimé après utilisation. Ne laissez jamais setup.php accessible en production.
    </div>

    <div class="tabs">
      <button class="tab <?= $step!=='reset_pwd'?'active':'' ?>" onclick="showTab('install')">Installation complète</button>
      <button class="tab <?= $step==='reset_pwd'?'active':'' ?>" onclick="showTab('reset')">Réinitialiser le mot de passe</button>
    </div>

    <!-- Panel: Installation complète -->
    <div class="panel <?= $step!=='reset_pwd'?'active':'' ?>" id="panel-install">
      <form method="post">
        <input type="hidden" name="step" value="install">

        <div style="font-size:13px;color:#4b5671;margin-bottom:16px;font-weight:600">Connexion à la base de données</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group">
            <label>Hôte MySQL</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host']??'localhost') ?>">
          </div>
          <div class="form-group">
            <label>Nom de la base</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name']??'brenfinance') ?>">
          </div>
          <div class="form-group">
            <label>Utilisateur MySQL</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user']??'root') ?>">
          </div>
          <div class="form-group">
            <label>Mot de passe MySQL</label>
            <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass']??'') ?>">
          </div>
        </div>

        <div class="divider">Compte administrateur</div>

        <div class="form-group">
          <label>Email administrateur</label>
          <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email']??'admin@brenfinance.cm') ?>">
        </div>
        <div class="form-group">
          <label>Mot de passe</label>
          <input type="password" name="admin_password" placeholder="Minimum 6 caractères">
        </div>
        <div class="form-group">
          <label>Confirmer le mot de passe</label>
          <input type="password" name="admin_password2" placeholder="Répétez le mot de passe">
        </div>
        <button type="submit" class="btn">Installer BrenFinance Suite</button>
      </form>
    </div>

    <!-- Panel: Réinitialisation mot de passe -->
    <div class="panel <?= $step==='reset_pwd'?'active':'' ?>" id="panel-reset">
      <p style="font-size:13px;color:#4b5671;margin-bottom:16px">Utilisez ce formulaire si vous avez oublié votre mot de passe ou si la connexion échoue.</p>
      <form method="post">
        <input type="hidden" name="step" value="reset_pwd">
        <div class="form-group">
          <label>Email du compte à modifier</label>
          <input type="email" name="email" value="admin@brenfinance.cm">
        </div>
        <div class="form-group">
          <label>Nouveau mot de passe</label>
          <input type="password" name="new_password" placeholder="Minimum 6 caractères">
        </div>
        <div class="form-group">
          <label>Confirmer le nouveau mot de passe</label>
          <input type="password" name="new_password2">
        </div>
        <button type="submit" class="btn btn-green">Réinitialiser le mot de passe</button>
      </form>
    </div>

  </div>
</div>
<script>
function showTab(t) {
  document.querySelectorAll('.tab').forEach((el,i)=>el.classList.toggle('active',i===(t==='install'?0:1)));
  document.getElementById('panel-install').classList.toggle('active', t==='install');
  document.getElementById('panel-reset').classList.toggle('active', t==='reset');
}
</script>
</body>
</html>
