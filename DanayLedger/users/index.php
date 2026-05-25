<?php
$pageTitle = 'Gestion des utilisateurs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

requirePermission('users');

$db = getDB();
$allRoles = getAllRoles();
$currentLevel = getCurrentRoleLevel();

// Un utilisateur ne voit que les rôles de niveau >= au sien
$visibleRoles = [];
$higherRoleSlugs = [];
foreach ($allRoles as $slug => $label) {
    $rLevel = getRoleLevel($slug);
    if ($rLevel >= $currentLevel) {
        $visibleRoles[$slug] = $label;
    } else {
        $higherRoleSlugs[] = $slug;
    }
}

// Filtres
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Construction de la requete
$where = [];
$params = [];

// Un utilisateur ne voit pas les utilisateurs ayant un rôle de niveau supérieur au sien
if (!empty($higherRoleSlugs)) {
    $placeholders = implode(',', array_fill(0, count($higherRoleSlugs), '?'));
    $where[] = "(u.role NOT IN ($placeholders))";
    $params = array_merge($params, $higherRoleSlugs);
}

if ($search !== '') {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== '' && array_key_exists($roleFilter, $allRoles)) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Comptage
$countQuery = "SELECT COUNT(*) FROM users u $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$perPage = ITEMS_PER_PAGE;
$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Recuperation des utilisateurs
$dataQuery = "SELECT u.id, u.username, u.email, u.full_name, u.role, u.is_active, u.last_login, u.created_at
              FROM users u
              $whereClause
              ORDER BY u.created_at DESC
              LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($dataQuery);
$stmt->execute($params);
$users = $stmt->fetchAll();

// URL de base pour la pagination
$baseUrl = APP_URL . '/users/index.php';
$queryParams = [];
if ($search !== '') $queryParams['search'] = $search;
if ($roleFilter !== '') $queryParams['role'] = $roleFilter;
if (!empty($queryParams)) {
    $baseUrl .= '?' . http_build_query($queryParams);
}
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="notif-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotifs > 0): ?>
                    <span class="notif-badge"><?php echo $unreadNotifs; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <h6 class="dropdown-header">Notifications</h6>
                    <?php
                    $notifs = $db->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 10");
                    $notifs->execute([$_SESSION['user_id']]);
                    foreach ($notifs->fetchAll() as $n): ?>
                    <a class="dropdown-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>" href="<?php echo e($n['lien'] ?? '#'); ?>">
                        <div class="fw-600"><?php echo e($n['titre']); ?></div>
                        <div class="small text-muted"><?php echo e($n['message']); ?></div>
                        <div class="notif-time"><?php echo formatDate($n['created_at']); ?></div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($notifs)): ?>
                    <div class="dropdown-item text-muted text-center py-3">Aucune notification</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <div class="header-user" data-bs-toggle="dropdown">
                    <div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div>
                    <div class="user-info d-none d-sm-block">
                        <div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div>
                        <div class="user-role"><?php echo e($roleLabel ?? ''); ?></div>
                    </div>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>D&eacute;connexion</a>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>

        <!-- Barre d'actions -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="<?php echo APP_URL; ?>/users/index.php" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Recherche</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Nom, identifiant ou email..." value="<?php echo e($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">R&ocirc;le</label>
                        <select name="role" class="form-select">
                            <option value="">Tous les r&ocirc;les</option>
                            <?php foreach ($visibleRoles as $slug => $label): ?>
                            <option value="<?php echo e($slug); ?>" <?php echo $roleFilter === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                        <a href="<?php echo APP_URL; ?>/users/index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    </div>
                    <?php if (hasPermission('users_create')): ?>
                    <div class="col-md-2 text-end">
                        <a href="<?php echo APP_URL; ?>/users/create.php" class="btn btn-success w-100"><i class="bi bi-plus-lg me-1"></i>Nouveau</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tableau des utilisateurs -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2"></i>Utilisateurs <span class="badge bg-secondary"><?php echo $total; ?></span></span>
            </div>
            <div class="table-container">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Identifiant</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>R&ocirc;le</th>
                            <th>Statut</th>
                            <th>Derni&egrave;re connexion</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Aucun utilisateur trouv&eacute;
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                            <?php $canManageUser = canManageRole($u['role']) && $u['id'] != $_SESSION['user_id']; ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary bg-opacity-10 text-primary me-2" style="width:32px;height:32px;font-size:12px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-weight:600;">
                                            <?php echo e(strtoupper(mb_substr($u['full_name'] ?? $u['username'], 0, 1))); ?>
                                        </div>
                                        <span class="fw-600"><?php echo e($u['username']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo e($u['full_name'] ?? '-'); ?></td>
                                <td><?php echo e($u['email'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $roleBadge = getRoleBadgeClass($u['role']);
                                    ?>
                                    <span class="badge <?php echo $roleBadge; ?>"><?php echo e(getRoleLabel($u['role'])); ?></span>
                                </td>
                                <td>
                                    <?php if (hasPermission('users_toggle') && canManageRole($u['role'])): ?>
                                    <form method="post" action="<?php echo APP_URL; ?>/users/edit.php" class="d-inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <div class="form-check form-switch d-inline-block">
                                            <input type="checkbox" class="form-check-input" role="switch"
                                                   name="is_active" value="1"
                                                   <?php echo $u['is_active'] ? 'checked' : ''; ?>
                                                   onchange="this.form.submit()"
                                                   <?php echo $u['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                        <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo formatDate($u['last_login']); ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (hasPermission('users_edit') && canManageRole($u['role'])): ?>
                                        <a href="<?php echo APP_URL; ?>/users/edit.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-primary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Affichage <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $total); ?> sur <?php echo $total; ?>
                </small>
                <?php echo renderPagination($page, $totalPages, $baseUrl); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>