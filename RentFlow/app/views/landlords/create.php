<?php
$title = 'Ajouter un Bailleur';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-person-plus"></i> Ajouter un Bailleur</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . '/landlords/store">
                ' . Csrf::field() . '
                <div class="mb-3">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact *</label>
                    <textarea name="contact" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Détails du contrat</label>
                    <textarea name="contract_details" class="form-control" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                <a href="' . BASE_URL . '/landlords" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </form>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
