<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('utilisateurs.gerer');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Toggle actif/désactivé (POST + CSRF) ──
    if (($_POST['action'] ?? '') === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        if ($tid && $tid !== currentUser()['id']) {
            $db->prepare("UPDATE utilisateurs SET actif = 1-actif WHERE id=?")->execute([$tid]);
            flash('Statut mis à jour.');
        }
        header('Location: ' . url('utilisateurs')); exit;
    }

    $nom    = trim($_POST['nom']    ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email']  ?? '');
    $login  = trim($_POST['login']  ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $actif  = (int)($_POST['actif'] ?? 1);
    $pass   = $_POST['password'] ?? '';

    // Valider que le rôle existe
    $roleCheck = $db->prepare("SELECT id FROM roles WHERE id = ?");
    $roleCheck->execute([$roleId]);
    if (!$roleCheck->fetch()) $roleId = 3; // fallback caissier

    // ── Anti-escalade : un utilisateur ne peut pas modifier son propre rôle ──
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        $cur = $db->prepare("SELECT role_id FROM utilisateurs WHERE id=?");
        $cur->execute([$id]);
        $roleId = (int)$cur->fetchColumn();
    }

    if (!$nom || !$prenom || !$email || !$login) {
        flash('Tous les champs obligatoires doivent être remplis.', 'error');
        header('Location: ' . ($id ? url('utilisateurs', ['action'=>'edit','id'=>$id]) : url('utilisateurs', ['action'=>'add']))); exit;
    }

    if ($id) {
        $db->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,login=?,role_id=?,actif=? WHERE id=?")
           ->execute([$nom,$prenom,$email,$login,$roleId,$actif,$id]);
        if ($pass !== '') {
            $db->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")
               ->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        }
        // Si l'utilisateur modifié est l'utilisateur courant, rafraîchir la session
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            refreshUserPermissions();
        }
        flash('Utilisateur mis à jour.');
    } else {
        if ($pass === '') { flash('Le mot de passe est requis.','error'); header('Location: ' . url('utilisateurs', ['action'=>'add'])); exit; }
        $db->prepare("INSERT INTO utilisateurs (nom,prenom,email,login,mot_de_passe,role_id,actif) VALUES (?,?,?,?,?,?,?)")
           ->execute([$nom,$prenom,$email,$login,password_hash($pass,PASSWORD_BCRYPT),$roleId,$actif]);
        flash("Utilisateur $prenom $nom créé.");
    }
    header('Location: ' . url('utilisateurs')); exit;
}


$roles = $db->query("SELECT id, code, libelle, est_systeme FROM roles ORDER BY est_systeme DESC, libelle")->fetchAll();

if (in_array($action, ['add','edit'])) {
    $u = ['nom'=>'','prenom'=>'','email'=>'','login'=>'','role_id'=>3,'actif'=>1];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id=?");
        $stmt->execute([$id]); $fetched = $stmt->fetch();
        if ($fetched) $u = $fetched;
    }
    layout_head(($id?'Modifier':'Ajouter') . ' utilisateur', 'utilisateurs');
    showFlash();
    ?>
    <div class="card" style="max-width:680px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier utilisateur' : 'Nouvel utilisateur' ?></div>
        <a href="<?= url('utilisateurs') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" value="<?= e($u['prenom']) ?>" required></div>
          <div class="form-group"><label>Nom *</label><input type="text" name="nom" value="<?= e($u['nom']) ?>" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
          <div class="form-group"><label>Identifiant *</label><input type="text" name="login" value="<?= e($u['login']) ?>" required autocomplete="off"></div>
          <div class="form-group">
            <label>Mot de passe <?= $id ? '(vide = inchangé)' : '*' ?></label>
            <input type="password" name="password" <?= $id?'':'required' ?> placeholder="••••••••" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label>Rôle</label>
            <select name="role_id">
              <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($u['role_id'] ?? 3) == $r['id'] ? 'selected' : '' ?>>
                <?= e($r['libelle']) ?><?php if ($r['est_systeme']): ?> (système)<?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select name="actif">
              <option value="1" <?= ($u['actif'] ?? 1)?'selected':'' ?>>Actif</option>
              <option value="0" <?= !($u['actif'] ?? 1)?'selected':'' ?>>Inactif</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('utilisateurs') ?>" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
        </div>
      </form>
    </div>
    <?php layout_foot(); exit;
}

$users = $db->query("
    SELECT u.*, r.libelle AS role_libelle, r.code AS role_code, r.est_systeme,
           (SELECT COUNT(*) FROM ventes WHERE caissier_id=u.id) AS nb_ventes
    FROM utilisateurs u
    JOIN roles r ON u.role_id = r.id
    ORDER BY r.est_systeme DESC, u.nom
")->fetchAll();

$roleColors = ['admin'=>'badge-green','pharmacien'=>'badge-blue','caissier'=>'badge-gold'];
$currentUid  = currentUser()['id'];

layout_head('Utilisateurs', 'utilisateurs');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Gestion des utilisateurs</div>
    <a href="<?= url('utilisateurs', ['action'=>'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Ajouter utilisateur</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nom</th><th>Identifiant</th><th>Email</th><th>Rôle</th>
          <th>Ventes</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $badgeClass = $roleColors[$u['role_code']] ?? 'badge-gray';
        ?>
        <tr>
          <td class="td-name"><?= e($u['prenom'].' '.$u['nom']) ?></td>
          <td class="td-mono"><?= e($u['login']) ?></td>
          <td class="text-sm"><?= e($u['email']) ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= e($u['role_libelle']) ?></span></td>
          <td><span class="badge badge-blue"><?= $u['nb_ventes'] ?></span></td>
          <td class="text-sm"><?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'Jamais' ?></td>
          <td><span class="badge <?= $u['actif']?'badge-green':'badge-red' ?>"><?= $u['actif']?'Actif':'Inactif' ?></span></td>
          <td>
            <div class="flex gap-8">
              <a href="<?= url('utilisateurs', ['action'=>'edit','id'=>$u['id']], trim(($u['prenom'] ?? '').' '.($u['nom'] ?? '')) ?: null) ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?> Modifier</a>
              <?php if ($u['id'] != $currentUid): ?>
              <button type="button"
                 class="btn <?= $u['actif']?'btn-danger':'btn-gold' ?> btn-xs"
                 onclick="confirmDeletePost('toggle','<?= (int)$u['id'] ?>','<?= $u['actif'] ? 'Désactiver cet utilisateur ?' : 'Activer cet utilisateur ?' ?>')">
                <?= $u['actif'] ? icon('lock',13).' Désactiver' : icon('unlock',13).' Activer' ?>
              </button>
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