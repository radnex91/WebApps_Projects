<?php /* pages/stock.php */
$pageTitle = 'État du Stock — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$articles = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");
$totalValeur = array_sum(array_column($articles, 'valeur_stock'));
?>

<div class="page-header">
  <h1>État du Stock Général</h1>
  <p>Valorisation au CMUPACE — <?= date('d/m/Y') ?></p>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card gold">
    <div class="stat-label">Total articles</div>
    <div class="stat-value"><?= count($articles) ?></div>
    <div class="stat-sub">Matériaux gérés</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Valeur totale du stock</div>
    <div class="stat-value money"><?= number_format($totalValeur, 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA — au CMUPACE</div>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Matériau</th>
        <th>Catégorie</th>
        <th>Unité</th>
        <th class="text-right">Stock Qté</th>
        <th class="text-right">CMUPACE</th>
        <th class="text-right">Valeur Stock</th>
        <th class="text-right">% de la valeur</th>
        <th>Statut</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $a): 
      $pct = $totalValeur > 0 ? round($a['valeur_stock'] / $totalValeur * 100, 1) : 0;
      $isAlert = $a['stock_alerte'] > 0 && $a['stock_actuel'] <= $a['stock_alerte'];
    ?>
    <tr>
      <td class="bold"><?= htmlspecialchars($a['designation']) ?></td>
      <td><span class="badge badge-gray"><?= $a['categorie'] ?></span></td>
      <td class="text-muted"><?= htmlspecialchars($a['unite']) ?></td>
      <td class="mono text-right <?= $isAlert ? 'text-red' : 'text-green' ?> fw-bold">
        <?= number_format($a['stock_actuel'], 0, ',', ' ') ?>
      </td>
      <td class="amount"><?= number_format($a['cmupace'], 0, ',', ' ') ?></td>
      <td class="amount fw-bold"><?= number_format($a['valeur_stock'], 0, ',', ' ') ?></td>
      <td style="min-width:120px;">
        <div style="display:flex; align-items:center; gap:8px;">
          <div style="flex:1; background:var(--bg4); border-radius:3px; height:6px; overflow:hidden;">
            <div style="width:<?= min($pct, 100) ?>%; height:100%; background:var(--accent); border-radius:3px;"></div>
          </div>
          <span class="mono" style="font-size:11px; min-width:32px; text-align:right;"><?= $pct ?>%</span>
        </div>
      </td>
      <td>
        <?php if ($isAlert): ?>
          <span class="badge badge-red">⚠ Stock bas</span>
        <?php elseif ($a['stock_actuel'] > 0): ?>
          <span class="badge badge-green">✓ Normal</span>
        <?php else: ?>
          <span class="badge badge-gray">Épuisé</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="/pages/fiche_mensuelle.php?article=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">Fiche</a>
        <a href="/pages/analyse_croisee.php?article=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">Analyse</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="font-family:var(--font-cond); font-size:14px;">TOTAL GÉNÉRAL</td>
        <td class="amount fw-bold" style="color:var(--accent); font-size:14px;"><?= number_format($totalValeur, 0, ',', ' ') ?> FCFA</td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
