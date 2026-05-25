<?php
require_once 'includes/config.php';
if (!empty($_SESSION['user'])) { header('Location: '.BASE_URL.'/pages/dashboard.php'); exit; }
$ent = getEntreprise();
$error = '';

// ============================================
// RATE LIMITING - 5 tentatives max en 15 min
// ============================================
function getLoginAttempts($ip, $email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND (email=? OR email='*') AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip, $email]);
    return (int)$stmt->fetchColumn();
}

function recordLoginAttempt($ip, $email, $success) {
    $db = getDB();
    // Enregistrer les échecs
    if (!$success) {
        $db->prepare("INSERT INTO login_attempts (ip_address, email, created_at) VALUES (?, ?, NOW())")
           ->execute([$ip, $email]);
    }
    // Nettoyer les anciens enregistrements (> 15 min)
    $db->query("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
}

function isRateLimited($ip, $email) {
    return getLoginAttempts($ip, $email) >= 5;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    // Vérifier rate limiting
    if (isRateLimited($ip, $email)) {
        $error = 'Trop de tentatives. Veuillez réessayer dans 15 minutes.';
        recordLoginAttempt($ip, $email, false);
    } elseif ($email && $mdp) {
        $db = getDB();
        $st = $db->prepare("SELECT * FROM utilisateurs WHERE email=? AND actif=1");
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            // Régénérer l'ID de session pour prévenir le fixation
            session_regenerate_id(true);
            // Nouveau token CSRF
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // Vider le cache permissions
            $_SESSION['permissions_cache'] = [];

            $_SESSION['user'] = $user;
            $db->prepare("UPDATE utilisateurs SET derniere_connexion=NOW() WHERE id=?")->execute([$user['id']]);
            // Enregistrer succès pour nettoyer les échecs précédents
            recordLoginAttempt($ip, $email, true);
            // Audit log
            $_SESSION['user'] = $user; // Needed for logAudit before redirect
            logAudit($db, 'login', 'utilisateur', $user['id'], ['email'=>$email]);
            header('Location: '.BASE_URL.'/pages/dashboard.php'); exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
            recordLoginAttempt($ip, $email, false);
            // Audit log for failed login
            $db2 = getDB();
            $db2->prepare("INSERT INTO audit_log (action, entity, new_values, ip_address, created_at) VALUES ('login_fail','utilisateur',?,?,NOW())")
               ->execute([json_encode(['email'=>$email]), $ip]);
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
<title>Connexion — <?= htmlspecialchars($ent['nom']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">
<style>
:root {
  --primary: <?= $ent['couleur_primaire'] ?>;
  --dark: <?= $ent['couleur_secondaire'] ?>;
  --bg: #f8fafc;
  --card: #ffffff;
  --text: #1e293b;
  --muted: #94a3b8;
  --border: #e2e8f0;
  --danger: #ef4444;
  --radius: 16px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  background: var(--dark);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}
.bg-mesh {
  position: fixed; inset: 0; z-index: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 40%, color-mix(in srgb, var(--primary) 25%, transparent), transparent),
    radial-gradient(ellipse 60% 50% at 80% 70%, color-mix(in srgb, var(--primary) 15%, transparent), transparent),
    var(--dark);
}
.bg-grid {
  position: fixed; inset: 0; z-index: 1;
  background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 40px 40px;
}
.login-wrapper {
  position: relative; z-index: 10;
  width: 100%; max-width: 440px;
  padding: 24px;
  animation: fadeUp .6s ease both;
}
@keyframes fadeUp {
  from { opacity:0; transform: translateY(30px); }
  to   { opacity:1; transform: translateY(0); }
}
.logo-area {
  text-align: center;
  margin-bottom: 40px;
}
.logo-area img {
  height: 64px; object-fit: contain; margin-bottom: 16px;
  filter: drop-shadow(0 4px 20px rgba(0,0,0,.3));
}
.logo-icon {
  width: 64px; height: 64px; border-radius: 18px;
  background: var(--primary);
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
  box-shadow: 0 8px 32px color-mix(in srgb, var(--primary) 40%, transparent);
}
.logo-icon svg { color: white; width: 30px; height: 30px; }
.company-name {
  font-family: 'Manrope', sans-serif;
  font-size: 26px; font-weight: 800;
  color: white;
  letter-spacing: -0.5px;
}
.company-slogan {
  color: rgba(255,255,255,.5);
  font-size: 14px; margin-top: 4px;
}
.card {
  background: rgba(255,255,255,.07);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 24px;
  padding: 36px;
}
.card-title {
  font-family: 'Manrope', sans-serif;
  font-size: 22px; font-weight: 700;
  color: white; margin-bottom: 6px;
}
.card-sub { color: rgba(255,255,255,.5); font-size: 14px; margin-bottom: 28px; }
.field { margin-bottom: 20px; }
.field label {
  display: block; font-size: 13px; font-weight: 500;
  color: rgba(255,255,255,.7); margin-bottom: 8px;
}
.input-wrap { position: relative; }
.input-wrap svg {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: rgba(255,255,255,.35); width: 16px; height: 16px;
}
.input-wrap input {
  width: 100%;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px;
  padding: 13px 14px 13px 42px;
  font-family: 'Manrope', sans-serif;
  font-size: 15px; color: white;
  outline: none;
  transition: border-color .2s, background .2s;
}
.input-wrap input:focus {
  border-color: var(--primary);
  background: rgba(255,255,255,.12);
}
.input-wrap input::placeholder { color: rgba(255,255,255,.25); }
.btn {
  width: 100%;
  background: var(--primary);
  color: white;
  border: none; border-radius: 12px;
  padding: 14px; font-size: 15px; font-weight: 600;
  font-family: 'Manrope', sans-serif;
  cursor: pointer; letter-spacing: 0.3px;
  transition: all .2s;
  box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 40%, transparent);
  margin-top: 8px;
}
.btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.error-box {
  background: rgba(239,68,68,.15);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: 10px; padding: 12px 16px;
  color: #fca5a5; font-size: 14px; margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}
.hint {
  text-align: center; margin-top: 24px;
  color: rgba(255,255,255,.3); font-size: 13px;
}
.hint strong { color: rgba(255,255,255,.6); }
</style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grid"></div>
<div class="login-wrapper">
  <div class="logo-area">
    <?php if (!empty($ent['logo']) && file_exists('uploads/logos/'.$ent['logo'])): ?>
      <img src="uploads/logos/<?= $ent['logo'] ?>" alt="Logo">
    <?php else: ?>
      <div class="logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
    <?php endif; ?>
    <div class="company-name"><?= htmlspecialchars($ent['nom']) ?></div>
    <?php if (!empty($ent['slogan'])): ?>
    <div class="company-slogan"><?= htmlspecialchars($ent['slogan']) ?></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-title">Connexion</div>
    <div class="card-sub">Accédez à votre espace de gestion</div>
    <?php if ($error): ?>
    <div class="error-box">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label>Adresse email</label>
        <div class="input-wrap">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input type="email" name="email" placeholder="votre@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
      </div>
      <div class="field">
        <label>Mot de passe</label>
        <div class="input-wrap">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="mot_de_passe" placeholder="••••••••" required>
        </div>
      </div>
      <button type="submit" class="btn">Se connecter →</button>
    </form>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
</body>
</html>
