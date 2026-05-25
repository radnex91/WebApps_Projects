<?php
// preferences.php — Endpoint AJAX pour les préférences utilisateur (thème)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// CSRF check manuel (pas csrf_verify() qui die() en HTML)
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

$action = post_str('action');

if ($action === 'save_theme') {
    $validPresets = ['ocean','foret','crepuscule','nuit','bordeaux','sarcelle','ambre','magnetique','custom'];
    $preset  = post_str('theme_preset');
    $primary = post_str('theme_primary');
    $accent  = post_str('theme_accent');
    $bg      = post_str('theme_bg');
    $surface = post_str('theme_surface');

    if ($preset !== '' && in_array($preset, $validPresets, true)) {
        save_user_pref('theme_preset', $preset);
    }
    if ($primary !== '' && preg_match('/^[0-9a-fA-F]{6}$/', $primary)) {
        save_user_pref('theme_primary', $primary);
    }
    if ($accent !== '' && preg_match('/^[0-9a-fA-F]{6}$/', $accent)) {
        save_user_pref('theme_accent', $accent);
    }
    if ($bg !== '' && preg_match('/^[0-9a-fA-F]{6}$/', $bg)) {
        save_user_pref('theme_bg', $bg);
    }
    if ($surface !== '' && preg_match('/^[0-9a-fA-F]{6}$/', $surface)) {
        save_user_pref('theme_surface', $surface);
    }

    logActivity('Thème personnel mis à jour', 'blue', 'settings');
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'reset_theme') {
    delete_user_prefs();
    logActivity('Thème personnel réinitialisé', 'yellow', 'settings');
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);