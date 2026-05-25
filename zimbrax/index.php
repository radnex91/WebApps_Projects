<?php
// index.php — App Shell principal
require_once 'includes/config.php';
requireLogin();

$user     = currentUser();
$userId   = $user['id'];
$folders  = getFolders($pdo, $userId);

// Unread total
$totalUnread = array_sum(array_column($folders, 'unread_count'));

// Inbox folder id
$inboxId = 0;
foreach ($folders as $f) { if ($f['type'] === 'inbox') { $inboxId = $f['id']; break; } }

// Upcoming events (next 5)
$events = $pdo->prepare("SELECT * FROM events WHERE user_id=? AND start_datetime >= NOW() ORDER BY start_datetime LIMIT 5");
$events->execute([$userId]);
$upcomingEvents = $events->fetchAll();

// Tasks todo
$tasksCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND status != 'done'");
$tasksCount->execute([$userId]);
$pendingTasks = $tasksCount->fetchColumn();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ZimbraX — <?= sanitize($user['display_name']) ?></title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<div id="app">

  <!-- ═══ TOPBAR ═══════════════════════════════════════════ -->
  <div class="topbar">
    <div class="logo"><div class="logo-pulse"></div>ZimbraX</div>
    <div class="search-wrap">
      <span class="search-icon">⌕</span>
      <input type="text" id="global-search" placeholder="Rechercher dans les e-mails, contacts, événements..." autocomplete="off">
    </div>
    <div class="tb-right">
      <button class="btn" onclick="openCompose()">✏ Nouveau message</button>
      <div class="tb-notif btn-icon" title="Notifications">
        🔔<div class="tb-notif-badge"></div>
      </div>
      <div class="avatar-btn" style="background:<?= sanitize($user['avatar_color']) ?>;"
           title="<?= sanitize($user['display_name']) ?>" onclick="toggleUserMenu()">
        <?= initials($user['display_name']) ?>
      </div>
    </div>
  </div>

  <!-- ═══ MAIN LAYOUT ══════════════════════════════════════ -->
  <div class="main-layout">

    <!-- ═══ SIDEBAR ═══════════════════════════════════════ -->
    <div class="sidebar" id="sidebar">
      <button class="compose-btn" onclick="openCompose()">✏&nbsp; Composer</button>

      <div class="nav-section">Messagerie</div>
      <?php foreach ($folders as $f): ?>
      <div class="nav-item <?= $f['type']==='inbox'?'active':'' ?>"
           data-folder="<?= $f['id'] ?>" data-type="<?= $f['type'] ?>" data-name="<?= sanitize($f['name']) ?>"
           onclick="loadFolder(<?= $f['id'] ?>, this.dataset.name, this)">
        <span class="nav-icon"><?= $f['icon'] ?></span>
        <span class="nav-label"><?= sanitize($f['name']) ?></span>
        <?php if ($f['unread_count'] > 0): ?>
        <span class="nav-badge"><?= $f['unread_count'] ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <div class="divider" style="margin:8px 0;"></div>
      <div class="nav-section">Applications</div>
      <div class="nav-item" onclick="switchApp('calendar',this)">
        <span class="nav-icon">📅</span>
        <span class="nav-label">Calendrier</span>
      </div>
      <div class="nav-item" onclick="switchApp('contacts',this)">
        <span class="nav-icon">👥</span>
        <span class="nav-label">Contacts</span>
      </div>
      <div class="nav-item" onclick="switchApp('tasks',this)">
        <span class="nav-icon">✅</span>
        <span class="nav-label">Tâches</span>
        <?php if ($pendingTasks > 0): ?>
        <span class="nav-badge green"><?= $pendingTasks ?></span>
        <?php endif; ?>
      </div>

      <div class="sidebar-footer">
        <?php if (!empty($user['is_admin'])): ?>
        <div class="nav-item" onclick="window.location='modules/settings/#users'" style="color:var(--purple);">
          <span class="nav-icon">👥</span>
          <span class="nav-label">Utilisateurs</span>
        </div>
        <?php endif; ?>
        <div class="nav-item" onclick="window.location='modules/settings/'">
          <span class="nav-icon">⚙</span>
          <span class="nav-label">Paramètres</span>
        </div>
        <div class="nav-item" onclick="window.location='logout.php'"
             style="color:var(--red);opacity:.8;">
          <span class="nav-icon">⇠</span>
          <span class="nav-label">Déconnexion</span>
        </div>
      </div>
    </div>

    <!-- ═══ CONTENT AREA ══════════════════════════════════ -->
    <div class="content-area" id="content-area">

      <!-- EMAIL LIST -->
      <div class="email-list" id="email-list-panel">
        <div class="list-header">
          <h3 id="folder-title">Boîte de réception</h3>
          <div style="display:flex;gap:4px;align-items:center;">
            <input type="text" id="email-search" placeholder="Filtrer..." style="background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:4px 8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:11px;outline:none;width:100px;">
            <button class="btn-icon" title="Sélectionner tout" onclick="selectAllEmails()">⊞</button>
            <button class="btn-icon" title="Actualiser" onclick="refreshEmails()">↻</button>
          </div>
        </div>
        <div id="bulk-actions" style="display:none;padding:6px 10px;background:var(--bg3);border-bottom:1px solid var(--border);gap:6px;align-items:center;">
          <span id="selected-count" style="font-size:11px;color:var(--text2);margin-right:4px;"></span>
          <button class="btn btn-sm" onclick="bulkAction('read')">Marquer lu</button>
          <button class="btn btn-sm" onclick="bulkAction('delete')">Supprimer</button>
          <button class="btn btn-sm btn-ghost" onclick="clearSelection()">✕</button>
        </div>
        <div class="email-scroll" id="email-items">
          <div style="padding:20px;text-align:center;color:var(--text3);">
            <div class="loader"></div>
            <p style="margin-top:8px;font-size:12px;">Chargement...</p>
          </div>
        </div>
      </div>

      <!-- EMAIL VIEW -->
      <div class="email-view" id="email-view-panel">
        <div class="empty-view">
          <div class="empty-icon">📧</div>
          <div style="font-size:15px;font-weight:500;color:var(--text2);">Sélectionnez un e-mail</div>
          <div style="font-size:12px;">Cliquez sur un message pour le lire</div>
        </div>
      </div>
    </div>

    <!-- ═══ RIGHT PANEL ════════════════════════════════════ -->
    <div class="right-panel" id="right-panel">
      <div class="panel-tabs">
        <div class="p-tab active" onclick="switchTab('calendar',this)">📅</div>
        <div class="p-tab" onclick="switchTab('contacts',this)">👥</div>
        <div class="p-tab" onclick="switchTab('tasks',this)">✅</div>
      </div>

      <!-- CALENDAR TAB -->
      <div id="tab-calendar" class="panel-body">
        <div class="cal-wrap">
          <div class="cal-head">
            <button class="btn-icon" onclick="calPrevMonth()">‹</button>
            <div class="cal-month" id="cal-month-title"></div>
            <button class="btn-icon" onclick="calNextMonth()">›</button>
          </div>
          <div class="cal-grid" id="cal-grid-days">
            <div class="cal-dow">L</div><div class="cal-dow">M</div>
            <div class="cal-dow">M</div><div class="cal-dow">J</div>
            <div class="cal-dow">V</div><div class="cal-dow">S</div>
            <div class="cal-dow">D</div>
          </div>
        </div>
        <div class="events-section">
          <div class="events-section-title">Prochains événements</div>
          <div id="upcoming-events">
            <?php foreach ($upcomingEvents as $ev): ?>
            <div class="ev-card" onclick="editEvent(<?= $ev['id'] ?>)">
              <div class="ev-stripe" style="background:<?= sanitize($ev['color']) ?>"></div>
              <div class="ev-card-info">
                <div class="ev-card-title"><?= sanitize($ev['title']) ?></div>
                <div class="ev-card-time">
                  <?php
                  $start = new DateTime($ev['start_datetime']);
                  $now   = new DateTime();
                  $diff  = $now->diff($start);
                  if ($diff->days === 0) echo 'Auj. '.$start->format('H:i');
                  elseif ($diff->days === 1) echo 'Dem. '.$start->format('H:i');
                  else echo $start->format('d M H:i');
                  ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($upcomingEvents)): ?>
            <div style="font-size:11px;color:var(--text3);text-align:center;padding:12px;">
              Aucun événement à venir
            </div>
            <?php endif; ?>
          </div>
          <button class="btn btn-ghost btn-sm" style="width:100%;margin-top:6px;justify-content:center;" onclick="openNewEvent()">+ Nouvel événement</button>
        </div>
      </div>

      <!-- CONTACTS TAB -->
      <div id="tab-contacts" class="panel-body" style="display:none;">
        <div style="padding:8px 10px;border-bottom:1px solid var(--border);">
          <input type="text" id="contact-search" class="form-control" placeholder="Rechercher un contact..." style="font-size:12px;padding:6px 10px;" oninput="searchContacts(this.value)">
        </div>
        <div id="contacts-list"></div>
        <div style="padding:8px 10px;border-top:1px solid var(--border);">
          <button class="btn btn-sm" style="width:100%;justify-content:center;" onclick="openNewContact()">+ Nouveau contact</button>
        </div>
      </div>

      <!-- TASKS TAB -->
      <div id="tab-tasks" class="panel-body" style="display:none;">
        <div style="padding:6px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px;">
          <select id="task-list-filter" class="form-control" style="font-size:11px;padding:4px 8px;" onchange="loadTasks()">
            <option value="">Toutes les listes</option>
            <option value="Travail">Travail</option>
            <option value="Perso">Perso</option>
            <option value="Finance">Finance</option>
          </select>
        </div>
        <div id="tasks-list"></div>
        <div class="task-add-row">
          <input type="text" id="new-task-input" placeholder="Nouvelle tâche..." onkeydown="if(event.key==='Enter')addTask()">
          <button class="btn btn-primary btn-sm" onclick="addTask()">+</button>
        </div>
      </div>
    </div>

  </div><!-- .main-layout -->

  <!-- ═══ STATUS BAR ════════════════════════════════════════ -->
  <div class="statusbar">
    <div class="sb-item"><div class="sb-dot" style="background:var(--green)"></div>Connecté</div>
    <div class="sb-item"><?= sanitize($user['email']) ?></div>
    <div class="sb-item" id="sb-unread"><?= $totalUnread ?> non lu(s)</div>
    <div class="sb-item" style="margin-left:auto;" id="sb-sync">Synchronisé</div>
  </div>
</div>

<!-- ═══ COMPOSE OVERLAY ════════════════════════════════════ -->
<div class="compose-overlay" id="compose-overlay" onclick="closeComposeOnBg(event)">
  <div class="compose-modal" id="compose-modal">
    <div class="cm-head" onclick="toggleMinimize()">
      <div class="cm-title">✏ Nouveau message</div>
      <div style="display:flex;gap:4px;">
        <button class="btn-icon" onclick="event.stopPropagation();minimizeCompose()" style="font-size:12px;">–</button>
        <button class="btn-icon" onclick="event.stopPropagation();closeCompose()" style="font-size:12px;">✕</button>
      </div>
    </div>
    <div id="compose-body">
      <div class="cm-field">
        <span class="cm-lbl">À</span>
        <input type="text" id="cm-to" placeholder="Destinataires..." autocomplete="off">
        <span style="font-size:11px;color:var(--text3);cursor:pointer;" onclick="toggleCcBcc()">Cc Bcc</span>
      </div>
      <div class="cm-field" id="cc-row" style="display:none;">
        <span class="cm-lbl">Cc</span>
        <input type="text" id="cm-cc" placeholder="">
      </div>
      <div class="cm-field" id="bcc-row" style="display:none;">
        <span class="cm-lbl">Cci</span>
        <input type="text" id="cm-bcc" placeholder="">
      </div>
      <div class="cm-field">
        <span class="cm-lbl">Objet</span>
        <input type="text" id="cm-subject" placeholder="Sujet du message...">
      </div>
      <div class="cm-body">
        <textarea id="cm-body" placeholder="Rédigez votre message..."></textarea>
        <div id="compose-signature" style="border-top:1px solid var(--border);margin-top:8px;padding-top:8px;font-size:12px;color:var(--text3);">
          -- <br><?= sanitize($user['display_name']) ?><br><?= sanitize($user['email']) ?>
        </div>
      </div>
      <div class="cm-footer">
        <button class="btn btn-primary" onclick="sendEmail()">Envoyer ↗</button>
        <button class="btn" onclick="saveDraft()" title="Enregistrer brouillon">📝</button>
        <button class="btn" onclick="document.getElementById('attach-input').click()" title="Pièce jointe">📎</button>
        <input type="file" id="attach-input" multiple style="display:none" onchange="handleAttachments(this)">
        <div id="attach-list" style="font-size:11px;color:var(--text2);"></div>
        <button class="btn btn-ghost" style="margin-left:auto;color:var(--red);" onclick="closeCompose()">🗑</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ EVENT MODAL ════════════════════════════════════════ -->
<div class="modal-backdrop" id="event-modal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="event-modal-title">Nouvel événement</h3>
      <button class="btn-icon" onclick="closeModal('event-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ev-id">
      <div class="form-group">
        <label>Titre *</label>
        <input type="text" id="ev-title" class="form-control" placeholder="Titre de l'événement...">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Date/heure début</label>
          <input type="datetime-local" id="ev-start" class="form-control">
        </div>
        <div class="form-group">
          <label>Date/heure fin</label>
          <input type="datetime-local" id="ev-end" class="form-control">
        </div>
      </div>
      <div class="form-group">
        <label>Lieu</label>
        <input type="text" id="ev-location" class="form-control" placeholder="Adresse, lien visio...">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea id="ev-desc" class="form-control" rows="3" placeholder="Détails..."></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Couleur</label>
          <input type="color" id="ev-color" class="form-control" value="#4f8ef7" style="height:38px;cursor:pointer;">
        </div>
        <div class="form-group">
          <label>Catégorie</label>
          <select id="ev-category" class="form-control">
            <option value="work">Travail</option>
            <option value="personal">Personnel</option>
            <option value="training">Formation</option>
            <option value="other">Autre</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-danger btn-sm" id="ev-delete-btn" onclick="deleteEvent()" style="display:none;">Supprimer</button>
      <button class="btn" onclick="closeModal('event-modal')">Annuler</button>
      <button class="btn btn-primary" onclick="saveEvent()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ═══ CONTACT MODAL ═════════════════════════════════════ -->
<div class="modal-backdrop" id="contact-modal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="contact-modal-title">Nouveau contact</h3>
      <button class="btn-icon" onclick="closeModal('contact-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ct-id">
      <div class="form-row">
        <div class="form-group"><label>Prénom *</label><input type="text" id="ct-firstname" class="form-control"></div>
        <div class="form-group"><label>Nom</label><input type="text" id="ct-lastname" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>E-mail</label><input type="email" id="ct-email" class="form-control"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" id="ct-phone" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Société</label><input type="text" id="ct-company" class="form-control"></div>
        <div class="form-group"><label>Fonction</label><input type="text" id="ct-job" class="form-control"></div>
      </div>
      <div class="form-group"><label>Notes</label><textarea id="ct-notes" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-danger btn-sm" id="ct-delete-btn" onclick="deleteContact()" style="display:none;">Supprimer</button>
      <button class="btn" onclick="closeModal('contact-modal')">Annuler</button>
      <button class="btn btn-primary" onclick="saveContact()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ═══ FLASH ══════════════════════════════════════════════ -->
<?php if ($flash): ?>
<div id="flash-msg" class="alert alert-<?= $flash['type'] ?>"
     style="position:fixed;bottom:30px;right:20px;z-index:999;min-width:280px;box-shadow:0 4px 20px rgba(0,0,0,.3);">
  <?= $flash['type']==='success'?'✓':'⚠' ?> <?= sanitize($flash['msg']) ?>
</div>
<script>setTimeout(()=>{const f=document.getElementById('flash-msg');if(f){f.style.opacity='0';f.style.transition='opacity .5s';setTimeout(()=>f.remove(),500);}},3500);</script>
<?php endif; ?>

<script src="js/app.js"></script>
<script>
// Pass PHP data to JS
const APP_CONFIG = {
  baseUrl: '<?= BASE_URL ?>',
  userId: <?= $userId ?>,
  inboxId: <?= $inboxId ?>,
  userName: '<?= sanitize($user['display_name']) ?>',
  userEmail: '<?= sanitize($user['email']) ?>'
};
</script>
</body>
</html>
