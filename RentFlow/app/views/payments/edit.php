<?php
$title = 'Modifier le Paiement';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-pencil-square"></i> Modifier le Paiement</h1>
    <a href="<?= BASE_URL ?>/payments" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="<?= BASE_URL ?>/payments/<?= $payment['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <!-- Agence (disabled) -->
                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-building"></i> Agence</h6>
                        <select class="form-select" disabled>
                            <option><?= htmlspecialchars($payment['agency_name']) ?></option>
                        </select>
                        <small class="text-muted">L'agence ne peut pas etre modifiee</small>
                    </div>

                    <!-- Bailleur (disabled) -->
                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-people"></i> Bailleur</h6>
                        <select class="form-select" disabled>
                            <option><?= htmlspecialchars($payment['landlord_name']) ?></option>
                        </select>
                        <small class="text-muted">Le bailleur ne peut pas etre modifie</small>
                    </div>

                    <!-- Lot (editable) -->
                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-grid"></i> Code du lot</h6>
                        <select name="batch_id" id="batchSelect" class="form-select" required>
                            <option value="">Selectionner le lot...</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['id'] ?>"
                                        data-price="<?= $batch['monthly_price'] ?>"
                                        <?= $batch['id'] == $payment['batch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($batch['name']) ?> - <?= number_format($batch['monthly_price'] ?? 0, 0, ',', ' ') ?> FCFA
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Details -->
                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-currency-exchange"></i> Details du paiement</h6>
                        <div class="mb-3">
                            <label class="form-label">Montant *</label>
                            <div class="input-group">
                                <input type="number" name="amount" id="amountInput" class="form-control"
                                       value="<?= htmlspecialchars($payment['amount']) ?>" required step="1000">
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date d'echeance *</label>
                            <input type="date" name="due_date" class="form-control"
                                   value="<?= htmlspecialchars($payment['due_date']) ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Date de paiement</label>
                            <input type="date" name="paid_date" class="form-control"
                                   value="<?= htmlspecialchars($payment['paid_date'] ?? '') ?>">
                            <small class="text-muted">Laissez vide si le paiement n'est pas encore effectue</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-check-lg"></i> Mettre a jour
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const batchSelect = document.getElementById('batchSelect');
    const amountInput = document.getElementById('amountInput');

    batchSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.price) {
            amountInput.value = selectedOption.dataset.price;
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
