<?php
$title = 'Enregistrer un Paiement';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-cash-stack"></i> Enregistrer un Paiement</h1>
    <a href="<?= BASE_URL ?>/payments" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/payments/store" id="paymentForm">
                    <?= Csrf::field() ?>

                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-building"></i> Etape 1: Choisir l'agence</h6>
                        <select name="agency_id" id="agencySelect" class="form-select" required>
                            <option value="">Selectionner l'agence...</option>
                            <?php foreach ($agencies as $agency): ?>
                                <option value="<?= $agency['id'] ?>"><?= htmlspecialchars($agency['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-people"></i> Etape 2: Choisir le bailleur</h6>
                        <select name="landlord_id" id="landlordSelect" class="form-select" required disabled>
                            <option value="">D'abord selectionner une agence...</option>
                        </select>
                        <div id="landlordLoading" class="d-none mt-2">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            <small class="text-muted">Chargement...</small>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-grid"></i> Etape 3: Choisir le lot</h6>
                        <select name="batch_id" id="batchSelect" class="form-select" required disabled>
                            <option value="">D'abord selectionner un bailleur...</option>
                        </select>
                        <div id="batchLoading" class="d-none mt-2">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            <small class="text-muted">Chargement...</small>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-light rounded-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-currency-exchange"></i> Etape 4: Details du paiement</h6>
                        <div class="mb-3">
                            <label class="form-label">Montant *</label>
                            <div class="input-group">
                                <input type="number" name="amount" id="amountInput" class="form-control" placeholder="Ex: 150000" required step="1000">
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date d'echeance *</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Date de paiement (si deja paye)</label>
                            <input type="date" name="paid_date" class="form-control">
                            <small class="text-muted">Laissez vide si le paiement n'est pas encore effectue</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-check-lg"></i> Enregistrer le paiement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var agencySelect = document.getElementById('agencySelect');
    var landlordSelect = document.getElementById('landlordSelect');
    var batchSelect = document.getElementById('batchSelect');
    var amountInput = document.getElementById('amountInput');
    var landlordLoading = document.getElementById('landlordLoading');
    var batchLoading = document.getElementById('batchLoading');

    agencySelect.addEventListener('change', function() {
        var agencyId = this.value;
        landlordSelect.innerHTML = '<option value="">Selectionner le bailleur...</option>';
        landlordSelect.disabled = true;
        batchSelect.innerHTML = '<option value="">D\'abord selectionner un bailleur...</option>';
        batchSelect.disabled = true;
        amountInput.value = '';

        if (!agencyId) {
            landlordSelect.innerHTML = '<option value="">D\'abord selectionner une agence...</option>';
            return;
        }

        landlordLoading.classList.remove('d-none');
        fetch('<?= BASE_URL ?>/payments/landlords-by-agency/' + agencyId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                landlordSelect.innerHTML = '<option value="">Selectionner le bailleur...</option>';
                if (data.length === 0) {
                    landlordSelect.innerHTML = '<option value="">Aucun bailleur pour cette agence</option>';
                } else {
                    data.forEach(function(landlord) {
                        var option = document.createElement('option');
                        option.value = landlord.id;
                        option.textContent = landlord.name;
                        landlordSelect.appendChild(option);
                    });
                    landlordSelect.disabled = false;
                }
            })
            .catch(function(error) { console.error('Error loading landlords:', error); })
            .finally(function() { landlordLoading.classList.add('d-none'); });
    });

    landlordSelect.addEventListener('change', function() {
        var agencyId = agencySelect.value;
        var landlordId = this.value;
        batchSelect.innerHTML = '<option value="">Selectionner le lot...</option>';
        batchSelect.disabled = true;
        amountInput.value = '';

        if (!agencyId || !landlordId) {
            batchSelect.innerHTML = '<option value="">D\'abord selectionner un bailleur...</option>';
            return;
        }

        batchLoading.classList.remove('d-none');
        fetch('<?= BASE_URL ?>/payments/batches-by-agency-landlord?agency_id=' + agencyId + '&landlord_id=' + landlordId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                batchSelect.innerHTML = '<option value="">Selectionner le lot...</option>';
                if (data.length === 0) {
                    batchSelect.innerHTML = '<option value="">Aucun lot pour ce bailleur</option>';
                } else {
                    data.forEach(function(batch) {
                        var option = document.createElement('option');
                        option.value = batch.id;
                        option.textContent = batch.name + ' - ' + parseInt(batch.monthly_price).toLocaleString() + ' FCFA';
                        option.dataset.price = batch.monthly_price;
                        batchSelect.appendChild(option);
                    });
                    batchSelect.disabled = false;
                }
            })
            .catch(function(error) { console.error('Error loading batches:', error); })
            .finally(function() { batchLoading.classList.add('d-none'); });
    });

    batchSelect.addEventListener('change', function() {
        var selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.price) {
            amountInput.value = selectedOption.dataset.price;
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
