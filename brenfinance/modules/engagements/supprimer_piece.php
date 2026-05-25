<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('engagements');
if (!hasPermission('engagements', 'creer')) { flash('danger','Vous n\'avez pas le droit de modifier cet engagement.'); header('Location: '.BASE_URL.'/modules/engagements/index.php'); exit; }

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$engId = (int)($_POST['engagement_id'] ?? 0);
$fileName = trim($_POST['delete_piece'] ?? '');

if (!$engId || $fileName === '') { flash('danger','Paramètres manquants.'); header('Location: index.php'); exit; }

// Check ownership and status
$checkR = $db->prepare("SELECT demandeur_id, statut, pieces_jointes FROM demandes_engagement WHERE id=?");
$checkR->execute([$engId]);
$eng = $checkR->fetch();

if (!$eng || (int)$eng['demandeur_id'] !== $userId || !in_array($eng['statut'], ['brouillon','renvoye'])) {
    flash('danger', 'Vous ne pouvez modifier que vos propres brouillons ou engagements renvoyés.');
    header('Location: index.php'); exit;
}

// Remove the piece from JSON
$pieces = json_decode($eng['pieces_jointes'] ?? '', true) ?: [];
$found = false;
$pieces = array_filter($pieces, function($p) use ($fileName, &$found) {
    if ($p['fichier'] === $fileName) {
        $found = true;
        $filePath = BASE_PATH . '/uploads/engagements/' . $p['fichier'];
        if (file_exists($filePath)) unlink($filePath);
        return false;
    }
    return true;
});
$pieces = array_values($pieces);

if ($found) {
    $db->prepare("UPDATE demandes_engagement SET pieces_jointes=? WHERE id=?")
       ->execute([json_encode($pieces), $engId]);
    auditLog('supprimer_piece','engagements','demandes_engagement',$engId);
    flash('success', 'Pièce jointe supprimée.');
} else {
    flash('warning', 'Fichier introuvable dans les pièces jointes.');
}

$from = $_POST['from'] ?? 'modifier';
header('Location: ' . ($from === 'detail' ? 'detail.php?id=' : 'modifier.php?id=') . $engId);
exit;