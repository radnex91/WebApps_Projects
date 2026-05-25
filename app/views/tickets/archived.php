<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-archive"></i> Tickets archivés</h1>
    <a href="/gestion-support/tickets" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<table class="table">
    <thead><tr><th>ID</th><th>Titre</th><th>Catégorie</th><th>Priorité</th><th>Statut</th><th>Assigné</th></tr></thead>
    <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
            <td><?= $t->id ?></td>
            <td><a href="/gestion-support/tickets/<?= $t->id ?>"><?= htmlspecialchars($t->title) ?></a></td>
            <td><?= htmlspecialchars($t->category_name ?? '-') ?></td>
            <td><?= htmlspecialchars($t->priority_name ?? '-') ?></td>
            <td><?= htmlspecialchars($t->status_name ?? '-') ?></td>
            <td><?= htmlspecialchars($t->assigned_name ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
