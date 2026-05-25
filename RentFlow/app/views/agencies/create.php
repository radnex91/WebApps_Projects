<?php
$title = 'Ajouter une Agence';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-building-add"></i> Ajouter une Agence</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . '/agencies/store">
                <div class="mb-3">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact *</label>
                    <textarea name="contact" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                <a href="' . BASE_URL . '/agencies" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </form>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
