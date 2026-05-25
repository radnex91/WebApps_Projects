<?php
require_once __DIR__ . '/functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

// Accept both JSON and form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 'all';
} else {
    $id = $_POST['id'] ?? 'all';
}

$db = getDB();
$userId = $_SESSION['user_id'];

if ($id === 'all') {
    $db->prepare("UPDATE notifications SET lue=1 WHERE utilisateur_id=? AND lue=0")->execute([$userId]);
} else {
    $db->prepare("UPDATE notifications SET lue=1 WHERE id=? AND utilisateur_id=?")->execute([(int)$id, $userId]);
}

echo json_encode(['success' => true]);