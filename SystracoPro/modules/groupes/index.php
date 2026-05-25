<?php
// modules/groupes/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('groupes.manage');
$pageTitle = 'Groupes de Véhicules';

if (isset($_GET['del']) && isSuperAdmin()) {
    $id = (int)$_GET['del'];
    $nb = $pdo->prepare("SELECT COUNT(*) FROM vehicules WHERE groupe_id=?");
    $nb->execute([$id]);
    if ((int)$nb->fetchColumn() > 0) {
        flash('Impossible : des véhicules appartiennent à ce groupe. Modifiez-les d\'abord.', 'danger');
    } else {
        $pdo->prepare("DELETE FROM groupes WHERE id=?")->execute([$id]);
        logAction($pdo, 'supprime_groupe', 'groupes', "Groupe $id supprimé");
        flash('Groupe supprimé.', 'warning');
    }
    redirect(BASE_URL . 'modules/groupes/');
}
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE groupes SET actif=NOT actif WHERE id=?")->execute([$id]);
    flash('Statut modifié.');
    redirect(BASE_URL . 'modules/groupes/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $prop_id = (int)($_POST['proprietaire_id'] ?? 0);
    $agid = (int)($_POST['agence_id'] ?? 0) ?: null;

    if (!$nom || !$prop_id) {
        flash('Nom et propriétaire obligatoires.', 'danger');
    } else {
        if (!$code) {
            $code = 'GRP-' . str_pad($pdo->query("SELECT COUNT(*)+1 FROM groupes")->fetchColumn(), 3, '0', STR_PAD_LEFT);
        }
        if ($id) {
            $pdo->prepare("UPDATE groupes SET nom=?,code=?,proprietaire_id=?,agence_id=? WHERE id=?")
                ->execute([$nom, $code, $prop_id, $agid, $id]);
            logAction($pdo, 'modifie_groupe', 'groupes', "Groupe $nom modifié");
            flash('Groupe modifié.');
        } else {
            $pdo->prepare("INSERT INTO groupes (nom,code,proprietaire_id,agence_id) VALUES (?,?,?,?)")
                ->execute([$nom, $code, $prop_id, $agid]);
            logAction($pdo, 'ajoute_groupe', 'groupes', "Groupe $nom ajouté");
            flash('Groupe ajouté.');
        }
        redirect(BASE_URL . 'modules/groupes/');
    }
}

$groupes = $pdo->query("SELECT g.*, p.nom as prop_nom, p.type as prop_type, a.ville as agence_ville, (SELECT COUNT(*) FROM vehicules v WHERE v.groupe_id=g.id) as nb_vehicules FROM groupes g JOIN proprietaires p ON g.proprietaire_id=p.id LEFT JOIN agences a ON g.agence_id=a.id ORDER BY g.actif DESC, g.nom")->fetchAll();
$proprietaires = $pdo->query("SELECT * FROM proprietaires WHERE actif=1 ORDER BY nom")->fetchAll();
$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Groupes</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-layer-group"></i> Groupes de Véhicules (<?= count($groupes) ?>)</h3>
    <button class="btn btn-primary btn-sm" onclick="resetGForm();openModal('grp-modal')"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
</div>

<?php foreach ($groupes as $g): ?>
<div class="card" style="margin-bottom:12px;">
  <div class="card-header">
    <h3>
      <i class="fas fa-layer-group" style="color:var(--primary);"></i>
      <?= sanitize($g['nom']) ?>
      <span style="font-size:11px;color:var(--text3);font-weight:400;margin-left:8px;"><?= sanitize($g['code']) ?></span>
      <?php if (!$g['actif']): ?><span class="badge badge-red" style="margin-left:6px;">Inactif</span><?php endif; ?>
      <span class="badge badge-blue" style="margin-left:6px;"><?= $g['nb_vehicules'] ?> véhicule(s)</span>
    </h3>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-xs btn-warning" onclick='editG(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
      <a href="?toggle=<?= $g['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $g['actif'] ? 'on text-success' : 'off' ?>"></i></a>
      <?php if (isSuperAdmin()): ?>
      <a href="?del=<?= $g['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce groupe ?')"><i class="fas fa-trash"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php
  $vehGrp = $pdo->prepare("SELECT v.*, a.ville FROM vehicules v LEFT JOIN agences a ON v.agence_id=a.id WHERE v.groupe_id=? ORDER BY v.immatriculation");
  $vehGrp->execute([$g['id']]);
  $vehs = $vehGrp->fetchAll();
  if ($vehs):
  ?>
  <div class="card-body" style="padding:0;">
    <div style="padding:8px 14px;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;background:var(--bg);border-bottom:1px solid var(--border);">
      <i class="fas fa-bus"></i> Propriétaire : <strong><?= sanitize($g['prop_nom']) ?></strong>
      <?php if ($g['agence_ville']): ?> — Agence : <?= sanitize($g['agence_ville']) ?><?php endif; ?>
    </div>
    <table>
      <thead><tr><th>Immatriculation</th><th>Marque / Modèle</th><th>Type</th><th>Capacité</th><th>Agence</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($vehs as $v): ?>
        <tr>
          <td><strong><?= sanitize($v['immatriculation']) ?></strong></td>
          <td><?= sanitize($v['marque'] . ' ' . $v['modele']) ?></td>
          <td><span class="badge badge-blue"><?= sanitize($v['type']) ?></span></td>
          <td style="text-align:center;"><?= $v['capacite'] ?></td>
          <td><?= sanitize($v['ville'] ?? '—') ?></td>
          <td><span class="tag-statut st-<?= $v['statut'] ?>"><?= statutLabel($v['statut']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="card-body" style="padding:12px;font-size:12px;color:var(--text3);">
    <i class="fas fa-info-circle"></i> Propriétaire : <strong><?= sanitize($g['prop_nom']) ?></strong> — Aucun véhicule dans ce groupe.
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($groupes)): ?>
<div class="empty card"><i class="fas fa-layer-group"></i><h3>Aucun groupe</h3><p>Créez un groupe de véhicules rattaché à un propriétaire.</p></div>
<?php endif; ?>

<!-- MODAL -->
<div class="modal-over" id="grp-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-layer-group"></i> Groupe</h3><button class="modal-x" onclick="closeModal('grp-modal')">✕</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <input type="hidden" name="id" id="g-id">
        <div class="form-grid">
          <div class="fg"><label class="flbl">Nom du groupe <span class="freq">*</span></label><input type="text" name="nom" id="g-nom" class="fc" required></div>
          <div class="fg"><label class="flbl">Code</label><input type="text" name="code" id="g-code" class="fc" placeholder="Auto si vide" style="text-transform:uppercase;"></div>
          <div class="fg"><label class="flbl">Propriétaire <span class="freq">*</span></label><select name="proprietaire_id" id="g-prop" class="fc" required><option value="">— Sélectionner —</option><?php foreach ($proprietaires as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['nom']) ?></option><?php endforeach; ?></select></div>
          <div class="fg"><label class="flbl">Agence (optionnel)</label><select name="agence_id" id="g-agence" class="fc"><option value="">— Aucune —</option><?php foreach ($agences as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['ville']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('grp-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function resetGForm(){document.getElementById('g-id').value='';document.getElementById('g-nom').value='';document.getElementById('g-code').value='';document.getElementById('g-prop').value='';document.getElementById('g-agence').value='';}
function editG(g) {
  document.getElementById('g-id').value = g.id;
  document.getElementById('g-nom').value = g.nom || '';
  document.getElementById('g-code').value = g.code || '';
  document.getElementById('g-prop').value = g.proprietaire_id || '';
  document.getElementById('g-agence').value = g.agence_id || '';
  openModal('grp-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>