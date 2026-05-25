<?php $title = 'Gestion des utilisateurs'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-shield-lock"></i> Utilisateurs</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg"></i> Nouvel utilisateur</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="badge bg-info"><?= e($u['role']) ?></span></td>
                        <td><?= $u['statut'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>' ?></td>
                        <td><?= formatDate($u['created_at'], 'd/m/Y') ?></td>
                        <td>
                            <a href="<?= url('auth/toggle-user/' . $u['id']) ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Changer le statut ?')">
                                <?= $u['statut'] ? 'Désactiver' : 'Activer' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form id="addUserForm" novalidate>
                <div class="modal-body">
                    <div id="addUserErrors" class="alert alert-danger d-none"></div>
                    <div class="row mb-2">
                        <div class="col-md-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Téléphone</label><input type="text" name="telephone" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Mot de passe *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                    <div class="mb-2">
                        <label class="form-label">Rôle *</label>
                        <select name="role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="receptionist">Réceptionniste</option>
                            <option value="hr">RH</option>
                            <option value="accountant">Comptable</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="addUserSubmitBtn">
                        <i class="bi bi-person-plus"></i> Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('addUserForm');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorsDiv = document.getElementById('addUserErrors');
        errorsDiv.classList.add('d-none');

        var submitBtn = document.getElementById('addUserSubmitBtn');
        var origHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Création…';

        try {
            var res = await fetch('<?= url("auth/create-user") ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form)
            });
            var data = await res.json();

            if (data.success) {
                var modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                if (modal) modal.hide();
                window.location.reload();
            } else if (data.errors) {
                errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' +
                    Object.values(data.errors).join('<br>');
                errorsDiv.classList.remove('d-none');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
            }
        } catch (err) {
            errorsDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Erreur réseau. Veuillez réessayer.';
            errorsDiv.classList.remove('d-none');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origHtml;
            }
        }
    });
})();
</script>
