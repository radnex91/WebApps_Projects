<?php
// api/emails.php
require_once '../includes/config.php';
requireLogin();

$user   = currentUser();
$uid    = $user['id'];

// Read php://input once and reuse
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($_POST['action'] ?? ($body['action'] ?? ''));

switch ($action) {

  // ── LIST emails in folder ──────────────────────────────
  case 'list':
    $folderId = (int)($_GET['folder_id'] ?? 0);
    // Verify folder belongs to user
    $fCheck = $pdo->prepare("SELECT id FROM folders WHERE id=? AND user_id=?");
    $fCheck->execute([$folderId, $uid]);
    if (!$fCheck->fetch()) apiError('Dossier invalide');

    $stmt = $pdo->prepare("
      SELECT e.*,
        LEFT(COALESCE(e.body_text, REGEXP_REPLACE(e.body_html,'<[^>]+>','')), 120) as preview,
        DATE_FORMAT(e.received_at, '%d/%m %H:%i') as received_fmt
      FROM emails e
      WHERE e.folder_id=? AND e.user_id=?
      ORDER BY e.received_at DESC
      LIMIT 100
    ");
    $stmt->execute([$folderId, $uid]);
    $emails = $stmt->fetchAll();

    // Add time_ago
    foreach ($emails as &$e) {
      $e['time_ago'] = timeAgo($e['received_at']);
      $e['preview']  = mb_strimwidth(strip_tags($e['preview'] ?? ''), 0, 100, '...');
    }
    apiSuccess(['emails' => $emails]);
    break;

  // ── READ single email ──────────────────────────────────
  case 'read':
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM emails WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    $email = $stmt->fetch();
    if (!$email) apiError('Email introuvable', 404);

    // Mark as read
    $pdo->prepare("UPDATE emails SET is_read=1 WHERE id=?")->execute([$id]);

    // Attachments
    $atts = $pdo->prepare("SELECT id, original_name, mime_type, size_bytes FROM attachments WHERE email_id=?");
    $atts->execute([$id]);
    $email['attachments'] = $atts->fetchAll();
    foreach ($email['attachments'] as &$a) $a['size_fmt'] = formatSize($a['size_bytes']);

    $email['received_at_fmt'] = date('d/m/Y H:i', strtotime($email['received_at']));
    apiSuccess(['email' => $email]);
    break;

  // ── DOWNLOAD attachment ────────────────────────────────
  case 'download':
    $attId = (int)($_GET['att_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT a.*, e.user_id FROM attachments a JOIN emails e ON e.id=a.email_id WHERE a.id=?");
    $stmt->execute([$attId]);
    $att = $stmt->fetch();
    if (!$att || $att['user_id'] != $uid) apiError('Pièce jointe introuvable', 404);

    $filePath = UPLOAD_DIR . $att['filename'];
    if (!file_exists($filePath)) apiError('Fichier introuvable sur le serveur', 404);

    header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($att['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;

  // ── SEND email ─────────────────────────────────────────
  case 'send':
    $to      = trim($body['to'] ?? '');
    $subject = trim($body['subject'] ?? '');
    $msgBody = trim($body['body'] ?? '');
    $cc      = trim($body['cc'] ?? '');
    $bcc     = trim($body['bcc'] ?? '');

    if (!$to) apiError('Destinataire requis');

    // Get sent folder
    $sentFolder = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='sent' LIMIT 1");
    $sentFolder->execute([$uid]);
    $sentFolderId = $sentFolder->fetchColumn();
    if (!$sentFolderId) apiError('Dossier Envoyés introuvable');

    $bodyHtml = '<p>'.nl2br(htmlspecialchars($msgBody, ENT_QUOTES, 'UTF-8')).'</p>';

    $stmt = $pdo->prepare("
      INSERT INTO emails (user_id, folder_id, from_name, from_email, to_email, cc_email, bcc_email, subject, body_html, body_text, is_read, sent_at, received_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())
    ");
    $stmt->execute([$uid, $sentFolderId, $user['display_name'], $user['email'],
                    $to, $cc, $bcc, $subject, $bodyHtml, $msgBody]);

    $emailId = $pdo->lastInsertId();

    // Handle attachments
    if (!empty($_FILES['attachments'])) {
      if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
      foreach ($_FILES['attachments']['name'] as $i => $origName) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $safeName = uniqid('att_') . ($ext ? '.' . $ext : '');
        $tmpPath = $_FILES['attachments']['tmp_name'][$i];
        $fileSize = $_FILES['attachments']['size'][$i];
        $mime = $_FILES['attachments']['type'][$i];
        if (move_uploaded_file($tmpPath, UPLOAD_DIR . $safeName)) {
          $pdo->prepare("INSERT INTO attachments (email_id, filename, original_name, mime_type, size_bytes) VALUES (?,?,?,?,?)")
              ->execute([$emailId, $safeName, $origName, $mime, $fileSize]);
          // Mark email as having attachment
          $pdo->prepare("UPDATE emails SET has_attachment=1 WHERE id=?")->execute([$emailId]);
        }
      }
    }

    // If recipient is also a zimbrax user → deliver to their inbox
    $recipient = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $recipient->execute([$to]);
    $recipUser = $recipient->fetch();
    if ($recipUser) {
      $recipInbox = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='inbox' LIMIT 1");
      $recipInbox->execute([$recipUser['id']]);
      $inboxId = $recipInbox->fetchColumn();
      if ($inboxId) {
        $pdo->prepare("INSERT INTO emails (user_id,folder_id,from_name,from_email,to_email,cc_email,subject,body_html,body_text,is_read,received_at,sent_at) VALUES (?,?,?,?,?,?,?,?,?,0,NOW(),NOW())")
            ->execute([$recipUser['id'], $inboxId, $user['display_name'], $user['email'], $to, $cc, $subject, $bodyHtml, $msgBody]);
      }
    }
    apiSuccess(['message' => 'Envoyé']);
    break;

  // ── SAVE DRAFT ─────────────────────────────────────────
  case 'draft':
    $draftFolder = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='drafts' LIMIT 1");
    $draftFolder->execute([$uid]);
    $draftId = $draftFolder->fetchColumn();
    if (!$draftId) apiError('Dossier Brouillons introuvable');

    $pdo->prepare("INSERT INTO emails (user_id,folder_id,from_email,to_email,subject,body_text,is_read) VALUES (?,?,?,?,?,?,1)")
        ->execute([$uid, $draftId, $user['email'], $body['to']??'', $body['subject']??'', $body['body']??'']);
    apiSuccess();
    break;

  // ── DELETE ─────────────────────────────────────────────
  case 'delete':
    $id = (int)($body['id'] ?? 0);
    // Move to trash instead of hard delete
    $trash = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='trash' LIMIT 1");
    $trash->execute([$uid]);
    $trashId = $trash->fetchColumn();
    if ($trashId) {
      $pdo->prepare("UPDATE emails SET folder_id=? WHERE id=? AND user_id=?")->execute([$trashId, $id, $uid]);
    } else {
      $pdo->prepare("DELETE FROM emails WHERE id=? AND user_id=?")->execute([$id, $uid]);
    }
    apiSuccess();
    break;

  // ── STAR toggle ────────────────────────────────────────
  case 'star':
    $id = (int)($body['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT is_starred FROM emails WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    $cur = $stmt->fetchColumn();
    $new = $cur ? 0 : 1;
    $pdo->prepare("UPDATE emails SET is_starred=? WHERE id=? AND user_id=?")->execute([$new, $id, $uid]);
    apiSuccess(['starred' => (bool)$new]);
    break;

  // ── MOVE to folder ─────────────────────────────────────
  case 'move':
    $id = (int)($body['id'] ?? 0);
    $targetFolder = (int)($body['folder_id'] ?? 0);
    // Verify target folder belongs to user
    $fCheck = $pdo->prepare("SELECT id FROM folders WHERE id=? AND user_id=?");
    $fCheck->execute([$targetFolder, $uid]);
    if (!$fCheck->fetch()) apiError('Dossier invalide');
    $pdo->prepare("UPDATE emails SET folder_id=? WHERE id=? AND user_id=?")->execute([$targetFolder, $id, $uid]);
    apiSuccess();
    break;

  // ── BULK actions ───────────────────────────────────────
  case 'bulk':
    $ids    = array_map('intval', $body['ids'] ?? []);
    $bAction= $body['bulk_action'] ?? '';
    if (empty($ids)) apiError('Aucun email sélectionné');
    if (count($ids) > 500) apiError('Trop d\'emails sélectionnés (max 500)');
    $ph = implode(',', array_fill(0, count($ids), '?'));

    if ($bAction === 'read') {
      $pdo->prepare("UPDATE emails SET is_read=1 WHERE id IN ($ph) AND user_id=?")
          ->execute([...$ids, $uid]);
    } elseif ($bAction === 'delete') {
      $trash = $pdo->prepare("SELECT id FROM folders WHERE user_id=? AND type='trash' LIMIT 1");
      $trash->execute([$uid]);
      $trashId = $trash->fetchColumn();
      if ($trashId) {
        $pdo->prepare("UPDATE emails SET folder_id=? WHERE id IN ($ph) AND user_id=?")
            ->execute([$trashId, ...$ids, $uid]);
      }
    }
    apiSuccess(['count' => count($ids)]);
    break;

  // ── SEARCH ────────────────────────────────────────────
  case 'search':
    $q = trim($_GET['q'] ?? '');
    if (!$q) apiSuccess(['emails' => []]);
    $q = mb_substr($q, 0, 200); // Limit search query length
    $stmt = $pdo->prepare("
      SELECT e.*,
        LEFT(COALESCE(e.body_text,''), 120) as preview
      FROM emails e
      WHERE e.user_id=?
        AND (e.subject LIKE ? OR e.from_email LIKE ? OR e.from_name LIKE ? OR e.body_text LIKE ?)
      ORDER BY e.received_at DESC LIMIT 50
    ");
    $qLike = "%$q%";
    $stmt->execute([$uid, $qLike, $qLike, $qLike, $qLike]);
    $emails = $stmt->fetchAll();
    foreach ($emails as &$e) $e['time_ago'] = timeAgo($e['received_at']);
    apiSuccess(['emails' => $emails]);
    break;

  // ── UNREAD COUNTS ──────────────────────────────────────
  case 'unread_counts':
    $stmt = $pdo->prepare("
      SELECT f.id, COUNT(CASE WHEN e.is_read=0 THEN 1 END) as unread
      FROM folders f
      LEFT JOIN emails e ON e.folder_id=f.id
      WHERE f.user_id=?
      GROUP BY f.id
    ");
    $stmt->execute([$uid]);
    apiSuccess(['counts' => $stmt->fetchAll()]);
    break;

  default:
    apiError('Action inconnue');
}