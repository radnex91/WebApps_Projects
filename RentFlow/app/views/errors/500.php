<?php
$title = 'Erreur Interne';
$content = '<div class="text-center py-5">
    <h1 class="display-1 text-muted">500</h1>
    <h3 class="mb-3">Erreur interne du serveur</h3>
    <p class="text-muted">Une erreur inattendue s\'est produite. Veuillez réessayer plus tard.</p>
    <a href="' . BASE_URL . '/dashboard" class="btn btn-primary mt-3">
        <i class="bi bi-house-door"></i> Retour au Dashboard
    </a>
</div>';
require __DIR__ . '/../layouts/main.php';
