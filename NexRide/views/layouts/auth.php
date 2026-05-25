<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Connexion</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <link rel="manifest" href="<?= BASE_URL ?>/public/manifest.json">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?= APP_NAME ?></h1>
                <p>Application de gestion de transport</p>
            </div>
            <?php if ($flash = \Core\Session::getInstance()->getFlash('error')): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</body>
</html>