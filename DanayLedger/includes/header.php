<?php
ob_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();

// Vérifier le backup automatique en fin de requête (ne bloque pas le chargement)
register_shutdown_function('autoBackupIfNeeded');

$currentUser = getCurrentUser();
$unreadNotifs = getUnreadNotificationsCount();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
if ($currentDir !== 'DanayLedger' && $currentDir !== '.') {
    $activePage = $currentDir;
} else {
    $activePage = $currentPage;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'DanayLedger'); ?> - DanayLedger</title>
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/img/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<a href="#main-content" class="skip-nav">Aller au contenu</a>
<div class="wrapper">