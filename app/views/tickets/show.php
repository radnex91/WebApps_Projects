<div class="page-header">
    <div>
        <h1>Ticket #<?= $ticket->id ?></h1>
        <div class="page-header-subtitle"><?= htmlspecialchars($ticket->title ?? $ticket->titre) ?></div>
    </div>
    <div class="page-actions">
        <a href="/gestion-support/tickets/<?= $ticket->id ?>/edit" class="btn-material btn-material-ghost">
            <i class="bi bi-pencil"></i> Modifier
        </a>
        <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/archive" style="display:inline">
            <button class="btn-material btn-material-ghost"><i class="bi bi-archive"></i> Archiver</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card-content mb-3">
            <div class="card-content-header">
                <h5><i class="bi bi-info-circle" style="margin-right:6px;color:var(--md-primary)"></i> Description</h5>
            </div>
            <div class="card-content-body">
                <p style="line-height:1.7;margin:0"><?= nl2br(htmlspecialchars($ticket->description ?? $ticket->description)) ?></p>
            </div>
        </div>

        <div class="card-content mb-3">
            <div class="card-content-header">
                <h5><i class="bi bi-chat-dots" style="margin-right:6px;color:var(--md-secondary)"></i> Commentaires</h5>
            </div>
            <div class="card-content-body">
                <?php if (!empty($comments)): ?>
                <div style="margin-bottom:20px">
                    <?php foreach ($comments as $c): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <div class="comment-avatar"><?= strtoupper(substr(htmlspecialchars($c->user_name ?? $c->user_name ?? '?'), 0, 1)) ?></div>
                            <div>
                                <div class="comment-author"><?= htmlspecialchars($c->user_name ?? $c->user_name) ?></div>
                                <div class="comment-date"><?= $c->created_at ?></div>
                            </div>
                        </div>
                        <div class="comment-body"><?= nl2br(htmlspecialchars($c->content)) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:16px 0;color:var(--md-text-secondary)">Aucun commentaire</div>
                <?php endif; ?>
                <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/comment">
                    <div class="field-group">
                        <textarea name="content" class="field-input" rows="2" placeholder="Ajouter un commentaire..." required></textarea>
                        <label class="field-label">Commentaire</label>
                    </div>
                    <button type="submit" class="btn-material btn-material-primary" style="margin-top:8px">
                        <i class="bi bi-send"></i> Commenter
                    </button>
                </form>
            </div>
        </div>

        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-clock-history" style="margin-right:6px;color:var(--md-text-secondary)"></i> Historique</h5>
            </div>
            <div class="card-content-body">
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-author"><?= htmlspecialchars($h->user_name ?? 'Système') ?></span>
                                <span class="timeline-date"><?= $h->created_at ?></span>
                            </div>
                            <div class="timeline-action"><?= htmlspecialchars($h->action) ?> - <?= htmlspecialchars($h->details) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                    <div style="text-align:center;padding:16px 0;color:var(--md-text-secondary)">Aucun historique</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-content mb-3">
            <div class="card-content-header">
                <h5><i class="bi bi-info-square" style="margin-right:6px;color:var(--md-primary)"></i> Informations</h5>
            </div>
            <div class="card-content-body">
                <table style="width:100%;font-size:13px">
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary);width:90px">Catégorie</td><td style="padding:4px 0"><?= htmlspecialchars($ticket->category_name ?? '-') ?></td></tr>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">Priorité</td>
                        <td style="padding:4px 0">
                            <?php $p = htmlspecialchars($ticket->priority_name ?? ''); $pc = match(strtolower($p)){'haute','élevée','elevée'=>'badge-high','moyenne'=>'badge-medium','basse'=>'badge-low',default=>'badge-medium'}; ?>
                            <span class="badge-material <?= $pc ?>"><?= $p ?: '-' ?></span>
                        </td>
                    </tr>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">Statut</td>
                        <td style="padding:4px 0">
                            <?php $s = htmlspecialchars($ticket->status_name ?? ''); $sc = match(strtolower($s)){'ouvert'=>'badge-open','en cours','en_cours'=>'badge-progress','résolu','resolu'=>'badge-resolved','fermé','ferme'=>'badge-closed',default=>'badge-open'}; ?>
                            <span class="badge-material <?= $sc ?>"><?= $s ?: '-' ?></span>
                        </td>
                    </tr>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">Créé par</td><td style="padding:4px 0"><?= htmlspecialchars($ticket->created_by_name ?? '-') ?></td></tr>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">Assigné à</td><td style="padding:4px 0"><?= htmlspecialchars($ticket->assigned_name ?? '-') ?></td></tr>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">Date</td><td style="padding:4px 0"><?= $ticket->created_at ?></td></tr>
                    <?php if (!empty($ticket->sla_limit)): ?>
                    <tr><td style="padding:4px 0;color:var(--md-text-secondary)">SLA</td><td style="padding:4px 0"><?= htmlspecialchars($ticket->sla_limit) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card-content mb-3">
            <div class="card-content-header">
                <h5><i class="bi bi-arrow-repeat" style="margin-right:6px;color:var(--md-secondary)"></i> Changer le statut</h5>
            </div>
            <div class="card-content-body">
                <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/status">
                    <div class="field-group">
                        <select name="status_id" class="field-input">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s->id ?>" <?= $s->id == $ticket->status_id ? 'selected' : '' ?>><?= htmlspecialchars($s->nom ?? $s->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="field-label">Statut</label>
                    </div>
                    <button type="submit" class="btn-material btn-material-primary" style="margin-top:12px;width:100%">Mettre à jour</button>
                </form>
            </div>
        </div>

        <?php if (\App\Helpers\Auth::role() === 'admin'): ?>
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-person-plus" style="margin-right:6px;color:var(--md-warning)"></i> Assigner</h5>
            </div>
            <div class="card-content-body">
                <form method="POST" action="/gestion-support/tickets/<?= $ticket->id ?>/assign">
                    <div class="field-group">
                        <select name="assigned_to" class="field-input">
                            <option value="">Non assigné</option>
                            <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech->id ?>" <?= $ticket->assigned_to == $tech->id ? 'selected' : '' ?>><?= htmlspecialchars($tech->nom ?? $tech->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="field-label">Technicien</label>
                    </div>
                    <button type="submit" class="btn-material btn-material-primary" style="margin-top:12px;width:100%">Assigner</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
