<?php
$title = 'Ajouter un Lot';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-grid-3x3-gap"></i> Ajouter un Lot</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . '/batches/store">
                ' . Csrf::field() . '
                <div class="mb-3">
                    <label class="form-label">Code du lot *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bailleur *</label>
                    <select name="landlord_id" class="form-control" required>
                        <option value="">Sélectionner...</option>';
                        foreach ($landlords as $landlord) {
                            $content .= '<option value="' . $landlord['id'] . '">' . htmlspecialchars($landlord['name']) . '</option>';
                        }
                    $content .= '</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Agence *</label>
                    <select name="agency_id" class="form-control" required>
                        <option value="">Sélectionner...</option>';
                        foreach ($agencies as $agency) {
                            $content .= '<option value="' . $agency['id'] . '">' . htmlspecialchars($agency['name']) . '</option>';
                        }
                    $content .= '</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prix du lot (mensualité) *</label>
                    <div class="input-group">
                        <input type="number" name="monthly_price" class="form-control" placeholder="Ex: 150000" required step="1000">
                        <span class="input-group-text">' . getCurrency() . '</span>
                    </div>
                    <small class="text-muted">Montant de la mensualité pour ce lot</small>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                <a href="' . BASE_URL . '/batches" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </form>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
