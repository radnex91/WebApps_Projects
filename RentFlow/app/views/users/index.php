<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people-fill"></i> Gestion des Utilisateurs</h2>
    <div>
        <a href="<?= BASE_URL ?>/users/roles" class="btn btn-info me-2"><i class="bi bi-shield-lock"></i> Rôles & Permissions</a>
        <a href="<?= BASE_URL ?>/users/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle(s)</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar-sm me-2"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                            <?= htmlspecialchars($user['name']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <?php
                        $roles = !empty($user['roles']) ? explode(',', $user['roles']) : [];
                        foreach ($roles as $role):
                            $badgeClass = match(trim($role)) {
                                'super_admin' => 'bg-danger',
                                'admin' => 'bg-warning',
                                'manager' => 'bg-info',
                                'agent' => 'bg-secondary',
                                default => 'bg-light text-dark'
                            };
                        ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(trim($role)) ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($roles)): ?>
                            <span class="text-muted">Aucun rôle</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/users/<?= $user['id'] ?>/edit" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= BASE_URL ?>/users/<?= $user['id'] ?>/delete" style="display:inline;">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer la suppression ?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.user-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.85rem;
}
</style>
