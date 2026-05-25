<?php
// modules/rapports/mensuel.php — Rapport mensuel par groupe et véhicule
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.agence');
$pageTitle = 'Rapport Mensuel Groupe / Véhicule';
$appName = getParam('nom_entreprise', APP_NAME);

$mois   = $_GET['mois'] ?? date('Y-m');
$grp_f  = (int)($_GET['grp'] ?? 0);
$date_d = $mois.'-01'; $date_f = date('Y-m-t', strtotime($date_d));

$groupes = $pdo->query("SELECT id,nom FROM groupes WHERE actif=1 ORDER BY nom")->fetchAll();
$data = []; $totaux = [];

if ($grp_f) {
    // Détail par véhicule du groupe
    $data = $pdo->query("SELECT v.immatriculation,v.description,g.nom as groupe,g.nom_contact,COUNT(b.id) as nb_brd,COALESCE(SUM(b.nb_passagers),0) as pass,COALESCE(SUM(b.nb_billets_gratuits),0) as grat,COALESCE(SUM(b.recette_totale),0) as r_brut,COALESCE(SUM(b.carburant),0) as carb,COALESCE(SUM(b.peage_total),0) as peage,COALESCE(SUM(b.retenue_agence),0) as retenue,COALESCE(SUM(b.ration_chauffeur),0) as ration,COALESCE(SUM(b.autres_depenses),0) as autres,COALESCE(SUM(b.recette_nette),0) as r_nette FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN bordereaux b ON b.vehicule_id=v.id AND b.date BETWEEN '$date_d' AND '$date_f' WHERE v.groupe_id=$grp_f AND v.actif=1 GROUP BY v.id ORDER BY v.immatriculation")->fetchAll();
    $totaux = ['r_brut'=>array_sum(array_column($data,'r_brut')),'carb'=>array_sum(array_column($data,'carb')),'peage'=>array_sum(array_column($data,'peage')),'retenue'=>array_sum(array_column($data,'retenue')),'ration'=>array_sum(array_column($data,'ration')),'autres'=>array_sum(array_column($data,'autres')),'r_nette'=>array_sum(array_column($data,'r_nette')),'pass'=>array_sum(array_column($data,'pass')),'nb_brd'=>array_sum(array_column($data,'nb_brd'))];
}

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Mensuel Groupe</div>
<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Groupe</label>
        <select name="grp" class="fc" style="min-width:200px;" required>
          <option value="">— Sélectionner un groupe —</option>
          <?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>" <?= $grp_f==$g['id']?'selected':'' ?>><?= h($g['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>"></div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-search"></i> Générer</button>
      <?php if(!empty($data)): ?><button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer</button><?php endif; ?>
    </form>
  </div>
</div>

<?php if(!$grp_f): ?>
<div class="empty card"><i class="fas fa-layer-group"></i><h3 style="margin-top:12px;">Sélectionnez un groupe et un mois</h3></div>
<?php elseif(empty($data)||!array_sum(array_column($data,'nb_brd'))): ?>
<div class="empty card"><i class="fas fa-bus"></i><h3 style="margin-top:12px;">Aucun bordereau pour ce groupe ce mois</h3></div>
<?php else: ?>
<div style="background:#fff;padding:20px;border-radius:var(--radius-lg);border:1px solid var(--border);">
  <div class="rpt-header">
    <div class="rpt-title"><?= h($appName) ?></div>
    <div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:8px;text-transform:uppercase;">RAPPORT MENSUEL PAR GROUPE ET VÉHICULE</div>
    <div style="font-size:14px;font-weight:600;">Groupe : <?= h($data[0]['groupe']??'') ?> | Mois : <?= date('F Y',strtotime($date_d)) ?></div>
    <div style="font-size:12px;color:#6b7280;">Contact : <?= h($data[0]['nom_contact']??'—') ?></div>
  </div>

  <table class="rpt-table">
    <thead><tr><th>Véhicule</th><th>Description</th><th>Voyages</th><th>Passagers</th><th>Gratuits</th><th>Recette brute</th><th>Carburant</th><th>Péages</th><th>Retenue</th><th>Ration</th><th>Autres</th><th>RECETTE NETTE</th></tr></thead>
    <tbody>
      <?php foreach($data as $d): if(!$d['nb_brd']) continue; ?>
      <tr>
        <td><code style="font-weight:700;font-size:12px;"><?= h($d['immatriculation']) ?></code></td>
        <td style="font-size:11px;"><?= h($d['description']??'—') ?></td>
        <td style="text-align:center;"><?= $d['nb_brd'] ?></td>
        <td style="text-align:center;"><?= number_format($d['pass']) ?></td>
        <td style="text-align:center;"><?= $d['grat'] ?></td>
        <td style="text-align:right;"><?= moneyRaw($d['r_brut']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($d['carb']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($d['peage']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($d['retenue']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($d['ration']) ?></td>
        <td style="text-align:right;color:#dc2626;"><?= moneyRaw($d['autres']) ?></td>
        <td style="text-align:right;font-weight:800;font-size:13px;color:var(--success);background:#f0fdf4;"><?= moneyRaw($d['r_nette']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="rpt-total">
        <td colspan="2" style="padding:8px 10px;"><strong>TOTAL GROUPE</strong></td>
        <td style="text-align:center;padding:8px 10px;"><?= $totaux['nb_brd'] ?></td>
        <td style="text-align:center;padding:8px 10px;"><?= number_format($totaux['pass']) ?></td>
        <td></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['r_brut']) ?></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['carb']) ?></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['peage']) ?></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['retenue']) ?></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['ration']) ?></td>
        <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($totaux['autres']) ?></td>
        <td style="text-align:right;padding:8px 10px;font-size:16px;"><?= moneyRaw($totaux['r_nette']) ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="rpt-sign" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:24px;">
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Représentant du Groupe</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div><small><?= h($data[0]['nom_contact']??'') ?></small></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Comptabilité</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Direction</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
  </div>
</div>
<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
