<?php
// Fonctions d'authentification - DanayLedger v2

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        session_name('DANAYLEDGER_SESSION');
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasPermission(string $permission): bool {
    if (!isLoggedIn()) return false;
    $perms = $_SESSION['user_permissions'] ?? [];
    return in_array($permission, $perms, true);
}

function requirePermission(string $permission): void {
    if (!hasPermission($permission)) {
        $_SESSION['flash_error'] = "Vous n'avez pas la permission d'accéder à cette page.";
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function attemptLogin(string $username, string $password): array {
    startSecureSession();

    if (isLockedOut($username)) {
        return ['success' => false, 'error' => 'Compte temporairement verrouillé. Réessayez dans ' . LOCKOUT_MINUTES . ' minutes.'];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user) {
        incrementLoginAttempts($username);
        addAuditLog('login_failed', 'user', 0, ['username' => $username]);
        return ['success' => false, 'error' => 'Identifiants incorrects ou compte désactivé.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        incrementLoginAttempts($username);
        addAuditLog('login_failed', 'user', $user['id'], ['username' => $username]);
        return ['success' => false, 'error' => 'Identifiants incorrects.'];
    }

    // Connexion réussie
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    // Charger les permissions du rôle en session
    $rolePerms = loadRolePermissions();
    $_SESSION['user_permissions'] = $rolePerms[$user['role']] ?? [];

    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    clearLoginAttempts($username);
    addAuditLog('login', 'user', $user['id'], ['username' => $user['username']]);

    return ['success' => true];
}

function isLockedOut(string $username): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT attempts, locked_until FROM login_attempts WHERE username = ?");
    $stmt->execute([$username]);
    $attempt = $stmt->fetch();

    if (!$attempt) return false;
    if ($attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) return true;
    if ($attempt['locked_until'] && strtotime($attempt['locked_until']) <= time()) {
        clearLoginAttempts($username);
        return false;
    }
    return ($attempt['attempts'] ?? 0) >= MAX_LOGIN_ATTEMPTS;
}

function incrementLoginAttempts(string $username): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, attempts FROM login_attempts WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newAttempts = $existing['attempts'] + 1;
        if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
            $stmt = $db->prepare("UPDATE login_attempts SET attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), last_attempt = NOW() WHERE username = ?");
            $stmt->execute([$newAttempts, LOCKOUT_MINUTES, $username]);
        } else {
            $stmt = $db->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE username = ?");
            $stmt->execute([$newAttempts, $username]);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO login_attempts (username, attempts, last_attempt) VALUES (?, 1, NOW())");
        $stmt->execute([$username]);
    }
}

function clearLoginAttempts(string $username): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
    $stmt->execute([$username]);
}

function logout(): void {
    startSecureSession();
    if (isset($_SESSION['user_id'])) {
        addAuditLog('logout', 'user', $_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name, role, avatar, is_active, last_login, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getRoleLabel(string $role): string {
    $roles = getAllRoles();
    return $roles[$role] ?? ucfirst($role);
}

function addAuditLog(string $action, string $entityType, int $entityId, array $details = [], ?array $oldValues = null, ?array $newValues = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Exception $e) {
        // Ne pas bloquer l'application si l'audit échoue
    }
}

function addNotification(?int $userId, string $type, string $titre, string $message, string $lien = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $titre, $message, $lien]);
    } catch (Exception $e) {}
}

function getUnreadNotificationsCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function getAllRoles(): array {
    static $roles = null;
    if ($roles === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT slug, name FROM roles ORDER BY id");
            $roles = [];
            while ($row = $stmt->fetch()) {
                $roles[$row['slug']] = $row['name'];
            }
        } catch (Exception $e) {
            $roles = [
                ROLE_ADMIN => 'Administrateur',
                ROLE_UTILISATEUR => 'Utilisateur',
                ROLE_VISITEUR => 'Visiteur (Lecture seule)',
            ];
        }
    }
    return $roles;
}

function loadRolePermissions(): array {
    static $perms = null;
    if ($perms === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT r.slug, rp.permission FROM roles r JOIN role_permissions rp ON r.id = rp.role_id ORDER BY r.id, rp.permission");
            $perms = [];
            while ($row = $stmt->fetch()) {
                $perms[$row['slug']][] = $row['permission'];
            }
        } catch (Exception $e) {
            $perms = [];
        }
    }
    return $perms;
}

function refreshSessionPermissions(): void {
    if (!isLoggedIn()) return;
    $rolePerms = loadRolePermissions();
    $role = $_SESSION['user_role'] ?? '';
    $_SESSION['user_permissions'] = $rolePerms[$role] ?? [];
}

function getRoleBadgeClass(string $slug): string {
    static $colorMap = [
        'admin' => 'bg-danger',
        'utilisateur' => 'bg-primary',
        'visiteur' => 'bg-secondary',
    ];
    return $colorMap[$slug] ?? 'bg-info';
}

function getRoleLevel(string $slug): int {
    static $levels = null;
    if ($levels === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT slug, level FROM roles");
            $levels = [];
            while ($row = $stmt->fetch()) {
                $levels[$row['slug']] = (int) $row['level'];
            }
        } catch (Exception $e) {
            $levels = ['admin' => 1, 'utilisateur' => 50, 'visiteur' => 100];
        }
    }
    return $levels[$slug] ?? 999;
}

function getCurrentRoleLevel(): int {
    return getRoleLevel($_SESSION['user_role'] ?? '');
}

function canManageRole(string $targetSlug): bool {
    $currentLevel = getCurrentRoleLevel();
    $targetLevel = getRoleLevel($targetSlug);
    return $currentLevel < $targetLevel;
}

function canManageUser(array $targetUser): bool {
    return canManageRole($targetUser['role']);
}