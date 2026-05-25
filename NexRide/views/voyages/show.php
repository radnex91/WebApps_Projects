<?php $title = 'Voyage #' . htmlspecialchars($voyage->reference); ?>

<div class="page-header">
    <h2>Voyage <?= htmlspecialchars($voyage->reference) ?></h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/voyages" class="btn btn-secondary">Retour</a>
        <?php if ($voyage->statut === 'PROGRAMME'): ?>
            <form method="POST" action="<?= BASE_URL ?>/voyages/changer-statut/<?= $voyage->id ?>" style="display:inline">
                <input type="hidden" name="statut" value="EN_COURS">
                <button type="submit" class="btn btn-success" onclick="return confirm('Démarrer ce voyage?')">Démarrer</button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/voyages/changer-statut/<?= $voyage->id ?>" style="display:inline">
                <input type="hidden" name="statut" value="ANNULE">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Annuler ce voyage?')">Annuler</button>
            </form>
        <?php elseif ($voyage->statut === 'EN_COURS'): ?>
            <form method="POST" action="<?= BASE_URL ?>/voyages/changer-statut/<?= $voyage->id ?>" style="display:inline">
                <input type="hidden" name="statut" value="TERMINE">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Terminer ce voyage?')">Terminer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Référence</label><span><?= htmlspecialchars($voyage->reference) ?></span></div>
            <div class="detail-item"><label>Titre</label><span><?= htmlspecialchars($voyage->titre) ?></span></div>
            <div class="detail-item"><label>Type</label><span><?= htmlspecialchars(unserialize(TYPE_VOYAGE)[$voyage->type_voyage] ?? $voyage->type_voyage) ?></span></div>
            <div class="detail-item"><label>Statut</label><span><?= \Core\Helpers::statusBadge($voyage->statut) ?></span></div>
            <div class="detail-item"><label>Départ</label><span><?= htmlspecialchars($voyage->depart) ?></span></div>
            <div class="detail-item"><label>Destination</label><span><?= htmlspecialchars($voyage->destination) ?></span></div>
            <div class="detail-item"><label>Date départ</label><span><?= \Core\Helpers::formatDateTime($voyage->date_depart) ?></span></div>
            <div class="detail-item"><label>Date arrivée</label><span><?= $voyage->date_arrivee ? \Core\Helpers::formatDateTime($voyage->date_arrivee) : '-' ?></span></div>
            <div class="detail-item"><label>Véhicule</label><span><?= $vehicule ? htmlspecialchars($vehicule->immatriculation . ' - ' . $vehicule->marque) : 'Non assigné' ?></span></div>
            <div class="detail-item"><label>Chauffeur</label><span><?= $chauffeur ? htmlspecialchars($chauffeur->getNomComplet()) : 'Non assigné' ?></span></div>
            <div class="detail-item"><label>Tarif base</label><span><?= $formatMoney($voyage->tarif_base) ?></span></div>
            <div class="detail-item"><label>Places</label><span><?= $voyage->places_disponibles ?>/<?= $voyage->nombre_places ?> disponibles</span></div>
            <div class="detail-item"><label>Recette</label><span><strong><?= $formatMoney($voyage->getRecette()) ?></strong></span></div>
            <div class="detail-item"><label>Passagers</label><span><?= $voyage->getNombrePassagers() ?></span></div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3>Billets associés (<?= count($billets) ?>)</h3>
        <a href="<?= BASE_URL ?>/billets/create" class="btn btn-sm btn-primary">+ Ajouter</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Réf.</th><th>Passager</th><th>Siège</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($billets as $b): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/billets/<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></a></td>
                        <td><?= htmlspecialchars($b->nom_passager) ?></td>
                        <td><?= htmlspecialchars($b->siege ?? '-') ?></td>
                        <td><?= $formatMoney($b->montant_total) ?></td>
                        <td><?= \Core\Helpers::statusBadge($b->statut) ?></td>
                        <td><a href="<?= BASE_URL ?>/billets/<?= $b->id ?>" class="btn btn-sm btn-info">Voir</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($billets)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Aucun billet pour ce voyage</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>