<?php
$title = 'Rapports & Statistiques';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-bar-chart"></i> Rapports & Statistiques</h2>
    <div>
        <form method="GET" class="d-flex gap-2">
            <select name="year" class="form-select" onchange="this.form.submit()">
                <?php foreach ($yearlyStats as $ys): ?>
                    <option value="<?= $ys['year'] ?>" <?= $ys['year'] == $year ? 'selected' : '' ?>>
                        <?= $ys['year'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="<?= BASE_URL ?>/reports/export?format=csv" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
        </form>
    </div>
</div>

<!-- Monthly Chart -->
<div class="chart-container">
    <h5><i class="bi bi-graph-up"></i> Paiements mensuels (<?= $year ?>)</h5>
    <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="chart-container">
            <h5><i class="bi bi-pie-chart"></i> Repartition par statut</h5>
            <canvas id="statusChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="chart-container">
            <h5><i class="bi bi-calendar-range"></i> Comparaison annuelle</h5>
            <canvas id="yearlyChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- Top Landlords -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="table-container">
            <h5><i class="bi bi-trophy"></i> Top 10 Bailleurs (CA)</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Bailleur</th>
                        <th>Contact</th>
                        <th>Paiements</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topLandlords as $index => $landlord): ?>
                    <tr>
                        <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'warning' : 'primary')) ?>"><?= $index + 1 ?></span></td>
                        <td><?= htmlspecialchars($landlord['landlord']) ?></td>
                        <td><?= htmlspecialchars($landlord['contact']) ?></td>
                        <td><?= $landlord['payments_count'] ?></td>
                        <td><strong><?= number_format($landlord['total_amount'], 0, ',', ' ') ?> FCFA</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Agencies -->
    <div class="col-md-6">
        <div class="table-container">
            <h5><i class="bi bi-building"></i> Top 10 Agences (CA)</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Agence</th>
                        <th>Contact</th>
                        <th>Paiements</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topAgencies as $index => $agency): ?>
                    <tr>
                        <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'warning' : 'primary')) ?>"><?= $index + 1 ?></span></td>
                        <td><?= htmlspecialchars($agency['agency']) ?></td>
                        <td><?= htmlspecialchars($agency['contact']) ?></td>
                        <td><?= $agency['payments_count'] ?></td>
                        <td><strong><?= number_format($agency['total_amount'], 0, ',', ' ') ?> FCFA</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Agency Stats -->
<div class="table-container mt-4">
    <h5><i class="bi bi-table"></i> Statistiques par Agence</h5>
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Agence</th>
                <th>Paiements</th>
                <th>Total</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statsByAgency as $stat): ?>
            <tr>
                <td><strong><?= htmlspecialchars($stat['agency']) ?></strong></td>
                <td><?= $stat['total_payments'] ?></td>
                <td><?= number_format($stat['total_amount'], 0, ',', ' ') ?> FCFA</td>
                <td>
                    <a href="<?= BASE_URL ?>/reports/agency/<?= $stat['id'] ?>" class="btn btn-sm btn-info">
                        <i class="bi bi-bar-chart"></i> Details
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Landlord Stats -->
<div class="table-container mt-4">
    <h5><i class="bi bi-people"></i> Statistiques par Bailleur</h5>
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Bailleur</th>
                <th>Paiements</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statsByLandlord as $stat): ?>
            <tr>
                <td><strong><?= htmlspecialchars($stat['landlord']) ?></strong></td>
                <td><?= $stat['total_payments'] ?></td>
                <td><?= number_format($stat['total_amount'], 0, ',', ' ') ?> FCFA</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.chart-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.table-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?= $months ?>,
        datasets: [{
            label: 'Montant (FCFA)',
            data: <?= $amounts ?>,
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

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?= json_encode($statusStats) ?>;
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(s => s.status_label || s.status),
        datasets: [{
            data: statusData.map(s => s.count),
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(0, 123, 255, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)'
            ]
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Yearly Chart
const yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
const yearlyData = <?= json_encode($yearlyStats) ?>;
new Chart(yearlyCtx, {
    type: 'line',
    data: {
        labels: yearlyData.map(y => y.year),
        datasets: [{
            label: 'Total (FCFA)',
            data: yearlyData.map(y => y.total),
            borderColor: 'rgba(118, 75, 162, 1)',
            backgroundColor: 'rgba(118, 75, 162, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Nb Paiements',
            data: yearlyData.map(y => y.count),
            borderColor: 'rgba(102, 126, 234, 1)',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            fill: true,
            tension: 0.4
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
