<?php
// modules/rapports/vehicule.php — Rapport mensuel par véhicule
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.agence');
$pageTitle = 'Rapport Mensuel par Véhicule';
$appName = getParam('nom_entreprise', APP_NAME);

$veh_sel = (int)($_GET['veh'] ?? 0);
$mois    = $_GET['mois'] ?? date('Y-m');
$date_d  = $mois.'-01';
$date_f  = date('Y-m-t', strtotime($date_d));

$vehicules = $pdo->query("SELECT v.*,g.nom as groupe_nom FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id WHERE v.actif=1 ORDER BY v.immatriculation")->fetchAll();
$vehicule  = null; $rows = []; $stats = [];

if ($veh_sel) {
    $s=$pdo->prepare("SELECT v.*,g.nom as groupe_nom,g.nom_contact as groupe_contact,g.banque,g.num_compte_bancaire FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id WHERE v.id=?");
    $s->execute([$veh_sel]); $vehicule=$s->fetch();

    $rows = $pdo->query("SELECT b.*,ad.nom as ag_dep,ad.code as code_dep,aa.nom as ag_arr,aa.code as code_arr FROM bordereaux b LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id WHERE b.vehicule_id=$veh_sel AND b.date BETWEEN '$date_d' AND '$date_f' ORDER BY b.date,b.num_bordereau")->fetchAll();

    $s2=$pdo->query("SELECT COUNT(*) as nb,COALESCE(SUM(nb_passagers),0) as pass,COALESCE(SUM(nb_billets_gratuits),0) as grat,COALESCE(SUM(recette_totale),0) as r_brut,COALESCE(SUM(carburant),0) as carb,COALESCE(SUM(peage_total),0) as peage,COALESCE(SUM(retenue_agence),0) as retenue,COALESCE(SUM(ration_chauffeur),0) as ration,COALESCE(SUM(autres_depenses),0) as autres,COALESCE(SUM(recette_nette),0) as r_nette FROM bordereaux WHERE vehicule_id=$veh_sel AND date BETWEEN '$date_d' AND '$date_f'");
    $stats = $s2->fetch();
}
include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Mensuel Véhicule</div>

<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Véhicule</label>
        <select name="veh" class="fc" style="min-width:220px;" required>
          <option value="">— Sélectionner un véhicule —</option>
          <?php foreach($vehicules as $v): ?>
          <option value="<?= $v['id'] ?>" <?= $veh_sel==$v['id']?'selected':'' ?>><?= h($v['immatriculation']) ?> (<?= h($v['groupe_nom']??'—') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>"></div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-search"></i> Générer</button>
      <?php if($vehicule&&!empty($rows)): ?><button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer</button><?php endif; ?>
    </form>
  </div>
</div>

<?php if(!$vehicule): ?>
<div class="empty card"><i class="fas fa-bus"></i><h3 style="margin-top:12px;">Sélectionnez un véhicule et un mois</h3></div>
<?php else: ?>

<div id="rapport-zone" style="background:#fff;padding:20px;border-radius:var(--radius-lg);border:1px solid var(--border);">

  <div class="rpt-header">
    <div class="rpt-title"><?= h($appName) ?></div>
    <div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:8px;text-transform:uppercase;">RAPPORT MENSUEL PAR VÉHICULE</div>
    <div style="font-size:14px;font-weight:600;">Mois de : <?= date('F Y', strtotime($date_d)) ?></div>
  </div>

  <!-- INFO VÉHICULE -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <tr style="background:#f0f7ff;"><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;width:140px;">Immatriculation</td><td style="padding:6px 10px;border:1px solid #ccc;font-size:16px;font-weight:900;"><?= h($vehicule['immatriculation']) ?></td></tr>
      <tr><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Marque / Modèle</td><td style="padding:6px 10px;border:1px solid #ccc;"><?= h(($vehicule['marque']??'—').' '.($vehicule['modele']??'')) ?></td></tr>
      <tr style="background:#f0f7ff;"><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Description</td><td style="padding:6px 10px;border:1px solid #ccc;"><?= h($vehicule['description']??'—') ?></td></tr>
    </table>
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <tr style="background:#f0f7ff;"><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;width:140px;">Groupe propriétaire</td><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;"><?= h($vehicule['groupe_nom']??'—') ?></td></tr>
      <tr><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Contact groupe</td><td style="padding:6px 10px;border:1px solid #ccc;"><?= h($vehicule['groupe_contact']??'—') ?></td></tr>
      <tr style="background:#f0f7ff;"><td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Banque / Compte</td><td style="padding:6px 10px;border:1px solid #ccc;"><?= h(($vehicule['banque']??'—').' — '.($vehicule['num_compte_bancaire']??'—')) ?></td></tr>
    </table>
  </div>

  <!-- SYNTHÈSE DU MOIS -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;">I. Synthèse du mois</div>
  <table class="rpt-table" style="width:60%;">
    <tr><th>Désignation</th><th>Valeur</th></tr>
    <tr><td>Nombre de voyages (bordereaux)</td><td style="text-align:right;"><strong><?= $stats['nb']??0 ?></strong></td></tr>
    <tr><td>Total passagers transportés</td><td style="text-align:right;"><strong><?= number_format($stats['pass']??0) ?></strong></td></tr>
    <tr><td>Dont billets gratuits</td><td style="text-align:right;"><?= $stats['grat']??0 ?></td></tr>
    <tr><td>Recette totale brute</td><td style="text-align:right;font-weight:700;"><?= moneyRaw($stats['r_brut']??0) ?> FCFA</td></tr>
    <tr><td>(−) Carburant total</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['carb']??0) ?>)</td></tr>
    <tr><td>(−) Péages total</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['peage']??0) ?>)</td></tr>
    <tr><td>(−) Retenues agences</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['retenue']??0) ?>)</td></tr>
    <tr><td>(−) Rations chauffeurs</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['ration']??0) ?>)</td></tr>
    <tr><td>(−) Autres dépenses</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['autres']??0) ?>)</td></tr>
    <tr class="rpt-total"><td><strong>RECETTE NETTE DU MOIS</strong></td><td style="text-align:right;font-size:16px;"><strong><?= moneyRaw($stats['r_nette']??0) ?> FCFA</strong></td></tr>
  </table>

  <!-- DÉTAIL BORDEREAUX -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;margin-top:16px;">II. Détail journalier (<?= count($rows) ?> bordereaux)</div>
  <?php if(!empty($rows)): ?>
  <table class="rpt-table">
    <thead><tr><th>N°</th><th>Date</th><th>Départ</th><th>Arrivée</th><th>Pass.</th><th>Grat.</th><th>Recette brute</th><th>Carburant</th><th>Péages</th><th>Retenue</th><th>Ration</th><th>Autres</th><th style="background:#0c5a2e;color:#fff;">Nette</th></tr></thead>
    <tbody>
      <?php
      $cur_mois = '';
      foreach($rows as $r):
        $m_r = date('F Y', strtotime($r['date']));
        if($m_r !== $cur_mois) { $cur_mois=$m_r; ?>
        <tr style="background:#e0f2fe;font-size:11px;font-weight:700;"><td colspan="13" style="padding:4px 10px;">📅 <?= $m_r ?></td></tr>
      <?php } ?>
      <tr>
        <td><strong><?= $r['num_bordereau'] ?></strong></td>
        <td><?= fdate($r['date']) ?></td>
        <td><span class="badge b-blue" style="font-size:10px;"><?= h($r['code_dep']??'—') ?></span></td>
        <td><span class="badge b-teal" style="font-size:10px;"><?= h($r['code_arr']??'—') ?></span></td>
        <td style="text-align:center;"><?= $r['nb_passagers'] ?></td>
        <td style="text-align:center;"><?= $r['nb_billets_gratuits'] ?></td>
        <td style="text-align:right;"><?= moneyRaw($r['recette_totale']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($r['carburant']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($r['peage_total']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($r['retenue_agence']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($r['ration_chauffeur']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($r['autres_depenses']) ?></td>
        <td style="text-align:right;font-weight:700;color:var(--success);background:#f0fdf4;"><?= moneyRaw($r['recette_nette']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="rpt-total">
        <td colspan="4" style="text-align:right;padding:7px 10px;">TOTAL :</td>
        <td style="padding:7px 10px;text-align:center;"><?= number_format($stats['pass']??0) ?></td>
        <td style="padding:7px 10px;text-align:center;"><?= $stats['grat']??0 ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['r_brut']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['carb']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['peage']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['retenue']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['ration']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;"><?= moneyRaw($stats['autres']??0) ?></td>
        <td style="padding:7px 10px;text-align:right;font-size:15px;"><?= moneyRaw($stats['r_nette']??0) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php else: ?>
  <div style="text-align:center;padding:24px;color:var(--text3);">Aucun bordereau pour ce véhicule sur cette période.</div>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:24px;">
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Représentant du Groupe</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div><small><?= h($vehicule['groupe_contact']??'') ?></small></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Comptabilité</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Direction</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
  </div>
</div>
<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
