<?php
// includes/dashboard-caissier.php
// Dashboard caissier : focus sur ses ventes personnelles du jour

requireLogin();
$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Ventes aujourd'hui (compteur)
$ventes_j = $db->prepare("SELECT COUNT(*) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?");
$ventes_j->execute([$userId]);
$ventes_j = $ventes_j->fetchColumn();

// Mon CA du jour
$ca_jour = $db->prepare("SELECT COALESCE(SUM(total),0) FROM ventes WHERE DATE(created_at)=CURDATE() AND caissier_id=?");
$ca_jour->execute([$userId]);
$ca_jour = $ca_jour->fetchColumn();

// Dernières transactions
$dernieres = $db->prepare("SELECT reference, client_nom, total, created_at FROM ventes WHERE caissier_id=? ORDER BY created_at DESC LIMIT 8");
$dernieres->execute([$userId]);
$dernieres = $dernieres->fetchAll();

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Ventes aujourd'hui</div>
    <div class="stat-value c-teal"><?= fmtInt((int)$ventes_j) ?></div>
    <div class="stat-sub">transactions effectuées</div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">Mon CA du jour</div>
    <div class="stat-value c-gold"><?= fmtMoney($ca_jour) ?></div>
    <div class="stat-sub">CA personnel</div>
  </div>
  <a href="<?= APP_URL ?>/modules/vente.php" class="stat-card s-blue" style="display:block;text-decoration:none;cursor:pointer;">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('cart',28) ?></div>
    <div class="stat-label">Point de Vente</div>
    <div class="stat-value c-blue" style="font-size:20px;">Nouvelle vente</div>
    <div class="stat-sub">Ouvrir la caisse</div>
  </a>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Mes dernières transactions</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Référence</th><th>Client</th><th>Total</th><th>Date</th><th>Heure</th></tr></thead>
      <tbody>
        <?php if ($dernieres): foreach ($dernieres as $v): ?>
        <tr>
          <td class="td-mono"><?= e($v['reference']) ?></td>
          <td><?= e($v['client_nom'] ?: '—') ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney($v['total']) ?></td>
          <td class="text-sm"><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
          <td class="text-sm"><?= date('H:i', strtotime($v['created_at'])) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="empty">Aucune transaction enregistrée</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_foot(); ?>
