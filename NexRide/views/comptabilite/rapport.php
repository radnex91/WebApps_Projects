<?php $title = 'Rapport comptable'; ?>

<div class="page-header">
    <h2>Rapport comptable</h2>
    <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-secondary">Retour</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Rapport mensuel <?= $annee ?></h3>
        <form method="GET" class="filter-form">
            <select name="annee" class="form-control" onchange="this.form.submit()">
                <?php for ($a = date('Y'); $a >= date('Y') - 3; $a--): ?>
                    <option value="<?= $a ?>" <?= $annee == $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Mois</th><th>Total Débit</th><th>Total Crédit</th><th>Solde</th></tr></thead>
                <tbody>
                    <?php foreach ($rapportMensuel as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r->mois) ?></strong></td>
                        <td><?= $formatMoney($r->total_debit) ?></td>
                        <td><?= $formatMoney($r->total_credit) ?></td>
                        <td><strong><?= $formatMoney($r->total_credit - $r->total_debit) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rapportMensuel)): ?>
                    <tr><td colspan="4" class="text-center text-muted">Aucune donnée pour cette année</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-body">
        <div class="chart-container">
            <div class="chart-bars">
                <?php $maxVal = max(array_merge(array_column($rapportMensuel, 'total_credit'), array_column($rapportMensuel, 'total_debit'), [1])); ?>
                <?php foreach ($rapportMensuel as $r): ?>
                <div class="chart-group">
                    <div class="chart-bar chart-bar-red" style="height: <?= ($r->total_debit / $maxVal) * 150 ?>px;" title="Débit: <?= $formatMoney($r->total_debit) ?>"></div>
                    <div class="chart-bar chart-bar-green" style="height: <?= ($r->total_credit / $maxVal) * 150 ?>px;" title="Crédit: <?= $formatMoney($r->total_credit) ?>"></div>
                    <div class="chart-label"><?= htmlspecialchars(substr($r->mois, 5, 2)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>