<?php
/**
 * Authentification : session et contrôle d'accès.
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/db.php';

function getCurrentUser() {
  if (empty($_SESSION['user_id'])) {
    return null;
  }
  $u = getUtilisateurById($_SESSION['user_id']);
  if (!$u || !$u['actif']) {
    unset($_SESSION['user_id'], $_SESSION['login'], $_SESSION['role']);
    return null;
  }
  return $u;
}

function requireLogin($minRole = 'partenaire') {
  $user = getCurrentUser();
  if (!$user) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
  }
  // client = 0 (accueil public seulement), partenaire = 1, admin = 2
  $order = ['client' => 0, 'partenaire' => 1, 'admin' => 2];
  $userLevel = $order[$user['role']] ?? 0;
  $requiredLevel = $order[$minRole] ?? 0;
  if ($userLevel < $requiredLevel) {
    header('Location: index.php');
    exit;
  }
  return $user;
}

function login($login, $password) {
  $user = getUtilisateurByLogin($login);
  if (!$user || !password_verify($password, $user['password_hash'])) {
    return false;
  }
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['login'] = $user['login'];
  $_SESSION['role'] = $user['role'];
  return $user;
}

function logout() {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
