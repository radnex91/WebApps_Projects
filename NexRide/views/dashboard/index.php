<div class="dashboard-grid">
    <div class="stat-card stat-primary">
        <div class="stat-value"><?= \Core\Helpers::formatMoney($recetteJour) ?></div>
        <div class="stat-label">Recette du jour</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-value"><?= $billetStats->confirmes ?></div>
        <div class="stat-label">Billets confirmés</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-value"><?= $voyageStats->en_cours + $voyageStats->programmes ?></div>
        <div class="stat-label">Voyages actifs</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-value"><?= \Core\Helpers::formatMoney($soldeGlobal) ?></div>
        <div class="stat-label">Solde caisse</div>
    </div>
</div>

<div class="dashboard-grid-2">
    <div class="card">
        <div class="card-header">
            <h3>Voyages du jour</h3>
            <a href="<?= BASE_URL ?>/voyages/create" class="btn btn-sm btn-primary">Nouveau</a>
        </div>
        <div class="card-body">
            <?php if (empty($voyagesDuJour)): ?>
                <p class="text-muted">Aucun voyage programmé aujourd'hui</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Réf.</th><th>Trajet</th><th>Départ</th><th>Véhicule</th><th>Chauffeur</th><th>Statut</th></tr></thead>
                        <tbody>
                            <?php foreach ($voyagesDuJour as $v): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>/voyages/<?= $v->id ?>"><?= htmlspecialchars($v->reference) ?></a></td>
                                <td><?= htmlspecialchars($v->depart) ?> &rarr; <?= htmlspecialchars($v->destination) ?></td>
                                <td><?= date('H:i', strtotime($v->date_depart)) ?></td>
                                <td><?= htmlspecialchars($v->immatriculation ?? '-') ?></td>
                                <td><?= htmlspecialchars($v->chauffeur_nom ?? '-') ?></td>
                                <td><?= \Core\Helpers::statusBadge($v->statut) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Billets récents</h3>
            <a href="<?= BASE_URL ?>/billets/create" class="btn btn-sm btn-primary">Nouveau</a>
        </div>
        <div class="card-body">
            <?php if (empty($billetsRecents)): ?>
                <p class="text-muted">Aucun billet récent</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Réf.</th><th>Passager</th><th>Trajet</th><th>Montant</th><th>Statut</th></tr></thead>
                        <tbody>
                            <?php foreach ($billetsRecents as $b): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>/billets/<?= $b->id ?>"><?= htmlspecialchars($b->reference) ?></a></td>
                                <td><?= htmlspecialchars($b->nom_passager) ?></td>
                                <td><?= htmlspecialchars($b->depart ?? '') ?> &rarr; <?= htmlspecialchars($b->destination ?? '') ?></td>
                                <td><?= \Core\Helpers::formatMoney($b->montant_total) ?></td>
                                <td><?= \Core\Helpers::statusBadge($b->statut) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3>Évolution des recettes (6 mois)</h3>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <div class="chart-bars">
                <?php $maxRev = max(array_column($monthlyRevenue, 'recette') ?: [1]); ?>
                <?php foreach ($monthlyRevenue as $m): ?>
                    <div class="chart-item">
                        <div class="chart-bar" style="height: <?= ($m->recette / $maxRev) * 200 ?>px;">
                            <span class="chart-value"><?= \Core\Helpers::formatMoney($m->recette) ?></span>
                        </div>
                        <div class="chart-label"><?= htmlspecialchars($m->mois) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>