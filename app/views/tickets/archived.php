<div class="page-header">
    <div>
        <h1>Tickets archivés</h1>
        <div class="page-header-subtitle">Liste des tickets archivés</div>
    </div>
    <div>
        <a href="/gestion-support/tickets" class="btn-material btn-material-outline"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>
</div>
<table class="table-material">
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