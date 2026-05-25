<?php
$pageTitle = 'Modifier le pointage';
$activeMenu = 'attendance';
$breadcrumbs = ['Pointage' => '/attendance', 'Modifier' => ''];
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-navy"><h3 class="card-title">Modifier le pointage</h3></div>
            <div class="card-body">
                <form method="POST" action="<?php echo APP_URL; ?>/attendance/<?php echo $record['id']; ?>/edit">
                    <div class="form-group">
                        <label>Employé</label>
                        <input type="text" class="form-control" value="<?php echo e($record['first_name'] . ' ' . $record['last_name']); ?>" disabled>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo e($record['date']); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Heure d'arrivée</label>
                                <input type="time" name="time_in" class="form-control" value="<?php echo e($record['time_in']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Heure de départ</label>
                                <input type="time" name="time_out" class="form-control" value="<?php echo e($record['time_out']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <?php foreach (['présent', 'absent', 'retard', 'congé', 'mi-journée'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $record['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Retard (min)</label>
                                <input type="number" name="late_minutes" class="form-control" value="<?php echo $record['late_minutes']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Départ anticipé (min)</label>
                                <input type="number" name="early_leave_minutes" class="form-control" value="<?php echo $record['early_leave_minutes']; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Heures supp (min)</label>
                                <input type="number" name="overtime_minutes" class="form-control" value="<?php echo $record['overtime_minutes']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?php echo e($record['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Enregistrer</button>
                    <a href="<?php echo APP_URL; ?>/attendance" class="btn btn-secondary ml-2">Annuler</a>
                </form>
            </div>
        </div>
    </div>
</div>