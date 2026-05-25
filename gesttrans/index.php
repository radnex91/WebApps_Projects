<?php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'Tableau de bord';
$today = date('Y-m-d'); $month = date('Y-m');
$aid = getUserAgenceId();
$wA = $aid ? "AND agence_depart_id=$aid" : "";
$wD = $aid ? "AND agence_id=$aid" : "";

// ── Stats du jour ─────────────────────────────────────────────
$stats['brd_jour']   = $pdo->query("SELECT COUNT(*) FROM bordereaux WHERE date='$today' $wA")->fetchColumn();
$stats['recette_jour']= $pdo->query("SELECT COALESCE(SUM(recette_totale),0) FROM bordereaux WHERE date='$today' $wA")->fetchColumn();
$stats['nette_jour'] = $pdo->query("SELECT COALESCE(SUM(recette_nette),0) FROM bordereaux WHERE date='$today' $wA")->fetchColumn();
$stats['pass_jour']  = $pdo->query("SELECT COALESCE(SUM(nb_passagers),0) FROM bordereaux WHERE date='$today' $wA")->fetchColumn();
$stats['versements_att']= $pdo->query("SELECT COUNT(*) FROM versements WHERE statut='en_attente' $wD")->fetchColumn();
$stats['dep_att']    = $pdo->query("SELECT COUNT(*) FROM depenses WHERE statut='en_attente' $wD")->fetchColumn();

// ── Totaux du mois ────────────────────────────────────────────
$stats['recette_mois']= $pdo->query("SELECT COALESCE(SUM(recette_totale),0) FROM bordereaux WHERE DATE_FORMAT(date,'%Y-%m')='$month' $wA")->fetchColumn();
$stats['nette_mois']  = $pdo->query("SELECT COALESCE(SUM(recette_nette),0) FROM bordereaux WHERE DATE_FORMAT(date,'%Y-%m')='$month' $wA")->fetchColumn();
$stats['pass_mois']   = $pdo->query("SELECT COALESCE(SUM(nb_passagers),0) FROM bordereaux WHERE DATE_FORMAT(date,'%Y-%m')='$month' $wA")->fetchColumn();
$stats['brd_mois']    = $pdo->query("SELECT COUNT(*) FROM bordereaux WHERE DATE_FORMAT(date,'%Y-%m')='$month' $wA")->fetchColumn();

// ── Derniers bordereaux ───────────────────────────────────────
$lastBrd=$pdo->query("SELECT b.*,v.immatriculation,ad.nom as ag_dep,aa.nom as ag_arr,CONCAT(u.prenom,' ',u.nom) as saisie_par FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id LEFT JOIN users u ON b.saisie_par=u.id WHERE 1=1 ".($aid?"AND b.agence_depart_id=$aid":"")." ORDER BY b.created_at DESC LIMIT 8")->fetchAll();

// ── Recettes 7 jours ──────────────────────────────────────────
$r7=$pdo->query("SELECT date, SUM(recette_totale) as t, SUM(recette_nette) as n, SUM(nb_passagers) as p, COUNT(*) as nb FROM bordereaux WHERE date>=DATE_SUB('$today',INTERVAL 6 DAY) ".($aid?"AND agence_depart_id=$aid":"")." GROUP BY date ORDER BY date")->fetchAll();

// ── Stats par agence (direction seulement) ────────────────────
$parAgence=[];
if(can('rapports.direction')){
    $parAgence=$pdo->query("SELECT a.code,a.nom,a.ville,COUNT(b.id) as nb_brd,COALESCE(SUM(b.recette_totale),0) as recette,COALESCE(SUM(b.recette_nette),0) as nette,COALESCE(SUM(b.nb_passagers),0) as passagers FROM agences a LEFT JOIN bordereaux b ON b.agence_depart_id=a.id AND DATE_FORMAT(b.date,'%Y-%m')='$month' WHERE a.actif=1 GROUP BY a.id ORDER BY recette DESC LIMIT 10")->fetchAll();
}

// ── Top véhicules ─────────────────────────────────────────────
$topVeh=$pdo->query("SELECT v.immatriculation,g.nom as groupe,COUNT(b.id) as nb,COALESCE(SUM(b.recette_nette),0) as nette FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN bordereaux b ON b.vehicule_id=v.id AND DATE_FORMAT(b.date,'%Y-%m')='$month' WHERE v.actif=1 GROUP BY v.id HAVING nb>0 ORDER BY nette DESC LIMIT 6")->fetchAll();

include 'includes/header.php';
?>
<div class="breadcrumb"><i class="fas fa-home"></i><span class="breadcrumb-sep">/</span>Tableau de bord</div>

<!-- STATS JOUR -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6)"><i class="fas fa-file-invoice"></i></div>
    <div><div class="stat-val"><?= $stats['brd_jour'] ?></div><div class="stat-lbl">Bordereaux aujourd'hui</div><div class="stat-sub"><?= $stats['brd_mois'] ?> ce mois</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-coins"></i></div>
    <div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($stats['recette_jour']) ?></div><div class="stat-lbl">Recette brute du jour</div><div class="stat-sub"><?= moneyRaw($stats['recette_mois']) ?> ce mois</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#0891b2,#38bdf8)"><i class="fas fa-wallet"></i></div>
    <div><div class="stat-val" style="font-size:14px;"><?= moneyRaw($stats['nette_jour']) ?></div><div class="stat-lbl">Recette nette du jour</div><div class="stat-sub"><?= moneyRaw($stats['nette_mois']) ?> ce mois</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-users"></i></div>
    <div><div class="stat-val"><?= $stats['pass_jour'] ?></div><div class="stat-lbl">Passagers aujourd'hui</div><div class="stat-sub"><?= number_format($stats['pass_mois']) ?> ce mois</div></div>
  </div>
  <div class="stat-card" style="<?= $stats['versements_att']>0?'border-color:var(--warning);':'' ?>">
    <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-university"></i></div>
    <div><div class="stat-val"><?= $stats['versements_att'] ?></div><div class="stat-lbl">Versements en attente</div></div>
  </div>
  <div class="stat-card" style="<?= $stats['dep_att']>0?'border-color:var(--warning);':'' ?>">
    <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-val"><?= $stats['dep_att'] ?></div><div class="stat-lbl">Dépenses en attente</div></div>
  </div>
</div>

<!-- GRAPHIQUE + AGENCES -->
<div class="grid-2" style="margin-bottom:20px;">
  <!-- GRAPHIQUE 7 JOURS -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Recettes — 7 derniers jours</h3></div>
    <div class="card-body">
      <?php $maxR=max(1,max(array_column($r7,'t')?:[1])); ?>
      <div style="display:flex;align-items:flex-end;gap:6px;height:120px;margin-bottom:8px;">
        <?php foreach($r7 as $r): $h=round($r['t']/$maxR*90); $hn=round($r['n']/$maxR*90); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;">
          <div style="font-size:9px;color:var(--text3);"><?= number_format($r['t']/1000,0) ?>K</div>
          <div style="width:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100px;gap:1px;">
            <div style="width:70%;height:<?= $h ?>px;background:var(--primary);border-radius:3px 3px 0 0;opacity:.6;min-height:3px;" title="Recette brute: <?= money($r['t']) ?>"></div>
            <div style="width:70%;height:<?= $hn ?>px;background:var(--success);border-radius:0;min-height:2px;margin-top:-<?= $hn ?>px;opacity:.9;" title="Recette nette: <?= money($r['n']) ?>"></div>
          </div>
          <div style="font-size:9px;color:var(--text3);"><?= date('d/m',strtotime($r['date'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($r7)): ?><div style="width:100%;text-align:center;color:var(--text3);font-size:12px;padding:20px;">Aucune donnée</div><?php endif; ?>
      </div>
      <div style="display:flex;gap:12px;font-size:11px;">
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--primary);border-radius:2px;opacity:.6;margin-right:4px;"></span>Brute</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--success);border-radius:2px;margin-right:4px;"></span>Nette</span>
      </div>
    </div>
  </div>

  <!-- PAR AGENCE ou TOP VEHICULES -->
  <?php if(!empty($parAgence)): ?>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-building"></i> Recettes ce mois par agence</h3></div>
    <div class="card-body" style="padding:0;">
      <?php $maxA=max(1,max(array_column($parAgence,'recette'))); ?>
      <table><thead><tr><th>Agence</th><th>Bordereaux</th><th>Recette nette</th></tr></thead><tbody>
        <?php foreach($parAgence as $a): $pct=round($a['recette']/$maxA*100); ?>
        <tr>
          <td><span class="badge b-blue"><?= h($a['code']) ?></span> <?= h($a['ville']) ?></td>
          <td style="text-align:center;"><?= $a['nb_brd'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--success)"></div></div>
              <span style="font-size:11px;white-space:nowrap;"><?= moneyRaw($a['nette']) ?></span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($parAgence)): ?><tr><td colspan="3" class="t-empty">Aucune donnée</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-bus"></i> Top véhicules ce mois</h3></div>
    <div class="card-body" style="padding:0;">
      <table><thead><tr><th>Véhicule</th><th>Groupe</th><th>Voyages</th><th>Recette nette</th></tr></thead><tbody>
        <?php foreach($topVeh as $i=>$v): ?>
        <tr>
          <td><code><?= h($v['immatriculation']) ?></code></td>
          <td style="font-size:12px;"><?= h($v['groupe']??'—') ?></td>
          <td style="text-align:center;"><?= $v['nb'] ?></td>
          <td style="font-weight:600;color:var(--success);"><?= moneyRaw($v['nette']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topVeh)): ?><tr><td colspan="4" class="t-empty">Aucun voyage ce mois</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- DERNIERS BORDEREAUX -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h3><i class="fas fa-file-invoice"></i> Derniers bordereaux</h3>
    <div style="display:flex;gap:8px;">
      <?php if(can('bordereaux.create')): ?><a href="<?= BASE_URL ?>modules/bordereaux/ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau bordereau</a><?php endif; ?>
      <a href="<?= BASE_URL ?>modules/bordereaux/" class="btn btn-ghost btn-sm">Voir tout</a>
    </div>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Date</th><th>Véhicule</th><th>Trajet</th><th>Passagers</th><th>Recette totale</th><th>Recette nette</th><th>Saisi par</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($lastBrd as $b): ?>
        <tr>
          <td><strong><?= $b['num_bordereau'] ?></strong></td>
          <td><?= fdate($b['date']) ?></td>
          <td><code style="font-size:11px;"><?= h($b['immatriculation']??'—') ?></code></td>
          <td><?= h($b['ag_dep']??'—') ?> → <?= h($b['ag_arr']??'—') ?></td>
          <td style="text-align:center;"><?= $b['nb_passagers'] ?></td>
          <td style="font-weight:600;"><?= moneyRaw($b['recette_totale']) ?></td>
          <td style="font-weight:700;color:var(--success);"><?= moneyRaw($b['recette_nette']) ?></td>
          <td style="font-size:12px;color:var(--text3);"><?= h($b['saisie_par']??'—') ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="<?= BASE_URL ?>modules/bordereaux/voir.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a>
              <?php if(can('bordereaux.edit')): ?><a href="<?= BASE_URL ?>modules/bordereaux/modifier.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
              <a href="<?= BASE_URL ?>modules/bordereaux/imprimer.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-print"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($lastBrd)): ?><tr><td colspan="9" class="t-empty"><i class="fas fa-file-invoice"></i>Aucun bordereau enregistré</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- ACCÈS RAPIDE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-bolt"></i> Accès rapide</h3></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;">
    <?php if(can('bordereaux.create')): ?><a href="<?= BASE_URL ?>modules/bordereaux/ajouter.php" class="btn btn-primary btn-lg"><i class="fas fa-file-invoice"></i> Saisir un bordereau</a><?php endif; ?>
    <?php if(can('versements.create')): ?><a href="<?= BASE_URL ?>modules/versements/ajouter.php" class="btn btn-success btn-lg"><i class="fas fa-university"></i> Versement bancaire</a><?php endif; ?>
    <?php if(can('depenses.create')): ?><a href="<?= BASE_URL ?>modules/depenses/ajouter.php" class="btn btn-warning btn-lg"><i class="fas fa-money-bill-wave"></i> Nouvelle dépense</a><?php endif; ?>
    <?php if(can('rapports.agence')): ?><a href="<?= BASE_URL ?>modules/rapports/journalier.php" class="btn btn-info btn-lg"><i class="fas fa-chart-bar"></i> Rapport journalier</a><?php endif; ?>
    <?php if(can('rapports.direction')): ?><a href="<?= BASE_URL ?>modules/rapports/direction.php" class="btn btn-purple btn-lg"><i class="fas fa-chart-line"></i> Rapport direction</a><?php endif; ?>
    <?php if(can('bons.manage')): ?><a href="<?= BASE_URL ?>modules/bons/ajouter.php" class="btn btn-orange btn-lg"><i class="fas fa-handshake"></i> Bon actionnaire</a><?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
