<?php
$title = 'Rapport Agence: ' . htmlspecialchars($agency['name']);
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building"></i> Rapport: <?= htmlspecialchars($agency['name']) ?></h2>
    <a href="<?= BASE_URL ?>/reports" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="card-body">
                <h5 class="card-title">Contact</h5>
                <p class="card-text"><?= htmlspecialchars($agency['contact']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="card-body">
                <h5 class="card-title">Lots</h5>
                <p class="card-text display-6"><?= $agency['batches_count'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="card-body">
                <h5 class="card-title">Annee</h5>
                <form method="GET" class="d-flex gap-2">
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="chart-container mt-4">
    <h5><i class="bi bi-graph-up"></i> Paiements mensuels (<?= $year ?>)</h5>
    <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="chart-container">
            <h5><i class="bi bi-table"></i> Details par mois</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $monthNames = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $amountsArr = json_decode($amounts, true);
                    for ($i = 0; $i < 12; $i++):
                        if ($amountsArr[$i] > 0):
                    ?>
                    <tr>
                        <td><?= $monthNames[$i] ?></td>
                        <td><strong><?= number_format($amountsArr[$i], 0, ',', ' ') ?> FCFA</strong></td>
                    </tr>
                    <?php endif; endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="chart-container">
            <h5><i class="bi bi-info-circle"></i> Information</h5>
            <p>Ce rapport presente les paiements mensuels pour l'agence <strong><?= htmlspecialchars($agency['name']) ?></strong> pour l'annee <?= $year ?>.</p>
            <p>Utilisez le selecteur d'annee pour changer de periode.</p>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s;
    overflow: hidden;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-card .card-body { padding: 20px; }
.chart-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const months = <?= $months ?>;
const amounts = <?= $amounts ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{
            label: 'Montant (FCFA)',
            data: amounts,
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
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
