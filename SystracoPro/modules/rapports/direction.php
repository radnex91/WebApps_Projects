<?php
// modules/rapports/direction.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('rapports.direction');
$pageTitle = 'Rapports Direction';
$mois = $_GET['mois'] ?? date('Y-m');
$agence_f = (int)($_GET['agence_id'] ?? 0);
$appName = getParam('nom_entreprise', APP_NAME);

$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();
$date_d  = $mois.'-01';
$date_f  = date('Y-m-t', strtotime($date_d));

// Stats globales
$wA = $agence_f ? "AND t.agence_id=$agence_f" : "";
$wAV = $agence_f ? "AND v.agence_id=$agence_f" : "";

$stats['recette']     = $pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM tickets t WHERE DATE(date_vente) BETWEEN '$date_d' AND '$date_f' AND statut='vendu' $wA")->fetchColumn();
$stats['tickets']     = $pdo->query("SELECT COUNT(*) FROM tickets t WHERE DATE(date_vente) BETWEEN '$date_d' AND '$date_f' AND statut='vendu' $wA")->fetchColumn();
$stats['annulations'] = $pdo->query("SELECT COUNT(*) FROM tickets t WHERE DATE(date_annulation) BETWEEN '$date_d' AND '$date_f' AND statut='annule' $wA")->fetchColumn();
$stats['voyages']     = $pdo->query("SELECT COUNT(*) FROM voyages v WHERE DATE(date_depart) BETWEEN '$date_d' AND '$date_f' $wAV")->fetchColumn();
$wAD = $agence_f ? "AND agence_id=$agence_f" : "";
$stats['depenses']    = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM depenses WHERE DATE(date_depense) BETWEEN '$date_d' AND '$date_f' AND statut IN ('approuve','paye') $wAD")->fetchColumn();
$stats['versements']  = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM versements WHERE DATE(date_versement) BETWEEN '$date_d' AND '$date_f' $wAD")->fetchColumn();

// Par agence
$par_agence = $pdo->query("SELECT a.nom,a.ville,a.code,COUNT(t.id) as nb_tks,COALESCE(SUM(t.montant_total),0) as recette FROM agences a LEFT JOIN tickets t ON t.agence_id=a.id AND DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' AND t.statut='vendu' WHERE a.actif=1 GROUP BY a.id ORDER BY recette DESC")->fetchAll();

// Recettes par jour (30 derniers jours)
$par_jour = $pdo->query("SELECT DATE(date_vente) as jour, SUM(montant_total) as total, COUNT(*) as nb FROM tickets WHERE statut='vendu' AND DATE(date_vente) BETWEEN '$date_d' AND '$date_f' $wA GROUP BY jour ORDER BY jour")->fetchAll();

// Top destinations
$top_dest = $pdo->query("SELECT IFNULL(ad.ville,a1.ville) as dep, IFNULL(aa.ville,a2.ville) as arr, COUNT(*) as nb_tks, SUM(t.montant_total) as recette FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.statut='vendu' AND DATE(t.date_vente) BETWEEN '$date_d' AND '$date_f' $wA GROUP BY dep, arr ORDER BY nb_tks DESC LIMIT 8")->fetchAll();

// Par mode paiement
$par_mode = $pdo->query("SELECT mode_paiement, COUNT(*) as nb, SUM(montant_total) as total FROM tickets WHERE statut='vendu' AND DATE(date_vente) BETWEEN '$date_d' AND '$date_f' $wA GROUP BY mode_paiement ORDER BY total DESC")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports Direction</div>

<div class="no-print card" style="margin-bottom:16px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="fg"><label class="flbl">Mois</label><input type="month" name="mois" class="fc" value="<?= $mois ?>" style="width:auto;"></div>
      <select name="agence_id" class="fc" style="width:auto;"><option value="">Toutes les agences</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $agence_f==$a['id']?'selected':'' ?>><?= sanitize($a['nom']) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Générer</button>
      <button type="button" onclick="window.print()" class="btn btn-info btn-sm"><i class="fas fa-print"></i> Imprimer</button>
    </form>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-val" style="font-size:15px;"><?= number_format($stats['recette'],0,',',' ') ?></div><div class="stat-lbl">Recette brute (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-ticket-alt"></i></div><div><div class="stat-val"><?= $stats['tickets'] ?></div><div class="stat-lbl">Tickets vendus</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-route"></i></div><div><div class="stat-val"><?= $stats['voyages'] ?></div><div class="stat-lbl">Voyages effectués</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-val" style="font-size:15px;"><?= number_format($stats['depenses'],0,',',' ') ?></div><div class="stat-lbl">Total dépenses</div></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-university"></i></div><div><div class="stat-val" style="font-size:15px;"><?= number_format($stats['versements'],0,',',' ') ?></div><div class="stat-lbl">Total versements</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
<!-- PAR AGENCE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-building"></i> Recettes par agence — <?= moisFrancais($date_d) ?></h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Agence</th><th>Ville</th><th>Tickets</th><th>Recette (FCFA)</th></tr></thead>
      <tbody>
        <?php $maxR=max(1,max(array_column($par_agence,'recette')?:[1])); foreach($par_agence as $ag): $pct=round($ag['recette']/$maxR*100); ?>
        <tr>
          <td><strong><?= sanitize($ag['code']) ?></strong></td>
          <td><?= sanitize($ag['ville']) ?></td>
          <td style="text-align:center;"><?= $ag['nb_tks'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--primary);"></div></div>
              <strong style="font-size:12px;white-space:nowrap;"><?= number_format($ag['recette'],0,',',' ') ?></strong>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:var(--bg);font-weight:700;"><td colspan="2">TOTAL</td><td style="text-align:center;"><?= $stats['tickets'] ?></td><td><strong style="color:var(--success);"><?= number_format($stats['recette'],0,',',' ') ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- TOP DESTINATIONS -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-star"></i> Top destinations</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Trajet</th><th>Tickets</th><th>Recette (FCFA)</th></tr></thead>
      <tbody>
        <?php foreach($top_dest as $i=>$d): ?>
        <tr>
          <td><?= ['🥇','🥈','🥉'][$i]??'📍' ?> <strong><?= sanitize($d['dep'].' → '.$d['arr']) ?></strong></td>
          <td style="text-align:center;"><?= $d['nb_tks'] ?></td>
          <td style="font-weight:600;"><?= number_format($d['recette'],0,',',' ') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($top_dest)): ?><tr><td colspan="3" class="t-empty">Aucune donnée</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- GRAPHIQUE JOURNALIER -->
<?php if(!empty($par_jour)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Évolution journalière des recettes</h3></div>
  <div class="card-body">
    <?php $maxJ=max(1,max(array_column($par_jour,'total'))); ?>
    <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding-bottom:8px;overflow-x:auto;">
      <?php foreach($par_jour as $j): $h=round($j['total']/$maxJ*100); ?>
      <div style="flex:0 0 auto;min-width:28px;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?= fdate($j['jour']) ?> : <?= money($j['total']) ?>">
        <div style="font-size:8px;color:var(--text3);writing-mode:vertical-lr;transform:rotate(180deg);"><?= number_format($j['total']/1000,0) ?>K</div>
        <div style="width:20px;height:<?= $h ?>px;background:var(--primary);border-radius:3px 3px 0 0;min-height:3px;"></div>
        <div style="font-size:8px;color:var(--text3);"><?= date('d/m',strtotime($j['jour'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- PAR MODE PAIEMENT -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-credit-card"></i> Répartition par mode de paiement</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Mode</th><th>Nb tickets</th><th>Montant (FCFA)</th><th>%</th></tr></thead>
      <tbody>
        <?php $modeLabels=['especes'=>'💵 Espèces','om'=>'📱 Orange Money','momo'=>'📱 MTN MoMo','carte'=>'💳 Carte','cheque'=>'📄 Chèque']; foreach($par_mode as $m): $pct2=$stats['recette']>0?round($m['total']/$stats['recette']*100):0; ?>
        <tr>
          <td><?= $modeLabels[$m['mode_paiement']]??sanitize($m['mode_paiement']) ?></td>
          <td style="text-align:center;"><?= $m['nb'] ?></td>
          <td style="font-weight:700;"><?= number_format($m['total'],0,',',' ') ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="width:80px;"><div class="progress-bar" style="width:<?= $pct2 ?>%;background:var(--primary)"></div></div>
              <span style="font-size:12px;"><?= $pct2 ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
