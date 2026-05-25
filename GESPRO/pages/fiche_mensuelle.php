<?php
$pageTitle = 'Suivi Mensuel — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$articleId = (int)($_GET['article'] ?? 0);
$annee     = (int)($_GET['annee']   ?? date('Y'));
$mois      = (int)($_GET['mois']    ?? date('m'));

$articles = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");

if (!$articleId && !empty($articles)) {
    $articleId = (int)$articles[0]['id'];
}

$article = $articleId ? queryOne("SELECT * FROM articles WHERE id=?", [$articleId]) : null;
$mouvements = $articleId ? getMouvements($articleId, $annee, $mois) : [];

// Stock reporté du mois précédent
$prevMois = $mois === 1 ? 12 : $mois - 1;
$prevAnnee = $mois === 1 ? $annee - 1 : $annee;
$lastMvt = queryOne(
    "SELECT stock_apres, valeur_stock_apres, cmupace FROM mouvements
     WHERE article_id=? AND (annee < ? OR (annee=? AND mois < ?))
     ORDER BY date_mouvement DESC, id DESC LIMIT 1",
    [$articleId, $annee, $annee, $mois]
);

$stockReport     = $article ? (float)($lastMvt['stock_apres'] ?? $article['stock_actuel']) : 0;
$valeurReport    = $article ? (float)($lastMvt['valeur_stock_apres'] ?? $article['valeur_stock']) : 0;
$cmupaceReport   = $article ? (float)($lastMvt['cmupace'] ?? $article['cmupace']) : 0;

// Totaux
$totalEntreeQte = 0; $totalEntreeMt = 0;
$totalSortieQte = 0; $totalSortieMt = 0;
foreach ($mouvements as $m) {
    if ($m['type_mouvement'] === 'entree') {
        $totalEntreeQte += $m['quantite'];
        $totalEntreeMt  += $m['montant'];
    } else {
        $totalSortieQte += $m['quantite'];
        $totalSortieMt  += $m['montant'];
    }
}
$stockFinal = $stockReport + $totalEntreeQte - $totalSortieQte;
$lastCmupace = !empty($mouvements) ? (float)end($mouvements)['cmupace'] : $cmupaceReport;
?>

<div class="page-header">
  <h1>Suivi Mensuel de Consommation</h1>
  <p>Fiche de mouvement de stock par article et par mois</p>
</div>

<!-- Filtres -->
<div class="filter-bar mb-24">
  <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
    <div class="form-group mb-0">
      <select name="article" class="form-control" onchange="this.form.submit()">
        <?php foreach ($articles as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $a['id'] == $articleId ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['designation']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group mb-0">
      <select name="mois" class="form-control" onchange="this.form.submit()">
        <?php foreach (MOIS_FR as $n => $label): ?>
        <option value="<?= $n ?>" <?= $n == $mois ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group mb-0">
      <select name="annee" class="form-control" onchange="this.form.submit()">
        <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
        <option value="<?= $y ?>" <?= $y == $annee ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="ml-auto flex gap-8">
      <a href="/pages/mouvement_form.php?type=entree&article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>" class="btn btn-green btn-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + Entrée
      </a>
      <a href="/pages/mouvement_form.php?type=sortie&article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>" class="btn btn-danger btn-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + Sortie (BSM)
      </a>
      <button onclick="printPage()" class="btn btn-secondary btn-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimer
      </button>
    </div>
  </form>
</div>

<?php if ($article): ?>
<!-- En-tête de la fiche -->
<div class="fiche-header">
  <div>
    <div class="fiche-title">SUIVI DE CONSOMMATION — <?= strtoupper(htmlspecialchars($article['designation'])) ?></div>
    <div class="fiche-meta">
      <span><strong>PROJET :</strong> <?= NOM_PROJET ?></span>
      <span><strong>CODE PROJET :</strong> <?= CODE_PROJET ?></span>
      <span><strong>PÉRIODE :</strong> <?= strtoupper(MOIS_FR[$mois]) ?> <?= $annee ?></span>
    </div>
  </div>
  <div style="text-align:center;">
    <div style="font-size:11px; letter-spacing:1px; color:var(--text3); text-transform:uppercase; margin-bottom:4px;">Prix unitaire</div>
    <div style="font-family:var(--font-cond); font-size:22px; font-weight:700; color:var(--accent);">
      <?= number_format($article['prix_unitaire'], 0, ',', ' ') ?> FCFA
    </div>
    <div style="font-size:10px; color:var(--text3); margin-top:2px;">/ <?= htmlspecialchars($article['unite']) ?></div>
  </div>
  <div class="fiche-cmupace">
    <div class="label">CMUPACE Actuel</div>
    <div class="value"><?= number_format($lastCmupace, 0, ',', ' ') ?> FCFA</div>
    <div style="font-size:11px; color:var(--text3); margin-top:4px;">Stock final: <?= number_format($stockFinal, 2, ',', ' ') ?> <?= htmlspecialchars($article['unite']) ?></div>
  </div>
</div>

<!-- Tableau de la fiche -->
<div class="card mb-16">
  <div class="table-wrap" style="border:none; border-radius:0;">
    <table id="ficheTable">
      <thead>
        <tr>
          <th rowspan="2">DATE</th>
          <th rowspan="2">DA/BC</th>
          <th rowspan="2">BCL/FACT</th>
          <th colspan="3" style="text-align:center; background:rgba(63,185,80,0.05); border-bottom:1px solid var(--border);">ENTRÉE</th>
          <th rowspan="2">BSM</th>
          <th colspan="3" style="text-align:center; background:rgba(248,81,73,0.05); border-bottom:1px solid var(--border);">SORTIE</th>
          <th colspan="2" style="text-align:center; background:rgba(88,166,255,0.05); border-bottom:1px solid var(--border);">STOCK</th>
          <th rowspan="2">CMUPACE</th>
          <th rowspan="2">AFFECTATION</th>
          <th rowspan="2">Actions</th>
        </tr>
        <tr>
          <th style="background:rgba(63,185,80,0.05);" class="text-right">QTÉ</th>
          <th style="background:rgba(63,185,80,0.05);" class="text-right">PU</th>
          <th style="background:rgba(63,185,80,0.05);" class="text-right">MONTANT</th>
          <th style="background:rgba(248,81,73,0.05);" class="text-right">QTÉ</th>
          <th style="background:rgba(248,81,73,0.05);" class="text-right">PU</th>
          <th style="background:rgba(248,81,73,0.05);" class="text-right">MONTANT</th>
          <th style="background:rgba(88,166,255,0.05);" class="text-right">QTÉ</th>
          <th style="background:rgba(88,166,255,0.05);" class="text-right">MONTANT</th>
        </tr>
      </thead>
      <tbody>
        <!-- Ligne REPORT -->
        <tr class="report-row">
          <td class="mono"><?= date('d/m/Y', mktime(0,0,0, $mois, 1, $annee) - 86400) ?></td>
          <td colspan="10">REPORT DU MOIS PRÉCÉDENT</td>
          <td class="amount text-accent"><?= number_format($stockReport, 0, ',', ' ') ?></td>
          <td class="amount text-accent"><?= number_format($valeurReport, 0, ',', ' ') ?></td>
          <td class="mono text-accent"><?= number_format($cmupaceReport, 0, ',', ' ') ?></td>
          <td><span class="badge badge-gold">REPORT</span></td>
          <td></td>
        </tr>

        <?php foreach ($mouvements as $m): ?>
        <tr class="<?= $m['type_mouvement'] === 'entree' ? 'entree-row' : 'sortie-row' ?>">
          <td class="mono"><?= date('d/m/Y', strtotime($m['date_mouvement'])) ?></td>
          <td class="text-muted"><?= htmlspecialchars($m['da_bc'] ?? '') ?></td>
          <td class="mono text-muted"><?= htmlspecialchars($m['bcl_fact'] ?? '') ?></td>

          <?php if ($m['type_mouvement'] === 'entree'): ?>
            <td class="amount text-green"><?= number_format($m['quantite'], 2, ',', ' ') ?></td>
            <td class="mono text-right"><?= number_format($m['prix_unitaire'], 0, ',', ' ') ?></td>
            <td class="amount text-green"><?= number_format($m['montant'], 0, ',', ' ') ?></td>
            <td></td>
            <td></td><td></td><td></td>
          <?php else: ?>
            <td></td><td></td><td></td>
            <td class="mono text-muted"><?= htmlspecialchars($m['bsm'] ?? '') ?></td>
            <td class="amount text-red"><?= number_format($m['quantite'], 2, ',', ' ') ?></td>
            <td class="mono text-right"><?= number_format($m['prix_unitaire'], 0, ',', ' ') ?></td>
            <td class="amount text-red"><?= number_format($m['montant'], 0, ',', ' ') ?></td>
          <?php endif; ?>

          <?php if ($m['type_mouvement'] === 'sortie'): ?>
            <!-- BSM déjà dans la colonne sortie -->
          <?php endif; ?>

          <td class="mono text-right bold"><?= number_format($m['stock_apres'], 0, ',', ' ') ?></td>
          <td class="amount"><?= number_format($m['valeur_stock_apres'], 0, ',', ' ') ?></td>
          <td class="mono text-right text-accent"><?= number_format($m['cmupace'], 0, ',', ' ') ?></td>
          <td class="text-muted"><?= htmlspecialchars($m['fournisseur_nom'] ?? $m['affectation_libre'] ?? '') ?></td>
          <td>
            <div class="flex gap-8">
              <a href="/pages/mouvement_form.php?id=<?= $m['id'] ?>&article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>" 
                 class="btn btn-secondary btn-sm" title="Modifier">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <button onclick="supprimerMouvement(<?= $m['id'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($mouvements)): ?>
        <tr>
          <td colspan="15" style="text-align:center; padding:32px; color:var(--text3);">
            Aucun mouvement enregistré pour ce mois.<br>
            <a href="/pages/mouvement_form.php?type=entree&article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>" class="btn btn-green btn-sm" style="margin-top:12px; display:inline-flex;">+ Ajouter une entrée</a>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="font-family:var(--font-cond); font-weight:700; letter-spacing:1px;">TOTAUX DU MOIS</td>
          <td class="text-right text-green" style="font-family:var(--font-mono);"><?= number_format($totalEntreeQte, 2, ',', ' ') ?></td>
          <td class="text-right text-muted" style="font-size:10px;">—</td>
          <td class="text-right text-green" style="font-family:var(--font-mono);"><?= number_format($totalEntreeMt, 0, ',', ' ') ?></td>
          <td>—</td>
          <td class="text-right text-red" style="font-family:var(--font-mono);"><?= number_format($totalSortieQte, 2, ',', ' ') ?></td>
          <td>—</td>
          <td class="text-right text-red" style="font-family:var(--font-mono);"><?= number_format($totalSortieMt, 0, ',', ' ') ?></td>
          <td class="text-right" style="font-family:var(--font-mono); color:var(--blue);"><?= number_format($stockFinal, 2, ',', ' ') ?></td>
          <td class="text-right" style="font-family:var(--font-mono); color:var(--blue);"><?= number_format($stockFinal * $lastCmupace, 0, ',', ' ') ?></td>
          <td class="text-accent" style="font-family:var(--font-mono);"><?= number_format($lastCmupace, 0, ',', ' ') ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Zone signatures -->
<div class="signature-zone">
  <div style="font-size:11px; letter-spacing:1px; color:var(--text3); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Signatures et Validations</div>
  <div class="signature-grid">
    <div class="sig-box">
      <div class="title">Assistant Gestionnaire</div>
      <div class="line"></div>
    </div>
    <div class="sig-box">
      <div class="title">Gestionnaire des Stocks</div>
      <div class="line"></div>
    </div>
    <div class="sig-box">
      <div class="title">R.A.F</div>
      <div class="line"></div>
    </div>
    <div class="sig-box">
      <div class="title">Directeur des Travaux</div>
      <div class="line"></div>
    </div>
  </div>
</div>

<?php else: ?>
<div class="empty-state">
  <svg fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
  <p>Aucun article disponible. <a href="/pages/articles.php">Créer un article</a></p>
</div>
<?php endif; ?>

<script>
function supprimerMouvement(id) {
  if (!confirm('Supprimer ce mouvement ? Le stock sera recalculé.')) return;
  fetch('/ajax/delete_mouvement.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({id})
  })
  .then(r => r.json())
  .then(r => {
    if (r.success) { App.toast('Mouvement supprimé', 'success'); setTimeout(() => location.reload(), 800); }
    else App.toast(r.message || 'Erreur', 'error');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
