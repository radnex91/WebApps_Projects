<?php
// 404 Error Page - DanayLedger
http_response_code(404);

// Minimal setup - no session/auth required
require_once __DIR__ . '/config/constants.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page introuvable | DanayLedger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg, #f0f2f5);
        }
        .error-container {
            text-align: center;
            max-width: 480px;
            padding: 2rem;
        }
        .error-code {
            font-size: clamp(5rem, 12vw, 8rem);
            font-weight: 800;
            line-height: 1;
            color: var(--primary, #0f3460);
            margin-bottom: 0.5rem;
        }
        .error-icon {
            font-size: 3rem;
            color: var(--text-muted, #6c757d);
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.25rem;
            color: var(--text-secondary, #495057);
            margin-bottom: 0.5rem;
        }
        .error-hint {
            font-size: 0.95rem;
            color: var(--text-muted, #6c757d);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="bi bi-compass"></i></div>
        <div class="error-code">404</div>
        <h1 class="error-message">Page introuvable</h1>
        <p class="error-hint">La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-primary btn-lg">
            <i class="bi bi-house me-2"></i>Retour au tableau de bord
        </a>
    </div>
</body>
</html>