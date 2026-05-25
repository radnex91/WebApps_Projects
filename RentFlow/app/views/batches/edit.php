<?php
$title = 'Modifier le Lot';
$content = '
<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-pencil"></i> Modifier le Lot</h1>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . '/batches/' . $batch['id'] . '/update">
                ' . Csrf::field() . '
                <div class="mb-3">
                    <label class="form-label">Code du lot *</label>
                    <input type="text" name="name" class="form-control" value="' . htmlspecialchars($batch['name']) . '" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bailleur *</label>
                    <select name="landlord_id" class="form-control" required>';
                        foreach ($landlords as $landlord) {
                            $selected = $batch['landlord_id'] == $landlord['id'] ? 'selected' : '';
                            $content .= '<option value="' . $landlord['id'] . '" ' . $selected . '>' . htmlspecialchars($landlord['name']) . '</option>';
                        }
                    $content .= '</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Agence *</label>
                    <select name="agency_id" class="form-control" required>';
                        foreach ($agencies as $agency) {
                            $selected = $batch['agency_id'] == $agency['id'] ? 'selected' : '';
                            $content .= '<option value="' . $agency['id'] . '" ' . $selected . '>' . htmlspecialchars($agency['name']) . '</option>';
                        }
                    $content .= '</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prix du lot (mensualité) *</label>
                    <div class="input-group">
                        <input type="number" name="monthly_price" class="form-control" value="' . htmlspecialchars($batch['monthly_price'] ?? '') . '" required step="1000">
                        <span class="input-group-text">' . getCurrency() . '</span>
                    </div>
                    <small class="text-muted">Montant de la mensualité pour ce lot</small>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Mettre à jour</button>
                <a href="' . BASE_URL . '/batches" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </form>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
