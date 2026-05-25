<?php
// api/users.php — Admin-only user management
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) apiError('Accès interdit', 403);

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($body['action'] ?? '');

switch ($action) {

  case 'list':
    $stmt = $pdo->query("SELECT id, username, email, display_name, avatar_color, is_admin, active, created_at, last_login FROM users ORDER BY id");
    apiSuccess(['users' => $stmt->fetchAll()]);
    break;

  case 'get':
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, username, email, display_name, avatar_color, is_admin, active, created_at, last_login FROM users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) apiError('Utilisateur introuvable', 404);
    apiSuccess(['user' => $u]);
    break;

  case 'create':
    $username    = trim($body['username'] ?? '');
    $email       = trim($body['email'] ?? '');
    $password    = $body['password'] ?? '';
    $displayName = trim($body['display_name'] ?? '');
    $avatarColor = trim($body['avatar_color'] ?? '#4f8ef7');
    $isAdmin     = (int)($body['is_admin'] ?? 0);

    if (!$username || !$email || !$password || !$displayName)
        apiError('Tous les champs obligatoires doivent être remplis');
    if (strlen($password) < 6)
        apiError('Mot de passe trop court (min. 6 car.)');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        apiError('E-mail invalide');

    // Check uniqueness
    $dup = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $dup->execute([$username, $email]);
    if ($dup->fetch()) apiError('Nom d\'utilisateur ou e-mail déjà utilisé');

    $pdo->prepare("INSERT INTO users (username, email, password, display_name, avatar_color, is_admin) VALUES (?,?,?,?,?,?)")
        ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $displayName, $avatarColor, $isAdmin]);
    apiSuccess(['id' => $pdo->lastInsertId()]);
    break;

  case 'update':
    $id = (int)($body['id'] ?? 0);
    $email       = trim($body['email'] ?? '');
    $displayName = trim($body['display_name'] ?? '');
    $avatarColor = trim($body['avatar_color'] ?? '#4f8ef7');
    $isAdmin     = (int)($body['is_admin'] ?? 0);
    $active      = (int)($body['active'] ?? 1);

    if (!$email || !$displayName) apiError('E-mail et nom requis');

    // Cannot de-activate the last admin
    $adminCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin=1 AND id!=?");
    $adminCount->execute([$id]);
    if ($adminCount->fetchColumn() == 0 && $isAdmin == 0)
        apiError('Il doit rester au moins un administrateur');

    // Cannot deactivate yourself
    if ($id == currentUser()['id'] && $active == 0)
        apiError('Vous ne pouvez pas désactiver votre propre compte');

    $pdo->prepare("UPDATE users SET email=?, display_name=?, avatar_color=?, is_admin=?, active=? WHERE id=?")
        ->execute([$email, $displayName, $avatarColor, $isAdmin, $active, $id]);
    apiSuccess();
    break;

  case 'reset_password':
    $id = (int)($body['id'] ?? 0);
    $newPass = $body['password'] ?? '';
    if (strlen($newPass) < 6) apiError('Mot de passe trop court (min. 6 car.)');
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
        ->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
    apiSuccess();
    break;

  case 'toggle_active':
    $id = (int)($body['id'] ?? 0);
    if ($id == currentUser()['id']) apiError('Vous ne pouvez pas désactiver votre propre compte');
    $stmt = $pdo->prepare("SELECT active, is_admin FROM users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) apiError('Utilisateur introuvable', 404);
    $newActive = $u['active'] ? 0 : 1;
    $pdo->prepare("UPDATE users SET active=? WHERE id=?")->execute([$newActive, $id]);
    apiSuccess(['active' => $newActive]);
    break;

  case 'delete':
    $id = (int)($body['id'] ?? 0);
    if ($id == currentUser()['id']) apiError('Vous ne pouvez pas supprimer votre propre compte');
    // Check last admin
    $adminCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin=1 AND id!=?");
    $adminCount->execute([$id]);
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if ($u && $u['is_admin'] && $adminCount->fetchColumn() == 0)
        apiError('Impossible de supprimer le dernier administrateur');
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    apiSuccess();
    break;

  default:
    apiError('Action inconnue');
}