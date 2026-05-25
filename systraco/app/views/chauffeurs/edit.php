<?php $title = 'Modifier Chauffeur'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-edit"></i> Modifier</h4></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>/chauffeurs/update/<?php echo $chauffeur['id']; ?>">
            <div class="row">
                <div class="col-md-6"><div class="mb-3"><label class="form-label">Nom et Prenom</label><input type="text" class="form-control" name="nom_prenom_chauffeur" value="<?php echo $chauffeur['nom_prenom_chauffeur']; ?>" required></div></div>
                <div class="col-md-6"><div class="mb-3"><label class="form-label">Telephone</label><input type="text" class="form-control" name="telephone" value="<?php echo $chauffeur['telephone']; ?>"></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">N° CNI</label><input type="text" class="form-control" name="numero_cni" value="<?php echo $chauffeur['numero_cni']; ?>"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">N° Permis</label><input type="text" class="form-control" name="numero_permis" value="<?php echo $chauffeur['numero_permis']; ?>"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">Expiration</label><input type="date" class="form-control" name="date_experiration" value="<?php echo $chauffeur['date_experiration']; ?>"></div></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i>Mettre a jour</button>
            <a href="<?php echo BASE_URL; ?>/chauffeurs" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';