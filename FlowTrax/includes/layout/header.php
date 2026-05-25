<?php require_once __DIR__ . '/../auth.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(getSetting('app_name', 'DEX Transport')) ?> <?= isset($page_title) ? '| ' . e($page_title) : '' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/topbar.php'; ?>
        <div class="content-wrapper">
<?php else: ?>
    <div class="auth-wrapper">
<?php endif; ?>
