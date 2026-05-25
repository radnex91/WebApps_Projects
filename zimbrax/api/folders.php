<?php
// api/folders.php
require_once '../includes/config.php';
requireLogin();
$uid    = currentUser()['id'];
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $pdo->prepare("INSERT INTO folders (user_id, name, type, sort_order) VALUES (?,?,'custom',99)")
            ->execute([$uid, $name]);
        flash("Dossier '$name' créé.");
    }
}
elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // Check it's custom and belongs to user
    $check = $pdo->prepare("SELECT id FROM folders WHERE id=? AND user_id=? AND type='custom'");
    $check->execute([$id, $uid]);
    if ($check->fetch()) {
        // Move emails to trash first
        $trash = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='trash' LIMIT 1");
        $trash->execute([$uid]);
        $trashId = $trash->fetchColumn();
        if ($trashId) $pdo->prepare("UPDATE emails SET folder_id=? WHERE folder_id=? AND user_id=?")->execute([$trashId, $id, $uid]);
        $pdo->prepare("DELETE FROM folders WHERE id=?")->execute([$id]);
        flash("Dossier supprimé.", 'warning');
    }
}

redirect(BASE_URL . 'modules/settings/');
