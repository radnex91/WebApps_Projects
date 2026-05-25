<?php $title = 'Billets'; ?>

<div class="page-header">
    <h2>Billets</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/billets/create" class="btn btn-primary">+ Nouveau billet</a>
    </div>
</div>

<div class="stats-row">
    <div class="stat-mini">Total: <strong><?= $stats->total ?></strong></div>
    <div class="stat-mini">Confirmés: <strong><?= $stats->confirmes ?></strong></div>
    <div class="stat-mini">Annulés: <strong><?= $stats->annules ?></strong></div>
    <div class="stat-mini">Recette: <strong><?= $formatMoney($stats->recette) ?></strong></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <select name="statut" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <?php foreach (unserialize(BILLET_STATUS) as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($statut ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Référence</th><th>Passager</th><th>Téléphone</th><th>Trajet</th><th>Montant</th><th>Paiement</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($billets['data'] as $b): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/billets/<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></a></td>
                        <td><?= htmlspecialchars($b->nom_passager) ?></td>
                        <td><?= htmlspecialchars($b->telephone_passager) ?></td>
                        <td><?= htmlspecialchars($b->depart ?? '') ?> &rarr; <?= htmlspecialchars($b->destination ?? '') ?></td>
                        <td><?= $formatMoney($b->montant_total) ?></td>
                        <td><?= \Core\Helpers::statusBadge($b->mode_paiement) ?></td>
                        <td><?= \Core\Helpers::statusBadge($b->statut) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/billets/<?= $b->id ?>" class="btn btn-sm btn-info">Voir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($billets['data'])): ?>
                    <tr><td colspan="8" class="text-center text-muted">Aucun billet trouvé</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($billets['last_page'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $billets['last_page']; $i++): ?>
                <a href="?page=<?= $i ?><?= $statut ? '&statut='.$statut : '' ?>" class="page-link <?= $i === $billets['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>