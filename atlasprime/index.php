<?php
$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

$agenceFilter = Auth::getAgenceFilter();
$isAdmin = $agenceFilter && $agenceFilter['is_admin'];

$recentColis = Database::fetchAll(
    "SELECT * FROM vue_colis ORDER BY created_at DESC LIMIT 10"
);
$colisParStatut = Database::fetchAll(
    "SELECT statut, COUNT(*) AS total FROM colis GROUP BY statut"
);
$colisParMois = Database::fetchAll(
    "SELECT DATE_FORMAT(date_expedition,'%b %Y') AS mois, COUNT(*) AS total, SUM(montant_total) AS ca
     FROM colis WHERE date_expedition >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(date_expedition,'%Y%m') ORDER BY DATE_FORMAT(date_expedition,'%Y%m')"
);

$voyagesActifs = Database::fetchAll(
    "SELECT v.*, ad.nom AS dep, aa.nom AS arr,
     COUNT(c.id) AS nb_colis
     FROM voyages v
     JOIN agences ad ON v.agence_depart_id=ad.id
     JOIN agences aa ON v.agence_arrivee_id=aa.id
     LEFT JOIN colis c ON c.voyage_id=v.id
     WHERE v.statut IN ('planifie','en_cours')
     GROUP BY v.id ORDER BY v.date_depart LIMIT 5"
);

$stats = [
    'total_colis' => Database::fetchOne("SELECT COUNT(*) c FROM colis")['c'] ?? 0,
    'colis_livres' => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut='livre'")['c'] ?? 0,
    'colis_transit' => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut IN ('en_transit','en_livraison')")['c'] ?? 0,
    'total_ca' => Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis")['c'] ?? 0,
    'voyages_actifs' => Database::fetchOne("SELECT COUNT(*) c FROM voyages WHERE statut IN ('planifie','en_cours')")['c'] ?? 0,
    'ca_jour' => Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis WHERE DATE(date_expedition) = CURDATE()")['c'] ?? 0,
    'ca_mois' => Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis WHERE MONTH(date_expedition) = MONTH(CURDATE()) AND YEAR(date_expedition) = YEAR(CURDATE())")['c'] ?? 0,
    'colis_non_payes' => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut_paiement != 'paye'")['c'] ?? 0,
];
?>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card" style="--card-color:#E8500A;--card-color-rgb:232,80,10">
        <div class="stat-icon"><i class="fas fa-box"></i></div>
        <div class="stat-value"><?= number_format($stats['total_colis']) ?></div>
        <div class="stat-label">Total colis enregistrés</div>
    </div>
    <div class="stat-card" style="--card-color:#D97706;--card-color-rgb:217,119,6">
        <div class="stat-icon"><i class="fas fa-truck-moving"></i></div>
        <div class="stat-value"><?= number_format($stats['colis_transit']) ?></div>
        <div class="stat-label">En transit</div>
    </div>
    <div class="stat-card" style="--card-color:#16A34A;--card-color-rgb:22,163,74">
        <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
        <div class="stat-value"><?= number_format($stats['colis_livres']) ?></div>
        <div class="stat-label">Livrés</div>
    </div>
    <div class="stat-card" style="--card-color:#0EA5E9;--card-color-rgb:14,165,233">
        <div class="stat-icon"><i class="fas fa-route"></i></div>
        <div class="stat-value"><?= number_format($stats['voyages_actifs']) ?></div>
        <div class="stat-label">Voyages actifs</div>
    </div>
    <div class="stat-card" style="--card-color:#F5A623;--card-color-rgb:245,166,35">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-value" style="font-size:1.2rem"><?= number_format($stats['ca_jour'],0,',',' ') ?></div>
        <div class="stat-label">CA aujourd'hui (FCFA)</div>
    </div>
    <div class="stat-card" style="--card-color:#7C3AED;--card-color-rgb:124,58,237">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-value" style="font-size:1.2rem"><?= number_format($stats['ca_mois'],0,',',' ') ?></div>
        <div class="stat-label">CA ce mois (FCFA)</div>
    </div>
    <div class="stat-card" style="--card-color:#DC2626;--card-color-rgb:220,38,38">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= number_format($stats['colis_non_payes']) ?></div>
        <div class="stat-label">Paiements en attente</div>
    </div>
</div>

<!-- CHARTS ROW -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Évolution des expéditions</div>
                <div class="card-subtitle">6 derniers mois</div>
            </div>
        </div>
        <canvas id="chartMois" height="100"></canvas>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Répartition par statut</div>
        </div>
        <canvas id="chartStatuts" height="200"></canvas>
    </div>
</div>

<!-- RECENT COLIS + VOYAGES -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Derniers colis</div>
                <div class="card-subtitle">10 plus récents</div>
            </div>
            <a href="pages/colis.php" class="btn btn-ghost btn-sm">Voir tout <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr>
                    <th>N° Colis</th><th>Expéditeur</th><th>Destination</th><th>Statut</th><th>Montant</th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentColis as $c): ?>
                <tr>
                    <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                    <td>
                        <div style="font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                        <div style="font-size:.74rem;color:var(--text-muted)"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                    </td>
                    <td style="font-size:.82rem"><?= htmlspecialchars($c['nom_arrivee']) ?></td>
                    <td><?= statutBadge($c['statut']) ?></td>
                    <td style="font-size:.82rem;font-family:var(--font-mono)"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recentColis): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:40px">Aucun colis enregistré</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Voyages actifs</div>
            <a href="pages/voyages.php" class="btn btn-ghost btn-sm">Voir tout</a>
        </div>
        <?php if ($voyagesActifs): ?>
        <?php foreach ($voyagesActifs as $v): ?>
        <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                <span class="colis-num"><?= $v['numero_voyage'] ?></span>
                <?= statutBadge($v['statut']) ?>
            </div>
            <div style="font-size:.85rem;font-weight:600;margin-bottom:6px;">
                <?= htmlspecialchars($v['dep']) ?> → <?= htmlspecialchars($v['arr']) ?>
            </div>
            <div style="font-size:.78rem;color:var(--text-muted);display:flex;gap:12px;">
                <span><i class="fas fa-truck" style="margin-right:4px"></i><?= htmlspecialchars($v['transporteur']) ?></span>
                <span><i class="fas fa-box" style="margin-right:4px"></i><?= $v['nb_colis'] ?> colis</span>
            </div>
            <div style="font-size:.75rem;color:var(--text-dim);margin-top:6px;">
                Départ: <?= formatDate($v['date_depart'], 'd/m/Y H:i') ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-route"></i><p>Aucun voyage actif</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
// Chart mensuel
<?php
$labels = array_column($colisParMois, 'mois');
$totaux = array_column($colisParMois, 'total');
$ca     = array_column($colisParMois, 'ca');
?>
const ctx1 = document.getElementById('chartMois').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Nombre de colis',
            data: <?= json_encode($totaux) ?>,
            backgroundColor: 'rgba(232,80,10,0.7)',
            borderColor: '#E8500A',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#8892AA', font: { family: 'Sora' } } } },
        scales: {
            x: { ticks: { color: '#8892AA' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#8892AA' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    }
});

// Donut par statut
<?php
$statutLabels = [];
$statutData   = [];
$statutColors = ['enregistre'=>'#0EA5E9','en_transit'=>'#D97706','en_livraison'=>'#7C3AED','livre'=>'#16A34A','retourne'=>'#8892AA','perdu'=>'#DC2626'];
foreach ($colisParStatut as $row) {
    $statutLabels[] = ucfirst(str_replace('_', ' ', $row['statut']));
    $statutData[]   = (int)$row['total'];
}
$colors = array_values($statutColors);
?>
const ctx2 = document.getElementById('chartStatuts').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statutLabels) ?>,
        datasets: [{ data: <?= json_encode($statutData) ?>, backgroundColor: <?= json_encode(array_values($statutColors)) ?>, borderWidth: 0 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { color: '#8892AA', font: { family: 'Sora' }, padding: 12 } } }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
