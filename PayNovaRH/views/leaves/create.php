<?php
$pageTitle = 'Nouvelle demande de congé';
$activeMenu = 'leaves';
$breadcrumbs = ['Congés' => '/leaves', 'Nouvelle demande' => ''];
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-navy">
                <h3 class="card-title">Demande de congé</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo APP_URL; ?>/leaves/create">
                    <?php if (Auth::isAdmin() || Auth::isManager()): ?>
                    <div class="form-group">
                        <label>Employé *</label>
                        <select name="employee_id" class="form-control select2" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Type de congé *</label>
                        <select name="leave_type_id" class="form-control" required id="leave_type">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?php echo $lt['id']; ?>" data-days="<?php echo $lt['days_allowed']; ?>" data-paid="<?php echo $lt['is_paid']; ?>">
                                <?php echo e($lt['name']); ?> (<?php echo $lt['days_allowed']; ?> jours)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date début *</label>
                                <input type="date" name="start_date" class="form-control" required id="start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date fin *</label>
                                <input type="date" name="end_date" class="form-control" required id="end_date">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre de jours</label>
                        <div class="form-control-plaintext font-weight-bold" id="total_days_display">Sélectionnez les dates</div>
                    </div>

                    <div class="form-group">
                        <label>Motif</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane mr-1"></i> Soumettre
                        </button>
                        <a href="<?php echo APP_URL; ?>/leaves" class="btn btn-secondary ml-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$('#start_date, #end_date').on('change', function() {
    var start = $('#start_date').val();
    var end = $('#end_date').val();
    if (start && end) {
        var d1 = new Date(start);
        var d2 = new Date(end);
        var days = Math.ceil((d2 - d1) / (1000*60*60*24)) + 1;
        $('#total_days_display').text(days + ' jour(s)');
    }
});
</script>