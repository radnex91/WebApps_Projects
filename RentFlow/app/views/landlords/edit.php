<?php
$title = 'Modifier le Bailleur';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-pencil"></i> Modifier le Bailleur</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . '/landlords/' . $landlord['id'] . '/update">
                ' . Csrf::field() . '
                <div class="mb-3">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" value="' . htmlspecialchars($landlord['name']) . '" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact *</label>
                    <textarea name="contact" class="form-control" rows="3" required>' . htmlspecialchars($landlord['contact']) . '</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Détails du contrat</label>
                    <textarea name="contract_details" class="form-control" rows="4">' . htmlspecialchars($landlord['contract_details'] ?? '') . '</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Mettre à jour</button>
                <a href="' . BASE_URL . '/landlords" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </form>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
