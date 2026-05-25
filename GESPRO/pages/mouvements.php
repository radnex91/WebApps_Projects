<?php /* pages/mouvements.php */
$pageTitle = 'Mouvements — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout_top.php';

$filArticle = (int)($_GET['article'] ?? 0);
$filType    = $_GET['type'] ?? '';
$filMois    = (int)($_GET['mois'] ?? 0);
$filAnnee   = (int)($_GET['annee'] ?? date('Y'));

$where = ['m.annee = :annee'];
$params = ['annee' => $filAnnee];

if ($filArticle) { $where[] = 'm.article_id = :article_id'; $params['article_id'] = $filArticle; }
if ($filType)    { $where[] = 'm.type_mouvement = :type';   $params['type'] = $filType; }
if ($filMois)    { $where[] = 'm.mois = :mois';             $params['mois'] = $filMois; }

$whereStr = implode(' AND ', $where);
$mouvements = query(
    "SELECT m.*, a.designation, a.unite, f.nom AS fournisseur_nom
     FROM mouvements m
     JOIN articles a ON m.article_id = a.id
     LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
     WHERE $whereStr
     ORDER BY m.date_mouvement DESC, m.id DESC
     LIMIT 500",
    $params
);

$articles = query("SELECT id, designation FROM articles WHERE actif=1 ORDER BY designation");
?>

<div class="page-header">
  <h1>Tous les Mouvements</h1>
  <p>Historique complet des entrées et sorties</p>
</div>

<div class="filter-bar mb-16">
  <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
    <select name="article" class="form-control">
      <option value="">Tous les articles</option>
      <?php foreach ($articles as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filArticle == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['designation']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="form-control">
      <option value="">Entrées + Sorties</option>
      <option value="entree" <?= $filType === 'entree' ? 'selected' : '' ?>>Entrées</option>
      <option value="sortie" <?= $filType === 'sortie' ? 'selected' : '' ?>>Sorties</option>
    </select>
    <select name="mois" class="form-control">
      <option value="">Tous les mois</option>
      <?php foreach (MOIS_FR as $n => $label): ?>
      <option value="<?= $n ?>" <?= $filMois == $n ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <select name="annee" class="form-control">
      <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
      <option value="<?= $y ?>" <?= $y == $filAnnee ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    <a href="/pages/mouvements.php" class="btn btn-secondary btn-sm">Reset</a>
    <span class="text-muted ml-auto" style="align-self:center; font-size:12px;"><?= count($mouvements) ?> ligne(s)</span>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th class="sortable">Date</th>
        <th>Type</th>
        <th class="sortable">Matériau</th>
        <th>DA/BC</th>
        <th>BSM/BCL</th>
        <th class="text-right sortable">Qté</th>
        <th class="text-right sortable">PU</th>
        <th class="text-right sortable">Montant</th>
        <th class="text-right">Stock après</th>
        <th class="text-right">CMUPACE</th>
        <th>Affectation</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($mouvements as $m): ?>
    <tr class="<?= $m['type_mouvement']==='entree'?'entree-row':'sortie-row' ?>">
      <td class="mono"><?= date('d/m/Y', strtotime($m['date_mouvement'])) ?></td>
      <td class="type-cell">
        <span class="badge <?= $m['type_mouvement']==='entree'?'badge-green':'badge-red' ?>"><?= strtoupper($m['type_mouvement']) ?></span>
      </td>
      <td class="bold"><?= htmlspecialchars($m['designation']) ?></td>
      <td class="mono text-muted"><?= htmlspecialchars($m['da_bc'] ?? '—') ?></td>
      <td class="mono text-muted"><?= htmlspecialchars($m['bsm'] ?? $m['bcl_fact'] ?? '—') ?></td>
      <td class="mono text-right"><?= number_format($m['quantite'], 2, ',', ' ') ?> <?= htmlspecialchars($m['unite']) ?></td>
      <td class="amount"><?= number_format($m['prix_unitaire'], 0, ',', ' ') ?></td>
      <td class="amount <?= $m['type_mouvement']==='entree'?'text-green':'text-red' ?>"><?= number_format($m['montant'], 0, ',', ' ') ?></td>
      <td class="mono text-right"><?= number_format($m['stock_apres'], 0, ',', ' ') ?></td>
      <td class="mono text-right text-accent"><?= number_format($m['cmupace'], 0, ',', ' ') ?></td>
      <td class="text-muted"><?= htmlspecialchars($m['fournisseur_nom'] ?? $m['affectation_libre'] ?? '—') ?></td>
      <td>
        <div class="flex gap-8">
          <a href="/pages/mouvement_form.php?id=<?= $m['id'] ?>&article=<?= $m['article_id'] ?>&annee=<?= $m['annee'] ?>&mois=<?= $m['mois'] ?>"
             class="btn btn-secondary btn-sm">
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
          </a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($mouvements)): ?>
    <tr><td colspan="12" class="text-center text-muted" style="padding:32px;">Aucun mouvement trouvé</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
