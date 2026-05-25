<?php $page_title = 'Nouveau compte utilisateur'; ?>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-person-plus me-2"></i>Créer un compte</h5>
    <a href="?page=personnel" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
  </div>
  <div class="card-body">
    <form method="POST" action="?page=personnel&action=store">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Prénom <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="prenom" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Nom <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="nom" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control" name="email" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Téléphone</label>
          <input type="text" class="form-control" name="telephone">
        </div>
        <div class="col-md-6">
          <label class="form-label">Rôle <span class="text-danger">*</span></label>
          <select class="form-select" name="role_id" required>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>"><?= ucfirst(e($r['nom'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Mot de passe <span class="text-danger">*</span></label>
          <input type="password" class="form-control" name="password" required minlength="8"
                 placeholder="Min. 8 caractères">
          <div class="form-text">Minimum 8 caractères</div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3">
          <a href="?page=personnel" class="btn btn-outline-secondary">Annuler</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check me-1"></i>Créer le compte
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>
