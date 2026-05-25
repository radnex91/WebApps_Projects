<?php
/**
 * ATLAS PRIME LOGISTICS - Script d'installation
 * Accès: http://localhost/atlas_prime/setup.php
 * SUPPRIMER ce fichier après installation !
 */

$step    = intval($_GET['step'] ?? 1);
$message = '';
$errors  = [];

// Config DB
$dbHost = $_POST['db_host'] ?? '127.0.0.1';
$dbName = $_POST['db_name'] ?? 'atlas_prime_logistics';
$dbUser = $_POST['db_user'] ?? 'root';
$dbPass = $_POST['db_pass'] ?? '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($step === 1) {
        // Test connexion
        try {
            $pdo = new PDO("mysql:host=$dbHost;port=3306;charset=utf8mb4", $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Créer la base
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Exécuter le SQL
            $sql = file_get_contents(__DIR__ . '/database.sql');
            // Découper les instructions
            $statements = array_filter(array_map('trim', explode(';', preg_replace('/--.*$/m','', $sql))));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    try { $pdo->exec($stmt); } catch (Exception $e) { /* Ignorer les doublons */ }
                }
            }

            // Mettre à jour config.php
            $configContent = file_get_contents(__DIR__ . '/includes/config.php');
            $configContent = preg_replace("/define\('DB_HOST', '.*?'\)/", "define('DB_HOST', '$dbHost')", $configContent);
            $configContent = preg_replace("/define\('DB_NAME', '.*?'\)/", "define('DB_NAME', '$dbName')", $configContent);
            $configContent = preg_replace("/define\('DB_USER', '.*?'\)/", "define('DB_USER', '$dbUser')", $configContent);
            $configContent = preg_replace("/define\('DB_PASS', '.*?'\)/", "define('DB_PASS', '$dbPass')", $configContent);
            file_put_contents(__DIR__ . '/includes/config.php', $configContent);

            header('Location: setup.php?step=2&success=1'); exit;
        } catch (PDOException $e) {
            $errors[] = 'Erreur connexion: ' . $e->getMessage();
        }
    } elseif ($step === 2) {
        // Créer admin custom
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $hash = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,username=?,password_hash=? WHERE id=1")
                ->execute([trim($_POST['admin_nom']), trim($_POST['admin_prenom']),
                           trim($_POST['admin_email']), trim($_POST['admin_user']), $hash]);
            header('Location: setup.php?step=3'); exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — Atlas Prime Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--primary:#E8500A;--accent:#F5A623;--bg:#0F1117;--card:#161A27;--border:rgba(255,255,255,0.08);--text:#E8ECF4;--muted:#8892AA;--font:'Sora',sans-serif}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.setup-wrap{width:100%;max-width:560px}
.setup-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,0.5)}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:24px;color:white;margin-bottom:12px}
h1{font-size:1.3rem;font-weight:700}
.steps{display:flex;gap:0;margin-bottom:28px;border-radius:10px;overflow:hidden;border:1px solid var(--border)}
.step-item{flex:1;padding:10px;text-align:center;font-size:.78rem;background:rgba(255,255,255,0.02);color:var(--muted)}
.step-item.active{background:var(--primary);color:white;font-weight:700}
.step-item.done{background:rgba(22,163,74,0.15);color:#4ADE80}
label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;margin-top:14px}
input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:9px;padding:11px 14px;color:var(--text);font-family:var(--font);font-size:.9rem}
input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,80,10,0.15)}
.btn{width:100%;background:linear-gradient(135deg,var(--primary),var(--accent));border:none;border-radius:10px;padding:13px;color:white;font-family:var(--font);font-size:1rem;font-weight:600;cursor:pointer;margin-top:20px;transition:all 0.2s}
.btn:hover{filter:brightness(1.1)}
.error{background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.25);color:#F87171;border-radius:9px;padding:12px;margin-bottom:16px;font-size:.85rem}
.success{background:rgba(22,163,74,0.12);border:1px solid rgba(22,163,74,0.25);color:#4ADE80;border-radius:9px;padding:16px;margin-bottom:16px;font-size:.85rem;text-align:center}
.info-box{background:rgba(14,165,233,0.08);border:1px solid rgba(14,165,233,0.2);border-radius:9px;padding:14px;margin-bottom:16px;font-size:.83rem;color:#38BDF8}
.final{text-align:center;padding:20px 0}
.final i{font-size:3rem;color:#4ADE80;margin-bottom:16px;display:block}
.link{display:block;text-align:center;margin-top:16px;color:var(--primary);text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="setup-wrap">
    <div class="setup-card">
        <div class="logo">
            <div class="logo-icon">🚚</div>
            <h1>Atlas Prime Logistics</h1>
            <p style="font-size:.82rem;color:var(--muted);margin-top:4px">Assistant d'installation</p>
        </div>

        <div class="steps">
            <div class="step-item <?= $step===1?'active':($step>1?'done':'') ?>">1. Base de données</div>
            <div class="step-item <?= $step===2?'active':($step>2?'done':'') ?>">2. Administrateur</div>
            <div class="step-item <?= $step===3?'active':'' ?>">3. Terminé</div>
        </div>

        <?php foreach ($errors as $e): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($step === 1): ?>
        <div class="info-box"><i class="fas fa-info-circle"></i> Assurez-vous que XAMPP est démarré et que MySQL/MariaDB est actif.</div>
        <form method="post" action="setup.php?step=1">
            <label>Hôte MySQL</label><input type="text" name="db_host" value="<?= htmlspecialchars($dbHost) ?>" placeholder="127.0.0.1">
            <label>Nom de la base</label><input type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
            <label>Utilisateur</label><input type="text" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
            <label>Mot de passe</label><input type="password" name="db_pass" value="" placeholder="(vide pour root XAMPP par défaut)">
            <button type="submit" class="btn"><i class="fas fa-database"></i> Créer la base de données</button>
        </form>

        <?php elseif ($step === 2): ?>
        <?php if (isset($_GET['success'])): ?><div class="success">✅ Base de données créée avec succès !</div><?php endif; ?>
        <p style="font-size:.85rem;color:var(--muted);margin-bottom:4px">Configurez le compte administrateur principal :</p>
        <form method="post" action="setup.php?step=2">
            <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
            <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
            <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
            <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
            <label>Prénom</label><input type="text" name="admin_prenom" required placeholder="Jean">
            <label>Nom</label><input type="text" name="admin_nom" required placeholder="Dupont">
            <label>Email</label><input type="email" name="admin_email" required placeholder="admin@votreentreprise.com">
            <label>Nom d'utilisateur</label><input type="text" name="admin_user" required value="admin">
            <label>Mot de passe (min. 6 caractères)</label><input type="password" name="admin_pass" required minlength="6">
            <button type="submit" class="btn"><i class="fas fa-user-shield"></i> Créer l'administrateur</button>
        </form>

        <?php elseif ($step === 3): ?>
        <div class="final">
            <i class="fas fa-circle-check"></i>
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:8px">Installation terminée !</h2>
            <p style="color:var(--muted);font-size:.9rem">Atlas Prime Logistics est prêt à l'emploi.</p>
            <div style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.2);border-radius:9px;padding:12px;margin:20px 0;font-size:.82rem;color:#F87171">
                <i class="fas fa-triangle-exclamation"></i>
                <strong>Important :</strong> Supprimez le fichier <code>setup.php</code> pour des raisons de sécurité !
            </div>
        </div>
        <a href="login.php" class="btn" style="text-decoration:none;display:block;text-align:center">
            <i class="fas fa-sign-in-alt"></i> Accéder à l'application
        </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
