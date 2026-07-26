<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/config/settings.php';
requireLogin();
requirePermission('dashboard.voir');

$role = $_SESSION['user_role'] ?? '';

// Routeur : inclut le template correspondant au rôle
// Les rôles d'encadrement (admin/directeur/informaticien/régisseur) partagent
// le tableau de bord de direction ; les rôles custom inconnus -> caissier.
$templates = [
    'caissier'      => __DIR__ . '/includes/dashboard-caissier.php',
    'pharmacien'    => __DIR__ . '/includes/dashboard-pharmacien.php',
    'admin'         => __DIR__ . '/includes/dashboard-admin.php',
    'directeur'     => __DIR__ . '/includes/dashboard-admin.php',
    'informaticien' => __DIR__ . '/includes/dashboard-admin.php',
    'regisseur'     => __DIR__ . '/includes/dashboard-admin.php',
];

$template = $templates[$role] ?? $templates['caissier']; // fallback caissier pour rôles custom
require $template;
