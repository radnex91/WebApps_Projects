<?php $title = 'Bordereaux'; ?>

<div class="page-header">
    <h2>Bordereaux</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/bordereaux/create" class="btn btn-primary">+ Nouveau bordereau</a>
    </div>
</div>

<div class="stats-row">
    <div class="stat-mini">Total: <strong><?= $stats->total ?></strong></div>
    <div class="stat-mini">Recettes: <strong><?= $formatMoney($stats->total_recettes) ?></strong></div>
    <div class="stat-mini">Dépenses: <strong><?= $formatMoney($stats->total_depenses) ?></strong></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Réf.</th><th>Date</th><th>Type</th><th>Montant</th><th>Statut</th><th>Créé par</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bordereaux['data'] as $b): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/bordereaux/<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></a></td>
                        <td><?= \Core\Helpers::formatDate($b->date_bordereau) ?></td>
                        <td><?= \Core\Helpers::statusBadge($b->type) ?></td>
                        <td><?= $formatMoney($b->montant_total) ?></td>
                        <td><?= \Core\Helpers::statusBadge($b->statut) ?></td>
                        <td><?= htmlspecialchars($b->created_nom ?? '-') ?></td>
                        <td><a href="<?= BASE_URL ?>/bordereaux/<?= $b->id ?>" class="btn btn-sm btn-info">Voir</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bordereaux['data'])): ?>
                    <tr><td colspan="7" class="text-center text-muted">Aucun bordereau</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($bordereaux['last_page'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $bordereaux['last_page']; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-link <?= $i === $bordereaux['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>