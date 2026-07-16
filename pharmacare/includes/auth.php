<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/rate_limit.php';
require_once __DIR__ . '/audit.php';

// ── Timeout d'inactivité (30 minutes) ──────────────────────
define('SESSION_TIMEOUT_SECONDS', 900);  // 15 min

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (IS_PROD) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL);
        }
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => IS_PROD,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        // Durée de vie du cookie : 30 min (le garbage collector PHP gère l'expiration)
        session_set_cookie_params(array_merge($cookieParams, ['lifetime' => SESSION_TIMEOUT_SECONDS]));
        session_name(SESSION_NAME);
        session_start();
    }

    // Vérifier le timeout d'inactivité
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        // Session expirée — nettoyage et redirection
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = $now;
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
        $db->prepare("UPDATE utilisateurs SET derniere_connexion=NOW() WHERE id=?")->execute([$user['id']]);
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
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Requête invalide (CSRF).');
    }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt(float $n): string { return number_format($n, 2, ',', ' '); }
function fmtInt(int $n): string { return number_format($n, 0, ',', ' '); }
function today(): string { return date('Y-m-d'); }
function genRef(string $prefix): string {
    $db   = getDB();
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $tables = [
        'VNT' => 'ventes',
        'CMD' => 'commandes',
    ];
    $table = $tables[$prefix] ?? 'ventes';

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