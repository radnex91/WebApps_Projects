<?php
requireLogin();
requirePermission('settings.view');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('settings.edit')) {
    $keys = $_POST['settings'] ?? [];
    $stmt = $db->prepare("UPDATE settings SET valeur = ? WHERE cle = ?");
    foreach ($keys as $cle => $valeur) {
        $stmt->execute([$valeur, $cle]);
    }
    $_SESSION['flash']['success'] = 'Paramètres mis à jour avec succès.';
    redirect('index.php?page=settings');
}

$settings = $db->query("SELECT * FROM settings ORDER BY id")->fetchAll();
?>
<?php if ($success = flash('success')): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">⚙️ Paramètres de l'application</h3>
    </div>
    <form method="POST">
        <div class="form-row">
            <?php foreach ($settings as $s): ?>
            <div class="form-group">
                <label><?= e($s['description'] ?: $s['cle']) ?></label>
                <?php if ($s['type'] === 'textarea'): ?>
                <textarea name="settings[<?= e($s['cle']) ?>]" class="form-control"><?= e($s['valeur']) ?></textarea>
                <?php else: ?>
                <input type="<?= $s['type'] === 'number' ? 'number' : 'text' ?>" name="settings[<?= e($s['cle']) ?>]" class="form-control" value="<?= e($s['valeur']) ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (hasPermission('settings.edit')): ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Enregistrer les paramètres</button>
        </div>
        <?php endif; ?>
    </form>
</div>
