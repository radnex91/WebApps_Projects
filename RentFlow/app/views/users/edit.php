<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil"></i> Modifier l'Utilisateur</h2>
    <a href="<?= BASE_URL ?>/users" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="<?= BASE_URL ?>/users/<?= $user['id'] ?>/update" method="POST">
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
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="8">
                        <small class="text-muted">Laisser vide pour conserver le mot de passe actuel (minimum 8 caractères)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rôle *</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Sélectionner un rôle --</option>
                            <?php
                            $userRoleIds = array_column($userRoles, 'id');
                            foreach ($roles as $role):
                                $selected = in_array($role['id'], $userRoleIds) ? 'selected' : '';
                            ?>
                                <option value="<?= $role['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($role['label']) ?> (<?= htmlspecialchars($role['name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Enregistrer
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
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informations</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>ID:</strong> <?= $user['id'] ?>
                </div>
                <div class="mb-2">
                    <strong>Créé le:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                </div>
                <div class="mb-2">
                    <strong>Dernière modif:</strong> <?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>
