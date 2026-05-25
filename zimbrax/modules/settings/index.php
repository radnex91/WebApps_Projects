<?php
// modules/settings/index.php
require_once '../../includes/config.php';
requireLogin();
$user   = currentUser();
$uid    = $user['id'];
$pageTitle = 'Paramètres';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $dn  = trim($_POST['display_name'] ?? '');
        $sig = trim($_POST['signature'] ?? '');
        $col = trim($_POST['avatar_color'] ?? '#4f8ef7');
        $tz  = trim($_POST['timezone'] ?? 'Africa/Douala');
        if ($dn) {
            $pdo->prepare("UPDATE users SET display_name=?,signature=?,avatar_color=?,timezone=? WHERE id=?")
                ->execute([$dn, $sig, $col, $tz, $uid]);
            // Refresh session
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$uid]);
            $u = $stmt->fetch();
            unset($u['password'], $u['mail_password']);
            $_SESSION['user'] = $u;
            flash('Profil mis à jour.');
        }
    }
    elseif ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?"); $stmt->execute([$uid]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($old, $hash)) { flash('Ancien mot de passe incorrect.', 'danger'); }
        elseif ($new !== $cfm) { flash('Les mots de passe ne correspondent pas.', 'danger'); }
        elseif (strlen($new) < 6) { flash('Mot de passe trop court (min. 6 car.)', 'danger'); }
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            flash('Mot de passe modifié.');
        }
    }
    elseif ($action === 'mail_server') {
        $pdo->prepare("UPDATE users SET imap_host=?,imap_port=?,smtp_host=?,smtp_port=?,mail_password=? WHERE id=?")
            ->execute([trim($_POST['imap_host']??''), (int)($_POST['imap_port']??993),
                       trim($_POST['smtp_host']??''), (int)($_POST['smtp_port']??587),
                       trim($_POST['mail_password']??''), $uid]);
        flash('Serveur mail configuré.');
    }
    redirect(BASE_URL . 'modules/settings/');
}

$flash = getFlash();
$stmt  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$uid]);
$user  = $stmt->fetch();

// Fetch all users for admin section
$allUsers = [];
if (!empty($user['is_admin'])) {
    $stmtU = $pdo->query("SELECT id, username, email, display_name, avatar_color, is_admin, active, created_at, last_login FROM users ORDER BY id");
    $allUsers = $stmtU->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Paramètres — ZimbraX</title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
<style>
.settings-layout{display:flex;min-height:100vh;background:var(--bg);}
.settings-sidebar{width:200px;background:var(--bg2);border-right:1px solid var(--border);padding:20px 0;}
.s-nav-item{padding:9px 20px;cursor:pointer;color:var(--text2);transition:all .12s;font-size:13px;display:flex;align-items:center;gap:8px;}
.s-nav-item:hover{background:var(--bg3);color:var(--text);}
.s-nav-item.active{background:var(--blue-d);color:var(--blue);}
.settings-content{flex:1;padding:32px;max-width:900px;overflow-y:auto;max-height:100vh;}
.section-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:20px;overflow:hidden;}
.section-head{padding:14px 20px;border-bottom:1px solid var(--border);font-weight:500;font-size:14px;display:flex;align-items:center;gap:8px;}
.section-body{padding:20px;}
.user-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);}
.user-row:last-child{border-bottom:none;}
.user-av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0;}
.user-info{flex:1;min-width:0;}
.user-name{font-size:13px;font-weight:500;color:var(--text);}
.user-email{font-size:11px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.user-meta{font-size:10px;color:var(--text3);margin-top:2px;}
.user-actions{display:flex;gap:6px;flex-shrink:0;}
.badge-admin{background:var(--purple-d);color:var(--purple);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;}
.badge-inactive{background:var(--red-d);color:var(--red);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);width:90%;max-width:480px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.5);}
.modal-box .modal-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.modal-box .modal-head h3{font-size:15px;font-weight:500;}
.modal-box .modal-body{padding:20px;}
.modal-box .modal-foot{padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;}
</style>
</head>
<body>
<div class="settings-layout">
  <div class="settings-sidebar">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);margin-bottom:8px;">
      <div class="logo" style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:15px;color:var(--blue);">⚙ Paramètres</div>
    </div>
    <div class="s-nav-item active" onclick="showSection('profile',this)">👤 Profil</div>
    <div class="s-nav-item" onclick="showSection('password',this)">🔒 Sécurité</div>
    <div class="s-nav-item" onclick="showSection('mail',this)">📧 Serveur Mail</div>
    <div class="s-nav-item" onclick="showSection('folders',this)">📁 Dossiers</div>
    <?php if (!empty($user['is_admin'])): ?>
    <div class="s-nav-item" onclick="showSection('users',this)">👥 Utilisateurs</div>
    <?php endif; ?>
    <div class="s-nav-item" onclick="showSection('about',this)">ℹ À propos</div>
    <div style="margin-top:16px;padding:10px 12px;">
      <a href="<?= BASE_URL ?>index.php" class="btn btn-sm" style="width:100%;justify-content:center;">← Retour</a>
    </div>
  </div>

  <div class="settings-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- PROFILE -->
    <div id="sec-profile">
      <div class="section-card">
        <div class="section-head">👤 Informations du profil</div>
        <div class="section-body">
          <form method="POST">
            <input type="hidden" name="action" value="profile">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
              <div id="av-preview" style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#fff;background:<?= sanitize($user['avatar_color'] ?? '#4f8ef7') ?>;">
                <?= initials($user['display_name'] ?? 'U') ?>
              </div>
              <div>
                <div style="font-size:13px;font-weight:500;"><?= sanitize($user['display_name'] ?? '') ?></div>
                <div style="font-size:12px;color:var(--text3);"><?= sanitize($user['email'] ?? '') ?></div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Nom affiché</label>
                <input type="text" name="display_name" class="form-control" value="<?= sanitize($user['display_name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Couleur avatar</label>
                <input type="color" name="avatar_color" class="form-control" value="<?= sanitize($user['avatar_color'] ?? '#4f8ef7') ?>" style="height:40px;cursor:pointer;" onchange="document.getElementById('av-preview').style.background=this.value">
              </div>
            </div>
            <div class="form-group">
              <label>Fuseau horaire</label>
              <select name="timezone" class="form-control">
                <?php foreach (['Africa/Douala','Africa/Lagos','Africa/Dakar','Europe/Paris','UTC'] as $tz): ?>
                <option value="<?= $tz ?>" <?= ($user['timezone'] ?? '')===$tz?'selected':'' ?>><?= $tz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Signature e-mail</label>
              <textarea name="signature" class="form-control" rows="4" placeholder="Votre signature..."><?= sanitize($user['signature']??'') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </form>
        </div>
      </div>
    </div>

    <!-- PASSWORD -->
    <div id="sec-password" style="display:none;">
      <div class="section-card">
        <div class="section-head">🔒 Changer le mot de passe</div>
        <div class="section-body">
          <form method="POST">
            <input type="hidden" name="action" value="password">
            <div class="form-group"><label>Mot de passe actuel</label><input type="password" name="old_password" class="form-control" required></div>
            <div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
            <div class="form-group"><label>Confirmer le nouveau mot de passe</label><input type="password" name="confirm_password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
          </form>
        </div>
      </div>
    </div>

    <!-- MAIL SERVER -->
    <div id="sec-mail" style="display:none;">
      <div class="section-card">
        <div class="section-head">📧 Configuration du serveur mail externe</div>
        <div class="section-body">
          <div class="alert alert-info">⚠ Configurez ici votre compte IMAP/SMTP externe (Gmail, Outlook, Yahoo...)</div>
          <form method="POST">
            <input type="hidden" name="action" value="mail_server">
            <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">IMAP (Réception)</div>
            <div class="form-row">
              <div class="form-group"><label>Hôte IMAP</label><input type="text" name="imap_host" class="form-control" value="<?= sanitize($user['imap_host']??'') ?>" placeholder="imap.gmail.com"></div>
              <div class="form-group"><label>Port</label><input type="number" name="imap_port" class="form-control" value="<?= $user['imap_port']??993 ?>"></div>
            </div>
            <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;margin-top:8px;">SMTP (Envoi)</div>
            <div class="form-row">
              <div class="form-group"><label>Hôte SMTP</label><input type="text" name="smtp_host" class="form-control" value="<?= sanitize($user['smtp_host']??'') ?>" placeholder="smtp.gmail.com"></div>
              <div class="form-group"><label>Port</label><input type="number" name="smtp_port" class="form-control" value="<?= $user['smtp_port']??587 ?>"></div>
            </div>
            <div class="form-group"><label>Mot de passe application</label><input type="password" name="mail_password" class="form-control" placeholder="Mot de passe app Gmail/Outlook..."></div>
            <button type="submit" class="btn btn-primary">Sauvegarder</button>
          </form>
        </div>
      </div>
    </div>

    <!-- USERS (Admin only) -->
    <?php if (!empty($user['is_admin'])): ?>
    <div id="sec-users" style="display:none;">
      <div class="section-card">
        <div class="section-head">
          👥 Gestion des utilisateurs
          <button class="btn btn-primary btn-sm" style="margin-left:auto;" onclick="openUserModal()">+ Nouvel utilisateur</button>
        </div>
        <div class="section-body">
          <?php foreach ($allUsers as $u): ?>
          <div class="user-row">
            <div class="user-av" style="background:<?= sanitize($u['avatar_color'] ?? '#4f8ef7') ?>">
              <?= initials($u['display_name'] ?? 'U') ?>
            </div>
            <div class="user-info">
              <div class="user-name">
                <?= sanitize($u['display_name'] ?? '') ?>
                <?php if ($u['is_admin']): ?><span class="badge-admin" style="margin-left:6px;">Admin</span><?php endif; ?>
                <?php if (!$u['active']): ?><span class="badge-inactive" style="margin-left:4px;">Inactif</span><?php endif; ?>
              </div>
              <div class="user-email"><?= sanitize($u['username'] ?? '') ?> — <?= sanitize($u['email'] ?? '') ?></div>
              <div class="user-meta">
                Créé: <?= $u['created_at'] ?? '-' ?>
                <?php if ($u['last_login']): ?> · Dernière connexion: <?= $u['last_login'] ?><?php endif; ?>
              </div>
            </div>
            <div class="user-actions">
              <button class="btn btn-sm" onclick="editUserModal(<?= $u['id'] ?>)" title="Modifier">✏</button>
              <button class="btn btn-sm" onclick="resetUserPassword(<?= $u['id'] ?>, '<?= sanitize($u['display_name'] ?? '') ?>')" title="Réinitialiser le mot de passe">🔑</button>
              <?php if ($u['id'] != $uid): ?>
              <button class="btn btn-sm" onclick="toggleUserActive(<?= $u['id'] ?>)" title="<?= $u['active'] ? 'Désactiver' : 'Activer' ?>"><?= $u['active'] ? '🔒' : '🔓' ?></button>
              <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= sanitize($u['display_name'] ?? '') ?>')" title="Supprimer">🗑</button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- User Modal -->
    <div class="modal-bg" id="user-modal">
      <div class="modal-box">
        <div class="modal-head">
          <h3 id="user-modal-title">Nouvel utilisateur</h3>
          <button class="btn-icon" onclick="closeUserModal()">✕</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="um-id">
          <div class="form-group">
            <label>Nom d'utilisateur *</label>
            <input type="text" id="um-username" class="form-control" placeholder="identifiant">
          </div>
          <div class="form-row">
            <div class="form-group"><label>Nom affiché *</label><input type="text" id="um-displayname" class="form-control" placeholder="Jean Dupont"></div>
            <div class="form-group"><label>E-mail *</label><input type="email" id="um-email" class="form-control" placeholder="jean@zimbrax.cm"></div>
          </div>
          <div id="um-password-field">
            <div class="form-group"><label>Mot de passe *</label><input type="password" id="um-password" class="form-control" placeholder="Min. 6 caractères"></div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Couleur avatar</label>
              <input type="color" id="um-color" class="form-control" value="#4f8ef7" style="height:38px;cursor:pointer;">
            </div>
            <div class="form-group">
              <label>Administrateur</label>
              <select id="um-isadmin" class="form-control">
                <option value="0">Non</option>
                <option value="1">Oui</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn" onclick="closeUserModal()">Annuler</button>
          <button class="btn btn-primary" onclick="saveUser()">Enregistrer</button>
        </div>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal-bg" id="reset-pw-modal">
      <div class="modal-box">
        <div class="modal-head">
          <h3 id="reset-pw-title">Réinitialiser le mot de passe</h3>
          <button class="btn-icon" onclick="document.getElementById('reset-pw-modal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="rpw-id">
          <div class="form-group"><label>Nouveau mot de passe</label><input type="password" id="rpw-password" class="form-control" placeholder="Min. 6 caractères" minlength="6"></div>
          <div class="form-group"><label>Confirmer</label><input type="password" id="rpw-confirm" class="form-control" placeholder="Confirmer le mot de passe"></div>
        </div>
        <div class="modal-foot">
          <button class="btn" onclick="document.getElementById('reset-pw-modal').classList.remove('open')">Annuler</button>
          <button class="btn btn-primary" onclick="saveResetPassword()">Changer</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ABOUT -->
    <div id="sec-about" style="display:none;">
      <div class="section-card">
        <div class="section-head">ℹ À propos de ZimbraX</div>
        <div class="section-body" style="text-align:center;padding:32px;">
          <div class="logo" style="font-size:28px;justify-content:center;margin-bottom:12px;font-family:'DM Sans',sans-serif;font-weight:700;color:var(--blue);">ZimbraX</div>
          <div style="color:var(--text3);font-size:13px;">Version 2.0 — PHP/MySQL</div>
          <div style="color:var(--text3);font-size:12px;margin-top:8px;">Webmail · Calendrier · Contacts · Tâches</div>
        </div>
      </div>
    </div>

    <!-- FOLDERS -->
    <div id="sec-folders" style="display:none;">
      <div class="section-card">
        <div class="section-head">📁 Gérer les dossiers</div>
        <div class="section-body">
          <?php
          $foldersAll = $pdo->prepare("SELECT * FROM folders WHERE user_id=? ORDER BY sort_order");
          $foldersAll->execute([$uid]);
          foreach ($foldersAll->fetchAll() as $f):
          ?>
          <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);">
            <span><?= $f['icon'] ?></span>
            <span style="flex:1;font-size:13px;"><?= sanitize($f['name']) ?></span>
            <span class="badge badge-gray"><?= $f['type'] ?></span>
            <?php if ($f['type'] === 'custom'): ?>
            <form method="POST" action="<?= BASE_URL ?>api/folders.php" style="display:inline;">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $f['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce dossier ?')">🗑</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <form method="POST" action="<?= BASE_URL ?>api/folders.php" style="margin-top:14px;display:flex;gap:8px;">
            <input type="hidden" name="action" value="create">
            <input type="text" name="name" class="form-control" placeholder="Nom du nouveau dossier..." required style="flex:1;">
            <button type="submit" class="btn btn-primary">+ Créer</button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>
<script>
function showSection(id, el) {
  document.querySelectorAll('[id^="sec-"]').forEach(s => s.style.display = 'none');
  document.getElementById('sec-'+id).style.display = '';
  document.querySelectorAll('.s-nav-item').forEach(n => n.classList.remove('active'));
  el.classList.add('active');
}

<?php if (!empty($user['is_admin'])): ?>
// ── User management ──────────────────────────────────────
const API = '<?= BASE_URL ?>api/users.php';

function openUserModal(u) {
  document.getElementById('um-id').value = u ? u.id : '';
  document.getElementById('um-username').value = u ? u.username : '';
  document.getElementById('um-displayname').value = u ? u.display_name : '';
  document.getElementById('um-email').value = u ? u.email : '';
  document.getElementById('um-color').value = u ? (u.avatar_color || '#4f8ef7') : '#4f8ef7';
  document.getElementById('um-isadmin').value = u ? u.is_admin : '0';
  document.getElementById('um-password-field').style.display = u ? 'none' : '';
  document.getElementById('um-password').value = '';
  document.getElementById('user-modal-title').textContent = u ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur';
  document.getElementById('user-modal').classList.add('open');
}

function closeUserModal() {
  document.getElementById('user-modal').classList.remove('open');
}

async function editUserModal(id) {
  const res = await fetch(API + '?action=get&id=' + id);
  const data = await res.json();
  if (data.success) openUserModal(data.user);
}

async function saveUser() {
  const id = document.getElementById('um-id').value;
  const payload = {
    action: id ? 'update' : 'create',
    email: document.getElementById('um-email').value.trim(),
    display_name: document.getElementById('um-displayname').value.trim(),
    avatar_color: document.getElementById('um-color').value,
    is_admin: document.getElementById('um-isadmin').value
  };
  if (id) payload.id = id;
  if (!id) {
    payload.username = document.getElementById('um-username').value.trim();
    payload.password = document.getElementById('um-password').value;
  }
  const res = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) { closeUserModal(); location.reload(); }
  else alert(data.error || 'Erreur');
}

async function toggleUserActive(id) {
  const res = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_active', id})
  });
  const data = await res.json();
  if (data.success) location.reload();
  else alert(data.error || 'Erreur');
}

async function deleteUser(id, name) {
  if (!confirm('Supprimer l\'utilisateur "' + name + '" ? Cette action est irréversible.')) return;
  const res = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id})
  });
  const data = await res.json();
  if (data.success) location.reload();
  else alert(data.error || 'Erreur');
}

function resetUserPassword(id, name) {
  document.getElementById('rpw-id').value = id;
  document.getElementById('rpw-password').value = '';
  document.getElementById('rpw-confirm').value = '';
  document.getElementById('reset-pw-title').textContent = 'Réinitialiser — ' + name;
  document.getElementById('reset-pw-modal').classList.add('open');
}

async function saveResetPassword() {
  const pw = document.getElementById('rpw-password').value;
  const cfm = document.getElementById('rpw-confirm').value;
  if (pw !== cfm) { alert('Les mots de passe ne correspondent pas'); return; }
  if (pw.length < 6) { alert('Min. 6 caractères'); return; }
  const id = document.getElementById('rpw-id').value;
  const res = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'reset_password', id, password: pw})
  });
  const data = await res.json();
  if (data.success) { document.getElementById('reset-pw-modal').classList.remove('open'); alert('Mot de passe réinitialisé'); }
  else alert(data.error || 'Erreur');
}

// Close modals on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeUserModal();
    document.getElementById('reset-pw-modal').classList.remove('open');
  }
});
<?php endif; ?>
</script>
</body>
</html>