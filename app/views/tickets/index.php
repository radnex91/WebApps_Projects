<div class="page-header">
    <div>
        <h1>Tickets</h1>
        <div class="page-header-subtitle">Liste de tous les tickets</div>
    </div>
    <div class="page-actions">
        <a href="/gestion-support/tickets/archived/list" class="btn-material btn-material-ghost">
            <i class="bi bi-archive"></i> Archivés
        </a>
        <a href="/gestion-support/tickets/create" class="btn-material btn-material-primary">
            <i class="bi bi-plus-circle"></i> Nouveau ticket
        </a>
    </div>
</div>

<div class="card-content">
    <div class="card-content-body" style="padding:0">
        <table class="table-material table">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Assigné à</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                <tr onclick="window.location='/gestion-support/tickets/<?= $t->id ?>'">
                    <td class="ticket-title"><?= htmlspecialchars($t->title ?? $t->titre) ?></td>
                    <td style="color:var(--md-text-secondary)"><?= htmlspecialchars($t->category_name ?? '-') ?></td>
                    <td>
                        <?php $p = htmlspecialchars($t->priority_name ?? ''); $pc = match(strtolower($p)){'haute','élevée','elevée'=>'badge-high','moyenne'=>'badge-medium','basse'=>'badge-low',default=>'badge-medium'}; ?>
                        <span class="badge-material <?= $pc ?>"><?= $p ?: '-' ?></span>
                    </td>
                    <td>
                        <?php $s = htmlspecialchars($t->status_name ?? ''); $sc = match(strtolower($s)){'ouvert'=>'badge-open','en cours','en_cours'=>'badge-progress','résolu','resolu'=>'badge-resolved','fermé','ferme'=>'badge-closed',default=>'badge-open'}; ?>
                        <span class="badge-material <?= $sc ?>"><?= $s ?: '-' ?></span>
                    </td>
                    <td style="color:var(--md-text-secondary)"><?= htmlspecialchars($t->assigned_name ?? '-') ?></td>
                    <td style="color:var(--md-text-secondary);font-size:12px"><?= $t->created_at ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:48px 20px;color:var(--md-text-secondary)">
                        <div style="font-size:36px;margin-bottom:8px;opacity:.5"><i class="bi bi-ticket"></i></div>
                        <div style="font-size:15px;font-weight:500;color:var(--md-text-primary);margin-bottom:4px">Aucun ticket</div>
                        <div style="font-size:13px">Créez un nouveau ticket pour commencer</div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
