<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/pagination.php';
requirePermission('ventes_hist.voir');
$db = getDB();

// Par défaut : historique du jour courant uniquement (l'utilisateur peut
// élargir la période via les filtres). Avant, debut valait le 1er du mois.
$dateDebut = isset($_GET['debut']) && is_string($_GET['debut']) ? $_GET['debut'] : date('Y-m-d');
$dateFin   = isset($_GET['fin'])   && is_string($_GET['fin'])   ? $_GET['fin']   : date('Y-m-d');
$mode      = isset($_GET['mode'])  && is_string($_GET['mode'])  ? $_GET['mode']  : '';

// Validation stricte des dates GET : ?debut=garbage sinon TypeError PHP 8 → 500.
$dDeb = DateTime::createFromFormat('Y-m-d', $dateDebut);
if (!($dDeb instanceof DateTime && $dDeb->format('Y-m-d') === $dateDebut)) $dateDebut = date('Y-m-d');
$dFin = DateTime::createFromFormat('Y-m-d', $dateFin);
if (!($dFin instanceof DateTime && $dFin->format('Y-m-d') === $dateFin))   $dateFin   = date('Y-m-d');
if ($dateFin < $dateDebut) { $tmp = $dateDebut; $dateDebut = $dateFin; $dateFin = $tmp; }
$estAdmin  = isAdmin();

// WHERE avec alias v. pour les requêtes avec jointures
$whereV = "v.created_at >= " . $db->quote($dateDebut) . " AND v.created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)";
if ($mode) $whereV .= " AND v.mode_paiement=" . $db->quote($mode);

// ── Export Excel de l'historique des ventes (période filtrée) ──────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $stX = $db->query("
        SELECT v.reference, v.created_at, CONCAT(u.prenom, ' ', u.nom) AS caissier,
               v.client_nom, v.mode_paiement, v.statut_paiement,
               v.sous_total, v.tva_total, v.remise_montant, v.total
        FROM ventes v
        LEFT JOIN utilisateurs u ON v.caissier_id = u.id
        WHERE $whereV
        ORDER BY v.created_at ASC
        LIMIT 20000
    ");
    $rowsX = [];
    foreach ($stX->fetchAll() as $v) {
        $rowsX[] = [
            $v['reference'], date('d/m/Y H:i', strtotime($v['created_at'])), $v['caissier'],
            $v['client_nom'], $v['mode_paiement'], $v['statut_paiement'],
            (float)$v['sous_total'], (float)$v['tva_total'], (float)$v['remise_montant'], (float)$v['total'],
        ];
    }
    export_xlsx_send('ventes_' . $dateDebut . '_' . $dateFin, 'Ventes',
        ['Référence', 'Date', 'Caissier', 'Client', 'Mode paiement', 'Statut',
         'Sous-total HT', 'TVA', 'Remise', 'Total TTC'], $rowsX);
}
if (!$estAdmin) $whereV .= " AND v.caissier_id=" . (int)$_SESSION['user_id'];

// WHERE sans alias pour requête simple
$whereS = "created_at >= " . $db->quote($dateDebut) . " AND created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)";
if ($mode) $whereS .= " AND mode_paiement=" . $db->quote($mode);
if (!$estAdmin) $whereS .= " AND caissier_id=" . (int)$_SESSION['user_id'];

// ── Pagination ──
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$totalVentes = (int)$db->query("SELECT COUNT(*) FROM ventes v WHERE $whereV")->fetchColumn();
$offset  = paginateOffset($page, $perPage);

$ventes = $db->query("
    SELECT v.*, u.prenom, u.nom AS u_nom
    FROM ventes v
    LEFT JOIN utilisateurs u ON v.caissier_id = u.id
    WHERE $whereV
    ORDER BY v.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();

$stats = $db->query("
    SELECT COUNT(*) AS nb,
           COALESCE(SUM(total),0) AS total,
           COALESCE(AVG(total),0) AS avg,
           COALESCE(MAX(total),0) AS max
    FROM ventes WHERE $whereS
")->fetch();

// Lignes de détail pour le modal JS
$venteIds = array_column($ventes, 'id');
$lignes   = [];
if ($venteIds) {
    $ph    = implode(',', array_fill(0, count($venteIds), '?'));
    $stmtL = $db->prepare("SELECT * FROM vente_lignes WHERE vente_id IN ($ph) ORDER BY id");
    $stmtL->execute($venteIds);
    $lignes = $stmtL->fetchAll();
}

// nb_lignes par vente : calculé en PHP depuis les lignes déjà chargées (évite
// un LEFT JOIN + GROUP BY sur vente_lignes qui impose une table temp + filesort
// à chaque page). Pour les ventes sans ligne, on retombe à 0.
$nbLignesMap = [];
foreach ($lignes as $l) {
    $vid = (int)$l['vente_id'];
    $nbLignesMap[$vid] = ($nbLignesMap[$vid] ?? 0) + 1;
}
foreach ($ventes as &$vRef) {
    $vRef['nb_lignes'] = $nbLignesMap[(int)$vRef['id']] ?? 0;
}
unset($vRef);

$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte bancaire',
    'chèque'    => 'Chèque',
    'assurance' => 'Assurance',
    'crédit'    => 'Crédit',
];

$appNom = getParam('app_nom', 'PharmaCare');
$tvaTaux = getParam('tva', '19.25');
$ticketSousTitre = getParam('ticket_sous_titre', 'Gestion Pharmacie');
$ticketPied      = getParam('ticket_pied', 'Merci pour votre achat !');
$pharmAdresse    = getParam('pharmacie_adresse', '');
$pharmTel        = getParam('pharmacie_telephone', '');
$pharmNif        = getParam('pharmacie_nif', '');
$pharmLogoUrl    = pharmacieLogoUrl();
$ticketCopies    = max(1, (int)getParam('ticket_nb_copies', '2'));
// Carte pharmacie_id => nom (le ticket affiche la pharmacie qui a servi)
$pharmacieNoms = [];
foreach ($db->query("SELECT id, nom FROM pharmacies")->fetchAll() as $r) {
    $pharmacieNoms[(int)$r['id']] = $r['nom'];
}

// ── Impression de l'historique (toutes les ventes de la période) ──────────
// Document A4 standalone (pas de nav/sidebar) : en-tête établissement,
// période filtrée, tableau complet des ventes + totaux. Auto-impression.
$action = $_GET['action'] ?? 'list';
if ($action === 'print') {
    $devSym = getParam('devise_symbole', 'FCFA');
    // Toutes les ventes de la période (hors pagination), plafonnées pour
    // rester raisonnable sur une très longue période.
    $ventesPrint = $db->query("
        SELECT v.*, u.prenom, u.nom AS u_nom
        FROM ventes v
        LEFT JOIN utilisateurs u ON v.caissier_id = u.id
        WHERE $whereV
        ORDER BY v.created_at ASC
        LIMIT 5000
    ")->fetchAll();

    // nb_lignes via un seul agrégat groupé (évite JOIN+GROUP BY sur 5000 ventes).
    $printIds = array_column($ventesPrint, 'id');
    if ($printIds) {
        $ph = implode(',', array_fill(0, count($printIds), '?'));
        $stmtNb = $db->prepare("SELECT vente_id, COUNT(*) AS nb FROM vente_lignes WHERE vente_id IN ($ph) GROUP BY vente_id");
        $stmtNb->execute($printIds);
        $nbMap = [];
        foreach ($stmtNb->fetchAll() as $r) $nbMap[(int)$r['vente_id']] = (int)$r['nb'];
        foreach ($ventesPrint as &$vpRef) {
            $vpRef['nb_lignes'] = $nbMap[(int)$vpRef['id']] ?? 0;
        }
        unset($vpRef);
    }

    $totSous = 0.0; $totTva = 0.0; $totTotal = 0.0;
    foreach ($ventesPrint as $vp) {
        $totSous  += (float)$vp['sous_total'];
        $totTva   += (float)$vp['tva_total'];
        $totTotal += (float)$vp['total'];
    }

    $periodeLbl = 'Du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin));
    $modeLbl = $mode ? ($modeLabels[$mode] ?? $mode) : 'Tous modes';
    $genereLe = date('d/m/Y à H:i');
    $etsNom  = getParam('app_nom', 'PharmaCare');
    $etsAdr  = getParam('pharmacie_adresse', '');
    $etsTel  = getParam('pharmacie_telephone', '');
    $etsNif  = getParam('pharmacie_nif', '');
    $logoUrl = pharmacieLogoUrl();
    $nbVentes = count($ventesPrint);
    $retUrl = url('ventes_hist', ['debut'=>$dateDebut, 'fin'=>$dateFin, 'mode'=>$mode]);

    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<title>Historique des ventes — <?= e($periodeLbl) ?></title>
<style>
  @page { size: A4; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 11px; margin: 0; }
  .bandeau { display: flex; justify-content: space-between; align-items: stretch; border: 2px solid #0f172a; margin-bottom: 12px; }
  .bandeau .gauche { padding: 10px 16px; }
  .bandeau .gauche .t { font-size: 17px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #0f172a; }
  .bandeau .gauche .s { font-size: 11px; color: #475569; margin-top: 2px; }
  .bandeau .droite { padding: 8px 16px; text-align: right; border-left: 1px solid #94a3b8; }
  .bandeau .droite .r { font-size: 13px; font-weight: 700; color: #0f172a; }
  .bandeau .droite .d { font-size: 10px; color: #475569; margin-top: 3px; }
  .ets { margin: 6px 2px 12px; font-size: 11px; color: #475569; }
  .ets .nom { font-weight: 700; color: #0f172a; }
  .meta { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px 18px; margin-bottom: 12px; font-size: 11px; }
  .meta .lbl { color: #64748b; display: inline-block; min-width: 110px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  th, td { border: 1px solid #94a3b8; padding: 4px 6px; vertical-align: top; }
  th { background: #0f172a; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
  td.right, th.right { text-align: right; }
  td.center, th.center { text-align: center; }
  tfoot td { font-weight: 700; background: #f1f5f9; }
  .pied { margin-top: 18px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
  .toolbar { text-align: center; margin-bottom: 10px; }
  .toolbar button { padding: 8px 18px; font-size: 13px; cursor: pointer; border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 6px; }
  @media print { .toolbar { display: none; } }
</style></head>
<body>
  <div class="toolbar"><button onclick="window.print()">🖨️ Imprimer l'historique</button></div>

  <div class="bandeau">
    <div class="gauche">
      <?php if ($logoUrl): ?><div style="margin-bottom:5px;"><img src="<?= e($logoUrl) ?>" alt="" style="max-height:48px;max-width:200px;"></div><?php endif; ?>
      <div class="t">Historique des ventes</div>
      <div class="s">Récapitulatif des ventes de la période</div>
    </div>
    <div class="droite">
      <div class="r"><?= e($nbVentes) ?> vente<?= $nbVentes>1?'s':'' ?></div>
      <div class="d"><?= e($periodeLbl) ?></div>
    </div>
  </div>

  <div class="ets">
    <span class="nom"><?= e($etsNom) ?></span>
    <?php if ($etsAdr): ?> — <?= e($etsAdr) ?><?php endif; ?>
    <?php if ($etsTel): ?> · Tél : <?= e($etsTel) ?><?php endif; ?>
    <?php if ($etsNif): ?> · NIF : <?= e($etsNif) ?><?php endif; ?>
  </div>

  <div class="meta">
    <div><span class="lbl">Période :</span> <?= e($periodeLbl) ?></div>
    <div><span class="lbl">Mode de paiement :</span> <?= e($modeLbl) ?></div>
    <div><span class="lbl">Généré le :</span> <?= e($genereLe) ?></div>
    <div><span class="lbl">Nombre de ventes :</span> <?= e($nbVentes) ?></div>
    <div><span class="lbl">Revenu total :</span> <?= fmtMoney($totTotal) ?></div>
    <div><span class="lbl">Caissier :</span> <?= $estAdmin ? 'Tous' : e($_SESSION['user_prenom'].' '.$_SESSION['user_nom']) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:3%;">#</th>
        <th>Référence</th>
        <th>Client</th>
        <th class="center" style="width:7%;">Art.</th>
        <th class="right">Sous-total</th>
        <th class="right">TVA</th>
        <th class="right">Total TTC</th>
        <th>Paiement</th>
        <th>Caissier</th>
        <th style="width:13%;">Date / Heure</th>
      </tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($ventesPrint as $vp): $i++; ?>
      <tr>
        <td class="center"><?= $i ?></td>
        <td class="td-mono" style="font-family:monospace;"><?= e($vp['reference']) ?></td>
        <td><?= e($vp['client_nom'] ?: '—') ?></td>
        <td class="center"><?= (int)$vp['nb_lignes'] ?></td>
        <td class="right"><?= fmtMoney((float)$vp['sous_total']) ?></td>
        <td class="right"><?= fmtMoney((float)$vp['tva_total']) ?></td>
        <td class="right"><?= fmtMoney((float)$vp['total']) ?></td>
        <td><?= e($modeLabels[$vp['mode_paiement']] ?? $vp['mode_paiement']) ?></td>
        <td><?= e(trim($vp['prenom'].' '.$vp['u_nom'])) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($vp['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$ventesPrint): ?>
      <tr><td colspan="10" style="text-align:center;padding:16px;">Aucune vente sur cette période.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if ($ventesPrint): ?>
    <tfoot>
      <tr>
        <td colspan="4" class="right">Totaux (<?= $nbVentes ?> vente<?= $nbVentes>1?'s':'' ?>)</td>
        <td class="right"><?= fmtMoney($totSous) ?></td>
        <td class="right"><?= fmtMoney($totTva) ?></td>
        <td class="right"><?= fmtMoney($totTotal) ?></td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <div class="pied">Document généré électroniquement par <?= e($etsNom) ?> le <?= e($genereLe) ?> — Historique des ventes (<?= e($periodeLbl) ?>).<br>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></div>

  <script>
    window.onafterprint = function(){ window.location.href = <?= json_encode($retUrl) ?>; };
    window.onload = function(){ setTimeout(function(){ window.print(); }, 300); };
  </script>
</body></html>
<?php
    exit;
}

layout_head('Historique des ventes', 'historique');
showFlash();
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('receipt',28) ?></div>
    <div class="stat-label">Transactions</div>
    <div class="stat-value c-teal"><?= fmtInt((int)$stats['nb']) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('money',28) ?></div>
    <div class="stat-label">Revenus totaux</div>
    <div class="stat-value c-gold" style="font-size:20px;"><?= fmtMoney((float)$stats['total']) ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('trending',28) ?></div>
    <div class="stat-label">Panier moyen</div>
    <div class="stat-value c-blue" style="font-size:20px;"><?= fmtMoney((float)$stats['avg']) ?></div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('chart',28) ?></div>
    <div class="stat-label">Vente max</div>
    <div class="stat-value c-purple" style="font-size:20px;"><?= fmtMoney((float)$stats['max']) ?></div>
  </div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-pad">
    <form method="GET" class="flex gap-8" style="flex-wrap:wrap;">
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Du</label>
        <input type="date" name="debut" value="<?= e($dateDebut) ?>" style="padding:7px 11px;">
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Au</label>
        <input type="date" name="fin" value="<?= e($dateFin) ?>" style="padding:7px 11px;">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Mode de paiement</label>
        <select name="mode" style="padding:7px 11px;width:auto;">
          <option value="">Tous</option>
          <?php foreach ($modeLabels as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= $mode===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;justify-content:flex-end;">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary btn-sm">
          <?= icon('filter',13) ?> Filtrer
        </button>
      </div>
      <div class="form-group" style="margin:0;justify-content:flex-end;">
        <label>&nbsp;</label>
        <a href="<?= url('ventes_hist') ?>" class="btn btn-ghost btn-sm"><?= icon('refresh',13) ?> Réinitialiser</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Historique des ventes</div>
    <div class="flex gap-8" style="align-items:center;">
      <span class="text-sm"><?= count($ventes) ?> résultat(s)</span>
      <a href="<?= url('ventes_hist', ['export'=>'1', 'debut'=>$dateDebut, 'fin'=>$dateFin, 'mode'=>$mode]) ?>"
         class="btn btn-ghost btn-sm" title="Exporter la période filtrée au format Excel (.xlsx)">
        <?= icon('download',13) ?> Exporter
      </a>
      <a href="<?= url('ventes_hist', ['action'=>'print','debut'=>$dateDebut,'fin'=>$dateFin,'mode'=>$mode]) ?>"
         class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
         title="Imprimer l'historique de la période filtrée">
        <?= icon('print',13) ?> Imprimer
      </a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Référence</th><th>Client</th><th>Articles</th>
          <th>Sous-total</th><th>TVA</th><th>Total TTC</th>
          <th>Paiement</th><th>Caissier</th><th>Date / Heure</th><th>Détail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ventes as $v): ?>
        <tr>
          <td class="td-mono"><?= e($v['reference']) ?></td>
          <td><?= e($v['client_nom'] ?: '—') ?></td>
          <td><span class="badge badge-blue"><?= $v['nb_lignes'] ?> art.</span></td>
          <td class="fw-mono text-sm"><?= fmtMoney((float)$v['sous_total']) ?></td>
          <td class="fw-mono text-sm"><?= fmtMoney((float)$v['tva_total']) ?></td>
          <td class="fw-mono c-teal"><?= fmtMoney((float)$v['total']) ?></td>
          <td class="text-sm"><?= e($modeLabels[$v['mode_paiement']] ?? $v['mode_paiement']) ?></td>
          <td class="text-sm"><?= e(trim($v['prenom'].' '.$v['u_nom'])) ?></td>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
          <td>
            <button class="btn btn-ghost btn-xs" onclick="showDetail(<?= $v['id'] ?>)">
              <?= icon('eye',13) ?> Voir
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ventes): ?>
        <tr><td colspan="10">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucune vente sur cette période</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($page, $perPage, $totalVentes, ['debut'=>$dateDebut,'fin'=>$dateFin,'mode'=>$mode]) ?>
</div>

<!-- Modal détail vente -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="detail-ref">Détail vente</div>
      <button class="modal-close" onclick="closeModal('modal-detail')">✕</button>
    </div>
    <div id="detail-body" class="card-pad"></div>
    <div class="modal-footer" id="detail-footer" style="display:none;">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-detail')">Fermer</button>
      <button class="btn btn-primary btn-sm" onclick="printReceiptFromHistory()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>
        Imprimer ticket
      </button>
    </div>
  </div>
</div>

<script>
const ventesData = <?= json_encode(array_column($ventes, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const lignesData = <?= json_encode($lignes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const modeLabels = <?= json_encode($modeLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const devSym     = <?= json_encode(getParam('devise_symbole','FCFA'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const devPos     = <?= json_encode(getParam('devise_pos','after'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const appNom     = <?= json_encode($appNom, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const tvaTaux    = <?= json_encode($tvaTaux, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const TICKET_TITLE = <?= json_encode($ticketSousTitre, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const TICKET_FOOT  = <?= json_encode($ticketPied, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const PHARM_ADDR = <?= json_encode($pharmAdresse, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const PHARM_TEL  = <?= json_encode($pharmTel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const PHARM_NIF  = <?= json_encode($pharmNif, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const PHARM_LOGO = <?= json_encode($pharmLogoUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const APP_BRAND  = <?= json_encode(defined('APP_NAME') ? APP_NAME : 'PharmaCare', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const TICKET_COPIES = <?= (int)$ticketCopies ?>;
function ticketCopyLabel(i){ return i===0 ? 'Exemplaire Client' : (i===1 ? 'Exemplaire Caisse' : ('Exemplaire '+(i+1))); }
const PHARMACIE_NOMS = <?= json_encode($pharmacieNoms, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let currentReceiptVid = null;

function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function fmtDA(n) {
  const f = Math.round(n).toLocaleString('fr-FR');
  return devPos === 'before' ? devSym + ' ' + f : f + ' ' + devSym;
}

// Rendu du ticket thermique — identique au ticket imprimé au POS (modules/vente.php #thermal-ticket)
function buildThermalTicket(v, lignes) {
  const phNom = PHARMACIE_NOMS[parseInt(v.pharmacie_id, 10)] || '';
  const lignesHtml = lignes.length
    ? lignes.map(l => `
        <div style="display:flex;justify-content:space-between;margin-bottom:1px;">
          <span>${escHtml(l.produit_nom)}</span><span>${fmtDA(parseFloat(l.total_ligne))}</span>
        </div>
        <div style="font-size:10px;color:#64748b;margin-bottom:3px;">
          ${fmtDA(parseFloat(l.prix_unitaire))} &times; ${(parseInt(l.quantite, 10))}
        </div>`).join('')
    : '<div style="padding:8px;text-align:center;color:#94a3b8;">Aucune ligne</div>';
  const remise = parseFloat(v.remise_pct || 0) > 0
    ? `<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--red,#ef4444);">
         <span>Remise ${(parseFloat(v.remise_pct)).toString()}% (HT)</span><span>-${fmtDA(parseFloat(v.remise_montant || 0))}</span>
       </div>` : '';
  const espece = (v.mode_paiement === 'espèces' && parseFloat(v.montant_recu || 0) > 0)
    ? `<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2,#475569);margin-top:4px;">
         <span>Reçu</span><span>${fmtDA(parseFloat(v.montant_recu))}</span>
       </div>
       <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2,#475569);">
         <span>Reliquat</span><span>${fmtDA(parseFloat(v.monnaie || 0))}</span>
       </div>` : '';
  const dt = v.created_at ? new Date(String(v.created_at).replace(' ', 'T')).toLocaleString('fr-FR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '';
  return `
    <div id="thermal-ticket" style="font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;max-width:280px;margin:0 auto;padding:10px 0;">
      <div style="text-align:center;border-bottom:1px dashed var(--border2,#cbd5e1);padding-bottom:10px;margin-bottom:10px;">
        ${PHARM_LOGO ? `<div style="margin-bottom:6px;"><img src="${escHtml(PHARM_LOGO)}" alt="" style="max-height:60px;max-width:90%;"></div>` : ''}
        <div style="font-family:var(--font-title,serif);font-size:16px;font-weight:600;">${escHtml(appNom)}</div>
        <div style="font-size:10px;color:var(--text3,#94a3b8);margin-top:2px;">${escHtml(TICKET_TITLE)}</div>
        ${phNom ? `<div style="font-size:11px;font-weight:600;margin-top:3px;color:var(--teal2,#0d9488);">${escHtml(phNom)}</div>` : ''}
        ${PHARM_ADDR ? `<div style="font-size:10px;color:var(--text3,#94a3b8);margin-top:2px;">${escHtml(PHARM_ADDR)}</div>` : ''}
        ${PHARM_TEL  ? `<div style="font-size:10px;color:var(--text3,#94a3b8);margin-top:1px;">${escHtml(PHARM_TEL)}</div>` : ''}
        ${PHARM_NIF  ? `<div style="font-size:10px;color:var(--text3,#94a3b8);margin-top:1px;">${escHtml(PHARM_NIF)}</div>` : ''}
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3,#94a3b8);margin-bottom:8px;">
        <span>${escHtml(v.reference)}</span><span>${dt}</span>
      </div>
      ${v.client_nom ? `<div style="font-size:11px;color:var(--text3,#94a3b8);margin-bottom:8px;">Client : ${escHtml(v.client_nom)}</div>` : ''}
      <div style="border-top:1px dashed var(--border2,#cbd5e1);border-bottom:1px dashed var(--border2,#cbd5e1);padding:6px 0;margin-bottom:8px;">
        ${lignesHtml}
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2,#475569);">
        <span>Sous-total HT</span><span>${fmtDA(parseFloat(v.sous_total || 0))}</span>
      </div>
      ${remise}
      ${parseFloat(v.tva_total || 0) > 0 ? `<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2,#475569);">
        <span>TVA (${escHtml(tvaTaux)}%)</span><span>${fmtDA(parseFloat(v.tva_total || 0))}</span>
      </div>` : ''}
      <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:6px;padding-top:6px;border-top:1px dashed var(--border2,#cbd5e1);">
        <span>TOTAL TTC</span><span style="color:var(--teal2,#0d9488);">${fmtDA(parseFloat(v.total || 0))}</span>
      </div>
      ${espece}
      <div style="text-align:center;margin-top:12px;padding-top:8px;border-top:1px dashed var(--border2,#cbd5e1);font-size:10px;color:var(--text3,#94a3b8);">
        ${escHtml(modeLabels[v.mode_paiement] || v.mode_paiement)}<br>
        Caissier : ${escHtml(((v.prenom || '') + ' ' + (v.u_nom || '')).trim())}<br>
        ${escHtml(TICKET_FOOT)}<br>
        <span style="color:var(--text3,#94a3b8);">&copy; ${new Date().getFullYear()} ${escHtml(APP_BRAND)}</span>
      </div>
    </div>`;
}

function showDetail(vid) {
  const v = ventesData[vid];
  if (!v) return;
  currentReceiptVid = vid;
  document.getElementById('detail-ref').textContent = v.reference;
  document.getElementById('detail-footer').style.display = 'flex';
  const lignes = lignesData.filter(l => parseInt(l.vente_id, 10) === parseInt(vid, 10));
  document.getElementById('detail-body').innerHTML = buildThermalTicket(v, lignes);
  openModal('modal-detail');
}

function printReceiptFromHistory() {
  if (!rateLimitClick('print.ticketHist', 15, 60000)) { rateLimitWarn('print.ticketHist', 15, 60000); return; }
  const vid = currentReceiptVid;
  if (!vid) return;
  // Même contenu que le ticket affiché (et que le ticket imprimé au POS)
  const content = document.getElementById('thermal-ticket').outerHTML;
  let copies = '';
  for (let i = 0; i < TICKET_COPIES; i++) {
    copies += `<div class="ticket-copy">
      <div class="copy-label">${escHtml(ticketCopyLabel(i))}</div>
      ${content}
    </div>`;
  }
  const win = window.open('', '_blank', 'width=320,height=600');
  win.document.write(`<!DOCTYPE html><html><head><title>Ticket ${escHtml(ventesData[vid].reference)}</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;padding:8px;max-width:280px;margin:0 auto;}
      .ticket-copy{page-break-after:always;}
      .ticket-copy:last-child{page-break-after:auto;}
      .copy-label{text-align:center;font-size:10px;font-weight:700;letter-spacing:1px;color:#0d9488;border:1px dashed #0d9488;border-radius:4px;padding:3px 0;margin-bottom:6px;text-transform:uppercase;}
      @media print{body{margin:0;}@page{margin:0;size:80mm auto;}}
    </style>
    <link href="<?= APP_URL ?>/assets/fonts/fonts.css" rel="stylesheet">
  </head><body>${copies}<script>window.onload=function(){window.print();}<\/script></body></html>`);
  win.document.close();
}
</script>

<?php layout_foot(); ?>
