<?php $title = 'Clients'; ?>
<div class="page-header">
    <h2>Clients</h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/clients/create" class="btn btn-primary">+ Nouveau client</a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-form" style="margin-bottom:1rem">
            <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($query ?? '') ?>" onchange="this.form.submit()">
            <button type="submit" class="btn btn-sm btn-primary">Rechercher</button>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Nom</th><th>Téléphone</th><th>Email</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($clients['data'] as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c->getNomComplet()) ?></td>
                        <td><?= htmlspecialchars($c->telephone) ?></td>
                        <td><?= htmlspecialchars($c->email ?? '-') ?></td>
                        <td><a href="<?= BASE_URL ?>/clients/<?= $c->id ?>" class="btn btn-sm btn-info">Voir</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clients['data'])): ?>
                    <tr><td colspan="4" class="text-center text-muted">Aucun client</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>