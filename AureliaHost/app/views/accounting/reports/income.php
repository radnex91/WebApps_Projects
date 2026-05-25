<?php $title = 'Compte de résultat'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-graph-up"></i> Compte de résultat <?= $year ?></h4>
    <form method="get" class="d-flex gap-2"><input type="number" name="year" class="form-control" value="<?= $year ?>" min="2020" max="2099"><button class="btn btn-primary"><i class="bi bi-search"></i></button></form>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header bg-success text-white"><h6 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Revenus mensuels</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Mois</th><th class="text-end">Montant</th></tr></thead><tbody>
            <?php $revenusMap = []; foreach($revenus as $r) $revenusMap[$r['mois']] = $r['total'];
            for ($m = 1; $m <= 12; $m++): ?>
            <tr><td><?= date('F', mktime(0,0,0,$m,1)) ?></td><td class="text-end text-success"><?= formatMoney($revenusMap[$m] ?? 0) ?></td></tr>
            <?php endfor; ?>
        </tbody></table></div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="bi bi-arrow-down-circle"></i> Dépenses mensuelles</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Mois</th><th class="text-end">Montant</th></tr></thead><tbody>
            <?php $depensesMap = []; foreach($depenses as $d) $depensesMap[$d['mois']] = $d['total'];
            for ($m = 1; $m <= 12; $m++): ?>
            <tr><td><?= date('F', mktime(0,0,0,$m,1)) ?></td><td class="text-end text-danger"><?= formatMoney($depensesMap[$m] ?? 0) ?></td></tr>
            <?php endfor; ?>
        </tbody></table></div></div>
    </div>
</div>
<div class="card mt-3"><div class="card-header"><h6 class="mb-0">Résumé annuel</h6></div>
<div class="card-body">
    <div class="row text-center">
        <div class="col-md-4"><h5 class="text-success"><?= formatMoney($totalRevenus) ?></h5><small>Revenus totaux</small></div>
        <div class="col-md-4"><h5 class="text-danger"><?= formatMoney($totalDepenses) ?></h5><small>Dépenses totales</small></div>
        <div class="col-md-4"><h5 class="text-<?= ($totalRevenus-$totalDepenses)>=0?'success':'danger' ?>"><?= formatMoney($totalRevenus - $totalDepenses) ?></h5><small>Résultat net</small></div>
    </div>
</div></div>
