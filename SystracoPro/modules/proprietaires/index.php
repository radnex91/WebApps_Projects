<?php
// modules/proprietaires/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('proprietaires.manage');
$pageTitle = 'Propriétaires';

if (isset($_GET['del']) && isSuperAdmin()) {
    $id = (int)$_GET['del'];
    $nb = $pdo->prepare("SELECT COUNT(*) FROM groupes WHERE proprietaire_id=?");
    $nb->execute([$id]);
    if ((int)$nb->fetchColumn() > 0) {
        flash('Impossible : des groupes appartiennent à ce propriétaire. Supprimez-les d\'abord.', 'danger');
    } else {
        $pdo->prepare("DELETE FROM proprietaires WHERE id=?")->execute([$id]);
        logAction($pdo, 'supprime_proprietaire', 'proprietaires', "Propriétaire $id supprimé");
        flash('Propriétaire supprimé.', 'warning');
    }
    redirect(BASE_URL . 'modules/proprietaires/');
}
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE proprietaires SET actif=NOT actif WHERE id=?")->execute([$id]);
    flash('Statut modifié.');
    redirect(BASE_URL . 'modules/proprietaires/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $tel = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $type = $_POST['type'] ?? 'personne';

    if (!$nom) {
        flash('Nom obligatoire.', 'danger');
    } else {
        if ($id) {
            $pdo->prepare("UPDATE proprietaires SET nom=?,telephone=?,email=?,adresse=?,type=? WHERE id=?")
                ->execute([$nom, $tel, $email, $adresse, $type, $id]);
            logAction($pdo, 'modifie_proprietaire', 'proprietaires', "Propriétaire $nom modifié");
            flash('Propriétaire modifié.');
        } else {
            $pdo->prepare("INSERT INTO proprietaires (nom,telephone,email,adresse,type) VALUES (?,?,?,?,?)")
                ->execute([$nom, $tel, $email, $adresse, $type]);
            logAction($pdo, 'ajoute_proprietaire', 'proprietaires', "Propriétaire $nom ajouté");
            flash('Propriétaire ajouté.');
        }
        redirect(BASE_URL . 'modules/proprietaires/');
    }
}

$proprietaires = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM groupes g WHERE g.proprietaire_id=p.id) as nb_groupes FROM proprietaires p ORDER BY p.actif DESC, p.nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Propriétaires</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-user-tie"></i> Propriétaires (<?= count($proprietaires) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="resetPForm();openModal('prop-modal')"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom / Raison sociale</th><th>Type</th><th>Téléphone</th><th>Email</th><th>Groupes</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($proprietaires as $p): ?>
          <tr>
            <td><strong><?= sanitize($p['nom']) ?></strong></td>
            <td><span class="badge <?= $p['type'] === 'societe' ? 'badge-purple' : 'badge-blue' ?>"><?= $p['type'] === 'societe' ? 'Société' : 'Personne' ?></span></td>
            <td><?= sanitize($p['telephone'] ?? '—') ?></td>
            <td style="font-size:12px;"><?= sanitize($p['email'] ?? '—') ?></td>
            <td style="text-align:center;"><strong><?= $p['nb_groupes'] ?></strong></td>
            <td><?= $p['actif'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Inactif</span>' ?></td>
            <td>
              <div style="display:flex;gap:3px;">
                <button class="btn btn-xs btn-warning" onclick='editP(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
                <a href="?toggle=<?= $p['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $p['actif'] ? 'on text-success' : 'off' ?>"></i></a>
                <?php if (isSuperAdmin()): ?>
                <a href="?del=<?= $p['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce propriétaire ?')"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($proprietaires)): ?>
          <tr><td colspan="7" class="t-empty"><i class="fas fa-user-tie"></i>Aucun propriétaire</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-over" id="prop-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-user-tie"></i> Propriétaire</h3><button class="modal-x" onclick="closeModal('prop-modal')">✕</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <input type="hidden" name="id" id="p-id">
        <div class="form-grid">
          <div class="fg full"><label class="flbl">Nom / Raison sociale <span class="freq">*</span></label><input type="text" name="nom" id="p-nom" class="fc" required></div>
          <div class="fg"><label class="flbl">Type</label><select name="type" id="p-type" class="fc"><option value="personne">Personne physique</option><option value="societe">Société</option></select></div>
          <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="telephone" id="p-tel" class="fc" placeholder="+237 6XX XXX XXX"></div>
          <div class="fg"><label class="flbl">Email</label><input type="email" name="email" id="p-email" class="fc"></div>
          <div class="fg full"><label class="flbl">Adresse</label><textarea name="adresse" id="p-adresse" class="fc" rows="2"></textarea></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('prop-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function resetPForm(){document.getElementById('p-id').value='';document.getElementById('p-nom').value='';document.getElementById('p-type').value='personne';document.getElementById('p-tel').value='';document.getElementById('p-email').value='';document.getElementById('p-adresse').value='';}
function editP(p) {
  document.getElementById('p-id').value = p.id;
  document.getElementById('p-nom').value = p.nom || '';
  document.getElementById('p-type').value = p.type || 'personne';
  document.getElementById('p-tel').value = p.telephone || '';
  document.getElementById('p-email').value = p.email || '';
  document.getElementById('p-adresse').value = p.adresse || '';
  openModal('prop-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>