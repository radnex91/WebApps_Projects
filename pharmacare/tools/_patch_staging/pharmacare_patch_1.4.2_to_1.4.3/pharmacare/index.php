<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/settings.php';
startSession();
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error = '';
$lockoutSeconds = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!paramsDbReachable()) {
        // MySQL arrêté : pas de page 503 — un message clair sur le formulaire.
        $error = 'Base de données indisponible — vérifiez que MySQL (XAMPP) est démarré, puis réessayez.';
    } else {
    $loginInput = trim($_POST['login'] ?? '');
    $pass       = $_POST['password'] ?? '';
    if ($loginInput && $pass) {
        $result = login($loginInput, $pass);
        if ($result['success']) {
            flash('Bienvenue, ' . $_SESSION['user_prenom'] . ' !');
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } elseif ($result['locked']) {
            $lockoutSeconds = $result['remaining'];
            $mins = (int)floor($lockoutSeconds / 60);
            $secs = $lockoutSeconds % 60;
            $error = sprintf('Trop de tentatives. Réessayez dans %d:%02d.', $mins, $secs);
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
    }
}

// Message de session expirée
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Votre session a expiré. Veuillez vous reconnecter.';
}

// Confirmation de réinitialisation de mot de passe (lien libre-service)
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Mot de passe réinitialisé avec succès. Connectez-vous avec votre nouveau mot de passe.';
}

// ── Utilisateurs en ligne (activité < 5 min) — affiché sur la page de connexion ──
// Garde-fou : si MySQL est arrêté, la page de connexion reste utilisable —
// on saute simplement ce panneau (il exige la BDD).
$enLigne = [];
if (paramsDbReachable()) {
    try {
        ensureActivityColumn();
        $enLigne = getDB()->query("
            SELECT u.prenom, u.nom, u.login, r.libelle AS role_libelle, r.code AS role_code
            FROM utilisateurs u
            JOIN roles r ON u.role_id = r.id
            WHERE u.actif = 1 AND u.derniere_activite > DATE_SUB(NOW(), INTERVAL 150 SECOND)
            ORDER BY u.derniere_activite DESC
        ")->fetchAll();
    } catch (Throwable $e) { /* table/colonne absente — panneau masqué */ }
}

$appNom = getParam('app_nom', 'PharmaCare');
$ticketSousTitre = getParam('ticket_sous_titre', 'Gestion Pharmacie');
$theme  = getParam('theme', 'dark-navy');
$isLightLogin = str_starts_with($theme, 'light');
$police = getParam('police', 'Manrope');
$policeTitre = getParam('police_titre', 'Manrope');

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
<link rel="stylesheet" href="<?= APP_URL ?>/assets/fonts/fonts.css?v=<?= APP_VERSION ?>">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --p-primary: <?= $pharmaPrimary ?>;
    --p-secondary: <?= $pharmaSecondary ?>;
    --p-accent: <?= $pharmaAccent ?>;
    --p-light: <?= $pharmaLight ?>;
    --ink-900: #0B1220;
    --ink-800: #0F172A;
    --ink-700: #1E293B;
    --ink-600: #334155;
    --line: rgba(148, 163, 184, 0.18);
    --text: #E2E8F0;
    --text-dim: #94A3B8;
    --text-mute: #64748B;
}

body {
    font-family: '<?= $police ?>', sans-serif;
    min-height: 100vh;
    background:
        radial-gradient(1200px 800px at 85% -10%, rgba(13, 148, 136, 0.18), transparent 60%),
        radial-gradient(900px 700px at -10% 110%, rgba(52, 211, 153, 0.12), transparent 55%),
        linear-gradient(160deg, #07101F 0%, #0F172A 45%, #0B1A26 100%);
    position: relative;
    overflow-x: hidden;
    color: var(--text);
}

/* ── Décor pharmacologique : réseau moléculaire (cycles benzène) ── */
.pharma-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.mol-grid {
    position: absolute;
    inset: -40px;
    opacity: 0.5;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220' viewBox='0 0 220 220'><g fill='none' stroke='%2314B8A6' stroke-width='1.4' opacity='0.10'><polygon points='110,20 190,64 190,156 110,200 30,156 30,64'/><polygon points='110,52 162,82 162,138 110,168 58,138 58,82'/><circle cx='110' cy='20' r='4' fill='%2314B8A6'/><circle cx='190' cy='64' r='4' fill='%2314B8A6'/><circle cx='190' cy='156' r='4' fill='%2314B8A6'/><circle cx='110' cy='200' r='4' fill='%2314B8A6'/><circle cx='30' cy='156' r='4' fill='%2314B8A6'/><circle cx='30' cy='64' r='4' fill='%2314B8A6'/></g></svg>");
    background-size: 220px 220px;
    mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, #000 30%, transparent 80%);
    -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, #000 30%, transparent 80%);
}

.glow-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
    opacity: 0.35;
}
.glow-orb.a { width: 380px; height: 380px; background: <?= $pharmaPrimary ?>; top: -120px; right: -80px; animation: drift 18s ease-in-out infinite; }
.glow-orb.b { width: 300px; height: 300px; background: <?= $pharmaAccent ?>; bottom: -100px; left: -60px; animation: drift 22s ease-in-out infinite reverse; }

@keyframes drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(20px, -30px) scale(1.08); }
}

/* Capsules flottantes */
.capsule {
    position: absolute;
    border-radius: 999px;
    opacity: 0.16;
    animation: floaty 14s ease-in-out infinite;
}
.capsule.c1 { width: 120px; height: 48px; top: 12%; left: 8%;
    background: linear-gradient(90deg, <?= $pharmaPrimary ?> 0 50%, #fff 50% 100%); transform: rotate(-18deg); }
.capsule.c2 { width: 90px; height: 36px; top: 70%; left: 14%;
    background: linear-gradient(90deg, #fff 0 50%, <?= $pharmaAccent ?> 50% 100%); transform: rotate(24deg); animation-delay: -4s; }
.capsule.c3 { width: 70px; height: 28px; top: 22%; right: 10%;
    background: linear-gradient(90deg, <?= $pharmaSecondary ?> 0 50%, #fff 50% 100%); transform: rotate(38deg); animation-delay: -8s; }

@keyframes floaty {
    0%, 100% { transform: translateY(0) rotate(var(--r, -18deg)); }
    50% { transform: translateY(-26px) rotate(calc(var(--r, -18deg) + 6deg)); }
}

/* ── Carte ── */
.login-container {
    position: relative;
    z-index: 10;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    flex-direction: column;
    gap: 18px;
}

.login-wrapper {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    max-width: 980px;
    width: 100%;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(26px) saturate(140%);
    -webkit-backdrop-filter: blur(26px) saturate(140%);
    border-radius: 28px;
    border: 1px solid rgba(148, 163, 184, 0.14);
    box-shadow:
        0 30px 60px -20px rgba(0, 0, 0, 0.6),
        0 0 80px rgba(13, 148, 136, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
    overflow: hidden;
    animation: rise 0.7s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes rise { from { opacity: 0; transform: translateY(18px) scale(.99); } to { opacity: 1; transform: none; } }

/* ── Volet marque (pharmacologie) ── */
.login-brand {
    position: relative;
    padding: 52px 44px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 18px;
    overflow: hidden;
    background:
        radial-gradient(120% 80% at 0% 0%, rgba(52, 211, 153, 0.22), transparent 55%),
        linear-gradient(150deg, <?= $pharmaPrimary ?> 0%, #0F766E 55%, #134E4A 100%);
    color: #fff;
}
.login-brand::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'><g fill='none' stroke='white' stroke-width='1.3' opacity='0.14'><polygon points='80,14 140,46 140,114 80,146 20,114 20,46'/><polygon points='80,40 116,60 116,100 80,120 44,100 44,60'/></g></svg>");
    background-size: 160px 160px;
    opacity: 0.6;
    pointer-events: none;
}

.brand-head { display: flex; align-items: center; gap: 18px; position: relative; z-index: 2; }

/* Emblème : mortier + capsule moléculaire */
.brand-logo {
    width: 76px; height: 76px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.28);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 10px 30px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.3);
    flex-shrink: 0;
}
.brand-logo svg { width: 42px; height: 42px; }

.brand-title {
    font-family: '<?= $policeTitre ?>', serif;
    font-size: 30px;
    font-weight: 700;
    letter-spacing: .5px;
    line-height: 1.05;
    text-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.brand-subtitle {
    font-size: 12px;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.82);
    margin-top: 4px;
}

.brand-tagline {
    position: relative; z-index: 2;
    font-size: 15px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.92);
    max-width: 340px;
}
.brand-tagline strong { color: #fff; font-weight: 600; }

.brand-divider {
    width: 56px; height: 3px; border-radius: 3px;
    background: linear-gradient(90deg, #fff, rgba(255,255,255,.3));
    position: relative; z-index: 2;
}

.brand-features { display: flex; flex-direction: column; gap: 14px; position: relative; z-index: 2; margin-top: 6px; }
.feature-item {
    display: flex; align-items: center; gap: 14px;
    font-size: 13.5px;
    color: rgba(255, 255, 255, 0.92);
}
.feature-icon {
    width: 34px; height: 34px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(6px);
}
.feature-icon svg { width: 17px; height: 17px; }

/* ── Volet formulaire ── */
.login-form-wrapper {
    padding: 52px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.form-header { margin-bottom: 30px; }
.form-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
    color: <?= $pharmaSecondary ?>;
    background: rgba(20, 184, 166, 0.10);
    border: 1px solid rgba(20, 184, 166, 0.25);
    padding: 6px 12px; border-radius: 999px;
    margin-bottom: 16px;
}
.form-eyebrow .dot { width: 6px; height: 6px; border-radius: 50%; background: <?= $pharmaAccent ?>; box-shadow: 0 0 8px <?= $pharmaAccent ?>; }
.form-title { font-size: 27px; font-weight: 600; color: #F1F5F9; margin-bottom: 8px; }
.form-subtitle { font-size: 14px; color: var(--text-mute); }

.login-form { display: flex; flex-direction: column; gap: 18px; }

.form-group { position: relative; }
.form-label {
    display: block;
    font-size: 12px; font-weight: 500;
    color: var(--text-dim);
    margin-bottom: 8px;
    letter-spacing: .4px;
}
.input-wrapper { position: relative; display: flex; align-items: center; }
.input-icon {
    position: absolute; left: 15px;
    width: 19px; height: 19px;
    color: #475569;
    transition: color .25s ease;
    z-index: 1;
}
.form-input {
    width: 100%;
    padding: 15px 46px 15px 48px;
    background: rgba(30, 41, 59, 0.6);
    border: 1.5px solid rgba(71, 85, 105, 0.45);
    border-radius: 14px;
    font-size: 14px;
    color: #E2E8F0;
    font-family: '<?= $police ?>', sans-serif;
    transition: all .25s ease;
    outline: none;
}
.form-input::placeholder { color: #475569; }
.form-input:focus {
    background: rgba(30, 41, 59, 0.9);
    border-color: <?= $pharmaPrimary ?>;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.14), 0 0 22px rgba(13, 148, 136, 0.10);
}
.form-input:focus ~ .input-icon { color: <?= $pharmaSecondary ?>; }
/* Autofill (Chrome/Edge) : sans ces règles, le navigateur injecte son propre
   fond (proche du noir sur page sombre) dès que les identifiants sauvegardés
   se remplissent → le champ « devient sombre à la saisie ». */
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover,
.form-input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 1000px #1E293B inset;
    -webkit-text-fill-color: #E2E8F0;
    caret-color: #E2E8F0;
    transition: background-color 9999s ease-out;
}
.form-input:autofill { background-color: #1E293B; color: #E2E8F0; }

.toggle-password {
    position: absolute; right: 12px;
    background: none; border: none; cursor: pointer;
    padding: 6px; color: #475569;
    transition: color .25s ease; z-index: 1;
    display: flex; align-items: center; justify-content: center;
}
.toggle-password:hover { color: #94A3B8; }

.error-box {
    background: rgba(239, 68, 68, 0.10);
    border: 1px solid rgba(239, 68, 68, 0.32);
    border-radius: 14px;
    padding: 13px 15px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 18px;
    animation: shake .5s ease;
}
@keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
.error-icon { width: 20px; height: 20px; color: #EF4444; flex-shrink: 0; }
.error-text { font-size: 13px; color: #F87171; }

.success-box {
    background: rgba(16, 185, 129, 0.10);
    border: 1px solid rgba(16, 185, 129, 0.35);
    border-radius: 14px;
    padding: 13px 15px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 18px;
}
.success-text { font-size: 13px; color: #6EE7B7; }

/* Bouton capsule */
.submit-btn {
    position: relative;
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, <?= $pharmaPrimary ?> 0%, <?= $pharmaSecondary ?> 55%, <?= $pharmaAccent ?> 100%);
    background-size: 200% 200%;
    border: none;
    border-radius: 14px;
    font-size: 15px; font-weight: 600;
    color: #fff;
    font-family: '<?= $police ?>', sans-serif;
    cursor: pointer;
    transition: all .35s ease;
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.32), inset 0 1px 0 rgba(255,255,255,.25);
    margin-top: 6px;
    overflow: hidden;
}
.submit-btn::before {
    content: '';
    position: absolute; top: 0; left: -120%;
    width: 60%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.28), transparent);
    transform: skewX(-20deg);
    transition: left .6s ease;
}
.submit-btn:hover { background-position: 100% 100%; transform: translateY(-2px); box-shadow: 0 14px 32px rgba(13,148,136,.42); }
.submit-btn:hover::before { left: 140%; }
.submit-btn:active { transform: translateY(0); }
.submit-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; pointer-events: none; }
.submit-btn:focus { outline: none; box-shadow: 0 0 0 4px rgba(13,148,136,.3), 0 14px 32px rgba(13,148,136,.42); }

.submit-btn .btn-content { display: inline-flex; align-items: center; gap: 10px; }
.submit-btn .btn-arrow { transition: transform .3s ease; }
.submit-btn:hover .btn-arrow { transform: translateX(4px); }

.demo-credentials {
    margin-top: 22px;
    padding: 16px;
    background: rgba(30, 41, 59, 0.45);
    border-radius: 14px;
    border: 1px solid rgba(71, 85, 105, 0.3);
}
.demo-title { font-size: 11px; color: var(--text-mute); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
.demo-users { display: flex; flex-wrap: wrap; gap: 8px; }
.demo-badge {
    padding: 6px 12px;
    background: rgba(13, 148, 136, 0.12);
    border: 1px solid rgba(13, 148, 136, 0.3);
    border-radius: 8px;
    font-size: 12px;
    color: #5EEAD4;
}
.demo-pass { margin-top: 10px; font-size: 12px; color: var(--text-mute); }
.demo-pass code {
    background: rgba(255, 255, 255, 0.06);
    padding: 2px 8px; border-radius: 5px;
    color: #94A3B8; font-family: 'DM Mono', monospace;
}

.footer-note {
    text-align: center;
    color: rgba(226, 232, 240, 0.5);
    font-size: 12px;
}
.footer-note .rx { color: <?= $pharmaSecondary ?>; font-weight: 600; letter-spacing: 1px; }

.online-panel {
    margin-top: 16px;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 12px 14px;
    background: rgba(148, 163, 184, 0.07);
}
.online-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 11px; letter-spacing: 1.6px; text-transform: uppercase;
    color: var(--text-mute); font-weight: 600; margin-bottom: 8px;
}
.online-count {
    background: rgba(13, 148, 136, 0.15); color: <?= $pharmaSecondary ?>;
    border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700;
}
.online-pulse {
    width: 8px; height: 8px; border-radius: 50%; background: #34D399;
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.18);
    animation: onlinePulse 2s ease-in-out infinite;
}
@keyframes onlinePulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
.online-list { display: flex; flex-direction: column; gap: 6px; }
.online-user { display: flex; align-items: center; gap: 9px; font-size: 12.5px; }
.online-avatar {
    width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(13, 148, 136, 0.14); color: <?= $pharmaSecondary ?>;
    font-size: 10.5px; font-weight: 700;
}
.online-name { color: var(--text); font-weight: 600; flex: 1; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.online-role { font-size: 10.5px; color: var(--text-mute);
    border: 1px solid var(--line); border-radius: 999px; padding: 2px 8px; }
.online-empty { font-size: 12px; color: var(--text-mute); font-style: italic; }

@media (max-width: 820px) {
    .login-wrapper { grid-template-columns: 1fr; max-width: 460px; }
    .login-brand { padding: 34px 32px; order: -1; gap: 14px; }
    .brand-title { font-size: 26px; }
    .login-form-wrapper { padding: 34px 30px; }
    .form-title { font-size: 24px; }
    .brand-tagline { font-size: 14px; }
}
@media (max-width: 480px) {
    .login-container { padding: 14px; }
    .login-form-wrapper { padding: 28px 22px; }
    .login-brand { padding: 28px 24px; }
    .brand-features { display: none; }
    .brand-tagline { display: none; }
    .demo-badge { font-size: 11px; }
}
<?php if ($isLightLogin): ?>
/* ── Variante CLAIRE de la page de connexion (thème light-clair) ── */
:root {
    --ink-900: #F8F9F6; --ink-800: #FCFCFA; --ink-700: #F1F3EF; --ink-600: #E7EAE4;
    --line: rgba(40, 60, 50, 0.14);
    --text: #2A3640;
    --text-dim: #4E5C55;
    --text-mute: #6B7A73;
}
body {
    background:
        radial-gradient(1200px 800px at 85% -10%, rgba(13, 148, 136, 0.10), transparent 60%),
        radial-gradient(900px 700px at -10% 110%, rgba(52, 211, 153, 0.08), transparent 55%),
        linear-gradient(160deg, #F8F9F6 0%, #F1F4EF 45%, #EDF3F1 100%);
    color: #2A3640;
}
.pharma-bg { opacity: .22; }
.glow-orb { opacity: .12; }
.capsule { opacity: .28; }
.login-wrapper {
    background: rgba(252, 252, 250, 0.92);
    border: 1px solid rgba(40, 60, 50, 0.10);
    box-shadow: 0 24px 70px rgba(40, 60, 50, 0.10);
}
.login-brand {
    background:
        radial-gradient(600px 420px at 80% 0%, rgba(13, 148, 136, 0.10), transparent 60%),
        linear-gradient(160deg, #0E5F58 0%, #0D9488 60%, #10B0A0 100%);
    color: #fff;
}
.login-brand .brand-sub,
.login-brand .brand-tagline { color: rgba(255, 255, 255, 0.82); }
.login-form-wrapper { background: rgba(252, 252, 250, 0.96); }
.form-title { color: #22303A; }
.form-subtitle { color: #6B7A73; }
.form-input {
    background: #FCFCFA;
    border: 1.5px solid #D5DDD4;
    color: #2A3640;
}
/* Sans ce :focus clair, c'est celui du thème sombre qui s'applique :
   fond bleu nuit rgba(30,41,59,0.9) sous un texte resté sombre → champ
   noir illisible dès la saisie. */
.form-input:focus {
    background: #FFFFFF;
    border-color: <?= $pharmaPrimary ?>;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12), 0 0 22px rgba(13, 148, 136, 0.10);
}
.form-input::placeholder { color: #8B968F; }
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover,
.form-input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 1000px #FCFCFA inset;
    -webkit-text-fill-color: #2A3640;
    caret-color: #2A3640;
    transition: background-color 9999s ease-out;
}
.form-input:autofill { background-color: #FCFCFA; color: #2A3640; }
.toggle-password { color: #6B7A73; }
.demo-credentials { background: rgba(40, 60, 50, 0.05); border: 1px solid rgba(40, 60, 50, 0.10); }
.demo-users code, .demo-pass code { background: rgba(40, 60, 50, 0.07); color: #2A3640; }
.demo-badge { background: rgba(13, 148, 136, 0.10); color: #0F766E; border: 1px solid rgba(13, 148, 136, 0.2); }
.footer-note { color: #6B7A73; }
<?php endif; ?>
</style>
</head>
<body>

<div class="pharma-bg">
    <div class="mol-grid"></div>
    <div class="glow-orb a"></div>
    <div class="glow-orb b"></div>
    <div class="capsule c1" style="--r:-18deg"></div>
    <div class="capsule c2" style="--r:24deg"></div>
    <div class="capsule c3" style="--r:38deg"></div>
</div>

<div class="login-container">
    <div class="login-wrapper">
        <!-- Volet pharmacologie -->
        <div class="login-brand">
            <div class="brand-head">
                <div class="brand-logo" aria-hidden="true">
                    <!-- Mortier + pestle + Rx -->
                    <svg viewBox="0 0 64 64" fill="none">
                        <path d="M14 30h36l-3 18a6 6 0 0 1-6 5H23a6 6 0 0 1-6-5l-3-18z" fill="white"/>
                        <path d="M40 12c4 4 4 10 0 14" stroke="#0D9488" stroke-width="4" stroke-linecap="round"/>
                        <path d="M28 8h12v6h-4l6 8h-6l-3-5-3 5h-6l6-8h-4V8z" fill="#0F766E"/>
                        <rect x="30" y="34" width="18" height="6" rx="3" fill="#0F766E"/>
                    </svg>
                </div>
                <div>
                    <div class="brand-title"><?= e(APP_NAME) ?></div>
                    <div class="brand-subtitle"><?= e($appNom && $appNom !== APP_NAME ? $appNom : $ticketSousTitre) ?></div>
                </div>
            </div>

            <div class="brand-divider"></div>

            <p class="brand-tagline">
                La plateforme de <strong>pharmacologie</strong> pour piloter stock,
                prescriptions et caisse — pensée pour les officines modernes.
            </p>

            <div class="brand-features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 7h10l-1 12a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2L7 7z"/>
                            <path d="M9 7V5a3 3 0 0 1 6 0v2"/>
                        </svg>
                    </div>
                    <span>Gestion de stock &amp; pharmacologie</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="8" width="18" height="8" rx="4" transform="rotate(-20 12 12)"/>
                            <line x1="12" y1="9.5" x2="12" y2="14.5" transform="rotate(-20 12 12)"/>
                        </svg>
                    </div>
                    <span>Point de vente &amp; règlements</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="M7 14l4-4 3 3 5-6"/>
                        </svg>
                    </div>
                    <span>Rapports &amp; statistiques OHADA</span>
                </div>
            </div>
        </div>

        <!-- Volet formulaire -->
        <div class="login-form-wrapper">
            <div class="form-header">
                <span class="form-eyebrow"><span class="dot"></span> Espace professionnel</span>
                <h2 class="form-title">Connexion</h2>
                <p class="form-subtitle">Accédez à votre officine numérique</p>
            </div>

            <?php if (!empty($success)): ?>
            <div class="success-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:#6ee7b7;flex-shrink:0;">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span class="success-text"><?= e($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error-box" id="error-box">
                <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <span class="error-text" id="error-text"><?= e($error) ?></span>
            </div>
            <?php if ($lockoutSeconds > 0): ?>
            <script>
            (function() {
                var remaining = <?= $lockoutSeconds ?>;
                var el = document.getElementById('error-text');
                var btn = document.querySelector('.submit-btn');
                if (btn) btn.disabled = true;
                var timer = setInterval(function() {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(timer);
                        el.textContent = 'Vous pouvez maintenant réessayer.';
                        if (btn) btn.disabled = false;
                        return;
                    }
                    var m = Math.floor(remaining / 60);
                    var s = remaining % 60;
                    el.textContent = 'Trop de tentatives. Réessayez dans ' + m + ':' + (s < 10 ? '0' : '') + s + '.';
                }, 1000);
            })();
            </script>
            <?php endif; ?>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">

                <div class="form-group">
                    <label class="form-label" for="login-input">Identifiant</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input type="text" name="login" id="login-input" value="<?= e($_POST['login'] ?? '') ?>"
                               placeholder="Entrez votre identifiant" required autofocus autocomplete="username"
                               class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password-input">Mot de passe</label>
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
                    <span class="btn-content">
                        Se connecter
                        <svg class="btn-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div style="text-align:center;margin-top:14px;">
                <a href="<?= APP_URL ?>/mot-de-passe-oublie" style="font-size:12px;color:#94A3B8;text-decoration:none;" onmouseover="this.style.color='#5EEAD4'" onmouseout="this.style.color='#94A3B8'">Mot de passe oublié ?</a>
            </div>

            <div class="online-panel" id="online-panel">
                <div class="online-title">
                    <span class="online-pulse"></span> Utilisateurs en ligne
                    <span class="online-count"><?= count($enLigne) ?></span>
                </div>
                <?php if ($enLigne): ?>
                <div class="online-list">
                    <?php foreach ($enLigne as $u): $init = strtoupper(mb_substr(($u['prenom'] ?: $u['login']), 0, 1) . mb_substr($u['nom'], 0, 1)); ?>
                    <div class="online-user">
                        <span class="online-avatar"><?= e($init) ?></span>
                        <span class="online-name"><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?></span>
                        <span class="online-role"><?= e($u['role_libelle']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="online-empty">Aucun utilisateur en ligne actuellement</div>
                <?php endif; ?>
            </div>

            <?php if (!IS_PROD): ?>
            <div class="demo-credentials">
                <div class="demo-title">Comptes de démonstration</div>
                <div class="demo-users">
                    <span class="demo-badge">admin</span>
                    <span class="demo-badge">pharmacien</span>
                    <span class="demo-badge">caissier</span>
                </div>
                <div class="demo-pass">Mot de passe : <code>password</code></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-note">
        <span class="rx">Rx</span> &nbsp;·&nbsp; &copy; 2026 <?= e(APP_NAME) ?> — Tous droits réservés
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