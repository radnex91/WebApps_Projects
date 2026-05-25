<?php $title = 'Voyages'; ?>

<div class="page-header">
    <h2>Voyages</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/voyages/create" class="btn btn-primary">+ Nouveau voyage</a>
    </div>
</div>

<div class="stats-row">
    <div class="stat-mini">Total: <strong><?= $stats->total ?></strong></div>
    <div class="stat-mini">Programmés: <strong><?= $stats->programmes ?></strong></div>
    <div class="stat-mini">En cours: <strong><?= $stats->en_cours ?></strong></div>
    <div class="stat-mini">Terminés: <strong><?= $stats->termines ?></strong></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <select name="statut" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="PROGRAMME" <?= ($statut ?? '') === 'PROGRAMME' ? 'selected' : '' ?>>Programmé</option>
                    <option value="EN_COURS" <?= ($statut ?? '') === 'EN_COURS' ? 'selected' : '' ?>>En cours</option>
                    <option value="TERMINE" <?= ($statut ?? '') === 'TERMINE' ? 'selected' : '' ?>>Terminé</option>
                    <option value="ANNULE" <?= ($statut ?? '') === 'ANNULE' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Réf.</th><th>Titre</th><th>Trajet</th><th>Départ</th><th>Places</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($voyages['data'] as $v): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/voyages/<?= $v->id ?>"><?= htmlspecialchars($v->reference) ?></a></td>
                        <td><?= htmlspecialchars($v->titre) ?></td>
                        <td><?= htmlspecialchars($v->depart) ?> &rarr; <?= htmlspecialchars($v->destination) ?></td>
                        <td><?= \Core\Helpers::formatDateTime($v->date_depart) ?></td>
                        <td><?= $v->places_disponibles ?>/<?= $v->nombre_places ?></td>
                        <td><?= \Core\Helpers::statusBadge($v->statut) ?></td>
                        <td><a href="<?= BASE_URL ?>/voyages/<?= $v->id ?>" class="btn btn-sm btn-info">Détails</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($voyages['data'])): ?>
                    <tr><td colspan="7" class="text-center text-muted">Aucun voyage trouvé</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($voyages['last_page'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $voyages['last_page']; $i++): ?>
                <a href="?page=<?= $i ?><?= $statut ? '&statut='.$statut : '' ?>" class="page-link <?= $i === $voyages['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>