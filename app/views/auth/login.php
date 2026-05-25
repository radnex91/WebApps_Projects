<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-ticket-perforated"></i>
            </div>
            <div class="login-title">
                <h1>Gestion Support</h1>
                <p>Connectez-vous à votre espace</p>
            </div>
            <?php if (isset($_SESSION['flash'])): ?>
                <?php foreach ($_SESSION['flash'] as $flash): ?>
                    <div class="flash-message <?= $flash['type'] === 'danger' ? 'error' : $flash['type'] ?>">
                        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
                        <?= $flash['message'] ?>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <form method="POST" action="/gestion-support/login" class="form-material">
                <div class="form-group-material">
                    <label class="form-label-material" for="email">Adresse email</label>
                    <input type="email" name="email" id="email" class="form-input-material" placeholder="exemple@email.com" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="password">Mot de passe</label>
                    <input type="password" name="password" id="password" class="form-input-material" placeholder="••••••••" required>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <label class="login-remember">
                        <input type="checkbox" name="remember"> Se souvenir
                    </label>
                </div>
                <button type="submit" class="btn-material btn-material-primary" style="width:100%;justify-content:center;padding:10px">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>
            </form>
            <div class="login-footer">
                <p style="margin-bottom:4px"><strong>Comptes de démo :</strong></p>
                <p style="margin-bottom:2px">admin@example.com / admin123</p>
                <p style="margin-bottom:2px">tech@example.com / tech123</p>
                <p>client@example.com / client123</p>
            </div>
        </div>
    </div>
</body>
</html>
