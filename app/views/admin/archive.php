<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-archive"></i> Archives</h1>
    </div>
</div>

<?php if (empty($tickets)): ?>
<div class="empty-state">
    <i class="bi bi-archive" style="font-size:48px;color:#9e9e9e"></i>
    <p>Aucun ticket archivé</p>
</div>
<?php else: ?>
<div class="table-container">
    <table class="table-material">
        <thead>
            <tr><th>Titre</th><th>Date d'archivage</th><th style="width:120px">Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td>
                    <a href="<?= BASE_URL ?>/tickets/<?= $t->id ?>" class="ticket-link">
                        <?= htmlspecialchars($t->titre) ?>
                    </a>
                </td>
                <td class="text-secondary"><?= $t->updated_at ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/archive/restore/<?= $t->id ?>" style="display:inline">
                        <button class="btn-material btn-material-small btn-material-success">
                            <i class="bi bi-arrow-counterclockwise"></i> Restaurer
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
