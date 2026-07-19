<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('remise.approbateurs.gerer');
$db = getDB();

// ── Actions POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $uid   = (int)($_POST['id'] ?? 0);
    $act   = $_POST['action'] ?? '';

    if ($uid && $act === 'ajouter') {
        $db->prepare("INSERT IGNORE INTO remise_approbateurs (utilisateur_id, added_by)
                      VALUES (?, ?)")
           ->execute([$uid, currentUser()['id']]);
        $st = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id=?");
        $st->execute([$uid]); $n = $st->fetch();
        auditLog('remise.approbateur.add', 'Ajout approbateur de remise : ' . ($n ? $n['prenom'].' '.$n['nom'] : "#$uid"), $uid);
        flash('Approbateur ajouté : il peut désormais autoriser des remises et générer des codes.');
    } elseif ($uid && $act === 'retirer') {
        $st = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id=?");
        $st->execute([$uid]); $n = $st->fetch();
        $db->prepare("DELETE FROM remise_approbateurs WHERE utilisateur_id=?")->execute([$uid]);
        auditLog('remise.approbateur.remove', 'Retrait approbateur de remise : ' . ($n ? $n['prenom'].' '.$n['nom'] : "#$uid"), $uid);
        flash('Approbateur retiré.', 'info');
    }
    header('Location: ' . url('remise_approbateurs')); exit;
}

// ── Liste : tous les utilisateurs actifs + flag approbateur ──
$users = $db->query("
    SELECT u.id, u.prenom, u.nom, u.login, r.libelle AS role_libelle, r.code AS role_code,
           (SELECT 1 FROM remise_approbateurs ra WHERE ra.utilisateur_id = u.id AND ra.actif = 1) AS approbateur
    FROM utilisateurs u
    JOIN roles r ON u.role_id = r.id
    WHERE u.actif = 1
    ORDER BY approbateur DESC, r.est_systeme DESC, u.nom, u.prenom
")->fetchAll();

$roleColors = ['admin' => 'badge-green', 'pharmacien' => 'badge-blue', 'caissier' => 'badge-gold', 'manager' => 'badge-purple', 'superviseur' => 'badge-cyan'];
$nbApprobateurs = count(array_filter($users, fn($u) => !empty($u['approbateur'])));

layout_head('Approbateurs de remise', 'remise_approbateurs');
showFlash();
?>

<div class="card">
  <div class="card-header">
    <div class="card-title">Approbateurs de remise</div>
    <span class="badge badge-blue"><?= $nbApprobateurs ?> actif(s)</span>
  </div>
  <div style="padding:14px 22px;color:var(--text2);font-size:13.5px;line-height:1.55;">
    <?= icon('info',14) ?> Les utilisateurs cochés comme approbateurs peuvent <strong>apparaître dans le select « Autorisé par »</strong> au point de vente
    et <strong>générer un code d'autorisation</strong> de remise. Les autres ne peuvent ni autoriser ni générer de code.
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>Statut approbateur</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $badgeClass = $roleColors[$u['role_code']] ?? 'badge-gray';
          $estApprouveur = !empty($u['approbateur']);
        ?>
        <tr>
          <td class="td-name"><?= e($u['prenom'].' '.$u['nom']) ?></td>
          <td class="td-mono"><?= e($u['login']) ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= e($u['role_libelle']) ?></span></td>
          <td>
            <?php if ($estApprouveur): ?>
              <span class="badge badge-green"><?= icon('check',12) ?> Approbateur</span>
            <?php else: ?>
              <span class="badge badge-gray">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($estApprouveur): ?>
              <button type="button" class="btn btn-danger btn-xs"
                onclick="confirmDeletePost('retirer','<?= (int)$u['id'] ?>','Retirer <?= e($u['prenom'].' '.$u['nom']) ?> de la liste des approbateurs ?')">
                <?= icon('x',13) ?> Retirer
              </button>
            <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-primary btn-xs"><?= icon('plus',13) ?> Ajouter</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); ?>