<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('dashboard.view');

$db = getDB();

// Stats
$stats = [];
$stmt = $db->query("SELECT COUNT(*) FROM vehicules WHERE active = 1");
$stats['vehicules'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM agences WHERE active = 1");
$stats['agences'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM chauffeurs WHERE active = 1");
$stats['chauffeurs'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM bulletins WHERE MONTH(date_voyage) = MONTH(CURDATE()) AND YEAR(date_voyage) = YEAR(CURDATE())");
$stats['bulletins_mois'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COALESCE(SUM(total_passagers), 0) FROM bulletins WHERE MONTH(date_voyage) = MONTH(CURDATE()) AND YEAR(date_voyage) = YEAR(CURDATE())");
$stats['passagers_mois'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COALESCE(AVG(taux_remplissage), 0) FROM bulletins WHERE MONTH(date_voyage) = MONTH(CURDATE()) AND YEAR(date_voyage) = YEAR(CURDATE())");
$stats['taux_moyen'] = round($stmt->fetchColumn(), 1);

$stmt = $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = 'debit' THEN montant ELSE 0 END), 0) FROM caisse WHERE MONTH(date_operation) = MONTH(CURDATE()) AND YEAR(date_operation) = YEAR(CURDATE())");
$stats['solde_mois'] = $stmt->fetchColumn();

// Recent bulletins
$bulletins = $db->query("
    SELECT b.*, v.immatriculation, a1.nom as agence_dep, a2.nom as agence_dest, c.nom as chauffeur_nom
    FROM bulletins b
    LEFT JOIN vehicules v ON b.vehicule_id = v.id
    LEFT JOIN agences a1 ON b.agence_depart_id = a1.id
    LEFT JOIN agences a2 ON b.agence_destination_id = a2.id
    LEFT JOIN chauffeurs c ON b.chauffeur_id = c.id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

// Chart data: daily passengers this month
$chartData = $db->query("
    SELECT DATE(date_voyage) as jour, SUM(total_passagers) as total
    FROM bulletins
    WHERE MONTH(date_voyage) = MONTH(CURDATE()) AND YEAR(date_voyage) = YEAR(CURDATE())
    GROUP BY DATE(date_voyage)
    ORDER BY jour
")->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach ($chartData as $d) {
    $chartLabels[] = formatDate($d['jour'], 'd/m');
    $chartValues[] = (int)$d['total'];
}
$chartLabelsJson = json_encode($chartLabels);
$chartValuesJson = json_encode($chartValues);
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue animate-bounce">🚌</div>
        <div class="stat-content">
            <h3><?= $stats['vehicules'] ?></h3>
            <p>Véhicules actifs</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green animate-pulse">🏢</div>
        <div class="stat-content">
            <h3><?= $stats['agences'] ?></h3>
            <p>Agences</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple animate-bounce">👨‍✈️</div>
        <div class="stat-content">
            <h3><?= $stats['chauffeurs'] ?></h3>
            <p>Chauffeurs</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow animate-pulse">📋</div>
        <div class="stat-content">
            <h3><?= $stats['bulletins_mois'] ?></h3>
            <p>Bulletins (ce mois)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue animate-bounce">👥</div>
        <div class="stat-content">
            <h3><?= $stats['passagers_mois'] ?></h3>
            <p>Passagers (ce mois)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green animate-pulse">📊</div>
        <div class="stat-content">
            <h3><?= $stats['taux_moyen'] ?>%</h3>
            <p>Taux remplissage moyen</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow animate-bounce">💰</div>
        <div class="stat-content">
            <h3><?= number_format($stats['solde_mois'], 0, ',', ' ') ?></h3>
            <p>Solde caisse (mois) FCFA</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">📈 Trafic passagers — <?= date('F Y') ?></h3>
    </div>
    <div style="height: 280px; position: relative;">
        <canvas id="chartPassagers"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">📋 Derniers bulletins</h3>
        <a href="index.php?page=bulletins" class="btn btn-sm btn-primary">Voir tout</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Véhicule</th>
                    <th>Chauffeur</th>
                    <th>Départ</th>
                    <th>Destination</th>
                    <th>Passagers</th>
                    <th>Taux</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bulletins)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:40px;">Aucun bulletin pour le moment. 🚀</td></tr>
                <?php else: foreach ($bulletins as $b): ?>
                <tr>
                    <td><?= formatDate($b['date_voyage']) ?></td>
                    <td><strong><?= e($b['immatriculation']) ?></strong></td>
                    <td><?= e($b['chauffeur_nom']) ?></td>
                    <td><?= e($b['agence_dep']) ?></td>
                    <td><?= e($b['agence_dest']) ?></td>
                    <td><strong><?= (int)$b['total_passagers'] ?></strong></td>
                    <td><span class="badge badge-<?= ($b['taux_remplissage'] ?? 0) > 75 ? 'success' : (($b['taux_remplissage'] ?? 0) > 50 ? 'warning' : 'danger') ?>"><?= $b['taux_remplissage'] ?>%</span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartPassagers').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= $chartLabelsJson ?>,
        datasets: [{
            label: 'Passagers',
            data: <?= $chartValuesJson ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { stepSize: 1 }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>
