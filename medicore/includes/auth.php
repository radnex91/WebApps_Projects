<?php
// ============================================================
//  MediCore ERP  Gestion authentification & sessions
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

//  Vérifier si l'utilisateur est connecté
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

//  Exiger la connexion
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login');
        exit;
    }
    // Vérifier expiration session
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_DURATION) {
        session_destroy();
        header('Location: ' . APP_URL . '/login?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

//  Connexion utilisateur
function login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Mot de passe: bcrypt uniquement
    $valid = false;
    if ($user) {
        if (password_verify($password, $user['mot_de_passe'])) {
            $valid = true;
        }
    }

    if (!$valid) {
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    // Mise à jour dernière connexion
    $db->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")->execute([$user['id']]);

    // Stocker en session
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_nom']      = $user['nom'];
    $_SESSION['user_prenom']   = $user['prenom'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_initiales']= $user['avatar_initiales'] ?? strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'role' => $user['role']];
}

//  Déconnexion
function logout(): void {
    session_destroy();
    session_start();
    $_SESSION['flash_logout'] = true;
    session_write_close();
    header('Location: ' . APP_URL . '/login');
    exit;
}

//  Vérifier rôle autorisé
function hasRole(array $roles): bool {
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

//  Utilisateur courant
function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'nom'       => $_SESSION['user_nom'] ?? '',
        'prenom'    => $_SESSION['user_prenom'] ?? '',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'initiales' => $_SESSION['user_initiales'] ?? 'US',
    ];
}

//  Log d'activité
function logActivity(string $action, string $couleur = 'blue', string $entite = '', int $entite_id = 0): void {
    try {
        $db = getDB();
        $user_id = $_SESSION['user_id'] ?? null;
        $db->prepare("INSERT INTO activite_log (utilisateur_id, action, entite, entite_id, couleur) VALUES (?,?,?,?,?)")
           ->execute([$user_id, $action, $entite, $entite_id ?: null, $couleur]);
    } catch (Exception $e) { _log_error('ACTIVITY_LOG', 'Echec log activité', __FILE__, __LINE__, $e); }
}

//  Traitement formulaire login (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    rate_limit_login(); // Anti-brute force: 5 tentatives/minute par IP
    csrf_verify();      //  Vérifie le token CSRF anti-falsification
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email) {
        header('Location: ' . APP_URL . '/login?error=' . urlencode('Email invalide.'));
        exit;
    }
    $result = login($email, $password);
    if ($result['success']) {
        session_regenerate_id(true); // Prévient la fixation de session
        header('Location: ' . APP_URL . '/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/login?error=' . urlencode($result['message']));
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
