<?php
// ============================================================
// includes/bootstrap.php - Chargement global
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Forcer l'encodage UTF-8 pour toutes les réponses HTML
header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/models.php';
require_once __DIR__ . '/../models/Sale.php';
require_once __DIR__ . '/../models/CaisseSession.php';
require_once __DIR__ . '/../models/Setting.php';

// Démarrage session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification expiration session
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?timeout=1');
    exit;
}
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();

    // Rafraîchir les permissions depuis la DB (permet les changements immédiats)
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        $permUser = new User();
        $permRow = $permUser->find((int)$_SESSION['user_id']);
        if ($permRow) {
            $decoded = json_decode($permRow['permissions'] ?? '[]', true);
            $_SESSION['user_permissions'] = is_array($decoded) ? $decoded : [];
            $_SESSION['user_role'] = $permRow['role'];
            $_SESSION['user'] = $permRow;
        }
    }
}

// Chargement des paramètres application
$settingModel = new Setting();
$appSettings = $settingModel->getAll();
