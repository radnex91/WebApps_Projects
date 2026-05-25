<?php $title = 'Générer bulletins de paie'; ?>
<div class="row"><div class="col-md-8 mx-auto">
    <div class="card"><div class="card-header"><h5 class="mb-0"><i class="bi bi-cash-stack"></i> Générer bulletins</h5></div>
    <div class="card-body">
        <form method="post" action="<?= url('hr/payroll') ?>">
            <div class="mb-3"><label class="form-label">Mois *</label><input type="month" name="mois" class="form-control" value="<?= $defaultMonth ?>"></div>
            <div class="mb-3"><label class="form-label">Employés *</label>
                <div class="row g-2">
                    <?php foreach ($employees as $e): ?>
                    <div class="col-md-6">
                        <div class="form-check card p-2">
                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= $e['id'] ?>" id="emp_<?= $e['id'] ?>">
                            <label class="form-check-label" for="emp_<?= $e['id'] ?>">
                                <?= e($e['prenom'].' '.$e['nom']) ?><br>
                                <small class="text-muted"><?= e($e['poste']) ?> — <?= formatMoney($e['salaire_base']) ?></small>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Générer</button>
            <a href="<?= url('hr/payroll') ?>" class="btn btn-secondary">Annuler</a>
        </form>
    </div></div>
</div></div>
