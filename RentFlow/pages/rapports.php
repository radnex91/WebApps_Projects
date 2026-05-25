<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'generate') {
        $_SESSION['rapport_filters'] = [
            'type' => $_POST['type'],
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
            'agence_id' => $_POST['agence_id'],
            'bailleur_id' => $_POST['bailleur_id'],
            'format' => $_POST['format'] ?? 'html'
        ];
    }
}

$filters = $_SESSION['rapport_filters'] ?? [];
$type = $filters['type'] ?? 'paiements';
$date_debut = $filters['date_debut'] ?? date('Y-01-01');
$date_fin = $filters['date_fin'] ?? date('Y-12-31');
$agence_id = $filters['agence_id'] ?? '';
$bailleur_id = $filters['bailleur_id'] ?? '';
$format = $filters['format'] ?? 'html';

$stmt = $pdo->query("SELECT id, nom FROM agences ORDER BY nom");
$agences = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom, prenom FROM bailleurs ORDER BY nom");
$bailleurs = $stmt->fetchAll();

$where = "WHERE p.date_paiement BETWEEN ? AND ?";
$params = [$date_debut, $date_fin];

if ($agence_id) {
    $where .= " AND l.agence_id = ?";
    $params[] = $agence_id;
}
if ($bailleur_id) {
    $where .= " AND l.bailleur_id = ?";
    $params[] = $bailleur_id;
}

$sql_base = "SELECT p.*, l.adresse, l.ville, l.loyer_mensuel, b.nom as nom_bailleur, b.prenom as prenom_bailleur, a.nom as nom_agence 
    FROM paiements p 
    JOIN lots l ON p.lot_id = l.id 
    JOIN bailleurs b ON l.bailleur_id = b.id 
    LEFT JOIN agences a ON l.agence_id = a.id";

$results = [];

if ($type === 'paiements' || $type === 'all') {
    $sql = $sql_base . " " . $where . " ORDER BY p.date_paiement DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results['paiements'] = $stmt->fetchAll();
}

if ($type === 'avance' || $type === 'all') {
    $sql = $sql_base . " AND p.statut = 'avance' " . $where . " ORDER BY p.date_paiement DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results['avance'] = $stmt->fetchAll();
}

if ($type === 'retard' || $type === 'all') {
    $sql = $sql_base . " AND p.statut = 'retard' " . $where . " ORDER BY p.date_paiement DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results['retard'] = $stmt->fetchAll();
}

if ($type === 'agence' || $type === 'all') {
    $sql = "SELECT a.nom as nom_agence, COUNT(p.id) as nb_paiements, SUM(p.montant) as total 
        FROM paiements p 
        JOIN lots l ON p.lot_id = l.id 
        JOIN agences a ON l.agence_id = a.id 
        WHERE p.date_paiement BETWEEN ? AND ?";
    $params_ag = [$date_debut, $date_fin];
    if ($bailleur_id) {
        $sql .= " AND l.bailleur_id = ?";
        $params_ag[] = $bailleur_id;
    }
    $sql .= " GROUP BY a.id ORDER BY total DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_ag);
    $results['par_agence'] = $stmt->fetchAll();
}

if ($type === 'bailleur' || $type === 'all') {
    $sql = "SELECT b.nom as nom_bailleur, b.prenom as prenom_bailleur, COUNT(p.id) as nb_paiements, SUM(p.montant) as total 
        FROM paiements p 
        JOIN lots l ON p.lot_id = l.id 
        JOIN bailleurs b ON l.bailleur_id = b.id 
        WHERE p.date_paiement BETWEEN ? AND ?";
    $params_b = [$date_debut, $date_fin];
    if ($agence_id) {
        $sql .= " AND l.agence_id = ?";
        $params_b[] = $agence_id;
    }
    $sql .= " GROUP BY b.id ORDER BY total DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_b);
    $results['par_bailleur'] = $stmt->fetchAll();
}

if ($type === 'stats' || $type === 'all') {
    $sql = "SELECT 
        COUNT(*) as total_paiements,
        SUM(montant) as montant_total,
        SUM(CASE WHEN statut = 'avance' THEN montant ELSE 0 END) as montant_avance,
        SUM(CASE WHEN statut = 'normal' THEN montant ELSE 0 END) as montant_normal,
        SUM(CASE WHEN statut = 'retard' THEN montant ELSE 0 END) as montant_retard,
        COUNT(CASE WHEN statut = 'avance' THEN 1 END) as nb_avance,
        COUNT(CASE WHEN statut = 'normal' THEN 1 END) as nb_normal,
        COUNT(CASE WHEN statut = 'retard' THEN 1 END) as nb_retard
        FROM paiements p 
        JOIN lots l ON p.lot_id = l.id 
        WHERE p.date_paiement BETWEEN ? AND ?";
    $params_st = [$date_debut, $date_fin];
    if ($agence_id) {
        $sql .= " AND l.agence_id = ?";
        $params_st[] = $agence_id;
    }
    if ($bailleur_id) {
        $sql .= " AND l.bailleur_id = ?";
        $params_st[] = $bailleur_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_st);
    $results['stats'] = $stmt->fetch();
}

$stats = $results['stats'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Rapports & Facturation</h2>
    <?php if (!empty($results)): ?>
    <a href="?page=rapports&export=<?php echo $format; ?>" class="btn btn-success" target="_blank">
        <i class="bi bi-download"></i> Exporter
    </a>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="generate">
            <div class="col-md-3">
                <label class="form-label">Type de rapport</label>
                <select name="type" class="form-select">
                    <option value="paiements" <?php echo $type === 'paiements' ? 'selected' : ''; ?>>Tous les paiements</option>
                    <option value="avance" <?php echo $type === 'avance' ? 'selected' : ''; ?>>Paiements en avance</option>
                    <option value="retard" <?php echo $type === 'retard' ? 'selected' : ''; ?>>Paiements en retard</option>
                    <option value="agence" <?php echo $type === 'agence' ? 'selected' : ''; ?>>Par agence</option>
                    <option value="bailleur" <?php echo $type === 'bailleur' ? 'selected' : ''; ?>>Par bailleur</option>
                    <option value="stats" <?php echo $type === 'stats' ? 'selected' : ''; ?>>Statistiques</option>
                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>Rapport complet</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date début</label>
                <input type="date" name="date_debut" class="form-control" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date fin</label>
                <input type="date" name="date_fin" class="form-control" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Agence</label>
                <select name="agence_id" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach ($agences as $a): ?>
                    <option value="<?php echo $a['id']; ?>" <?php echo $agence_id == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Bailleur</label>
                <select name="bailleur_id" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($bailleurs as $b): ?>
                    <option value="<?php echo $b['id']; ?>" <?php echo $bailleur_id == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['nom'] . ' ' . $b['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<?php if ($stats): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($stats['montant_total'] ?? 0); ?></h3>
                <p class="mb-0">Total général</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($stats['montant_avance'] ?? 0); ?></h3>
                <p class="mb-0">En avance (<?php echo $stats['nb_avance'] ?? 0; ?>)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($stats['montant_normal'] ?? 0); ?></h3>
                <p class="mb-0">Normal (<?php echo $stats['nb_normal'] ?? 0; ?>)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3><?php echo formatPrix($stats['montant_retard'] ?? 0); ?></h3>
                <p class="mb-0">En retard (<?php echo $stats['nb_retard'] ?? 0; ?>)</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($type === 'agence' && !empty($results['par_agence'])): ?>
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Rapport par Agence</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Agence</th>
                    <th>Nombre de paiements</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['par_agence'] as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['nom_agence']); ?></strong></td>
                    <td><?php echo $row['nb_paiements']; ?></td>
                    <td><?php echo formatPrix($row['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($type === 'bailleur' && !empty($results['par_bailleur'])): ?>
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Rapport par Bailleur</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Bailleur</th>
                    <th>Nombre de paiements</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['par_bailleur'] as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['nom_bailleur'] . ' ' . $row['prenom_bailleur']); ?></strong></td>
                    <td><?php echo $row['nb_paiements']; ?></td>
                    <td><?php echo formatPrix($row['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (($type === 'paiements' || $type === 'all') && !empty($results['paiements'])): ?>
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Liste des Paiements</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Lot</th>
                    <th>Bailleur</th>
                    <th>Agence</th>
                    <th>Montant</th>
                    <th>Date</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['paiements'] as $p): ?>
                <?php $badge = match($p['statut']) { 'avance' => 'success', 'normal' => 'info', 'retard' => 'warning' }; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['mois']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['adresse']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_bailleur'] . ' ' . $p['prenom_bailleur']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_agence'] ?? '-'); ?></td>
                    <td><?php echo formatPrix($p['montant']); ?></td>
                    <td><?php echo formatDate($p['date_paiement']); ?></td>
                    <td><span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($p['statut']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (($type === 'avance' || $type === 'all') && !empty($results['avance'])): ?>
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white"><h5 class="mb-0">Paiements en Avance</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Lot</th>
                    <th>Bailleur</th>
                    <th>Montant</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['avance'] as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['mois']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['adresse'] . ', ' . $p['ville']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_bailleur'] . ' ' . $p['prenom_bailleur']); ?></td>
                    <td><?php echo formatPrix($p['montant']); ?></td>
                    <td><?php echo formatDate($p['date_paiement']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (($type === 'retard' || $type === 'all') && !empty($results['retard'])): ?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning"><h5 class="mb-0">Paiements en Retard</h5></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Lot</th>
                    <th>Bailleur</th>
                    <th>Agence</th>
                    <th>Montant</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['retard'] as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['mois']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['adresse'] . ', ' . $p['ville']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_bailleur'] . ' ' . $p['prenom_bailleur']); ?></td>
                    <td><?php echo htmlspecialchars($p['nom_agence'] ?? '-'); ?></td>
                    <td><?php echo formatPrix($p['montant']); ?></td>
                    <td><?php echo formatDate($p['date_paiement']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (empty($results) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
<div class="alert alert-info">Sélectionnez les critères et cliquez sur le bouton de filtrage pour générer un rapport.</div>
<?php endif; ?>