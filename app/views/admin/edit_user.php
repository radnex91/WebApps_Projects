<div class="page-header">
    <div>
        <h1>Modifier utilisateur</h1>
        <div class="page-header-subtitle">Modifier un compte utilisateur existant</div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-pencil" style="margin-right:6px;color:var(--md-secondary)"></i> Informations</h5>
            </div>
            <div class="card-content-body">
                <form method="POST" action="/gestion-support/admin/users/edit/<?= $user->id ?>">
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Nom</label>
                        <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($user->nom) ?>" required>
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Email</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user->email) ?>" required>
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-input" minlength="8">
                        <div style="font-size:11px;color:#888;margin-top:4px">Laisser vide pour conserver l'actuel. Minimum 8 caractères.</div>
                    </div>
                    <div style="margin-bottom:20px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Rôle</label>
                        <select name="role" class="form-input">
                            <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            <option value="technicien" <?= $user->role === 'technicien' ? 'selected' : '' ?>>Technicien</option>
                            <option value="client" <?= $user->role === 'client' ? 'selected' : '' ?>>Client</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-save"></i> Enregistrer</button>
                        <a href="/gestion-support/admin/users" class="btn-material btn-material-outline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>