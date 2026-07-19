<?php
// includes/dashboard-pharmacien.php
// Dashboard pharmacien : stock, commandes, tendances de vente. Pas de CA.

requireLogin();
$db = getDB();
require_once __DIR__ . '/charts.php';

// Alertes stock
$alertes  = $db->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn();
$ruptures = $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn();

// Commandes en attente
$cmd_attente = $db->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')")->fetchColumn();

// Ventes 7j (tendance)
$ventes7 = $db->query("
  SELECT DATE(created_at) AS jour, SUM(total) AS total, COUNT(*) AS nb
  FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at) ORDER BY jour
")->fetchAll();

// Ventes 30 derniers jours (courbe d'évolution)
$v30rows = $db->query("
  SELECT DATE(created_at) AS jour, COUNT(*) AS nb
  FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
  GROUP BY DATE(created_at) ORDER BY jour
")->fetchAll();
$v30 = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('j M', strtotime($d));
    $v30[$lbl] = 0;
}
foreach ($v30rows as $r) {
    $lbl = date('j M', strtotime($r['jour']));
    if (isset($v30[$lbl])) $v30[$lbl] = (int)$r['nb'];
}

// Stock critique
$critique = $db->query("
  SELECT p.nom, p.stock, p.seuil_alerte, c.nom AS cat
  FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id
  WHERE p.stock <= p.seuil_alerte AND p.actif=1
  ORDER BY p.stock ASC LIMIT 6
")->fetchAll();

// Top 5 produits (30j)
$top = $db->query("
  SELECT vl.produit_nom, SUM(vl.quantite) AS qte
  FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id
  WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5
")->fetchAll();
$maxQ = $top ? max(array_column($top, 'qte')) : 1;

// Prepare les jours pour le mini graphique
$jours = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $jours[$d] = ['total' => 0, 'nb' => 0, 'label' => date('D', strtotime($d))];
}
$fr = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'];
foreach ($ventes7 as $v) {
    $jours[$v['jour']] = ['total' => $v['total'], 'nb' => $v['nb'], 'label' => $jours[$v['jour']]['label'] ?? ''];
}
$maxV = max(array_column($jours, 'nb')) ?: 1;

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-red">
    <div class="stat-icon" style="color:var(--red);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes stock</div>
    <div class="stat-value c-red"><?= $alertes ?></div>
    <div class="stat-sub"><?= $ruptures ?> en rupture totale</div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('clipboard',28) ?></div>
    <div class="stat-label">Commandes en attente</div>
    <div class="stat-value c-blue"><?= $cmd_attente ?></div>
    <div class="stat-sub">à traiter</div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-label">Ventes — 7 derniers jours</div>
    <div class="chart-wrap" style="padding:8px 0 0;">
      <div class="chart-bars" style="height:52px;">
        <?php
        $colors = ['var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)', 'var(--teal)'];
        $ci = 0;
        foreach ($jours as $d => $j):
          $pct = $maxV > 0 ? round($j['nb'] / $maxV * 44) : 4;
          $lbl = $fr[$j['label']] ?? $j['label'];
        ?>
        <div class="bar-wrap" title="<?= $lbl ?> : <?= $j['nb'] ?> ventes">
          <div class="bar" style="height:<?= max($pct, 4) ?>px;background:<?= $colors[$ci++ % 7] ?>;opacity:.8;"></div>
          <div class="bar-label"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="stat-sub">tendance hebdomadaire</div>
  </div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">Évolution des ventes — 30 derniers jours</div>
    <span class="badge" style="background:var(--blue-dim);color:var(--blue);">
      <?= array_sum($v30) ?> ventes
    </span>
  </div>
  <div class="line-chart-wrap">
    <?php renderLineChart($v30, 'var(--blue)', '', 'ventes'); ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Stock critique</div>
      <a href="<?= url('stock') ?>" class="btn btn-ghost btn-xs">Voir tout</a>
    </div>
    <?php if ($critique): foreach ($critique as $p): ?>
    <div style="padding:9px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:13px;font-weight:500;"><?= e($p['nom']) ?></div>
        <div class="text-xs"><?= e($p['cat'] ?? '—') ?> · seuil : <?= $p['seuil_alerte'] ?></div>
      </div>
      <span class="badge <?= $p['stock'] == 0 ? 'badge-red' : 'badge-gold' ?>"><?= $p['stock'] ?> u.</span>
    </div>
    <?php endforeach; else: ?>
    <div class="empty">
      <div style="color:var(--teal2);margin-bottom:8px;"><?= icon('check',32) ?></div>
      <div>Aucune alerte de stock</div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Top 5 produits (30j)</div></div>
    <div class="card-pad">
      <?php if ($top): foreach ($top as $p): ?>
      <div style="margin-bottom:13px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:4px;">
          <span><?= e($p['produit_nom']) ?></span>
          <span class="fw-mono c-teal"><?= $p['qte'] ?> ventes</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= round($p['qte'] / $maxQ * 100) ?>%;background:linear-gradient(90deg,var(--teal),var(--blue));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty"><div style="color:var(--text3);margin-bottom:8px;"><?= icon('chart',32) ?></div><div>Aucune donnée</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
