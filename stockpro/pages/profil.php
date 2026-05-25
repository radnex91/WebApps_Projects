<?php
$page_title = 'Mon profil';
$page_id = 'profil';
require_once '../includes/header.php';
requireAuth();
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'profil') {
        $nom    = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email  = trim($_POST['email']);
        if (!validateString($nom, 100, true) || !validateString($prenom, 100, true)) {
            $msg = '<div class="alert alert-danger">Le nom et le prénom sont obligatoires.</div>';
        } elseif (!validateEmail($email)) {
            $msg = '<div class="alert alert-danger">Email invalide.</div>';
        } else {
            $db->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=? WHERE id=?")->execute([$nom,$prenom,$email,$_SESSION['user']['id']]);
            $_SESSION['user']['nom']    = $nom;
            $_SESSION['user']['prenom'] = $prenom;
            $_SESSION['user']['email']  = $email;
            logAudit($db, 'update', 'utilisateur', $_SESSION['user']['id'], ['email'=>$email]);
            $msg = '<div class="alert alert-success">Profil mis à jour.</div>';
        }
    } elseif ($action === 'mdp') {
        $ancien  = $_POST['ancien_mdp'];
        $nouveau = $_POST['nouveau_mdp'];
        $confirm = $_POST['confirm_mdp'];
        $current = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id=?");
        $current->execute([$_SESSION['user']['id']]);
        $current = $current->fetchColumn();
        if (!password_verify($ancien, $current)) {
            $msg = '<div class="alert alert-danger">Ancien mot de passe incorrect.</div>';
        } elseif ($nouveau !== $confirm) {
            $msg = '<div class="alert alert-danger">Les mots de passe ne correspondent pas.</div>';
        } elseif (strlen($nouveau) < 6) {
            $msg = '<div class="alert alert-danger">Le mot de passe doit faire au moins 6 caractères.</div>';
        } else {
            $db->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([password_hash($nouveau, PASSWORD_DEFAULT), $_SESSION['user']['id']]);
            $msg = '<div class="alert alert-success">Mot de passe modifié avec succès.</div>';
        }
    }
}

$u = $_SESSION['user'];
$roles_labels = ['super_admin'=>'Super Administrateur','admin'=>'Administrateur','gestionnaire'=>'Gestionnaire','caissier'=>'Caissier','lecteur'=>'Lecteur'];

// Stats utilisateur
$nb_mvt = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE utilisateur_id=?");
$nb_mvt->execute([$u['id']]);
$nb_mvt = $nb_mvt->fetchColumn();
?>

<?= $msg ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;max-width:860px;align-items:start">
    <!-- Carte profil -->
    <div>
        <div class="card" style="text-align:center;padding:28px 20px;margin-bottom:16px">
            <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-family:'Manrope',sans-serif;font-weight:800;font-size:24px;color:white;margin:0 auto 16px">
                <?= mb_strtoupper(mb_substr($u['prenom'],0,1,'UTF-8').mb_substr($u['nom'],0,1,'UTF-8'),'UTF-8') ?>
            </div>
            <div style="font-family:'Manrope',sans-serif;font-weight:700;font-size:18px;margin-bottom:4px"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
            <div style="color:var(--muted);font-size:13px;margin-bottom:12px"><?= htmlspecialchars($u['email']) ?></div>
            <span class="badge badge-purple"><?= $roles_labels[$u['role']] ?? $u['role'] ?></span>
        </div>
        <div class="card" style="padding:20px">
            <div style="font-family:'Manrope',sans-serif;font-weight:700;font-size:13px;margin-bottom:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Activité</div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                <span style="font-size:13px;color:var(--text-2)">Mouvements créés</span>
                <span style="font-weight:700"><?= $nb_mvt ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0">
                <span style="font-size:13px;color:var(--text-2)">Dernière connexion</span>
                <span style="font-size:12px;color:var(--muted)"><?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'N/A' ?></span>
            </div>
        </div>
    </div>

    <div>
        <!-- Modifier profil -->
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">Informations personnelles</div></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profil">
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label class="form-label">Prénom</label>
                            <input class="form-control" name="prenom" required value="<?= htmlspecialchars($u['prenom']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input class="form-control" name="nom" required value="<?= htmlspecialchars($u['nom']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($u['email']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </form>
            </div>
        </div>

        <!-- Changer mot de passe -->
        <div class="card">
            <div class="card-header"><div class="card-title">Changer le mot de passe</div></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mdp">
                    <div class="form-group">
                        <label class="form-label">Ancien mot de passe</label>
                        <input class="form-control" type="password" name="ancien_mdp" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input class="form-control" type="password" name="nouveau_mdp" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmer le nouveau</label>
                        <input class="form-control" type="password" name="confirm_mdp" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Changer le mot de passe</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
