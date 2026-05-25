<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AureliaHost — Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-wrapper" role="main">
        <!-- Left Panel — Brand -->
        <div class="login-brand-panel" aria-hidden="true">
            <div class="login-brand-overlay"></div>
            <div class="login-brand-grid"></div>
            <div class="login-brand-content">
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <h1 class="login-brand-name">AureliaHost</h1>
                    <p class="login-brand-tagline">Système de Gestion Hôtelière</p>
                </div>
                <div class="login-features">
                    <div class="login-feature">
                        <div class="login-feature-icon"><i class="bi bi-calendar-check"></i></div>
                        <div class="login-feature-text">
                            <span class="login-feature-title">Réservations & Planning</span>
                            <span class="login-feature-desc">Gérez vos chambres et réservations en temps réel</span>
                        </div>
                    </div>
                    <div class="login-feature">
                        <div class="login-feature-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="login-feature-text">
                            <span class="login-feature-title">Gestion RH & Paie</span>
                            <span class="login-feature-desc">Suivez vos équipes, congés et bulletins</span>
                        </div>
                    </div>
                    <div class="login-feature">
                        <div class="login-feature-icon"><i class="bi bi-graph-up"></i></div>
                        <div class="login-feature-text">
                            <span class="login-feature-title">Comptabilité & Rapports</span>
                            <span class="login-feature-desc">Factures, paiements et analyses financières</span>
                        </div>
                    </div>
                </div>
                <div class="login-footer-text">
                    <small>&copy; <?= date('Y') ?> AureliaHost. Tous droits réservés.</small>
                </div>
            </div>
        </div>

        <!-- Right Panel — Form -->
        <div class="login-form-panel">
            <div class="login-form-container">
                <div class="login-form-header">
                    <span class="login-badge">Espace professionnel</span>
                    <h2>Bienvenue</h2>
                    <p>Connectez-vous pour accéder à votre espace</p>
                </div>

                <?php if (Session::hasFlash('error')): ?>
                    <div class="login-alert login-alert-error" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= e(Session::getFlash('error')) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (Session::hasFlash('success')): ?>
                    <div class="login-alert login-alert-success" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?= e(Session::getFlash('success')) ?></span>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
