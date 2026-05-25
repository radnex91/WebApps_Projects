<?php
$title = 'Tableau de bord';
ob_start();
?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <i class="fas fa-ticket-alt"></i>
            <div class="number"><?php echo $stats['tickets_aujourdhui']; ?></div>
            <div>Billets aujourd'hui</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="fas fa-money-bill-wave"></i>
            <div class="number"><?php echo number_format($stats['recette_aujourdhui'], 0, ',', ' '); ?> CFA</div>
            <div>Recette aujourd'hui</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="number"><?php echo $stats['depart_aujourdhui']; ?></div>
            <div>Departs aujourd'hui</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="fas fa-building"></i>
            <div class="number"><?php echo $stats['agences']; ?></div>
            <div>Agences</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Activite recente</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Aucune activite recente</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bus"></i> Prochains departs</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Aucun depart programme</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layout.php';