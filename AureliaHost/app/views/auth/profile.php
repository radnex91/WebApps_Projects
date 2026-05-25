<?php $title = 'Mon Profil'; ?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-circle"></i> Profil utilisateur</h5></div>
            <div class="card-body">
                <table class="table">
                    <tr><th>Nom</th><td><?= e($user['nom']) ?></td></tr>
                    <tr><th>Prénom</th><td><?= e($user['prenom']) ?></td></tr>
                    <tr><th>Email</th><td><?= e($user['email']) ?></td></tr>
                    <tr><th>Téléphone</th><td><?= e($user['telephone'] ?? '—') ?></td></tr>
                    <tr><th>Rôle</th><td><span class="badge bg-info"><?= e($user['role']) ?></span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
