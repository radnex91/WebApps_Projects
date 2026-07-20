<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('rapports_caissier.voir');

$userId = (int)$_SESSION['user_id'];
$user   = currentUser();
$db     = getDB();

// ── Sélecteur de période ────────────────────────────────────
$periode = $_GET['periode'] ?? 'mois';
$debut   = $_GET['debut'] ?? '';
$fin     = $_GET['fin']   ?? '';

switch ($periode) {
    case 'aujourdhui':
        $dateDebut = date('Y-m-d');
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Aujourd'hui";
        break;
    case 'annee':
        $dateDebut = date('Y-01-01');
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Cette année";
        break;
    case 'perso':
        $dateDebut = $debut ?: date('Y-m-01');
        $dateFin   = $fin   ?: date('Y-m-d');
        $periodeLabel = "Du " . date('d/m/Y', strtotime($dateDebut)) . " au " . date('d/m/Y', strtotime($dateFin));
        break;
    default:
        $periode = 'mois';
        $dateDebut = date('Y-m-01');
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Ce mois";
        break;
}

// ── Calcul période précédente (pour tendances) ──────────────
$startD = new DateTime($dateDebut);
$endD   = new DateTime($dateFin);
$interval = $startD->diff($endD);
$days      = $interval->days + 1;
$prevEnd   = (clone $startD)->modify('-1 day')->format('Y-m-d');
$prevStart = (clone $startD)->sub(new DateInterval("P{$days}D"))->format('Y-m-d');

// ── Statistiques principales ─────────────────────────────────
$stmt = $db->prepare("
    SELECT COUNT(*)                            AS nb_ventes,
           COALESCE(SUM(total), 0)             AS ca_total,
           COALESCE(AVG(total), 0)             AS panier_moyen,
           COALESCE(MAX(total), 0)             AS vente_max
    FROM ventes
    WHERE caissier_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmt->execute([$userId, $dateDebut, $dateFin]);
$stats = $stmt->fetch();

// ── Stats période précédente ─────────────────────────────────
$stmtPrev = $db->prepare("
    SELECT COUNT(*)                            AS nb_ventes,
           COALESCE(SUM(total), 0)             AS ca_total,
           COALESCE(AVG(total), 0)             AS panier_moyen
    FROM ventes
    WHERE caissier_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmtPrev->execute([$userId, $prevStart, $prevEnd]);
$prev = $stmtPrev->fetch();

function pctChange($current, $previous) {
    if ($previous > 0) return round((($current - $previous) / $previous) * 100, 1);
    return null;
}

$caDelta      = pctChange((float)$stats['ca_total'], (float)$prev['ca_total']);
$nbDelta      = pctChange((int)$stats['nb_ventes'], (int)$prev['nb_ventes']);
$panierDelta  = pctChange((float)$stats['panier_moyen'], (float)$prev['panier_moyen']);

function trendArrow($delta) {
    if ($delta === null) return '';
    if ($delta > 0) return '<span style="color:var(--teal2);">↑</span>';
    if ($delta < 0) return '<span style="color:var(--red);">↓</span>';
    return '<span style="color:var(--text3);">→</span>';
}

function trendPct($delta) {
    if ($delta === null) return '';
    $sign = $delta > 0 ? '+' : '';
    $cls = $delta > 0 ? 'var(--teal2)' : ($delta < 0 ? 'var(--red)' : 'var(--text3)');
    return '<span style="font-size:11px;color:' . $cls . ';margin-left:6px;">' . trendArrow($delta) . ' ' . $sign . $delta . '%</span>';
}

// ── Répartition par mode de paiement ──────────────────────────
$stmtMode = $db->prepare("
    SELECT mode_paiement, COUNT(*) AS nb, COALESCE(SUM(total), 0) AS total
    FROM ventes
    WHERE caissier_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY mode_paiement ORDER BY total DESC
");
$stmtMode->execute([$userId, $dateDebut, $dateFin]);
$modes = $stmtMode->fetchAll();
$totalMode = array_sum(array_column($modes, 'total')) ?: 1;

// ── Dernières transactions ────────────────────────────────────
$stmtRecent = $db->prepare("
    SELECT v.id, v.reference, v.client_nom, v.total, v.mode_paiement, v.created_at,
           COUNT(vl.id) AS nb_articles
    FROM ventes v
    LEFT JOIN vente_lignes vl ON vl.vente_id = v.id
    WHERE v.caissier_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY v.id ORDER BY v.created_at DESC LIMIT 10
");
$stmtRecent->execute([$userId, $dateDebut, $dateFin]);
$recent = $stmtRecent->fetchAll();

layout_head('Mes Rapports', 'rapports_caissier');
showFlash();
?>

<div class="flex-between" style="margin-bottom:20px;">
  <div style="font-family:var(--font-title);font-size:20px;font-weight:600;">
    Mes Rapports — <?= e($user['prenom'] . ' ' . $user['nom']) ?>
  </div>
  <div class="flex gap-8" style="flex-wrap:wrap;align-items:center;">
    <form method="GET" id="periode-form" class="flex gap-6" style="flex-wrap:wrap;align-items:center;">
      <select name="periode" onchange="periodeChange(this.value)" style="padding:7px 13px;font-size:13px;width:auto;">
        <option value="aujourdhui" <?= $periode==='aujourdhui'?'selected':'' ?>>Aujourd'hui</option>
        <option value="mois" <?= $periode==='mois'?'selected':'' ?>>Ce mois</option>
        <option value="annee" <?= $periode==='annee'?'selected':'' ?>>Cette année</option>
        <option value="perso" <?= $periode==='perso'?'selected':'' ?>>Personnalisé</option>
      </select>
      <div id="custom-dates" style="display:<?= $periode==='perso'?'flex':'none' ?>;gap:8px;align-items:center;">
        <input type="date" name="debut" value="<?= e($dateDebut) ?>" style="padding:7px 11px;font-size:13px;">
        <span style="color:var(--text3);">→</span>
        <input type="date" name="fin" value="<?= e($dateFin) ?>" style="padding:7px 11px;font-size:13px;">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><?= icon('filter',13) ?> Filtrer</button>
      <a href="<?= url('rapports_caissier') ?>" class="btn btn-ghost btn-sm"><?= icon('refresh',13) ?> Réinitialiser</a>
    </form>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">CA Total</div>
    <div class="stat-value c-teal" style="font-size:20px;"><?= fmtMoney((float)$stats['ca_total']) ?> <?= trendPct($caDelta) ?></div>
    <div class="stat-sub"><?= $periodeLabel ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Ventes effectuées</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$stats['nb_ventes']) ?> vente<?= (int)$stats['nb_ventes']>1?'s':'' ?> <?= trendPct($nbDelta) ?></div>
    <div class="stat-sub"><?= $periodeLabel ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('trending',28) ?></div>
    <div class="stat-label">Panier moyen</div>
    <div class="stat-value c-blue" style="font-size:20px;"><?= fmtMoney((float)$stats['panier_moyen']) ?> <?= trendPct($panierDelta) ?></div>
    <div class="stat-sub">par transaction</div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('chart',28) ?></div>
    <div class="stat-label">Vente max</div>
    <div class="stat-value c-purple" style="font-size:20px;"><?= fmtMoney((float)$stats['vente_max']) ?></div>
    <div class="stat-sub"><?= $periodeLabel ?></div>
  </div>
</div>

<?php
$modeInfo = [
    'espèces'   => ['badge-green',  'Espèces'],
    'carte'     => ['badge-blue',   'Carte bancaire'],
    'chèque'    => ['badge-gray',   'Chèque'],
    'assurance' => ['badge-purple', 'Assurance'],
];
?>
<!-- Répartition par mode de paiement -->
<?php if ($modes): ?>
<div class="card" style="margin-bottom:22px;">
  <div class="card-header"><div class="card-title">Répartition par mode de paiement</div></div>
  <div class="card-pad">
    <?php foreach ($modes as $m):
      [$badge, $label] = $modeInfo[$m['mode_paiement']] ?? ['badge-gray', $m['mode_paiement']];
      $pct = round($m['total'] / $totalMode * 100, 1);
    ?>
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
      <div style="flex:1;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span><span class="badge <?= $badge ?>" style="margin-right:6px;"><?= $label ?></span><?= $m['nb'] ?> transaction<?= $m['nb']>1?'s':'' ?></span>
          <span class="fw-mono c-teal"><?= fmtMoney((float)$m['total']) ?> <span class="text-sm">(<?= $pct ?>%)</span></span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= $pct ?>%;background:var(--teal);opacity:.7;"></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Dernières transactions -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Détail des ventes</div>
    <div style="font-size:12px;color:var(--text3);"><?= fmtInt((int)$stats['nb_ventes']) ?> vente<?= (int)$stats['nb_ventes']>1?'s':'' ?> sur la période</div>
  </div>
  <div style="overflow-x:auto;">
    <?php if ($recent): ?>
    <table>
      <thead><tr>
        <th>Référence</th>
        <th>Client</th>
        <th>Articles</th>
        <th>Total</th>
        <th>Paiement</th>
        <th>Date</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recent as $r):
        $mBadge = $modeInfo[$r['mode_paiement']] ?? ['badge-gray', $r['mode_paiement']];
      ?>
      <tr>
        <td class="fw-mono"><a href="<?= url('ventes_hist', ['debut'=>$dateDebut,'fin'=>$dateFin]) ?>" style="color:var(--teal2);text-decoration:none;"><?= e($r['reference']) ?></a></td>
        <td><?= e($r['client_nom'] ?: '—') ?></td>
        <td><?= $r['nb_articles'] ?></td>
        <td class="fw-mono c-teal"><?= fmtMoney((float)$r['total']) ?></td>
        <td><span class="badge <?= $mBadge[0] ?>"><?= $mBadge[1] ?></span></td>
        <td style="font-size:12px;color:var(--text3);"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ((int)$stats['nb_ventes'] > 10): ?>
    <div style="padding:12px 16px;text-align:center;border-top:1px solid var(--border);">
      <a href="<?= url('ventes_hist', ['debut'=>$dateDebut,'fin'=>$dateFin]) ?>" style="font-size:13px;font-weight:500;color:var(--teal2);text-decoration:none;"><?= icon('history',13) ?> Voir tout l'historique →</a>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="card-pad">
      <div class="empty">
        <div style="color:var(--text3);margin-bottom:8px;"><?= icon('receipt',32) ?></div>
        <div>Aucune transaction pour cette période</div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function periodeChange(val) {
  var el = document.getElementById('custom-dates');
  el.style.display = val === 'perso' ? 'flex' : 'none';
}
</script>

<?php layout_foot(); ?>