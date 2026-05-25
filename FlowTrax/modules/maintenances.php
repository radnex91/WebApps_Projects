<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('maintenances.delete');
        $db->prepare("DELETE FROM maintenances WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Maintenance supprimée avec succès.';
        redirect('index.php?page=maintenances');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'maintenances.create' : 'maintenances.edit');

        $m = ['resume'=>'', 'departement'=>'', 'date_debut'=>'', 'type_maintenance'=>'', 'etapes'=>'', 'materiel'=>'', 'personnel'=>'', 'ressources'=>''];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM maintenances WHERE id = ?");
            $stmt->execute([$id]); $m = $stmt->fetch();
            if (!$m) { $_SESSION['flash']['error'] = 'Maintenance introuvable.'; redirect('index.php?page=maintenances'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'resume' => $_POST['resume'],
                'departement' => $_POST['departement'],
                'date_debut' => $_POST['date_debut'],
                'type_maintenance' => $_POST['type_maintenance'],
                'etapes' => $_POST['etapes'],
                'materiel' => $_POST['materiel'],
                'personnel' => $_POST['personnel'],
                'ressources' => $_POST['ressources'],
            ];

            if ($action === 'create') {
                $data['created_by'] = $_SESSION['user_id'];
                $stmt = $db->prepare("INSERT INTO maintenances (resume, departement, date_debut, type_maintenance, etapes, materiel, personnel, ressources, created_by) VALUES (:resume, :departement, :date_debut, :type_maintenance, :etapes, :materiel, :personnel, :ressources, :created_by)");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Maintenance enregistrée avec succès.';
            } else {
                $stmt = $db->prepare("UPDATE maintenances SET resume=:resume, departement=:departement, date_debut=:date_debut, type_maintenance=:type_maintenance, etapes=:etapes, materiel=:materiel, personnel=:personnel, ressources=:ressources WHERE id=$id");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Maintenance mise à jour avec succès.';
            }
            redirect('index.php?page=maintenances');
        }
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '🔧 Nouvelle maintenance' : '✏️ Modifier la maintenance' ?></h3>
                <a href="index.php?page=maintenances" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Résumé du problème *</label>
                        <input type="text" name="resume" class="form-control" value="<?= e($m['resume']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Département</label>
                        <input type="text" name="departement" class="form-control" value="<?= e($m['departement']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date et heure</label>
                        <input type="datetime-local" name="date_debut" class="form-control" value="<?= e($m['date_debut'] ? str_replace(' ', 'T', $m['date_debut']) : '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Type de maintenance</label>
                        <select name="type_maintenance" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <option value="Réparation" <?= $m['type_maintenance'] === 'Réparation' ? 'selected' : '' ?>>🔧 Réparation</option>
                            <option value="Mise à jour" <?= $m['type_maintenance'] === 'Mise à jour' ? 'selected' : '' ?>>🔄 Mise à jour</option>
                            <option value="Installation" <?= $m['type_maintenance'] === 'Installation' ? 'selected' : '' ?>>📦 Installation</option>
                            <option value="Maintenance préventive" <?= $m['type_maintenance'] === 'Maintenance préventive' ? 'selected' : '' ?>>🛡️ Préventive</option>
                            <option value="Urgence" <?= $m['type_maintenance'] === 'Urgence' ? 'selected' : '' ?>>🚨 Urgence</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Étapes de maintenance</label>
                    <textarea name="etapes" class="form-control" rows="3"><?= e($m['etapes']) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Matériel utilisé</label>
                        <input type="text" name="materiel" class="form-control" value="<?= e($m['materiel']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Personnel impliqué</label>
                        <input type="text" name="personnel" class="form-control" value="<?= e($m['personnel']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Ressources consommées</label>
                        <input type="text" name="ressources" class="form-control" value="<?= e($m['ressources']) ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <a href="index.php?page=maintenances" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default:
        requirePermission('maintenances.view');
        $maintenances = $db->query("
            SELECT m.*, u.prenom, u.nom
            FROM maintenances m
            LEFT JOIN users u ON m.created_by = u.id
            ORDER BY m.created_at DESC
        ")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher une maintenance...">
            </div>
            <?php if (hasPermission('maintenances.create')): ?>
            <a href="index.php?page=maintenances&action=create" class="btn btn-primary">➕ Nouvelle maintenance</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔧 Maintenance IT</h3>
                <span class="badge badge-info"><?= count($maintenances) ?> intervention(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Résumé</th>
                            <th>Département</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Personnel</th>
                            <th>Saisi par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maintenances)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400);">Aucune maintenance enregistrée. 🔧</td></tr>
                        <?php else: foreach ($maintenances as $m): ?>
                        <tr>
                            <td><strong><?= e($m['resume']) ?></strong></td>
                            <td><?= e($m['departement']) ?></td>
                            <td><?= formatDatetime($m['date_debut']) ?></td>
                            <td><span class="badge badge-info"><?= e($m['type_maintenance']) ?></span></td>
                            <td><?= e($m['personnel']) ?></td>
                            <td><?= e($m['prenom']) ?> <?= e($m['nom']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('maintenances.edit')): ?>
                                    <a href="index.php?page=maintenances&action=edit&id=<?= $m['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('maintenances.delete')): ?>
                                    <a href="index.php?page=maintenances&action=delete&id=<?= $m['id'] ?>" class="btn-icon" data-confirm="Supprimer cette maintenance ?" title="Supprimer">🗑️</a>
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
