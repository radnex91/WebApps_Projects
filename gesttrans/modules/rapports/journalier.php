<?php
// modules/rapports/journalier.php — RAPPORT JOURNALIER PAR AGENCE
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.agence');
$pageTitle='Rapport Journalier par Agence';
$aid=getUserAgenceId();
$date=$_GET['date']??date('Y-m-d');
$ag_sel=(int)($_GET['ag']??$aid??0);
$appName=getParam('nom_entreprise',APP_NAME);
$agences=$pdo->query("SELECT id,nom,code,ville FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$agence=null;
if($ag_sel){$s=$pdo->prepare("SELECT * FROM agences WHERE id=?");$s->execute([$ag_sel]);$agence=$s->fetch();}

$brd_jour=[]; $stats=[]; $versements=[]; $depenses=[];
if($agence){
    // Bordereaux du jour
    $brd_jour=$pdo->query("SELECT b.*,v.immatriculation,g.nom as groupe,aa.nom as ag_arr FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id WHERE b.agence_depart_id={$ag_sel} AND b.date='$date' ORDER BY b.num_bordereau")->fetchAll();
    // Stats
    $s=$pdo->query("SELECT COUNT(*) as nb, COALESCE(SUM(nb_passagers),0) as pass, COALESCE(SUM(nb_billets_gratuits),0) as grat, COALESCE(SUM(recette_totale),0) as r_brut, COALESCE(SUM(carburant),0) as carb, COALESCE(SUM(peage_total),0) as peage, COALESCE(SUM(retenue_agence),0) as retenue, COALESCE(SUM(ration_chauffeur),0) as ration, COALESCE(SUM(autres_depenses),0) as autres, COALESCE(SUM(recette_nette),0) as r_nette FROM bordereaux WHERE agence_depart_id=$ag_sel AND date='$date'");
    $stats=$s->fetch()??[];
    // Versements
    $versements=$pdo->query("SELECT * FROM versements WHERE agence_id=$ag_sel AND date='$date' ORDER BY created_at")->fetchAll();
    $tot_vers=array_sum(array_column($versements,'versement_agence'));
    // Dépenses approuvées
    $depenses=$pdo->query("SELECT d.*,CONCAT(p.prenom,' ',p.nom) as employe_nom FROM depenses d LEFT JOIN personnel p ON d.employe_id=p.id WHERE d.agence_id=$ag_sel AND d.date_depense='$date' AND d.statut IN ('approuve','paye') ORDER BY d.created_at")->fetchAll();
    $tot_dep=array_sum(array_column($depenses,'montant'));
}
include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Journalier</div>

<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Agence</label>
        <select name="ag" class="fc" style="min-width:250px;">
          <option value="">— Sélectionner une agence —</option>
          <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $ag_sel==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label class="flbl">Date</label><input type="date" name="date" class="fc" value="<?= $date ?>"></div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-search"></i> Générer</button>
      <?php if($agence&&!empty($brd_jour)): ?>
      <button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer PDF</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if(!$agence): ?>
<div class="empty card"><i class="fas fa-building"></i><h3 style="margin-top:12px;">Sélectionnez une agence et une date</h3></div>
<?php else: ?>

<div id="rapport-zone" style="background:#fff;padding:20px;border-radius:var(--radius-lg);border:1px solid var(--border);">

  <!-- EN-TÊTE RAPPORT -->
  <div class="rpt-header">
    <div class="rpt-title"><?= h($appName) ?></div>
    <div class="rpt-subtitle"><?= h($agence['nom']) ?> — <?= h($agence['ville']??'') ?></div>
    <div style="font-size:18px;font-weight:900;margin-top:8px;color:var(--primary);text-transform:uppercase;">
      RAPPORT JOURNALIER D'AGENCE
    </div>
    <div style="font-size:14px;font-weight:600;margin-top:4px;">Date : <?= fdate($date) ?></div>
    <div style="font-size:11px;color:var(--text3);">Généré le <?= date('d/m/Y à H:i') ?></div>
  </div>

  <!-- SYNTHÈSE FINANCIÈRE -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;">I. Synthèse financière</div>
  <table class="rpt-table">
    <tr><th style="width:60%;">Désignation</th><th>Montant (FCFA)</th></tr>
    <tr><td>Recette totale brute (<?= $stats['nb']??0 ?> bordereaux)</td><td style="text-align:right;font-weight:700;"><?= moneyRaw($stats['r_brut']??0) ?></td></tr>
    <tr><td>(−) Total Carburant</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['carb']??0) ?>)</td></tr>
    <tr><td>(−) Total Péages</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['peage']??0) ?>)</td></tr>
    <tr><td>(−) Total Retenues agences</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['retenue']??0) ?>)</td></tr>
    <tr><td>(−) Total Rations chauffeurs</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['ration']??0) ?>)</td></tr>
    <tr><td>(−) Autres dépenses bordereaux</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($stats['autres']??0) ?>)</td></tr>
    <tr class="rpt-total"><td><strong>RECETTE NETTE TOTALE</strong></td><td style="text-align:right;font-size:16px;"><strong><?= moneyRaw($stats['r_nette']??0) ?></strong></td></tr>
    <?php if(!empty($versements)): $tv=array_sum(array_column($versements,'versement_agence')); ?>
    <tr><td>Total versements bancaires</td><td style="text-align:right;color:#d97706;font-weight:700;">(<?= moneyRaw($tv) ?>)</td></tr>
    <?php endif; ?>
    <?php if(!empty($depenses)): $td2=array_sum(array_column($depenses,'montant')); ?>
    <tr><td>Total dépenses imputées</td><td style="text-align:right;color:#dc2626;font-weight:700;">(<?= moneyRaw($td2) ?>)</td></tr>
    <?php endif; ?>
  </table>

  <!-- PASSAGERS -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;margin-top:16px;">II. Statistiques voyageurs</div>
  <table class="rpt-table">
    <tr><th>Indicateur</th><th>Valeur</th></tr>
    <tr><td>Nombre de bordereaux</td><td><strong><?= $stats['nb']??0 ?></strong></td></tr>
    <tr><td>Nombre total de passagers</td><td><strong><?= $stats['pass']??0 ?></strong></td></tr>
    <tr><td>Dont billets gratuits</td><td><?= $stats['grat']??0 ?></td></tr>
  </table>

  <!-- DÉTAIL BORDEREAUX -->
  <?php if(!empty($brd_jour)): ?>
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;margin-top:16px;">III. Détail des bordereaux (<?= count($brd_jour) ?>)</div>
  <table class="rpt-table">
    <thead><tr><th>N°</th><th>Code</th><th>Véhicule</th><th>Groupe</th><th>Destination</th><th>Pass.</th><th>Gratuits</th><th>Recette brute</th><th>Carburant</th><th>Péages</th><th>Retenue</th><th>Ration</th><th>Autres</th><th>Nette</th></tr></thead>
    <tbody>
      <?php foreach($brd_jour as $b): ?>
      <tr>
        <td><strong><?= $b['num_bordereau'] ?></strong></td>
        <td style="font-size:10px;"><?= h($b['code_bordereau']??'—') ?></td>
        <td><code><?= h($b['immatriculation']??'—') ?></code></td>
        <td style="font-size:10px;"><?= h($b['groupe']??'—') ?></td>
        <td><?= h($b['ag_arr']??'—') ?></td>
        <td style="text-align:center;"><?= $b['nb_passagers'] ?></td>
        <td style="text-align:center;"><?= $b['nb_billets_gratuits'] ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['recette_totale']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['carburant']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['peage_total']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['retenue_agence']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['ration_chauffeur']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($b['autres_depenses']) ?></td>
        <td style="text-align:right;font-weight:700;color:var(--success);"><?= moneyRaw($b['recette_nette']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="rpt-total">
        <td colspan="5" style="text-align:right;padding:6px 10px;">TOTAL :</td>
        <td style="padding:6px 10px;text-align:center;"><?= $stats['pass']??0 ?></td>
        <td style="padding:6px 10px;text-align:center;"><?= $stats['grat']??0 ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['r_brut']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['carb']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['peage']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['retenue']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['ration']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;"><?= moneyRaw($stats['autres']??0) ?></td>
        <td style="text-align:right;padding:6px 10px;font-size:14px;"><?= moneyRaw($stats['r_nette']??0) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <!-- VERSEMENTS -->
  <?php if(!empty($versements)): ?>
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;margin-top:16px;">IV. Versements bancaires</div>
  <table class="rpt-table">
    <thead><tr><th>Référence</th><th>Versement agence</th><th>Décaissement</th><th>Recette agence</th><th>Quittance</th><th>Statut</th></tr></thead>
    <tbody>
      <?php foreach($versements as $v): ?>
      <tr>
        <td><?= h($v['ref_versement']??'—') ?></td>
        <td style="text-align:right;font-weight:700;"><?= moneyRaw($v['versement_agence']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($v['decaissement_agence']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($v['recette_agence']) ?></td>
        <td><?= h($v['quittance']??'—') ?></td>
        <td><span class="st-<?= $v['statut'] ?>"><?= h($v['statut']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="rpt-total"><td><strong>TOTAL VERSEMENTS</strong></td><td style="text-align:right;padding:6px 10px;"><strong><?= moneyRaw(array_sum(array_column($versements,'versement_agence'))) ?></strong></td><td colspan="4"></td></tr></tfoot>
  </table>
  <?php endif; ?>

  <!-- DÉPENSES -->
  <?php if(!empty($depenses)): ?>
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--primary);border-bottom:2px solid var(--primary);padding-bottom:4px;margin-bottom:10px;margin-top:16px;">V. Dépenses imputées</div>
  <table class="rpt-table">
    <thead><tr><th>Type</th><th>Objet</th><th>Ordonnateur</th><th>Mode paiement</th><th>Montant</th></tr></thead>
    <tbody>
      <?php foreach($depenses as $d): ?>
      <tr>
        <td><?= h($d['type_depense']) ?></td>
        <td><?= h($d['objet']) ?></td>
        <td><?= h($d['employe_nom']??'—') ?></td>
        <td><?= h($d['mode_paiement']) ?></td>
        <td style="text-align:right;font-weight:700;color:#dc2626;"><?= moneyRaw($d['montant']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="rpt-total"><td colspan="4"><strong>TOTAL DÉPENSES</strong></td><td style="text-align:right;padding:6px 10px;"><strong><?= moneyRaw(array_sum(array_column($depenses,'montant'))) ?></strong></td></tr></tfoot>
  </table>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <div class="rpt-sign" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:24px;">
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">L'Opérateur de saisie</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Le Chef d'Agence</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Direction</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div><div style="font-size:10px;color:#9ca3af;">Nom & Cachet</div></div>
  </div>

  <div style="text-align:center;font-size:10px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:8px;margin-top:14px;">
    Rapport généré par <?= h($appName) ?> le <?= date('d/m/Y à H:i') ?> — Confidentiel
  </div>
</div>
<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
