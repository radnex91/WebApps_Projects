<?php
$pageTitle = 'Nouvel utilisateur';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

requireLogin();
requirePermission('users_create');

$db = getDB();
$allRoles = getAllRoles();
$currentLevel = getCurrentRoleLevel();

// Un utilisateur ne peut assigner que des rôles de niveau >= au sien
$assignableRoles = [];
foreach ($allRoles as $slug => $label) {
    if (getRoleLevel($slug) >= $currentLevel) {
        $assignableRoles[$slug] = $label;
    }
}

$errors = [];
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => !empty($assignableRoles) ? array_key_first($assignableRoles) : '',
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verification CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Token de securite invalide. Veuillez reessayer.');
    }

    // Nettoyage des donnees
    $formData['username'] = cleanInput($_POST['username'] ?? '');
    $formData['email'] = cleanInput($_POST['email'] ?? '');
    $formData['full_name'] = cleanInput($_POST['full_name'] ?? '');
    $formData['role'] = $_POST['role'] ?? ROLE_UTILISATEUR;
    $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if ($formData['username'] === '') {
        $errors['username'] = 'L\'identifiant est obligatoire.';
    } elseif (mb_strlen($formData['username']) < 3) {
        $errors['username'] = 'L\'identifiant doit contenir au moins 3 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
        $errors['username'] = 'L\'identifiant ne peut contenir que des lettres, chiffres et underscores.';
    } else {
        // Verifier unicite
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Cet identifiant est deja utilise.';
        }
    }

    if ($formData['email'] === '') {
        $errors['email'] = 'L\'adresse email est obligatoire.';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'L\'adresse email n\'est pas valide.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Cet adresse email est deja utilisee.';
        }
    }

    if ($formData['full_name'] === '') {
        $errors['full_name'] = 'Le nom complet est obligatoire.';
    } elseif (mb_strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Le nom complet doit contenir au moins 2 caracteres.';
    }

    if ($password === '') {
        $errors['password'] = 'Le mot de passe est obligatoire.';
    } elseif (mb_strlen($password) < 8) {
        $errors['password'] = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Le mot de passe doit contenir au moins un chiffre.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'La confirmation du mot de passe est obligatoire.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
    }

    if (!array_key_exists($formData['role'], $allRoles)) {
        $errors['role'] = 'Le role selectionne n\'est pas valide.';
    } elseif (!canManageRole($formData['role'])) {
        $errors['role'] = 'Vous n\'avez pas la permission d\'assigner ce rôle.';
    }

    // Creation si aucune erreur
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $formData['username'],
            $formData['email'],
            $passwordHash,
            $formData['full_name'],
            $formData['role'],
            $formData['is_active'],
        ]);

        $newUserId = (int) $db->lastInsertId();

        addAuditLog('create', 'user', $newUserId, [
            'username' => $formData['username'],
            'email' => $formData['email'],
            'full_name' => $formData['full_name'],
            'role' => $formData['role'],
            'is_active' => $formData['is_active'],
        ]);

        redirectWithMessage(APP_URL . '/users/index.php', 'success', 'Utilisateur cree avec succes.');
    }
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

        <!-- Fil d'Ariane -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/users/index.php">Utilisateurs</a></li>
                <li class="breadcrumb-item active">Nouveau</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-plus-fill me-2"></i>Cr&eacute;er un utilisateur</span>
                        <a href="<?php echo APP_URL; ?>/users/index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" novalidate>
                            <?php echo csrfField(); ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-bold">Identifiant <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" value="<?php echo e($formData['username']); ?>" required autofocus>
                                    <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['username']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="full_name" class="form-label fw-bold">Nom complet <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" value="<?php echo e($formData['full_name']); ?>" required>
                                    <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-bold">Adresse email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo e($formData['email']); ?>" required>
                                    <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['email']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="role" class="form-label fw-bold">R&ocirc;le <span class="text-danger">*</span></label>
                                    <select name="role" id="role" class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" required>
                                        <?php foreach ($assignableRoles as $slug => $label): ?>
                                        <option value="<?php echo e($slug); ?>" <?php echo $formData['role'] === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['role'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['role']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-bold">Mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" required>
                                    <div class="form-text">Minimum 8 caracteres, avec majuscule, minuscule et chiffre.</div>
                                    <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-bold">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" required>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['confirm_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" role="switch" name="is_active" id="is_active" value="1" <?php echo $formData['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="is_active">Compte actif</label>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo APP_URL; ?>/users/index.php" class="btn btn-outline-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Cr&eacute;er l'utilisateur</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>