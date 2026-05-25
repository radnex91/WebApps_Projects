<?php $title = 'Nouveau client'; ?>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-person-plus"></i> Nouveau client</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hotel/clients') ?>">
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div><div class="col-md-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div><div class="col-md-6"><label class="form-label">Téléphone</label><input type="text" name="telephone" class="form-control"></div></div>
            <div class="mb-3"><label class="form-label">Adresse</label><textarea name="adresse" class="form-control" rows="2"></textarea></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control" value=""></div><div class="col-md-4"><label class="form-label">Pays</label><input type="text" name="pays" class="form-control" value="Maroc"></div><div class="col-md-4"><label class="form-label">Date naissance</label><input type="date" name="date_naissance" class="form-control"></div></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Doc. type</label><select name="document_type" class="form-select"><option value="cin">CIN</option><option value="passeport">Passeport</option></select></div><div class="col-md-8"><label class="form-label">N° document</label><input type="text" name="document_numero" class="form-control"></div></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= url('hotel/clients') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
