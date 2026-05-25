<?php
$title = 'Accès refusé';
$content = '<div class="text-center py-5">
    <h1 class="display-1 text-muted">403</h1>
    <h3 class="mb-3">Accès refusé</h3>
    <p class="text-muted">Vous n\'avez pas les droits nécessaires pour accéder à cette page.</p>
    <form action="' . BASE_URL . '/logout" method="POST" class="mt-3">
        ' . Csrf::field() . '
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-right"></i> Retour à la connexion
        </button>
    </form>
</div>';
require __DIR__ . '/../layouts/main.php';
