<?php $title = 'Depenses'; ob_start(); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-money-bill"></i> Depenses</h4>
        <a href="<?php echo BASE_URL; ?>/depenses/add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="<?php echo $date; ?>"></div>
            <div class="col-md-2"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary d-block">Rechercher</button></div>
        </form>
        <table class="table table-hover">
            <thead><tr><th>Caisse</th><th>Type</th><th>Libelle</th><th>Montant</th><th>Date</th><th>Par</th></tr></thead>
            <tbody>
                <?php foreach ($depenses as $d): ?>
                <tr><td><?php echo $d['caisse']; ?></td><td><?php echo $d['type_operation']; ?></td><td><?php echo $d['libelle']; ?></td><td><?php echo number_format($d['montant_depenses'], 0, ',', ' '); ?></td><td><?php echo $d['date_depense']; ?></td><td><?php echo $d['saisie_par']; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';