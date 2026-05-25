<?php

declare(strict_types=1);

function ensureSetupComplete(): void
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        runMigrations();
    } catch (Throwable $exception) {
        redirect('setup.php');
    }
}

function runMigrations(): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('Impossible de lire le schema SQL.');
    }

    db()->exec($schema);

    $uploadDir = __DIR__ . '/../uploads/attachments';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Impossible de preparer le dossier de pieces jointes.');
    }

    $done = true;
}

function authenticate(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

function mailboxStats(int $userId): array
{
    $stmt = db()->prepare('SELECT folder, COUNT(*) AS total FROM mailbox_entries WHERE user_id = :user_id GROUP BY folder');
    $stmt->execute(['user_id' => $userId]);

    $stats = ['inbox' => 0, 'starred' => 0, 'sent' => 0, 'drafts' => 0, 'spam' => 0, 'trash' => 0];

    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['folder']] = (int) $row['total'];
    }

    return $stats;
}

function mailboxFolders(array $stats): array
{
    return [
        ['id' => 'inbox', 'label' => 'Boite de reception', 'icon' => 'inbox', 'count' => $stats['inbox']],
        ['id' => 'starred', 'label' => 'Suivis', 'icon' => 'star', 'count' => $stats['starred']],
        ['id' => 'sent', 'label' => 'Envoyes', 'icon' => 'send', 'count' => $stats['sent']],
        ['id' => 'drafts', 'label' => 'Brouillons', 'icon' => 'draft', 'count' => $stats['drafts']],
        ['id' => 'spam', 'label' => 'Indesirables', 'icon' => 'warning', 'count' => $stats['spam']],
        ['id' => 'trash', 'label' => 'Corbeille', 'icon' => 'delete', 'count' => $stats['trash']],
    ];
}

function mailboxMessages(int $userId, string $folder): array
{
    $stmt = db()->prepare('
        SELECT
            me.id AS mailbox_id,
            m.id AS message_id,
            me.folder,
            me.is_read,
            me.is_starred,
            m.subject,
            m.body,
            m.created_at,
            m.sender_name,
            m.sender_email,
            COALESCE(
                GROUP_CONCAT(CONCAT(COALESCE(mr.recipient_name, mr.recipient_email), " <", mr.recipient_email, ">") SEPARATOR ", "),
                ""
            ) AS recipients
        FROM mailbox_entries me
        INNER JOIN messages m ON m.id = me.message_id
        LEFT JOIN message_recipients mr ON mr.message_id = m.id
        WHERE me.user_id = :user_id AND me.folder = :folder
        GROUP BY me.id, me.folder, me.is_read, me.is_starred, m.id
        ORDER BY m.created_at DESC
    ');
    $stmt->execute(['user_id' => $userId, 'folder' => $folder]);

    $messages = $stmt->fetchAll();

    foreach ($messages as &$message) {
        $message['attachments'] = attachmentsForMessage((int) $message['message_id']);
    }

    return $messages;
}

function markMessageRead(int $mailboxId, int $userId): void
{
    $stmt = db()->prepare('UPDATE mailbox_entries SET is_read = 1 WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $mailboxId, 'user_id' => $userId]);
}

function toggleStar(int $mailboxId, int $userId): void
{
    $stmt = db()->prepare('
        UPDATE mailbox_entries
        SET is_starred = IF(is_starred = 1, 0, 1), folder = IF(folder = "starred", "inbox", IF(is_starred = 1, "inbox", "starred"))
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute(['id' => $mailboxId, 'user_id' => $userId]);
}

function moveMessage(int $mailboxId, int $userId, string $folder): void
{
    $allowed = ['inbox', 'sent', 'drafts', 'spam', 'trash'];

    if (!in_array($folder, $allowed, true)) {
        return;
    }

    $stmt = db()->prepare('UPDATE mailbox_entries SET folder = :folder WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['folder' => $folder, 'id' => $mailboxId, 'user_id' => $userId]);
}

function contactsForUser(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM contacts WHERE user_id = :user_id ORDER BY full_name ASC');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function eventsForUser(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE user_id = :user_id ORDER BY start_at ASC LIMIT 8');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function tasksForUser(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE user_id = :user_id ORDER BY is_done ASC, due_date ASC, id DESC');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function addContact(int $userId, string $fullName, string $email, string $phone, string $company): void
{
    $stmt = db()->prepare('INSERT INTO contacts (user_id, full_name, email, phone, company) VALUES (:user_id, :full_name, :email, :phone, :company)');
    $stmt->execute([
        'user_id' => $userId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
    ]);
}

function addEvent(int $userId, string $title, string $startAt, string $endAt, string $location): void
{
    $stmt = db()->prepare('INSERT INTO events (user_id, title, start_at, end_at, location) VALUES (:user_id, :title, :start_at, :end_at, :location)');
    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'location' => $location,
    ]);
}

function addTask(int $userId, string $title, string $dueDate): void
{
    $stmt = db()->prepare('INSERT INTO tasks (user_id, title, due_date) VALUES (:user_id, :title, :due_date)');
    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'due_date' => $dueDate !== '' ? $dueDate : null,
    ]);
}

function toggleTask(int $taskId, int $userId): void
{
    $stmt = db()->prepare('UPDATE tasks SET is_done = IF(is_done = 1, 0, 1) WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $taskId, 'user_id' => $userId]);
}

function sendMessage(int $senderId, string $senderName, string $senderEmail, string $recipientsRaw, string $subject, string $body): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, sender_name, sender_email, subject, body) VALUES (:sender_id, :sender_name, :sender_email, :subject, :body)');
        $stmt->execute([
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'subject' => $subject,
            'body' => $body,
        ]);

        $messageId = (int) $pdo->lastInsertId();
        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientsRaw))));

        $recipientInsert = $pdo->prepare('INSERT INTO message_recipients (message_id, user_id, recipient_name, recipient_email) VALUES (:message_id, :user_id, :recipient_name, :recipient_email)');
        $mailboxInsert = $pdo->prepare('INSERT INTO mailbox_entries (user_id, message_id, folder, is_read, is_starred) VALUES (:user_id, :message_id, :folder, :is_read, 0)');
        $userLookup = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = :email LIMIT 1');

        foreach ($recipients as $email) {
            $userLookup->execute(['email' => $email]);
            $recipientUser = $userLookup->fetch();

            $recipientInsert->execute([
                'message_id' => $messageId,
                'user_id' => $recipientUser['id'] ?? null,
                'recipient_name' => $recipientUser['full_name'] ?? null,
                'recipient_email' => $email,
            ]);

            if ($recipientUser) {
                $mailboxInsert->execute([
                    'user_id' => (int) $recipientUser['id'],
                    'message_id' => $messageId,
                    'folder' => 'inbox',
                    'is_read' => 0,
                ]);
            }
        }

        $mailboxInsert->execute([
            'user_id' => $senderId,
            'message_id' => $messageId,
            'folder' => 'sent',
            'is_read' => 1,
        ]);

        $pdo->commit();
        return $messageId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function storeAttachments(int $messageId, array $files): void
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return;
    }

    $uploadDir = __DIR__ . '/../uploads/attachments';
    $stmt = db()->prepare('
        INSERT INTO attachments (message_id, original_name, stored_name, file_path, mime_type, file_size)
        VALUES (:message_id, :original_name, :stored_name, :file_path, :mime_type, :file_size)
    ');

    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $originalName = (string) $files['name'][$index];
        $tmpName = (string) $files['tmp_name'][$index];
        $size = (int) $files['size'][$index];

        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('Chaque piece jointe doit faire moins de 5 Mo.');
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . strtolower($extension) : '');
        $relativePath = 'uploads/attachments/' . $storedName;
        $targetPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Impossible d enregistrer une piece jointe.');
        }

        $mimeType = mime_content_type($targetPath) ?: 'application/octet-stream';

        $stmt->execute([
            'message_id' => $messageId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => $size,
        ]);
    }
}

function attachmentsForMessage(int $messageId): array
{
    $stmt = db()->prepare('SELECT * FROM attachments WHERE message_id = :message_id ORDER BY id ASC');
    $stmt->execute(['message_id' => $messageId]);

    return $stmt->fetchAll();
}

function searchableOverview(int $userId, string $query): array
{
    if ($query === '') {
        return ['messages' => [], 'contacts' => [], 'events' => [], 'tasks' => []];
    }

    $like = '%' . $query . '%';

    $messageStmt = db()->prepare('
        SELECT me.id AS mailbox_id, m.subject, m.body, m.sender_name, m.created_at
        FROM mailbox_entries me
        INNER JOIN messages m ON m.id = me.message_id
        WHERE me.user_id = :user_id
            AND (m.subject LIKE :search OR m.body LIKE :search OR m.sender_name LIKE :search OR m.sender_email LIKE :search)
        ORDER BY m.created_at DESC
        LIMIT 8
    ');
    $messageStmt->execute(['user_id' => $userId, 'search' => $like]);

    $contactStmt = db()->prepare('
        SELECT id, full_name, email, company
        FROM contacts
        WHERE user_id = :user_id
            AND (full_name LIKE :search OR email LIKE :search OR company LIKE :search)
        ORDER BY full_name ASC
        LIMIT 8
    ');
    $contactStmt->execute(['user_id' => $userId, 'search' => $like]);

    $eventStmt = db()->prepare('
        SELECT id, title, start_at, location
        FROM events
        WHERE user_id = :user_id
            AND (title LIKE :search OR location LIKE :search)
        ORDER BY start_at ASC
        LIMIT 8
    ');
    $eventStmt->execute(['user_id' => $userId, 'search' => $like]);

    $taskStmt = db()->prepare('
        SELECT id, title, due_date, is_done
        FROM tasks
        WHERE user_id = :user_id
            AND title LIKE :search
        ORDER BY is_done ASC, due_date ASC
        LIMIT 8
    ');
    $taskStmt->execute(['user_id' => $userId, 'search' => $like]);

    return [
        'messages' => $messageStmt->fetchAll(),
        'contacts' => $contactStmt->fetchAll(),
        'events' => $eventStmt->fetchAll(),
        'tasks' => $taskStmt->fetchAll(),
    ];
}

function usersForAdmin(): array
{
    $stmt = db()->query('
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.role,
            u.created_at,
            COUNT(DISTINCT c.id) AS contacts_count,
            COUNT(DISTINCT e.id) AS events_count,
            COUNT(DISTINCT t.id) AS tasks_count
        FROM users u
        LEFT JOIN contacts c ON c.user_id = u.id
        LEFT JOIN events e ON e.user_id = u.id
        LEFT JOIN tasks t ON t.user_id = u.id
        GROUP BY u.id
        ORDER BY u.created_at ASC
    ');

    return $stmt->fetchAll();
}

function userDirectory(): array
{
    $stmt = db()->query('SELECT id, full_name, email, role FROM users ORDER BY full_name ASC');

    return $stmt->fetchAll();
}

function createUser(string $fullName, string $email, string $password, string $role): void
{
    $allowedRoles = ['Administrator', 'Manager', 'User'];

    if (!in_array($role, $allowedRoles, true)) {
        $role = 'User';
    }

    $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)');
    $stmt->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
}

function updateUserRole(int $userId, string $role): void
{
    $allowedRoles = ['Administrator', 'Manager', 'User'];

    if (!in_array($role, $allowedRoles, true)) {
        throw new RuntimeException('Role invalide.');
    }

    $stmt = db()->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute(['role' => $role, 'id' => $userId]);
}

function isAdmin(array $user): bool
{
    return ($user['role'] ?? '') === 'Administrator';
}
