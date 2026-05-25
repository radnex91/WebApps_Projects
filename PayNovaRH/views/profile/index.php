<?php
$title = 'Mon profil';
$activeMenu = 'profile';
$breadcrumbs = ['Mon profil' => false];

$currentUser = Auth::user();
$employee = $currentUser['employee'] ?? null;
?>

<div class="row">
    <!-- User Info Card -->
    <div class="col-lg-4">
        <div class="card card-navy card-outline">
            <div class="card-body box-profile">
                <!-- Avatar -->
                <div class="text-center">
                    <?php if (!empty($employee['photo'])): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($employee['photo']); ?>"
                             class="profile-user-img img-fluid img-circle elevation-2"
                             alt="Photo de profil"
                             style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="profile-user-img img-fluid img-circle elevation-2 bg-navy d-flex align-items-center justify-content-center mx-auto"
                             style="width: 120px; height: 120px;">
                            <i class="fas fa-user fa-3x text-white"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="profile-username text-center mt-3">
                    <?php echo e(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? $currentUser['username'] ?? '')); ?>
                </h3>

                <p class="text-muted text-center">
                    <?php echo e($currentUser['role_name'] ?? 'Utilisateur'); ?>
                </p>

                <!-- Contact Info -->
                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <i class="fas fa-envelope mr-2 text-navy"></i>
                        <strong>E-mail</strong>
                        <span class="float-right text-muted">
                            <?php echo e($currentUser['email'] ?? $employee['email'] ?? '--'); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-phone mr-2 text-navy"></i>
                        <strong>Téléphone</strong>
                        <span class="float-right text-muted">
                            <?php echo e($employee['phone'] ?? '--'); ?>
                        </span>
                    </li>
                    <?php if (!empty($employee['department_name'])): ?>
                    <li class="list-group-item">
                        <i class="fas fa-building mr-2 text-navy"></i>
                        <strong>Département</strong>
                        <span class="float-right text-muted">
                            <?php echo e($employee['department_name']); ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($employee['position_name'])): ?>
                    <li class="list-group-item">
                        <i class="fas fa-briefcase mr-2 text-navy"></i>
                        <strong>Poste</strong>
                        <span class="float-right text-muted">
                            <?php echo e($employee['position_name']); ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($employee['hire_date'])): ?>
                    <li class="list-group-item">
                        <i class="fas fa-calendar mr-2 text-navy"></i>
                        <strong>Date d'embauche</strong>
                        <span class="float-right text-muted">
                            <?php echo formatDate($employee['hire_date']); ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($employee['contract_type'])): ?>
                    <li class="list-group-item">
                        <i class="fas fa-file-contract mr-2 text-navy"></i>
                        <strong>Type de contrat</strong>
                        <span class="float-right text-muted">
                            <?php echo e($employee['contract_type']); ?>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Edit Profile Link -->
                <a href="<?php echo APP_URL; ?>/profile/edit" class="btn btn-navy btn-block">
                    <i class="fas fa-edit mr-1"></i> Modifier le profil
                </a>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-8">
        <!-- Employee Details Card -->
        <?php if ($employee): ?>
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-id-card mr-1"></i> Informations professionnelles
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted font-weight-bold" style="width: 40%;">Matricule</td>
                                    <td><?php echo e($employee['employee_code'] ?? '--'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Département</td>
                                    <td><?php echo e($employee['department_name'] ?? '--'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Poste</td>
                                    <td><?php echo e($employee['position_name'] ?? '--'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Date d'embauche</td>
                                    <td><?php echo formatDate($employee['hire_date'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Type de contrat</td>
                                    <td><?php echo e($employee['contract_type'] ?? '--'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted font-weight-bold" style="width: 40%;">Statut</td>
                                    <td><?php echo getStatusBadge($employee['status'] ?? 'active'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Salaire de base</td>
                                    <td>
                                        <?php
                                        echo isset($employee['base_salary'])
                                            ? formatMoney($employee['base_salary'])
                                            : '--';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Genre</td>
                                    <td>
                                        <?php
                                        $gender = $employee['gender'] ?? '';
                                        echo $gender === 'M' ? 'Homme' : ($gender === 'F' ? 'Femme' : '--');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Date de naissance</td>
                                    <td><?php echo formatDate($employee['birth_date'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted font-weight-bold">Adresse</td>
                                    <td><?php echo e($employee['address'] ?? '--'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Anciennete -->
                <?php if (!empty($employee['hire_date'])): ?>
                <?php
                $hireDate = new DateTime($employee['hire_date']);
                $today = new DateTime();
                $seniority = $hireDate->diff($today);
                $seniorityText = '';
                if ($seniority->y > 0) {
                    $seniorityText .= $seniority->y . ' an' . ($seniority->y > 1 ? 's' : '');
                }
                if ($seniority->m > 0) {
                    $seniorityText .= ($seniorityText ? ', ' : '') . $seniority->m . ' mois';
                }
                ?>
                <div class="callout callout-info mt-3">
                    <small>
                        <i class="fas fa-info-circle mr-1"></i>
                        Ancienneté dans l'entreprise : <strong><?php echo $seniorityText ?: 'Moins d\'un mois'; ?></strong>
                        (depuis le <?php echo formatDate($employee['hire_date']); ?>)
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card card-navy card-outline">
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle mr-2"></i>
                    Votre compte n'est pas encore lié à un profil employé. Veuillez contacter l'administrateur.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Change Password Card -->
        <div class="card card-navy card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-key mr-1"></i> Changer le mot de passe
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="<?php echo APP_URL; ?>/profile/change-password" method="post" id="changePasswordForm">
                    <!-- CSRF Token -->
                    
                        <?php echo Session::csrfField(); ?>

                    <!-- Current Password -->
                    <div class="form-group">
                        <label for="current_password">
                            <i class="fas fa-lock mr-1 text-muted"></i> Mot de passe actuel
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   name="current_password"
                                   id="current_password"
                                   class="form-control"
                                   placeholder="Entrez votre mot de passe actuel"
                                   required
                                   autocomplete="current-password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-key mr-1 text-muted"></i> Nouveau mot de passe
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   name="new_password"
                                   id="new_password"
                                   class="form-control"
                                   placeholder="Entrez le nouveau mot de passe"
                                   required
                                   minlength="8"
                                   autocomplete="new-password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            Minimum 8 caractères, incluant au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
                        </small>
                        <!-- Password strength indicator -->
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small id="passwordStrengthText" class="text-muted"></small>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-check-circle mr-1 text-muted"></i> Confirmer le mot de passe
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   name="confirm_password"
                                   id="confirm_password"
                                   class="form-control"
                                   placeholder="Confirmez le nouveau mot de passe"
                                   required
                                   autocomplete="new-password">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small id="passwordMatchText" class="form-text text-muted"></small>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-navy">
                            <i class="fas fa-save mr-1"></i> Mettre à jour le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    // Toggle password visibility
    $('.toggle-password').on('click', function () {
        var target = $($(this).data('target'));
        var icon = $(this).find('i');
        if (target.attr('type') === 'password') {
            target.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            target.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Password strength meter
    $('#new_password').on('input', function () {
        var val = $(this).val();
        var strength = 0;
        var bar = $('#passwordStrength');
        var text = $('#passwordStrengthText');

        if (val.length >= 8) strength += 25;
        if (val.length >= 12) strength += 10;
        if (/[a-z]/.test(val)) strength += 15;
        if (/[A-Z]/.test(val)) strength += 15;
        if (/[0-9]/.test(val)) strength += 15;
        if (/[^a-zA-Z0-9]/.test(val)) strength += 20;

        strength = Math.min(strength, 100);

        bar.css('width', strength + '%').attr('aria-valuenow', strength);

        if (strength < 30) {
            bar.removeClass('bg-warning bg-success').addClass('bg-danger');
            text.text('Faible').removeClass('text-warning text-success').addClass('text-danger');
        } else if (strength < 70) {
            bar.removeClass('bg-danger bg-success').addClass('bg-warning');
            text.text('Moyen').removeClass('text-danger text-success').addClass('text-warning');
        } else {
            bar.removeClass('bg-danger bg-warning').addClass('bg-success');
            text.text('Fort').removeClass('text-danger text-warning').addClass('text-success');
        }

        if (val.length === 0) {
            bar.css('width', '0%');
            text.text('');
        }

        checkPasswordMatch();
    });

    // Password match check
    $('#confirm_password').on('input', checkPasswordMatch);

    function checkPasswordMatch() {
        var newPwd = $('#new_password').val();
        var confirmPwd = $('#confirm_password').val();
        var matchText = $('#passwordMatchText');

        if (confirmPwd.length === 0) {
            matchText.text('');
            return;
        }

        if (newPwd === confirmPwd) {
            matchText.text('Les mots de passe correspondent.')
                     .removeClass('text-danger').addClass('text-success');
        } else {
            matchText.text('Les mots de passe ne correspondent pas.')
                     .removeClass('text-success').addClass('text-danger');
        }
    }

    // Form submission with validation
    $('#changePasswordForm').on('submit', function (e) {
        var newPwd = $('#new_password').val();
        var confirmPwd = $('#confirm_password').val();

        if (newPwd !== confirmPwd) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les mots de passe ne correspondent pas.',
                confirmButtonColor: '#001f3f'
            });
            return false;
        }

        if (newPwd.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Le mot de passe doit contenir au moins 8 caractères.',
                confirmButtonColor: '#001f3f'
            });
            return false;
        }

        return confirm('Voulez-vous vraiment changer votre mot de passe ?');
    });
});
</script>