<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-gear"></i> Paramètres</h1>
</div>
<div class="row">
    <div class="col-md-6">
        <form method="POST" action="/gestion-support/admin/settings">
            <div class="mb-3"><label class="form-label">Nom de l'application</label><input type="text" name="app_name" class="form-control" value="Gestion Support"></div>
            <div class="mb-3"><label class="form-label">Email de notification</label><input type="email" name="notification_email" class="form-control" value="admin@example.com"></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
        </form>
    </div>
</div>
