<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/settings.php';
startSession();
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $loginInput = trim($_POST['login'] ?? '');
    $pass       = $_POST['password'] ?? '';
    if ($loginInput && $pass) {
        if (login($loginInput, $pass)) {
            flash('Bienvenue, ' . $_SESSION['user_prenom'] . ' !');
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}

$appNom = getParam('app_nom', 'PharmaCare');
$ticketSousTitre = getParam('ticket_sous_titre', 'Gestion Pharmacie');
$theme  = getParam('theme', 'dark-navy');
$police = getParam('police', 'DM Sans');
$policeTitre = getParam('police_titre', 'Cormorant Garamond');
$fontUrl = 'https://fonts.googleapis.com/css2?family=' . urlencode($police) . ':wght@300;400;500;600;700&family=' . urlencode($policeTitre) . ':wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap';

$pharmaPrimary = '#0D9488';
$pharmaSecondary = '#14B8A6';
$pharmaAccent = '#34D399';
$pharmaLight = '#CCFBF1';
$bgDark = '#0F172A';
$bgCard = 'rgba(15, 23, 42, 0.85)';
$bgCardLight = 'rgba(255, 255, 255, 0.95)';
$borderGlow = 'rgba(13, 148, 136, 0.3)';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — <?= e($appNom) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= $fontUrl ?>" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: '<?= $police ?>', sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 35%, #0F172A 70%, #134E4A 100%);
    position: relative;
    overflow-x: hidden;
}

.pharma-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.pharma-pattern {
    position: absolute;
    opacity: 0.03;
}

.circle-1 {
    width: 600px;
    height: 600px;
    border-radius: 50%;
    border: 2px solid <?= $pharmaPrimary ?>;
    top: -150px;
    right: -150px;
    animation: float 20s ease-in-out infinite;
}

.circle-2 {
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, <?= $pharmaPrimary ?> 0%, transparent 70%);
    bottom: -100px;
    left: -100px;
    animation: pulse 8s ease-in-out infinite;
}

.circle-3 {
    width: 300px;
    height: 300px;
    border-radius: 50%;
    border: 1px solid <?= $pharmaSecondary ?>;
    top: 50%;
    left: 30%;
    animation: float 15s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(5deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.05; }
    50% { transform: scale(1.1); opacity: 0.08; }
}

.pharma-dots {
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle, <?= $pharmaPrimary ?> 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.02;
}

.login-container {
    position: relative;
    z-index: 10;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 900px;
    width: 100%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(13, 148, 136, 0.2);
    box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.5),
        0 0 60px rgba(13, 148, 136, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
    overflow: hidden;
}

.login-brand {
    background: linear-gradient(135deg, <?= $pharmaPrimary ?> 0%, <?= $pharmaSecondary ?> 100%);
    padding: 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.login-brand::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    animation: rotate 30s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.brand-logo {
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 28px;
    position: relative;
    z-index: 2;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.brand-logo svg {
    width: 56px;
    height: 56px;
}

.brand-title {
    font-family: '<?= $policeTitre ?>', serif;
    font-size: 36px;
    font-weight: 700;
    color: white;
    letter-spacing: 1px;
    margin-bottom: 8px;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.brand-subtitle {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    letter-spacing: 3px;
    text-transform: uppercase;
    position: relative;
    z-index: 2;
}

.brand-divider {
    width: 60px;
    height: 3px;
    background: rgba(255, 255, 255, 0.4);
    border-radius: 2px;
    margin: 24px 0;
    position: relative;
    z-index: 2;
}

.brand-features {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    z-index: 2;
    margin-top: 12px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 13px;
}

.feature-icon {
    width: 28px;
    height: 28px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-form-wrapper {
    padding: 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.form-header {
    margin-bottom: 36px;
}

.form-title {
    font-size: 28px;
    font-weight: 600;
    color: #F1F5F9;
    margin-bottom: 8px;
}

.form-subtitle {
    font-size: 14px;
    color: #64748B;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    position: relative;
}

.form-label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #94A3B8;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    color: #475569;
    transition: color 0.3s ease;
    z-index: 1;
}

.form-input {
    width: 100%;
    padding: 14px 14px 14px 48px;
    background: rgba(30, 41, 59, 0.8);
    border: 1.5px solid rgba(71, 85, 105, 0.5);
    border-radius: 12px;
    font-size: 14px;
    color: #E2E8F0;
    font-family: '<?= $police ?>', sans-serif;
    transition: all 0.3s ease;
    outline: none;
}

.form-input::placeholder {
    color: #475569;
}

.form-input:focus {
    background: rgba(30, 41, 59, 1);
    border-color: <?= $pharmaPrimary ?>;
    box-shadow: 
        0 0 0 3px rgba(13, 148, 136, 0.15),
        0 0 20px rgba(13, 148, 136, 0.1);
}

.form-input:focus + .input-icon,
.form-input:focus ~ .input-icon {
    color: <?= $pharmaSecondary ?>;
}

.toggle-password {
    position: absolute;
    right: 14px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #475569;
    transition: color 0.3s ease;
    z-index: 1;
}

.toggle-password:hover {
    color: #94A3B8;
}

.error-box {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.error-icon {
    width: 20px;
    height: 20px;
    color: #EF4444;
    flex-shrink: 0;
}

.error-text {
    font-size: 13px;
    color: #F87171;
}

.submit-btn {
    width: 100%;
    padding: 15px 24px;
    background: linear-gradient(135deg, <?= $pharmaPrimary ?> 0%, <?= $pharmaSecondary ?> 50%, <?= $pharmaAccent ?> 100%);
    background-size: 200% 200%;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    color: white;
    font-family: '<?= $police ?>', sans-serif;
    cursor: pointer;
    transition: all 0.4s ease;
    box-shadow: 
        0 4px 15px rgba(13, 148, 136, 0.3),
        0 0 0 0 rgba(13, 148, 136, 0.4);
    margin-top: 8px;
    position: relative;
    overflow: hidden;
}

.submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.submit-btn:hover {
    background-position: 100% 100%;
    transform: translateY(-2px);
    box-shadow: 
        0 8px 25px rgba(13, 148, 136, 0.4),
        0 0 30px rgba(13, 148, 136, 0.2);
}

.submit-btn:hover::before {
    left: 100%;
}

.submit-btn:active {
    transform: translateY(0);
}

.submit-btn:focus {
    outline: none;
    box-shadow: 
        0 0 0 3px rgba(13, 148, 136, 0.3),
        0 8px 25px rgba(13, 148, 136, 0.4);
}

.demo-credentials {
    margin-top: 24px;
    padding: 16px;
    background: rgba(30, 41, 59, 0.5);
    border-radius: 12px;
    border: 1px solid rgba(71, 85, 105, 0.3);
}

.demo-title {
    font-size: 11px;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.demo-users {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.demo-badge {
    padding: 6px 12px;
    background: rgba(13, 148, 136, 0.1);
    border: 1px solid rgba(13, 148, 136, 0.3);
    border-radius: 6px;
    font-size: 12px;
    color: #5EEAD4;
}

.demo-pass {
    margin-top: 10px;
    font-size: 12px;
    color: #64748B;
}

.demo-pass code {
    background: rgba(255, 255, 255, 0.05);
    padding: 2px 8px;
    border-radius: 4px;
    color: #94A3B8;
    font-family: 'DM Mono', monospace;
}

@media (max-width: 768px) {
    .login-wrapper {
        grid-template-columns: 1fr;
        max-width: 420px;
    }
    
    .login-brand {
        padding: 32px;
        order: -1;
    }
    
    .brand-title {
        font-size: 28px;
    }
    
    .brand-logo {
        width: 80px;
        height: 80px;
        margin-bottom: 20px;
    }
    
    .login-form-wrapper {
        padding: 32px;
    }
    
    .form-title {
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .login-container {
        padding: 12px;
    }
    
    .login-form-wrapper {
        padding: 24px;
    }
    
    .login-brand {
        padding: 24px;
    }
    
    .brand-features {
        display: none;
    }
    
    .demo-badge {
        font-size: 11px;
    }
}
</style>
</head>
<body>

<div class="pharma-bg">
    <div class="pharma-dots"></div>
    <div class="pharma-pattern circle-1"></div>
    <div class="pharma-pattern circle-2"></div>
    <div class="pharma-pattern circle-3"></div>
</div>

<div class="login-container">
    <div class="login-wrapper">
        <div class="login-brand">
            <div class="brand-logo">
                <svg viewBox="0 0 512 512" fill="none">
                    <rect x="164" y="94" width="184" height="324" rx="36" fill="white"/>
                    <rect x="94" y="164" width="324" height="184" rx="36" fill="white"/>
                </svg>
            </div>
            <div class="brand-title"><?= e($appNom) ?></div>
            <div class="brand-subtitle"><?= e($ticketSousTitre) ?></div>
            <div class="brand-divider"></div>
            <div class="brand-features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <span>Gestion complète de stock</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </div>
                    <span>Point de vente intégré</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <span>Rapports & statistiques</span>
                </div>
            </div>
        </div>
        
        <div class="login-form-wrapper">
            <div class="form-header">
                <h2 class="form-title">Connexion</h2>
                <p class="form-subtitle">Accédez à votre espace professionnel</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-box">
                <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <span class="error-text"><?= e($error) ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                
                <div class="form-group">
                    <label class="form-label">Identifiant</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input type="text" name="login" value="<?= e($_POST['login'] ?? '') ?>"
                               placeholder="Entrez votre identifiant" required autofocus autocomplete="username"
                               class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mot de passe</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="password" id="password-input" placeholder="••••••••" 
                               required autocomplete="current-password" class="form-input">
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Afficher le mot de passe">
                            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    Se connecter
                </button>
            </form>
            
            <div class="demo-credentials">
                <div class="demo-title">Comptes de démonstration</div>
                <div class="demo-users">
                    <span class="demo-badge">admin</span>
                    <span class="demo-badge">pharmacien</span>
                    <span class="demo-badge">caissier</span>
                </div>
                <div class="demo-pass">Mot de passe: <code>password</code></div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password-input');
    const icon = document.getElementById('eye-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>
</body>
</html>
