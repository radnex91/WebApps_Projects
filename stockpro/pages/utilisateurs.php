<?php
$page_title = 'Utilisateurs';
$page_id = 'utilisateurs';
require_once '../includes/header.php';
requireAuth(['super_admin','admin']);
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $nom    = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email  = trim($_POST['email']);
        $role   = $_POST['role'];
        $actif  = isset($_POST['actif']) ? 1 : 0;
        $id     = (int)($_POST['id'] ?? 0);
        $mdp    = trim($_POST['mot_de_passe'] ?? '');

        // Restreindre: admin ne peut pas créer super_admin
        if ($user['role']==='admin' && $role==='super_admin') $role='admin';

        if (!validateString($nom, 100, true) || !validateString($prenom, 100, true)) {
            $msg = '<div class="alert alert-danger">Le nom et le prénom sont obligatoires.</div>';
        } elseif (!validateEmail($email)) {
            $msg = '<div class="alert alert-danger">Email invalide.</div>';
        } else {
            if ($id) {
                $sql = "UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,actif=? WHERE id=?";
                $params = [$nom,$prenom,$email,$role,$actif,$id];
                if ($mdp) { $sql = "UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,actif=?,mot_de_passe=? WHERE id=?"; $params = [$nom,$prenom,$email,$role,$actif,password_hash($mdp,PASSWORD_DEFAULT),$id]; }
                $db->prepare($sql)->execute($params);
                logAudit($db, 'update', 'utilisateur', $id, ['email'=>$email,'role'=>$role]);
            } else {
                if (!$mdp) { $msg = '<div class="alert alert-danger">Mot de passe requis.</div>'; }
                else {
                    $db->prepare("INSERT INTO utilisateurs (nom,prenom,email,role,actif,mot_de_passe) VALUES(?,?,?,?,?,?)")
                       ->execute([$nom,$prenom,$email,$role,$actif,password_hash($mdp,PASSWORD_DEFAULT)]);
                    logAudit($db, 'create', 'utilisateur', $db->lastInsertId(), ['email'=>$email,'role'=>$role]);
                }
            }
            if (empty($msg)) $msg='<div class="alert alert-success">Utilisateur sauvegardé.</div>';
        }
    } elseif ($action==='delete') {
        $idd = (int)$_POST['id'];
        if ($idd !== (int)$user['id']) {
            $db->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$idd]);
            logAudit($db, 'delete', 'utilisateur', $idd);
        }
        $msg='<div class="alert alert-success">Utilisateur supprimé.</div>';
    }
}

$utilisateurs = $db->query("SELECT * FROM utilisateurs ORDER BY role,nom")->fetchAll();
$edit = null;
if (!empty($_GET['edit'])) {
    $st = $db->prepare("SELECT * FROM utilisateurs WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
    if ($edit) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("modal-user"))</script>';
}

$roles_info = [
    'super_admin' => ['Super Admin','badge-purple','Accès total'],
    'admin'       => ['Admin','badge-blue','Gestion complète sauf config système'],
    'gestionnaire'=> ['Gestionnaire','badge-green','Produits et mouvements'],
    'caissier'    => ['Caissier','badge-orange','Sorties uniquement'],
    'lecteur'     => ['Lecteur','badge-gray','Lecture seule'],
];
?>

<?= $msg ?>

<div style="margin-bottom:20px;display:flex;justify-content:flex-end">
  <button class="btn btn-primary" onclick="openModal('modal-user')">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvel utilisateur
  </button>
</div>

<!-- Explication des rôles -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><div class="card-title">Rôles et permissions</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
      <?php foreach ($roles_info as $r=>$info): ?>
      <div style="padding:12px;border-radius:10px;background:var(--bg);border:1px solid var(--border)">
        <div style="margin-bottom:6px"><span class="badge <?= $info[1] ?>"><?= $info[0] ?></span></div>
        <div style="font-size:12px;color:var(--text-2)"><?= $info[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($utilisateurs as $u): ?>
        <?php $ri = $roles_info[$u['role']] ?? [$u['role'],'badge-gray','']; ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-family:'Manrope',sans-serif;font-weight:700;font-size:13px;color:white;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($u['prenom'],0,1,'UTF-8').mb_substr($u['nom'],0,1,'UTF-8'),'UTF-8') ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
                <?php if ($u['id']==(int)$_SESSION['user']['id']): ?><div style="font-size:11px;color:var(--primary)">← Vous</div><?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge <?= $ri[1] ?>"><?= $ri[0] ?></span></td>
          <td>
            <?php if ($u['actif']): ?>
            <span class="badge badge-green">Actif</span>
            <?php else: ?>
            <span class="badge badge-red">Inactif</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px;color:var(--muted)">
            <?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'Jamais' ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <?php if ($u['id'] !== (int)$_SESSION['user']['id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="modal-user">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= $edit?'Modifier':'Nouvel' ?> utilisateur</div>
      <button class="modal-close" onclick="closeModal('modal-user')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">
      <div class="modal-body">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Prénom *</label>
            <input class="form-control" name="prenom" required value="<?= htmlspecialchars($edit['prenom']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input class="form-control" name="nom" required value="<?= htmlspecialchars($edit['nom']??'') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($edit['email']??'') ?>">
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Rôle *</label>
            <select class="form-control form-select" name="role">
              <?php foreach ($roles_info as $r=>$ri): ?>
              <?php if ($user['role']!=='super_admin' && $r==='super_admin') continue; ?>
              <option value="<?= $r ?>" <?= ($edit['role']??'lecteur')===$r?'selected':'' ?>><?= $ri[0] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
              <input type="checkbox" name="actif" value="1" <?= ($edit['actif']??1)?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--primary)">
              Compte actif
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe <?= $edit?'(laisser vide pour conserver)':'*' ?></label>
          <input class="form-control" type="password" name="mot_de_passe" placeholder="<?= $edit?'••••••••':'Nouveau mot de passe' ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-user')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Sécurité : Tentatives de connexion -->
<?php if ($user['role'] === 'super_admin'): ?>
<div class="card" style="margin-top:24px">
  <div class="card-header"><div class="card-title">🔒 Tentatives de connexion récentes</div></div>
  <div style="overflow-x:auto">
    <?php
    $login_attempts = $db->query("SELECT la.*, u.prenom, u.nom FROM login_attempts la LEFT JOIN utilisateurs u ON la.email=u.email ORDER BY la.created_at DESC LIMIT 20")->fetchAll();
    if (empty($login_attempts)):
    ?>
    <div class="empty-state" style="padding:30px">
      <p style="color:var(--muted)">Aucune tentative de connexion enregistrée.</p>
    </div>
    <?php else: ?>
    <table>
      <thead><tr><th>Date</th><th>Adresse IP</th><th>Email</th><th>Utilisateur</th></tr></thead>
      <tbody>
      <?php foreach ($login_attempts as $la): ?>
      <tr>
        <td style="font-size:13px;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($la['created_at'])) ?></td>
        <td style="font-size:13px;font-family:monospace"><?= htmlspecialchars($la['ip_address']) ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($la['email']) ?></td>
        <td style="font-size:13px"><?= $la['nom'] ? htmlspecialchars($la['prenom'].' '.$la['nom']) : '<span style="color:var(--muted)">Inconnu</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Sécurité : Journal d'audit -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <div class="card-title">📋 Journal d'audit</div>
    <?php
    $audit_count = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    if ($audit_count > 50): ?>
    <span class="badge badge-blue"><?= $audit_count ?> entrées</span>
    <?php endif; ?>
  </div>
  <div style="overflow-x:auto">
    <?php
    $audit_logs = $db->query("
      SELECT al.*, u.prenom, u.nom
      FROM audit_log al
      LEFT JOIN utilisateurs u ON al.utilisateur_id=u.id
      ORDER BY al.created_at DESC LIMIT 50
    ")->fetchAll();
    if (empty($audit_logs)):
    ?>
    <div class="empty-state" style="padding:30px">
      <p style="color:var(--muted)">Aucune action enregistrée dans le journal.</p>
    </div>
    <?php else: ?>
    <table>
      <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détails</th></tr></thead>
      <tbody>
      <?php foreach ($audit_logs as $log):
        $action_labels = [
          'create' => '<span class="badge badge-green">Création</span>',
          'update' => '<span class="badge badge-blue">Modification</span>',
          'delete' => '<span class="badge badge-red">Suppression</span>',
          'login'  => '<span class="badge badge-purple">Connexion</span>',
          'login_fail' => '<span class="badge badge-orange">Échec connexion</span>',
        ];
        $label = $action_labels[$log['action']] ?? '<span class="badge badge-gray">'.htmlspecialchars($log['action']).'</span>';
        $entity_labels = [
          'produit' => '📦 Produit',
          'utilisateur' => '👤 Utilisateur',
          'categorie' => '🏷️ Catégorie',
          'fournisseur' => '🚚 Fournisseur',
          'client' => '👥 Client',
          'mouvement' => '🔄 Mouvement',
          'entreprise' => '⚙️ Entreprise',
        ];
        $entity = $entity_labels[$log['entity']] ?? $log['entity'];
      ?>
      <tr>
        <td style="font-size:13px;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
        <td style="font-size:13px"><?= $log['nom'] ? htmlspecialchars($log['prenom'].' '.$log['nom']) : '<span style="color:var(--muted)">Système</span>' ?></td>
        <td><?= $label ?></td>
        <td style="font-size:13px"><?= $entity ?><?php if ($log['entity_id']): ?> #<?= $log['entity_id'] ?><?php endif; ?></td>
        <td style="font-size:12px;color:var(--muted);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?php
          if ($log['new_values']) {
            $nv = json_decode($log['new_values'], true);
            if ($nv) echo htmlspecialchars(implode(', ', array_map(fn($k,$v)=>"$k: $v", array_keys(array_slice($nv,0,3)), array_values(array_slice($nv,0,3)))));
          }
          ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
