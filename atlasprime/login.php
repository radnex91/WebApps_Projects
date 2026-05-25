<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

Auth::startSession();
if (Auth::isLoggedIn()) { header('Location: ' . APP_ROOT . 'index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $result = Auth::login($username, $password);
        if ($result['success']) { header('Location: ' . APP_ROOT . 'index.php'); exit; }
        else $error = $result['message'];
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
    <title>Connexion — Atlas Prime Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --primary:#E8500A; --accent:#F5A623;
        --bg:#0F1117; --card:#161A27; --border:rgba(255,255,255,0.08);
        --text:#E8ECF4; --muted:#8892AA; --dim:#555D73;
        --font:'Sora',sans-serif; --mono:'Space Mono',monospace;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;
        display:flex;align-items:center;justify-content:center;
        background: 
            radial-gradient(ellipse at 20% 30%, rgba(232,80,10,0.15) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 70%, rgba(245,166,35,0.1) 0%, transparent 50%),
            radial-gradient(ellipse at 50% 50%, rgba(124,58,237,0.08) 0%, transparent 60%),
            linear-gradient(180deg, #0F1117 0%, #1a1f2e 100%);
        position:relative;overflow:hidden;}
    body::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;
        background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30 0L60 30L30 60L0 30z' fill='rgba(255,255,255,0.015)'/%3E%3C/svg%3E");
        opacity:0.5;}
    body::after{content:'';position:absolute;width:600px;height:600px;border:1px solid rgba(232,80,10,0.08);
        border-radius:50%;top:50%;left:50%;transform:translate(-50%,-50%);
        animation:pulse 8s ease-in-out infinite;}
    @keyframes pulse{0%,100%{transform:translate(-50%,-50%) scale(1);opacity:0.3}
        50%{transform:translate(-50%,-50%) scale(1.1);opacity:0.1}}
    .login-wrap{width:100%;max-width:420px;padding:24px;}
    .login-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:48px;
        box-shadow:0 25px 80px rgba(0,0,0,0.6);}
    .logo-area{text-align:center;margin-bottom:32px;}
    .logo-icon{width:72px;height:72px;background:linear-gradient(135deg,var(--primary),var(--accent));
        border-radius:20px;display:inline-flex;align-items:center;justify-content:center;
        font-size:30px;color:white;margin-bottom:20px;
        box-shadow:0 12px 32px rgba(232,80,10,0.35);}
    .logo-name{font-size:1.6rem;font-weight:700;color:var(--text);display:block;margin-bottom:4px;}
    .logo-sub{font-size:0.8rem;color:var(--muted);letter-spacing:2px;text-transform:uppercase;}
    .form-group{margin-bottom:24px;}
    label{display:block;font-size:0.85rem;font-weight:600;color:var(--muted);
        letter-spacing:0.5px;margin-bottom:10px;}
    .input-wrap{position:relative;}
    .input-wrap > i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--dim);z-index:3;pointer-events:none;}
    .input-wrap input{padding-right:40px;position:relative;z-index:1;width:100%;appearance:none;-webkit-appearance:none;}
    .input-wrap .toggle-pass{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--dim);cursor:pointer;background:none;border:none;font-size:1rem;z-index:5;padding:4px;}
    input{appearance:none;-webkit-appearance:none;width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);
        border-radius:10px;padding:14px 16px 14px 42px;color:var(--text);
        font-family:var(--font);font-size:1rem;transition:all 0.25s;}
    input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(232,80,10,0.15);background:rgba(255,255,255,0.06);}
    input::placeholder{color:var(--dim);}
    .input-wrap .toggle-pass:hover{color:var(--primary);}
    .btn-login{width:100%;background:linear-gradient(135deg,var(--primary),var(--accent));
        border:none;border-radius:12px;padding:16px;color:white;
        font-family:var(--font);font-size:1.05rem;font-weight:600;cursor:pointer;
        transition:all 0.25s;margin-top:12px;display:flex;align-items:center;justify-content:center;gap:10px;}
    .btn-login:hover{filter:brightness(1.1);transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,80,10,0.45);}
    .error{background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.25);
        color:#F87171;border-radius:10px;padding:12px 16px;margin-bottom:20px;
        font-size:0.875rem;display:flex;align-items:center;gap:8px;}
    .footer-note{text-align:center;margin-top:24px;font-size:0.75rem;color:var(--dim);}
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-truck-fast"></i></div>
            <span class="logo-name">Atlas Prime Logistics</span>
            <span class="logo-sub">Gestion des transports</span>
        </div>

        <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Identifiant</label>
                <div class="input-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Nom d'utilisateur ou email"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        </div>
    <p class="footer-note">© <?= date('Y') ?> Atlas Prime Logistics — v1.0</p>
</div>
<script>
function togglePassword() {
    const input = document.getElementById('password');
    const btn = document.querySelector('.toggle-pass i');
    if (input.type === 'password') {
        input.type = 'text';
        btn.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        btn.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
