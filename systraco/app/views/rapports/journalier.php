<?php $title = 'Rapport Journalier'; ob_start(); ?>
<div class="card">
    <div class="card-header">
        <h4><i class="fas fa-calendar-day"></i> Rapport du <?php echo $date; ?></h4>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="<?php echo $date; ?>"></div>
            <div class="col-md-2"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary d-block">Voir</button></div>
        </form>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-ticket-alt"></i>
                    <div class="number"><?php echo $billets['total']; ?></div>
                    <div>Billets Vendus</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="number"><?php echo number_format($billets['recette'], 0, ',', ' '); ?></div>
                    <div>Recette Billets</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-cash-register"></i>
                    <div class="number"><?php echo number_format($recettes['total'], 0, ',', ' '); ?></div>
                    <div>Encaissements</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';