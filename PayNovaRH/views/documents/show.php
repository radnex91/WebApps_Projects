<?php
$title = e($document['title']);
$activeMenu = 'documents';
$breadcrumbs = ['Documents' => '/documents', e($document['title']) => null];
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt mr-1"></i> Détails du document
                </h3>
                <div class="card-tools">
                    <a href="<?php echo APP_URL; ?>/documents/<?php echo e($document['id']); ?>/download" class="btn btn-primary btn-sm">
                        <i class="fas fa-download mr-1"></i> Télécharger
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width:160px;">Titre</th>
                        <td><?php echo e($document['title']); ?></td>
                    </tr>
                    <tr>
                        <th>Employé</th>
                        <td><?php echo e(($document['first_name'] ?? '') . ' ' . ($document['last_name'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Catégorie</th>
                        <td>
                            <?php
                            $catColors = ['contrat' => 'primary', 'paie' => 'success', 'formation' => 'info', 'évaluation' => 'warning', 'autre' => 'secondary'];
                            $catColor = $catColors[$document['category'] ?? 'autre'] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?php echo $catColor; ?>">
                                <?php echo e(ucfirst($document['category'] ?? 'autre')); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?php echo nl2br(e($document['description'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Type de fichier</th>
                        <td><?php echo e($document['file_type'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Taille</th>
                        <td>
                            <?php
                            $size = (int)($document['file_size'] ?? 0);
                            if ($size >= 1048576) echo number_format($size / 1048576, 1) . ' Mo';
                            elseif ($size >= 1024) echo number_format($size / 1024, 1) . ' Ko';
                            else echo $size . ' o';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Date d'ajout</th>
                        <td><?php echo formatDate($document['created_at']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="card-footer">
                <a href="<?php echo APP_URL; ?>/documents" class="btn btn-default">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à la liste
                </a>
            </div>
        </div>
    </div>
</div>