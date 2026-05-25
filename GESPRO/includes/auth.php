<?php
// ============================================================
// auth.php - Authentification & Session
// ============================================================
require_once __DIR__ . '/../config.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    if (!isset($_SESSION['user_id'])) return false;
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function currentUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function hasRole(string ...$roles): bool {
    $user = currentUser();
    if (!$user) return false;
    return in_array($user['role'], $roles);
}

function login(string $loginInput, string $password): bool {
    require_once __DIR__ . '/db.php';
    $user = queryOne("SELECT * FROM utilisateurs WHERE login = ? AND actif = 1", [$loginInput]);
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function logout(): void {
    startSession();
    session_destroy();
    header('Location: /login.php');
    exit;
}
