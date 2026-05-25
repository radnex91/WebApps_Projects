<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — AureliaHost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#1a1d23; color:#e1e4e8; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .err { text-align:center; }
        .err h1 { font-size:6rem; color:#dc3545; font-weight:800; }
    </style>
</head>
<body>
<div class="err">
    <h1>404</h1>
    <p class="lead">Page introuvable</p>
    <a href="<?= BASE_URL ?>" class="btn btn-primary mt-3">Retour au dashboard</a>
</div>
</body>
</html>
