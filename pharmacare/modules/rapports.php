<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('rapports.voir');
$db = getDB();

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
    case '7j':
        $dateDebut = date('Y-m-d', strtotime('-6 days'));
        $dateFin   = date('Y-m-d');
        $periodeLabel = "7 derniers jours";
        break;
    case '30j':
        $dateDebut = date('Y-m-d', strtotime('-29 days'));
        $dateFin   = date('Y-m-d');
        $periodeLabel = "30 derniers jours";
        break;
    case 'trimestre':
        $dateDebut = date('Y-m-d', strtotime('first day of this month -2 months'));
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Ce trimestre";
        break;
    case 'annee':
        $dateDebut = date('Y-01-01');
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Cette année";
        break;
    case 'perso':
        $dateDebut = $debut ?: date('Y-m-01');
        $dateFin   = $fin   ?: date('Y-m-d');
        if ($dateFin < $dateDebut) { $tmp = $dateDebut; $dateDebut = $dateFin; $dateFin = $tmp; }
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
$startD  = new DateTime($dateDebut);
$endD    = new DateTime($dateFin);
$days    = $startD->diff($endD)->days + 1;
$prevEnd   = (clone $startD)->modify('-1 day')->format('Y-m-d');
$prevStart = (clone $startD)->sub(new DateInterval("P{$days}D"))->format('Y-m-d');

function pctChange($current, $previous) {
    if ($previous > 0) return round((($current - $previous) / $previous) * 100, 1);
    return null;
}
function trendPct($delta) {
    if ($delta === null) return '';
    $sign = $delta > 0 ? '+' : '';
    $arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '→');
    $cls = $delta > 0 ? 'var(--teal2)' : ($delta < 0 ? 'var(--red)' : 'var(--text3)');
    return '<span style="font-size:11px;color:' . $cls . ';margin-left:6px;">' . $arrow . ' ' . $sign . $delta . '%</span>';
}

// ── KPI période courante ─────────────────────────────────────
$stmt = $db->prepare("
    SELECT COUNT(*)                            AS nb_ventes,
           COALESCE(SUM(total), 0)             AS ca_total,
           COALESCE(AVG(total), 0)             AS panier_moyen,
           COALESCE(MAX(total), 0)             AS vente_max
    FROM ventes
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmt->execute([$dateDebut, $dateFin]);
$stats = $stmt->fetch();

// ── KPI période précédente ───────────────────────────────────
$stmtPrev = $db->prepare("
    SELECT COUNT(*)                            AS nb_ventes,
           COALESCE(SUM(total), 0)             AS ca_total,
           COALESCE(AVG(total), 0)             AS panier_moyen
    FROM ventes
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmtPrev->execute([$prevStart, $prevEnd]);
$prev = $stmtPrev->fetch();

$caDelta     = pctChange((float)$stats['ca_total'], (float)$prev['ca_total']);
$nbDelta     = pctChange((int)$stats['nb_ventes'], (int)$prev['nb_ventes']);
$panierDelta = pctChange((float)$stats['panier_moyen'], (float)$prev['panier_moyen']);

// ── Évolution du CA par jour (puis regroupement intelligent) ─
$stmtJour = $db->prepare("
    SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total, COUNT(*) AS nb
    FROM ventes
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY d ORDER BY d
");
$stmtJour->execute([$dateDebut, $dateFin]);
$rowsJour = $stmtJour->fetchAll();
$mapJour = array_column($rowsJour, null, 'd');

// Choix du regroupement selon l'amplitude de la période
if ($days <= 31) {
    $bucket = 'day';
} elseif ($days <= 120) {
    $bucket = 'week';
} else {
    $bucket = 'month';
}

$moisLabels = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
$series = []; // [label => [total, nb]]

if ($bucket === 'day') {
    $cur = new DateTime($dateDebut);
    $last = new DateTime($dateFin);
    while ($cur <= $last) {
        $k = $cur->format('Y-m-d');
        $t = isset($mapJour[$k]) ? (float)$mapJour[$k]['total'] : 0;
        $n = isset($mapJour[$k]) ? (int)$mapJour[$k]['nb'] : 0;
        $series[$cur->format('d/m')] = [$t, $n];
        $cur->modify('+1 day');
    }
} elseif ($bucket === 'week') {
    // semaines ISO commençant le lundi — on découpe la plage en tranches de 7 jours
    $cur = new DateTime($dateDebut);
    $last = new DateTime($dateFin);
    $idx = 1;
    while ($cur <= $last) {
        $wEnd = (clone $cur)->modify('+6 days');
        if ($wEnd > $last) $wEnd = clone $last;
        $t = 0; $n = 0;
        $c = clone $cur;
        while ($c <= $wEnd) {
            $k = $c->format('Y-m-d');
            if (isset($mapJour[$k])) { $t += (float)$mapJour[$k]['total']; $n += (int)$mapJour[$k]['nb']; }
            $c->modify('+1 day');
        }
        $series['S' . $idx . ' (' . $cur->format('d/m') . ')'] = [$t, $n];
        $cur = $wEnd->modify('+1 day');
        $idx++;
    }
} else { // month
    $cur = new DateTime($dateDebut);
    $last = new DateTime($dateFin);
    while ($cur <= $last) {
        $mEnd = (clone $cur)->modify('last day of this month');
        if ($mEnd > $last) $mEnd = clone $last;
        $t = 0; $n = 0;
        $c = clone $cur;
        while ($c <= $mEnd) {
            $k = $c->format('Y-m-d');
            if (isset($mapJour[$k])) { $t += (float)$mapJour[$k]['total']; $n += (int)$mapJour[$k]['nb']; }
            $c->modify('+1 day');
        }
        $series[$moisLabels[(int)$cur->format('n')] . ' ' . $cur->format('y')] = [$t, $n];
        $cur = $mEnd->modify('+1 day');
    }
}

$seriesVals  = array_map(fn($x) => $x[0], $series);
$seriesNb    = array_map(fn($x) => $x[1], $series);
$maxCA       = max($seriesVals) ?: 1;
$bucketLabel = $bucket === 'day' ? 'par jour' : ($bucket === 'week' ? 'par semaine' : 'par mois');

// ── Top catégories ───────────────────────────────────────────
$stmtCats = $db->prepare("
    SELECT c.nom, SUM(vl.quantite) AS qte, SUM(vl.total_ligne) AS rev
    FROM vente_lignes vl
    JOIN ventes v ON vl.vente_id = v.id
    JOIN produits p ON vl.produit_id = p.id
    JOIN categories c ON p.categorie_id = c.id
    WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY c.id ORDER BY rev DESC LIMIT 6
");
$stmtCats->execute([$dateDebut, $dateFin]);
$top_cats = $stmtCats->fetchAll();
$maxRev = $top_cats ? max(array_column($top_cats, 'rev')) : 1;

// ── Top produits ─────────────────────────────────────────────
$stmtProd = $db->prepare("
    SELECT vl.produit_nom,
           SUM(vl.quantite)     AS qte,
           SUM(vl.total_ligne)  AS ca
    FROM vente_lignes vl
    JOIN ventes v ON vl.vente_id = v.id
    WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY vl.produit_id, vl.produit_nom
    ORDER BY ca DESC LIMIT 8
");
$stmtProd->execute([$dateDebut, $dateFin]);
$top_prod = $stmtProd->fetchAll();
$maxProdCa = $top_prod ? max(array_column($top_prod, 'ca')) : 1;

// ── Répartition par mode de paiement ─────────────────────────
$stmtMode = $db->prepare("
    SELECT mode_paiement, SUM(total) AS total, COUNT(*) AS nb
    FROM ventes
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY mode_paiement ORDER BY total DESC
");
$stmtMode->execute([$dateDebut, $dateFin]);
$ca_mode = $stmtMode->fetchAll();

// ── Répartition par caissier ─────────────────────────────────
$stmtCais = $db->prepare("
    SELECT u.prenom, u.nom, COUNT(v.id) AS nb, COALESCE(SUM(v.total),0) AS ca
    FROM ventes v
    LEFT JOIN utilisateurs u ON u.id = v.caissier_id
    WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY v.caissier_id ORDER BY ca DESC
");
$stmtCais->execute([$dateDebut, $dateFin]);
$ca_cais = $stmtCais->fetchAll();
$maxCaisCa = $ca_cais ? max(array_column($ca_cais, 'ca')) : 1;

// ── Mouvements détaillés (une ligne par produit vendu) ───────
// Agrégats sur toute la période (sans charger toutes les lignes)
$stmtMvtStats = $db->prepare("
    SELECT COUNT(*) AS nb,
           COALESCE(SUM(vl.quantite),0) AS tot_qte,
           COALESCE(SUM(vl.total_ligne),0) AS tot_ca
    FROM vente_lignes vl
    JOIN ventes v ON vl.vente_id = v.id
    WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
");
$stmtMvtStats->execute([$dateDebut, $dateFin]);
$mvtStats = $stmtMvtStats->fetch();
$nbMvt     = (int)($mvtStats['nb'] ?? 0);
$totQteMvt = (float)($mvtStats['tot_qte'] ?? 0);
$totCaMvt  = (float)($mvtStats['tot_ca'] ?? 0);

// Liste paginée (affichage + impression + CSV portent sur la page courante)
require_once __DIR__ . '/../includes/pagination.php';
$mvtPerPage = 50;
$mvtPage    = max(1, (int)($_GET['mvt_page'] ?? 1));
$mvtOffset  = paginateOffset($mvtPage, $mvtPerPage);

$stmtMvt = $db->prepare("
    SELECT v.id, v.reference, v.client_nom, v.mode_paiement, v.statut_paiement,
           v.created_at,
           u.prenom, u.nom AS u_nom,
           vl.produit_nom, vl.quantite, vl.prix_unitaire, vl.total_ligne
    FROM vente_lignes vl
    JOIN ventes v        ON vl.vente_id = v.id
    LEFT JOIN utilisateurs u ON u.id = v.caissier_id
    WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY v.created_at DESC, vl.id ASC
    LIMIT $mvtPerPage OFFSET $mvtOffset
");
$stmtMvt->execute([$dateDebut, $dateFin]);
$mouvements = $stmtMvt->fetchAll();

// ── Infos complémentaires (hors période) ─────────────────────
$nb_produits     = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
$valeur_stock    = $db->query("SELECT COALESCE(SUM(stock*prix_achat),0) FROM produits WHERE actif=1")->fetchColumn();
$nb_fournisseurs = $db->query("SELECT COUNT(*) FROM fournisseurs WHERE actif=1")->fetchColumn();

$modeInfo = [
    'espèces'   => ['badge-green',  'Espèces'],
    'carte'     => ['badge-blue',   'Carte bancaire'],
    'chèque'    => ['badge-gray',   'Chèque'],
    'assurance' => ['badge-purple', 'Assurance'],
    'crédit'    => ['badge-gold',   'Crédit'],
];

layout_head('Rapports & Analyses', 'rapports');
showFlash();
?>

<div class="flex-between" style="margin-bottom:16px;flex-wrap:wrap;gap:12px;">
  <div style="font-family:var(--font-title);font-size:20px;font-weight:600;">
    Rapport de ventes <span style="color:var(--text3);font-weight:400;font-size:14px;">— <?= e($periodeLabel) ?></span>
  </div>
  <button onclick="printRapport()" class="btn btn-ghost btn-sm" style="gap:6px;">
    <?= icon('receipt',13) ?> Imprimer
  </button>
</div>

<!-- Filtre période -->
<form method="GET" id="periode-form" class="card" style="margin-bottom:22px;">
  <div style="padding:16px 20px;">
    <div class="flex-between" style="margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <div style="font-family:var(--font-title);font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;color:var(--text2);">
        <?= icon('calendar',16) ?> Sélection de la période
      </div>
      <a href="<?= url('rapports') ?>" class="btn btn-ghost btn-sm" style="gap:6px;"><?= icon('refresh',13) ?> Réinitialiser</a>
    </div>

    <!-- Raccourcis préréglés -->
    <div class="flex" style="gap:8px;flex-wrap:wrap;margin-bottom:14px;">
      <?php
      $presets = [
        'aujourdhui' => 'Aujourd\'hui',
        '7j'        => '7 jours',
        '30j'       => '30 jours',
        'mois'      => 'Ce mois',
        'trimestre' => 'Ce trimestre',
        'annee'     => 'Cette année',
      ];
      foreach ($presets as $val => $lbl):
        $actif = ($periode === $val);
      ?>
      <button type="button"
              onclick="selectPreset('<?= $val ?>')"
              class="btn btn-sm"
              style="gap:6px;<?= $actif
                ? 'background:var(--teal);color:var(--btn-text);border-color:var(--teal);'
                : 'background:var(--glass);color:var(--text2);border:1px solid var(--border);' ?>">
        <?= $lbl ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Plage personnalisée (toujours visible) -->
    <div style="padding-top:14px;border-top:1px solid var(--border);">
      <div style="font-size:12px;color:var(--text3);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
        <?= icon('filter',13) ?> Période personnalisée
      </div>
      <div class="flex" style="gap:10px;flex-wrap:wrap;align-items:center;">
        <label style="font-size:12px;color:var(--text2);display:flex;align-items:center;gap:6px;">
          Du
          <input type="date" name="debut" id="date-debut" value="<?= e($dateDebut) ?>"
                 onchange="onCustomDate()" style="padding:7px 11px;font-size:13px;">
        </label>
        <span style="color:var(--text3);">→</span>
        <label style="font-size:12px;color:var(--text2);display:flex;align-items:center;gap:6px;">
          Au
          <input type="date" name="fin" id="date-fin" value="<?= e($dateFin) ?>"
                 onchange="onCustomDate()" style="padding:7px 11px;font-size:13px;">
        </label>
        <button type="submit" class="btn btn-primary btn-sm" style="gap:6px;"><?= icon('check',13) ?> Appliquer</button>
        <button type="button" onclick="quickRange(30)" class="btn btn-ghost btn-sm" style="gap:6px;font-size:11px;">30 derniers jours</button>
        <button type="button" onclick="quickRange(90)" class="btn btn-ghost btn-sm" style="gap:6px;font-size:11px;">90 derniers jours</button>
        <button type="button" onclick="thisMonth()" class="btn btn-ghost btn-sm" style="gap:6px;font-size:11px;">Mois en cours</button>
      </div>
    </div>

    <input type="hidden" name="periode" id="periode-hidden" value="<?= e($periode) ?>">
  </div>
</form>

<!-- KPI -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">CA Total</div>
    <div class="stat-value c-teal" style="font-size:20px;"><?= fmtMoney((float)$stats['ca_total']) ?> <?= trendPct($caDelta) ?></div>
    <div class="stat-sub"><?= e($periodeLabel) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('cart',28) ?></div>
    <div class="stat-label">Transactions</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$stats['nb_ventes']) ?> <?= trendPct($nbDelta) ?></div>
    <div class="stat-sub"><?= e($periodeLabel) ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('trending',28) ?></div>
    <div class="stat-label">Panier moyen</div>
    <div class="stat-value c-blue" style="font-size:20px;"><?= fmtMoney((float)$stats['panier_moyen']) ?> <?= trendPct($panierDelta) ?></div>
    <div class="stat-sub">par transaction</div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('chart',28) ?></div>
    <div class="stat-label">Vente maximale</div>
    <div class="stat-value c-purple" style="font-size:20px;"><?= fmtMoney((float)$stats['vente_max']) ?></div>
    <div class="stat-sub"><?= e($periodeLabel) ?></div>
  </div>
</div>

<!-- Évolution du CA -->
<div class="card" style="margin-bottom:22px;">
  <div class="card-header">
    <div class="card-title">Évolution du chiffre d'affaires <span style="color:var(--text3);font-weight:400;font-size:12px;">(<?= $bucketLabel ?>)</span></div>
  </div>
  <div style="padding:18px 22px;">
    <?php if (array_sum($seriesVals) > 0): ?>
    <div class="chart-bars" style="height:160px;align-items:flex-end;">
      <?php
      $colors = ['#00c9a7','#4895ef','#f0b429','#9b59b6','#e74c3c','#e67e22','#1abc9c','#3498db','#e91e63','#795548','#00bcd4','#8bc34a'];
      $i = 0;
      foreach ($series as $label => $vals):
        [$t, $n] = $vals;
        $h = $maxCA > 0 ? round($t / $maxCA * 150) : 4;
        $col = $colors[$i % count($colors)];
      ?>
      <div class="bar-wrap" title="<?= e($label) ?> : <?= fmtMoney($t) ?> (<?= $n ?> ventes)">
        <div class="bar" style="height:<?= max($h,4) ?>px;background:<?= $col ?>;opacity:.85;cursor:default;"></div>
        <div class="bar-label"><?= e($label) ?></div>
      </div>
      <?php $i++; endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty">
      <div style="color:var(--text3);margin-bottom:8px;"><?= icon('chart',32) ?></div>
      <div>Aucune vente sur cette période</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
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

  <!-- Top produits -->
  <div class="card">
    <div class="card-header"><div class="card-title">Top produits</div></div>
    <div class="card-pad">
      <?php if ($top_prod): foreach ($top_prod as $p): ?>
      <div style="margin-bottom:14px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span style="max-width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($p['produit_nom']) ?></span>
          <span class="flex gap-8">
            <span class="badge badge-gray">x<?= $p['qte'] ?></span>
            <span class="fw-mono c-gold"><?= fmtMoney((float)$p['ca']) ?></span>
          </span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= round($p['ca']/$maxProdCa*100) ?>%;background:linear-gradient(90deg,var(--gold),var(--teal));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty">
        <div style="color:var(--text3);margin-bottom:8px;"><?= icon('pill',32) ?></div>
        <div>Aucune donnée</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modes de paiement + Caissiers -->
<div class="grid-2" style="margin-top:22px;">
  <div class="card">
    <div class="card-header"><div class="card-title">Répartition par mode de paiement</div></div>
    <div class="card-pad">
      <?php if ($ca_mode):
        $totalMode = array_sum(array_column($ca_mode,'total')) ?: 1;
        foreach ($ca_mode as $m):
          [$badge, $label] = $modeInfo[$m['mode_paiement']] ?? ['badge-gray', $m['mode_paiement']];
          $pct = round($m['total'] / $totalMode * 100, 1);
      ?>
      <div style="margin-bottom:14px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span><span class="badge <?= $badge ?>" style="margin-right:6px;"><?= $label ?></span><?= $m['nb'] ?> transactions</span>
          <span class="fw-mono c-teal"><?= fmtMoney((float)$m['total']) ?> <span class="text-sm" style="color:var(--text3);">(<?= $pct ?>%)</span></span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= $pct ?>%;background:var(--teal);opacity:.7;"></div>
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

  <div class="card">
    <div class="card-header"><div class="card-title">Performance par caissier</div></div>
    <div class="card-pad">
      <?php if ($ca_cais): foreach ($ca_cais as $c):
        $pct = $maxCaisCa > 0 ? round($c['ca']/$maxCaisCa*100) : 0;
        $nom = trim(($c['prenom'] ?? '').' '.($c['nom'] ?? ''));
        $nom = $nom !== '' ? $nom : 'Caissier supprimé';
      ?>
      <div style="margin-bottom:14px;">
        <div class="flex-between" style="font-size:13px;margin-bottom:5px;">
          <span><?= e($nom) ?></span>
          <span class="flex gap-8">
            <span class="badge badge-gray"><?= $c['nb'] ?> ventes</span>
            <span class="fw-mono c-blue"><?= fmtMoney((float)$c['ca']) ?></span>
          </span>
        </div>
        <div class="progress-bar" style="height:5px;">
          <div class="progress-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,var(--blue),var(--teal));"></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty">
        <div style="color:var(--text3);margin-bottom:8px;"><?= icon('users',32) ?></div>
        <div>Aucune donnée</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Détail des mouvements de vente -->
<div class="card" style="margin-top:22px;">
  <div class="card-header">
    <div class="card-title">Détail des mouvements de vente</div>
    <span class="flex gap-8" style="align-items:center;">
      <span class="text-sm" id="mvt-count"><?= $nbMvt ?> ligne(s) · <?= fmtInt((int)$totQteMvt) ?> articles</span>
      <button class="btn btn-ghost btn-sm" style="gap:6px;" onclick="exportMvtCSV()">
        <?= icon('report',13) ?> Export CSV
      </button>
    </span>
  </div>
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
    <input type="text" id="mvt-search" placeholder="Rechercher (réf, client, médicament, caissier)…"
           oninput="filterMvt()" style="width:100%;padding:9px 13px;font-size:13px;">
  </div>
  <div class="table-wrap">
    <table id="mvt-table">
      <thead>
        <tr>
          <th>Date / Heure</th><th>Référence</th><th>Client</th><th>Caissier</th>
          <th>Médicament</th><th>Qté</th><th>Prix unit.</th><th>Total ligne</th>
          <th>Paiement</th><th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($mouvements): foreach ($mouvements as $m):
          $caissier = trim(($m['prenom'] ?? '').' '.($m['u_nom'] ?? ''));
          $caissier = $caissier !== '' ? $caissier : '—';
        ?>
        <tr data-search="<?= e(strtolower(($m['reference'].' '.$m['client_nom'].' '.$m['produit_nom'].' '.$caissier.' '.$m['mode_paiement']))) ?>">
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td class="td-mono text-sm"><?= e($m['reference']) ?></td>
          <td class="text-sm"><?= e($m['client_nom'] ?: '—') ?></td>
          <td class="text-sm"><?= e($caissier) ?></td>
          <td class="text-sm"><?= e($m['produit_nom']) ?></td>
          <td class="fw-mono text-sm" style="text-align:right;"><?= fmtInt((int)$m['quantite']) ?></td>
          <td class="fw-mono text-sm" style="text-align:right;"><?= fmtMoney((float)$m['prix_unitaire']) ?></td>
          <td class="fw-mono c-teal text-sm" style="text-align:right;"><?= fmtMoney((float)$m['total_ligne']) ?></td>
          <td class="text-sm"><?= e($modeInfo[$m['mode_paiement']][1] ?? $m['mode_paiement']) ?></td>
          <td class="text-sm">
            <?php
              $stCls = ['payé'=>'badge-green','en_attente'=>'badge-gold','partiel'=>'badge-blue'];
              $stLbl = ['payé'=>'Payé','en_attente'=>'En attente','partiel'=>'Partiel'];
              $s = $m['statut_paiement'];
            ?>
            <span class="badge <?= $stCls[$s] ?? 'badge-gray' ?>"><?= e($stLbl[$s] ?? $s) ?></span>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="10">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucun mouvement de vente sur cette période</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($mouvements): ?>
      <tfoot>
        <tr style="border-top:2px solid var(--border2);">
          <td colspan="5" style="padding:12px 14px;font-weight:600;font-size:12px;color:var(--text2);">Totaux (<?= $nbMvt ?> mouvements)</td>
          <td class="fw-mono text-sm" style="text-align:right;padding:12px 14px;font-weight:600;"><?= fmtInt((int)$totQteMvt) ?></td>
          <td></td>
          <td class="fw-mono c-teal text-sm" style="text-align:right;padding:12px 14px;font-weight:600;"><?= fmtMoney((float)$totCaMvt) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?= renderPagination($mvtPage, $mvtPerPage, $nbMvt, ['periode'=>$periode,'debut'=>$debut,'fin'=>$fin], 'mvt_page') ?>
</div>

<!-- ── Contenu imprimable (tous les mouvements de la période) ── -->
<div id="print-rapport-content" style="display:none;">
  <div class="pr-header">
    <div class="pr-app"><?= e(getParam('app_nom', 'PharmaCare')) ?></div>
    <div class="pr-title">Rapport des mouvements de vente</div>
    <div class="pr-period">Période : <?= e($periodeLabel) ?> (<?= date('d/m/Y', strtotime($dateDebut)) ?> → <?= date('d/m/Y', strtotime($dateFin)) ?>)</div>
    <div class="pr-meta">Édité le <?= date('d/m/Y à H:i') ?> · <?= $nbMvt ?> mouvement(s) · <?= fmtInt((int)$totQteMvt) ?> article(s)</div>
  </div>

  <table class="pr-table">
    <thead>
      <tr>
        <th>Date / Heure</th><th>Référence</th><th>Client</th><th>Caissier</th>
        <th>Médicament</th><th class="num">Qté</th><th class="num">Prix unit.</th>
        <th class="num">Total ligne</th><th>Paiement</th><th>Statut</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($mouvements): foreach ($mouvements as $m):
        $caissier = trim(($m['prenom'] ?? '').' '.($m['u_nom'] ?? ''));
        $caissier = $caissier !== '' ? $caissier : '—';
        $stLbl = ['payé'=>'Payé','en_attente'=>'En attente','partiel'=>'Partiel'];
        $s = $m['statut_paiement'];
      ?>
      <tr>
        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
        <td><?= e($m['reference']) ?></td>
        <td><?= e($m['client_nom'] ?: '—') ?></td>
        <td><?= e($caissier) ?></td>
        <td><?= e($m['produit_nom']) ?></td>
        <td class="num"><?= fmtInt((int)$m['quantite']) ?></td>
        <td class="num"><?= fmtMoney((float)$m['prix_unitaire']) ?></td>
        <td class="num"><?= fmtMoney((float)$m['total_ligne']) ?></td>
        <td><?= e($modeInfo[$m['mode_paiement']][1] ?? $m['mode_paiement']) ?></td>
        <td><?= e($stLbl[$s] ?? $s) ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="10" class="pr-empty">Aucun mouvement de vente sur cette période</td></tr>
      <?php endif; ?>
    </tbody>
    <?php if ($mouvements): ?>
    <tfoot>
      <tr>
        <td colspan="5">Totaux (<?= $nbMvt ?> mouvements)</td>
        <td class="num"><?= fmtInt((int)$totQteMvt) ?></td>
        <td></td>
        <td class="num"><?= fmtMoney((float)$totCaMvt) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <div class="pr-summary">
    <div><span>CA total :</span> <strong><?= fmtMoney((float)$stats['ca_total']) ?></strong></div>
    <div><span>Transactions :</span> <strong><?= fmtInt((int)$stats['nb_ventes']) ?></strong></div>
    <div><span>Panier moyen :</span> <strong><?= fmtMoney((float)$stats['panier_moyen']) ?></strong></div>
    <div><span>Total lignes :</span> <strong><?= fmtMoney((float)$totCaMvt) ?></strong></div>
  </div>
</div>

<!-- Infos stock (contexte global) -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-top:22px;">
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Valeur d'achat du stock</div>
    <div class="stat-value c-blue" style="font-size:20px;"><?= fmtMoney((float)$valeur_stock) ?></div>
    <div class="stat-sub"><?= $nb_produits ?> références actives</div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('building',28) ?></div>
    <div class="stat-label">Fournisseurs actifs</div>
    <div class="stat-value c-purple"><?= $nb_fournisseurs ?></div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('trending',28) ?></div>
    <div class="stat-label">Marge brute potentielle</div>
    <div class="stat-value c-teal" style="font-size:20px;">
      <?php
      // CA période − valeur d'achat estimée des produits vendus sur la période
      $stmtCout = $db->prepare("
          SELECT COALESCE(SUM(vl.quantite * p.prix_achat),0) AS cout
          FROM vente_lignes vl
          JOIN ventes v ON vl.vente_id = v.id
          JOIN produits p ON vl.produit_id = p.id
          WHERE v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
      ");
      $stmtCout->execute([$dateDebut, $dateFin]);
      $coutAchat = (float)$stmtCout->fetchColumn();
      echo fmtMoney((float)$stats['ca_total'] - $coutAchat);
      ?>
    </div>
    <div class="stat-sub"><?= e($periodeLabel) ?> (CA − coût d'achat)</div>
  </div>
</div>

<script>
(function(){
  var form   = document.getElementById('periode-form');
  var hidden = document.getElementById('periode-hidden');
  var dDebut = document.getElementById('date-debut');
  var dFin   = document.getElementById('date-fin');

  function iso(d){ return d.toISOString().slice(0,10); }
  function today(){ return new Date(); }

  // Raccourcis prédéfinis : on désactive les dates et on soumet
  window.selectPreset = function(val){
    hidden.value = val;
    if (dDebut) dDebut.disabled = true;
    if (dFin)   dFin.disabled   = true;
    form.submit();
  };

  // Dès qu'on touche aux dates → bascule en mode personnalisé
  window.onCustomDate = function(){
    hidden.value = 'perso';
    // corrige l'ordre si fin < debut
    if (dDebut && dFin && dFin.value && dDebut.value && dFin.value < dDebut.value) {
      var tmp = dDebut.value; dDebut.value = dFin.value; dFin.value = tmp;
    }
  };

  // Raccourcis rapides de plage personnalisée
  window.quickRange = function(days){
    var end = today();
    var start = new Date(end.getTime() - (days - 1) * 86400000);
    dDebut.value = iso(start);
    dFin.value   = iso(end);
    onCustomDate();
    form.submit();
  };

  window.thisMonth = function(){
    var n = new Date();
    dDebut.value = n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-01';
    dFin.value   = iso(n);
    onCustomDate();
    form.submit();
  };

  // ── Impression du rapport (throttle) ─────────────────────
  window.printRapport = function(){
    if (!rateLimitClick('print.rapport', 10, 60000)) { rateLimitWarn('print.rapport', 10, 60000); return; }
    var src = document.getElementById('print-rapport-content');
    if (!src) { window.print(); return; }

    // Iframe caché : pas de blocage par le pop-up blocker
    var old = document.getElementById('print-rapport-iframe');
    if (old) old.parentNode.removeChild(old);

    var iframe = document.createElement('iframe');
    iframe.id = 'print-rapport-iframe';
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;top:0;';
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
      + '<title>Rapport des mouvements de vente</title>'
      + '<link href="<?= APP_URL ?>/assets/fonts/fonts.css" rel="stylesheet">'
      + '<style>'
      + '*{margin:0;padding:0;box-sizing:border-box;}'
      + 'body{font-family:\'Manrope\',sans-serif;color:#1e293b;padding:24px;max-width:1000px;margin:0 auto;}'
      + '.pr-header{text-align:center;border-bottom:2px solid #1e293b;padding-bottom:14px;margin-bottom:18px;}'
      + '.pr-app{font-size:20px;font-weight:700;letter-spacing:.3px;}'
      + '.pr-title{font-size:16px;font-weight:600;margin-top:4px;color:#334155;}'
      + '.pr-period{font-size:13px;margin-top:8px;color:#475569;}'
      + '.pr-meta{font-size:11px;color:#94a3b8;margin-top:4px;}'
      + '.pr-table{width:100%;border-collapse:collapse;font-size:11px;margin-top:6px;}'
      + '.pr-table th{background:#1e293b;color:#fff;padding:7px 6px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.3px;}'
      + '.pr-table th.num{text-align:right;}'
      + '.pr-table td{padding:6px 6px;border-bottom:1px solid #e2e8f0;}'
      + '.pr-table td.num{text-align:right;font-family:\'DM Mono\',monospace;}'
      + '.pr-table tbody tr:nth-child(even){background:#f8fafc;}'
      + '.pr-table tfoot td{font-weight:700;border-top:2px solid #1e293b;background:#f1f5f9;padding:8px 6px;}'
      + '.pr-table tfoot td.num{font-family:\'DM Mono\',monospace;}'
      + '.pr-empty{text-align:center;padding:24px;color:#94a3b8;}'
      + '.pr-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px;border-top:1px solid #e2e8f0;padding-top:14px;}'
      + '.pr-summary div{font-size:12px;}'
      + '.pr-summary span{color:#64748b;}'
      + '.pr-summary strong{font-family:\'DM Mono\',monospace;}'
      + '@media print{body{padding:0;max-width:none;}@page{margin:12mm;size:A4 landscape;}}'
      + '</style></head><body>' + src.innerHTML + '</body></html>');
    doc.close();

    // Lancer l'impression une fois le contenu (et les polices) prêt
    var done = false;
    function launch(){
      if (done) return; done = true;
      try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { window.print(); }
    }
    if (doc.readyState === 'complete') {
      setTimeout(launch, 250);
    } else {
      iframe.onload = function(){ setTimeout(launch, 250); };
      setTimeout(launch, 1500); // filet de sécurité
    }
  };

  // ── Tableau des mouvements ───────────────────────────────
  var mvtTable = document.getElementById('mvt-table');
  var mvtRows  = mvtTable ? mvtTable.querySelectorAll('tbody tr[data-search]') : [];
  var LIMIT    = 20;
  var expanded = false;

  function applyMvtView(){
    if (!mvtRows.length) return;
    var q = (document.getElementById('mvt-search').value || '').toLowerCase().trim();
    var shown = 0;
    mvtRows.forEach(function(r){
      var match = !q || r.getAttribute('data-search').indexOf(q) !== -1;
      var visible = match && (expanded || shown < LIMIT);
      r.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });
  }

  window.filterMvt = function(){
    // quand on filtre, on affiche tous les résultats correspondants
    if (!expanded){
      expanded = true;
      var more = document.getElementById('mvt-more');
      if (more) more.style.display = 'none';
    }
    applyMvtView();
    var cnt = document.getElementById('mvt-count');
    if (cnt) {
      var q = (document.getElementById('mvt-search').value || '').toLowerCase().trim();
      var n = 0;
      mvtRows.forEach(function(r){ if (!q || r.getAttribute('data-search').indexOf(q) !== -1) n++; });
      cnt.textContent = n + ' ligne(s)';
    }
  };

  window.showAllMvt = function(){
    expanded = true;
    applyMvtView();
    var more = document.getElementById('mvt-more');
    if (more) more.style.display = 'none';
  };

  window.exportMvtCSV = function(){
    if (!rateLimitClick('export.mvt', 10, 60000)) { rateLimitWarn('export.mvt', 10, 60000); return; }
    if (!mvtRows.length) return;
    var headers = ['Date/Heure','Reference','Client','Caissier','Medicament','Qte','Prix unitaire','Total ligne','Paiement','Statut'];
    var lines = [headers.join(';')];
    mvtRows.forEach(function(r){
      var cells = r.querySelectorAll('td');
      if (cells.length < 10) return;
      // reconstruit les valeurs brutes à partir du contenu textuel des cellules
      var raw = [];
      for (var i = 0; i < 10; i++) {
        var txt = cells[i].textContent.replace(/\s+/g, ' ').trim();
        // les montants contiennent le symbole devise + espaces → on garde tel quel
        raw.push('"' + txt.replace(/"/g, '""') + '"');
      }
      lines.push(raw.join(';'));
    });
    var blob = new Blob(["﻿" + lines.join("\n")], {type: 'text/csv;charset=utf-8;'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'mouvements_ventes_<?= $dateDebut ?>_<?= $dateFin ?>.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  };

  // état initial : limiter à 20 lignes
  applyMvtView();
})();
</script>

<?php layout_foot(); ?>