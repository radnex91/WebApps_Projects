<?php
$editing    = isset($client);
$page_title = $editing ? 'Modifier le client' : 'Nouveau client';
$val = fn(string $k) => e($client[$k] ?? ($_POST[$k] ?? ''));
?>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-person-plus me-2 text-primary"></i><?= $page_title ?></h5>
    <a href="<?= APP_URL ?>/index.php?page=clients" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
  </div>
  <div class="card-body">
    <form method="POST" action="<?= APP_URL ?>/index.php?page=clients&action=<?= $editing ? 'update' : 'store' ?>">
      <?= csrf_field() ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $client['id'] ?>"><?php endif; ?>

      <div class="row g-3">
        <div class="col-12"><div class="fw-bold text-muted border-bottom pb-2 mb-1 small text-uppercase">Identité</div></div>

        <div class="col-md-6">
          <label class="form-label">Prénom <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="prenom" required value="<?= $val('prenom') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nom <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="nom" required value="<?= $val('nom') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="<?= $val('email') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Téléphone <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="telephone" required value="<?= $val('telephone') ?>"
                 placeholder="+237 6XX XXX XXX">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nationalité</label>
          <input type="text" class="form-control" name="nationalite" value="<?= $val('nationalite') ?>"
                 placeholder="Camerounaise">
        </div>
        <div class="col-md-4">
          <label class="form-label">Date de naissance</label>
          <input type="date" class="form-control" name="date_naissance"
                 value="<?= $val('date_naissance') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Type de client</label>
          <select class="form-select" name="type_client">
            <?php foreach (['standard'=>'Standard','fidele'=>'Fidèle','vip'=>'VIP','professionnel'=>'Professionnel'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($client['type_client']??'standard') === $v ? 'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 mt-2"><div class="fw-bold text-muted border-bottom pb-2 mb-1 small text-uppercase">Pièce d'identité</div></div>
        <div class="col-md-4">
          <label class="form-label">Type de pièce</label>
          <select class="form-select" name="type_piece">
            <?php foreach (['CNI'=>'CNI','Passeport'=>'Passeport','Permis'=>'Permis de conduire','Carte_sejour'=>"Carte de séjour",'Autre'=>'Autre'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($client['type_piece']??'CNI') === $v ? 'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Numéro de pièce</label>
          <input type="text" class="form-control" name="numero_piece" value="<?= $val('numero_piece') ?>">
        </div>

        <div class="col-12 mt-2"><div class="fw-bold text-muted border-bottom pb-2 mb-1 small text-uppercase">Adresse</div></div>
        <div class="col-12">
          <label class="form-label">Adresse complète</label>
          <textarea class="form-control" name="adresse" rows="2"><?= $val('adresse') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Ville</label>
          <input type="text" class="form-control" name="ville" value="<?= $val('ville') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Pays</label>
          <input type="text" class="form-control" name="pays" value="<?= $val('pays') ?: 'Cameroun' ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="2"
                    placeholder="Préférences, remarques..."><?= $val('notes') ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3">
          <a href="<?= APP_URL ?>/index.php?page=clients" class="btn btn-outline-secondary">Annuler</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check me-1"></i><?= $editing ? 'Mettre à jour' : 'Créer le client' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>
