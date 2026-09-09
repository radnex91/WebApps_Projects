<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/rate_limit.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/url.php';

/**
 * Délai d'inactivité RÉELLE avant déconnexion auto (en secondes).
 * Configurable par l'admin via le paramètre 'delai_inactivite_min' (en minutes,
 * page Paramètres). Défaut : 15 minutes.
 * Tant que l'utilisateur travaille (souris/clavier détectés), sa session est
 * rafraîchie → il n'est JAMAIS déconnecté pendant le travail.
 * 0 = déconnexion auto désactivée.
 */
function sessionTimeoutSeconds(): int {
    $min = (int)getParam('delai_inactivite_min', '15');
    if ($min < 0) $min = 0;
    return $min * 60;
}

/**
 * Démarre/vérifie la session.
 *
 * $touchActivity (défaut true) : la requête rafraîchit le compteur
 *   d'inactivité. C'est le comportement normal : toute page consultée,
 *   tout formulaire soumis = l'utilisateur travaille = il reste connecté.
 *   false = requête « passive » (heartbeat /ping sans interaction réelle
 *   de l'utilisateur) : on vérifie l'expiration MAIS on ne rafraîchit PAS
 *   le compteur — après le délai d'inactivité la session est déconnectée.
 * $redirectOnExpire (défaut true) : session expirée → redirection vers le
 *   login. false (appels AJAX) : session détruite silencieusement, c'est
 *   l'appelant qui répond 401.
 */
function startSession(bool $touchActivity = true, bool $redirectOnExpire = true): void {
    if (session_status() === PHP_SESSION_NONE) {
        $timeout = sessionTimeoutSeconds();
        // Durcissement de la session (anti-fixation + GC borné).
        // use_strict_mode = 1 : le serveur refuse un SID qu'il n'a pas créé,
        //   bloquant la fixation de session. gc_maxlifetime aligné sur le
        //   timeout d'inactivité côté PHP (le nettoyage auto reste borné).
        ini_set('session.use_strict_mode', '1');
        // gc_maxlifetime aligné sur le timeout d'inactivité côté PHP.
        // Si le timeout est désactivé (0), on garde une valeur sûre (24 h)
        // au lieu de 0 (qui ferait ramasser la session par le GC immédiatement).
        ini_set('session.gc_maxlifetime', (string)($timeout > 0 ? $timeout : 86400));
        ini_set('session.cookie_lifetime', '0');
        // Le cookie secure ne doit PAS dépendre de IS_PROD mais du schéma réel
        // de la requête : sur un LAN en HTTP (sans TLS), secure=true empêcherait
        // le navigateur d'envoyer le cookie → session perdue → échec CSRF.
        // On active secure uniquement si la requête courante est en HTTPS.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || (($_SERVER['SERVER_PORT'] ?? 0) == 443);

        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        // Durée de vie du cookie alignée sur le délai d'inactivité (0 si désactivé)
        session_set_cookie_params(array_merge($cookieParams, ['lifetime' => $timeout]));
        session_name(SESSION_NAME);
        session_start();
    }

    // Vérifier le timeout d'inactivité (0 = désactivé)
    $now = time();
    $timeout = sessionTimeoutSeconds();
    if ($timeout > 0 && isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
        // Session expirée — nettoyage et redirection
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        if ($redirectOnExpire) {
            header('Location: ' . APP_URL . '/index.php?timeout=1');
            exit;
        }
        return; // appel AJAX : l'appelant répondra 401
    }
    // L'utilisateur travaille (requête réelle) → session rafraîchie.
    if ($touchActivity) {
        $_SESSION['last_activity'] = $now;
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    touchUserActivity();
}

// ── Suivi d'activité (utilisateurs en ligne) ──────────────
// Heartbeat throttlé à 60 s par session : met à jour utilisateurs.derniere_activite.
// « En ligne » = activité de moins de 5 minutes (affiché sur la page de connexion).
function ensureActivityColumn(): void {
    static $done = null;
    if ($done !== null) return;
    $done = true;
    try { getDB()->query("SELECT derniere_activite FROM utilisateurs LIMIT 1"); }
    catch (Throwable $e) {
        try { getDB()->exec("ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS derniere_activite DATETIME DEFAULT NULL"); }
        catch (Throwable $e2) { /* non bloquant */ }
    }
}

// ── Récupération locale de mot de passe (sans email) ──────
// Chaque utilisateur configure LUI-MÊME (menu « Mon compte ») :
//   - une question secrète + réponse (réponse stockée hachée bcrypt) ;
//   - un code de récupération à usage unique (stocké haché bcrypt).
// Le reset « mot de passe oublié » vérifie ces facteurs localement.
function ensureResetColumns(): void {
    static $done = null;
    if ($done !== null) return;
    $done = true;
    try { getDB()->query("SELECT question_secrete, reponse_secrete, code_recuperation FROM utilisateurs LIMIT 1"); return; }
    catch (Throwable $e) {}
    try {
        getDB()->exec("ALTER TABLE utilisateurs
            ADD COLUMN IF NOT EXISTS question_secrete VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reponse_secrete VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS code_recuperation VARCHAR(255) DEFAULT NULL");
    } catch (Throwable $e2) { /* non bloquant */ }
}

/** Normalisation d'une réponse/code avant hachage ou vérification. */
function reset_normalize(string $v): string {
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($v)) ?? '', 'UTF-8');
}

function touchUserActivity(bool $force = false): void {
    if (empty($_SESSION['user_id'])) return;
    $now = time();
    if (!$force && isset($_SESSION['act_ping']) && $now - (int)$_SESSION['act_ping'] < 60) return;
    $_SESSION['act_ping'] = $now;
    ensureActivityColumn();
    try {
        getDB()->prepare("UPDATE utilisateurs SET derniere_activite = NOW() WHERE id = ?")
               ->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) { /* non bloquant */ }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        header('Location: ' . APP_URL . '/dashboard.php?err=access');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']         ?? 0,
        'nom'       => $_SESSION['user_nom']        ?? '',
        'prenom'    => $_SESSION['user_prenom']     ?? '',
        'role'      => $_SESSION['user_role']       ?? '',
        'role_id'   => $_SESSION['user_role_id']    ?? 0,
        'login'     => $_SESSION['user_login']      ?? '',
        'email'     => $_SESSION['user_email']      ?? '',
    ];
}

// ── RBAC : Permissions ────────────────────────────────────

function loadPermissions(int $roleId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.code
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$roleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasPermission(string $code): bool {
    if (!isLoggedIn()) return false;
    // L'admin a toujours toutes les permissions (sécurité)
    if (($_SESSION['user_role'] ?? '') === 'admin') return true;
    // Charger les permissions en session si absentes (migration douce)
    if (!isset($_SESSION['user_permissions'])) refreshUserPermissions();
    return in_array($code, $_SESSION['user_permissions'] ?? [], true);
}

function requirePermission(string $code): void {
    requireLogin();
    if (!hasPermission($code)) {
        header('Location: ' . APP_URL . '/dashboard.php?err=access');
        exit;
    }
}

function refreshUserPermissions(): void {
    startSession();
    $roleId = $_SESSION['user_role_id'] ?? 0;
    if ($roleId > 0) {
        $_SESSION['user_permissions'] = loadPermissions($roleId);
    } else {
        // Fallback : charger par code de rôle
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM roles WHERE code = ?");
        $stmt->execute([$_SESSION['user_role'] ?? '']);
        $r = $stmt->fetch();
        if ($r) {
            $_SESSION['user_role_id'] = $r['id'];
            $_SESSION['user_permissions'] = loadPermissions((int)$r['id']);
        }
    }
}

// ── Rôles (wrappers dépréciés, conserve la compatibilité) ──

function isAdmin(): bool     { return hasPermission('utilisateurs.gerer'); }
function isPharmacien(): bool { return hasPermission('produits.ajouter'); }

/**
 * Un menu latéral est-il activé par l'admin ?
 * L'admin peut désactiver/activer chaque entrée du sidebar (table `menus`).
 * S'applique à tous les utilisateurs (y compris l'admin) — sauf le module Menus
 * lui-même, qui reste toujours accessible à qui a la permission.
 */
function menuActif(string $code): bool {
    static $actifs = null;
    if ($actifs === null) {
        $actifs = [];
        try {
            $rows = getDB()->query("SELECT code, actif FROM menus")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $actifs[$r['code']] = (int)$r['actif'] === 1;
        } catch (Exception $e) { /* table absente → tout actif */ }
    }
    // Si le code n'est pas dans la table, on considère le menu actif (compat).
    return $actifs[$code] ?? true;
}

// ── Authentification ──────────────────────────────────────

function login(string $loginInput, string $password): array {
    $ip = clientIp();

    // Vérifier si l'IP est bloquée
    $remaining = rateLimitRemaining($ip);
    if ($remaining > 0) {
        return ['success' => false, 'locked' => true, 'remaining' => $remaining];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.code AS role_code, r.libelle AS role_libelle
        FROM utilisateurs u
        JOIN roles r ON u.role_id = r.id
        WHERE u.login = ? AND u.actif = 1
    ");
    $stmt->execute([$loginInput]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']          = $user['id'];
        $_SESSION['user_nom']        = $user['nom'];
        $_SESSION['user_prenom']     = $user['prenom'];
        $_SESSION['user_role']       = $user['role_code'];
        $_SESSION['user_role_id']    = $user['role_id'];
        $_SESSION['user_role_libelle'] = $user['role_libelle'];
        $_SESSION['user_login']      = $user['login'];
        $_SESSION['user_email']      = $user['email'];
        $_SESSION['user_permissions'] = loadPermissions((int)$user['role_id']);
        ensureActivityColumn();
        $db->prepare("UPDATE utilisateurs SET derniere_connexion=NOW(), derniere_activite=NOW() WHERE id=?")->execute([$user['id']]);
        rateLimitReset($ip);
        auditLog('auth.login', sprintf('Connexion : %s (%s)', $user['login'], $user['role_code']));
        return ['success' => true, 'locked' => false];
    }

    $blocked = rateLimitFail($ip);
    if ($blocked) {
        auditLog('auth.blocked', sprintf('IP bloquée : %s (login: %s)', $ip, $loginInput));
    }
    return ['success' => false, 'locked' => $blocked, 'remaining' => $blocked ? RATE_LIMIT_LOCKOUT : 0];
}

function logout(): void {
    // Marque l'utilisateur hors ligne immédiatement (au lieu d'attendre l'expiry du ping)
    // Garde-fou offline-first : si MySQL est arrêté, on saute la mise à jour
    // (getDB() afficherait la 503 et tuerait le script avant session_destroy()).
    if (!empty($_SESSION['user_id']) && paramsDbReachable()) {
        try {
            ensureActivityColumn();
            getDB()->prepare("UPDATE utilisateurs SET derniere_activite = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")
               ->execute([(int)$_SESSION['user_id']]);
        } catch (Throwable $e) { /* non bloquant */ }
    }
    startSession();
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verifyCsrf(): void {
    $sent = $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);  // CSRF invalide / session expirée (403 : code standard rendu par le SAPI ; 419 non reconnu -> 500)
        if (IS_PROD) {
            error_log('CSRF refusé : ' . ($_SERVER['REQUEST_URI'] ?? '?') .
                      ' IP=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        }
        // Page d'erreur claire + retour automatique vers le login
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">' .
             '<title>Session expirée — PharmaCare</title>' .
             '<meta http-equiv="refresh" content="3;url=' . e(APP_URL . '/index.php?timeout=1') . '">' .
             '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;' .
             'min-height:100vh;background:#0F172A;color:#E2E8F0;margin:0}' .
             '.box{text-align:center;padding:40px;background:#1E293B;border-radius:12px;' .
             'border:1px solid #334155;max-width:400px}' .
             'h1{color:#F87171;margin:0 0 12px}p{color:#94A3B8;line-height:1.5}</style></head>' .
             '<body><div class="box"><h1>Session expirée</h1>' .
             '<p>Votre session a expiré pour des raisons de sécurité. ' .
             'Vous allez être redirigé vers la page de connexion.</p></div></body></html>';
        exit;
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmt(float $n): string { return number_format($n, 2, ',', ' '); }
function fmtInt(int $n): string { return number_format($n, 0, ',', ' '); }
function today(): string { return date('Y-m-d'); }
function genRef(string $prefix): string {
    $db   = getDB();
    $year = (int)date('Y');

    // Séquence atomique sans race : INSERT…ON DUPLICATE KEY UPDATE avec
    // LAST_INSERT_ID(compteur+1) incrémente et expose la valeur de façon
    // atomique et par-connexion. Deux caissiers concurrents obtiennent deux
    // numéros distincts, sans retry ni collision.
    try {
        $db->prepare("INSERT INTO compteurs_ref (prefix, annee, compteur)
                      VALUES (?, ?, 1)
                      ON DUPLICATE KEY UPDATE compteur = LAST_INSERT_ID(compteur + 1)")
           ->execute([$prefix, $year]);
        $seq = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        if ($seq > 0) {
            return $prefix . '-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {
        // table manquante → fallback MAX ci-dessous
    }

    // Fallback : MAX(reference) si la table compteurs_ref n'existe pas encore.
    $tables = [
        'VNT' => 'ventes',
        'CMD' => 'commandes',
        'TRF' => 'transferts_magasin',
    ];
    $table = $tables[$prefix] ?? 'ventes';
    $like  = $prefix . '-' . $year . '-%';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $stmt = $db->prepare(
            "SELECT reference FROM `$table` WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1"
        );
        $stmt->execute([$like]);
        $last = $stmt->fetchColumn();
        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next  = (int)end($parts) + 1;
        }
        $ref = $prefix . '-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        try {
            $check = $db->prepare("SELECT 1 FROM `$table` WHERE reference = ?");
            $check->execute([$ref]);
            if (!$check->fetchColumn()) {
                return $ref;
            }
        } catch (Exception $e) {
            return $ref;
        }
    }
    return $prefix . '-' . $year . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

// fmtMoney() est définie dans config/settings.php

function flash(string $msg, string $type = 'success'): void {
    startSession();
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

/**
 * Message d'erreur prod-safe pour les catch(PDOException|Exception).
 * En dev : affiche le message réel (utile pour diagnostiquer).
 * En prod : message générique + journalisation via error_log() pour ne pas
 * fuiter d'internals PDO / chemins serveur dans l'UI.
 */
function flashError(Throwable $e, string $contexte = ''): void {
    if (!defined('IS_PROD') || !IS_PROD) {
        $msg = $e->getMessage();
        if ($contexte !== '') $msg = $contexte . ' : ' . $msg;
        flash($msg, 'error');
        return;
    }
    $log = 'PharmaCare error';
    if ($contexte !== '') $log .= ' [' . $contexte . ']';
    $log .= ': ' . $e->getMessage();
    error_log($log);
    flash('Une erreur est survenue. Elle a été journalisée ; réessayez ou contactez un administrateur.', 'error');
}

function showFlash(): void {
    startSession();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $type = e($f['type'] ?? 'info');
        $msg  = e($f['msg']);
        $svgIcons = [
            'success' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            'error'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            'info'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        ];
        $iconSvg = $svgIcons[$f['type']] ?? $svgIcons['info'];
        echo '<div class="alert alert-' . $type . '" id="auto-alert">';
        echo '<div class="toast-body">';
        echo '<div class="toast-icon">' . $iconSvg . '</div>';
        echo '<div class="toast-msg">' . $msg . '</div>';
        echo '</div>';
        echo '<div class="toast-progress" style="transform:scaleX(1);transition:transform 4s linear;"></div>';
        echo '</div>';
        echo '<script>(function(){var a=document.getElementById("auto-alert");if(!a)return;requestAnimationFrame(function(){requestAnimationFrame(function(){var p=a.querySelector(".toast-progress");if(p)p.style.transform="scaleX(0)";});});setTimeout(function(){a.classList.add("removing");setTimeout(function(){a.remove();},300);},4000);})();</script>';
    }
}