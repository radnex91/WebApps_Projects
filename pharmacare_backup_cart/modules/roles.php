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

// ── POST : mise à jour des permissions ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'add' && hasPermission('roles.gerer')) {
    verifyCsrf();
    $roleId = (int)($_POST['role_id'] ?? 0);
    $role = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $role->execute([$roleId]);
    $role = $role->fetch();

    if ($role) {
        $perms = $_POST['perms'] ?? [];
        // Valider les permission IDs
        $validIds = $db->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($perms as $pid) {
            $pid = (int)$pid;
            if (in_array($pid, $validIds)) {
                $stmt->execute([$roleId, $pid]);
            }
        }
        // Si c'est le rôle de l'utilisateur courant, rafraîchir sa session
        if ($roleId === (int)($_SESSION['user_role_id'] ?? 0)) {
            refreshUserPermissions();
        }
        flash('Permissions mises à jour.');
    }
    header('Location: ' . APP_URL . '/modules/roles.php'); exit;
}

// ── Ajout d'un rôle personnalisé ──────────────────────────
if ($action === 'add' && hasPermission('roles.gerer') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $libelle = trim($_POST['libelle'] ?? '');
    if ($libelle === '') {
        flash('Le nom du rôle est requis.', 'error');
    } else {
        // Générer le code à partir du libellé
        $code = strtolower(preg_replace('/[^a-z0-9]+/', '-', str_replace(['à','â','é','è','ê','ë','ï','î','ô','ù','û','ü','ç','œ','æ'],
                ['a','a','e','e','e','e','i','i','o','u','u','u','c','oe','ae'], strtolower($libelle))));
        $code = trim($code, '-') ?: 'role-' . time();
        // Vérifier unicité
        $check = $db->prepare("SELECT id FROM roles WHERE code = ?");
        $check->execute([$code]);
        if ($check->fetch()) {
            $code .= '-' . time();
        }
        $db->prepare("INSERT INTO roles (code, libelle, est_systeme) VALUES (?, ?, 0)")->execute([$code, $libelle]);
        $newId = $db->lastInsertId();
        // Assigner les permissions cochées
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
    header('Location: ' . APP_URL . '/modules/roles.php'); exit;
}

// ── Suppression d'un rôle personnalisé ─────────────────────
if ($action === 'delete' && hasPermission('roles.gerer') && $id) {
    $role = $db->prepare("SELECT * FROM roles WHERE id = ? AND est_systeme = 0");
    $role->execute([$id]);
    $role = $role->fetch();
    if ($role) {
        // Réassigner les utilisateurs de ce rôle au rôle caissier (id=3)
        $db->prepare("UPDATE utilisateurs SET role_id = 3 WHERE role_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
        flash("Rôle « {$role['libelle']} » supprimé. Les utilisateurs ont été réassignés au rôle Caissier.");
    }
    header('Location: ' . APP_URL . '/modules/roles.php'); exit;
}

// ── Édition des permissions d'un rôle ──────────────────────
if ($action === 'edit' && $id) {
    $role = $db->prepare("SELECT * FROM roles WHERE id = ?")->fetch() ?: null;
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if (!$role) { flash('Rôle introuvable.', 'error'); header('Location: ' . APP_URL . '/modules/roles.php'); exit; }

    $stmtP = $db->query("SELECT * FROM permissions ORDER BY module, id");
    $allPerms = $stmtP->fetchAll();

    $stmtR = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmtR->execute([$id]);
    $rolePerms = $stmtR->fetchAll(PDO::FETCH_COLUMN);

    $userCount = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE role_id = ?");
    $userCount->execute([$id]);
    $nbUsers = $userCount->fetchColumn();

    layout_head('Permissions — ' . e($role['libelle']), 'roles');
    showFlash();
    ?>
    <div class="card" style="max-width:720px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Permissions — <?= e($role['libelle']) ?></div>
        <a href="<?= APP_URL ?>/modules/roles.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <div class="card-pad">
        <div style="display:flex;gap:20px;margin-bottom:18px;font-size:13px;color:var(--text3);">
          <span><?= $nbUsers ?> utilisateur<?= $nbUsers > 1 ? 's' : '' ?></span>
          <span><?= count($rolePerms) ?> permission<?= count($rolePerms) > 1 ? 's' : '' ?></span>
          <?php if ($role['est_systeme']): ?><span class="badge badge-blue">Rôle système</span><?php endif; ?>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="role_id" value="<?= $id ?>">
          <?php
          $currentModule = '';
          foreach ($allPerms as $p):
            if ($p['module'] !== $currentModule):
              if ($currentModule !== '') echo '</div>';
              $currentModule = $p['module'];
          ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:12px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
              <?= e($moduleLabels[$p['module']] ?? $p['module']) ?>
            </div>
          <?php endif; ?>
            <label style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px;cursor:pointer;">
              <input type="checkbox" name="perms[]" value="<?= $p['id'] ?>"
                <?= in_array($p['id'], $rolePerms) ? 'checked' : '' ?>
                <?= ($role['code'] === 'admin') ? 'disabled checked' : '' ?>>
              <span><?= e($p['libelle']) ?></span>
              <span style="color:var(--text3);font-family:'DM Mono',monospace;font-size:11px;margin-left:auto;"><?= e($p['code']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if ($currentModule !== '') echo '</div>'; ?>
          <?php if ($role['code'] === 'admin'): ?>
            <div style="background:var(--teal-dim);border:1px solid var(--teal);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--teal2);margin-bottom:14px;">
              L'administrateur dispose de toutes les permissions par conception. Elles ne peuvent pas être retirées.
            </div>
          <?php endif; ?>
          <?php if ($role['code'] !== 'admin'): ?>
          <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer les permissions</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <?php layout_foot(); exit;
}

// ── Formulaire d'ajout ──────────────────────────────────────
if ($action === 'add' && hasPermission('roles.gerer')) {
    $allPerms = $db->query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();

    layout_head('Nouveau rôle', 'roles');
    showFlash();
    ?>
    <div class="card" style="max-width:720px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Nouveau rôle</div>
        <a href="<?= APP_URL ?>/modules/roles.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <form method="POST" action="?action=add">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="card-pad">
          <div class="form-group">
            <label>Nom du rôle *</label>
            <input type="text" name="libelle" required placeholder="ex: Superviseur">
          </div>
          <div style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:12px;">Permissions</div>
          <?php
          $currentModule = '';
          foreach ($allPerms as $p):
            if ($p['module'] !== $currentModule):
              if ($currentModule !== '') echo '</div>';
              $currentModule = $p['module'];
          ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
              <?= e($moduleLabels[$p['module']] ?? $p['module']) ?>
            </div>
          <?php endif; ?>
            <label style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px;cursor:pointer;">
              <input type="checkbox" name="perms[]" value="<?= $p['id'] ?>">
              <span><?= e($p['libelle']) ?></span>
              <span style="color:var(--text3);font-family:'DM Mono',monospace;font-size:11px;margin-left:auto;"><?= e($p['code']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if ($currentModule !== '') echo '</div>'; ?>
        </div>
        <div class="modal-footer">
          <a href="<?= APP_URL ?>/modules/roles.php" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary"><?= icon('plus',14) ?> Créer le rôle</button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
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
    <a href="?action=add" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Nouveau rôle</a>
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
              <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?> Permissions</a>
              <?php if (!$r['est_systeme'] && hasPermission('roles.gerer')): ?>
              <a href="?action=delete&id=<?= $r['id'] ?>" class="btn btn-ghost btn-xs" style="color:var(--red);"
                 onclick="return confirm('Supprimer ce rôle ? Les utilisateurs seront réassignés au rôle Caissier.')"><?= icon('trash',13) ?></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); ?>