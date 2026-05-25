<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

$flash = get_flash();
$user = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">StockPro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (user_can('view_dashboard')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Tableau de bord</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_categories')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>" href="categories.php">Categories</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_products')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>" href="products.php">Produits</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_movements')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'movements.php' ? 'active' : '' ?>" href="movements.php">Mouvements</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_sales')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'sales.php' ? 'active' : '' ?>" href="sales.php">Ventes</a></li>
                <?php endif; ?>
                <?php if (user_can('view_reports')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php">Rapports</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_users')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php">Utilisateurs</a></li>
                <?php endif; ?>
                <?php if (user_can('manage_roles')): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'roles.php' ? 'active' : '' ?>" href="roles.php">Roles</a></li>
                <?php endif; ?>
            </ul>
            <?php if ($user): ?>
                <div class="d-flex align-items-center gap-3 text-white small">
                    <span><?= e($user['full_name']) ?> (<?= e($user['role_name']) ?>)</span>
                    <a class="btn btn-outline-light btn-sm" href="logout.php">Deconnexion</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
