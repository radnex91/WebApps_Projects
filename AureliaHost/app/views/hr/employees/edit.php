<?php $title = 'Modifier employé'; ?>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil"></i> Modifier employé</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hr/employees/' . $employee['id']) ?>">
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?= e($employee['nom']) ?>" required></div><div class="col-md-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" value="<?= e($employee['prenom']) ?>" required></div></div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($employee['email']) ?>"></div><div class="col-md-6"><label class="form-label">Téléphone</label><input type="text" name="telephone" class="form-control" value="<?= e($employee['telephone']) ?>"></div></div>
            <div class="mb-3"><label class="form-label">Adresse</label><textarea name="adresse" class="form-control" rows="2"><?= e($employee['adresse']) ?></textarea></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Département</label><select name="department_id" class="form-select"><option value="">— Aucun —</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>" <?=$employee['department_id']==$d['id']?'selected':''?>><?=e($d['nom'])?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Poste</label><input type="text" name="poste" class="form-control" value="<?= e($employee['poste']) ?>"></div><div class="col-md-4"><label class="form-label">Date embauche</label><input type="date" name="date_embauche" class="form-control" value="<?= $employee['date_embauche'] ?>"></div></div>
            <div class="row mb-3"><div class="col-md-4"><label class="form-label">Salaire base</label><input type="number" step="0.01" name="salaire_base" class="form-control" value="<?= $employee['salaire_base'] ?>"></div><div class="col-md-4"><label class="form-label">Type contrat</label><select name="type_contrat" class="form-select"><?php foreach(['cdi','cdd','stage','freelance'] as $t): ?><option value="<?=$t?>" <?=$employee['type_contrat']===$t?'selected':''?>><?=strtoupper($t)?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Statut</label><select name="statut" class="form-select"><?php foreach(['actif','inactif','suspendu'] as $s): ?><option value="<?=$s?>" <?=$employee['statut']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach; ?></select></div></div>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
            <a href="<?= url('hr/employees') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
