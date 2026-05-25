<?php
$month = date('Y-m');
$months = ['', 'Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];
$month_name = $months[intval(date('m'))] . ' ' . date('Y');

$stmt = $pdo->query("SELECT COUNT(*) FROM lots");
$total_lots = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(loyer_mensuel), 0) FROM lots");
$loyer_mensuel = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE mois = '$month'");
$paye_mois = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM paiements WHERE mois = '$month' AND statut = 'retard'");
$retard_mois = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM lots WHERE agence_id IS NULL");
$lots_sans_agence = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bailleurs");
$total_bailleurs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM agences");
$total_agences = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COALESCE(SUM(p.montant), 0) as total, 
    SUM(CASE WHEN p.statut = 'avance' THEN p.montant ELSE 0 END) as avance,
    SUM(CASE WHEN p.statut = 'normal' THEN p.montant ELSE 0 END) as normal,
    SUM(CASE WHEN p.statut = 'retard' THEN p.montant ELSE 0 END) as retard
    FROM paiements p WHERE p.mois = '$month'
");
$stats_mois = $stmt->fetch();

$stmt = $pdo->query("
    SELECT b.nom, b.prenom, COUNT(l.id) as nb_lots, COALESCE(SUM(l.loyer_mensuel), 0) as revenus
    FROM bailleurs b LEFT JOIN lots l ON b.id = l.bailleur_id
    GROUP BY b.id ORDER BY revenus DESC LIMIT 5
");
$top_bailleurs = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT a.nom, COUNT(l.id) as nb_lots, COALESCE(SUM(l.loyer_mensuel), 0) as revenus
    FROM agences a LEFT JOIN lots l ON a.id = l.agence_id
    GROUP BY a.id ORDER BY nb_lots DESC LIMIT 5
");
$top_agences = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT p.*, l.adresse, b.nom as nom_bailleur
    FROM paiements p JOIN lots l ON p.lot_id = l.id
    JOIN bailleurs b ON l.bailleur_id = b.id
    ORDER BY p.date_paiement DESC LIMIT 10
");
$derniers_paiements = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT l.adresse, l.ville, p.mois, p.montant, a.nom as nom_agence
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    LEFT JOIN agences a ON l.agence_id = a.id
    WHERE p.statut = 'retard'
    ORDER BY p.date_paiement ASC LIMIT 5
");
$retards = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dashboard - <?php echo getParam('nom_entreprise', 'RentFlow'); ?></h2>
    <span class="text-muted">Mois: <strong><?php echo $month_name; ?></strong></span>
</div>

<?php if ($retard_mois > 0 || $lots_sans_agence > 0): ?>
<div class="row mb-4">
    <?php if ($retard_mois > 0): ?>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Alertes Retards (<?php echo $retard_mois; ?>)</h5>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <?php foreach($retards as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['adresse']); ?></td>
                        <td><?php echo htmlspecialchars($r['mois']); ?></td>
                        <td class="text-end"><?php echo formatPrix($r['montant']); ?></td>
                        <td><span class="badge badge-danger">Retard</span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($lots_sans_agence > 0): ?>
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-building"></i> Lots sans Agence (<?php echo $lots_sans_agence; ?>)</h5>
            </div>
            <div class="card-body">
                <a href="index.php?page=lots" class="btn btn-warning">Gerer les lots</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stats-card bg-primary">
        <div class="card-title">Total Lots</div>
        <div class="card-value"><?php echo $total_lots; ?></div>
        <div class="card-sub"><?php echo $total_bailleurs; ?> bailleurs</div>
    </div>
    <div class="stats-card bg-success">
        <div class="card-title">Revenus Mensuels</div>
        <div class="card-value"><?php echo formatPrix($loyer_mensuel); ?></div>
        <div class="card-sub">Theorique</div>
    </div>
    <div class="stats-card bg-info">
        <div class="card-title">Paiements Rechus</div>
        <div class="card-value"><?php echo formatPrix($stats_mois['total']); ?></div>
        <div class="card-sub"><?php echo intval($stats_mois['avance']) + intval($stats_mois['normal']) + intval($stats_mois['retard']); ?> paiements</div>
    </div>
    <div class="stats-card <?php echo $retard_mois > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
        <div class="card-title">En Retard</div>
        <div class="card-value"><?php echo $retard_mois; ?></div>
        <div class="card-sub"><?php echo formatPrix($stats_mois['retard']); ?></div>
    </div>
    <div class="stats-card bg-warning">
        <div class="card-title">En Avance</div>
        <div class="card-value"><?php echo formatPrix($stats_mois['avance']); ?></div>
    </div>
    <div class="stats-card bg-purple">
        <div class="card-title">Taux Recouvrement</div>
        <div class="card-value"><?php echo $loyer_mensuel > 0 ? round($paye_mois / $loyer_mensuel * 100) : 0; ?>%</div>
        <div class="card-sub">Objectif: 100%</div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5>Top Bailleurs</h5></div>
            <div class="card-body p-0">
                <table class="table">
                    <tbody>
                        <?php foreach($top_bailleurs as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></td>
                            <td class="text-center"><?php echo $b['nb_lots']; ?> lots</td>
                            <td class="text-end"><?php echo formatPrix($b['revenus']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_bailleurs)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Aucun</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5>Top Agences</h5></div>
            <div class="card-body p-0">
                <table class="table">
                    <tbody>
                        <?php foreach($top_agences as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['nom']); ?></td>
                            <td class="text-center"><?php echo $a['nb_lots']; ?></td>
                            <td class="text-end"><?php echo formatPrix($a['revenus']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_agences)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Aucune</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5>Derniers Paiements</h5></div>
            <div class="card-body p-0">
                <table class="table">
                    <tbody>
                        <?php foreach($derniers_paiements as $p): ?>
                        <?php $badge = $p['statut'] == 'avance' ? 'success' : ($p['statut'] == 'retard' ? 'danger' : 'info'); ?>
                        <tr>
                            <td>
                                <div><?php echo htmlspecialchars($p['adresse']); ?></div>
                                <small class="text-muted"><?php echo $p['mois']; ?></small>
                            </td>
                            <td class="text-end"><?php echo formatPrix($p['montant']); ?></td>
                            <td><span class="badge badge-<?php echo $badge; ?>"><?php echo substr($p['statut'], 0, 3); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($derniers_paiements)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Aucun paiement</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5>Repartition Ville</h5></div>
    <div class="card-body">
        <?php
        $stmt = $pdo->query("SELECT ville, COUNT(*) as nb, SUM(loyer_mensuel) as total FROM lots WHERE agence_id IS NOT NULL GROUP BY ville ORDER BY nb DESC");
        while($row = $stmt->fetch()): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span><?php echo htmlspecialchars($row['ville'] ?: 'Non defini'); ?></span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-primary"><?php echo $row['nb']; ?> lots</span>
                <span class="text-success"><?php echo formatPrix($row['total']); ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>