<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('roles.voir');
$db = getDB();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$moduleLabels = [
    'dashboard'    => 'Tableau de bord',
    'vente'       => 'Point de vente',
    'stock'       => 'Gestion du stock',
    'produits'    => 'Médicaments',
    'fournisseurs'=> 'Fournisseurs',
    'commandes'   => 'Commandes',
    'ventes_hist' => 'Historique des ventes',
    'rapports'    => 'Rapports',
    'utilisateurs'=> 'Utilisateurs',
    'categories'  => 'Catégories',
    'parametres'  => 'Paramètres',
    'roles'         => 'Rôles & Permissions',
    'comptabilite'  => 'Comptabilité',
    'caisse'        => 'Caisses',
];

// ── Suppression d'un rôle personnalisé (POST + CSRF) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && hasPermission('roles.gerer')) {
    verifyCsrf();
    $delId = (int)($_POST['id'] ?? 0);
    $role = $db->prepare("SELECT * FROM roles WHERE id = ? AND est_systeme = 0");
    $role->execute([$delId]);
    $role = $role->fetch();
    if ($role) {
        $db->prepare("UPDATE utilisateurs SET role_id = 3 WHERE role_id = ?")->execute([$delId]);
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$delId]);
        $db->prepare("DELETE FROM roles WHERE id = ?")->execute([$delId]);
        flash("Rôle « {$role['libelle']} » supprimé. Les utilisateurs ont été réassignés au rôle Caissier.");
    }
    header('Location: ' . url('roles')); exit;
}

// ── POST : mise à jour des permissions ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'add' && hasPermission('roles.gerer')) {
    verifyCsrf();
    $roleId = (int)($_POST['role_id'] ?? 0);
    $role = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $role->execute([$roleId]);
    $role = $role->fetch();

    if ($role) {
        $perms = $_POST['perms'] ?? [];
        $validIds = $db->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($perms as $pid) {
            $pid = (int)$pid;
            if (in_array($pid, $validIds)) {
                $stmt->execute([$roleId, $pid]);
            }
        }
        if ($roleId === (int)($_SESSION['user_role_id'] ?? 0)) {
            refreshUserPermissions();
        }
        flash('Permissions mises à jour.');
    }
    header('Location: ' . url('roles')); exit;
}

// ── Ajout d'un rôle personnalisé ──────────────────────────
if ($action === 'add' && hasPermission('roles.gerer') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $libelle = trim($_POST['libelle'] ?? '');
    if ($libelle === '') {
        flash('Le nom du rôle est requis.', 'error');
    } else {
        $code = strtolower(preg_replace('/[^a-z0-9]+/', '-', str_replace(['à','â','é','è','ê','ë','ï','î','ô','ù','û','ü','ç','œ','æ'],
                ['a','a','e','e','e','e','i','i','o','u','u','u','c','oe','ae'], strtolower($libelle))));
        $code = trim($code, '-') ?: 'role-' . time();
        $check = $db->prepare("SELECT id FROM roles WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetch()) {
            $code .= '-' . time();
        }
        $db->prepare("INSERT INTO roles (code, libelle, est_systeme) VALUES (?, ?, 0)")->execute([$code, $libelle]);
        $newId = $db->lastInsertId();
        $perms = $_POST['perms'] ?? [];
        $validIds = $db->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($perms as $pid) {
            $pid = (int)$pid;
            if (in_array($pid, $validIds)) {
                $stmt->execute([$newId, $pid]);
            }
        }
        flash("Rôle « $libelle » créé.");
    }
    header('Location: ' . url('roles')); exit;
}

// ── Données pour les modales ─────────────────────────────────
$allPerms = $db->query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
$allRolePerms = $db->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$rolePermMap = [];
foreach ($allRolePerms as $rp) {
    $rolePermMap[(int)$rp['role_id']][] = (int)$rp['permission_id'];
}

// ── Liste des rôles ─────────────────────────────────────────
$roles = $db->query("
    SELECT r.*, COUNT(DISTINCT rp.permission_id) AS nb_perms,
           COUNT(DISTINCT u.id) AS nb_users
    FROM roles r
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    LEFT JOIN utilisateurs u ON r.id = u.role_id
    GROUP BY r.id ORDER BY r.est_systeme DESC, r.libelle
")->fetchAll();

layout_head('Rôles & Permissions', 'roles');
showFlash();
?>

<div class="card">
  <div class="card-header">
    <div class="card-title">Rôles & Permissions</div>
    <?php if (hasPermission('roles.gerer')): ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-new-role')"><?= icon('plus',14) ?> Nouveau rôle</button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Rôle</th><th>Utilisateurs</th><th>Permissions</th><th>Type</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($roles as $r): ?>
        <tr>
          <td class="td-name">
            <strong><?= e($r['libelle']) ?></strong>
            <div style="font-size:11px;color:var(--text3);font-family:'DM Mono',monospace;"><?= e($r['code']) ?></div>
          </td>
          <td class="fw-mono"><?= $r['nb_users'] ?></td>
          <td class="fw-mono"><?= $r['nb_perms'] ?> / 25</td>
          <td><?= $r['est_systeme'] ? '<span class="badge badge-blue">Système</span>' : '<span class="badge badge-gray">Personnalisé</span>' ?></td>
          <td>
            <div class="flex gap-8">
              <button type="button" class="btn btn-ghost btn-xs"
                onclick='openEditRoleModal(<?= (int)$r['id'] ?>, <?= json_encode($rolePermMap[(int)$r['id']] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS) ?>, <?= json_encode($r['libelle'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS) ?>, <?= ($r['code'] === 'admin') ? 'true' : 'false' ?>)'>
                <?= icon('edit',13) ?> Permissions
              </button>
              <?php if (!$r['est_systeme'] && hasPermission('roles.gerer')): ?>
              <button type="button" class="btn btn-ghost btn-xs" style="color:var(--red);"
                 onclick="confirmDeletePost('delete','<?= (int)$r['id'] ?>','Supprimer ce rôle ?')"><?= icon('trash',13) ?></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal Édition Permissions ── -->
<div class="modal-overlay" id="modal-edit-perms">
  <div class="modal modal-lg" style="width:920px;max-width:94vw;">
    <div class="modal-header">
      <div class="modal-title" id="modal-edit-title">Permissions</div>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-perms')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="role_id" id="edit-role-id">
      <div class="card-pad" id="modal-edit-body"></div>
      <div class="modal-footer" id="modal-edit-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-perms')">Annuler</button>
        <button type="submit" class="btn btn-primary" id="edit-submit-btn"><?= icon('save',14) ?> Enregistrer les permissions</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal Nouveau Rôle ── -->
<div class="modal-overlay" id="modal-new-role">
  <div class="modal modal-lg" style="width:920px;max-width:94vw;">
    <div class="modal-header">
      <div class="modal-title">Nouveau rôle</div>
      <button type="button" class="modal-close" onclick="closeModal('modal-new-role')">✕</button>
    </div>
    <form method="POST" action="?action=add">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="card-pad">
        <div class="form-group">
          <label>Nom du rôle *</label>
          <input type="text" name="libelle" required placeholder="ex: Superviseur" autofocus>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
          <span style="font-size:13px;font-weight:600;color:var(--text2);">Permissions</span>
          <button type="button" class="btn btn-ghost btn-xs" onclick="var cbs=this.closest('form').querySelectorAll('input[name=\'perms[]\']');var allChecked=true;cbs.forEach(function(c){if(!c.checked)allChecked=false;});cbs.forEach(function(c){c.checked=!allChecked;})">Tout cocher</button>
        </div>
        <?php
        // Regroupement par module + ordre d'apparition
        $byModule = [];
        foreach ($allPerms as $p) {
            $byModule[$p['module']][] = $p;
        }
        $modIdx = 0;
        ?>
        <div class="role-tabs" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);">
          <?php foreach ($byModule as $mod => $perms): ?>
          <button type="button" class="btn btn-sm <?= $modIdx === 0 ? 'btn-primary' : 'btn-ghost' ?>"
                  data-rtab="<?= e($mod) ?>" onclick="switchRoleTab(this)" style="white-space:nowrap;">
            <?= e($moduleLabels[$mod] ?? $mod) ?> <span style="opacity:.7;font-weight:400;"><?= count($perms) ?></span>
          </button>
          <?php $modIdx++; endforeach; ?>
        </div>
        <?php $modIdx = 0; ?>
        <?php foreach ($byModule as $mod => $perms): ?>
        <div class="role-tab-panel" data-panel="<?= e($mod) ?>" style="display:<?= $modIdx === 0 ? 'block' : 'none' ?>;">
          <?php foreach ($perms as $p): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="perms[]" value="<?= $p['id'] ?>">
            <span><?= e($p['libelle']) ?></span>
            <span style="color:var(--text3);font-family:'DM Mono',monospace;font-size:11px;margin-left:auto;"><?= e($p['code']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php $modIdx++; endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-new-role')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus',14) ?> Créer le rôle</button>
      </div>
    </form>
  </div>
</div>

<script>
const ALL_PERMISSIONS = <?= json_encode($allPerms) ?>;
const MODULE_LABELS = <?= json_encode($moduleLabels) ?>;

function openEditRoleModal(roleId, permsJson, roleName, isAdmin) {
  document.getElementById('edit-role-id').value = roleId;
  document.getElementById('modal-edit-title').textContent = 'Permissions — ' + roleName;

  const rolePerms = Array.isArray(permsJson) ? permsJson : JSON.parse(permsJson);
  const body = document.getElementById('modal-edit-body');

  // Regroupement des permissions par module
  const byModule = {};
  const order = [];
  ALL_PERMISSIONS.forEach(function(p) {
    if (!(p.module in byModule)) { byModule[p.module] = []; order.push(p.module); }
    byModule[p.module].push(p);
  });

  let html = '';
  if (isAdmin) {
    html += '<div style="background:var(--teal-dim);border:1px solid var(--teal);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--teal2);margin-bottom:14px;">' +
      "L'administrateur dispose de toutes les permissions par conception. Elles ne peuvent pas être retirées." +
      '</div>';
  }

  html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;">' +
    '<span style="font-size:13px;font-weight:600;color:var(--text2);">Permissions</span>' +
    '<button type="button" class="btn btn-ghost btn-xs" onclick="var cbs=this.closest(\'form\').querySelectorAll(\'input[name=\\\'perms[]\\\']\');var allChecked=true;cbs.forEach(function(c){if(!c.checked)allChecked=false;});cbs.forEach(function(c){c.checked=!allChecked;})">Tout cocher</button>' +
    '</div>';

  // Onglets : un par module
  html += '<div class="role-tabs" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);">';
  order.forEach(function(m, i) {
    html += '<button type="button" class="btn btn-sm ' + (i === 0 ? 'btn-primary' : 'btn-ghost') + '" data-rtab="' + escHtml(m) + '" onclick="switchRoleTab(this)" style="white-space:nowrap;">' +
      escHtml(MODULE_LABELS[m] || m) + ' <span style="opacity:.7;font-weight:400;">' + byModule[m].length + '</span></button>';
  });
  html += '</div>';

  // Panneaux : un par module
  order.forEach(function(m, i) {
    html += '<div class="role-tab-panel" data-panel="' + escHtml(m) + '" style="display:' + (i === 0 ? 'block' : 'none') + ';">';
    byModule[m].forEach(function(p) {
      var checked = rolePerms.indexOf(p.id) !== -1 ? 'checked' : '';
      var disabled = isAdmin ? 'disabled' : '';
      html += '<label style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px;cursor:pointer;">' +
        '<input type="checkbox" name="perms[]" value="' + p.id + '" ' + checked + ' ' + disabled + '>' +
        '<span>' + escHtml(p.libelle) + '</span>' +
        '<span style="color:var(--text3);font-family:\'DM Mono\',monospace;font-size:11px;margin-left:auto;">' + escHtml(p.code) + '</span>' +
        '</label>';
    });
    html += '</div>';
  });

  body.innerHTML = html;
  document.getElementById('edit-submit-btn').style.display = isAdmin ? 'none' : '';
  openModal('modal-edit-perms');
}

function switchRoleTab(btn) {
  var form = btn.closest('form');
  var m = btn.getAttribute('data-rtab');
  form.querySelectorAll('.role-tabs .btn').forEach(function(b) {
    b.classList.remove('btn-primary'); b.classList.add('btn-ghost');
  });
  btn.classList.remove('btn-ghost'); btn.classList.add('btn-primary');
  form.querySelectorAll('.role-tab-panel').forEach(function(p) {
    p.style.display = p.getAttribute('data-panel') === m ? 'block' : 'none';
  });
}
</script>

<?php layout_foot(); ?>
