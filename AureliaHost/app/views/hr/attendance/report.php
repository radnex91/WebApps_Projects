<?php $title = 'Rapport de présence'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-file-earmark-bar-graph"></i> Rapport mensuel</h4>
    <form class="d-flex gap-2" method="get">
        <input type="month" name="month" class="form-control" value="<?= $month ?>">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead><tr><th>Employé</th><th>Poste</th><th>Présents</th><th>Absents</th><th>Retards</th><th>Congés</th></tr></thead>
        <tbody>
            <?php foreach ($report as $r): ?>
            <tr><td><?= e($r['prenom'].' '.$r['nom']) ?></td><td><?= e($r['poste']) ?></td><td class="text-success fw-bold"><?= $r['jours_presents'] ?></td><td class="text-danger fw-bold"><?= $r['jours_absents'] ?></td><td class="text-warning fw-bold"><?= $r['jours_retard'] ?></td><td class="text-info fw-bold"><?= $r['jours_conge'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
