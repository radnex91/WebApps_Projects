<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (login($username, $password)) {
        $redirect = $_SESSION['redirect_after'] ?? 'index.php?page=dashboard';
        unset($_SESSION['redirect_after']);
        redirect($redirect);
    } else {
        $error = 'Identifiants incorrects ou compte désactivé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — DEX Transport</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .login-bg {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .login-bg::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37,99,235,0.08) 0%, transparent 70%);
            top: -200px;
            right: -200px;
        }
        .login-bg::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124,58,237,0.08) 0%, transparent 70%);
            bottom: -100px;
            left: -100px;
        }
        .login-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            animation: slideUp 0.6s ease;
            position: relative;
            z-index: 1;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo .logo-icon {
            font-size: 56px;
            display: block;
            margin-bottom: 12px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        .login-logo h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-logo p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .login-field {
            margin-bottom: 18px;
        }
        .login-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .login-field .input-group {
            display: flex;
            align-items: center;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 0 14px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .login-field .input-group:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            background: #fff;
        }
        .login-field .input-group .input-icon {
            font-size: 20px;
            margin-right: 10px;
        }
        .login-field input {
            width: 100%;
            padding: 12px 0;
            border: none;
            background: transparent;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            color: #1e293b;
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            margin-top: 8px;
        }
        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37,99,235,0.3);
        }
        .login-btn:active {
            transform: translateY(0);
        }
        .login-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.3s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 12px;
        }
        .floating-emojis {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }
        .floating-emojis span {
            position: absolute;
            font-size: 30px;
            animation: floatAround 6s ease-in-out infinite;
            opacity: 0.15;
        }
        @keyframes floatAround {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
        .floating-emojis span:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; font-size: 40px; }
        .floating-emojis span:nth-child(2) { top: 20%; right: 15%; animation-delay: 1s; font-size: 35px; }
        .floating-emojis span:nth-child(3) { bottom: 30%; left: 20%; animation-delay: 2s; font-size: 45px; }
        .floating-emojis span:nth-child(4) { bottom: 15%; right: 25%; animation-delay: 0.5s; font-size: 30px; }
        .floating-emojis span:nth-child(5) { top: 50%; left: 5%; animation-delay: 1.5s; font-size: 38px; }
        .floating-emojis span:nth-child(6) { top: 60%; right: 8%; animation-delay: 2.5s; font-size: 42px; }
    </style>
</head>
<body>
<div class="login-bg">
    <div class="floating-emojis">
        <span>🚌</span>
        <span>🌍</span>
        <span>🏢</span>
        <span>💰</span>
        <span>🛣️</span>
        <span>🔐</span>
    </div>
    <div class="login-card">
        <div class="login-logo">
            <span class="logo-icon">🚌</span>
            <h1>DEX Transport</h1>
            <p>Système de gestion des transports</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error">❌ <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="login-field">
                <label>Nom d'utilisateur ou Email</label>
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <input type="text" name="username" placeholder="admin@dex-transport.com" required autofocus>
                </div>
            </div>
            <div class="login-field">
                <label>Mot de passe</label>
                <div class="input-group">
                    <span class="input-icon">🔑</span>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="login-btn">🔓 Se connecter</button>
        </form>
        <div class="login-footer">
            <p>DEX Transport &copy; <?= date('Y') ?> — Tous droits réservés</p>
        </div>
    </div>
</div>
</body>
</html>
