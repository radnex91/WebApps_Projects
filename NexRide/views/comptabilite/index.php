<?php $title = 'Comptabilité'; ?>

<div class="page-header">
    <h2>Comptabilité</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/comptabilite/create" class="btn btn-primary">+ Nouvelle écriture</a>
        <a href="<?= BASE_URL ?>/comptabilite/rapport" class="btn btn-secondary">Rapports</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card stat-info">
        <div class="stat-value"><?= $formatMoney($totaux->total_debit) ?></div>
        <div class="stat-label">Total Débits</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-value"><?= $formatMoney($totaux->total_credit) ?></div>
        <div class="stat-label">Total Crédits</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-value"><?= $formatMoney($soldeGlobal) ?></div>
        <div class="stat-label">Solde caisse</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Journal comptable</h3>
        <form method="GET" class="filter-form">
            <input type="date" name="debut" value="<?= $debut ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            <input type="date" name="fin" value="<?= $fin ?>" class="form-control form-control-sm" onchange="this.form.submit()">
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Date</th><th>Réf.</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Type</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($journal as $e): ?>
                    <tr>
                        <td><?= \Core\Helpers::formatDate($e->date_ecriture) ?></td>
                        <td><?= htmlspecialchars($e->reference) ?></td>
                        <td><?= htmlspecialchars($e->libelle) ?></td>
                        <td><?= htmlspecialchars($e->compte) ?> - <?= htmlspecialchars($e->compte_label ?? '') ?></td>
                        <td><?= $e->debit > 0 ? $formatMoney($e->debit) : '-' ?></td>
                        <td><?= $e->credit > 0 ? $formatMoney($e->credit) : '-' ?></td>
                        <td><?= \Core\Helpers::statusBadge($e->type_operation) ?></td>
                        <td><?= \Core\Helpers::statusBadge($e->statut) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($journal)): ?>
                    <tr><td colspan="8" class="text-center text-muted">Aucune écriture pour cette période</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($balance)): ?>
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header"><h3>Balance des comptes</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Compte</th><th>Libellé</th><th>Total Débit</th><th>Total Crédit</th><th>Solde</th></tr></thead>
                <tbody>
                    <?php foreach ($balance as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b->compte) ?></td>
                        <td><?= htmlspecialchars($b->compte_label ?? '') ?></td>
                        <td><?= $formatMoney($b->total_debit) ?></td>
                        <td><?= $formatMoney($b->total_credit) ?></td>
                        <td><strong><?= $formatMoney($b->solde) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header"><h3>Caisses</h3></div>
    <div class="card-body">
        <div class="dashboard-grid">
            <?php foreach ($caisses as $c): ?>
            <div class="stat-card">
                <div class="stat-value"><?= $formatMoney($c->solde_actuel) ?></div>
                <div class="stat-label"><?= htmlspecialchars($c->libelle) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>