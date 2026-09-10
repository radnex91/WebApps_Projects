<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <div class="page-header-subtitle">Vue d'ensemble de vos tickets</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi amber">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">À traiter</div>
                    <div class="card-kpi-value"><?= $openCount ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-inbox"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi green">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Résolus</div>
                    <div class="card-kpi-value"><?= $resolvedCount ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-content">
    <div class="card-content-header">
        <h5><i class="bi bi-list-task" style="margin-right:6px;color:var(--md-secondary)"></i> Mes tickets assignés</h5>
    </div>
    <div class="card-content-body" style="padding:0">
        <table class="table-material table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignedTickets as $t): ?>
                <tr onclick="window.location='/gestion-support/tickets/<?= $t->id ?>'">
                    <td><span class="ticket-id">#<?= $t->id ?></span></td>
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
                    <td style="color:var(--md-text-secondary);font-size:12px"><?= $t->created_at ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($assignedTickets)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--md-text-secondary)">Aucun ticket assigné</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
