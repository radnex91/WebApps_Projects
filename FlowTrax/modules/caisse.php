<?php
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($action) {
    case 'delete':
        requirePermission('caisse.delete');
        $db->prepare("DELETE FROM caisse WHERE id = ?")->execute([$id]);
        $_SESSION['flash']['success'] = 'Opération supprimée avec succès.';
        redirect('index.php?page=caisse');
        break;

    case 'create':
        requirePermission('caisse.create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $db->prepare("INSERT INTO caisse (type, montant, description, date_operation, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['type'],
                str_replace([' ', ','], ['', '.'], $_POST['montant']),
                $_POST['description'],
                $_POST['date_operation'],
                $_SESSION['user_id']
            ]);
            $_SESSION['flash']['success'] = 'Opération enregistrée avec succès.';
            redirect('index.php?page=caisse');
        }
        break;
}

// Stats
$solde = $db->query("
    SELECT COALESCE(SUM(CASE WHEN type='credit' THEN montant ELSE 0 END), 0) as total_credit,
           COALESCE(SUM(CASE WHEN type='debit' THEN montant ELSE 0 END), 0) as total_debit
    FROM caisse
")->fetch();
$soldeGlobal = $solde['total_credit'] - $solde['total_debit'];

// Monthly stats
$soldeMois = $db->query("
    SELECT COALESCE(SUM(CASE WHEN type='credit' THEN montant ELSE 0 END), 0) as credit_mois,
           COALESCE(SUM(CASE WHEN type='debit' THEN montant ELSE 0 END), 0) as debit_mois
    FROM caisse WHERE MONTH(date_operation) = MONTH(CURDATE()) AND YEAR(date_operation) = YEAR(CURDATE())
")->fetch();
$soldeMoisCalc = $soldeMois['credit_mois'] - $soldeMois['debit_mois'];

// Operations
$operations = $db->query("
    SELECT c.*, u.prenom, u.nom
    FROM caisse c
    LEFT JOIN users u ON c.created_by = u.id
    ORDER BY c.date_operation DESC, c.created_at DESC
    LIMIT 50
")->fetchAll();
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green animate-bounce">💰</div>
        <div class="stat-content">
            <h3><?= formatMoney($soldeGlobal) ?></h3>
            <p>Solde global</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue animate-pulse">📈</div>
        <div class="stat-content">
            <h3><?= formatMoney($soldeMois['credit_mois']) ?></h3>
            <p>Crédits (ce mois)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red animate-bounce">📉</div>
        <div class="stat-content">
            <h3><?= formatMoney($soldeMois['debit_mois']) ?></h3>
            <p>Débits (ce mois)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow animate-pulse">💵</div>
        <div class="stat-content">
            <h3><?= formatMoney($soldeMoisCalc) ?></h3>
            <p>Solde du mois</p>
        </div>
    </div>
</div>

<?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">💳 Nouvelle opération</h3>
    </div>
    <form method="POST" action="index.php?page=caisse&action=create">
        <div class="form-row">
            <div class="form-group">
                <label>Type *</label>
                <select name="type" class="form-control" required>
                    <option value="credit">💰 Crédit (Entrée)</option>
                    <option value="debit">💸 Débit (Sortie)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Montant *</label>
                <input type="text" name="montant" class="form-control" placeholder="Ex: 50000" required>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date_operation" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" placeholder="Motif de l'opération..."></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">📋 Dernières opérations</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Montant</th>
                    <th>Description</th>
                    <th>Saisi par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($operations)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400);">Aucune opération. 💰</td></tr>
                <?php else: foreach ($operations as $o): ?>
                <tr>
                    <td><?= formatDate($o['date_operation']) ?></td>
                    <td><?= $o['type'] === 'credit' ? '<span class="badge badge-success">💰 Crédit</span>' : '<span class="badge badge-danger">💸 Débit</span>' ?></td>
                    <td><strong><?= formatMoney($o['montant']) ?></strong></td>
                    <td><?= e($o['description']) ?></td>
                    <td><?= e($o['prenom']) ?> <?= e($o['nom']) ?></td>
                    <td>
                        <?php if (hasPermission('caisse.delete')): ?>
                        <a href="index.php?page=caisse&action=delete&id=<?= $o['id'] ?>" class="btn-icon" data-confirm="Supprimer cette opération ?" title="Supprimer">🗑️</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
