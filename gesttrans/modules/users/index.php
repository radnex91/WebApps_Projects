<?php
// modules/users/index.php — Gestion des Utilisateurs
require_once '../../includes/config.php';
requireLogin(); requirePerm('users.manage');
$pageTitle = 'Gestion des Utilisateurs';

if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET actif=NOT actif WHERE id=?")->execute([$uid]);
        flash('Statut modifié.');
    }
    redirect(BASE_URL.'modules/users/');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $un     = trim($_POST['username'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $rid    = (int)($_POST['role_id'] ?? 0);
    $aid    = (int)($_POST['agence_id'] ?? 0) ?: null;
    $pw     = $_POST['password'] ?? '';
    $col    = trim($_POST['avatar_color'] ?? '#1e40af');
    if (!$nom || !$un || !$rid) {
        flash('Nom, username et rôle obligatoires.','danger');
    } else {
        try {
            if ($id) {
                if ($pw) {
                    $pdo->prepare("UPDATE users SET nom=?,prenom=?,username=?,email=?,role_id=?,agence_id=?,avatar_color=?,password=? WHERE id=?")
                        ->execute([$nom,$prenom,$un,$email,$rid,$aid,$col,password_hash($pw,PASSWORD_DEFAULT),$id]);
                } else {
                    $pdo->prepare("UPDATE users SET nom=?,prenom=?,username=?,email=?,role_id=?,agence_id=?,avatar_color=? WHERE id=?")
                        ->execute([$nom,$prenom,$un,$email,$rid,$aid,$col,$id]);
                }
                flash('Utilisateur modifié.');
            } else {
                if (!$pw) { flash('Mot de passe requis pour le nouvel utilisateur.','danger'); redirect(BASE_URL.'modules/users/'); }
                $pdo->prepare("INSERT INTO users (nom,prenom,username,email,role_id,agence_id,avatar_color,password) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$nom,$prenom,$un,$email,$rid,$aid,$col,password_hash($pw,PASSWORD_DEFAULT)]);
                flash('Utilisateur créé.');
            }
        } catch(PDOException $e) {
            flash('Username déjà utilisé.','danger');
        }
    }
    redirect(BASE_URL.'modules/users/');
}

$roles   = $pdo->query("SELECT * FROM roles ORDER BY niveau DESC")->fetchAll();
$agences = $pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$users   = $pdo->query("SELECT u.*,r.nom as role_nom,r.couleur as role_color,a.nom as agence_nom FROM users u JOIN roles r ON u.role_id=r.id LEFT JOIN agences a ON u.agence_id=a.id ORDER BY r.niveau DESC,u.nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Utilisateurs</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-users-cog"></i> Utilisateurs (<?= count($users) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('um');document.getElementById('u-id').value=''"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Utilisateur</th><th>Username</th><th>Rôle</th><th>Agence</th><th>Email</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($users as $u): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="avatar avatar-sm" style="background:<?= h($u['avatar_color']) ?>"><?= initials(($u['prenom']??'').' '.$u['nom']) ?></div>
            <div><strong><?= h(trim(($u['prenom']??'').' '.$u['nom'])) ?></strong></div>
          </div>
        </td>
        <td><code>@<?= h($u['username']) ?></code></td>
        <td><span class="badge" style="background:<?= h($u['role_color']) ?>20;color:<?= h($u['role_color']) ?>"><?= h($u['role_nom']) ?></span></td>
        <td style="font-size:12px;"><?= h($u['agence_nom']??'— Siège —') ?></td>
        <td style="font-size:12px;"><?= h($u['email']??'—') ?></td>
        <td style="font-size:11px;color:var(--text3);"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Jamais' ?></td>
        <td><?= $u['actif'] ? '<span class="badge b-green">Actif</span>' : '<span class="badge b-gray">Inactif</span>' ?></td>
        <td>
          <div style="display:flex;gap:3px;">
            <button class="btn btn-xs btn-warning" onclick="editU(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>)" title="Modifier"><i class="fas fa-edit"></i></button>
            <?php if($u['id'] !== $_SESSION['user_id']): ?>
            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-xs btn-ghost" title="Activer/Désactiver"><i class="fas fa-toggle-<?= $u['actif']?'on':'off' ?>"></i></a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div></div>
</div>

<!-- MODAL UTILISATEUR -->
<div class="modal-over" id="um">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-user-plus"></i> Utilisateur</h3><button class="modal-x" onclick="closeModal('um')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="id" id="u-id">
        <div class="form-grid">
          <div class="fg"><label class="flbl">Prénom</label><input type="text" name="prenom" id="u-prenom" class="fc"></div>
          <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="nom" id="u-nom" class="fc" required></div>
          <div class="fg"><label class="flbl">Username <span class="freq">*</span></label><input type="text" name="username" id="u-un" class="fc" required placeholder="login"></div>
          <div class="fg"><label class="flbl">Email</label><input type="email" name="email" id="u-email" class="fc"></div>
          <div class="fg"><label class="flbl">Rôle <span class="freq">*</span></label>
            <select name="role_id" id="u-role" class="fc" required>
              <option value="">—</option>
              <?php foreach($roles as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label class="flbl">Agence</label>
            <select name="agence_id" id="u-agence" class="fc">
              <option value="">— Siège / Tous —</option>
              <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= h($a['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label class="flbl">Mot de passe</label><input type="password" name="password" id="u-pw" class="fc" placeholder="(inchangé si vide)"></div>
          <div class="fg"><label class="flbl">Couleur avatar</label><input type="color" name="avatar_color" id="u-col" class="fc" value="#1e40af" style="height:38px;cursor:pointer;"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('um')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function editU(u){
  document.getElementById('u-id').value=u.id;
  document.getElementById('u-nom').value=u.nom;
  document.getElementById('u-prenom').value=u.prenom||'';
  document.getElementById('u-un').value=u.username;
  document.getElementById('u-email').value=u.email||'';
  document.getElementById('u-role').value=u.role_id;
  document.getElementById('u-agence').value=u.agence_id||'';
  document.getElementById('u-pw').value='';
  document.getElementById('u-col').value=u.avatar_color||'#1e40af';
  openModal('um');
}
</script>
<?php include '../../includes/footer.php'; ?>
