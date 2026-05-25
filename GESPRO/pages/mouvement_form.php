<?php
$pageTitle = 'Enregistrement Mouvement — ' . APP_TITLE;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$type      = $_GET['type'] ?? 'entree';
$articleId = (int)($_GET['article'] ?? 0);
$annee     = (int)($_GET['annee']   ?? date('Y'));
$mois      = (int)($_GET['mois']    ?? date('m'));
$editId    = (int)($_GET['id']      ?? 0);

$articles     = query("SELECT * FROM articles WHERE actif=1 ORDER BY designation");
$fournisseurs = query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom");

$editMvt = $editId ? queryOne("SELECT * FROM mouvements WHERE id=?", [$editId]) : null;
if ($editMvt) {
    $type      = $editMvt['type_mouvement'];
    $articleId = $editMvt['article_id'];
    $annee     = $editMvt['annee'];
    $mois      = $editMvt['mois'];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'article_id'      => (int)$_POST['article_id'],
        'type_mouvement'  => $_POST['type_mouvement'],
        'date_mouvement'  => $_POST['date_mouvement'],
        'quantite'        => (float)str_replace(',', '.', $_POST['quantite'] ?? 0),
        'prix_unitaire'   => (float)str_replace(',', '.', $_POST['prix_unitaire'] ?? 0),
        'da_bc'           => trim($_POST['da_bc'] ?? ''),
        'bcl_fact'        => trim($_POST['bcl_fact'] ?? ''),
        'bsm'             => trim($_POST['bsm'] ?? ''),
        'fournisseur_id'  => $_POST['fournisseur_id'] ? (int)$_POST['fournisseur_id'] : null,
        'affectation_libre' => trim($_POST['affectation_libre'] ?? ''),
        'observations'    => trim($_POST['observations'] ?? ''),
        'mois'            => (int)date('m', strtotime($_POST['date_mouvement'])),
        'annee'           => (int)date('Y', strtotime($_POST['date_mouvement'])),
        'created_by'      => currentUser()['id'],
    ];

    if ($data['quantite'] <= 0) $error = 'La quantité doit être supérieure à 0.';
    elseif ($data['prix_unitaire'] <= 0) $error = 'Le prix unitaire doit être supérieur à 0.';
    elseif (empty($data['date_mouvement'])) $error = 'La date est requise.';

    if (!$error) {
        if ($editId) {
            $fields = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            $data['id'] = $editId;
            execute("UPDATE mouvements SET $fields WHERE id = :id", $data);
        } else {
            $keys = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            execute("INSERT INTO mouvements ($keys) VALUES ($placeholders)", $data);
        }

        // Recalculer CMUPACE depuis ce mouvement
        calculerCMUPACE($data['article_id'], $data['date_mouvement']);
        // Mettre à jour analyse mensuelle
        updateAnalyseMensuelle($data['article_id'], $data['annee'], $data['mois']);

        header("Location: /pages/fiche_mensuelle.php?article={$data['article_id']}&annee={$data['annee']}&mois={$data['mois']}");
        exit;
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="page-header">
  <h1><?= $editId ? 'Modifier le mouvement' : ($type === 'entree' ? 'Nouvelle Entrée en Stock' : 'Nouvelle Sortie (BSM)') ?></h1>
  <p>
    <?= $type === 'entree' ? 'Réception de matériau ou approvisionnement via caisse' : 'Bon de Sortie Matériel (BSM) pour consommation sur chantier' ?>
  </p>
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="max-width:780px;">
<div class="card">
  <div class="card-header">
    <span class="card-title" style="color:<?= $type==='entree' ? 'var(--green)' : 'var(--red)' ?>">
      <?= $type === 'entree' ? '▼ ENTRÉE' : '▲ SORTIE (BSM)' ?>
    </span>
    <a href="/pages/fiche_mensuelle.php?article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>" class="btn btn-secondary btn-sm">
      ← Retour fiche
    </a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="type_mouvement" value="<?= htmlspecialchars($type) ?>">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Matériau <span class="required">*</span></label>
          <select name="article_id" class="form-control" required onchange="updatePU(this)">
            <?php foreach ($articles as $a): ?>
            <option value="<?= $a['id'] ?>" data-pu="<?= $a['prix_unitaire'] ?>"
              <?= ($editMvt ? $editMvt['article_id'] : $articleId) == $a['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($a['designation']) ?> (<?= htmlspecialchars($a['unite']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date <span class="required">*</span></label>
          <input type="date" name="date_mouvement" class="form-control" required
            value="<?= htmlspecialchars($editMvt['date_mouvement'] ?? date('Y-m-d')) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Quantité <span class="required">*</span></label>
          <input type="number" name="quantite" class="form-control" required step="0.001" min="0.001"
            value="<?= htmlspecialchars($editMvt['quantite'] ?? '') ?>" placeholder="0.000">
        </div>
        <div class="form-group">
          <label class="form-label">Prix Unitaire (FCFA) <span class="required">*</span></label>
          <input type="number" name="prix_unitaire" id="prix_unitaire" class="form-control" required step="1" min="1"
            value="<?= htmlspecialchars($editMvt['prix_unitaire'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Montant (FCFA)</label>
          <div class="form-control calc-montant" style="background:var(--bg3); color:var(--accent); font-family:var(--font-mono);">
            <?= $editMvt ? number_format($editMvt['montant'], 0, ',', ' ') : '—' ?>
          </div>
        </div>
      </div>

      <hr class="divider">
      <div style="font-size:11px; letter-spacing:1px; color:var(--text3); text-transform:uppercase; margin-bottom:14px; font-weight:600;">Références documentaires</div>

      <div class="form-row">
        <?php if ($type === 'entree'): ?>
        <div class="form-group">
          <label class="form-label">DA/BC <span class="text-muted">(Demande d'achat)</span></label>
          <input type="text" name="da_bc" class="form-control"
            value="<?= htmlspecialchars($editMvt['da_bc'] ?? '') ?>" placeholder="N° DA ou BC">
          <div class="form-hint">Laisser vide pour approvisionnement via caisse</div>
        </div>
        <div class="form-group">
          <label class="form-label">BCL / Facture</label>
          <input type="text" name="bcl_fact" class="form-control"
            value="<?= htmlspecialchars($editMvt['bcl_fact'] ?? '') ?>" placeholder="N° BCL ou Facture">
        </div>
        <?php else: ?>
        <div class="form-group">
          <label class="form-label">N° BSM <span class="required">*</span></label>
          <input type="text" name="bsm" class="form-control" required
            value="<?= htmlspecialchars($editMvt['bsm'] ?? '') ?>" placeholder="Ex: 125, 248, 316...">
          <div class="form-hint">Numéro du Bon de Sortie Matériel</div>
        </div>
        <?php endif; ?>
      </div>

      <hr class="divider">
      <div style="font-size:11px; letter-spacing:1px; color:var(--text3); text-transform:uppercase; margin-bottom:14px; font-weight:600;">Affectation</div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">
            <?= $type === 'entree' ? 'Fournisseur' : 'Destinataire / Chantier' ?>
          </label>
          <select name="fournisseur_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($fournisseurs as $f): ?>
            <option value="<?= $f['id'] ?>"
              <?= ($editMvt['fournisseur_id'] ?? null) == $f['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($f['nom']) ?> (<?= $f['type'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Affectation libre</label>
          <input type="text" name="affectation_libre" class="form-control"
            value="<?= htmlspecialchars($editMvt['affectation_libre'] ?? '') ?>"
            placeholder="BAKIDJA, RAMADAN, NGB, HYPOLITE...">
          <div class="form-hint">Utilisé si le fournisseur n'est pas dans la liste</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Observations</label>
        <textarea name="observations" class="form-control" rows="2" 
          placeholder="Notes ou remarques..."><?= htmlspecialchars($editMvt['observations'] ?? '') ?></textarea>
      </div>

      <div class="modal-footer" style="padding:0; padding-top:20px;">
        <a href="/pages/fiche_mensuelle.php?article=<?= $articleId ?>&annee=<?= $annee ?>&mois=<?= $mois ?>"
           class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <?= $editId ? 'Mettre à jour' : 'Enregistrer' ?>
        </button>
      </div>
    </form>
  </div>
</div>
</div>

<script>
function updatePU(sel) {
  const pu = sel.options[sel.selectedIndex]?.dataset?.pu;
  if (pu) document.getElementById('prix_unitaire').value = pu;
}
// Init
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('[name="article_id"]');
  if (sel && !document.getElementById('prix_unitaire').value) updatePU(sel);
});
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
