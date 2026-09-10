<div class="page-header">
    <div>
        <h1>Paramètres</h1>
        <div class="page-header-subtitle">Configuration générale de l'application</div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-gear" style="margin-right:6px;color:var(--md-secondary)"></i> Paramètres</h5>
            </div>
            <div class="card-content-body">
                <form method="POST" action="/gestion-support/admin/settings">
                    <div style="margin-bottom:16px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Nom de l'application</label>
                        <input type="text" name="app_name" class="form-input" value="Gestion Support">
                    </div>
                    <div style="margin-bottom:20px">
                        <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#444">Email de notification</label>
                        <input type="email" name="notification_email" class="form-input" value="admin@example.com">
                    </div>
                    <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-save"></i> Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>