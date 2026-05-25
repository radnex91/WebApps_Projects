<?php
$pageTitle = 'Fiche employé';
$activeMenu = 'employees';
$breadcrumbs = ['Employés' => '/employees', $employee['first_name'] . ' ' . $employee['last_name'] => ''];
?>

<div class="row">
    <!-- Employee Profile Card -->
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body box-profile text-center">
                <div class="mb-3">
                    <?php if (!empty($employee['photo'])): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/<?php echo e($employee['photo']); ?>"
                             class="profile-user-img img-fluid img-circle"
                             alt="Photo de l'employé"
                             style="max-width:120px;">
                    <?php else: ?>
                        <div class="mx-auto bg-navy text-white rounded-circle d-flex align-items-center justify-content-center"
                             style="width:120px;height:120px;font-size:48px;">
                            <?php echo e(mb_strtoupper(mb_substr($employee['first_name'] ?? 'U', 0, 1) . mb_substr($employee['last_name'] ?? '', 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="profile-username">
                    <?php echo e(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')); ?>
                </h3>
                <p class="text-muted mb-2">
                    <?php echo e($employee['position_title'] ?? 'Non défini'); ?>
                </p>
                <div class="mb-3">
                    <?php echo getStatusBadge($employee['status'] ?? 'actif'); ?>
                </div>

                <ul class="list-group list-group-unbordered mb-3 text-left">
                    <li class="list-group-item">
                        <b><i class="fas fa-building mr-1"></i> Département</b>
                        <span class="float-right"><?php echo e($employee['department_name'] ?? '-'); ?></span>
                    </li>
                    <li class="list-group-item">
                        <b><i class="fas fa-envelope mr-1"></i> Email</b>
                        <span class="float-right small"><?php echo e($employee['email'] ?? '-'); ?></span>
                    </li>
                    <li class="list-group-item">
                        <b><i class="fas fa-phone mr-1"></i> Téléphone</b>
                        <span class="float-right"><?php echo e($employee['phone'] ?? '-'); ?></span>
                    </li>
                    <li class="list-group-item">
                        <b><i class="fas fa-calendar-alt mr-1"></i> Date d'embauche</b>
                        <span class="float-right"><?php echo formatDate($employee['hire_date'] ?? null); ?></span>
                    </li>
                </ul>

                <?php if (Auth::hasPermission('employees', 'edit')): ?>
                    <a href="<?php echo APP_URL; ?>/employees/<?php echo (int)$employee['id']; ?>/edit"
                       class="btn btn-warning btn-block btn-sm">
                        <i class="fas fa-edit mr-1"></i> Modifier
                    </a>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/employees" class="btn btn-default btn-block btn-sm mt-2">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à la liste
                </a>
            </div>
        </div>
    </div>

    <!-- Employee Details -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="pill" href="#personal">Informations personnelles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="pill" href="#contract">Contrat</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="pill" href="#leaves">Congés</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="pill" href="#documents">Documents</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane active" id="personal">
                        <h5 class="text-navy mb-3"><i class="fas fa-user mr-1"></i> Détails personnels</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted" width="40%">Date de naissance</th>
                                        <td><?php echo formatDate($employee['birth_date'] ?? null) ?: '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Genre</th>
                                        <td><?php echo e($employee['gender'] === 'homme' ? 'Homme' : ($employee['gender'] === 'femme' ? 'Femme' : '-')); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Situation matrimoniale</th>
                                        <td><?php echo e(ucfirst($employee['marital_status'] ?? '-') ?: '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Nombre d'enfants</th>
                                        <td><?php echo e($employee['children_count'] ?? '0'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted" width="40%">Adresse</th>
                                        <td><?php echo e($employee['address'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Ville</th>
                                        <td><?php echo e($employee['city'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Pays</th>
                                        <td><?php echo e($employee['country'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">CIN</th>
                                        <td><?php echo e($employee['cin'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">CNSS</th>
                                        <td><?php echo e($employee['cnss'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <hr>
                        <h5 class="text-warning mb-3"><i class="fas fa-phone-alt mr-1"></i> Contact d'urgence</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted" width="40%">Nom</th>
                                        <td><?php echo e($employee['emergency_name'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Lien de parenté</th>
                                        <td><?php echo e($employee['emergency_relationship'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Téléphone</th>
                                        <td><?php echo e($employee['emergency_phone'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <hr>
                        <h5 class="text-success mb-3"><i class="fas fa-university mr-1"></i> Informations bancaires</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-muted" width="40%">Banque</th>
                                        <td><?php echo e($employee['bank_name'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">RIB</th>
                                        <td><?php echo e($employee['bank_rib'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Titulaire du compte</th>
                                        <td><?php echo e($employee['bank_account_holder'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Contract Tab -->
                    <div class="tab-pane" id="contract">
                        <h5 class="text-navy mb-3"><i class="fas fa-file-contract mr-1"></i> Informations du contrat</h5>
                        <?php if (!empty($contract)): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th class="text-muted" width="40%">Type</th>
                                            <td><?php echo getStatusBadge($contract['type'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted">Date de début</th>
                                            <td><?php echo formatDate($contract['start_date'] ?? null); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted">Date de fin</th>
                                            <td><?php echo formatDate($contract['end_date'] ?? null) ?: 'Non définie'; ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted">Date de renouvellement</th>
                                            <td><?php echo formatDate($contract['renewal_date'] ?? null) ?: '-'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th class="text-muted" width="40%">Salaire</th>
                                            <td><?php echo formatMoney($contract['salary'] ?? 0); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="text-muted">Statut</th>
                                            <td><?php echo getStatusBadge($contract['status'] ?? ''); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <?php if (!empty($contract['description'])): ?>
                                <div class="mt-2">
                                    <strong class="text-muted">Description :</strong>
                                    <p class="mt-1"><?php echo e($contract['description']); ?></p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-file-contract fa-2x mb-2 d-block"></i>
                                Aucun contrat enregistré pour cet employé.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Leaves Tab -->
                    <div class="tab-pane" id="leaves">
                        <h5 class="text-navy mb-3"><i class="fas fa-calendar-alt mr-1"></i> Solde de congés</h5>
                        <?php if (!empty($leaveBalances)): ?>
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Type de congé</th>
                                        <th class="text-center">Droits</th>
                                        <th class="text-center">Pris</th>
                                        <th class="text-center">Restants</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveBalances as $balance): ?>
                                        <tr>
                                            <td><?php echo e($balance['type_name'] ?? ''); ?></td>
                                            <td class="text-center"><?php echo (int)($balance['total'] ?? 0); ?></td>
                                            <td class="text-center"><?php echo (int)($balance['used'] ?? 0); ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-<?php echo (($balance['remaining'] ?? 0) > 0) ? 'success' : 'secondary'; ?>">
                                                    <?php echo (int)($balance['remaining'] ?? 0); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-alt fa-2x mb-2 d-block"></i>
                                Aucun solde de congés enregistré.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Documents Tab -->
                    <div class="tab-pane" id="documents">
                        <h5 class="text-navy mb-3"><i class="fas fa-folder mr-1"></i> Documents</h5>
                        <?php if (!empty($documents)): ?>
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Date d'ajout</th>
                                        <th width="80">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-file mr-1 text-muted"></i>
                                                <?php echo e($doc['name'] ?? $doc['original_name'] ?? ''); ?>
                                            </td>
                                            <td><?php echo e($doc['type'] ?? '-'); ?></td>
                                            <td><?php echo formatDate($doc['created_at'] ?? null); ?></td>
                                            <td class="text-center">
                                                <a href="<?php echo APP_URL; ?>/uploads/<?php echo e($doc['file_path'] ?? ''); ?>"
                                                   class="btn btn-info btn-xs"
                                                   target="_blank"
                                                   title="Télécharger">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                Aucun document enregistré pour cet employé.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>