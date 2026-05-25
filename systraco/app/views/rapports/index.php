<?php $title = 'Rapports'; ob_start(); ?>
<div class="card">
    <div class="card-header"><h4><i class="fas fa-chart-bar"></i> Rapports</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>/rapports/journalier" class="btn btn-outline-primary w-100 p-4">
                    <i class="fas fa-calendar-day fa-2x"></i><br>Journalier
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>/rapports/exploitation" class="btn btn-outline-primary w-100 p-4">
                    <i class="fas fa-chart-line fa-2x"></i><br>Exploitation
                </a>
            </div>
            <div class="col-md-4">
                <a href="#" class="btn btn-outline-primary w-100 p-4">
                    <i class="fas fa-money-bill-wave fa-2x"></i><br>Recettes
                </a>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require_once ROOT_PATH . '/app/views/layout.php';