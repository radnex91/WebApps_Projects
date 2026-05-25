<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('agences.delete');
        $db->prepare("DELETE FROM agences WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Agence supprimée avec succès.';
        redirect('index.php?page=agences');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'agences.create' : 'agences.edit');

        $a = ['nom'=>'', 'ville'=>'', 'region'=>'', 'contact'=>'', 'active'=>1];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM agences WHERE id = ?");
            $stmt->execute([$id]); $a = $stmt->fetch();
            if (!$a) { $_SESSION['flash']['error'] = 'Agence introuvable.'; redirect('index.php?page=agences'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nom' => $_POST['nom'],
                'ville' => $_POST['ville'],
                'region' => $_POST['region'],
                'contact' => $_POST['contact'],
                'active' => isset($_POST['active']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO agences (nom, ville, region, contact, active) VALUES (:nom, :ville, :region, :contact, :active)");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Agence créée avec succès.';
            } else {
                $stmt = $db->prepare("UPDATE agences SET nom=:nom, ville=:ville, region=:region, contact=:contact, active=:active WHERE id=$id");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Agence mise à jour avec succès.';
            }
            redirect('index.php?page=agences');
        }

        $regions = ['Littoral', 'Centre', 'Est', 'Adamaoua', 'Nord', 'Extrême-Nord'];
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '🏢 Nouvelle agence' : '✏️ Modifier l\'agence' ?></h3>
                <a href="index.php?page=agences" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom de l'agence *</label>
                        <input type="text" name="nom" class="form-control" value="<?= e($a['nom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="ville" class="form-control" value="<?= e($a['ville']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Région</label>
                        <select name="region" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($regions as $r): ?>
                            <option value="<?= $r ?>" <?= $a['region'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact</label>
                        <input type="text" name="contact" class="form-control" value="<?= e($a['contact']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="active" value="1" <?= $a['active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-weight:600;">✅ Agence active</span>
                    </label>
                </div>
                <div class="form-actions">
                    <a href="index.php?page=agences" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default:
        requirePermission('agences.view');
        $agences = $db->query("SELECT * FROM agences ORDER BY region, nom")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher une agence...">
            </div>
            <?php if (hasPermission('agences.create')): ?>
            <a href="index.php?page=agences&action=create" class="btn btn-primary">➕ Nouvelle agence</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🏢 Liste des agences</h3>
                <span class="badge badge-info"><?= count($agences) ?> agence(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Agence</th>
                            <th>Ville</th>
                            <th>Région</th>
                            <th>Contact</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agences)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400);">Aucune agence enregistrée. 🏢</td></tr>
                        <?php else: foreach ($agences as $a): ?>
                        <tr>
                            <td><strong><?= e($a['nom']) ?></strong></td>
                            <td><?= e($a['ville']) ?></td>
                            <td><span class="badge badge-primary"><?= e($a['region']) ?></span></td>
                            <td><?= e($a['contact']) ?></td>
                            <td><?= $a['active'] ? '<span class="badge badge-success">✅ Active</span>' : '<span class="badge badge-secondary">❌ Inactive</span>' ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('agences.edit')): ?>
                                    <a href="index.php?page=agences&action=edit&id=<?= $a['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('agences.delete')): ?>
                                    <a href="index.php?page=agences&action=delete&id=<?= $a['id'] ?>" class="btn-icon" data-confirm="Supprimer cette agence ?" title="Supprimer">🗑️</a>
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
