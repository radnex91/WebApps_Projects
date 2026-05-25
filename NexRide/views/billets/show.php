<?php $title = 'Billet #' . htmlspecialchars($billet->reference); ?>

<div class="page-header">
    <h2>Billet <?= htmlspecialchars($billet->reference) ?></h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/billets" class="btn btn-secondary">Retour</a>
        <?php if ($billet->statut === 'EN_ATTENTE'): ?>
            <a href="<?= BASE_URL ?>/billets/confirmer/<?= $billet->id ?>" class="btn btn-success" onclick="return confirm('Confirmer ce billet?')">Confirmer</a>
            <a href="<?= BASE_URL ?>/billets/annuler/<?= $billet->id ?>" class="btn btn-danger" onclick="return confirm('Annuler ce billet?')">Annuler</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Référence</label>
                <span><?= htmlspecialchars($billet->reference) ?></span>
            </div>
            <div class="detail-item">
                <label>Statut</label>
                <span><?= \Core\Helpers::statusBadge($billet->statut) ?></span>
            </div>
            <div class="detail-item">
                <label>Passager</label>
                <span><?= htmlspecialchars($billet->nom_passager) ?></span>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <span><?= htmlspecialchars($billet->telephone_passager) ?></span>
            </div>
            <?php if ($billet->email_passager): ?>
            <div class="detail-item">
                <label>Email</label>
                <span><?= htmlspecialchars($billet->email_passager) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Siège</label>
                <span><?= htmlspecialchars($billet->siege ?? '-') ?></span>
            </div>
            <div class="detail-item">
                <label>Montant</label>
                <span><?= $formatMoney($billet->montant) ?></span>
            </div>
            <div class="detail-item">
                <label>Taxe (<?= TVA * 100 ?>%)</label>
                <span><?= $formatMoney($billet->taxe) ?></span>
            </div>
            <div class="detail-item">
                <label>Remise</label>
                <span><?= $formatMoney($billet->remise) ?></span>
            </div>
            <div class="detail-item">
                <label>Total</label>
                <span><strong><?= $formatMoney($billet->montant_total) ?></strong></span>
            </div>
            <div class="detail-item">
                <label>Paiement</label>
                <span><?= \Core\Helpers::statusBadge($billet->mode_paiement) ?></span>
            </div>
            <div class="detail-item">
                <label>Code validation</label>
                <span><strong><?= htmlspecialchars($billet->code_validation) ?></strong></span>
            </div>
            <div class="detail-item">
                <label>Date d'émission</label>
                <span><?= \Core\Helpers::formatDateTime($billet->date_emission) ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($voyage): ?>
<div class="card" style="margin-top: 1rem;">
    <div class="card-header"><h3>Voyage associé</h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Référence</label>
                <span><a href="<?= BASE_URL ?>/voyages/<?= $voyage->id ?>"><?= htmlspecialchars($voyage->reference) ?></a></span>
            </div>
            <div class="detail-item">
                <label>Trajet</label>
                <span><?= htmlspecialchars($voyage->depart) ?> &rarr; <?= htmlspecialchars($voyage->destination) ?></span>
            </div>
            <div class="detail-item">
                <label>Départ</label>
                <span><?= \Core\Helpers::formatDateTime($voyage->date_depart) ?></span>
            </div>
            <div class="detail-item">
                <label>Statut</label>
                <span><?= \Core\Helpers::statusBadge($voyage->statut) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>