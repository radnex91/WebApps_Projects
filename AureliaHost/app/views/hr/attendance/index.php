<?php $title = 'Présences'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-clock"></i> Présences</h4>
    <div class="d-flex gap-2">
        <a href="<?= url('hr/attendance/report') ?>" class="btn btn-outline-info"><i class="bi bi-file-earmark-bar-graph"></i> Rapport mensuel</a>
    </div>
</div>
<form class="row g-2 mb-3 align-items-end" method="get">
    <div class="col-auto"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= $date ?>"></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i> Afficher</button></div>
</form>

<div class="card mb-3"><div class="card-header"><h6 class="mb-0">Enregistrement rapide — <?= formatDate($date, 'l d/m/Y') ?></h6></div>
<div class="card-body">
    <form method="post" action="<?= url('hr/attendance') ?>" class="row g-2">
        <input type="hidden" name="date" value="<?= $date ?>">
        <div class="col-md-3"><select name="employee_id" class="form-select" required><option value="">— Employé —</option><?php foreach($employees as $e): ?><option value="<?=$e['id']?>"><?=e($e['prenom'].' '.$e['nom'])?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small">Entrée</label><input type="time" name="heure_entree" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">Sortie</label><input type="time" name="heure_sortie" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">Statut</label><select name="statut" class="form-select"><?php foreach(['present','absent','retard','congé'] as $s): ?><option value="<?=$s?>"><?=ucfirst($s)?></option><?php endforeach; ?></select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-success"><i class="bi bi-save"></i></button></div>
    </form>
</div></div>

<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Employé</th><th>Poste</th><th>Entrée</th><th>Sortie</th><th>Statut</th></tr></thead>
        <tbody>
            <?php foreach ($attendances as $a): ?>
            <tr><td><?= e($a['prenom'].' '.$a['nom']) ?></td><td><?= e($a['poste']) ?></td><td><?= $a['heure_entree'] ?? '—' ?></td><td><?= $a['heure_sortie'] ?? '—' ?></td><td><span class="badge bg-<?= $a['statut']==='present'?'success':($a['statut']==='absent'?'danger':($a['statut']==='retard'?'warning':'info')) ?>"><?= $a['statut'] ?></span></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
