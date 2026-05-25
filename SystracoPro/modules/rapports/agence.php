<?php
// modules/rapports/agence.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.agence');
$pageTitle = 'Rapport Journalier Agence';
$aid = getUserAgenceId();
$date_rpt = $_GET['date'] ?? date('Y-m-d');
$print = isset($_GET['print']);

if (!$aid && !can('rapports.direction')) { flash('Agence non définie.','danger'); redirect(BASE_URL.'index.php'); }

// Forcer agence si admin
$agence_id = $aid ?? (int)($_GET['agence_id'] ?? 0);
if (!$agence_id) {
    $agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();
    include '../../includes/header.php'; ?>
    <div class="card"><div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="agence_id" class="fc" style="max-width:300px;"><option value="">— Sélectionner une agence —</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['nom']) ?></option><?php endforeach; ?></select>
      <input type="date" name="date" class="fc" value="<?= $date_rpt ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary">Générer</button>
    </form></div></div>
    <?php include '../../includes/footer.php'; exit();
}

$agence = $pdo->prepare("SELECT * FROM agences WHERE id=?"); $agence->execute([$agence_id]); $agence=$agence->fetch();
$appName = getParam('nom_entreprise',APP_NAME);

// ── CALCULS ─────────────────────────────────────────────────
// Tickets du jour
$tks = $pdo->query("SELECT t.*,IFNULL(ad.ville,a1.ville) as dep,IFNULL(aa.ville,a2.ville) as arr,v.numero as voy_num,CONCAT(u.prenom,' ',u.nom) as guichetier FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id LEFT JOIN utilisateurs u ON t.guichetier_id=u.id WHERE t.agence_id=$agence_id AND DATE(t.date_vente)='$date_rpt'")->fetchAll();
$sold_tks = array_filter($tks, fn($t) => $t['statut']==='vendu');
$cancel_tks = array_filter($tks, fn($t) => $t['statut']==='annule');
$recette_brute = array_sum(array_column(array_values($sold_tks),'montant_total'));
$recette_annule = array_sum(array_column(array_values($cancel_tks),'montant_total'));

// Par mode de paiement
$by_mode = [];
foreach($sold_tks as $t) { $by_mode[$t['mode_paiement']] = ($by_mode[$t['mode_paiement']]??0) + $t['montant_total']; }

// Par guichetier
$by_guich = [];
foreach($sold_tks as $t) { $g=$t['guichetier']??'Inconnu'; if(!isset($by_guich[$g])) $by_guich[$g]=['nb'=>0,'montant'=>0]; $by_guich[$g]['nb']++; $by_guich[$g]['montant']+=$t['montant_total']; }

// Dépenses
$deps = $pdo->query("SELECT * FROM depenses WHERE agence_id=$agence_id AND DATE(date_depense)='$date_rpt' AND statut IN ('approuve','paye')")->fetchAll();
$total_dep = array_sum(array_column($deps,'montant'));

// Versements
$vers = $pdo->query("SELECT * FROM versements WHERE agence_id=$agence_id AND DATE(date_versement)='$date_rpt'")->fetchAll();
$total_vers = array_sum(array_column($vers,'montant'));
$vers_banque = array_sum(array_column(array_filter($vers,fn($v)=>$v['type']==='banque'),'montant'));
$vers_om = array_sum(array_column(array_filter($vers,fn($v)=>$v['type']==='om'),'montant'));
$vers_momo = array_sum(array_column(array_filter($vers,fn($v)=>$v['type']==='momo'),'montant'));

// Solde
$solde = $recette_brute - $total_dep - $total_vers;

// Voyages du jour
$voys = $pdo->query("SELECT v.*,a1.ville as dep,a2.ville as arr,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauf,(SELECT COUNT(*) FROM tickets t WHERE t.voyage_id=v.id AND t.statut='vendu') as nb_tks,(SELECT SUM(montant_total) FROM tickets t WHERE t.voyage_id=v.id AND t.statut='vendu') as recette_voy FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.agence_id=$agence_id AND DATE(v.date_depart)='$date_rpt'")->fetchAll();

// Upsert rapport
$existing = $pdo->prepare("SELECT id FROM rapports_journaliers WHERE agence_id=? AND date_rapport=?"); $existing->execute([$agence_id,$date_rpt]);
if($rpt_id = $existing->fetchColumn()){
    $pdo->prepare("UPDATE rapports_journaliers SET nb_tickets=?,recette_brute=?,versement_banque=?,versement_om=?,versement_momo=?,total_depenses=?,solde=?,genere_par=? WHERE id=?")->execute([count($sold_tks),$recette_brute,$vers_banque,$vers_om,$vers_momo,$total_dep,$solde,$_SESSION['user_id'],$rpt_id]);
} else {
    $num=genNumero($pdo,'rapports_journaliers','numero','RPT');
    $pdo->prepare("INSERT INTO rapports_journaliers (numero,agence_id,date_rapport,nb_tickets,recette_brute,versement_banque,versement_om,versement_momo,total_depenses,solde,genere_par) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$num,$agence_id,$date_rpt,count($sold_tks),$recette_brute,$vers_banque,$vers_om,$vers_momo,$total_dep,$solde,$_SESSION['user_id']]);
}

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapport journalier</div>

<div class="no-print" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if(isAdmin()): ?><select name="agence_id" class="fc" style="width:auto;"><?php foreach(($agences??[]) as $ag): ?><option value="<?= $ag['id'] ?>" <?= $ag['id']==$agence_id?'selected':'' ?>><?= sanitize($ag['nom']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input type="date" name="date" class="fc" value="<?= $date_rpt ?>" style="width:auto;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Actualiser</button>
  </form>
  <button onclick="window.print()" class="btn btn-info btn-sm"><i class="fas fa-print"></i> Imprimer</button>
  <?php if(can('depenses.create')): ?><a href="../depenses/ajouter.php?agence_id=<?= $agence_id ?>&date=<?= $date_rpt ?>" class="btn btn-warning btn-sm"><i class="fas fa-plus"></i> Dépense</a><?php endif; ?>
  <?php if(can('versements.create')): ?><a href="../versements/ajouter.php?agence_id=<?= $agence_id ?>&date=<?= $date_rpt ?>" class="btn btn-orange btn-sm"><i class="fas fa-university"></i> Versement</a><?php endif; ?>
</div>

<!-- RAPPORT IMPRIMABLE -->
<div id="rapport-zone">
<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;font-family:'Times New Roman',serif;">

  <!-- EN-TÊTE -->
  <div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:12px;margin-bottom:14px;">
    <div style="font-size:16px;font-weight:900;color:#1e3a8a;text-transform:uppercase;"><?= sanitize($appName) ?></div>
    <div style="font-size:12px;color:#555;"><?= sanitize($agence['nom']) ?> | <?= sanitize($agence['ville']) ?></div>
    <div style="font-size:20px;font-weight:900;margin-top:8px;text-transform:uppercase;color:#1e3a8a;">RAPPORT JOURNALIER D'AGENCE</div>
    <div style="font-size:14px;font-weight:600;">Date : <?= fdate($date_rpt) ?> &nbsp;|&nbsp; Généré le : <?= fdatetime(date('Y-m-d H:i:s')) ?></div>
  </div>

  <!-- SYNTHÈSE FINANCIÈRE -->
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">I. SYNTHÈSE FINANCIÈRE</div>
    <table data-no-filter class="rpt-table">
      <tr><th style="width:60%;">Désignation</th><th>Montant (FCFA)</th></tr>
      <tr><td>Recette brute des ventes</td><td style="text-align:right;font-weight:700;"><?= number_format($recette_brute,0,',',' ') ?></td></tr>
      <tr><td>Tickets annulés (<?= count($cancel_tks) ?> tickets)</td><td style="text-align:right;color:#dc2626;">(<?= number_format($recette_annule,0,',',' ') ?>)</td></tr>
      <tr style="background:#f0f7ff;"><td><strong>Total dépenses imputées</strong></td><td style="text-align:right;color:#dc2626;font-weight:700;">(<?= number_format($total_dep,0,',',' ') ?>)</td></tr>
      <tr style="background:#f0f7ff;"><td><strong>Total versements</strong></td><td style="text-align:right;color:#d97706;font-weight:700;">(<?= number_format($total_vers,0,',',' ') ?>)</td></tr>
      <tr class="rpt-total"><td><strong>SOLDE EN CAISSE</strong></td><td style="text-align:right;font-size:14px;"><?= number_format($solde,0,',',' ') ?></td></tr>
    </table>
  </div>

  <!-- PAR MODE PAIEMENT -->
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">II. RECETTES PAR MODE DE PAIEMENT</div>
    <table data-no-filter class="rpt-table">
      <tr><th>Mode</th><th>Nb tickets</th><th>Montant (FCFA)</th></tr>
      <?php
      $modes=['especes'=>'💵 Espèces','om'=>'📱 Orange Money','momo'=>'📱 MTN MoMo','carte'=>'💳 Carte','cheque'=>'📄 Chèque'];
      foreach($modes as $k=>$v):
        $nb=count(array_filter($sold_tks,fn($t)=>$t['mode_paiement']===$k));
        $mt=$by_mode[$k]??0;
      ?>
      <tr><td><?= $v ?></td><td style="text-align:center;"><?= $nb ?></td><td style="text-align:right;font-weight:<?= $mt>0?'700':'400' ?>;"><?= number_format($mt,0,',',' ') ?></td></tr>
      <?php endforeach; ?>
      <tr class="rpt-total"><td><strong>TOTAL</strong></td><td style="text-align:center;"><strong><?= count($sold_tks) ?></strong></td><td style="text-align:right;"><strong><?= number_format($recette_brute,0,',',' ') ?></strong></td></tr>
    </table>
  </div>

  <!-- PAR GUICHETIER -->
  <?php if(!empty($by_guich)): ?>
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">III. RECETTES PAR GUICHETIER</div>
    <table data-no-filter class="rpt-table"><tr><th>Guichetier</th><th>Nb tickets</th><th>Montant (FCFA)</th></tr>
      <?php foreach($by_guich as $g=>$d): ?><tr><td><?= sanitize($g) ?></td><td style="text-align:center;"><?= $d['nb'] ?></td><td style="text-align:right;font-weight:700;"><?= number_format($d['montant'],0,',',' ') ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <!-- VOYAGES -->
  <?php if(!empty($voys)): ?>
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">IV. VOYAGES DU JOUR</div>
    <table data-no-filter class="rpt-table">
      <tr><th>Trajet</th><th>Véhicule</th><th>Chauffeur</th><th>Heure dep.</th><th>Passagers</th><th>Recette</th><th>Statut</th></tr>
      <?php foreach($voys as $v): ?>
      <tr><td><?= sanitize($v['dep'].' → '.$v['arr']) ?></td><td><?= sanitize($v['immatriculation']??'—') ?></td><td><?= sanitize($v['chauf']??'—') ?></td><td><?= date('H:i',strtotime($v['date_depart'])) ?></td><td style="text-align:center;"><?= $v['nb_tks'] ?></td><td style="text-align:right;font-weight:600;"><?= number_format($v['recette_voy']??0,0,',',' ') ?></td><td><?= statutLabel($v['statut']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <!-- DÉPENSES -->
  <?php if(!empty($deps)): ?>
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">V. DÉPENSES IMPUTÉES</div>
    <table data-no-filter class="rpt-table">
      <tr><th>Libellé</th><th>Catégorie</th><th>Montant (FCFA)</th></tr>
      <?php foreach($deps as $d): ?><tr><td><?= sanitize($d['libelle']) ?></td><td><?= categorieDepLabel($d['categorie']) ?></td><td style="text-align:right;font-weight:600;"><?= number_format($d['montant'],0,',',' ') ?></td></tr><?php endforeach; ?>
      <tr class="rpt-total"><td colspan="2"><strong>TOTAL DÉPENSES</strong></td><td style="text-align:right;"><strong><?= number_format($total_dep,0,',',' ') ?></strong></td></tr>
    </table>
  </div>
  <?php endif; ?>

  <!-- VERSEMENTS -->
  <?php if(!empty($vers)): ?>
  <div style="margin-bottom:14px;">
    <div style="font-size:12px;font-weight:900;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:8px;">VI. VERSEMENTS EFFECTUÉS</div>
    <table data-no-filter class="rpt-table">
      <tr><th>Type</th><th>Référence</th><th>Banque/Opérateur</th><th>Montant (FCFA)</th><th>Statut</th></tr>
      <?php foreach($vers as $v): ?><tr><td><?= sanitize(strtoupper($v['type'])) ?></td><td><?= sanitize($v['reference']??'—') ?></td><td><?= sanitize($v['banque']??'—') ?></td><td style="text-align:right;font-weight:600;"><?= number_format($v['montant'],0,',',' ') ?></td><td><?= statutLabel($v['statut']) ?></td></tr><?php endforeach; ?>
      <tr class="rpt-total"><td colspan="3"><strong>TOTAL VERSEMENTS</strong></td><td style="text-align:right;"><strong><?= number_format($total_vers,0,',',' ') ?></strong></td><td></td></tr>
    </table>
  </div>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <div class="brd-sign" style="margin-top:24px;">
    <div class="brd-sign-box"><div>Le Guichetier</div><div class="brd-sign-line"></div></div>
    <div class="brd-sign-box"><div>Le Chef de Guichet</div><div class="brd-sign-line"></div></div>
    <div class="brd-sign-box"><div>Le Chef d'Agence</div><div class="brd-sign-line"></div><small>Nom & Cachet</small></div>
  </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
