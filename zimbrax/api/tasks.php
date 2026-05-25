<?php
// api/tasks.php
require_once '../includes/config.php';
requireLogin();
$uid  = currentUser()['id'];
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($body['action'] ?? '');

switch ($action) {
  case 'list':
    $list = trim($_GET['list'] ?? '');
    if ($list) {
      $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id=? AND list_name=? ORDER BY status='done',priority='urgent' DESC,priority='high' DESC,sort_order,created_at DESC");
      $stmt->execute([$uid, $list]);
    } else {
      $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id=? ORDER BY status='done',FIELD(priority,'urgent','high','normal','low'),due_date IS NULL,due_date,created_at DESC LIMIT 100");
      $stmt->execute([$uid]);
    }
    apiSuccess(['tasks' => $stmt->fetchAll()]);
    break;

  case 'create':
    $title = trim($body['title'] ?? '');
    if (!$title) apiError('Titre requis');
    $priority = $body['priority'] ?? 'normal';
    if (!in_array($priority, ['low','normal','high','urgent'], true))
        apiError('Priorité invalide');
    $status = $body['status'] ?? 'todo';
    if (!in_array($status, ['todo','in_progress','done','cancelled'], true))
        apiError('Statut invalide');
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id,title,description,due_date,priority,status,list_name,color) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$uid, $title, $body['description']??'',
                    $body['due_date']??null, $priority,
                    $status, $body['list_name']??'Tâches', $body['color']??'#4f8ef7']);
    apiSuccess(['id' => $pdo->lastInsertId()]);
    break;

  case 'update':
    $id = (int)($body['id'] ?? 0);
    $fields = []; $params = [];
    foreach (['title','description','due_date','list_name','color'] as $f) {
      if (isset($body[$f])) { $fields[] = "$f=?"; $params[] = $body[$f]; }
    }
    // Validate and add priority
    if (isset($body['priority'])) {
      if (!in_array($body['priority'], ['low','normal','high','urgent'], true))
          apiError('Priorité invalide');
      $fields[] = 'priority=?'; $params[] = $body['priority'];
    }
    // Validate and add status + completed_at management
    if (isset($body['status'])) {
      if (!in_array($body['status'], ['todo','in_progress','done','cancelled'], true))
          apiError('Statut invalide');
      $fields[] = 'status=?'; $params[] = $body['status'];
      if ($body['status'] === 'done') {
        $fields[] = 'completed_at=NOW()';
      } else {
        // Clear completed_at when reverting from done
        $fields[] = 'completed_at=NULL';
      }
    }
    if ($fields) {
      $params[] = $id; $params[] = $uid;
      $pdo->prepare("UPDATE tasks SET ".implode(',',$fields)." WHERE id=? AND user_id=?")->execute($params);
    }
    apiSuccess();
    break;

  case 'delete':
    $pdo->prepare("DELETE FROM tasks WHERE id=? AND user_id=?")->execute([(int)($body['id']??0), $uid]);
    apiSuccess();
    break;

  default: apiError('Action inconnue');
}