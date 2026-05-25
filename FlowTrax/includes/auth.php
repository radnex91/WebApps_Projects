<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'];
        redirect('index.php?page=login');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.nom as role_nom
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function hasPermission(string $permissionCode): bool {
    if (!isLoggedIn()) return false;

    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.code = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $permissionCode]);
    return $stmt->fetchColumn() > 0;
}

function requirePermission(string $permissionCode): void {
    if (!hasPermission($permissionCode)) {
        $_SESSION['error'] = "Accès refusé : permission insuffisante.";
        redirect('index.php?page=dashboard');
    }
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['user_nom'] = $user['nom'] . ' ' . $user['prenom'];

        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        return true;
    }
    return false;
}

function logout(): void {
    session_destroy();
    redirect('index.php?page=login');
}
