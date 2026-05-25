<?php
// modules/rapports/rapprochement.php — Rapprochement Bancaire
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.direction');
$pageTitle = 'Rapprochement Bancaire';
$appName = getParam('nom_entreprise', APP_NAME);
$mois = $_GET['mois'] ?? date('Y-m');
$date_d = $mois.'-01'; $date_f = date('Y-m-t', strtotime($date_d));
$agences = $pdo->query("SELECT id,nom,code FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();

// Retenues par agence
$retenues=$pdo->query("SELECT ad.nom as agence,ad.code,SUM(b.retenue_agence) as retenue,COUNT(b.id) as nb FROM bordereaux b LEFT JOIN agences ad ON b.agence_depart_id=ad.id WHERE b.date BETWEEN '$date_d' AND '$date_f' GROUP BY b.agence_depart_id ORDER BY ad.nom")->fetchAll();
// Versements par agence
$versements=$pdo->query("SELECT a.nom as agence,a.code,SUM(v.versement_agence) as verse,SUM(v.decaissement_agence) as decaisse,SUM(v.recette_agence) as recette,COUNT(v.id) as nb FROM versements v LEFT JOIN agences a ON v.agence_id=a.id WHERE v.date BETWEEN '$date_d' AND '$date_f' GROUP BY v.agence_id ORDER BY a.nom")->fetchAll();

// Jointure
$data=[];
foreach($retenues as $r) $data[$r['code']]=['agence'=>$r['agence'],'code'=>$r['code'],'retenue'=>$r['retenue'],'nb_brd'=>$r['nb'],'verse'=>0,'decaisse'=>0,'recette'=>0,'nb_vers'=>0];
foreach($versements as $v) {
    if(!isset($data[$v['code']])) $data[$v['code']]=['agence'=>$v['agence'],'code'=>$v['code'],'retenue'=>0,'nb_brd'=>0,'verse'=>0,'decaisse'=>0,'recette'=>0,'nb_vers'=>0];
    $data[$v['code']]['verse']=$v['verse']; $data[$v['code']]['decaisse']=$v['decaisse']; $data[$v['code']]['recette']=$v['recette']; $data[$v['code']]['nb_vers']=$v['nb'];
}
foreach($data as &$d) $d['ecart']=$d['retenue']-$d['verse'];

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Rapprochement</div>
<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>"></div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-sync"></i> Actualiser</button>
      <button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer</button>
    </form>
  </div>
</div>
<div style="background:#fff;padding:20px;border-radius:var(--radius-lg);border:1px solid var(--border);">
  <div class="rpt-header">
    <div class="rpt-title"><?= h($appName) ?></div>
    <div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:8px;text-transform:uppercase;">RAPPROCHEMENT BANCAIRE</div>
    <div style="font-size:14px;font-weight:600;">Mois de : <?= date('F Y',strtotime($date_d)) ?></div>
  </div>
  <table class="rpt-table">
    <thead><tr><th>Agence</th><th>Nb Brd.</th><th>Total Retenues</th><th>Nb Versements</th><th>Total Versements</th><th>Décaissements</th><th>Recette Agence</th><th>Écart (Ret.−Vers.)</th></tr></thead>
    <tbody>
      <?php $tot_ret=0;$tot_ver=0;$tot_dec=0;$tot_rec=0;$tot_eca=0; ?>
      <?php foreach($data as $d):
        $tot_ret+=$d['retenue'];$tot_ver+=$d['verse'];$tot_dec+=$d['decaisse'];$tot_rec+=$d['recette'];$tot_eca+=$d['ecart'];
      ?>
      <tr>
        <td><span class="badge b-blue"><?= h($d['code']) ?></span> <?= h($d['agence']??'—') ?></td>
        <td style="text-align:center;"><?= $d['nb_brd'] ?></td>
        <td style="text-align:right;font-weight:600;color:var(--primary);"><?= moneyRaw($d['retenue']) ?></td>
        <td style="text-align:center;"><?= $d['nb_vers'] ?></td>
        <td style="text-align:right;font-weight:600;color:var(--success);"><?= moneyRaw($d['verse']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($d['decaisse']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($d['recette']) ?></td>
        <td style="text-align:right;font-weight:700;color:<?= $d['ecart']>0?'var(--danger)':($d['ecart']<0?'var(--success)':'var(--text)') ?>;"><?= moneyRaw($d['ecart']) ?><?= $d['ecart']>0?' ⚠':($d['ecart']<0?' ✓':'') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($data)): ?><tr><td colspan="8" class="t-empty">Aucune donnée pour cette période</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr class="rpt-total">
      <td style="padding:8px 10px;"><strong>TOTAUX</strong></td>
      <td></td>
      <td style="text-align:right;padding:8px 10px;"><strong><?= moneyRaw($tot_ret) ?></strong></td>
      <td></td>
      <td style="text-align:right;padding:8px 10px;"><strong><?= moneyRaw($tot_ver) ?></strong></td>
      <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($tot_dec) ?></td>
      <td style="text-align:right;padding:8px 10px;"><?= moneyRaw($tot_rec) ?></td>
      <td style="text-align:right;padding:8px 10px;font-size:14px;color:<?= $tot_eca>0?'#fca5a5':($tot_eca<0?'#86efac':'#fff') ?>;"><strong><?= moneyRaw($tot_eca) ?></strong></td>
    </tr></tfoot>
  </table>
  <div style="margin-top:12px;padding:12px;background:#fef3c7;border-radius:var(--radius);font-size:12px;">
    <strong>Légende :</strong> ⚠ Écart positif = versement insuffisant (agence doit de l'argent) | ✓ Écart négatif = trop versé (crédit agence)
  </div>
  <div class="rpt-sign" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:24px;">
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Comptabilité</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Le DAF</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Direction</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
