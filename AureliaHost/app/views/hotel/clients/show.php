<?php $title = 'Client ' . e($client['prenom'] . ' ' . $client['nom']); ?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3"><div class="card-header"><h5 class="mb-0"><i class="bi bi-person"></i> <?= e($client['prenom'] . ' ' . $client['nom']) ?></h5></div>
        <div class="card-body">
            <table class="table">
                <tr><th>Email</th><td><?= e($client['email']) ?></td></tr>
                <tr><th>Téléphone</th><td><?= e($client['telephone']) ?></td></tr>
                <tr><th>Adresse</th><td><?= e($client['adresse']) ?></td></tr>
                <tr><th>Ville</th><td><?= e($client['ville']) ?></td></tr>
                <tr><th>Pays</th><td><?= e($client['pays']) ?></td></tr>
                <tr><th>Document</th><td><?= strtoupper($client['document_type']) ?> — <?= e($client['document_numero']) ?></td></tr>
                <tr><th>Né(e) le</th><td><?= $client['date_naissance'] ? formatDate($client['date_naissance']) : '—' ?></td></tr>
            </table>
            <a href="<?= url('hotel/clients') ?>" class="btn btn-secondary">Retour</a>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-check"></i> Historique des réservations</h6></div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Check-in</th><th>Check-out</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= formatDate($r['date_checkin']) ?></td>
                        <td><?= formatDate($r['date_checkout']) ?></td>
                        <td><?= formatMoney($r['montant_total']) ?></td>
                        <td><span class="badge bg-<?= $r['statut']==='confirmee'?'primary':($r['statut']==='en_cours'?'success':'secondary') ?>"><?= $r['statut'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
