<?php
declare(strict_types=1);
/**
 * Mot de passe oublié — réinitialisation 100 % LOCALE (sans email, sans admin).
 *
 * Méthodes de récupération (configurées par chaque utilisateur via « Mon compte ») :
 *   - réponse à la question secrète personnelle ;
 *   - code de récupération à usage unique.
 *
 * Flux : saisir l'identifiant → le système affiche les facteurs configurés →
 * vérification locale → nouveau mot de passe immédiat.
 *
 * Sécurité :
 *   - réponses/codes stockés hachés bcrypt, comparés via password_verify ;
 *   - anti-énumération : message identique compte inconnu / sans méthode ;
 *   - 3 échecs max par identifiant et par IP, par heure ;
 *   - le code de récupération est consommé (invalidé) après usage réussi ;
 *   - journalisation d'audit de chaque tentative.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
$db = getDB();
$isLight = str_starts_with(getParam('theme', 'dark-navy'), 'light');
ensureResetColumns();

$etsNom = getParam('app_nom', 'PharmaCare');

// ── Table des échecs (anti-bruteforce local) ─────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS mdp_echecs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(100) NOT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mdp_echec (login, created_at),
        INDEX idx_mdp_echec_ip (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

function mdp_page_head(string $titre): void
{
    global $isLight;
    ?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
    <title><?= htmlspecialchars($titre) ?> | PharmaCare</title>
    <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:<?= $isLight ? '#2a3640' : '#e2e8f0' ?>;<?php if ($isLight): ?>background:linear-gradient(135deg,#F8F9F6 0%,#F1F4EF 45%,#EDF3F1 100%)<?php else: ?>background:linear-gradient(135deg,#0F172A 0%,#1E293B 35%,#0F172A 70%,#134E4A 100%)<?php endif; ?>}
    .card{width:100%;max-width:470px;<?php if ($isLight): ?>background:#fcfcfa;border:1px solid rgba(40,60,50,.12);box-shadow:0 20px 60px rgba(40,60,50,.10)<?php else: ?>background:rgba(15,23,42,.9);border:1px solid #334155<?php endif; ?>;border-radius:18px;padding:32px 28px}
    h1{font-size:18px;<?= $isLight ? 'color:#0d9488' : 'color:#5eead4' ?>;margin-bottom:6px}
    p.sub{font-size:12px;<?= $isLight ? 'color:#4e5c55' : 'color:#94a3b8' ?>;margin-bottom:18px;line-height:1.5}
    label{display:block;font-size:12px;<?= $isLight ? 'color:#4e5c55' : 'color:#94a3b8' ?>;margin:12px 0 5px}
    input{width:100%;padding:10px 12px;border-radius:9px;border:1px solid <?= $isLight ? '#d5ddd4' : '#334155' ?>;background:<?= $isLight ? '#fcfcfa' : '#0b1220' ?>;color:<?= $isLight ? '#2a3640' : '#e2e8f0' ?>;font-size:14px}
    button{width:100%;margin-top:16px;padding:11px;border:0;border-radius:9px;background:#0d9488;color:#fff;font-weight:600;font-size:14px;cursor:pointer}
    button:hover{background:#0f766e}
    a.back{display:block;text-align:center;margin-top:14px;font-size:12px;text-decoration:none;<?= $isLight ? 'color:#64748b' : 'color:#94a3b8' ?>}
    a.back:hover{color:#0d9488}
    .msg{padding:11px 13px;border-radius:9px;font-size:13px;margin-bottom:14px;line-height:1.5}
    .msg-ok{background:rgba(16,185,129,.12);color:<?= $isLight ? '#047857' : '#6ee7b7' ?>;border:1px solid rgba(16,185,129,.35)}
    .msg-err{background:rgba(239,68,68,.12);color:<?= $isLight ? '#b91c1c' : '#fca5a5' ?>;border:1px solid rgba(239,68,68,.35)}
    .qbox{padding:12px 14px;border-radius:9px;<?= $isLight ? 'background:rgba(13,148,136,.07)' : 'background:rgba(94,234,212,.07)' ?>;font-size:14px;font-weight:600;margin-bottom:4px}
    </style></head><body>
    <div class="card">
    <h1><?= htmlspecialchars($titre) ?></h1>
    <?php
}
function mdp_page_foot(): void
{
    ?><a class="back" href="<?= APP_URL ?>/index.php">← Retour à la connexion</a>
    </div></body></html><?php
}

function mdp_form_login(): void
{
    ?>
    <form method="POST">
        <input type="hidden" name="action" value="verifier">
        <label for="login">Votre identifiant *</label>
        <input type="text" id="login" name="login" required autofocus autocomplete="username">
        <button type="submit">Continuer</button>
    </form>
    <p class="sub" style="margin-top:14px;">La récupération utilise les éléments que vous avez configurés dans
    <strong>Mon compte</strong> : question secrète et/ou code de récupération.</p>
    <?php
}

// ── Anti-bruteforce : 3 échecs / heure par identifiant, 10 par IP ──
function mdp_echecs_bloque(PDO $db, string $login): bool
{
    $st = $db->prepare("SELECT COUNT(*) FROM mdp_echecs WHERE (login = ? OR ip = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $st->execute([$login, $_SERVER['REMOTE_ADDR'] ?? '']);
    return (int)$st->fetchColumn() >= 3;
}
function mdp_echec(PDO $db, string $login): void
{
    $db->prepare("INSERT INTO mdp_echecs (login, ip) VALUES (?, ?)")->execute([$login, $_SERVER['REMOTE_ADDR'] ?? '']);
}

// ── Étape 1 : identification ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verifier') {
    $login = trim((string)($_POST['login'] ?? ''));
    ensureResetColumns();

    $user = null;
    if ($login !== '' && !mdp_echecs_bloque($db, $login)) {
        $st = $db->prepare("SELECT id, login, question_secrete, code_recuperation FROM utilisateurs WHERE login = ? AND actif = 1");
        $st->execute([$login]);
        $user = $st->fetch();
    }

    // Anti-énumération : même message si compte inconnu ou sans méthode.
    if (!$user || (empty($user['question_secrete']) && empty($user['code_recuperation']))) {
        mdp_page_head('Mot de passe oublié'); ?>
        <div class="msg msg-err">Aucune méthode de récupération n'est disponible pour cet identifiant (ou identifiant inconnu).</div>
        <p class="sub">Pour activer la récupération en libre-service : connectez-vous une fois avec votre mot de passe
        habituel puis ouvrez <strong>Mon compte</strong> (menu Principal) pour configurer votre
        <strong>question secrète</strong> et/ou <strong>générer un code de récupération</strong>.<br>
        Si vous ne pouvez plus vous connecter du tout, contactez la direction.</p>
        <?php mdp_form_login(); mdp_page_foot(); exit;
    }

    // Facteurs configurés → étape 2
    mdp_page_head('Nouveau mot de passe'); ?>
    <p class="sub">Compte <strong><?= htmlspecialchars($user['login']) ?></strong> — vérifiez votre identité pour définir un nouveau mot de passe (8 caractères min.).</p>
    <form method="POST">
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="login" value="<?= htmlspecialchars($user['login']) ?>">
        <?php if (!empty($user['question_secrete'])): ?>
        <div class="form-group" style="margin:12px 0 5px;">
            <label style="margin:0 0 6px;">Question secrète *</label>
            <div class="qbox"><?= htmlspecialchars($user['question_secrete']) ?></div>
            <input type="text" name="reponse" placeholder="Votre réponse (casse ignorée)" autocomplete="off">
        </div>
        <?php endif; ?>
        <?php if (!empty($user['code_recuperation'])): ?>
        <div class="form-group" style="margin:12px 0 5px;">
            <label>Code de récupération <?= !empty($user['question_secrete']) ? '(si vous l\'avez noté)' : '*' ?></label>
            <input type="text" name="code" autocomplete="off" placeholder="16 caractères" style="font-family:monospace;letter-spacing:2px;">
        </div>
        <?php endif; ?>
        <div class="form-group" style="margin:12px 0 5px;">
            <label>Nouveau mot de passe *</label>
            <input type="password" name="np1" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group" style="margin:12px 0 5px;">
            <label>Confirmer *</label>
            <input type="password" name="np2" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit">Réinitialiser le mot de passe</button>
    </form>
    <?php mdp_page_foot(); exit;
}

// ── Étape 2 : vérification + nouveau mot de passe ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $login  = trim((string)($_POST['login'] ?? ''));
    $rep    = (string)($_POST['reponse'] ?? '');
    $code   = (string)($_POST['code'] ?? '');
    $np1    = (string)($_POST['np1'] ?? '');
    $np2    = (string)($_POST['np2'] ?? '');
    ensureResetColumns();

    $bloque = mdp_echecs_bloque($db, $login);
    $user = null;
    if (!$bloque && $login !== '') {
        $st = $db->prepare("SELECT id, login, question_secrete, reponse_secrete, code_recuperation FROM utilisateurs WHERE login = ? AND actif = 1");
        $st->execute([$login]);
        $user = $st->fetch();
    }

    $verifie = false; $viaCode = false;
    if ($user && $np1 === $np2 && strlen($np1) >= 8) {
        // TOUTES les méthodes configurées doivent être validées.
        $okQuestion = true; $okCode = true;
        if (!empty($user['question_secrete'])) {
            $okQuestion = !empty($rep) && password_verify(reset_normalize($rep), $user['reponse_secrete']);
        }
        if (!empty($user['code_recuperation'])) {
            $okCode = !empty($code) && password_verify(reset_normalize($code), $user['code_recuperation']);
        }
        if ($okQuestion && $okCode) { $verifie = true; $viaCode = !empty($user['code_recuperation']); }
    }

    if (!$verifie) {
        mdp_echec($db, $login);
        auditLog('mdp.reset_echec', 'Tentative de récupération échouée : ' . ($bloque ? 'rate limit' : $login));
        mdp_page_head('Nouveau mot de passe'); ?>
        <div class="msg msg-err">
        <?php if ($bloque): ?>
            Trop de tentatives pour cet identifiant. Réessayez dans une heure ou contactez la direction.
        <?php elseif ($np1 !== $np2 || strlen($np1) < 8): ?>
            Les mots de passe ne correspondent pas ou sont trop courts (8 min).
        <?php else: ?>
            Vérification échouée. <?= (int)$db->query("SELECT COUNT(*) FROM mdp_echecs WHERE login = " . $db->quote($login) . " AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn() ?>/3 tentatives restantes cette heure.
        <?php endif; ?>
        </div>
        <?php mdp_form_login(); mdp_page_foot(); exit;
    }

    // Succès → nouveau mot de passe + consommation du code (usage unique)
    $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?")
       ->execute([password_hash($np1, PASSWORD_DEFAULT), $user['id']]);
    if ($viaCode) {
        $db->prepare("UPDATE utilisateurs SET code_recuperation = NULL WHERE id = ?")->execute([$user['id']]);
    }
    $db->exec("DELETE FROM mdp_echecs WHERE login = " . $db->quote($login));
    auditLog('mdp.reinitialise', 'Mot de passe réinitialisé en libre-service (' . ($viaCode ? 'code' : 'question secrète') . ') — utilisateur #' . $user['id']);

    mdp_page_head('Mot de passe réinitialisé'); ?>
    <div class="msg msg-ok"><strong>Mot de passe réinitialisé avec succès.</strong><br>
    Connectez-vous avec votre nouveau mot de passe.
    <?php if ($viaCode): ?><br>Votre code de récupération a été consommé — générez-en un nouveau depuis <strong>Mon compte</strong> après connexion.<?php endif; ?>
    </div>
    <?php mdp_page_foot(); exit;
}

// ── Page initiale ────────────────────────────────────────────
mdp_page_head('Mot de passe oublié'); ?>
<p class="sub">Récupération <strong>locale</strong>, sans email et sans administrateur : saisissez votre identifiant,
puis validez votre <strong>question secrète</strong> et/ou votre <strong>code de récupération</strong>
(configurables à l'avance dans <strong>Mon compte</strong>).</p>
<?php mdp_form_login(); mdp_page_foot();