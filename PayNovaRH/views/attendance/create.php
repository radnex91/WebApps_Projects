<?php
$pageTitle = 'Enregistrer un pointage';
$activeMenu = 'attendance';
$breadcrumbs = ['Pointage' => '/attendance', 'Nouveau' => ''];
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-navy"><h3 class="card-title">Pointage</h3></div>
            <div class="card-body">
                <form method="POST" action="<?php echo APP_URL; ?>/attendance/create">
                    <div class="form-group">
                        <label>Employé *</label>
                        <select name="employee_id" class="form-control select2" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date *</label>
                                <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Heure d'arrivée</label>
                                <input type="time" name="time_in" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Heure de départ</label>
                                <input type="time" name="time_out" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <option value="présent">Présent</option>
                            <option value="absent">Absent</option>
                            <option value="retard">Retard</option>
                            <option value="congé">Congé</option>
                            <option value="mi-journée">Mi-journée</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Enregistrer</button>
                    <a href="<?php echo APP_URL; ?>/attendance" class="btn btn-secondary ml-2">Annuler</a>
                </form>
            </div>
        </div>
    </div>
</div>