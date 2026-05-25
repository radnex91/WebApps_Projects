<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Page non trouvée | <?php echo APP_NAME; ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        .error-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-template {
            text-align: center;
            padding: 40px 20px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 800;
            color: #001f3f;
            line-height: 1;
            margin-bottom: 10px;
        }
        .error-actions {
            margin-top: 30px;
        }
        .error-actions .btn {
            margin: 0 5px;
        }
    </style>
</head>
<body class="hold-transition login-page bg-navy">
<div class="error-page">
    <div class="error-template">
        <div class="error-template">
            <h1 class="error-code">404</h1>
            <h2 class="text-white mt-3">Page non trouvee</h2>
            <p class="text-light mt-3" style="max-width: 500px; margin: 0 auto;">
                Desole, la page que vous recherchez n'existe pas ou a ete deplacee.
                Veuillez verifier l'URL ou retourner a la page d'accueil.
            </p>
            <div class="error-actions mt-4">
                <a href="<?php echo APP_URL; ?>/" class="btn btn-primary btn-lg">
                    <i class="fas fa-home mr-2"></i> Retour a l'accueil
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Page precedente
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>