<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('ventes_hist.voir');
$db = getDB();

$dateDebut = $_GET['debut'] ?? date('Y-m-01');
$dateFin   = $_GET['fin']   ?? date('Y-m-d');
$mode      = $_GET['mode']  ?? '';
$estAdmin  = isAdmin();

// WHERE avec alias v. pour les requêtes avec jointures
$whereV = "DATE(v.created_at) BETWEEN " . $db->quote($dateDebut) . " AND " . $db->quote($dateFin);
if ($mode) $whereV .= " AND v.mode_paiement=" . $db->quote($mode);
if (!$estAdmin) $whereV .= " AND v.caissier_id=" . (int)$_SESSION['user_id'];

// WHERE sans alias pour requête simple
$whereS = "DATE(created_at) BETWEEN " . $db->quote($dateDebut) . " AND " . $db->quote($dateFin);
if ($mode) $whereS .= " AND mode_paiement=" . $db->quote($mode);
if (!$estAdmin) $whereS .= " AND caissier_id=" . (int)$_SESSION['user_id'];

$ventes = $db->query("
    SELECT v.*, u.prenom, u.nom AS u_nom, COUNT(vl.id) AS nb_lignes
    FROM ventes v
    LEFT JOIN utilisateurs u ON v.caissier_id = u.id
    LEFT JOIN vente_lignes vl ON vl.vente_id = v.id
    WHERE $whereV
    GROUP BY v.id ORDER BY v.created_at DESC
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

$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte bancaire',
    'chèque'    => 'Chèque',
    'assurance' => 'Assurance',
    'crédit'    => 'Crédit',
];

$appNom = getParam('app_nom', 'PharmaCare');
$tvaTaux = getParam('tva', '19.25');

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
        <a href="?" class="btn btn-ghost btn-sm"><?= icon('refresh',13) ?> Réinitialiser</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Historique des ventes</div>
    <span class="text-sm"><?= count($ventes) ?> résultat(s)</span>
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
let currentReceiptVid = null;

function fmtDA(n) {
  const f = Math.round(n).toLocaleString('fr-FR');
  return devPos === 'before' ? devSym + ' ' + f : f + ' ' + devSym;
}

function showDetail(vid) {
  const v = ventesData[vid];
  if (!v) return;
  currentReceiptVid = vid;
  document.getElementById('detail-ref').textContent = v.reference;
  document.getElementById('detail-footer').style.display = 'flex';
  const lignes = lignesData.filter(l => parseInt(l.vente_id) === parseInt(vid));
  const rowsHtml = lignes.length
    ? lignes.map(l => `
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px 7px;">${l.produit_nom}</td>
          <td style="padding:8px 7px;text-align:right;">${l.quantite}</td>
          <td style="padding:8px 7px;text-align:right;font-family:'DM Mono',monospace;">${fmtDA(l.prix_unitaire)}</td>
          <td style="padding:8px 7px;text-align:right;font-family:'DM Mono',monospace;color:var(--teal2);">${fmtDA(l.total_ligne)}</td>
        </tr>`).join('')
    : '<tr><td colspan="4" style="padding:16px;text-align:center;color:var(--text3);">Aucune ligne</td></tr>';

  document.getElementById('detail-body').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-size:13px;">
      <div><span style="color:var(--text3);">Client :</span> ${v.client_nom || '—'}</div>
      <div><span style="color:var(--text3);">Paiement :</span> ${modeLabels[v.mode_paiement] || v.mode_paiement}</div>
      <div><span style="color:var(--text3);">Reçu :</span> <span style="font-family:'DM Mono',monospace;">${fmtDA(v.montant_recu||0)}</span></div>
      <div><span style="color:var(--text3);">Monnaie :</span> <span style="font-family:'DM Mono',monospace;">${fmtDA(v.monnaie||0)}</span></div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="border-bottom:1px solid var(--border);">
        <th style="padding:7px;text-align:left;color:var(--text3);font-size:10px;text-transform:uppercase;">Médicament</th>
        <th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Qté</th>
        <th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Prix unit.</th>
        <th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Total</th>
      </tr></thead>
      <tbody>${rowsHtml}</tbody>
    </table>
    <div style="text-align:right;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);">
      <div style="font-size:12px;color:var(--text3);margin-bottom:4px;">
        Sous-total : ${fmtDA(v.sous_total)} &nbsp;·&nbsp; TVA : ${fmtDA(v.tva_total)}
      </div>
      <div style="font-family:var(--font-title,serif);font-size:24px;font-weight:600;color:var(--teal2);">
        Total : ${fmtDA(v.total)}
      </div>
    </div>`;
  openModal('modal-detail');
}

function printReceiptFromHistory() {
  if (!rateLimitClick('print.ticketHist', 15, 60000)) { rateLimitWarn('print.ticketHist', 15, 60000); return; }
  const vid = currentReceiptVid;
  if (!vid) return;
  const v = ventesData[vid];
  const lignes = lignesData.filter(l => parseInt(l.vente_id) === parseInt(vid));
  const linesHtml = lignes.map(l =>
    `<div style="display:flex;justify-content:space-between;"><span>${l.produit_nom} x${l.quantite}</span><span>${fmtDA(l.total_ligne)}</span></div>`
  ).join('');
  const receiptHtml = `
    <div style="text-align:center;border-bottom:1px dashed #aaa;padding-bottom:10px;margin-bottom:10px;">
      <div style="font-size:16px;font-weight:700;">${appNom}</div>
      <div style="font-size:10px;color:#666;margin-top:2px;">Gestion Pharmacie</div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;color:#666;margin-bottom:8px;">
      <span>${v.reference}</span><span>${new Date(v.created_at).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})}</span>
    </div>
    ${v.client_nom ? `<div style="font-size:11px;color:#666;margin-bottom:8px;">Client : ${v.client_nom}</div>` : ''}
    <div style="border-top:1px dashed #aaa;border-bottom:1px dashed #aaa;padding:6px 0;margin-bottom:8px;">${linesHtml}</div>
    <div style="display:flex;justify-content:space-between;font-size:11px;"><span>Sous-total HT</span><span>${fmtDA(v.sous_total)}</span></div>
    <div style="display:flex;justify-content:space-between;font-size:11px;"><span>TVA (${tvaTaux}%)</span><span>${fmtDA(v.tva_total)}</span></div>
    <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:6px;padding-top:6px;border-top:1px dashed #aaa;"><span>TOTAL TTC</span><span>${fmtDA(v.total)}</span></div>
    ${v.mode_paiement === 'espèces' && v.montant_recu > 0 ? `<div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;"><span>Reçu</span><span>${fmtDA(v.montant_recu)}</span></div><div style="display:flex;justify-content:space-between;font-size:11px;"><span>Monnaie</span><span>${fmtDA(v.monnaie)}</span></div>` : ''}
    <div style="text-align:center;margin-top:12px;padding-top:8px;border-top:1px dashed #aaa;font-size:10px;color:#666;">${modeLabels[v.mode_paiement] || v.mode_paiement}<br>Merci pour votre achat !</div>`;
  const win = window.open('', '_blank', 'width=320,height=600');
  win.document.write(`<!DOCTYPE html><html><head><title>Ticket ${v.reference}</title>
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;padding:8px;max-width:280px;margin:0 auto;}@media print{body{margin:0;}@page{margin:0;size:80mm auto;}}</style>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  </head><body>${receiptHtml}<script>window.onload=function(){window.print();}<\/script></body></html>`);
  win.document.close();
}
</script>

<?php layout_foot(); ?>
