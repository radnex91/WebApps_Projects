<?php
$title = 'Modifier Agence';
ob_start();
?>
<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-edit"></i> Modifier Agence</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/agences/update/<?php echo $agence['id']; ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Code Agence *</label>
                        <input type="text" class="form-control" name="code_agence" value="<?php echo $agence['code_agence']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Nom Agence *</label>
                        <input type="text" class="form-control" name="nom_agence" value="<?php echo $agence['nom_agence']; ?>" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Ville</label>
                        <input type="text" class="form-control" name="ville_agence" value="<?php echo $agence['ville_agence']; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Cle</label>
                        <input type="text" class="form-control" name="cle_agence" value="<?php echo $agence['cle_agence']; ?>" maxlength="3">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Entreprise</label>
                        <input type="text" class="form-control" name="entreprise" value="<?php echo $agence['entreprise']; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Telephone</label>
                        <input type="text" class="form-control" name="telephone" value="<?php echo $agence['telephone']; ?>">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Responsable</label>
                <input type="text" class="form-control" name="responsable" value="<?php echo $agence['responsable']; ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Mettre a jour
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