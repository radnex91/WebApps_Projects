<?php $title = 'Employé ' . e($employee['prenom'].' '.$employee['nom']); ?>
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3"><div class="card-header"><h5 class="mb-0"><i class="bi bi-person-badge"></i> <?= e($employee['prenom'].' '.$employee['nom']) ?></h5></div>
        <div class="card-body">
            <table class="table table-sm">
                <tr><th>Poste</th><td><?= e($employee['poste']) ?></td></tr>
                <tr><th>Département</th><td><?= e($employee['department_nom']) ?></td></tr>
                <tr><th>Email</th><td><?= e($employee['email']) ?></td></tr>
                <tr><th>Téléphone</th><td><?= e($employee['telephone']) ?></td></tr>
                <tr><th>Contrat</th><td><span class="badge bg-info"><?= strtoupper($employee['type_contrat']) ?></span></td></tr>
                <tr><th>Salaire</th><td><?= formatMoney($employee['salaire_base']) ?></td></tr>
                <tr><th>Embauché le</th><td><?= $employee['date_embauche'] ? formatDate($employee['date_embauche']) : '—' ?></td></tr>
                <tr><th>Statut</th><td><span class="badge bg-<?= $employee['statut']==='actif'?'success':'danger' ?>"><?= $employee['statut'] ?></span></td></tr>
            </table>
            <a href="<?= url('hr/employees') ?>" class="btn btn-secondary btn-sm">Retour</a>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="bi bi-clock"></i> Présences récentes</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>Entrée</th><th>Sortie</th><th>Statut</th></tr></thead>
            <tbody><?php foreach ($attendance as $a): ?>
            <tr><td><?= formatDate($a['date']) ?></td><td><?= $a['heure_entree'] ?? '—' ?></td><td><?= $a['heure_sortie'] ?? '—' ?></td><td><?= $a['statut'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-heart"></i> Congés récents</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0">
            <thead><tr><th>Type</th><th>Du</th><th>Au</th><th>Statut</th></tr></thead>
            <tbody><?php foreach ($leaves as $l): ?>
            <tr><td><?= $l['type'] ?></td><td><?= formatDate($l['date_debut']) ?></td><td><?= formatDate($l['date_fin']) ?></td><td><span class="badge bg-<?= $l['statut']==='approuve'?'success':($l['statut']==='refuse'?'danger':'warning') ?>"><?= $l['statut'] ?></span></td></tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
    </div>
</div>
