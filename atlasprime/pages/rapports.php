<?php
$pageTitle = 'Rapports & Statistiques';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('rapports_voir');

$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin']   ?? date('Y-m-t');

// Stats de la période
$colisTotal   = Database::fetchOne("SELECT COUNT(*) c, COALESCE(SUM(montant_total),0) ca, COALESCE(SUM(poids),0) pds FROM colis WHERE DATE(date_expedition) BETWEEN ? AND ?", [$dateDebut, $dateFin]);
$colisLivres  = Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut='livre' AND DATE(date_expedition) BETWEEN ? AND ?", [$dateDebut, $dateFin])['c'];
$colisPayes   = Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis WHERE statut_paiement='paye' AND DATE(date_expedition) BETWEEN ? AND ?", [$dateDebut, $dateFin])['c'];

// Par jour (période)
$parJour = Database::fetchAll(
    "SELECT DATE(date_expedition) AS jour, COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS ca
     FROM colis WHERE DATE(date_expedition) BETWEEN ? AND ?
     GROUP BY DATE(date_expedition) ORDER BY jour",
    [$dateDebut, $dateFin]
);

// Par agence
$parAgence = Database::fetchAll(
    "SELECT ad.nom, COUNT(*) AS nb_depart, COALESCE(SUM(c.montant_total),0) AS ca
     FROM colis c JOIN agences ad ON c.agence_depart_id=ad.id
     WHERE DATE(c.date_expedition) BETWEEN ? AND ?
     GROUP BY ad.id ORDER BY nb_depart DESC",
    [$dateDebut, $dateFin]
);

// Par mode paiement
$parPaiement = Database::fetchAll(
    "SELECT mode_paiement, COUNT(*) nb, COALESCE(SUM(montant_total),0) ca
     FROM colis WHERE DATE(date_expedition) BETWEEN ? AND ?
     GROUP BY mode_paiement",
    [$dateDebut, $dateFin]
);

// Top destinataires
$topDestinataires = Database::fetchAll(
    "SELECT destinataire_nom, destinataire_telephone, COUNT(*) nb, COALESCE(SUM(montant_total),0) ca
     FROM colis WHERE DATE(date_expedition) BETWEEN ? AND ?
     GROUP BY destinataire_nom, destinataire_telephone
     ORDER BY nb DESC LIMIT 10",
    [$dateDebut, $dateFin]
);

// Top expéditeurs
$topExpediteurs = Database::fetchAll(
    "SELECT expediteur_nom, expediteur_telephone, COUNT(*) nb, COALESCE(SUM(montant_total),0) ca
     FROM colis WHERE DATE(date_expedition) BETWEEN ? AND ?
     GROUP BY expediteur_nom, expediteur_telephone
     ORDER BY nb DESC LIMIT 10",
    [$dateDebut, $dateFin]
);

// Par opérateur
$parOperateur = Database::fetchAll(
    "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, COUNT(c.id) AS nb, COALESCE(SUM(c.montant_total),0) AS ca
     FROM colis c JOIN utilisateurs u ON c.cree_par=u.id
     WHERE DATE(c.date_expedition) BETWEEN ? AND ?
     GROUP BY u.id ORDER BY nb DESC",
    [$dateDebut, $dateFin]
);
?>

<!-- FILTER BAR -->
<form method="get" class="filter-bar" style="margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <label style="font-size:.8rem;color:var(--text-muted)">Période :</label>
        <input type="date" name="date_debut" class="form-control" value="<?= $dateDebut ?>" style="width:auto">
        <span style="color:var(--text-muted)">→</span>
        <input type="date" name="date_fin" class="form-control" value="<?= $dateFin ?>" style="width:auto">
        <button type="submit" class="btn btn-primary"><i class="fas fa-chart-bar"></i> Générer</button>
        <a href="rapports.php" class="btn btn-ghost">Ce mois</a>
    </div>
    <div style="margin-left:auto">
        <button type="button" class="btn btn-secondary btn-print"><i class="fas fa-print"></i> Imprimer</button>
    </div>
</form>

<!-- KPI -->
<div class="stat-grid" style="margin-bottom:24px">
    <div class="stat-card" style="--card-color:#E8500A">
        <div class="stat-icon"><i class="fas fa-box"></i></div>
        <div class="stat-value"><?= number_format($colisTotal['c']) ?></div>
        <div class="stat-label">Colis expédiés</div>
    </div>
    <div class="stat-card" style="--card-color:#16A34A">
        <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
        <div class="stat-value"><?= number_format($colisLivres) ?></div>
        <div class="stat-label">Livrés</div>
    </div>
    <div class="stat-card" style="--card-color:#F5A623">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-value" style="font-size:1.3rem"><?= number_format($colisTotal['ca'],0,',',' ') ?></div>
        <div class="stat-label">CA total (FCFA)</div>
    </div>
    <div class="stat-card" style="--card-color:#0EA5E9">
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-value" style="font-size:1.3rem"><?= number_format($colisPayes,0,',',' ') ?></div>
        <div class="stat-label">CA encaissé (FCFA)</div>
    </div>
    <div class="stat-card" style="--card-color:#7C3AED">
        <div class="stat-icon"><i class="fas fa-weight-hanging"></i></div>
        <div class="stat-value"><?= number_format($colisTotal['pds'],1) ?></div>
        <div class="stat-label">Poids total (kg)</div>
    </div>
</div>

<!-- GRAPHIQUES -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Évolution quotidienne</div>
            <div class="card-subtitle"><?= date('d/m/Y', strtotime($dateDebut)) ?> — <?= date('d/m/Y', strtotime($dateFin)) ?></div>
        </div>
        <canvas id="chartJour" height="110"></canvas>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Mode de paiement</div></div>
        <canvas id="chartPaie" height="200"></canvas>
    </div>
</div>

<!-- TABLES DÉTAILLÉES -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
    <!-- Par agence -->
    <div class="card">
        <div class="card-header"><div class="card-title">Performance par agence</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Agence</th><th>Colis</th><th>CA (FCFA)</th></tr></thead>
                <tbody>
                <?php foreach ($parAgence as $a): ?>
                <tr>
                    <td style="font-size:.82rem"><?= htmlspecialchars($a['nom']) ?></td>
                    <td><span class="badge badge-info"><?= $a['nb_depart'] ?></span></td>
                    <td style="font-family:var(--font-mono);font-size:.8rem"><?= number_format($a['ca'],0,',',' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Par opérateur -->
    <div class="card">
        <div class="card-header"><div class="card-title">Performance par opérateur</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Opérateur</th><th>Colis traités</th><th>CA généré</th></tr></thead>
                <tbody>
                <?php foreach ($parOperateur as $o): ?>
                <tr>
                    <td style="font-size:.85rem;font-weight:500"><?= htmlspecialchars($o['nom']) ?></td>
                    <td><span class="badge badge-primary"><?= $o['nb'] ?></span></td>
                    <td style="font-family:var(--font-mono);font-size:.8rem"><?= number_format($o['ca'],0,',',' ') ?> F</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <!-- Top expéditeurs -->
    <div class="card">
        <div class="card-header"><div class="card-title">Top expéditeurs</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>#</th><th>Expéditeur</th><th>Colis</th><th>CA</th></tr></thead>
                <tbody>
                <?php foreach ($topExpediteurs as $i => $e): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8rem"><?= $i+1 ?></td>
                    <td>
                        <div style="font-size:.85rem;font-weight:500"><?= htmlspecialchars($e['expediteur_nom']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($e['expediteur_telephone']) ?></div>
                    </td>
                    <td><span class="badge badge-info"><?= $e['nb'] ?></span></td>
                    <td style="font-family:var(--font-mono);font-size:.78rem"><?= number_format($e['ca'],0,',',' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top destinataires -->
    <div class="card">
        <div class="card-header"><div class="card-title">Top destinataires</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>#</th><th>Destinataire</th><th>Colis</th><th>CA</th></tr></thead>
                <tbody>
                <?php foreach ($topDestinataires as $i => $d): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.8rem"><?= $i+1 ?></td>
                    <td>
                        <div style="font-size:.85rem;font-weight:500"><?= htmlspecialchars($d['destinataire_nom']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($d['destinataire_telephone']) ?></div>
                    </td>
                    <td><span class="badge badge-success"><?= $d['nb'] ?></span></td>
                    <td style="font-family:var(--font-mono);font-size:.78rem"><?= number_format($d['ca'],0,',',' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
<?php
$jourLabels = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $parJour);
$jourNb     = array_column($parJour, 'nb');
$jourCa     = array_column($parJour, 'ca');
?>
new Chart(document.getElementById('chartJour'), {
    data: {
        labels: <?= json_encode($jourLabels) ?>,
        datasets: [
            { type:'bar', label:'Colis', data: <?= json_encode($jourNb) ?>, backgroundColor:'rgba(232,80,10,0.6)', borderRadius:3, yAxisID:'y' },
            { type:'line', label:'CA (FCFA)', data: <?= json_encode($jourCa) ?>, borderColor:'#F5A623', backgroundColor:'rgba(245,166,35,0.1)', tension:0.4, yAxisID:'y2' }
        ]
    },
    options: {
        responsive:true,
        plugins:{legend:{labels:{color:'#8892AA',font:{family:'Sora'}}}},
        scales:{
            x:{ticks:{color:'#8892AA'},grid:{color:'rgba(255,255,255,0.04)'}},
            y:{ticks:{color:'#8892AA'},grid:{color:'rgba(255,255,255,0.04)'},position:'left'},
            y2:{ticks:{color:'#F5A623'},position:'right',grid:{display:false}}
        }
    }
});

new Chart(document.getElementById('chartPaie'), {
    type:'doughnut',
    data:{
        labels: <?= json_encode(array_map(fn($r) => ucfirst(str_replace('_',' ',$r['mode_paiement'])), $parPaiement)) ?>,
        datasets:[{
            data: <?= json_encode(array_column($parPaiement,'nb')) ?>,
            backgroundColor:['#E8500A','#0EA5E9','#16A34A','#7C3AED'],
            borderWidth:0
        }]
    },
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#8892AA',font:{family:'Sora'},padding:10}}}}
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
