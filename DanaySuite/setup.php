<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$status = null;
$errors = [];

try {
    db()->query('SELECT 1 FROM users LIMIT 1');
    $status = 'L application est deja installee.';
} catch (Throwable $exception) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        try {
            $server = dbServer();
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $schema = file_get_contents(__DIR__ . '/database/schema.sql');

            if ($schema === false) {
                throw new RuntimeException('Impossible de lire le schema SQL.');
            }

            $pdo = db();
            $pdo->exec($schema);

            $exists = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

            if ($exists === 0) {
                $userStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $seedUsers = [
                    ['Danay Admin', 'admin@danaysuite.local', 'admin123', 'Administrator'],
                    ['Amina Okoro', 'amina.okoro@danaysuite.local', 'amina123', 'Manager'],
                    ['Kevin Mendy', 'kevin.mendy@danaysuite.local', 'kevin123', 'User'],
                ];
                $userIds = [];

                foreach ($seedUsers as [$fullName, $email, $password, $role]) {
                    $userStmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                    $userIds[$email] = (int) $pdo->lastInsertId();
                }

                $userId = $userIds['admin@danaysuite.local'];

                $contacts = [
                    ['Amina Okoro', 'amina.okoro@danaysuite.local', '+234 801 555 2001', 'Danay Commercial'],
                    ['Kevin Mendy', 'kevin.mendy@danaysuite.local', '+221 77 100 2234', 'Support N2'],
                    ['Sarah Lawson', 'sarah.lawson@danaysuite.local', '+44 20 7946 1000', 'Finance Europe'],
                ];

                $contactStmt = $pdo->prepare('INSERT INTO contacts (user_id, full_name, email, phone, company) VALUES (?, ?, ?, ?, ?)');
                foreach ($contacts as $contact) {
                    $contactStmt->execute(array_merge([$userId], $contact));
                }

                $events = [
                    ['Daily ops', '2026-04-09 09:00:00', '2026-04-09 09:30:00', 'Salle Atlas'],
                    ['Point produit', '2026-04-09 11:30:00', '2026-04-09 12:30:00', 'Visio interne'],
                    ['Client premium', '2026-04-09 15:00:00', '2026-04-09 16:00:00', 'Danay Holding'],
                ];

                $eventStmt = $pdo->prepare('INSERT INTO events (user_id, title, start_at, end_at, location) VALUES (?, ?, ?, ?, ?)');
                foreach ($events as $event) {
                    $eventStmt->execute(array_merge([$userId], $event));
                }

                $tasks = [
                    ['Valider le module calendrier', '2026-04-10', 0],
                    ['Relancer le fournisseur SMS', '2026-04-09', 0],
                    ['Nettoyer les dossiers partages', '2026-04-08', 1],
                ];

                $taskStmt = $pdo->prepare('INSERT INTO tasks (user_id, title, due_date, is_done) VALUES (?, ?, ?, ?)');
                foreach ($tasks as $task) {
                    $taskStmt->execute(array_merge([$userId], $task));
                }

                $messageStmt = $pdo->prepare('INSERT INTO messages (sender_id, sender_name, sender_email, subject, body, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                $recipientStmt = $pdo->prepare('INSERT INTO message_recipients (message_id, user_id, recipient_name, recipient_email) VALUES (?, ?, ?, ?)');
                $mailboxStmt = $pdo->prepare('INSERT INTO mailbox_entries (user_id, message_id, folder, is_read, is_starred) VALUES (?, ?, ?, ?, ?)');

                $seedMessages = [
                    ['Direction Generale', 'direction@danaysuite.local', 'Reunion strategique du vendredi', "Bonjour equipe,\n\nMerci de preparer les points de suivi produit, support et facturation avant 16h.\n\nCordialement,\nDirection Generale", '2026-04-09 08:45:00', 'inbox', 0, 1],
                    ['Support VIP', 'support@danaysuite.local', 'Ticket #4821 - Synchronisation mobile', "Bonjour,\n\nLe client indique que la boite se synchronise correctement sur le webmail, mais plus dans l application mobile.\n\nMerci de verifier la configuration ActiveSync.\n\nSupport VIP", '2026-04-09 07:18:00', 'inbox', 0, 0],
                    ['Finance', 'finance@danaysuite.local', 'Validation de la facture fournisseur', "Bonjour,\n\nPeux-tu confirmer la validation de la facture hebergement pour enclencher le paiement dans les delais ?\n\nMerci,\nEquipe Finance", '2026-04-08 16:20:00', 'inbox', 1, 0],
                ];

                foreach ($seedMessages as $item) {
                    $messageStmt->execute([null, $item[0], $item[1], $item[2], $item[3], $item[4]]);
                    $messageId = (int) $pdo->lastInsertId();
                    $recipientStmt->execute([$messageId, $userId, 'Danay Admin', 'admin@danaysuite.local']);
                    $mailboxStmt->execute([$userId, $messageId, $item[5], $item[6], $item[7]]);
                }

                $messageStmt->execute([
                    $userIds['admin@danaysuite.local'],
                    'Danay Admin',
                    'admin@danaysuite.local',
                    'Bienvenue dans DanaySuite Mail',
                    "Bonjour Amina,\n\nTon compte multi-utilisateur est pret. Tu peux maintenant recevoir des messages internes et utiliser le calendrier partage.\n\nDanay Admin",
                    '2026-04-09 10:00:00',
                ]);
                $messageId = (int) $pdo->lastInsertId();
                $recipientStmt->execute([$messageId, $userIds['amina.okoro@danaysuite.local'], 'Amina Okoro', 'amina.okoro@danaysuite.local']);
                $mailboxStmt->execute([$userIds['amina.okoro@danaysuite.local'], $messageId, 'inbox', 0, 0]);
                $mailboxStmt->execute([$userIds['admin@danaysuite.local'], $messageId, 'sent', 1, 0]);
            }

            flash('success', 'Installation terminee. Vous pouvez maintenant vous connecter.');
            redirect('login.php');
        } catch (Throwable $installException) {
            $errors[] = $installException->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-card setup-shell">
            <div class="brand">
                <div class="brand-mark">D</div>
                <div class="brand-text">
                    <strong><?php echo e(APP_NAME); ?></strong>
                    <span>Installation XAMPP</span>
                </div>
            </div>

            <h1>Installation</h1>
            <p>Cette etape cree la base MySQL, les tables et le compte demo.</p>

            <?php if ($status): ?>
                <div class="flash flash-success">
                    <span><?php echo e($status); ?></span>
                </div>
                <a href="login.php" class="compose-btn auth-submit" style="text-decoration:none;">Aller a la connexion</a>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="flash flash-error">
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endforeach; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="compose-btn auth-submit">Installer l'application</button>
                </form>

                <div class="auth-tip">
                    Parametres par defaut: base <?php echo e(DB_NAME); ?>, utilisateur MySQL <?php echo e(DB_USER); ?>.
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
