<?php
$pageTitle = 'Paramétrage';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('settings');

$db = getDB();
$agenceCount = $db->query("SELECT COUNT(*) FROM agence")->fetchColumn();
$bankCount = $db->query("SELECT COUNT(*) FROM bank")->fetchColumn();
$catCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$typeCount = $db->query("SELECT COUNT(*) FROM types_operations")->fetchColumn();
$vehiculeCount = $db->query("SELECT COUNT(*) FROM vehicules")->fetchColumn();
$proprioCount = $db->query("SELECT COUNT(*) FROM proprietaire")->fetchColumn();
$paramCount = $db->query("SELECT COUNT(*) FROM parametres")->fetchColumn();
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-gear-fill me-2"></i>Paramétrage Général</h1><p class="page-subtitle">Configuration de l'application</p></div></div>
        <div class="row g-4">
            <div class="col-md-6 col-xl-4">
                <a href="agences.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-building text-primary" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Agences</h5>
                            <p class="text-muted mb-1">Gestion des agences DANAY EXPRESS</p>
                            <span class="badge bg-primary"><?php echo $agenceCount; ?> agence(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="banks.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-bank text-info" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Banques</h5>
                            <p class="text-muted mb-1">Gestion des banques</p>
                            <span class="badge bg-info text-dark"><?php echo $bankCount; ?> banque(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="proprietaires.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-people text-success" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Propriétaires</h5>
                            <p class="text-muted mb-1">Gestion des propriétaires</p>
                            <span class="badge bg-success"><?php echo $proprioCount; ?> propriétaire(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="repartition.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-pie-chart text-warning" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Clé de répartition</h5>
                            <p class="text-muted mb-1">Répartition des parts</p>
                            <i class="bi bi-arrow-right text-muted"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="categories.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-tags text-success" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Catégories</h5>
                            <p class="text-muted mb-1">Catégories recettes / dépenses</p>
                            <span class="badge bg-success"><?php echo $catCount; ?> catégorie(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="types_operations.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-arrow-left-right text-info" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Types d'opérations</h5>
                            <p class="text-muted mb-1">Types d'opérations financières</p>
                            <span class="badge bg-info text-dark"><?php echo $typeCount; ?> type(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="vehicules.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-truck text-warning" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Véhicules</h5>
                            <p class="text-muted mb-1">Gestion des camions et véhicules</p>
                            <span class="badge bg-warning text-dark"><?php echo $vehiculeCount; ?> véhicule(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a href="parametres.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-sliders text-secondary" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Paramètres système</h5>
                            <p class="text-muted mb-1">Configuration générale</p>
                            <span class="badge bg-secondary"><?php echo $paramCount; ?> paramètre(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <?php if (hasPermission('settings_roles')):
                $roleCount = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
            ?>
            <div class="col-md-6 col-xl-4">
                <a href="roles.php" class="text-decoration-none">
                    <div class="card h-100 border-0" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
                        <div class="card-body text-center py-4">
                            <div class="mb-3"><i class="bi bi-shield-lock text-danger" style="font-size:2.5rem"></i></div>
                            <h5 class="fw-bold">Rôles & Permissions</h5>
                            <p class="text-muted mb-1">Gestion des rôles et autorisations</p>
                            <span class="badge bg-danger"><?php echo $roleCount; ?> rôle(s)</span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>