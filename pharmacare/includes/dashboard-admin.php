<?php
// includes/dashboard-admin.php
// Dashboard admin : vue d'ensemble complete

requireLogin();
$db = getDB();
require_once __DIR__ . '/charts.php';
require_once __DIR__ . '/cache_file.php';

// ── Cache disque 60 s des agrégats ventes (coût constant face à la volumétrie) ──
function dashCachedScalar(PDO $db, string $key, string $sql, int $ttl = 60) {
    $found = false;
    $v = cache_get($key, $ttl, $found);
    if (!$found) {
        $v = $db->query($sql)->fetchColumn();
        cache_set($key, $v, $ttl);
    }
    return $v;
}
function dashCachedRows(PDO $db, string $key, string $sql, int $ttl = 60): array {
    $found = false;
    $v = cache_get($key, $ttl, $found);
    if (!$found) {
        $v = $db->query($sql)->fetchAll();
        cache_set($key, $v, $ttl);
    }
    return $v;
}

// CA du mois (sargable : plage [1er du mois courant, 1er du mois suivant[)
$ca_mois  = dashCachedScalar($db, 'dash.admin.ca_mois', "SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND created_at < DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')");

// CA année en cours (sargable : plage [1er jan., 1er jan. année suivante[)
$ca_annee = dashCachedScalar($db, 'dash.admin.ca_annee', "SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= MAKEDATE(YEAR(NOW()),1) AND created_at < MAKEDATE(YEAR(NOW())+1,1)");
$nb_ventes_annee = dashCachedScalar($db, 'dash.admin.nb_ventes_annee', "SELECT COUNT(*) FROM ventes WHERE created_at >= MAKEDATE(YEAR(NOW()),1) AND created_at < MAKEDATE(YEAR(NOW())+1,1)");

// Médicaments en stock
$nb_prods = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
$val_stock = $db->query("SELECT COALESCE(SUM(stock * prix_vente),0) FROM produits WHERE actif=1")->fetchColumn();

// Alertes stock
$alertes  = $db->query("SELECT COUNT(*) FROM produits WHERE stock <= seuil_alerte AND actif=1")->fetchColumn();
$ruptures = $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn();

// Ventes aujourd'hui (sargable : created_at >= aujourd'hui minuit)
$ventes_j = $db->query("SELECT COUNT(*) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();
$ca_jour  = $db->query("SELECT COALESCE(SUM(total),0) FROM ventes WHERE created_at >= CURDATE()")->fetchColumn();

// Ventes 7 derniers jours
$ventes7 = dashCachedRows($db, 'dash.admin.ventes7', "
  SELECT DATE(created_at) AS jour, SUM(total) AS total, COUNT(*) AS nb
  FROM ventes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at) ORDER BY jour
");

// ── Évolution du CA — période sélectionnable ───────────────
$caPeriode = $_GET['ca_periode'] ?? '30j';
$periodesCa = [
    '7j'    => ['titre' => '7 derniers jours',            'unit' => 'day'],
    '30j'   => ['titre' => '30 derniers jours',           'unit' => 'day'],
    '90j'   => ['titre' => 'Trimestre — 90 derniers jours', 'unit' => 'week'],
    '12m'   => ['titre' => '12 derniers mois',            'unit' => 'month'],
    'annee' => ['titre' => 'Année en cours',              'unit' => 'month'],
];
if (!isset($periodesCa[$caPeriode])) $caPeriode = '30j';
$cfgCa = $periodesCa[$caPeriode];

$moisFr = [1=>'janv',2=>'févr',3=>'mars',4=>'avr',5=>'mai',6=>'juin',7=>'juil',8=>'août',9=>'sept',10=>'oct',11=>'nov',12=>'déc'];

// Construction des buckets (un par jour / semaine / mois)
$caBuckets = [];
if ($cfgCa['unit'] === 'day') {
    $n = ($caPeriode === '7j') ? 7 : 30;
    for ($i = $n - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $caBuckets[] = ['key' => $d, 'label' => date('j/m', strtotime($d)), 'days' => [$d]];
    }
} elseif ($cfgCa['unit'] === 'week') {
    $mondayThis = date('Y-m-d', strtotime('monday this week'));
    for ($i = 12; $i >= 0; $i--) {
        $ws = date('Y-m-d', strtotime("-$i weeks", strtotime($mondayThis)));
        $days = [];
        for ($k = 0; $k < 7; $k++) $days[] = date('Y-m-d', strtotime("+$k days", strtotime($ws)));
        $caBuckets[] = ['key' => $ws, 'label' => date('j/m', strtotime($ws)), 'days' => $days];
    }
} else { // month
    if ($caPeriode === 'annee') {
        $y = (int)date('Y'); $curM = (int)date('n');
        for ($m = 1; $m <= $curM; $m++) {
            $ym = sprintf('%d-%02d', $y, $m);
            $dim = (int)date('t', strtotime("$ym-01"));
            $days = [];
            for ($k = 1; $k <= $dim; $k++) $days[] = sprintf('%s-%02d', $ym, $k);
            $caBuckets[] = ['key' => $ym, 'label' => $moisFr[$m], 'days' => $days];
        }
    } else { // 12m
        for ($i = 11; $i >= 0; $i--) {
            $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
            $ym = date('Y-m', $ts);
            $y = (int)date('Y', $ts); $m = (int)date('n', $ts);
            $dim = (int)date('t', $ts);
            $days = [];
            for ($k = 1; $k <= $dim; $k++) $days[] = sprintf('%s-%02d', $ym, $k);
            $caBuckets[] = ['key' => $ym, 'label' => $moisFr[$m] . " '" . substr((string)$y, 2), 'days' => $days];
        }
    }
}

// Totals journaliers sur la plage couverte (cache 60 s, par période)
$caMinDate = min(array_merge(...array_column($caBuckets, 'days')));
$caDailyRows = dashCachedRows($db, 'dash.admin.ca_daily.' . $caPeriode, "
  SELECT DATE(created_at) AS jour, COALESCE(SUM(total),0) AS total, COUNT(*) AS nb
  FROM ventes WHERE created_at >= " . $db->quote($caMinDate) . "
  GROUP BY jour
");
$caDailyMap = []; $caNbMap = [];
foreach ($caDailyRows as $r) {
    $caDailyMap[$r['jour']] = (float)$r['total'];
    $caNbMap[$r['jour']]    = (int)$r['nb'];
}

// Agrégation par bucket : CA (close), nb ventes, panier moyen
$candleData = [];
$caBars = [];
$prevClose = 0;
foreach ($caBuckets as $b) {
    $close = 0; $nb = 0; $high = 0; $low = PHP_FLOAT_MAX;
    foreach ($b['days'] as $dd) {
        $t = $caDailyMap[$dd] ?? 0;
        $close += $t;
        $nb    += $caNbMap[$dd] ?? 0;
        if ($t > $high) $high = $t;
        if ($t > 0 && $t < $low) $low = $t;
    }
    if ($low === PHP_FLOAT_MAX) $low = 0;
    $open = $prevClose;
    $candleData[$b['label']] = [
        'open'  => $open,
        'high'  => max($high, $open, $close),
        'low'   => $low > 0 ? $low : min($open, $close),
        'close' => $close,
    ];
    $caBars[$b['label']] = [
        'value' => $close,
        'nb'    => $nb,
        'avg'   => $nb > 0 ? $close / $nb : 0,
    ];
    $prevClose = $close;
}

// Stock critique
$critique = $db->query("
  SELECT p.nom, p.stock, p.seuil_alerte, c.nom AS cat
  FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id
  WHERE p.stock <= p.seuil_alerte AND p.actif=1
  ORDER BY p.stock ASC LIMIT 6
")->fetchAll();

// Dernières ventes
$dernieres = $db->query("
  SELECT v.*, u.prenom, u.nom AS u_nom
  FROM ventes v LEFT JOIN utilisateurs u ON v.caissier_id=u.id
  ORDER BY v.created_at DESC LIMIT 8
")->fetchAll();

// Top 5 produits (30j)
$top = dashCachedRows($db, 'dash.admin.top5', "
  SELECT vl.produit_nom, SUM(vl.quantite) AS qte
  FROM vente_lignes vl JOIN ventes v ON vl.vente_id=v.id
  WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY vl.produit_nom ORDER BY qte DESC LIMIT 5
");
$maxQ = $top ? max(array_column($top, 'qte')) : 1;

// Activite utilisateurs
$activite = $db->query("
  SELECT u.prenom, u.nom, u.derniere_connexion, r.libelle AS role_libelle
  FROM utilisateurs u JOIN roles r ON u.role_id = r.id
  WHERE u.actif = 1 ORDER BY u.derniere_connexion DESC LIMIT 5
")->fetchAll();

// Commandes en cours
$cmd_en_cours = $db->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')")->fetchColumn();

// Périodes de pointe : ventes par jour de la semaine (30 derniers jours)
$ventesParJour = dashCachedRows($db, 'dash.admin.pointe', "
    SELECT DAYOFWEEK(created_at) AS dow, SUM(total) AS total, COUNT(*) AS nb
    FROM ventes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY dow ORDER BY dow
");
$joursSemaine = [1 => 'Dim', 2 => 'Lun', 3 => 'Mar', 4 => 'Mer', 5 => 'Jeu', 6 => 'Ven', 7 => 'Sam'];
$pointeData = array_fill(1, 7, ['total' => 0, 'nb' => 0]);
foreach ($ventesParJour as $v) {
    $pointeData[(int)$v['dow']] = ['total' => (float)$v['total'], 'nb' => (int)$v['nb']];
}
$maxPointe = max(array_column($pointeData, 'total') ?: [1]);
$jourPointe = array_keys($pointeData, max($pointeData))[0] ?? 0;
$jourPointeLabel = $joursSemaine[$jourPointe] ?? '';

// Prepare les jours pour le graphique
$jours = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $jours[$d] = ['total' => 0, 'nb' => 0, 'label' => date('D', strtotime($d))];
}
$fr = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'];
foreach ($ventes7 as $v) {
    $jours[$v['jour']] = ['total' => $v['total'], 'nb' => $v['nb'], 'label' => $jours[$v['jour']]['label'] ?? ''];
}
$maxV = max(array_column($jours, 'total') ?: [1]);

layout_head('Tableau de bord', 'dashboard');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">CA du mois</div>
    <div class="stat-value c-teal"><?= fmtMoney($ca_mois) ?></div>
    <div class="stat-sub">Aujourd'hui : <?= fmtMoney($ca_jour) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Médicaments en stock</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$nb_prods) ?></div>
    <div class="stat-sub">Valeur marchande : <?= fmtMoney($val_stock) ?></div>
  </div>
  <div class="stat-card s-red">
    <div class="stat-icon" style="color:var(--red);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes stock</div>
    <div class="stat-value c-red"><?= $alertes ?></div>
    <div class="stat-sub"><?= $ruptures ?> en rupture totale</div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Ventes aujourd'hui</div>
    <div class="stat-value c-blue"><?= $ventes_j ?></div>
    <div class="stat-sub">transactions effectuées</div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple);opacity:.25;"><?= icon('trending',28) ?></div>
    <div class="stat-label">CA année en cours</div>
    <div class="stat-value c-purple"><?= fmtMoney($ca_annee) ?></div>
    <div class="stat-sub"><?= fmtInt((int)$nb_ventes_annee) ?> ventes</div>
  </div>
</div>

<?php require __DIR__ . '/dashboard-alertes-pharmacies.php'; ?>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">Évolution du CA — <?= e($cfgCa['titre']) ?></div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <form method="get" style="display:flex;">
        <select name="ca_periode" onchange="this.form.submit()" style="padding:5px 10px;font-size:12px;border-radius:var(--radius-sm);background:var(--bg);color:var(--text);border:1px solid var(--border2);cursor:pointer;">
          <?php foreach ($periodesCa as $k => $pc): ?>
          <option value="<?= $k ?>" <?= $caPeriode === $k ? 'selected' : '' ?>><?= e($pc['titre']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span class="badge" style="background:var(--teal-dim);color:var(--teal2);">
        <?= fmtMoney(array_sum(array_column($candleData, 'close'))) ?> total
      </span>
    </div>
  </div>
  <div class="line-chart-wrap">
    <?php
    $caLineData = [];
    foreach ($caBars as $lbl => $b) $caLineData[$lbl] = $b['value'];
    renderLineChart($caLineData, 'var(--teal)', '', 'FCFA');
    ?>
  </div>
  <div class="card-pad" style="padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:22px;flex-wrap:wrap;font-size:12px;color:var(--text3);">
    <?php
    $caTotalCa = array_sum(array_column($caBars, 'value'));
    $caTotalNb = array_sum(array_column($caBars, 'nb'));
    $caPanier = $caTotalNb > 0 ? $caTotalCa / $caTotalNb : 0;
    ?>
    <span><strong style="color:var(--text2);font-family:var(--font-mono,monospace);"><?= fmtMoney($caTotalCa) ?></strong> CA total</span>
    <span><strong style="color:var(--text2);"><?= fmtInt((int)$caTotalNb) ?></strong> ventes</span>
    <span><strong style="color:var(--text2);font-family:var(--font-mono,monospace);"><?= fmtMoney($caPanier) ?></strong> panier moyen</span>
    <span><strong style="color:var(--gold);font-family:var(--font-mono,monospace);"><?= fmtMoney(max(array_column($caBars, 'value'))) ?></strong> meilleur bucket</span>
  </div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">Périodes de pointe — 30 derniers jours</div>
    <span class="badge" style="background:var(--teal-dim);color:var(--teal2);">
      Pointe : <?= $jourPointeLabel ?>
    </span>
  </div>
  <div class="card-pad">
    <div style="display:flex;align-items:end;gap:6px;height:110px;">
      <?php foreach ($pointeData as $dow => $d):
        $pct = $maxPointe > 0 ? round($d['total'] / $maxPointe * 100) : 0;
        $estPointe = $dow === $jourPointe;
        $label = $joursSemaine[$dow];
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
        <div style="font-size:10px;font-weight:500;color:var(--text3);min-height:16px;white-space:nowrap;">
          <?= $d['nb'] > 0 ? $d['nb'] . ' v.' : '' ?>
        </div>
        <div style="width:100%;border-radius:8px 8px 0 0;transition:all 0.2s;
          height:<?= max($pct, 3) ?>px;
          background:<?= $estPointe
            ? 'linear-gradient(180deg,var(--teal2),var(--teal-dim))'
            : 'var(--border2)' ?>;">
        </div>
        <div style="font-size:11px;font-weight:<?= $estPointe ? '700' : '400' ?>;
          color:<?= $estPointe ? 'var(--teal2)' : 'var(--text3)' ?>;">
          <?= $label ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);font-size:12px;color:var(--text2);">
      <strong style="color:var(--teal2);"><?= $jourPointeLabel ?></strong> est le jour le plus chargé —
      <?php if ($jourPointeLabel === 'Lun'): ?>
      vérifiez les stocks le samedi avant la fermeture.
      <?php elseif ($jourPointeLabel === 'Sam'): ?>
      anticipez les réapprovisionnements le vendredi.
      <?php elseif ($jourPointeLabel === 'Dim'): ?>
      préparez les stocks le samedi pour le dimanche.
      <?php else: ?>
      planifiez les réapprovisionnements la veille pour absorber l'affluence.
      <?php endif; ?>
    </div>
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
    <div class="card-header">
      <div class="card-title">Dernières ventes</div>
      <a href="<?= url('ventes_hist') ?>" class="btn btn-ghost btn-xs">Historique</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Référence</th><th>Client</th><th>Total</th><th>Caissier</th><th>Heure</th></tr></thead>
        <tbody>
          <?php foreach ($dernieres as $v): ?>
          <tr>
            <td class="td-mono"><?= e($v['reference']) ?></td>
            <td><?= e($v['client_nom'] ?: '—') ?></td>
            <td class="fw-mono c-teal"><?= fmtMoney($v['total']) ?></td>
            <td class="text-sm"><?= e($v['prenom'] . ' ' . $v['u_nom']) ?></td>
            <td class="text-sm"><?= date('H:i', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2">
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

  <a href="<?= url('commandes') ?>" class="card" style="display:block;text-decoration:none;color:inherit;">
    <div class="card-header">
      <div class="card-title">Commandes en cours</div>
      <span class="badge badge-blue"><?= $cmd_en_cours ?></span>
    </div>
    <div class="card-pad" style="text-align:center;padding:44px 20px;">
      <div style="color:var(--blue);margin-bottom:12px;"><?= icon('truck',40) ?></div>
      <div style="font-size:30px;font-weight:700;font-family:var(--font-title);color:var(--blue);"><?= $cmd_en_cours ?></div>
      <div class="text-sm" style="margin-top:4px;">commande(s) en attente ou en cours</div>
    </div>
  </a>
</div>

<?php layout_foot(); ?>
