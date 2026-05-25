<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-person-plus"></i> Ajouter un utilisateur</h1>
</div>
<div class="row">
    <div class="col-md-6">
        <form method="POST" action="/gestion-support/admin/users/add">
            <div class="mb-3"><label class="form-label">Nom</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Mot de passe</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-select">
                    <option value="admin">Administrateur</option>
                    <option value="technicien">Technicien</option>
                    <option value="client" selected>Client</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
            <a href="/gestion-support/admin/users" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
