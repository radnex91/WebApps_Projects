<?php $title = 'Client - ' . htmlspecialchars($client->getNomComplet()); ?>
<div class="page-header">
    <h2><?= htmlspecialchars($client->getNomComplet()) ?></h2>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/clients" class="btn btn-secondary">Retour</a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Nom complet</label><span><?= htmlspecialchars($client->getNomComplet()) ?></span></div>
            <div class="detail-item"><label>Téléphone</label><span><?= htmlspecialchars($client->telephone) ?></span></div>
            <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($client->email ?? '-') ?></span></div>
            <div class="detail-item"><label>Adresse</label><span><?= htmlspecialchars($client->adresse ?? '-') ?></span></div>
            <div class="detail-item"><label>Pièce</label><span><?= htmlspecialchars($client->piece_identite ?? '-') ?> <?= htmlspecialchars($client->num_piece ?? '') ?></span></div>
        </div>
    </div>
</div>