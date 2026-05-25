<?php
$pageTitle = 'Mon profil';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

requireLogin();

$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Recuperer les donnees a jour de l'utilisateur
$stmt = $db->prepare("SELECT id, username, email, full_name, role, avatar, is_active, last_login, created_at, updated_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    logout();
}

$errors = [];
$formData = [
    'full_name' => $currentUser['full_name'],
    'email' => $currentUser['email'],
];
$passwordChanged = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verification CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/users/profile.php', 'error', 'Token de securite invalide. Veuillez reessayer.');
    }

    $formData['full_name'] = cleanInput($_POST['full_name'] ?? '');
    $formData['email'] = cleanInput($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation du nom complet
    if ($formData['full_name'] === '') {
        $errors['full_name'] = 'Le nom complet est obligatoire.';
    } elseif (mb_strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Le nom complet doit contenir au moins 2 caracteres.';
    }

    // Validation de l'email
    if ($formData['email'] === '') {
        $errors['email'] = 'L\'adresse email est obligatoire.';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'L\'adresse email n\'est pas valide.';
    } elseif ($formData['email'] !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $userId]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Cet adresse email est deja utilisee.';
        }
    }

    // Validation du mot de passe (optionnel)
    if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
        // Si l'utilisateur veut changer le mot de passe, il faut tous les champs
        if ($currentPassword === '') {
            $errors['current_password'] = 'Le mot de passe actuel est obligatoire pour changer le mot de passe.';
        } else {
            // Verifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();
            if (!password_verify($currentPassword, $hash)) {
                $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
            }
        }

        if ($newPassword === '') {
            $errors['new_password'] = 'Le nouveau mot de passe est obligatoire.';
        } elseif (mb_strlen($newPassword) < 8) {
            $errors['new_password'] = 'Le mot de passe doit contenir au moins 8 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors['new_password'] = 'Le mot de passe doit contenir au moins une majuscule.';
        } elseif (!preg_match('/[a-z]/', $newPassword)) {
            $errors['new_password'] = 'Le mot de passe doit contenir au moins une minuscule.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $errors['new_password'] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'La confirmation du mot de passe est obligatoire.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
        }
    }

    // Mise a jour si aucune erreur
    if (empty($errors)) {
        $oldValues = [
            'full_name' => $currentUser['full_name'],
            'email' => $currentUser['email'],
        ];
        $newValues = [
            'full_name' => $formData['full_name'],
            'email' => $formData['email'],
        ];

        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$formData['full_name'], $formData['email'], $passwordHash, $userId]);
            $newValues['password_changed'] = true;
            $passwordChanged = true;
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$formData['full_name'], $formData['email'], $userId]);
        }

        // Mettre a jour la session
        $_SESSION['full_name'] = $formData['full_name'];

        addAuditLog('update_profile', 'user', $userId, [
            'username' => $currentUser['username'],
        ], $oldValues, $newValues);

        // Recharger les donnees
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, avatar, is_active, last_login, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        if ($passwordChanged) {
            redirectWithMessage(APP_URL . '/users/profile.php', 'success', 'Profil mis a jour avec succes. Mot de passe change.');
        } else {
            redirectWithMessage(APP_URL . '/users/profile.php', 'success', 'Profil mis a jour avec succes.');
        }
    }
}

// Determiner l'initiale pour l'avatar
$initials = strtoupper(mb_substr($currentUser['full_name'], 0, 1));
if (mb_strlen($currentUser['full_name']) >= 2) {
    $parts = explode(' ', $currentUser['full_name']);
    if (count($parts) >= 2) {
        $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
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
                    $notifs->execute([$userId]);
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
                    <a class="dropdown-item active" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>D&eacute;connexion</a>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>

        <div class="row g-4">
            <!-- Carte d'identite de l'utilisateur -->
            <div class="col-lg-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="avatar bg-primary bg-opacity-10 text-primary mx-auto mb-3"
                             style="width:80px;height:80px;font-size:28px;display:flex;align-items:center;justify-content:center;border-radius:16px;font-weight:700;">
                            <?php echo e($initials); ?>
                        </div>
                        <h5 class="mb-1"><?php echo e($currentUser['full_name']); ?></h5>
                        <p class="text-muted mb-2">@<?php echo e($currentUser['username']); ?></p>
                        <span class="badge bg-primary mb-3"><?php echo e(getRoleLabel($currentUser['role'])); ?></span>
                        <div class="text-start mt-3">
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="bi bi-envelope me-2"></i>Email</span>
                                <span class="fw-600"><?php echo e($currentUser['email']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="bi bi-clock me-2"></i>Derni&egrave;re connexion</span>
                                <span class="fw-600"><?php echo formatDate($currentUser['last_login']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="bi bi-calendar me-2"></i>Compte cr&eacute;&eacute; le</span>
                                <span class="fw-600"><?php echo formatDate($currentUser['created_at'], 'd/m/Y'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="bi bi-toggle-on me-2"></i>Statut</span>
                                <span class="badge <?php echo $currentUser['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $currentUser['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire de modification -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-square me-2"></i>Modifier mon profil
                    </div>
                    <div class="card-body">
                        <form method="post" action="" novalidate>
                            <?php echo csrfField(); ?>

                            <!-- Informations generales -->
                            <h6 class="text-primary mb-3"><i class="bi bi-person me-1"></i>Informations personnelles</h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label fw-bold">Nom complet <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" value="<?php echo e($formData['full_name']); ?>" required autofocus>
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
                                    <label class="form-label fw-bold">Identifiant</label>
                                    <input type="text" class="form-control" value="<?php echo e($currentUser['username']); ?>" disabled>
                                    <div class="form-text">L'identifiant ne peut pas &ecirc;tre modifi&eacute;.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">R&ocirc;le</label>
                                    <input type="text" class="form-control" value="<?php echo e(getRoleLabel($currentUser['role'])); ?>" disabled>
                                    <div class="form-text">Le r&ocirc;le ne peut pas &ecirc;tre modifi&eacute; depuis le profil.</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Changement de mot de passe -->
                            <h6 class="text-primary mb-3"><i class="bi bi-shield-lock me-1"></i>Changer le mot de passe</h6>
                            <p class="text-muted small mb-3">Laissez ces champs vides si vous ne souhaitez pas changer votre mot de passe.</p>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label for="current_password" class="form-label fw-bold">Mot de passe actuel</label>
                                    <input type="password" name="current_password" id="current_password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" autocomplete="current-password">
                                    <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['current_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="new_password" class="form-label fw-bold">Nouveau mot de passe</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
                                    <div class="form-text">Minimum 8 caracteres, avec majuscule, minuscule et chiffre.</div>
                                    <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['new_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-bold">Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
                                    <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['confirm_password']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-outline-secondary">Annuler</a>
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