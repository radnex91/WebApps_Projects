<?php
// api/contacts.php
require_once '../includes/config.php';
requireLogin();
$uid  = currentUser()['id'];
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($body['action'] ?? '');

switch ($action) {
  case 'list':
    $q = trim($_GET['q'] ?? '');
    if ($q) {
      $stmt = $pdo->prepare("SELECT * FROM contacts WHERE user_id=? AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?) ORDER BY first_name LIMIT 100");
      $stmt->execute([$uid, "%$q%", "%$q%", "%$q%", "%$q%"]);
    } else {
      $stmt = $pdo->prepare("SELECT * FROM contacts WHERE user_id=? ORDER BY is_favorite DESC, first_name LIMIT 200");
      $stmt->execute([$uid]);
    }
    apiSuccess(['contacts' => $stmt->fetchAll()]);
    break;

  case 'get':
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    $c = $stmt->fetch();
    if (!$c) apiError('Contact introuvable', 404);
    apiSuccess(['contact' => $c]);
    break;

  case 'create':
    $fn = trim($body['first_name'] ?? '');
    if (!$fn) apiError('Prénom requis');
    $colors = ['#4f8ef7','#3dd68c','#f75f5f','#f7a84f','#a78bfa','#2dd4bf'];
    $color  = $colors[array_rand($colors)];
    $stmt = $pdo->prepare("INSERT INTO contacts (user_id,first_name,last_name,email,phone,company,job_title,notes,avatar_color) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$uid, $fn, $body['last_name']??'', $body['email']??'', $body['phone']??'',
                    $body['company']??'', $body['job_title']??'', $body['notes']??'', $color]);
    apiSuccess(['id' => $pdo->lastInsertId()]);
    break;

  case 'update':
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare("UPDATE contacts SET first_name=?,last_name=?,email=?,phone=?,company=?,job_title=?,notes=? WHERE id=? AND user_id=?")
        ->execute([$body['first_name']??'', $body['last_name']??'', $body['email']??'',
                   $body['phone']??'', $body['company']??'', $body['job_title']??'', $body['notes']??'', $id, $uid]);
    apiSuccess();
    break;

  case 'delete':
    $pdo->prepare("DELETE FROM contacts WHERE id=? AND user_id=?")->execute([(int)($body['id']??0), $uid]);
    apiSuccess();
    break;

  default: apiError('Action inconnue');
}
