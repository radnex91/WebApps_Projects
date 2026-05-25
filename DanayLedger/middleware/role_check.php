<?php
// Vérification des permissions par rôle - DanayLedger
require_once __DIR__ . '/../includes/auth.php';

/**
 * Vérifier l'accès à une page selon le rôle
 * Utiliser en haut de chaque page protégée
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, $allowedRoles, true)) {
        $_SESSION['flash_error'] = "Accès refusé. Vous n'avez pas les droits nécessaires.";
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Vérifier si l'utilisateur peut créer
 */
function canCreate(): bool {
    return hasPermission('transactions_create') || hasPermission('clients_create') || hasPermission('drivers_create') || hasPermission('users_create');
}

/**
 * Vérifier si l'utilisateur peut modifier
 */
function canEdit(): bool {
    return hasPermission('transactions_edit') || hasPermission('clients_edit') || hasPermission('drivers_edit') || hasPermission('users_edit');
}

/**
 * Vérifier si l'utilisateur peut supprimer
 */
function canDelete(): bool {
    return hasPermission('transactions_delete') || hasPermission('clients_delete') || hasPermission('drivers_delete') || hasPermission('users_delete');
}

/**
 * Vérifier si l'utilisateur peut valider des transactions
 */
function canValidate(): bool {
    return hasPermission('transactions_validate');
}

/**
 * Obtenir les pages de navigation selon le rôle
 */
function getNavPages(): array {
    $perms = $_SESSION['user_permissions'] ?? [];

    $pages = [];
    if (in_array('dashboard', $perms)) $pages[] = 'dashboard';
    if (in_array('recettes', $perms)) $pages[] = 'recettes';
    if (in_array('depenses', $perms)) $pages[] = 'depenses';
    if (in_array('recettes_camions', $perms)) $pages[] = 'recettes_camions';
    if (in_array('versements', $perms)) $pages[] = 'versements';
    if (in_array('settings', $perms)) $pages[] = 'settings';
    if (in_array('users', $perms)) $pages[] = 'users';
    if (in_array('rapports', $perms)) $pages[] = 'rapports';
    if (in_array('recherche', $perms)) $pages[] = 'recherche';
    if (in_array('rapprochement', $perms)) $pages[] = 'rapprochement';
    if (in_array('imports', $perms)) $pages[] = 'imports';
    if (in_array('exports', $perms)) $pages[] = 'exports';
    if (in_array('audit', $perms)) $pages[] = 'audit';
    if (in_array('sauvegarde', $perms)) $pages[] = 'sauvegarde';

    return $pages;
}