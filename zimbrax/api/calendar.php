<?php
// api/calendar.php
require_once '../includes/config.php';
requireLogin();
$uid  = currentUser()['id'];
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($body['action'] ?? '');

switch ($action) {

  case 'list':
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $end   = date('Y-m-t 23:59:59', strtotime($start));
    $stmt  = $pdo->prepare("SELECT * FROM events WHERE user_id=? AND start_datetime BETWEEN ? AND ? ORDER BY start_datetime");
    $stmt->execute([$uid, $start, $end]);
    apiSuccess(['events' => $stmt->fetchAll()]);
    break;

  case 'get':
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    $ev   = $stmt->fetch();
    if (!$ev) apiError('Événement introuvable', 404);
    apiSuccess(['event' => $ev]);
    break;

  case 'create':
    $title = trim($body['title'] ?? '');
    if (!$title) apiError('Titre requis');
    $startDt = trim($body['start_datetime'] ?? '');
    $endDt   = trim($body['end_datetime'] ?? '');
    // Validate datetime format
    if ($startDt && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $startDt))
        apiError('Format date/heure début invalide');
    if ($endDt && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $endDt))
        apiError('Format date/heure fin invalide');
    $startDt = $startDt ?: date('Y-m-d H:i:s');
    $endDt   = $endDt ?: date('Y-m-d H:i:s', strtotime('+1 hour'));
    // Validate end is after start
    if (strtotime($endDt) <= strtotime($startDt))
        apiError('La date de fin doit être après la date de début');
    $stmt = $pdo->prepare("INSERT INTO events (user_id,title,description,location,start_datetime,end_datetime,color,category) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$uid, $title, $body['description']??'', $body['location']??'',
                    $startDt, $endDt,
                    $body['color']??'#4f8ef7', $body['category']??'work']);
    apiSuccess(['id' => $pdo->lastInsertId()]);
    break;

  case 'update':
    $id = (int)($body['id'] ?? 0);
    $title = trim($body['title'] ?? '');
    if ($title === '') apiError('Titre requis');
    $startDt = trim($body['start_datetime'] ?? '');
    $endDt   = trim($body['end_datetime'] ?? '');
    // Validate datetime format
    if ($startDt && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $startDt))
        apiError('Format date/heure début invalide');
    if ($endDt && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $endDt))
        apiError('Format date/heure fin invalide');
    // Validate end is after start
    if ($startDt && $endDt && strtotime($endDt) <= strtotime($startDt))
        apiError('La date de fin doit être après la date de début');
    $pdo->prepare("UPDATE events SET title=?,description=?,location=?,start_datetime=?,end_datetime=?,color=?,category=? WHERE id=? AND user_id=?")
        ->execute([$title, $body['description']??'', $body['location']??'',
                   $startDt ?: date('Y-m-d H:i:s'), $endDt ?: date('Y-m-d H:i:s', strtotime('+1 hour')),
                   $body['color']??'#4f8ef7', $body['category']??'work', $id, $uid]);
    apiSuccess();
    break;

  case 'delete':
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare("DELETE FROM events WHERE id=? AND user_id=?")->execute([$id, $uid]);
    apiSuccess();
    break;

  default:
    apiError('Action inconnue');
}