<?php $title = 'Bordereau #' . htmlspecialchars($bordereau->reference); ?>

<div class="page-header">
    <h2>Bordereau <?= htmlspecialchars($bordereau->reference) ?></h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/bordereaux" class="btn btn-secondary">Retour</a>
        <?php if ($bordereau->statut === 'BROUILLON'): ?>
            <a href="<?= BASE_URL ?>/bordereaux/valider/<?= $bordereau->id ?>" class="btn btn-success" onclick="return confirm('Valider ce bordereau?')">Valider</a>
        <?php endif; ?>
        <?php if ($bordereau->statut === 'VALIDE'): ?>
            <a href="<?= BASE_URL ?>/bordereaux/cloturer/<?= $bordereau->id ?>" class="btn btn-primary" onclick="return confirm('Clôturer ce bordereau?')">Clôturer</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Référence</label><span><?= htmlspecialchars($bordereau->reference) ?></span></div>
            <div class="detail-item"><label>Date</label><span><?= \Core\Helpers::formatDate($bordereau->date_bordereau) ?></span></div>
            <div class="detail-item"><label>Type</label><span><?= \Core\Helpers::statusBadge($bordereau->type) ?></span></div>
            <div class="detail-item"><label>Statut</label><span><?= \Core\Helpers::statusBadge($bordereau->statut) ?></span></div>
            <div class="detail-item"><label>Montant total</label><span><strong><?= $formatMoney($bordereau->montant_total) ?></strong></span></div>
            <?php if ($bordereau->description): ?>
            <div class="detail-item"><label>Description</label><span><?= htmlspecialchars($bordereau->description) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header"><h3>Lignes (<?= count($lignes) ?>)</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Libellé</th><th>Type</th><th>Catégorie</th><th>Montant</th></tr></thead>
                <tbody>
                    <?php $i = 1; $total = 0; ?>
                    <?php foreach ($lignes as $l): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($l->libelle) ?></td>
                        <td><?= \Core\Helpers::statusBadge($l->type) ?></td>
                        <td><?= htmlspecialchars($l->categorie ?? '-') ?></td>
                        <td><?= $formatMoney($l->montant) ?></td>
                    </tr>
                    <?php $total += $l->montant; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="4">Total</th><th><?= $formatMoney($total) ?></th></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>