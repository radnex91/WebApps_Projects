<?php
$pageTitle = 'Gestion des utilisateurs';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('utilisateurs_gerer');

$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $required = ['nom','prenom','username','email','password'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) $errors[] = "Le champ $f est requis.";
        }
        $existing = Database::fetchOne("SELECT id FROM utilisateurs WHERE username=? OR email=?",
            [trim($_POST['username']), trim($_POST['email'])]);
        if ($existing) $errors[] = "Nom d'utilisateur ou email déjà utilisé.";
        
        if (!$errors) {
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost'=>12]);
            Database::execute(
                "INSERT INTO utilisateurs (nom,prenom,email,username,password_hash,role_id) VALUES (?,?,?,?,?,?)",
                [trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['email']),
                 trim($_POST['username']), $hash, intval($_POST['role_id'])]
            );
            Auth::logAction('USER_CREE','utilisateurs');
            $_SESSION['flash']=['type'=>'success','message'=>'Utilisateur créé.'];
            header('Location: utilisateurs.php'); exit;
        }
    } elseif ($action === 'update') {
        $uid = intval($_POST['id']);
        $actif = isset($_POST['actif']) ? 1 : 0;
        $agenceId = !empty($_POST['agence_id']) ? intval($_POST['agence_id']) : null;
        $data = [trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['email']),
                 intval($_POST['role_id']), $actif, $agenceId, $uid];
        $sql = "UPDATE utilisateurs SET nom=?,prenom=?,email=?,role_id=?,actif=?,agence_id=?";
        if (!empty($_POST['password'])) {
            $sql .= ",password_hash=?";
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $data = [trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['email']),
                     intval($_POST['role_id']), $actif, $agenceId, $hash, $uid];
        }
        $sql .= " WHERE id=?";
        Database::execute($sql, $data);
        Auth::logAction('USER_MODIFIE','utilisateurs',$uid);
        $_SESSION['flash']=['type'=>'success','message'=>'Utilisateur mis à jour.'];
        header('Location: utilisateurs.php'); exit;
    } elseif ($action === 'delete') {
        $uid = intval($_POST['id']);
        $current = Auth::currentUser();
        if ($uid !== $current['id']) {
            Database::execute("DELETE FROM utilisateurs WHERE id=?", [$uid]);
            Auth::logAction('USER_SUPPRIME','utilisateurs',$uid);
            $_SESSION['flash']=['type'=>'success','message'=>'Utilisateur supprimé.'];
        } else {
            $_SESSION['flash']=['type'=>'error','message'=>'Vous ne pouvez pas vous supprimer vous-même.'];
        }
        header('Location: utilisateurs.php'); exit;
    }
}

$users = Database::fetchAll(
    "SELECT u.*, r.nom AS role_nom, r.niveau AS role_niveau,
     (SELECT COUNT(*) FROM colis WHERE cree_par=u.id) AS nb_colis,
     (SELECT COUNT(*) FROM voyages WHERE cree_par=u.id) AS nb_voyages,
     a.nom AS agence_nom
     FROM utilisateurs u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN agences a ON u.agence_id = a.id
     ORDER BY r.niveau DESC, u.nom"
);
$allRoles = Database::fetchAll("SELECT * FROM roles WHERE actif = 1 ORDER BY niveau DESC");
$agences = Database::fetchAll("SELECT id, nom FROM agences ORDER BY nom");
$roleColors = ['admin'=>'badge-danger','superviseur'=>'badge-warning','operateur'=>'badge-info','guichetier'=>'badge-info'];
$roleIcons  = ['admin'=>'fa-shield','superviseur'=>'fa-eye','operateur'=>'fa-user','guichetier'=>'fa-user'];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700">Utilisateurs</h2>
        <p class="text-muted" style="font-size:.85rem"><?= count($users) ?> comptes configurés</p>
    </div>
    <button class="btn btn-primary" data-modal="modal-add-user"><i class="fas fa-user-plus"></i> Nouvel utilisateur</button>
</div>

<?php if ($errors): ?>
<div class="flash flash-error mb-2"><?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>Utilisateur</th><th>Contact</th><th>Rôle</th><th>Agence</th><th>Activité</th><th>Dernière cnx</th><th>Statut</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                             background:linear-gradient(135deg,var(--primary),var(--accent));
                             display:flex;align-items:center;justify-content:center;
                             font-weight:700;color:white;font-size:.85rem">
                            <?= strtoupper(substr($u['prenom'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
                            <div style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted)">@<?= htmlspecialchars($u['username']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge <?= $roleColors[$u['role_nom']] ?? 'badge-secondary' ?>">
                        <i class="fas <?= $roleIcons[$u['role_nom']] ?? 'fa-user' ?>" style="margin-right:4px"></i>
                        <?= ucfirst($u['role_nom']) ?>
                    </span>
                </td>
                <td style="font-size:.82rem">
                    <?= $u['agence_nom'] ? '<i class="fas fa-building" style="margin-right:4px;color:var(--primary)"></i>' . htmlspecialchars($u['agence_nom']) : '<span style="color:var(--text-dim)">—</span>' ?>
                </td>
                <td>
                    <div style="display:flex;gap:12px;font-size:.82rem">
                        <span title="Colis créés"><i class="fas fa-box" style="color:var(--primary);margin-right:4px"></i><?= $u['nb_colis'] ?></span>
                        <span title="Voyages créés"><i class="fas fa-route" style="color:var(--info);margin-right:4px"></i><?= $u['nb_voyages'] ?></span>
                    </div>
                </td>
                <td style="font-size:.78rem;color:var(--text-muted)">
                    <?= $u['derniere_connexion'] ? formatDate($u['derniere_connexion'],'d/m/Y H:i') : 'Jamais' ?>
                </td>
                <td>
                    <span class="badge <?= $u['actif']?'badge-success':'badge-danger' ?>"><?= $u['actif']?'Actif':'Inactif' ?></span>
                </td>
                <td>
                    <?php if ($u['id'] != $user['id']): ?>
                    <div class="action-btns">
<button class="action-btn edit" title="Modifier"
                            onclick='openEditUser(<?= json_encode($u) ?>)'>
                            <i class="fas fa-pencil"></i>
                        </button>
                        <form method="post">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="action-btn del" title="Supprimer"
                                    data-confirm="Confirmer la suppression de <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?> ?">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <span style="font-size:.75rem;color:var(--text-dim)">Moi</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ADD USER -->
<div class="modal-backdrop" id="modal-add-user">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvel utilisateur</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Prénom <span class="req">*</span></label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom <span class="req">*</span></label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom d'utilisateur <span class="req">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="prenom.nom">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="req">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle <span class="req">*</span></label>
                    <select name="role_id" class="form-control" required>
                        <?php foreach ($allRoles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars(ucfirst($r['nom'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mot de passe <span class="req">*</span></label> <span class="req">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div class="modal-backdrop" id="modal-edit-user">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier l'utilisateur</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="eu_id">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" id="eu_prenom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" id="eu_nom" class="form-control" required>
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Email</label>
                    <input type="text" name="email" id="eu_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle</label>
                    <select name="role_id" id="eu_role_id" class="form-control">
                        <?php foreach ($allRoles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars(ucfirst($r['nom'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Agence</label>
                    <select name="agence_id" id="eu_agence_id" class="form-control">
                        <option value="">Aucune</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe <small style="color:var(--text-muted)">(laisser vide = inchangé)</small></label>
                    <input type="password" name="password" class="form-control" minlength="6" placeholder="••••••">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="actif" id="eu_actif" value="1"> Compte actif
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUser(u) {
    document.getElementById('eu_id').value     = u.id;
    document.getElementById('eu_prenom').value  = u.prenom;
    document.getElementById('eu_nom').value     = u.nom;
    document.getElementById('eu_email').value   = u.email ? u.email : '';
    document.getElementById('eu_role_id').value = u.role_id || u.role_nom;
    document.getElementById('eu_agence_id').value = u.agence_id || '';
    document.getElementById('eu_actif').checked = u.actif == 1;
    document.getElementById('modal-edit-user').classList.add('open');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
