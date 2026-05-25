<?php
// modules/rapports/guichetier.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.guichet');
$pageTitle = 'Rapport Journalier Guichetier';
$aid = getUserAgenceId();
$date_rpt = $_GET['date'] ?? date('Y-m-d');

// Si chef / admin : peut choisir guichetier
$guichetier_id = (int)($_GET['guichetier_id'] ?? 0);
if (!$guichetier_id && isGuichetier()) $guichetier_id = $_SESSION['user_id'];

$guichetiers = [];
if (isChefGuichet() || isChefAgence() || isAdmin()) {
    $wG = $aid ? "AND u.agence_id=$aid" : "";
    $guichetiers = $pdo->query("SELECT u.id,CONCAT(u.prenom,' ',u.nom) as nom,u.username FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE r.code IN ('guichetier','chef_guichet') $wG ORDER BY u.nom")->fetchAll();
}

$guichetier = null;
if ($guichetier_id) {
    $s=$pdo->prepare("SELECT u.*,a.nom as agence_nom,a.ville FROM utilisateurs u LEFT JOIN agences a ON u.agence_id=a.id WHERE u.id=?");
    $s->execute([$guichetier_id]); $guichetier=$s->fetch();
}

$tickets=[]; $stats=[];
if ($guichetier_id) {
    // Tickets du guichetier
    $tickets=$pdo->query("SELECT t.*,IFNULL(ad.ville,a1.ville) as dep,IFNULL(aa.ville,a2.ville) as arr,v.date_depart FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.guichetier_id=$guichetier_id AND DATE(t.date_vente)='$date_rpt' ORDER BY t.date_vente")->fetchAll();
    $sold  = array_filter($tickets, fn($t)=>$t['statut']==='vendu');
    $annul = array_filter($tickets, fn($t)=>$t['statut']==='annule');
    $stats = [
        'nb_vendu'  => count($sold),
        'nb_annule' => count($annul),
        'total'     => array_sum(array_column(array_values($sold),'montant_total')),
        'especes'   => array_sum(array_column(array_filter(array_values($sold),fn($t)=>$t['mode_paiement']==='especes'),'montant_total')),
        'om'        => array_sum(array_column(array_filter(array_values($sold),fn($t)=>$t['mode_paiement']==='om'),'montant_total')),
        'momo'      => array_sum(array_column(array_filter(array_values($sold),fn($t)=>$t['mode_paiement']==='momo'),'montant_total')),
        'carte'     => array_sum(array_column(array_filter(array_values($sold),fn($t)=>$t['mode_paiement']==='carte'),'montant_total')),
        'annule_m'  => array_sum(array_column(array_values($annul),'montant_total')),
    ];
    // Upsert rapport guichetier
    $ex=$pdo->prepare("SELECT id FROM rapports_guichetiers WHERE guichetier_id=? AND date_rapport=?"); $ex->execute([$guichetier_id,$date_rpt]);
    if($ex->fetchColumn()){
        $pdo->prepare("UPDATE rapports_guichetiers SET nb_tickets=?,montant_total=?,montant_especes=?,montant_om=?,montant_momo=?,nb_annulations=?,montant_annule=? WHERE guichetier_id=? AND date_rapport=?")->execute([$stats['nb_vendu'],$stats['total'],$stats['especes'],$stats['om'],$stats['momo'],$stats['nb_annule'],$stats['annule_m'],$guichetier_id,$date_rpt]);
    } else {
        $pdo->prepare("INSERT INTO rapports_guichetiers (guichetier_id,agence_id,date_rapport,nb_tickets,montant_total,montant_especes,montant_om,montant_momo,nb_annulations,montant_annule) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$guichetier_id,$guichetier['agence_id']??$aid,$date_rpt,$stats['nb_vendu'],$stats['total'],$stats['especes'],$stats['om'],$stats['momo'],$stats['nb_annule'],$stats['annule_m']]);
    }
}
$appName=getParam('nom_entreprise',APP_NAME);
include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapport Guichetier</div>

<div class="no-print card" style="margin-bottom:16px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <?php if(!empty($guichetiers)): ?>
      <select name="guichetier_id" class="fc" style="max-width:250px;">
        <option value="">— Sélectionner un guichetier —</option>
        <?php foreach($guichetiers as $g): ?><option value="<?= $g['id'] ?>" <?= $guichetier_id==$g['id']?'selected':'' ?>><?= sanitize($g['nom']) ?> (@<?= sanitize($g['username']) ?>)</option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <input type="date" name="date" class="fc" value="<?= $date_rpt ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Générer</button>
      <?php if($guichetier): ?><button type="button" onclick="window.print()" class="btn btn-info btn-sm"><i class="fas fa-print"></i> Imprimer</button><?php endif; ?>
    </form>
  </div>
</div>

<?php if($guichetier && !empty($stats)): ?>
<div id="rpt-zone">
<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;font-family:'Times New Roman',serif;">

  <!-- EN-TÊTE -->
  <div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;margin-bottom:12px;">
    <div style="font-size:15px;font-weight:900;color:#1e3a8a;text-transform:uppercase;"><?= sanitize($appName) ?></div>
    <div style="font-size:12px;"><?= sanitize($guichetier['agence_nom']??'') ?></div>
    <div style="font-size:18px;font-weight:900;margin-top:6px;text-transform:uppercase;color:#1e3a8a;">RAPPORT JOURNALIER GUICHETIER</div>
    <div style="font-size:12px;">Date : <strong><?= fdate($date_rpt) ?></strong></div>
  </div>

  <!-- INFO GUICHETIER -->
  <table data-no-filter style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:12px;">
    <tr style="background:#f0f7ff;">
      <td style="padding:5px 10px;border:1px solid #ccc;font-weight:600;">Guichetier :</td><td style="padding:5px 10px;border:1px solid #ccc;"><?= sanitize($guichetier['prenom'].' '.$guichetier['nom']) ?></td>
      <td style="padding:5px 10px;border:1px solid #ccc;font-weight:600;">Agence :</td><td style="padding:5px 10px;border:1px solid #ccc;"><?= sanitize($guichetier['agence_nom']??'—') ?></td>
    </tr>
    <tr>
      <td style="padding:5px 10px;border:1px solid #ccc;font-weight:600;">Nb tickets vendus :</td><td style="padding:5px 10px;border:1px solid #ccc;font-weight:700;"><?= $stats['nb_vendu'] ?></td>
      <td style="padding:5px 10px;border:1px solid #ccc;font-weight:600;">Nb annulations :</td><td style="padding:5px 10px;border:1px solid #ccc;color:#dc2626;font-weight:700;"><?= $stats['nb_annule'] ?></td>
    </tr>
  </table>

  <!-- SYNTHÈSE -->
  <div style="font-size:11px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:3px;margin-bottom:8px;">SYNTHÈSE FINANCIÈRE</div>
  <table data-no-filter style="width:60%;border-collapse:collapse;font-size:12px;margin-bottom:12px;">
    <tr><td style="padding:5px 10px;border:1px solid #ccc;">💵 Espèces</td><td style="padding:5px 10px;border:1px solid #ccc;text-align:right;font-weight:700;"><?= number_format($stats['especes'],0,',',' ') ?> FCFA</td></tr>
    <tr style="background:#f0f7ff;"><td style="padding:5px 10px;border:1px solid #ccc;">📱 Orange Money</td><td style="padding:5px 10px;border:1px solid #ccc;text-align:right;font-weight:700;"><?= number_format($stats['om'],0,',',' ') ?> FCFA</td></tr>
    <tr><td style="padding:5px 10px;border:1px solid #ccc;">📱 MTN MoMo</td><td style="padding:5px 10px;border:1px solid #ccc;text-align:right;font-weight:700;"><?= number_format($stats['momo'],0,',',' ') ?> FCFA</td></tr>
    <tr style="background:#f0f7ff;"><td style="padding:5px 10px;border:1px solid #ccc;">💳 Carte</td><td style="padding:5px 10px;border:1px solid #ccc;text-align:right;"><?= number_format($stats['carte'],0,',',' ') ?> FCFA</td></tr>
    <tr style="background:#1e3a8a;color:#fff;font-weight:700;"><td style="padding:7px 10px;border:1px solid #555;">TOTAL ENCAISSÉ</td><td style="padding:7px 10px;border:1px solid #555;text-align:right;font-size:14px;"><?= number_format($stats['total'],0,',',' ') ?> FCFA</td></tr>
    <?php if($stats['annule_m']>0): ?><tr style="color:#dc2626;"><td style="padding:5px 10px;border:1px solid #ccc;">Montant annulé</td><td style="padding:5px 10px;border:1px solid #ccc;text-align:right;">(<?= number_format($stats['annule_m'],0,',',' ') ?>) FCFA</td></tr><?php endif; ?>
  </table>

  <!-- DÉTAIL TICKETS -->
  <div style="font-size:11px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:3px;margin-bottom:8px;">DÉTAIL DES TICKETS (<?= count($tickets) ?>)</div>
  <table data-no-filter style="width:100%;border-collapse:collapse;font-size:11px;">
    <thead><tr style="background:#1e3a8a;color:#fff;"><th style="padding:5px 8px;border:1px solid #555;">N° Ticket</th><th style="padding:5px 8px;border:1px solid #555;">Passager</th><th style="padding:5px 8px;border:1px solid #555;">Trajet</th><th style="padding:5px 8px;border:1px solid #555;">Siège</th><th style="padding:5px 8px;border:1px solid #555;">Mode</th><th style="padding:5px 8px;border:1px solid #555;">Montant</th><th style="padding:5px 8px;border:1px solid #555;">Statut</th></tr></thead>
    <tbody>
      <?php foreach($tickets as $i=>$t): ?>
      <tr style="background:<?= $i%2===0?'#f8f8f8':'#fff' ?>;<?= $t['statut']==='annule'?'color:#dc2626;text-decoration:line-through;':'' ?>">
        <td style="padding:4px 8px;border:1px solid #ddd;font-family:monospace;"><?= sanitize($t['numero']) ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;"><?= sanitize($t['passager_nom']) ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;"><?= sanitize($t['dep'].'→'.$t['arr']) ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;text-align:center;"><?= sanitize($t['siege']??'—') ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;"><?= sanitize($t['mode_paiement']) ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;text-align:right;font-weight:600;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
        <td style="padding:4px 8px;border:1px solid #ddd;"><?= statutLabel($t['statut']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- SIGNATURES -->
  <div class="brd-sign" style="margin-top:20px;">
    <div class="brd-sign-box"><div>Le Guichetier</div><div class="brd-sign-line"></div><small><?= sanitize($guichetier['prenom'].' '.$guichetier['nom']) ?></small></div>
    <div class="brd-sign-box"><div>Le Chef de Guichet</div><div class="brd-sign-line"></div></div>
    <div class="brd-sign-box"><div>Le Chef d'Agence</div><div class="brd-sign-line"></div></div>
  </div>
</div>
</div>
<?php elseif(!$guichetier_id): ?>
<div class="empty card"><i class="fas fa-user"></i><h3 style="margin-top:12px;">Sélectionnez un guichetier et une date</h3></div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text3);"><i class="fas fa-ticket-alt" style="font-size:36px;opacity:.2;"></i><p style="margin-top:12px;">Aucun ticket pour ce guichetier à cette date.</p></div></div>
<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
