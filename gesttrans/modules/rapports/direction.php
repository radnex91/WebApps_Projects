<?php
// modules/rapports/direction.php — Rapports Direction / Compte d'exploitation
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.direction');
$pageTitle = 'Rapports Direction';
$appName = getParam('nom_entreprise', APP_NAME);

$mois   = $_GET['mois'] ?? date('Y-m');
$ag_sel = (int)($_GET['ag'] ?? 0);
$date_d = $mois.'-01';
$date_f = date('Y-m-t', strtotime($date_d));

$agences = $pdo->query("SELECT id,nom,code,ville FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();

// ── Stats globales ──────────────────────────────────────────
$wA  = $ag_sel ? "AND b.agence_depart_id=$ag_sel" : "";
$wDep= $ag_sel ? "AND d.agence_id=$ag_sel" : "";
$wVer= $ag_sel ? "AND v.agence_id=$ag_sel" : "";

$global=$pdo->query("SELECT COUNT(*) as nb_brd,COALESCE(SUM(nb_passagers),0) as pass,COALESCE(SUM(recette_totale),0) as r_brut,COALESCE(SUM(carburant),0) as carb,COALESCE(SUM(peage_total),0) as peage,COALESCE(SUM(retenue_agence),0) as retenue,COALESCE(SUM(ration_chauffeur),0) as ration,COALESCE(SUM(autres_depenses),0) as autres,COALESCE(SUM(recette_nette),0) as r_nette FROM bordereaux b WHERE b.date BETWEEN '$date_d' AND '$date_f' $wA")->fetch();
$tot_dep=$pdo->query("SELECT COALESCE(SUM(montant),0) FROM depenses d WHERE d.date_depense BETWEEN '$date_d' AND '$date_f' AND d.statut IN ('approuve','paye') $wDep")->fetchColumn();
$tot_ver=$pdo->query("SELECT COALESCE(SUM(versement_agence),0) FROM versements v WHERE v.date BETWEEN '$date_d' AND '$date_f' $wVer")->fetchColumn();
$tot_bons=$pdo->query("SELECT COALESCE(SUM(montant),0) FROM bons_actionnaires WHERE date_paiement BETWEEN '$date_d' AND '$date_f'")->fetchColumn();

// ── Par agence ──────────────────────────────────────────────
$par_agence=$pdo->query("SELECT a.code,a.nom,a.ville,COUNT(b.id) as nb_brd,COALESCE(SUM(b.nb_passagers),0) as pass,COALESCE(SUM(b.recette_totale),0) as r_brut,COALESCE(SUM(b.carburant),0) as carb,COALESCE(SUM(b.peage_total),0) as peage,COALESCE(SUM(b.retenue_agence),0) as retenue,COALESCE(SUM(b.ration_chauffeur),0) as ration,COALESCE(SUM(b.autres_depenses),0) as autres,COALESCE(SUM(b.recette_nette),0) as r_nette FROM agences a LEFT JOIN bordereaux b ON b.agence_depart_id=a.id AND b.date BETWEEN '$date_d' AND '$date_f' WHERE a.actif=1 ".($ag_sel?"AND a.id=$ag_sel":"")." GROUP BY a.id HAVING nb_brd>0 ORDER BY r_nette DESC")->fetchAll();

// ── Par groupe (actionnaires) ───────────────────────────────
$par_groupe=$pdo->query("SELECT g.nom,g.nom_contact,COUNT(b.id) as nb_brd,COALESCE(SUM(b.nb_passagers),0) as pass,COALESCE(SUM(b.recette_totale),0) as r_brut,COALESCE(SUM(b.recette_nette),0) as r_nette FROM groupes g LEFT JOIN vehicules v ON v.groupe_id=g.id LEFT JOIN bordereaux b ON b.vehicule_id=v.id AND b.date BETWEEN '$date_d' AND '$date_f' GROUP BY g.id HAVING nb_brd>0 ORDER BY r_nette DESC LIMIT 15")->fetchAll();

// ── Évolution quotidienne ───────────────────────────────────
$evolution=$pdo->query("SELECT date,SUM(recette_totale) as brut,SUM(recette_nette) as nette,SUM(nb_passagers) as pass,COUNT(*) as nb FROM bordereaux b WHERE date BETWEEN '$date_d' AND '$date_f' $wA GROUP BY date ORDER BY date")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Direction</div>

<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>"></div>
      <select name="ag" class="fc" style="width:auto;">
        <option value="">Toutes les agences</option>
        <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $ag_sel==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-sync"></i> Générer</button>
      <button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer</button>
    </form>
  </div>
</div>

<!-- KPI CARDS -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6)"><i class="fas fa-file-invoice"></i></div><div><div class="stat-val"><?= number_format($global['nb_brd']??0) ?></div><div class="stat-lbl">Bordereaux</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-users"></i></div><div><div class="stat-val"><?= number_format($global['pass']??0) ?></div><div class="stat-lbl">Passagers</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-coins"></i></div><div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($global['r_brut']??0) ?></div><div class="stat-lbl">Recette brute (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#0891b2,#38bdf8)"><i class="fas fa-wallet"></i></div><div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($global['r_nette']??0) ?></div><div class="stat-lbl">Recette nette (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($tot_dep??0) ?></div><div class="stat-lbl">Dépenses approuvées</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-university"></i></div><div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($tot_ver??0) ?></div><div class="stat-lbl">Versements bancaires</div></div></div>
</div>

<!-- ÉVOLUTION GRAPHIQUE -->
<?php if(!empty($evolution)): ?>
<div class="card no-print" style="margin-bottom:18px;">
  <div class="card-header"><h3><i class="fas fa-chart-area"></i> Évolution des recettes — <?= date('F Y',strtotime($date_d)) ?></h3></div>
  <div class="card-body">
    <?php $maxE=max(1,max(array_column($evolution,'brut')?:[1])); ?>
    <div style="display:flex;align-items:flex-end;gap:3px;height:100px;overflow-x:auto;padding-bottom:4px;">
      <?php foreach($evolution as $e): $hb=round($e['brut']/$maxE*90); $hn=round($e['nette']/$maxE*90); ?>
      <div style="flex:0 0 auto;min-width:24px;display:flex;flex-direction:column;align-items:center;gap:1px;" title="<?= fdate($e['date']) ?> | Brute: <?= money($e['brut']) ?> | Nette: <?= money($e['nette']) ?>">
        <div style="width:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:95px;">
          <div style="width:14px;height:<?= $hb ?>px;background:var(--primary);border-radius:2px 2px 0 0;opacity:.5;min-height:2px;"></div>
        </div>
        <div style="font-size:8px;color:var(--text3);white-space:nowrap;"><?= date('d',strtotime($e['date'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
<!-- PAR AGENCE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-building"></i> Résultats par agence — <?= date('F Y',strtotime($date_d)) ?></h3></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Agence</th><th>Brd.</th><th>Pass.</th><th>Recette brute</th><th>Recette nette</th></tr></thead>
    <tbody>
      <?php foreach($par_agence as $a): ?>
      <tr>
        <td><span class="badge b-blue"><?= h($a['code']) ?></span> <?= h($a['ville']) ?></td>
        <td style="text-align:center;"><?= $a['nb_brd'] ?></td>
        <td style="text-align:center;"><?= number_format($a['pass']) ?></td>
        <td style="text-align:right;"><?= moneyRaw($a['r_brut']) ?></td>
        <td style="text-align:right;font-weight:700;color:var(--success);"><?= moneyRaw($a['r_nette']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($par_agence)): ?><tr><td colspan="5" class="t-empty">Aucune donnée</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr style="background:var(--primary);color:#fff;font-weight:700;">
      <td colspan="2" style="padding:7px 12px;">TOTAL</td>
      <td style="padding:7px 12px;text-align:center;"><?= number_format($global['pass']??0) ?></td>
      <td style="padding:7px 12px;text-align:right;"><?= moneyRaw($global['r_brut']??0) ?></td>
      <td style="padding:7px 12px;text-align:right;"><?= moneyRaw($global['r_nette']??0) ?></td>
    </tr></tfoot>
  </table>
  </div></div>
</div>

<!-- PAR GROUPE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-layer-group"></i> Résultats par groupe</h3></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Groupe</th><th>Contact</th><th>Voyages</th><th>Passagers</th><th>Recette nette</th></tr></thead>
    <tbody>
      <?php foreach($par_groupe as $g): ?>
      <tr>
        <td><strong><?= h($g['nom']) ?></strong></td>
        <td style="font-size:12px;"><?= h($g['nom_contact']??'—') ?></td>
        <td style="text-align:center;"><?= $g['nb_brd'] ?></td>
        <td style="text-align:center;"><?= number_format($g['pass']) ?></td>
        <td style="text-align:right;font-weight:700;color:var(--success);"><?= moneyRaw($g['r_nette']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($par_groupe)): ?><tr><td colspan="5" class="t-empty">Aucune donnée</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div></div>
</div>
</div>

<!-- COMPTE D'EXPLOITATION -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-balance-scale"></i> Compte d'exploitation — <?= date('F Y',strtotime($date_d)) ?></h3></div>
  <div class="card-body">
    <table class="rpt-table" style="width:60%;">
      <tr><th>Désignation</th><th>Montant (FCFA)</th></tr>
      <tr style="background:#f0f7ff;"><td>Recette totale brute</td><td style="text-align:right;font-weight:700;"><?= moneyRaw($global['r_brut']??0) ?></td></tr>
      <tr><td>(−) Carburant total</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($global['carb']??0) ?>)</td></tr>
      <tr style="background:#f0f7ff;"><td>(−) Péages total</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($global['peage']??0) ?>)</td></tr>
      <tr><td>(−) Retenues agences</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($global['retenue']??0) ?>)</td></tr>
      <tr style="background:#f0f7ff;"><td>(−) Rations chauffeurs</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($global['ration']??0) ?>)</td></tr>
      <tr><td>(−) Autres dépenses voyages</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($global['autres']??0) ?>)</td></tr>
      <tr class="rpt-total"><td><strong>Recette nette exploitation</strong></td><td style="text-align:right;font-size:14px;"><strong><?= moneyRaw($global['r_nette']??0) ?></strong></td></tr>
      <tr><td>(−) Dépenses agences imputées</td><td style="text-align:right;color:#dc2626;">(<?= moneyRaw($tot_dep??0) ?>)</td></tr>
      <tr style="background:#f0f7ff;"><td>(−) Versements bancaires effectués</td><td style="text-align:right;color:#d97706;">(<?= moneyRaw($tot_ver??0) ?>)</td></tr>
      <tr><td>Bons actionnaires payés</td><td style="text-align:right;color:#7c3aed;"><?= moneyRaw($tot_bons??0) ?></td></tr>
      <?php $solde=($global['r_nette']??0)-($tot_dep??0)-($tot_ver??0); ?>
      <tr style="background:#1e3a8a;color:#fff;font-weight:900;">
        <td style="padding:10px 12px;font-size:14px;">SOLDE NET DISPONIBLE</td>
        <td style="text-align:right;padding:10px 12px;font-size:18px;"><?= moneyRaw($solde) ?></td>
      </tr>
    </table>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
