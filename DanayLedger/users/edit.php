<?php
$pageTitle = 'Modifier un utilisateur';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

requireLogin();
requirePermission('users_edit');

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

// Recuperation de l'utilisateur
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Utilisateur introuvable.');
}

$stmt = $db->prepare("SELECT id, username, email, full_name, role, is_active, created_at FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Utilisateur introuvable.');
}

// Un utilisateur ne peut pas modifier un utilisateur de niveau supérieur
if (!canManageRole($user['role'])) {
    redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Vous n\'avez pas accès à cet utilisateur.');
}

$errors = [];
$formData = [
    'username' => $user['username'],
    'email' => $user['email'],
    'full_name' => $user['full_name'],
    'role' => $user['role'],
    'is_active' => (int)$user['is_active'],
];

// Traitement du toggle is_active (depuis la liste)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Token de securite invalide.');
    }

    $toggleId = (int)($_POST['id'] ?? 0);
    if ($toggleId <= 0) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Utilisateur invalide.');
    }

    // Empecher de desactiver son propre compte
    if ($toggleId === (int)$_SESSION['user_id']) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Vous ne pouvez pas desactiver votre propre compte.');
    }

    $stmt = $db->prepare("SELECT id, is_active, username FROM users WHERE id = ?");
    $stmt->execute([$toggleId]);
    $toggleUser = $stmt->fetch();

    if (!$toggleUser) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Utilisateur introuvable.');
    }

    // Un utilisateur ne peut pas modifier un utilisateur de niveau supérieur
    if (!canManageRole($toggleUser['role'])) {
        redirectWithMessage(APP_URL . '/users/index.php', 'error', 'Vous n\'avez pas accès à cet utilisateur.');
    }

    $newActive = $toggleUser['is_active'] ? 0 : 1;

    $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newActive, $toggleId]);

    $oldValues = ['is_active' => (int)$toggleUser['is_active']];
    $newValues = ['is_active' => $newActive];

    addAuditLog('toggle_active', 'user', $toggleId, [
        'username' => $toggleUser['username'],
        'new_status' => $newActive ? 'actif' : 'inactif',
    ], $oldValues, $newValues);

    $statusLabel = $newActive ? 'active' : 'desactive';
    redirectWithMessage(APP_URL . '/users/index.php', 'success', 'Utilisateur ' . e($toggleUser['username']) . ' ' . $statusLabel . ' avec succes.');
}

// Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // Verification CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/users/edit.php?id=' . $id, 'error', 'Token de securite invalide. Veuillez reessayer.');
    }

    // Nettoyage des donnees
    $formData['username'] = cleanInput($_POST['username'] ?? '');
    $formData['email'] = cleanInput($_POST['email'] ?? '');
    $formData['full_name'] = cleanInput($_POST['full_name'] ?? '');
    $formData['role'] = $_POST['role'] ?? $user['role'];
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
    } elseif ($formData['username'] !== $user['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$formData['username'], $id]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Cet identifiant est deja utilise.';
        }
    }

    if ($formData['email'] === '') {
        $errors['email'] = 'L\'adresse email est obligatoire.';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'L\'adresse email n\'est pas valide.';
    } elseif ($formData['email'] !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $id]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Cet adresse email est deja utilisee.';
        }
    }

    if ($formData['full_name'] === '') {
        $errors['full_name'] = 'Le nom complet est obligatoire.';
    } elseif (mb_strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Le nom complet doit contenir au moins 2 caracteres.';
    }

    // Mot de passe : optionnel en modification
    if ($password !== '') {
        if (mb_strlen($password) < 8) {
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
    }

    if (!array_key_exists($formData['role'], $allRoles)) {
        $errors['role'] = 'Le role selectionne n\'est pas valide.';
    } elseif (!canManageRole($formData['role'])) {
        $errors['role'] = 'Vous n\'avez pas la permission d\'assigner ce rôle.';
    }

    // Empecher de desactiver son propre compte
    if ($id === (int)$_SESSION['user_id'] && !$formData['is_active']) {
        $errors['is_active'] = 'Vous ne pouvez pas desactiver votre propre compte.';
    }

    // Mise a jour si aucune erreur
    if (empty($errors)) {
        // Construire les old/new values pour audit
        $oldValues = [
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'is_active' => (int)$user['is_active'],
        ];

        $newValues = [
            'username' => $formData['username'],
            'email' => $formData['email'],
            'full_name' => $formData['full_name'],
            'role' => $formData['role'],
            'is_active' => $formData['is_active'],
        ];

        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, full_name = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $formData['username'],
                $formData['email'],
                $passwordHash,
                $formData['full_name'],
                $formData['role'],
                $formData['is_active'],
                $id,
            ]);
            $newValues['password_changed'] = true;
        } else {
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $formData['username'],
                $formData['email'],
                $formData['full_name'],
                $formData['role'],
                $formData['is_active'],
                $id,
            ]);
        }

        addAuditLog('update', 'user', $id, [
            'username' => $formData['username'],
        ], $oldValues, $newValues);

        redirectWithMessage(APP_URL . '/users/index.php', 'success', 'Utilisateur modifie avec succes.');
    }
}
?>

<!-- Main Content -->
<div class="main-content" id="main-content" role="main">
    <header class="main-header" role="banner">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button>
            <span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown">
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
                <div class="header-user" role="button" tabindex="0" aria-label="Menu utilisateur" data-bs-toggle="dropdown">
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
                <li class="breadcrumb-item active">Modifier - <?php echo e($user['username']); ?></li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-pencil-square me-2"></i>Modifier l'utilisateur <strong><?php echo e($user['username']); ?></strong></span>
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
                                    <select name="role" id="role" class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" required <?php echo $id === (int)$_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        <?php foreach ($assignableRoles as $slug => $label): ?>
                                        <option value="<?php echo e($slug); ?>" <?php echo $formData['role'] === $slug ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($id === (int)$_SESSION['user_id']): ?>
                                    <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Vous ne pouvez pas modifier votre propre role.</div>
                                    <input type="hidden" name="role" value="<?php echo e($formData['role']); ?>">
                                    <?php endif; ?>
                                    <?php if (isset($errors['role'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['role']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <hr>
                                    <p class="text-muted small mb-2"><i class="bi bi-shield-lock me-1"></i>Laissez les champs mot de passe vides pour ne pas le modifier.</p>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-bold">Nouveau mot de passe</label>
                                    <input type="password" name="password" id="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
                                    <div class="form-text">Minimum 8 caracteres, avec majuscule, minuscule et chiffre.</div>
                                    <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
                                    <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['confirm_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" role="switch" name="is_active" id="is_active" value="1" <?php echo $formData['is_active'] ? 'checked' : ''; ?> <?php echo $id === (int)$_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="is_active">Compte actif</label>
                                    </div>
                                    <?php if ($id === (int)$_SESSION['user_id']): ?>
                                    <input type="hidden" name="is_active" value="1">
                                    <?php endif; ?>
                                    <?php if (isset($errors['is_active'])): ?>
                                    <div class="text-danger small mt-1"><?php echo e($errors['is_active']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo APP_URL; ?>/users/index.php" class="btn btn-outline-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>