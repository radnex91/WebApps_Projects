<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app.php';

ensureSetupComplete();

if (currentUser() !== null) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $user = authenticate(post('email'), post('password'));

    if ($user === null) {
        flash('error', 'Identifiants invalides.');
        redirect('login.php');
    }

    loginUser($user);
    flash('success', 'Connexion reussie. Bienvenue dans votre espace.');
    redirect('index.php');
}

$flash = consumeFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="brand">
                <div class="brand-mark">D</div>
                <div class="brand-text">
                    <strong><?php echo e(APP_NAME); ?></strong>
                    <span>Messagerie collaborative</span>
                </div>
            </div>

            <h1>Connexion</h1>
            <p>Connectez-vous pour acceder a votre messagerie.</p>

            <?php if ($flash): ?>
                <div class="flash flash-<?php echo e($flash['type']); ?>">
                    <span><?php echo e($flash['message']); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="admin@danaysuite.local" required>
                </label>
                <label>
                    <span>Mot de passe</span>
                    <input type="password" name="password" value="admin123" required>
                </label>
                <button type="submit" class="compose-btn auth-submit">Se connecter</button>
            </form>

            <div class="auth-tip">
                Compte demo : admin@danaysuite.local / admin123
            </div>
        </section>
    </main>
</body>
</html>
