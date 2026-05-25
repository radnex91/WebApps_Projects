<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('vehicules.delete');
        $db->prepare("DELETE FROM vehicules WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Véhicule supprimé avec succès.';
        redirect('index.php?page=vehicules');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'vehicules.create' : 'vehicules.edit');

        $v = ['immatriculation'=>'', 'marque'=>'', 'modele'=>'', 'nb_places'=>0, 'proprietaire'=>'', 'type_vehicule'=>'', 'active'=>1];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM vehicules WHERE id = ?");
            $stmt->execute([$id]); $v = $stmt->fetch();
            if (!$v) { $_SESSION['flash']['error'] = 'Véhicule introuvable.'; redirect('index.php?page=vehicules'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'immatriculation' => $_POST['immatriculation'],
                'marque' => $_POST['marque'],
                'modele' => $_POST['modele'],
                'nb_places' => (int)$_POST['nb_places'],
                'proprietaire' => $_POST['proprietaire'],
                'type_vehicule' => $_POST['type_vehicule'],
                'active' => isset($_POST['active']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO vehicules (immatriculation, marque, modele, nb_places, proprietaire, type_vehicule, active) VALUES (:immatriculation, :marque, :modele, :nb_places, :proprietaire, :type_vehicule, :active)");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Véhicule ajouté avec succès.';
            } else {
                $sets = "immatriculation=:immatriculation, marque=:marque, modele=:modele, nb_places=:nb_places, proprietaire=:proprietaire, type_vehicule=:type_vehicule, active=:active";
                $stmt = $db->prepare("UPDATE vehicules SET $sets WHERE id=$id");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Véhicule mis à jour avec succès.';
            }
            redirect('index.php?page=vehicules');
        }
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '🚌 Nouveau véhicule' : '✏️ Modifier le véhicule' ?></h3>
                <a href="index.php?page=vehicules" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Immatriculation *</label>
                        <input type="text" name="immatriculation" class="form-control" value="<?= e($v['immatriculation']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Marque</label>
                        <input type="text" name="marque" class="form-control" value="<?= e($v['marque']) ?>" placeholder="TOYOTA COASTER, MERCEDES...">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Modèle</label>
                        <input type="text" name="modele" class="form-control" value="<?= e($v['modele']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Nombre de places *</label>
                        <input type="number" name="nb_places" class="form-control" value="<?= $v['nb_places'] ?>" required min="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Propriétaire</label>
                        <input type="text" name="proprietaire" class="form-control" value="<?= e($v['proprietaire']) ?>" placeholder="COPRES, ETS OHC...">
                    </div>
                    <div class="form-group">
                        <label>Type de véhicule</label>
                        <select name="type_vehicule" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <option value="BUS MERCEDES" <?= $v['type_vehicule'] === 'BUS MERCEDES' ? 'selected' : '' ?>>BUS MERCEDES</option>
                            <option value="BUS KINGLONG" <?= $v['type_vehicule'] === 'BUS KINGLONG' ? 'selected' : '' ?>>BUS KINGLONG</option>
                            <option value="TOYOTA COASTER" <?= $v['type_vehicule'] === 'TOYOTA COASTER' ? 'selected' : '' ?>>TOYOTA COASTER</option>
                            <option value="TOYOTA HIACE" <?= $v['type_vehicule'] === 'TOYOTA HIACE' ? 'selected' : '' ?>>TOYOTA HIACE</option>
                            <option value="KINGLONG" <?= $v['type_vehicule'] === 'KINGLONG' ? 'selected' : '' ?>>KINGLONG</option>
                            <option value="ATEGO" <?= $v['type_vehicule'] === 'ATEGO' ? 'selected' : '' ?>>ATEGO</option>
                            <option value="BUS MERCEDES VIP" <?= $v['type_vehicule'] === 'BUS MERCEDES VIP' ? 'selected' : '' ?>>BUS MERCEDES VIP</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-weight:600;">✅ Véhicule actif</span>
                    </label>
                </div>
                <div class="form-actions">
                    <a href="index.php?page=vehicules" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default:
        requirePermission('vehicules.view');
        $vehicules = $db->query("SELECT * FROM vehicules ORDER BY immatriculation")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher un véhicule...">
            </div>
            <?php if (hasPermission('vehicules.create')): ?>
            <a href="index.php?page=vehicules&action=create" class="btn btn-primary">➕ Nouveau véhicule</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🚌 Parc automobile</h3>
                <span class="badge badge-info"><?= count($vehicules) ?> véhicule(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Immatriculation</th>
                            <th>Marque / Modèle</th>
                            <th>Places</th>
                            <th>Propriétaire</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vehicules)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400);">Aucun véhicule enregistré. 🚌</td></tr>
                        <?php else: foreach ($vehicules as $v): ?>
                        <tr>
                            <td><strong><?= e($v['immatriculation']) ?></strong></td>
                            <td><?= e($v['marque']) ?> <?= e($v['modele']) ?></td>
                            <td><?= (int)$v['nb_places'] ?></td>
                            <td><?= e($v['proprietaire']) ?></td>
                            <td><span class="badge badge-info"><?= e($v['type_vehicule']) ?></span></td>
                            <td><?= $v['active'] ? '<span class="badge badge-success">✅ Actif</span>' : '<span class="badge badge-secondary">❌ Inactif</span>' ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('vehicules.edit')): ?>
                                    <a href="index.php?page=vehicules&action=edit&id=<?= $v['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('vehicules.delete')): ?>
                                    <a href="index.php?page=vehicules&action=delete&id=<?= $v['id'] ?>" class="btn-icon" data-confirm="Supprimer ce véhicule ?" title="Supprimer">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        break;
}
