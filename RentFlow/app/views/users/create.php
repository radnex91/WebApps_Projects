<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle"></i> Ajouter un Utilisateur</h2>
    <a href="<?= BASE_URL ?>/users" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="<?= BASE_URL ?>/users/store" method="POST">
                    <?= Csrf::field() ?>

                    <?php if (isset($_SESSION['errors'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <ul class="mb-0">
                                <?php foreach ($_SESSION['errors'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['errors']); ?>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nom complet *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($_SESSION['old_input']['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($_SESSION['old_input']['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <small class="text-muted">Minimum 8 caractères</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rôle *</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Sélectionner un rôle --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['label']) ?> (<?= htmlspecialchars($role['name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Créer l'utilisateur
                        </button>
                        <a href="<?= BASE_URL ?>/users" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Rôles disponibles</h6>
            </div>
            <div class="card-body">
                <dl class="mb-0 small">
                    <dt class="text-danger">Super Administrateur</dt>
                    <dd class="mb-2">Accès complet à toutes les fonctionnalités</dd>

                    <dt class="text-warning">Administrateur</dt>
                    <dd class="mb-2">Gestion complète sauf paramètres système</dd>

                    <dt class="text-info">Gestionnaire</dt>
                    <dd class="mb-2">Gestion des paiements, lots et bailleurs</dd>

                    <dt class="text-secondary">Agent</dt>
                    <dd class="mb-2">Consultation et saisie des paiements</dd>

                    <dt>Observateur</dt>
                    <dd class="mb-0">Lecture seule</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php unset($_SESSION['old_input']); ?>
