<?php
// modules/rapports/actionnaires.php — Situation des Actionnaires
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.direction');
$pageTitle = 'Situation des Actionnaires';
$appName = getParam('nom_entreprise', APP_NAME);
$mois  = $_GET['mois'] ?? date('Y-m');
$grp_f = (int)($_GET['grp'] ?? 0);
$date_d = $mois.'-01'; $date_f = date('Y-m-t', strtotime($date_d));

$groupes = $pdo->query("SELECT id,nom FROM groupes WHERE actif=1 ORDER BY nom")->fetchAll();
$wG = $grp_f ? "AND g.id=$grp_f" : "";

// Recettes nettes par groupe via bordereaux
$par_grp=$pdo->query("SELECT g.nom as groupe,g.nom_contact,g.banque,g.num_compte_bancaire,COUNT(DISTINCT v.id) as nb_veh,COUNT(b.id) as nb_brd,COALESCE(SUM(b.nb_passagers),0) as pass,COALESCE(SUM(b.recette_totale),0) as r_brut,COALESCE(SUM(b.carburant),0) as carb,COALESCE(SUM(b.peage_total),0) as peage,COALESCE(SUM(b.retenue_agence),0) as retenue,COALESCE(SUM(b.ration_chauffeur),0) as ration,COALESCE(SUM(b.autres_depenses),0) as autres,COALESCE(SUM(b.recette_nette),0) as r_nette FROM groupes g LEFT JOIN vehicules v ON v.groupe_id=g.id LEFT JOIN bordereaux b ON b.vehicule_id=v.id AND b.date BETWEEN '$date_d' AND '$date_f' WHERE g.actif=1 $wG GROUP BY g.id ORDER BY r_nette DESC")->fetchAll();

// Bons actionnaires
$bons=$pdo->query("SELECT g.nom as groupe,SUM(ba.montant) as total_bons,COUNT(ba.id) as nb FROM bons_actionnaires ba JOIN groupes g ON ba.groupe_id=g.id WHERE ba.date_paiement BETWEEN '$date_d' AND '$date_f' GROUP BY ba.groupe_id")->fetchAll();
$bons_map=[];
foreach($bons as $b) $bons_map[$b['groupe']]=$b;

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports<span class="breadcrumb-sep">/</span>Actionnaires</div>
<div class="no-print card" style="margin-bottom:14px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>"></div>
      <select name="grp" class="fc" style="width:auto;"><option value="">Tous les groupes</option><?php foreach($groupes as $g): ?><option value="<?= $g['id'] ?>" <?= $grp_f==$g['id']?'selected':'' ?>><?= h($g['nom']) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-sync"></i> Actualiser</button>
      <button type="button" onclick="window.print()" class="btn btn-info" style="align-self:flex-end;"><i class="fas fa-print"></i> Imprimer</button>
    </form>
  </div>
</div>
<div style="background:#fff;padding:20px;border-radius:var(--radius-lg);border:1px solid var(--border);">
  <div class="rpt-header">
    <div class="rpt-title"><?= h($appName) ?></div>
    <div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:8px;text-transform:uppercase;">SITUATION DES ACTIONNAIRES</div>
    <div style="font-size:14px;font-weight:600;">Mois de : <?= date('F Y',strtotime($date_d)) ?></div>
  </div>

  <?php foreach($par_grp as $g): $bon=$bons_map[$g['groupe']]??null; ?>
  <div style="margin-bottom:20px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
    <!-- En-tête groupe -->
    <div style="background:#1e3a8a;color:#fff;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
      <div><strong style="font-size:14px;"><?= h($g['groupe']) ?></strong> <span style="font-size:11px;opacity:.8;">— <?= h($g['nom_contact']??'') ?></span></div>
      <div style="text-align:right;font-size:11px;opacity:.8;"><?= h($g['banque']??'') ?> <?= h($g['num_compte_bancaire']??'') ?></div>
    </div>
    <!-- Détail -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);background:#f8fafc;">
      <?php $items=[['Véhicules',$g['nb_veh'],'#'],['Voyages',$g['nb_brd'],''],['Passagers',number_format($g['pass']),''],['Recette brute',moneyRaw($g['r_brut']),'FCFA']]; foreach($items as [$k,$v,$u]): ?>
      <div style="padding:12px;border-right:1px solid #e5e7eb;text-align:center;">
        <div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;"><?= $k ?></div>
        <div style="font-size:16px;font-weight:700;color:#1e3a8a;"><?= $v ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="padding:12px 14px;display:grid;grid-template-columns:repeat(6,1fr);gap:6px;background:#fff;font-size:11px;">
      <?php $fins=[['Carburant',moneyRaw($g['carb']),'#dc2626'],['Péages',moneyRaw($g['peage']),'#dc2626'],['Retenue',moneyRaw($g['retenue']),'#dc2626'],['Ration chauf.',moneyRaw($g['ration']),'#dc2626'],['Autres',moneyRaw($g['autres']),'#dc2626'],['Recette nette',moneyRaw($g['r_nette']).' FCFA','#16a34a']];
      foreach($fins as [$k,$v,$c]): ?>
      <div style="text-align:center;padding:6px;background:<?= $c==='#16a34a'?'#f0fdf4':'#fef2f2' ?>;border-radius:6px;">
        <div style="color:#6b7280;margin-bottom:2px;"><?= $k ?></div>
        <div style="font-weight:700;color:<?= $c ?>;"><?= $v ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if($bon): ?>
    <div style="background:#fffbeb;padding:8px 14px;font-size:12px;border-top:1px solid #fde68a;">
      🤝 <strong>Bons actionnaires ce mois :</strong> <?= $bon['nb'] ?> bon(s) — Total : <strong style="color:#d97706;"><?= moneyRaw($bon['total_bons']) ?> FCFA</strong>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if(empty($par_grp)): ?><div style="text-align:center;padding:40px;color:#9ca3af;"><i class="fas fa-layer-group" style="font-size:36px;opacity:.2;display:block;margin-bottom:10px;"></i>Aucune donnée pour cette période</div><?php endif; ?>

  <div class="rpt-sign" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:24px;">
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Comptabilité</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Le DAF</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
    <div style="text-align:center;"><div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">La Direction Générale</div><div style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div></div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
