<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('rapports.voir');
$db = getDB();

$annee = (int)($_GET['annee'] ?? date('Y'));

$stmtCA = $db->prepare("
    SELECT MONTH(created_at) AS mois, SUM(total) AS total, COUNT(*) AS nb
    FROM ventes WHERE YEAR(created_at)=?
    GROUP BY MONTH(created_at) ORDER BY mois
");
$stmtCA->execute([$annee]);
$ca_mois = $stmtCA->fetchAll();

$moisLabels = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
$caParMois = array_fill(1, 12, 0);
$nbParMois = array_fill(1, 12, 0);
foreach ($ca_mois as $r) {
    $caParMois[$r['mois']] = (float)$r['total'];
    $nbParMois[$r['mois']] = (int)$r['nb'];
}
$maxCA = max($caParMois) ?: 1;

$stmtMode = $db->prepare("
    SELECT mode_paiement, SUM(total) AS total, COUNT(*) AS nb
    FROM ventes WHERE YEAR(created_at)=?
    GROUP BY mode_paiement
");
$stmtMode->execute([$annee]);
$ca_mode = $stmtMode->fetchAll();

$stmtCats = $db->prepare("
    SELECT c.nom, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS rev
    FROM vente_lignes vl
    JOIN ventes v ON vl.vente_id = v.id
    JOIN produits p ON vl.produit_id = p.id
    JOIN categories c ON p.categorie_id = c.id
    WHERE YEAR(v.created_at)=?
    GROUP BY c.id ORDER BY rev DESC LIMIT 6
");
$stmtCats->execute([$annee]);
$top_cats = $stmtCats->fetchAll();
$maxRev = $top_cats ? max(array_column($top_cats,'rev')) : 1;

$stmtGlobal = $db->prepare("
    SELECT COUNT(*) AS nb_ventes,
           COALESCE(SUM(total),0)  AS ca_total,
           COALESCE(AVG(total),0)  AS avg_panier
    FROM ventes WHERE YEAR(created_at)=?
");
$stmtGlobal->execute([$annee]);
$global = $stmtGlobal->fetch();

$nb_produits     = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
$valeur_stock    = $db->query("SELECT COALESCE(SUM(stock*prix_achat),0) FROM produits WHERE actif=1")->fetchColumn();
$nb_fournisseurs = $db->query("SELECT COUNT(*) FROM fournisseurs WHERE actif=1")->fetchColumn();

layout_head('Rapports & Analyses', 'rapports');
showFlash();
?>

<div class="flex-between" style="margin-bottom:20px;">
  <div style="font-family:var(--font-title);font-size:20px;font-weight:600;">
    Rapport annuel <?= $annee ?>
  </div>
  <form method="GET" class="flex gap-8">
    <select name="annee" onchange="this.form.submit()" style="padding:7px 13px;font-size:13px;width:auto;">
      <?php for ($y = date('Y'); $y >= date('Y')-4; $y--): ?>
      <option <?= $y===$annee?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">CA Total <?= $annee ?></div>
    <div class="stat-value c-teal" style="font-size:20px;"><?= fmtMoney((float)$global['ca_total']) ?></div>
    <div class="stat-sub"><?= fmtInt((int)$global['nb_ventes']) ?> transactions</div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('cart',28) ?></div>
    <div class="stat-label">Panier moyen</div>
    <div class="stat-value c-gold" style="font-size:20px;"><?= fmtMoney((float)$global['avg_panier']) ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Valeur d'achat du stock</div>
    <div class="stat-value c-blue" style="font-size:20px;"><?= fmtMoney((float)$valeur_stock) ?></div>
    <div class="stat-sub"><?= $nb_produits ?> références</div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('building',28) ?></div>
    <div class="stat-label">Fournisseurs actifs</div>
    <div class="stat-value c-purple"><?= $nb_fournisseurs ?></div>
  </div>
</div>

<div class="grid-2">
  <!-- CA mensuel -->
  <div class="card">
    <div class="card-header"><div class="card-title">Chiffre d'affaires mensuel</div></div>
    <div style="padding:18px 22px;">
      <div class="chart-bars" style="height:120px;align-items:flex-end;">
        <?php
        $colors = ['#00c9a7','#4895ef','#f0b429','#9b59b6','#e74c3c','#e67e22','#1abc9c','#3498db','#e91e63','#795548','#00bcd4','#8bc34a'];
        for ($m = 1; $m <= 12; $m++):
          $h = $maxCA > 0 ? round($caParMois[$m] / $maxCA * 110) : 4;
        ?>
        <div class="bar-wrap" title="<?= $moisLabels[$m] ?> : <?= fmtMoney($caParMois[$m]) ?> (<?= $nbParMois[$m] ?> ventes)">
          <div class="bar" style="height:<?= max($h,4) ?>px;background:<?= $colors[$m-1] ?>;opacity:.8;cursor:default;"></div>
          <div class="bar-label"><?= $moisLabels[$m] ?></div>
        </div>
        <?php endfor; ?>
      </div>
      <table style="width:100%;margin-top:14px;font-size:11px;border-collapse:collapse;">
        <thead><tr>
          <?php for ($m=1;$m<=12;$m++): ?>
          <th style="text-align:center;padding:4px 2px;color:var(--text3);border-bottom:1px solid var(--border);font-size:9px;"><?= $moisLabels[$m] ?></th>
          <?php endfor; ?>
        </tr></thead>
        <tbody><tr>
          <?php for ($m=1;$m<=12;$m++): ?>
          <td style="text-align:center;padding:4px 2px;font-family:'DM Mono',monospace;font-size:10px;color:var(--teal2);">
            <?= $caParMois[$m] > 0 ? number_format($caParMois[$m]/1000, 0).'k' : '—' ?>
          </td>
          <?php endfor; ?>
        </tr></tbody>
      </table>
    </div>
  </div>

  <!-- Top catégories -->
  <div class="card">
    <div class="card-header"><div class="card-title">CA par catégorie</div></div>
    <div class="card-pad">
      <?php if ($top_cats): foreach ($top_cats as $cat): ?>
      <div style="margin-bottom:14px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span><?= e($cat['nom']) ?></span>
          <span class="flex gap-8">
            <span class="badge badge-blue"><?= $cat['qte'] ?> ventes</span>
            <span class="fw-mono c-teal"><?= fmtMoney((float)$cat['rev']) ?></span>
          </span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= round($cat['rev']/$maxRev*100) ?>%;background:linear-gradient(90deg,var(--teal),var(--blue));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty">
        <div style="color:var(--text3);margin-bottom:8px;"><?= icon('chart',32) ?></div>
        <div>Aucune donnée</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modes de paiement -->
<div class="card">
  <div class="card-header"><div class="card-title">Répartition par mode de paiement</div></div>
  <div class="card-pad">
    <?php if ($ca_mode):
      $totalMode = array_sum(array_column($ca_mode,'total')) ?: 1;
      $modeInfo = [
        'espèces'   => ['badge-green',  'Espèces'],
        'carte'     => ['badge-blue',   'Carte bancaire'],
        'chèque'    => ['badge-gray',   'Chèque'],
        'assurance' => ['badge-purple', 'Assurance'],
      ];
      foreach ($ca_mode as $m):
        [$badge, $label] = $modeInfo[$m['mode_paiement']] ?? ['badge-gray', $m['mode_paiement']];
        $pct = round($m['total'] / $totalMode * 100, 1);
    ?>
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
      <div style="flex:1;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span><span class="badge <?= $badge ?>" style="margin-right:6px;"><?= $label ?></span><?= $m['nb'] ?> transactions</span>
          <span class="fw-mono c-teal"><?= fmtMoney((float)$m['total']) ?> <span class="text-sm">(<?= $pct ?>%)</span></span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= $pct ?>%;background:var(--teal);opacity:.7;"></div>
        </div>
      </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty">
      <div style="color:var(--text3);margin-bottom:8px;"><?= icon('receipt',32) ?></div>
      <div>Aucune donnée</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php layout_foot(); ?>
