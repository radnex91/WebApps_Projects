<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('chauffeurs.delete');
        $db->prepare("DELETE FROM chauffeurs WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Chauffeur supprimé avec succès.';
        redirect('index.php?page=chauffeurs');
        break;

    case 'create':
    case 'edit':
        requirePermission($action === 'create' ? 'chauffeurs.create' : 'chauffeurs.edit');

        $c = ['nom'=>'', 'permis'=>'', 'telephone'=>'', 'agence_id'=>'', 'active'=>1];
        if ($action === 'edit' && $id) {
            $stmt = $db->prepare("SELECT * FROM chauffeurs WHERE id = ?");
            $stmt->execute([$id]); $c = $stmt->fetch();
            if (!$c) { $_SESSION['flash']['error'] = 'Chauffeur introuvable.'; redirect('index.php?page=chauffeurs'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nom' => $_POST['nom'],
                'permis' => $_POST['permis'],
                'telephone' => $_POST['telephone'],
                'agence_id' => $_POST['agence_id'] ?: null,
                'active' => isset($_POST['active']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO chauffeurs (nom, permis, telephone, agence_id, active) VALUES (:nom, :permis, :telephone, :agence_id, :active)");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Chauffeur ajouté avec succès.';
            } else {
                $stmt = $db->prepare("UPDATE chauffeurs SET nom=:nom, permis=:permis, telephone=:telephone, agence_id=:agence_id, active=:active WHERE id=$id");
                $stmt->execute($data);
                $_SESSION['flash']['success'] = 'Chauffeur mis à jour avec succès.';
            }
            redirect('index.php?page=chauffeurs');
        }

        $agences = $db->query("SELECT * FROM agences WHERE active = 1 ORDER BY nom")->fetchAll();
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $action === 'create' ? '👨‍✈️ Nouveau chauffeur' : '✏️ Modifier le chauffeur' ?></h3>
                <a href="index.php?page=chauffeurs" class="btn btn-sm btn-ghost">← Retour</a>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom complet *</label>
                        <input type="text" name="nom" class="form-control" value="<?= e($c['nom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>N° Permis</label>
                        <input type="text" name="permis" class="form-control" value="<?= e($c['permis']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>📞 Téléphone</label>
                        <input type="text" name="telephone" class="form-control" value="<?= e($c['telephone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>🏢 Agence de rattachement</label>
                        <select name="agence_id" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $c['agence_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-weight:600;">✅ Chauffeur actif</span>
                    </label>
                </div>
                <div class="form-actions">
                    <a href="index.php?page=chauffeurs" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
        break;

    default:
        requirePermission('chauffeurs.view');
        $chauffeurs = $db->query("
            SELECT c.*, a.nom as agence_nom
            FROM chauffeurs c
            LEFT JOIN agences a ON c.agence_id = a.id
            ORDER BY c.nom
        ")->fetchAll();
        ?>
        <div class="actions-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="table-search" placeholder="Rechercher un chauffeur...">
            </div>
            <?php if (hasPermission('chauffeurs.create')): ?>
            <a href="index.php?page=chauffeurs&action=create" class="btn btn-primary">➕ Nouveau chauffeur</a>
            <?php endif; ?>
        </div>

        <?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">👨‍✈️ Liste des chauffeurs</h3>
                <span class="badge badge-info"><?= count($chauffeurs) ?> chauffeur(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Permis</th>
                            <th>📞 Téléphone</th>
                            <th>🏢 Agence</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($chauffeurs)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400);">Aucun chauffeur enregistré. 👨‍✈️</td></tr>
                        <?php else: foreach ($chauffeurs as $c): ?>
                        <tr>
                            <td><strong><?= e($c['nom']) ?></strong></td>
                            <td><?= e($c['permis']) ?></td>
                            <td><?= e($c['telephone']) ?></td>
                            <td><?= e($c['agence_nom']) ?></td>
                            <td><?= $c['active'] ? '<span class="badge badge-success">✅ Actif</span>' : '<span class="badge badge-secondary">❌ Inactif</span>' ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (hasPermission('chauffeurs.edit')): ?>
                                    <a href="index.php?page=chauffeurs&action=edit&id=<?= $c['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('chauffeurs.delete')): ?>
                                    <a href="index.php?page=chauffeurs&action=delete&id=<?= $c['id'] ?>" class="btn-icon" data-confirm="Supprimer ce chauffeur ?" title="Supprimer">🗑️</a>
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
