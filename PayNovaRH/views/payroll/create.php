<?php
$pageTitle = 'Nouvelle période de paie';
$activeMenu = 'payroll';
$breadcrumbs = ['Paie' => '/payroll', 'Nouvelle période' => ''];
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-navy"><h3 class="card-title">Créer une période de paie</h3></div>
            <div class="card-body">
                <form method="POST" action="<?php echo APP_URL; ?>/payroll/create">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mois *</label>
                                <select name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>><?php echo getMonthName($m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Année *</label>
                                <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date de paiement</label>
                        <input type="date" name="payment_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i> Des entrées de paie seront automatiquement créées pour tous les employés actifs avec un contrat valide.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Créer</button>
                    <a href="<?php echo APP_URL; ?>/payroll" class="btn btn-secondary ml-2">Annuler</a>
                </form>
            </div>
        </div>
    </div>
</div>