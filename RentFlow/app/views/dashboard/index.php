<?php
$title = 'Dashboard';
ob_start();
?>

<h2><i class="bi bi-speedometer2"></i> Dashboard</h2>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Bailleurs</h6>
                    <h3><?= $stats['total_landlords'] ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-people fs-1 text-primary"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Agences</h6>
                    <h3><?= $stats['total_agencies'] ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-building fs-1 text-success"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Lots</h6>
                    <h3><?= $stats['total_batches'] ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-grid fs-1 text-info"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Total Loyers</h6>
                    <h3><?= formatCurrency($stats['total_rent']) ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-cash-stack fs-1 text-warning"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card danger">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Paiements en retard</h6>
                    <h3 class="text-danger"><?= $stats['late_payments'] ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-exclamation-triangle fs-1 text-danger"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card success">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="text-muted">Paiements anticipes</h6>
                    <h3 class="text-success"><?= $stats['early_payments'] ?></h3>
                </div>
                <div class="align-self-center"><i class="bi bi-check-circle fs-1 text-success"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-bar-chart"></i> Paiements par Agence</h5></div>
            <div class="card-body">
                <canvas id="agencyChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-people-fill"></i> Paiements par Bailleur</h5></div>
            <div class="card-body">
                <canvas id="landlordChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-building"></i> Top Agences</h5></div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Agence</th><th>Paiements</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($stats['by_agency'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['agency']) ?></td>
                            <td><?= $row['total_payments'] ?></td>
                            <td><strong><?= formatCurrency($row['total_amount']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-fill"></i> Top Bailleurs</h5></div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Bailleur</th><th>Paiements</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($stats['by_landlord'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['landlord']) ?></td>
                            <td><?= $row['total_payments'] ?></td>
                            <td><strong><?= formatCurrency($row['total_amount']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="<?= BASE_URL ?>/reports" class="btn btn-primary">
        <i class="bi bi-bar-chart"></i> Voir les rapports detailles
    </a>
    <a href="<?= BASE_URL ?>/payments/export/csv" class="btn btn-success ms-2">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s;
    border-left: 4px solid #0d6efd;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-card.success { border-left-color: #198754; }
.stat-card.info { border-left-color: #0dcaf0; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.danger { border-left-color: #dc3545; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const agencyCtx = document.getElementById('agencyChart').getContext('2d');
const agencyLabels = <?= json_encode(array_column($stats['by_agency'], 'agency')) ?>;
const agencyData = <?= json_encode(array_column($stats['by_agency'], 'total_amount')) ?>;

new Chart(agencyCtx, {
    type: 'bar',
    data: {
        labels: agencyLabels,
        datasets: [{
            label: 'Montant (FCFA)',
            data: agencyData,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});

const landlordCtx = document.getElementById('landlordChart').getContext('2d');
const landlordLabels = <?= json_encode(array_column($stats['by_landlord'], 'landlord')) ?>;
const landlordData = <?= json_encode(array_column($stats['by_landlord'], 'total_amount')) ?>;

new Chart(landlordCtx, {
    type: 'bar',
    data: {
        labels: landlordLabels,
        datasets: [{
            label: 'Montant (FCFA)',
            data: landlordData,
            backgroundColor: 'rgba(118, 75, 162, 0.8)',
            borderColor: 'rgba(118, 75, 162, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
