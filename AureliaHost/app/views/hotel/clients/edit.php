<?php $title = 'Modifier client'; ?>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier client</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/clients/' . $client['id']) ?>">
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?= e($client['nom']) ?>" required></div><div class="col-md-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" value="<?= e($client['prenom']) ?>" required></div></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($client['email']) ?>"></div><div class="col-md-6"><label class="form-label">Téléphone</label><input type="text" name="telephone" class="form-control" value="<?= e($client['telephone']) ?>"></div></div>
            <div class="mb-3"><label class="form-label">Adresse</label><textarea name="adresse" class="form-control" rows="2"><?= e($client['adresse']) ?></textarea></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control" value="<?= e($client['ville']) ?>"></div><div class="col-md-4"><label class="form-label">Pays</label><input type="text" name="pays" class="form-control" value="<?= e($client['pays']) ?>"></div><div class="col-md-4"><label class="form-label">Date naissance</label><input type="date" name="date_naissance" class="form-control" value="<?= $client['date_naissance'] ?>"></div></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Doc. type</label><select name="document_type" class="form-select"><option value="cin" <?= $client['document_type']==='cin'?'selected':'' ?>>CIN</option><option value="passeport" <?= $client['document_type']==='passeport'?'selected':'' ?>>Passeport</option></select></div><div class="col-md-8"><label class="form-label">N° document</label><input type="text" name="document_numero" class="form-control" value="<?= e($client['document_numero']) ?>"></div></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hotel/clients') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
