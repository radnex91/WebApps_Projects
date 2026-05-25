<?php
// modules/export.php — Module d'exportation données (Excel/CSV)
require_once '../includes/config.php';
requireLogin(); requirePerm('rapports.direction');
$pageTitle = 'Export des Données';

$type   = $_GET['type']   ?? '';
$date_d = $_GET['date_d'] ?? date('Y-m-d', strtotime('-30 days'));
$date_f = $_GET['date_f'] ?? date('Y-m-d');
$agence_id = (int)($_GET['agence_id'] ?? getUserAgenceId() ?? 0);
$format = $_GET['format'] ?? 'csv';

// ─── Générer export ────────────────────────────────────────────
if ($type && isset($_GET['download'])) {
    $aid = $agence_id;
    $wA  = $aid ? "AND t.agence_id=$aid" : "";

    switch ($type) {
        case 'tickets':
            $data = $pdo->query("SELECT t.numero as 'N° Ticket', t.passager_nom as 'Nom Passager', t.passager_tel as 'Téléphone', t.siege as 'Siège', t.classe as 'Classe', t.montant_total as 'Montant (FCFA)', t.mode_paiement as 'Mode Paiement', IFNULL(ad.ville,a1.ville) as 'Départ', IFNULL(aa.ville,a2.ville) as 'Arrivée', t.statut as 'Statut', DATE(t.date_vente) as 'Date', CONCAT(u.prenom,' ',u.nom) as 'Guichetier' FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id LEFT JOIN utilisateurs u ON t.guichetier_id=u.id WHERE DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' $wA ORDER BY t.date_vente DESC")->fetchAll();
            $filename = "tickets_{$date_d}_{$date_f}";
            break;

        case 'recettes':
            $data = $pdo->query("SELECT DATE(t.date_vente) as 'Date', a.ville as 'Agence', COUNT(*) as 'Nb Tickets', SUM(t.montant_total) as 'Recette Brute (FCFA)', SUM(CASE WHEN t.mode_paiement='especes' THEN t.montant_total ELSE 0 END) as 'Espèces', SUM(CASE WHEN t.mode_paiement='om' THEN t.montant_total ELSE 0 END) as 'Orange Money', SUM(CASE WHEN t.mode_paiement='momo' THEN t.montant_total ELSE 0 END) as 'MTN MoMo' FROM tickets t JOIN agences a ON t.agence_id=a.id WHERE t.statut='vendu' AND DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' ".($aid?"AND t.agence_id=$aid":"")." GROUP BY DATE(t.date_vente),t.agence_id ORDER BY DATE(t.date_vente) DESC")->fetchAll();
            $filename = "recettes_{$date_d}_{$date_f}";
            break;

        case 'bordereaux':
            $data = $pdo->query("SELECT b.numero as 'N° Bordereau', b.type as 'Type', b.agence_depart as 'Départ', b.agence_arrivee as 'Arrivée', b.vehicule_immat as 'Véhicule', b.chauffeur_nom as 'Chauffeur', b.date_depart as 'Date Départ', b.nb_passagers as 'Nb Passagers', b.recette_brute as 'Recette Brute', b.montant_carburant as 'Carburant', b.montant_peage as 'Péages', b.avance_chauffeur as 'Avance', b.recette_nette as 'Recette Nette', b.statut as 'Statut' FROM bordereaux b WHERE DATE(b.created_at) BETWEEN '$date_d' AND '$date_f' ".($aid?"AND b.agence_id=$aid":"")." ORDER BY b.created_at DESC")->fetchAll();
            $filename = "bordereaux_{$date_d}_{$date_f}";
            break;

        case 'depenses':
            $data = $pdo->query("SELECT d.numero as 'N° Dépense', DATE(d.date_depense) as 'Date', a.ville as 'Agence', d.categorie as 'Catégorie', d.libelle as 'Libellé', d.montant as 'Montant (FCFA)', d.beneficiaire as 'Bénéficiaire', d.statut as 'Statut', CONCAT(u.prenom,' ',u.nom) as 'Imputé par' FROM depenses d JOIN agences a ON d.agence_id=a.id LEFT JOIN utilisateurs u ON d.impute_par=u.id WHERE DATE(d.date_depense) BETWEEN '$date_d' AND '$date_f' ".($aid?"AND d.agence_id=$aid":"")." ORDER BY d.date_depense DESC")->fetchAll();
            $filename = "depenses_{$date_d}_{$date_f}";
            break;

        case 'versements':
            $data = $pdo->query("SELECT v.numero as 'N° Versement', DATE(v.date_versement) as 'Date', a.ville as 'Agence', v.type as 'Type', v.montant as 'Montant (FCFA)', v.banque as 'Banque/Opérateur', v.reference as 'Référence', v.statut as 'Statut', CONCAT(u.prenom,' ',u.nom) as 'Saisi par' FROM versements v JOIN agences a ON v.agence_id=a.id LEFT JOIN utilisateurs u ON v.saisi_par=u.id WHERE DATE(v.date_versement) BETWEEN '$date_d' AND '$date_f' ".($aid?"AND v.agence_id=$aid":"")." ORDER BY v.date_versement DESC")->fetchAll();
            $filename = "versements_{$date_d}_{$date_f}";
            break;

        case 'voyages':
            $data = $pdo->query("SELECT v.numero as 'N° Voyage', IFNULL(a1.ville,'') as 'Départ', IFNULL(a2.ville,'') as 'Arrivée', v.date_depart as 'Date Départ', vh.immatriculation as 'Véhicule', CONCAT(p.prenom,' ',p.nom) as 'Chauffeur', v.places_dispo as 'Places Total', (SELECT COUNT(*) FROM tickets t WHERE t.voyage_id=v.id AND t.statut='vendu') as 'Places Vendues', (SELECT SUM(montant_total) FROM tickets t WHERE t.voyage_id=v.id AND t.statut='vendu') as 'Recette', v.montant_carburant as 'Carburant', v.montant_peage as 'Péages', v.statut as 'Statut' FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules vh ON v.vehicule_id=vh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE DATE(v.date_depart) BETWEEN '$date_d' AND '$date_f' ".($aid?"AND v.agence_id=$aid":"")." ORDER BY v.date_depart DESC")->fetchAll();
            $filename = "voyages_{$date_d}_{$date_f}";
            break;

        default:
            $data = []; $filename = 'export';
    }

    if (!empty($data)) {
        // Export CSV
        $filename_clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename_clean.'.csv"');
        header('Pragma: no-cache');
        $output = fopen('php://output', 'w');
        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        // En-têtes
        fputcsv($output, array_keys($data[0]), ';');
        // Données
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        fclose($output);
        logAction($pdo, 'export_'.$type, 'export', "$type — $date_d à $date_f");
        exit();
    } else {
        flash("Aucune donnée à exporter pour la période sélectionnée.", 'warning');
    }
}

$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();
include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Export données</div>

<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header">
    <h3><i class="fas fa-file-excel"></i> Export des données (CSV/Excel)</h3>
  </div>
  <div class="card-body">
    <div class="flash flash-info" style="border-radius:var(--radius);margin-bottom:16px;">
      <i class="fas fa-info-circle"></i>
      Les fichiers sont exportés au format <strong>CSV</strong> compatible Excel. Ouvrez avec Excel en sélectionnant le séparateur <strong>point-virgule (;)</strong>.
    </div>

    <form method="GET">
      <div class="form-grid" style="margin-bottom:16px;">
        <div class="fg">
          <label class="flbl">Type d'export <span class="freq">*</span></label>
          <select name="type" class="fc" required>
            <option value="">— Sélectionner —</option>
            <option value="tickets" <?= $type==='tickets'?'selected':'' ?>>🎟️ Tickets vendus</option>
            <option value="recettes" <?= $type==='recettes'?'selected':'' ?>>💰 Recettes journalières</option>
            <option value="bordereaux" <?= $type==='bordereaux'?'selected':'' ?>>📋 Bordereaux</option>
            <option value="voyages" <?= $type==='voyages'?'selected':'' ?>>🚌 Voyages</option>
            <option value="depenses" <?= $type==='depenses'?'selected':'' ?>>💸 Dépenses</option>
            <option value="versements" <?= $type==='versements'?'selected':'' ?>>🏦 Versements</option>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Agence</label>
          <select name="agence_id" class="fc">
            <option value="0">Toutes les agences</option>
            <?php foreach($agences as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $agence_id==$a['id']?'selected':'' ?>><?= sanitize($a['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Date début</label>
          <input type="date" name="date_d" class="fc" value="<?= $date_d ?>">
        </div>
        <div class="fg">
          <label class="flbl">Date fin</label>
          <input type="date" name="date_f" class="fc" value="<?= $date_f ?>">
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" name="download" value="1" class="btn btn-success btn-lg">
          <i class="fas fa-download"></i> Télécharger CSV
        </button>
        <button type="submit" class="btn btn-secondary">
          <i class="fas fa-eye"></i> Prévisualiser
        </button>
      </div>
    </form>

    <!-- PRÉVISUALISATION -->
    <?php if ($type && !isset($_GET['download'])):
        $aid2 = $agence_id; $wA2 = $aid2 ? "AND t.agence_id=$aid2" : "";
        $preview = null;
        switch($type) {
            case 'tickets': $preview=$pdo->query("SELECT COUNT(*) as nb, SUM(t.montant_total) as total FROM tickets t WHERE DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' $wA2")->fetch(); break;
            case 'recettes': $preview=$pdo->query("SELECT COUNT(DISTINCT DATE(t.date_vente)) as nb, SUM(t.montant_total) as total FROM tickets t WHERE t.statut='vendu' AND DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' ".($aid2?"AND t.agence_id=$aid2":""))->fetch(); break;
            case 'bordereaux': $preview=$pdo->query("SELECT COUNT(*) as nb, SUM(b.recette_nette) as total FROM bordereaux b WHERE DATE(b.created_at) BETWEEN '$date_d' AND '$date_f' ".($aid2?"AND b.agence_id=$aid2":""))->fetch(); break;
            case 'depenses': $preview=$pdo->query("SELECT COUNT(*) as nb, SUM(d.montant) as total FROM depenses d WHERE DATE(d.date_depense) BETWEEN '$date_d' AND '$date_f' ".($aid2?"AND d.agence_id=$aid2":""))->fetch(); break;
            case 'versements': $preview=$pdo->query("SELECT COUNT(*) as nb, SUM(v.montant) as total FROM versements v WHERE DATE(v.date_versement) BETWEEN '$date_d' AND '$date_f' ".($aid2?"AND v.agence_id=$aid2":""))->fetch(); break;
            case 'voyages': $preview=$pdo->query("SELECT COUNT(*) as nb, 0 as total FROM voyages v WHERE DATE(v.date_depart) BETWEEN '$date_d' AND '$date_f' ".($aid2?"AND v.agence_id=$aid2":""))->fetch(); break;
        }
        if ($preview):
    ?>
    <div style="margin-top:16px;padding:14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
      <div style="font-size:12px;font-weight:600;margin-bottom:8px;">Aperçu de l'export :</div>
      <div style="display:flex;gap:20px;font-size:13px;">
        <span>📊 <strong><?= $preview['nb'] ?></strong> lignes</span>
        <?php if($preview['total']>0): ?><span>💰 Total : <strong><?= number_format($preview['total'],0,',',' ') ?> FCFA</strong></span><?php endif; ?>
        <span>📅 <?= fdate($date_d) ?> → <?= fdate($date_f) ?></span>
      </div>
    </div>
    <?php endif; endif; ?>
  </div>
</div>

<!-- HISTORIQUE EXPORTS -->
<div class="card" style="max-width:700px;margin:20px auto 0;">
  <div class="card-header"><h3><i class="fas fa-history"></i> Historique des exports récents</h3></div>
  <div class="card-body" style="padding:0;">
    <?php
    $logs = $pdo->prepare("SELECT l.*,CONCAT(u.prenom,' ',u.nom) as user_nom FROM logs l LEFT JOIN utilisateurs u ON l.user_id=u.id WHERE l.module='export' ORDER BY l.created_at DESC LIMIT 10");
    $logs->execute(); $logs=$logs->fetchAll();
    ?>
    <?php foreach($logs as $log): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-bottom:1px solid var(--border);font-size:12px;">
      <div>
        <span class="badge badge-blue"><?= sanitize(str_replace('export_','',$log['action'])) ?></span>
        <span style="margin-left:8px;"><?= sanitize($log['details']) ?></span>
      </div>
      <div style="color:var(--text3);"><?= sanitize($log['user_nom']??'—') ?> · <?= timeAgo($log['created_at']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($logs)): ?><div style="padding:20px;text-align:center;color:var(--text3);">Aucun export effectué</div><?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
