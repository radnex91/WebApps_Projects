<?php
$title = '404 - Page non trouvée';
$content = '
<div class="container text-center mt-5">
    <h1 class="display-1 fw-bold text-primary">404</h1>
    <p class="lead">Page non trouvée</p>
    <a href="' . BASE_URL . '/dashboard" class="btn btn-primary"><i class="bi bi-house"></i> Retour au Dashboard</a>
</div>';
require __DIR__ . '/../layouts/main.php';
