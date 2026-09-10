<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
$db = getDB();

// S'assure que la permission existe et est accordée à TOUS les rôles :
// le rapport est auto-scopé sur l'utilisateur connecté (aucun risque de
// escalade — chacun ne voit/imprime que ses propres ventes).
try {
    $db->exec("INSERT IGNORE INTO permissions (code, libelle, module)
               VALUES ('rapports_caissier.voir', 'Voir et imprimer ses rapports de ventes', 'rapports_caissier')");
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'rapports_caissier.voir'");
    // Menu : activé par défaut (row absente = actif de toute façon)
} catch (Throwable $e) { /* non bloquant */ }

requirePermission('rapports_caissier.voir');

$userId = (int)$_SESSION['user_id'];
$user   = currentUser();

// ── Sélecteur de période ────────────────────────────────────
$periode = $_GET['periode'] ?? 'mois';
$debut   = isset($_GET['debut']) && is_string($_GET['debut']) ? $_GET['debut'] : '';
$fin     = isset($_GET['fin'])   && is_string($_GET['fin'])   ? $_GET['fin']   : '';

// Validation stricte des dates GET : ?debut=garbage sinon TypeError PHP 8 → 500.
$dDeb = $debut !== '' ? DateTime::createFromFormat('Y-m-d', $debut) : false;
if (!($dDeb instanceof DateTime && $dDeb->format('Y-m-d') === $debut)) $debut = '';
$dFin = $fin !== '' ? DateTime::createFromFormat('Y-m-d', $fin) : false;
if (!($dFin instanceof DateTime && $dFin->format('Y-m-d') === $fin))  $fin = '';

switch ($periode) {
    case 'aujourdhui':
        $dateDebut = date('Y-m-d');
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Aujourd'hui";
        break;
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin   = date('Y-m-d');
        $periodeLabel = "Cette semaine";
        break;
    case 'trimestre':
        $moisQ = (int)(floor(((int)date('n') - 1) / 3) * 3 + 1);
        $dateDebut = date('Y') . '-' . str_pad((string)$moisQ, 2, '0', STR_PAD_LEFT) . '-01';
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

// ── Impression du rapport (document A4 autonome, auto-print) ──
if (($_GET['action'] ?? '') === 'print') {
    $devSym  = getParam('devise_symbole', 'FCFA');
    $etsNom  = getParam('app_nom', 'PharmaCare');
    $etsAdr  = getParam('pharmacie_adresse', '');
    $etsTel  = getParam('pharmacie_telephone', '');
    $etsNif  = getParam('pharmacie_nif', '');
    $logoUrl = pharmacieLogoUrl();

    // Toutes les ventes de la période (pas seulement 10) + sessions de caisse.
    $stAll = $db->prepare("
        SELECT v.reference, v.client_nom, v.total, v.mode_paiement, v.created_at,
               COUNT(vl.id) AS nb_articles
        FROM ventes v
        LEFT JOIN vente_lignes vl ON vl.vente_id = v.id
        WHERE v.caissier_id = ? AND v.est_annulee = 0
          AND v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY v.id ORDER BY v.created_at ASC
        LIMIT 3000
    ");
    $stAll->execute([$userId, $dateDebut, $dateFin]);
    $ventesPrint = $stAll->fetchAll();

    $stSess = $db->prepare("
        SELECT s.*, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN caisses c ON s.caisse_id = c.id
        WHERE s.caissier_id = ?
          AND s.date_ouverture >= ? AND s.date_ouverture < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY s.date_ouverture ASC
        LIMIT 200
    ");
    $stSess->execute([$userId, $dateDebut, $dateFin]);
    $sessionsPrint = $stSess->fetchAll();

    $totArticles = 0;
    foreach ($ventesPrint as $vp) $totArticles += (int)$vp['nb_articles'];

    // Répartition par mode (partagée avec l'écran)
    $stmtModeP = $db->prepare("
        SELECT mode_paiement, COUNT(*) AS nb, COALESCE(SUM(total), 0) AS total
        FROM ventes
        WHERE caissier_id = ? AND est_annulee = 0
          AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY mode_paiement ORDER BY total DESC
    ");
    $stmtModeP->execute([$userId, $dateDebut, $dateFin]);
    $modes = $stmtModeP->fetchAll();
    $totalMode = array_sum(array_column($modes, 'total')) ?: 1;

    // Résumé (cohérent avec la liste imprimée : ventes non annulées uniquement)
    $nbP = count($ventesPrint);
    $caP = array_sum(array_map(fn($v) => (float)$v['total'], $ventesPrint));
    $stats = [
        'nb_ventes'    => $nbP,
        'ca_total'     => $caP,
        'panier_moyen' => $nbP > 0 ? $caP / $nbP : 0,
        'vente_max'    => $nbP > 0 ? max(array_map(fn($v) => (float)$v['total'], $ventesPrint)) : 0,
    ];

    $modeLabels = [
        'espèces'   => 'Espèces',
        'carte'     => 'Carte bancaire',
        'chèque'    => 'Chèque',
        'assurance' => 'Assurance',
        'crédit'    => 'Crédit',
    ];
    ?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Rapport <?= e($periodeLabel) ?> — <?= e($user['prenom'] . ' ' . $user['nom']) ?> | <?= e(APP_NAME) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;color:#1e293b;font-size:12px;padding:24px}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f766e;padding-bottom:12px;margin-bottom:14px}
.brand{display:flex;gap:10px;align-items:center}
.brand img{height:44px;width:44px;object-fit:contain}
.brand h1{font-size:17px;color:#0f766e}
.brand p{font-size:11px;color:#64748b;line-height:1.4}
.docmeta{text-align:right;font-size:11px;color:#64748b}
.docmeta strong{color:#0f172a;font-size:13px}
h2{font-size:13px;color:#0f766e;margin:16px 0 8px;border-bottom:1px solid #cbd5e1;padding-bottom:4px}
table{width:100%;border-collapse:collapse;margin-bottom:10px}
th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}
th{background:#f1f5f9;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
td.num,th.num{text-align:right;font-family:Consolas,monospace}
tr.tot td{font-weight:700;background:#f0fdfa}
.kpis{display:flex;gap:8px;margin-bottom:6px}
.kpi{flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px}
.kpi b{display:block;font-size:15px;color:#0f766e}
.kpi span{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.signatures{display:flex;justify-content:space-between;margin-top:34px}
.signatures .sig{width:42%;text-align:center;font-size:11px;color:#475569}
.signatures .line{margin-top:46px;border-top:1px solid #94a3b8;padding-top:4px}
.pied{margin-top:18px;font-size:10px;color:#94a3b8;text-align:center;border-top:1px solid #e2e8f0;padding-top:8px}
@media print{
  body{padding:0;font-size:11px}
  @page{size:A4;margin:12mm}
}
</style></head><body>
<div class="head">
  <div class="brand">
    <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt=""><?php endif; ?>
    <div><h1><?= e($etsNom) ?></h1>
    <p><?= e($etsAdr) ?><?= $etsTel ? ' — ' . e($etsTel) : '' ?><br><?= $etsNif ? 'NIF : ' . e($etsNif) : '' ?></p></div></div>
  <div class="docmeta">
    <strong>RAPPORT D'ACTIVITÉ CAISSIER</strong><br>
    Période : <?= e($periodeLabel) ?><br>
    Édité le <?= date('d/m/Y à H:i') ?>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><span>Caissier</span><strong style="font-size:13px;color:#0f172a"><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong></div>
  <div class="kpi"><span>Chiffre d'affaires</span><strong><?= fmtMoney((float)$stats['ca_total']) . ' ' . e($devSym) ?></strong></div>
  <div class="kpi"><span>Ventes</span><strong><?= fmtInt((int)$stats['nb_ventes']) ?></strong></div>
  <div class="kpi"><span>Panier moyen</span><strong><?= fmtMoney((float)$stats['panier_moyen']) ?></strong></div>
  <div class="kpi"><span>Articles vendus</span><strong><?= fmtInt($totArticles) ?></strong></div>
</div>

<?php if ($modes): ?>
<h2>Encaissements par mode de paiement</h2>
<table>
  <thead><tr><th>Mode</th><th class="num">Transactions</th><th class="num">Montant</th><th class="num">Part</th></tr></thead>
  <tbody>
  <?php foreach ($modes as $m): $pctM = round($m['total'] / $totalMode * 100, 1); ?>
  <tr><td><?= e($modeLabels[$m['mode_paiement']] ?? ucfirst($m['mode_paiement'])) ?></td>
      <td class="num"><?= (int)$m['nb'] ?></td>
      <td class="num"><?= fmtMoney((float)$m['total']) ?></td>
      <td class="num"><?= $pctM ?> %</td></tr>
  <?php endforeach; ?>
  <?php if ((int)$stats['nb_ventes'] !== (int)array_sum(array_column($modes, 'nb'))): ?>
  <!-- ventes sans mode connu : ligne d'écart éventuelle -->
  <?php endif; ?>
  </tr></tbody>
  <tfoot><tr class="tot"><td>TOTAL</td><td class="num"><?= fmtInt((int)$stats['nb_ventes']) ?></td><td class="num"><?= fmtMoney((float)$stats['ca_total']) ?></td><td class="num">100 %</td></tr></tfoot>
</table>
<?php endif; ?>

<?php if ($sessionsPrint): ?>
<h2>Sessions de caisse de la période</h2>
<table>
  <thead><tr><th>Poste</th><th>Ouverture</th><th>Fermeture</th><th class="num">Fond</th><th class="num">Attendu</th><th class="num">Réel</th><th class="num">Écart</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($sessionsPrint as $s): ?>
  <tr>
    <td><?= e($s['caisse_nom']) ?></td>
    <td><?= date('d/m/y H:i', strtotime($s['date_ouverture'])) ?></td>
    <td><?= $s['date_fermeture'] ? date('d/m/y H:i', strtotime($s['date_fermeture'])) : '—' ?></td>
    <td class="num"><?= fmtMoney((float)$s['fond_initial']) ?></td>
    <td class="num"><?= $s['solde_attendu'] !== null ? fmtMoney((float)$s['solde_attendu']) : '—' ?></td>
    <td class="num"><?= $s['solde_reel'] !== null ? fmtMoney((float)$s['solde_reel']) : '—' ?></td>
    <td class="num"><?= $s['ecart'] !== null ? fmtMoney((float)$s['ecart']) : '—' ?></td>
    <td><?= $s['statut'] === 'ouverte' ? 'En cours' : 'Fermée' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Détail des ventes (<?= fmtInt(count($ventesPrint)) ?>)</h2>
<?php if ($ventesPrint): ?>
<table>
  <thead><tr><th>Référence</th><th>Date</th><th>Client</th><th>Mode</th><th class="num">Articles</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($ventesPrint as $vp): ?>
  <tr>
    <td class="fw-mono"><?= e($vp['reference']) ?></td>
    <td><?= date('d/m/y H:i', strtotime($vp['created_at'])) ?></td>
    <td><?= e($vp['client_nom'] ?: '—') ?></td>
    <td><?= e($modeLabels[$vp['mode_paiement']] ?? ucfirst($vp['mode_paiement'])) ?></td>
    <td class="num"><?= (int)$vp['nb_articles'] ?></td>
    <td class="num"><?= fmtMoney((float)$vp['total']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="tot"><td colspan="5">TOTAL (<?= fmtInt(count($ventesPrint)) ?> vente<?= count($ventesPrint)>1?'s':'' ?>)</td><td class="num"><?= fmtMoney((float)$stats['ca_total']) ?></td></tr></tfoot>
</table>
<?php else: ?>
<p style="color:#64748b;font-style:italic;padding:10px 0;">Aucune vente enregistrée sur la période.</p>
<?php endif; ?>

<div class="signatures">
  <div class="sig"><div class="line">Le Caissier</div></div>
  <div class="sig"><div class="line">Visa de la Direction</div></div>
</div>

<div class="pied">Document généré électroniquement par <?= e($etsNom) ?> le <?= date('d/m/Y à H:i') ?> — Rapport caissier (<?= e($periodeLabel) ?>)<br>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></div>

<script>
window.onafterprint = function(){ window.location.href = <?= json_encode(url('rapports_caissier', ['periode' => $periode, 'debut' => $dateDebut, 'fin' => $dateFin])) ?>; };
window.onload = function(){ setTimeout(function(){ window.print(); }, 300); };
</script>
</body></html>
<?php
    exit;
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
    WHERE v.caissier_id = ? AND v.created_at >= ? AND v.created_at < DATE_ADD(?, INTERVAL 1 DAY)
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
        <option value="aujourdhui" <?= $periode==='aujourdhui'?'selected':'' ?>>Aujourd'hui (journalier)</option>
        <option value="semaine" <?= $periode==='semaine'?'selected':'' ?>>Cette semaine</option>
        <option value="mois" <?= $periode==='mois'?'selected':'' ?>>Ce mois (mensuel)</option>
        <option value="trimestre" <?= $periode==='trimestre'?'selected':'' ?>>Ce trimestre (trimestriel)</option>
        <option value="annee" <?= $periode==='annee'?'selected':'' ?>>Cette année (annuel)</option>
        <option value="perso" <?= $periode==='perso'?'selected':'' ?>>Personnalisé</option>
      </select>
      <div id="custom-dates" style="display:<?= $periode==='perso'?'flex':'none' ?>;gap:8px;align-items:center;">
        <input type="date" name="debut" value="<?= e($dateDebut) ?>" style="padding:7px 11px;font-size:13px;">
        <span style="color:var(--text3);">→</span>
        <input type="date" name="fin" value="<?= e($dateFin) ?>" style="padding:7px 11px;font-size:13px;">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><?= icon('filter',13) ?> Filtrer</button>
      <a href="<?= url('rapports_caissier', ['action'=>'print','periode'=>$periode,'debut'=>$dateDebut,'fin'=>$dateFin]) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Imprimer le rapport de la période affichée"><?= icon('print',13) ?> Imprimer</a>
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