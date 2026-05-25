<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard">
            <i class="bi bi-house-door"></i> <?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'active' : '' ?>" 
                       href="<?= BASE_URL ?>/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= strpos($_SERVER['REQUEST_URI'], '/landlords') !== false || strpos($_SERVER['REQUEST_URI'], '/agencies') !== false || strpos($_SERVER['REQUEST_URI'], '/batches') !== false ? 'active' : '' ?>" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-people"></i> Gestion
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/landlords">
                            <i class="bi bi-person"></i> Bailleurs
                        </a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/agencies">
                            <i class="bi bi-building"></i> Agences
                        </a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/batches">
                            <i class="bi bi-grid"></i> Lots
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/payments') !== false ? 'active' : '' ?>" 
                       href="<?= BASE_URL ?>/payments">
                        <i class="bi bi-cash-stack"></i> Paiements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/reports') !== false ? 'active' : '' ?>" 
                       href="<?= BASE_URL ?>/reports">
                        <i class="bi bi-bar-chart"></i> Rapports
                    </a>
                </li>
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= strpos($_SERVER['REQUEST_URI'], '/users') !== false || strpos($_SERVER['REQUEST_URI'], '/settings') !== false ? 'active' : '' ?>" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Administration
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/users">
                            <i class="bi bi-people-fill"></i> Utilisateurs
                        </a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/users/roles">
                            <i class="bi bi-shield-lock"></i> Rôles & Permissions
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings">
                            <i class="bi bi-gear"></i> Paramètres
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Invité') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
