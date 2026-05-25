<?php $title = 'Nouvelle demande de congé'; ?>
<div class="row"><div class="col-md-6 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-calendar-plus"></i> Nouvelle demande</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hr/leaves') ?>">
            <div class="mb-3"><label class="form-label">Employé *</label>
                <select name="employee_id" class="form-select" required><option value="">— Sélectionner —</option><?php foreach($employees as $e): ?><option value="<?=$e['id']?>"><?=e($e['prenom'].' '.$e['nom'])?></option><?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="form-label">Type</label>
                <select name="type" class="form-select"><?php foreach(['conge_paye','maladie','maternite','sans_solde','autre'] as $t): ?><option value="<?=$t?>"><?=ucfirst(str_replace('_',' ',$t))?></option><?php endforeach; ?></select>
            </div>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label">Date début *</label><input type="date" name="date_debut" class="form-control" required></div><div class="col-md-6"><label class="form-label">Date fin *</label><input type="date" name="date_fin" class="form-control" required></div></div>
            <div class="mb-3"><label class="form-label">Motif</label><textarea name="motif" class="form-control" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary">Soumettre</button>
            <a href="<?= url('hr/leaves') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
