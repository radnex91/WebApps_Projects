<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-pencil"></i> Modifier utilisateur</h1>
</div>
<div class="row">
    <div class="col-md-6">
        <form method="POST" action="/gestion-support/admin/users/edit/<?= $user->id ?>">
            <div class="mb-3"><label class="form-label">Nom</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user->nom) ?>" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->email) ?>" required></div>
            <div class="mb-3"><label class="form-label">Nouveau mot de passe (laisser vide pour conserver)</label><input type="password" name="password" class="form-control"></div>
            <div class="mb-3">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-select">
                    <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    <option value="technicien" <?= $user->role === 'technicien' ? 'selected' : '' ?>>Technicien</option>
                    <option value="client" <?= $user->role === 'client' ? 'selected' : '' ?>>Client</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
            <a href="/gestion-support/admin/users" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
