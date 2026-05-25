<?php
$message = '';

$stats = [];

$year = $_GET['year'] ?? date('Y');
$agence_id = $_GET['agence_id'] ?? '';
$bailleur_id = $_GET['bailleur_id'] ?? '';

$stmt = $pdo->query("SELECT id, nom FROM agences ORDER BY nom");
$agences = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom, prenom FROM bailleurs ORDER BY nom");
$bailleurs = $stmt->fetchAll();

$where = "WHERE YEAR(p.date_paiement) = ?";
$params = [$year];
if ($agence_id) {
    $where .= " AND l.agence_id = ?";
    $params[] = $agence_id;
}
if ($bailleur_id) {
    $where .= " AND l.bailleur_id = ?";
    $params[] = $bailleur_id;
}

$sql = "SELECT 
    COUNT(*) as total_paiements,
    SUM(p.montant) as montant_total,
    SUM(CASE WHEN p.statut = 'avance' THEN p.montant ELSE 0 END) as montant_avance,
    SUM(CASE WHEN p.statut = 'normal' THEN p.montant ELSE 0 END) as montant_normal,
    SUM(CASE WHEN p.statut = 'retard' THEN p.montant ELSE 0 END) as montant_retard,
    COUNT(CASE WHEN p.statut = 'avance' THEN 1 END) as nb_avance,
    COUNT(CASE WHEN p.statut = 'normal' THEN 1 END) as nb_normal,
    COUNT(CASE WHEN p.statut = 'retard' THEN 1 END) as nb_retard
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    $where";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stats['global'] = $stmt->fetch();

$sql = "SELECT MONTH(p.date_paiement) as mois, 
    SUM(p.montant) as total,
    COUNT(*) as nb
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    $where
    GROUP BY MONTH(p.date_paiement)
    ORDER BY mois";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stats['mensuel'] = $stmt->fetchAll();

$sql = "SELECT a.nom as nom_agence, 
    COUNT(p.id) as nb_paiements, 
    SUM(p.montant) as total,
    SUM(CASE WHEN p.statut = 'retard' THEN p.montant ELSE 0 END) as total_retard,
    SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as nb_retard
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    JOIN agences a ON l.agence_id = a.id 
    $where
    GROUP BY a.id
    ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stats['par_agence'] = $stmt->fetchAll();

$sql = "SELECT b.nom as nom_bailleur, b.prenom as prenom_bailleur,
    COUNT(p.id) as nb_paiements, 
    SUM(p.montant) as total,
    SUM(CASE WHEN p.statut = 'retard' THEN p.montant ELSE 0 END) as total_retard,
    SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as nb_retard
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    JOIN bailleurs b ON l.bailleur_id = b.id 
    $where
    GROUP BY b.id
    ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stats['par_bailleur'] = $stmt->fetchAll();

$sql = "SELECT l.code, l.adresse, l.ville,
    COUNT(p.id) as nb_paiements, 
    SUM(p.montant) as total,
    SUM(CASE WHEN p.statut = 'retard' THEN p.montant ELSE 0 END) as total_retard,
    SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as nb_retard
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    $where
    GROUP BY l.id
    ORDER BY total DESC
    LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stats['par_lot'] = $stmt->fetchAll();

$s = $stats['global'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Statistiques</h2>
    <form method="get" class="d-flex gap-2">
        <input type="hidden" name="page" value="statistiques">
        <select name="year" class="form-select">
            <?php for($y = date('Y'); $y >= date('Y')-5; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <select name="agence_id" class="form-select" style="width:auto;">
            <option value="">Toutes les agences</option>
            <?php foreach ($agences as $a): ?>
            <option value="<?php echo $a['id']; ?>" <?php echo $agence_id == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nom']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="bailleur_id" class="form-select" style="width:auto;">
            <option value="">Tous les bailleurs</option>
            <?php foreach ($bailleurs as $b): ?>
            <option value="<?php echo $b['id']; ?>" <?php echo $bailleur_id == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
    </form>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($s['montant_total'] ?? 0); ?></h3>
                <p class="mb-0">Total des paiements</p>
                <small><?php echo $s['total_paiements'] ?? 0; ?> paiements</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($s['montant_avance'] ?? 0); ?></h3>
                <p class="mb-0">En avance</p>
                <small><?php echo $s['nb_avance'] ?? 0; ?> paiements</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($s['montant_normal'] ?? 0); ?></h3>
                <p class="mb-0">Normal</p>
                <small><?php echo $s['nb_normal'] ?? 0; ?> paiements</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($s['montant_retard'] ?? 0); ?></h3>
                <p class="mb-0">En retard</p>
                <small><?php echo $s['nb_retard'] ?? 0; ?> paiements</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Par Agence</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Agence</th>
                            <th>Paiements</th>
                            <th>Total</th>
                            <th>Retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['par_agence'] as $a): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($a['nom_agence']); ?></strong></td>
                            <td><?php echo $a['nb_paiements']; ?></td>
                            <td><?php echo formatPrix($a['total']); ?></td>
                            <td>
                                <?php if ($a['nb_retard'] > 0): ?>
                                <span class="badge badge-warning"><?php echo formatPrix($a['total_retard']); ?></span>
                                <?php else: ?>
                                <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats['par_agence'])): ?>
                        <tr><td colspan="4" class="text-center text-muted">Aucune donnée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Par Bailleur</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Bailleur</th>
                            <th>Paiements</th>
                            <th>Total</th>
                            <th>Retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['par_bailleur'] as $b): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($b['nom_bailleur'] . ' ' . $b['prenom_bailleur']); ?></strong></td>
                            <td><?php echo $b['nb_paiements']; ?></td>
                            <td><?php echo formatPrix($b['total']); ?></td>
                            <td>
                                <?php if ($b['nb_retard'] > 0): ?>
                                <span class="badge badge-warning"><?php echo formatPrix($b['total_retard']); ?></span>
                                <?php else: ?>
                                <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats['par_bailleur'])): ?>
                        <tr><td colspan="4" class="text-center text-muted">Aucune donnée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Évolution mensuelle</h5></div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Nombre</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                foreach ($stats['mensuel'] as $m): ?>
                <tr>
                    <td><strong><?php echo $months[$m['mois']]; ?></strong></td>
                    <td><?php echo $m['nb']; ?></td>
                    <td><?php echo formatPrix($m['total']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($stats['mensuel'])): ?>
                <tr><td colspan="3" class="text-center text-muted">Aucune donnée</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Top 20 Lots</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Adresse</th>
                    <th>Paiements</th>
                    <th>Total</th>
                    <th>Retard</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['par_lot'] as $l): ?>
                <tr>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($l['code']); ?></span></td>
                    <td><?php echo htmlspecialchars($l['adresse'] . ', ' . $l['ville']); ?></td>
                    <td><?php echo $l['nb_paiements']; ?></td>
                    <td><?php echo formatPrix($l['total']); ?></td>
                    <td>
                        <?php if ($l['nb_retard'] > 0): ?>
                        <span class="badge badge-warning"><?php echo formatPrix($l['total_retard']); ?></span>
                        <?php else: ?>
                        <span class="text-success">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>