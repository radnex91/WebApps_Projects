<?php
$title = 'Paiements';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-cash-stack"></i> Paiements</h1>
        <div>
            <a href="<?= BASE_URL ?>/payments/late" class="btn btn-danger me-2"><i class="bi bi-exclamation-triangle"></i> Retards</a>
            <a href="<?= BASE_URL ?>/payments/early" class="btn btn-success me-2"><i class="bi bi-check-circle"></i> Anticipés</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="bi bi-plus-circle"></i> Ajouter
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Lot</th><th>Bailleur</th><th>Agence</th>
                        <th>Montant</th><th>Échéance</th><th>Paiement</th><th>Statut</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['batch_name']) ?></td>
                        <td><?= htmlspecialchars($payment['landlord_name']) ?></td>
                        <td><?= htmlspecialchars($payment['agency_name']) ?></td>
                        <td><?= formatCurrency($payment['amount']) ?></td>
                        <td><?= $payment['due_date'] ?></td>
                        <td><?= $payment['paid_date'] ?? '<span class="text-muted">Non payé</span>' ?></td>
                        <td>
                            <?php
                            $statusLabels = [
                                'EARLY' => ['label' => 'Anticipé', 'class' => 'badge-early'],
                                'ON_TIME' => ['label' => 'À temps', 'class' => 'badge-ontime'],
                                'LATE' => ['label' => 'En retard', 'class' => 'badge-late'],
                                'PENDING' => ['label' => 'En attente', 'class' => 'badge-pending'],
                            ];
                            $status = $statusLabels[$payment['payment_status']] ?? ['label' => 'Inconnu', 'class' => 'badge-pending'];
                            ?>
                            <span class="badge badge-sm <?= $status['class'] ?>"><?= $status['label'] ?></span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/payments/<?= $payment['id'] ?>/edit" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="<?= BASE_URL ?>/payments/<?= $payment['id'] ?>/delete" style="display:inline;">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer la suppression ?')"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal de Paiement -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-bottom: none;">
                <h5 class="modal-title" id="paymentModalLabel"><i class="bi bi-cash-stack me-2"></i> Nouveau Paiement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="modalPaymentForm" method="POST" action="<?= BASE_URL ?>/payments/store">
                <?= Csrf::field() ?>
                <div class="modal-body" style="padding: 25px;">
                    <!-- Étape 1: Agence -->
                    <div class="mb-3" id="step1">
                        <label class="form-label fw-bold"><i class="bi bi-building"></i> Étape 1: Agence *</label>
                        <select name="agency_id" id="modalAgencySelect" class="form-select" required>
                            <option value="">Sélectionner l'agence...</option>
                            <?php foreach ($agencies ?? [] as $agency): ?>
                            <option value="<?= $agency['id'] ?>"><?= htmlspecialchars($agency['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Étape 2: Bailleur (MASQUÉ par défaut) -->
                    <div class="mb-3 d-none" id="step2">
                        <label class="form-label fw-bold"><i class="bi bi-people"></i> Étape 2: Bailleur *</label>
                        <select name="landlord_id" id="modalLandlordSelect" class="form-select" required disabled>
                            <option value="">D'abord sélectionner une agence...</option>
                        </select>
                        <div id="modalLandlordLoading" class="d-none mt-1">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            <small>Chargement...</small>
                        </div>
                    </div>

                    <!-- Étape 3: Lot (MASQUÉ par défaut) -->
                    <div class="mb-3 d-none" id="step3">
                        <label class="form-label fw-bold"><i class="bi bi-grid"></i> Étape 3: Code du lot *</label>
                        <select name="batch_id" id="modalBatchSelect" class="form-select" required disabled>
                            <option value="">D'abord sélectionner un bailleur...</option>
                        </select>
                        <div id="modalBatchLoading" class="d-none mt-1">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            <small>Chargement...</small>
                        </div>
                    </div>

                    <!-- Étape 4: Détails -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="bi bi-currency-exchange"></i> Montant *</label>
                                <div class="input-group">
                                    <input type="number" name="amount" id="modalAmountInput" class="form-control" placeholder="Ex: 150000" required step="1000">
                                    <span class="input-group-text">FCFA</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="bi bi-calendar-event"></i> Date d'échéance *</label>
                                <input type="date" name="due_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-calendar-check"></i> Date de paiement (si déjà payé)</label>
                        <input type="date" name="paid_date" class="form-control">
                        <small class="text-muted">Laissez vide si le paiement n'est pas encore effectué</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: none; padding: 15px 25px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Annuler</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="bi bi-check-lg"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const agencySelect = document.getElementById('modalAgencySelect');
    const landlordSelect = document.getElementById('modalLandlordSelect');
    const batchSelect = document.getElementById('modalBatchSelect');
    const amountInput = document.getElementById('modalAmountInput');
    const landlordLoading = document.getElementById('modalLandlordLoading');
    const batchLoading = document.getElementById('modalBatchLoading');
    const form = document.getElementById('modalPaymentForm');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');

    // Reset modal on close
    document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
        form.reset();
        step2.classList.add('d-none');
        step3.classList.add('d-none');
        landlordSelect.innerHTML = '<option value="">D\'abord sélectionner une agence...</option>';
        landlordSelect.disabled = true;
        batchSelect.innerHTML = '<option value="">D\'abord sélectionner un bailleur...</option>';
        batchSelect.disabled = true;
        amountInput.value = '';
    });

    // When agency changes - SHOW STEP 2
    agencySelect.addEventListener('change', function() {
        const agencyId = this.value;
        
        // Reset steps 2 and 3
        step2.classList.add('d-none');
        step3.classList.add('d-none');
        landlordSelect.innerHTML = '<option value="">Sélectionner le bailleur...</option>';
        landlordSelect.disabled = true;
        batchSelect.innerHTML = '<option value="">D\'abord sélectionner un bailleur...</option>';
        batchSelect.disabled = true;
        amountInput.value = '';

        if (!agencyId) {
            step2.classList.add('d-none');
            return;
        }

        // Show step 2
        step2.classList.remove('d-none');
        landlordLoading.classList.remove('d-none');
        
        console.log('Loading landlords for agency:', agencyId);
        
        fetch('<?= BASE_URL ?>/payments/landlords-by-agency/' + agencyId)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Landlords loaded:', data);
                landlordSelect.innerHTML = '<option value="">Sélectionner le bailleur...</option>';
                if (data.length === 0) {
                    landlordSelect.innerHTML = '<option value="">Aucun bailleur pour cette agence</option>';
                } else {
                    data.forEach(landlord => {
                        const option = document.createElement('option');
                        option.value = landlord.id;
                        option.textContent = landlord.name;
                        landlordSelect.appendChild(option);
                    });
                    landlordSelect.disabled = false;
                    console.log('Landlord select enabled');
                }
            })
            .catch(error => {
                console.error('Error loading landlords:', error);
                landlordSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            })
            .finally(() => landlordLoading.classList.add('d-none'));
    });

    // When landlord changes - SHOW STEP 3
    landlordSelect.addEventListener('change', function() {
        const agencyId = agencySelect.value;
        const landlordId = this.value;
        
        // Reset step 3
        step3.classList.add('d-none');
        batchSelect.innerHTML = '<option value="">Sélectionner le lot...</option>';
        batchSelect.disabled = true;
        amountInput.value = '';

        if (!agencyId || !landlordId) {
            return;
        }

        // Show step 3
        step3.classList.remove('d-none');
        batchLoading.classList.remove('d-none');
        
        console.log('Loading batches for agency:', agencyId, 'landlord:', landlordId);
        
        fetch('<?= BASE_URL ?>/payments/batches-by-agency-landlord?agency_id=' + agencyId + '&landlord_id=' + landlordId)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Batches loaded:', data);
                batchSelect.innerHTML = '<option value="">Sélectionner le lot...</option>';
                if (data.length === 0) {
                    batchSelect.innerHTML = '<option value="">Aucun lot pour ce bailleur</option>';
                } else {
                    data.forEach(batch => {
                        const option = document.createElement('option');
                        option.value = batch.id;
                        option.textContent = batch.name + ' - ' + parseInt(batch.monthly_price).toLocaleString() + ' FCFA';
                        option.dataset.price = batch.monthly_price;
                        batchSelect.appendChild(option);
                    });
                    batchSelect.disabled = false;
                    console.log('Batch select enabled');
                }
            })
            .catch(error => {
                console.error('Error loading batches:', error);
                batchSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            })
            .finally(() => batchLoading.classList.add('d-none'));
    });

    // Auto-fill amount when batch changes
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
