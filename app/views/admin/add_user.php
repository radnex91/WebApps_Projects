<div class="page-header">
    <div>
        <h1>Ajouter un utilisateur</h1>
        <div class="page-header-subtitle">Créer un nouveau compte utilisateur</div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-person-plus" style="margin-right:6px;color:var(--md-secondary)"></i> Informations</h5>
            </div>
            <div class="card-content-body">
                <form method="POST" action="/gestion-support/admin/users/add">
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Nom</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Email</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Mot de passe</label>
                        <input type="password" name="password" class="form-input" required minlength="8">
                        <div style="font-size:11px;color:#888;margin-top:4px">Minimum 8 caractères</div>
                    </div>
                    <div style="margin-bottom:20px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Rôle</label>
                        <select name="role" class="form-input">
                            <option value="admin">Administrateur</option>
                            <option value="technicien">Technicien</option>
                            <option value="client" selected>Client</option>
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