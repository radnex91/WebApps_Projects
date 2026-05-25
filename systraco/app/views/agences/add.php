<?php
$title = 'Nouvelle Agence';
ob_start();
?>
<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-plus"></i> Nouvelle Agence</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/agences/store">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Code Agence *</label>
                        <input type="text" class="form-control" name="code_agence" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Nom Agence *</label>
                        <input type="text" class="form-control" name="nom_agence" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Ville</label>
                        <input type="text" class="form-control" name="ville_agence">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Cle (3 caracteres)</label>
                        <input type="text" class="form-control" name="cle_agence" maxlength="3">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Entreprise</label>
                        <input type="text" class="form-control" name="entreprise">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Telephone</label>
                        <input type="text" class="form-control" name="telephone">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Responsable</label>
                <input type="text" class="form-control" name="responsable">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <a href="<?php echo BASE_URL; ?>/agences" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layout.php';