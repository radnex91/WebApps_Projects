<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-person"></i> Mon profil</h1>
    </div>
</div>

<div class="row" style="margin-top:20px">
    <div class="col-md-6">
        <div class="card-material">
            <div class="card-material-header">
                <i class="bi bi-pencil"></i> Informations personnelles
            </div>
            <div class="card-material-body">
                <form method="POST" action="<?= BASE_URL ?>/profile" class="form-material">
                    <div class="form-group-material">
                        <label class="form-label-material" for="name">Nom</label>
                        <input type="text" name="name" id="name" class="form-input-material" value="<?= htmlspecialchars($user->nom) ?>" required>
                    </div>
                    <div class="form-group-material">
                        <label class="form-label-material" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-input-material" value="<?= htmlspecialchars($user->email) ?>" required>
                    </div>
                    <div class="form-group-material">
                        <label class="form-label-material" for="password">Nouveau mot de passe</label>
                        <input type="password" name="password" id="password" class="form-input-material" placeholder="Laisser vide pour conserver">
                    </div>
                    <button type="submit" class="btn-material btn-material-primary" style="justify-content:center;width:100%">
                        <i class="bi bi-save"></i> Mettre à jour
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
