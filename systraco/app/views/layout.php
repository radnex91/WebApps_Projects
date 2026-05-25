<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'SYSTRACO'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo assets('css/style.css'); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/dashboard">
                <i class="fas fa-bus"></i> SYSTRACO
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="gestionDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Gestion
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/agences">Agences</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/itineraires">Itineraires</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/chauffeurs">Chauffeurs</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/vehicules">Vehicules</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/groupes">Groupes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="billetDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ticket"></i> Billetterie
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/billets">Billets</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/reservations">Reservations</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="transportDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-route"></i> Transport
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/departements">Departements</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/programmation">Programmation</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/bordereaux">Bordereaux</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="caisseDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-money-bill"></i> Caisse
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/encaissements">Encaissements</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/depenses">Depenses</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/rapports">
                            <i class="fas fa-chart-bar"></i> Rapports
                        </a>
                    </li>
                    <?php if (getUserRole() === 'Administrateur'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/utilisateurs">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['nom_utilisateur'] ?? 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout">Deconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container mt-4">
        <?php $flash = getFlash(); ?>
        <?php if (!empty($flash)): ?>
            <?php foreach ($flash as $type => $message): ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php echo $content ?? ''; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>