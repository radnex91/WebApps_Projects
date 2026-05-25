<?php
$title = 'Rapports & Statistiques';
$activeMenu = 'reports';
$breadcrumbs = ['Rapports' => null];
?>

<div class="row">
    <!-- Employees Report -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Employés</h5>
                <p class="card-text text-muted">Statistiques du personnel, départements, genres</p>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/reports/employees" class="btn btn-primary btn-sm btn-block">
                    <i class="fas fa-chart-pie mr-1"></i> Voir le rapport
                </a>
                <a href="<?php echo APP_URL; ?>/reports/export/employees" class="btn btn-outline-primary btn-sm btn-block mt-1">
                    <i class="fas fa-file-csv mr-1"></i> Exporter CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Leaves Report -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-3x text-info mb-3"></i>
                <h5 class="card-title">Congés</h5>
                <p class="card-text text-muted">Rapport des congés par type et statut</p>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/reports/leaves" class="btn btn-info btn-sm btn-block">
                    <i class="fas fa-chart-bar mr-1"></i> Voir le rapport
                </a>
            </div>
        </div>
    </div>

    <!-- Payroll Report -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                <h5 class="card-title">Paie</h5>
                <p class="card-text text-muted">Résumé mensuel de la paie</p>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/reports/payroll" class="btn btn-success btn-sm btn-block">
                    <i class="fas fa-chart-line mr-1"></i> Voir le rapport
                </a>
            </div>
        </div>
    </div>

    <!-- Attendance Report -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h5 class="card-title">Pointage</h5>
                <p class="card-text text-muted">Statistiques de présence et retards</p>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/reports/attendance" class="btn btn-warning btn-sm btn-block">
                    <i class="fas fa-chart-area mr-1"></i> Voir le rapport
                </a>
                <a href="<?php echo APP_URL; ?>/reports/export/attendance" class="btn btn-outline-warning btn-sm btn-block mt-1">
                    <i class="fas fa-file-csv mr-1"></i> Exporter CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Recruitment Report -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-bullhorn fa-3x text-danger mb-3"></i>
                <h5 class="card-title">Recrutement</h5>
                <p class="card-text text-muted">Candidatures par statut et par offre</p>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/reports/recruitment" class="btn btn-danger btn-sm btn-block">
                    <i class="fas fa-chart-pie mr-1"></i> Voir le rapport
                </a>
            </div>
        </div>
    </div>
</div>