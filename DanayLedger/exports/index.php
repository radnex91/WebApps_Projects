<?php
$pageTitle = 'Exportation';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requirePermission('exports');

$db = getDB();

// Handle export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $type = $_POST['export_type'] ?? '';
    $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
    $dateTo = $_POST['date_to'] ?? date('Y-m-t');
    $agenceId = (int)($_POST['agence_id'] ?? 0) ?: null;
    $format = $_POST['format'] ?? 'csv';

    $agenceFilter = $agenceId ? " AND agence_id = $agenceId" : '';
    $agenceVerseFilter = $agenceId ? " AND agenceverse_id = $agenceId" : '';
    $headers = [];
    $data = [];

    switch ($type) {
        case 'recettes':
            $headers = ['Référence', 'Date', 'Montant Expédition', 'Montant Accompagnement', 'Agence', 'Opérateur', 'Description', 'Statut', 'Créé le'];
            $stmt = $db->prepare("SELECT r.reference, r.date as date_recette, r.montantexpedition, r.montantAccompagnement, a.nomagence as agence, r.nomoperateur, r.description, r.statut, r.created_at FROM recette r LEFT JOIN agence a ON r.agence_id=a.id WHERE r.date BETWEEN ? AND ?$agenceFilter ORDER BY r.date");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) {
                $data[] = [$row['reference'], $row['date_recette'], $row['montantexpedition'], $row['montantAccompagnement'], $row['agence']??'', $row['nomoperateur']??'', $row['description']??'', $row['statut'], $row['created_at']];
            }
            break;

        case 'depenses':
            $headers = ['Référence', 'Date', 'Montant', 'Catégorie', 'Agence', 'Nature', 'Description', 'Statut', 'Créé le'];
            $stmt = $db->prepare("SELECT d.reference, d.date_depense, d.montant, c.nom as cat, a.nomagence as agence, d.nature, d.description, d.statut, d.created_at FROM depenses d LEFT JOIN categories c ON d.categorie_id=c.id LEFT JOIN agence a ON d.agence_id=a.id WHERE d.date_depense BETWEEN ? AND ?$agenceFilter ORDER BY d.date_depense");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) {
                $data[] = [$row['reference'], $row['date_depense'], $row['montant'], $row['cat']??'', $row['agence']??'', $row['nature']??'', $row['description']??'', $row['statut'], $row['created_at']];
            }
            break;

        case 'recettes_camions':
            $headers = ['Référence', 'Date', 'Montant', 'Camion', 'Agence départ', 'Agence arrivée', 'Catégorie', 'Client', 'Statut', 'Créé le'];
            $stmt = $db->prepare("SELECT rc.reference, rc.date_recette, rc.montant, v.immatriculation, ad.nomagence as agence_depart, aa.nomagence as agence_arrivee, c.nom as cat_nom, rc.client, rc.statut, rc.created_at FROM recettes_camions rc LEFT JOIN vehicules v ON rc.vehicule_id=v.id LEFT JOIN agence ad ON rc.agence_depart_id=ad.id LEFT JOIN agence aa ON rc.agence_arrivee_id=aa.id LEFT JOIN categories c ON rc.categorie_id=c.id WHERE rc.date_recette BETWEEN ? AND ?$agenceFilter ORDER BY rc.date_recette");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) {
                $data[] = [$row['reference'], $row['date_recette'], $row['montant'], $row['immatriculation']??'', $row['agence_depart']??'', $row['agence_arrivee']??'', $row['cat_nom']??'', $row['client']??'', $row['statut'], $row['created_at']];
            }
            break;

        case 'versements':
            $headers = ['Référence', 'Date', 'Montant', 'Banque', 'Réf. bancaire', 'Agence', 'Statut', 'Créé le'];
            $stmt = $db->prepare("SELECT vb.reference, vb.dateversement as date_versement, vb.sommeverse as montant, b.nombank as banque, vb.refversement as reference_bancaire, a.nomagence as agence, vb.statut, vb.created_at FROM versement vb LEFT JOIN agence a ON vb.agenceverse_id=a.id LEFT JOIN bank b ON vb.bank_id=b.id WHERE vb.dateversement BETWEEN ? AND ?$agenceVerseFilter ORDER BY vb.dateversement");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) {
                $data[] = [$row['reference'], $row['date_versement'], $row['montant'], $row['banque']??'', $row['reference_bancaire']??'', $row['agence']??'', $row['statut'], $row['created_at']];
            }
            break;

        case 'rapport':
            $headers = ['Type', 'Référence', 'Date', 'Montant', 'Agence', 'Statut'];
            // Recettes
            $stmt = $db->prepare("SELECT 'Recette' as type, r.reference, r.date as date_op, (r.montantexpedition + r.montantAccompagnement) as montant, a.nomagence as agence, r.statut FROM recette r LEFT JOIN agence a ON r.agence_id=a.id WHERE r.date BETWEEN ? AND ?$agenceFilter AND r.statut='validee'");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) { $data[] = array_values($row); }
            // Dépenses
            $stmt = $db->prepare("SELECT 'Dépense' as type, d.reference, d.date_depense as date_op, d.montant, a.nomagence as agence, c.nom as cat, d.statut FROM depenses d LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN categories c ON d.categorie_id=c.id WHERE d.date_depense BETWEEN ? AND ?$agenceFilter AND d.statut='validee'");
            $stmt->execute([$dateFrom, $dateTo]);
            foreach ($stmt->fetchAll() as $row) { $data[] = array_values($row); }
            break;
    }

    addAuditLog('export', 'system', 0, [], ['type' => $type, 'format' => $format]);

    if ($format === 'csv') {
        exportCSV($type . '_' . date('Ymd'), $headers, $data);
    }
    // PDF export could be added with a library like FPDF/TCPDF
    exit;
}

$agences = getAgences($db);
?>
<div class="main-content">
    <header class="main-header"><div class="header-left"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6></div><div class="header-right"><div class="dropdown"><button class="notif-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i></button><div class="dropdown-menu dropdown-menu-end notif-dropdown"><h6 class="dropdown-header">Notifications</h6><div class="dropdown-item text-muted text-center py-3">Aucune notification</div></div></div><div class="dropdown"><div class="header-user" data-bs-toggle="dropdown"><div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div><div class="user-info d-none d-sm-block"><div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div><div class="user-role"><?php echo e(getRoleLabel($_SESSION['user_role'] ?? '')); ?></div></div></div><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a><div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></div></div></div></header>
    <div class="page-content fade-in">
        <div class="page-header"><div><h1 class="page-title"><i class="bi bi-download me-2"></i>Exportation des Données</h1></div></div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card"><div class="card-header"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Configurer l'export</div><div class="card-body">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Type de données <span class="text-danger">*</span></label>
                                <select name="export_type" class="form-select" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="recettes">Recettes journalières</option>
                                    <option value="depenses">Dépenses</option>
                                    <option value="recettes_camions">Recettes camions</option>
                                    <option value="versements">Versements bancaires</option>
                                    <option value="rapport">Rapport complet</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Format</label>
                                <select name="format" class="form-select">
                                    <option value="csv">CSV (Excel)</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Date début</label><input type="date" name="date_from" value="<?php echo date('Y-m-01'); ?>" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Date fin</label><input type="date" name="date_to" value="<?php echo date('Y-m-t'); ?>" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Agence</label>
                                <select name="agence_id" class="form-select"><option value="">Toutes</option><?php foreach($agences as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo e($a['nomagence']); ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Exporter</button></div>
                        </div>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card"><div class="card-header"><i class="bi bi-info-circle me-2"></i>Informations</div><div class="card-body">
                    <p class="small text-muted">L'export CSV est compatible avec Excel, Google Sheets et LibreOffice Calc.</p>
                    <p class="small text-muted">Le séparateur utilisé est le point-virgule (;) pour une compatibilité optimale avec les paramètres régionaux français.</p>
                    <p class="small text-muted">Les exports contiennent uniquement les transactions validées.</p>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>