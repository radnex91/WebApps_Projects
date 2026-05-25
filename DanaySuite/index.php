<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app.php';

ensureSetupComplete();
$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $action = post('action');

        if ($action === 'send_message') {
            $recipients = post('recipients');
            $subject = post('subject');
            $body = post('body');

            if ($recipients === '' || $subject === '' || $body === '') {
                throw new RuntimeException('Tous les champs du message sont obligatoires.');
            }

            $latestMessageId = sendMessage((int) $user['id'], $user['full_name'], $user['email'], $recipients, $subject, $body);
            storeAttachments($latestMessageId, $_FILES['attachments'] ?? []);
            flash('success', 'Message envoye avec succes.');
        }

        if ($action === 'add_contact') {
            if (post('full_name') === '' || post('email') === '') {
                throw new RuntimeException('Le nom et l email du contact sont requis.');
            }

            addContact((int) $user['id'], post('full_name'), post('email'), post('phone'), post('company'));
            flash('success', 'Contact enregistre.');
        }

        if ($action === 'add_event') {
            if (post('title') === '' || post('start_at') === '' || post('end_at') === '') {
                throw new RuntimeException('Le titre, le debut et la fin sont requis pour un evenement.');
            }

            $startAt = normalizeDateTimeInput(post('start_at'));
            $endAt = normalizeDateTimeInput(post('end_at'));

            if ($endAt < $startAt) {
                throw new RuntimeException('La fin de l evenement doit etre posterieure au debut.');
            }

            addEvent((int) $user['id'], post('title'), $startAt, $endAt, post('location'));
            flash('success', 'Evenement ajoute au calendrier.');
        }

        if ($action === 'add_task') {
            if (post('title') === '') {
                throw new RuntimeException('Le titre de la tache est requis.');
            }

            addTask((int) $user['id'], post('title'), post('due_date'));
            flash('success', 'Tache ajoutee.');
        }

        if ($action === 'toggle_task') {
            toggleTask((int) post('task_id'), (int) $user['id']);
            flash('success', 'Etat de la tache mis a jour.');
        }

        if ($action === 'move_message') {
            moveMessage((int) post('mailbox_id'), (int) $user['id'], post('target_folder'));
            flash('success', 'Message deplace.');
        }

        if ($action === 'toggle_star') {
            toggleStar((int) post('mailbox_id'), (int) $user['id']);
            flash('success', 'Statut du message mis a jour.');
        }

        if ($action === 'create_user') {
            if (!isAdmin($user)) {
                throw new RuntimeException('Action reservee a l administration.');
            }

            if (post('full_name') === '' || post('email') === '' || post('password') === '') {
                throw new RuntimeException('Nom, email et mot de passe sont requis pour creer un utilisateur.');
            }

            createUser(post('full_name'), post('email'), post('password'), post('role', 'User'));
            flash('success', 'Utilisateur cree.');
        }

        if ($action === 'update_user_role') {
            if (!isAdmin($user)) {
                throw new RuntimeException('Action reservee a l administration.');
            }

            updateUserRole((int) post('user_id'), post('role'));
            flash('success', 'Role utilisateur mis a jour.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    $params = ['folder' => (string) ($_GET['folder'] ?? 'inbox')];

    if (!empty($_GET['q'])) {
        $params['q'] = (string) $_GET['q'];
    }

    redirect('index.php?' . http_build_query($params));
}

$folder = $_GET['folder'] ?? 'inbox';
$allowedFolders = ['inbox', 'starred', 'sent', 'drafts', 'spam', 'trash'];

if (!in_array($folder, $allowedFolders, true)) {
    $folder = 'inbox';
}

$search = trim((string) ($_GET['q'] ?? ''));
$stats = mailboxStats((int) $user['id']);
$folders = mailboxFolders($stats);
$messages = mailboxMessages((int) $user['id'], $folder);
$selectedId = isset($_GET['message']) ? (int) $_GET['message'] : ($messages[0]['mailbox_id'] ?? 0);
$primaryMessage = null;

foreach ($messages as $message) {
    if ((int) $message['mailbox_id'] === $selectedId) {
        $primaryMessage = $message;
        break;
    }
}

if ($primaryMessage === null && !empty($messages)) {
    $primaryMessage = $messages[0];
    $selectedId = (int) $primaryMessage['mailbox_id'];
}

if ($primaryMessage !== null && (int) $primaryMessage['is_read'] === 0) {
    markMessageRead((int) $primaryMessage['mailbox_id'], (int) $user['id']);
    $primaryMessage['is_read'] = 1;
}

$contacts = contactsForUser((int) $user['id']);
$events = eventsForUser((int) $user['id']);
$tasks = tasksForUser((int) $user['id']);
$directory = userDirectory();
$adminUsers = isAdmin($user) ? usersForAdmin() : [];
$searchResults = searchableOverview((int) $user['id'], $search);
$flash = consumeFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="sidebar-inner">
                <div class="brand">
                    <div class="brand-mark">D</div>
                    <div class="brand-text">
                        <strong><?php echo e(APP_NAME); ?></strong>
                        <span>Messagerie collaborative</span>
                    </div>
                </div>

                <a href="#composer" class="compose-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Nouveau message
                </a>

                <nav class="sidebar-nav">
                    <span class="sidebar-label">Navigation</span>
                    <a href="#composer" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Composer
                    </a>
                    <a href="#mail-zone" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        Messages
                    </a>
                    <a href="#workspace-zone" class="sidebar-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Mon espace
                    </a>
                    <?php if (isAdmin($user)): ?>
                        <a href="#admin-zone" class="sidebar-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                            Administration
                        </a>
                    <?php endif; ?>
                </nav>

                <nav>
                    <span class="sidebar-label">Dossiers</span>
                    <div class="folder-list">
                        <?php
                        $folderIcons = [
                            'inbox' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
                            'starred' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
                            'sent' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
                            'drafts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
                            'spam' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                            'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
                        ];
                        ?>
                        <?php foreach ($folders as $item): ?>
                            <a class="folder-item <?php echo $folder === $item['id'] ? 'active' : ''; ?>" href="?folder=<?php echo e($item['id']); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                <?php echo $folderIcons[$item['id']] ?? ''; ?>
                                <span><?php echo e($item['label']); ?></span>
                                <span class="folder-count"><?php echo e((string) $item['count']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <div class="sidebar-bottom">
                    <div class="team-list">
                        <?php foreach (array_slice($directory, 0, 4) as $member): ?>
                            <div class="team-member">
                                <span class="user-avatar"><?php echo e(initials($member['full_name'])); ?></span>
                                <div>
                                    <strong><?php echo e($member['full_name']); ?></strong>
                                    <small><?php echo e($member['role']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="user-card">
                        <span class="user-avatar"><?php echo e(initials($user['full_name'])); ?></span>
                        <div class="user-info">
                            <strong><?php echo e($user['full_name']); ?></strong>
                            <span><?php echo e($user['role']); ?></span>
                        </div>
                        <a href="logout.php" class="logout-link" title="Se deconnecter">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-app">
            <header class="topbar">
                <div class="topbar-left">
                    <p class="eyebrow">Tableau de bord</p>
                    <h1>Espace collaboratif</h1>
                </div>
                <div class="topbar-right">
                    <form method="get" class="search-wrapper">
                        <input type="hidden" name="folder" value="<?php echo e($folder); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Rechercher messages, contacts, agenda...">
                    </form>
                    <a href="setup.php" class="setup-link" title="Installation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    </a>
                    <div class="topbar-profile">
                        <span class="user-avatar"><?php echo e(initials($user['full_name'])); ?></span>
                        <div>
                            <strong><?php echo e($user['full_name']); ?></strong>
                            <small><?php echo e($user['email']); ?></small>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="flash flash-<?php echo e($flash['type']); ?>">
                    <span><?php echo e($flash['message']); ?></span>
                </div>
            <?php endif; ?>

            <section class="hero-grid">
                <article class="hero-card hero-primary">
                    <div class="hero-copy">
                        <p class="eyebrow">Zimbra modernise</p>
                        <h2>Multi-utilisateurs, recherche globale, administration et pieces jointes.</h2>
                        <p>Les messages internes sont distribues entre comptes connus. Les administrateurs peuvent creer des comptes et piloter les roles.</p>
                    </div>
                    <div class="hero-stats">
                        <?php foreach ($stats as $key => $value): ?>
                            <div class="stat-box">
                                <span><?php echo e(strtoupper($key)); ?></span>
                                <strong><?php echo e((string) $value); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="hero-card hero-secondary">
                    <div class="panel-head">
                        <h3>Vue rapide</h3>
                        <span><?php echo e((string) count($directory)); ?> collaborateurs</span>
                    </div>
                    <div class="overview-grid">
                        <div class="overview-item">
                            <strong><?php echo e((string) count($events)); ?></strong>
                            <span>Evenements</span>
                        </div>
                        <div class="overview-item">
                            <strong><?php echo e((string) count($contacts)); ?></strong>
                            <span>Contacts</span>
                        </div>
                        <div class="overview-item">
                            <strong><?php echo e((string) count($tasks)); ?></strong>
                            <span>Taches</span>
                        </div>
                        <div class="overview-item">
                            <strong><?php echo e((string) count($adminUsers ?: $directory)); ?></strong>
                            <span>Utilisateurs</span>
                        </div>
                    </div>
                </article>
            </section>

            <?php if ($search !== ''): ?>
                <section class="panel search-panel">
                    <div class="panel-head">
                        <h3>Resultats de recherche</h3>
                        <span><?php echo e($search); ?></span>
                    </div>
                    <div class="search-grid">
                        <div class="search-block">
                            <h4>Messages</h4>
                            <?php if ($searchResults['messages']): ?>
                                <?php foreach ($searchResults['messages'] as $item): ?>
                                    <a class="search-item" href="?folder=<?php echo e($folder); ?>&message=<?php echo e((string) $item['mailbox_id']); ?>&q=<?php echo urlencode($search); ?>">
                                        <strong><?php echo e($item['subject']); ?></strong>
                                        <span><?php echo e($item['sender_name']); ?> - <?php echo e(formatDateTime($item['created_at'])); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucun message trouve.</p>
                            <?php endif; ?>
                        </div>
                        <div class="search-block">
                            <h4>Contacts</h4>
                            <?php if ($searchResults['contacts']): ?>
                                <?php foreach ($searchResults['contacts'] as $item): ?>
                                    <div class="search-item">
                                        <strong><?php echo e($item['full_name']); ?></strong>
                                        <span><?php echo e($item['email']); ?><?php echo $item['company'] ? ' - ' . e($item['company']) : ''; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucun contact trouve.</p>
                            <?php endif; ?>
                        </div>
                        <div class="search-block">
                            <h4>Agenda</h4>
                            <?php if ($searchResults['events']): ?>
                                <?php foreach ($searchResults['events'] as $item): ?>
                                    <div class="search-item">
                                        <strong><?php echo e($item['title']); ?></strong>
                                        <span><?php echo e(formatEventTime($item['start_at'])); ?><?php echo $item['location'] ? ' - ' . e($item['location']) : ''; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucun evenement trouve.</p>
                            <?php endif; ?>
                        </div>
                        <div class="search-block">
                            <h4>Taches</h4>
                            <?php if ($searchResults['tasks']): ?>
                                <?php foreach ($searchResults['tasks'] as $item): ?>
                                    <div class="search-item">
                                        <strong><?php echo e($item['title']); ?></strong>
                                        <span><?php echo $item['due_date'] ? e($item['due_date']) : 'Sans echeance'; ?> - <?php echo (int) $item['is_done'] === 1 ? 'Terminee' : 'Ouverte'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucune tache trouvee.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel compose-panel" id="composer">
                <form method="post" class="compose-form" enctype="multipart/form-data">
                    <div class="panel-head">
                        <h3>Nouveau message</h3>
                        <button type="button" class="action-btn compose-close" onclick="document.getElementById('composer').classList.remove('compose-visible')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Fermer
                        </button>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="send_message">
                    <div class="compose-fields">
                        <input type="text" name="recipients" placeholder="Destinataires (emails separes par des virgules)" required>
                        <input type="text" name="subject" placeholder="Objet du message" required>
                        <textarea name="body" rows="6" placeholder="Redigez votre message..." required></textarea>
                        <div class="compose-actions">
                            <label class="attach-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                <span>Joindre un fichier</span>
                                <input type="file" name="attachments[]" multiple>
                            </label>
                            <button type="submit" class="compose-btn">Envoyer le message</button>
                        </div>
                    </div>
                </form>
            </section>

            <script>
            document.querySelectorAll('[href="#composer"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('composer').classList.add('compose-visible');
                });
            });
            if (window.location.hash === '#composer') {
                document.getElementById('composer').classList.add('compose-visible');
            }
            </script>

            <section class="content-grid">
                <section class="mail-column panel" id="mail-zone">
                    <div class="panel-head">
                        <h3>Messages <?php echo e($folder); ?></h3>
                        <span><?php echo e((string) count($messages)); ?> elements</span>
                    </div>

                    <div class="message-list">
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $message): ?>
                                <a class="message-card <?php echo (int) $message['mailbox_id'] === $selectedId ? 'selected' : ''; ?> <?php echo (int) $message['is_read'] === 0 ? 'unread' : ''; ?>" href="?folder=<?php echo e($folder); ?>&message=<?php echo e((string) $message['mailbox_id']); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                    <span class="message-avatar"><?php echo e(initials($message['sender_name'])); ?></span>
                                    <div class="message-content">
                                        <div class="message-sender">
                                            <strong><?php echo e($message['sender_name']); ?></strong>
                                            <time><?php echo e(formatDateTime($message['created_at'])); ?></time>
                                        </div>
                                        <h4><?php echo e($message['subject']); ?></h4>
                                        <p><?php echo e(excerpt($message['body'])); ?></p>
                                    </div>
                                    <span class="message-tag"><?php echo count($message['attachments']) > 0 ? count($message['attachments']) . ' pj' : ((int) $message['is_starred'] === 1 ? 'Suivi' : ucfirst($message['folder'])); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">Aucun message dans ce dossier.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="reader-column panel">
                    <?php if ($primaryMessage): ?>
                        <div class="reader-head">
                            <div>
                                <span class="eyebrow">Lecture</span>
                                <h3><?php echo e($primaryMessage['subject']); ?></h3>
                            </div>
                            <div class="action-row">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="toggle_star">
                                    <input type="hidden" name="mailbox_id" value="<?php echo e((string) $primaryMessage['mailbox_id']); ?>">
                                    <button type="submit" class="action-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        <?php echo (int) $primaryMessage['is_starred'] === 1 ? 'Retirer' : 'Suivre'; ?>
                                    </button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="move_message">
                                    <input type="hidden" name="mailbox_id" value="<?php echo e((string) $primaryMessage['mailbox_id']); ?>">
                                    <input type="hidden" name="target_folder" value="trash">
                                    <button type="submit" class="action-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Corbeille
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="reader-meta">
                            <span class="user-avatar"><?php echo e(initials($primaryMessage['sender_name'])); ?></span>
                            <div class="sender-info">
                                <strong><?php echo e($primaryMessage['sender_name']); ?></strong>
                                <span><?php echo e($primaryMessage['sender_email']); ?></span>
                            </div>
                            <div class="reader-badges">
                                <span class="badge"><?php echo e($primaryMessage['recipients'] !== '' ? $primaryMessage['recipients'] : 'Sans destinataire'); ?></span>
                                <span class="badge muted"><?php echo e(formatDateTime($primaryMessage['created_at'])); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($primaryMessage['attachments'])): ?>
                            <div class="attachment-list">
                                <?php foreach ($primaryMessage['attachments'] as $attachment): ?>
                                    <a class="attachment-item" href="<?php echo e($attachment['file_path']); ?>" target="_blank" rel="noreferrer">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                        <strong><?php echo e($attachment['original_name']); ?></strong>
                                        <span><?php echo e(formatBytes((int) $attachment['file_size'])); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <article class="reader-body"><?php echo nl2br(e($primaryMessage['body'])); ?></article>
                    <?php else: ?>
                        <div class="empty-reader">
                            <h3>Aucun message selectionne</h3>
                            <p>Choisissez un message dans la liste ou envoyez-en un nouveau.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="side-column" id="workspace-zone">
                    <section class="panel compact-panel">
                        <div class="panel-head">
                            <h3>Agenda</h3>
                            <span><?php echo e((string) count($events)); ?></span>
                        </div>
                        <div class="agenda-list">
                            <?php if ($events): ?>
                                <?php foreach (array_slice($events, 0, 5) as $event): ?>
                                    <div class="agenda-item">
                                        <time><?php echo e(formatEventTime($event['start_at'])); ?></time>
                                        <div>
                                            <span><?php echo e($event['title']); ?></span>
                                            <small><?php echo e($event['location'] ?: 'Sans lieu'); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucun evenement programme.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="panel compact-panel">
                        <div class="panel-head">
                            <h3>Contacts</h3>
                            <span><?php echo e((string) count($contacts)); ?></span>
                        </div>
                        <div class="contact-list">
                            <?php if ($contacts): ?>
                                <?php foreach (array_slice($contacts, 0, 5) as $contact): ?>
                                    <div class="contact-card">
                                        <span class="user-avatar"><?php echo e(initials($contact['full_name'])); ?></span>
                                        <div>
                                            <strong><?php echo e($contact['full_name']); ?></strong>
                                            <p><?php echo e($contact['company'] ?: $contact['email']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucun contact pour le moment.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="panel compact-panel">
                        <div class="panel-head">
                            <h3>Taches</h3>
                            <span><?php echo e((string) count($tasks)); ?></span>
                        </div>
                        <div class="task-list">
                            <?php if ($tasks): ?>
                                <?php foreach (array_slice($tasks, 0, 5) as $task): ?>
                                    <form method="post" class="task-item <?php echo (int) $task['is_done'] === 1 ? 'done' : ''; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="toggle_task">
                                        <input type="hidden" name="task_id" value="<?php echo e((string) $task['id']); ?>">
                                        <input type="checkbox" <?php echo (int) $task['is_done'] === 1 ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <span><?php echo e($task['title']); ?><?php echo $task['due_date'] ? ' - ' . e($task['due_date']) : ''; ?></span>
                                    </form>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-state">Aucune tache.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </aside>
            </section>

            <?php if (isAdmin($user)): ?>
                <section class="panel admin-panel" id="admin-zone">
                    <div class="panel-head">
                        <h3>Administration</h3>
                        <span>Gestion multi-utilisateurs</span>
                    </div>
                    <div class="admin-grid">
                        <form method="post" class="stack-form">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="create_user">
                            <h4>Nouveau compte</h4>
                            <input type="text" name="full_name" placeholder="Nom complet" required>
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="password" name="password" placeholder="Mot de passe" required>
                            <select name="role" class="admin-select">
                                <option value="User">User</option>
                                <option value="Manager">Manager</option>
                                <option value="Administrator">Administrator</option>
                            </select>
                            <button type="submit" class="compose-btn">Creer utilisateur</button>
                        </form>

                        <div class="admin-users">
                            <?php foreach ($adminUsers as $adminUser): ?>
                                <form method="post" class="admin-user-card">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="update_user_role">
                                    <input type="hidden" name="user_id" value="<?php echo e((string) $adminUser['id']); ?>">
                                    <div>
                                        <strong><?php echo e($adminUser['full_name']); ?></strong>
                                        <p><?php echo e($adminUser['email']); ?></p>
                                    </div>
                                    <div class="admin-user-meta">
                                        <span><?php echo e((string) $adminUser['contacts_count']); ?> contacts</span>
                                        <span><?php echo e((string) $adminUser['events_count']); ?> events</span>
                                        <span><?php echo e((string) $adminUser['tasks_count']); ?> tasks</span>
                                    </div>
                                    <div class="admin-user-actions">
                                        <select name="role" class="admin-select">
                                            <option value="User" <?php echo $adminUser['role'] === 'User' ? 'selected' : ''; ?>>User</option>
                                            <option value="Manager" <?php echo $adminUser['role'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="Administrator" <?php echo $adminUser['role'] === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                        <button type="submit" class="action-btn">Mettre a jour</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
