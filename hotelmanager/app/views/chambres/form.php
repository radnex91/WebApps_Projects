<?php $page_title = isset($chambre) ? 'Modifier la chambre' : 'Nouvelle chambre'; ?>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-door-open me-2"></i><?= $page_title ?></h5>
    <a href="<?= APP_URL ?>/index.php?page=chambres" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
  </div>
  <div class="card-body">
    <form method="POST" action="<?= APP_URL ?>/index.php?page=chambres&action=<?= isset($chambre) ? 'update' : 'store' ?>">
      <?= csrf_field() ?>
      <?php if (isset($chambre)): ?>
      <input type="hidden" name="id" value="<?= $chambre['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Numéro <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="numero" required
                 value="<?= e($chambre['numero'] ?? '') ?>" placeholder="101">
        </div>
        <div class="col-md-4">
          <label class="form-label">Type <span class="text-danger">*</span></label>
          <select class="form-select" name="type_id" required>
            <?php foreach ($types as $t): ?>
            <option value="<?= $t['id'] ?>" <?= ($chambre['type_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
              <?= e($t['nom']) ?> — <?= format_money($t['tarif_nuit']) ?>/nuit
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Étage</label>
          <input type="number" class="form-control" name="etage" min="0" max="50"
                 value="<?= $chambre['etage'] ?? 1 ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Statut</label>
          <select class="form-select" name="statut">
            <option value="disponible" <?= ($chambre['statut'] ?? '') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
            <option value="nettoyage"  <?= ($chambre['statut'] ?? '') === 'nettoyage'  ? 'selected' : '' ?>>En nettoyage</option>
            <option value="maintenance"<?= ($chambre['statut'] ?? '') === 'maintenance'? 'selected' : '' ?>>Maintenance</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="2"><?= e($chambre['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Notes internes</label>
          <textarea class="form-control" name="notes_internes" rows="2"
                    placeholder="Visible uniquement par le personnel"><?= e($chambre['notes_internes'] ?? '') ?></textarea>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3">
          <a href="<?= APP_URL ?>/index.php?page=chambres" class="btn btn-outline-secondary">Annuler</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check me-1"></i><?= isset($chambre) ? 'Enregistrer' : 'Créer la chambre' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>
