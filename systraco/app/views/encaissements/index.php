<?php $title = 'Encaissements'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-money-bill-wave"></i> Encaissements</h4>
        <a href="<?php echo BASE_URL; ?>/encaissements/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="<?php echo $date; ?>"></div>
            <div class="col-md-2"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary d-block">Rechercher</button></div>
        </form>
        <table class="table table-hover">
            <thead><tr><th>Caisse</th><th>Type</th><th>Libelle</th><th>Montant</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($encaissements as $e): ?>
                <tr><td><?php echo $e['caisse']; ?></td><td><?php echo $e['type_operation']; ?></td><td><?php echo $e['libelle']; ?></td><td><?php echo number_format($e['montant_encaissement'], 0, ',', ' '); ?></td><td><?php echo $e['date_encaissement']; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';