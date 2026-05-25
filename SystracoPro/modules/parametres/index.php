<?php // modules/parametres/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('parametres.manage');
$pageTitle = 'Paramètres Système';

// ── Classes de tickets : CRUD ──────────────────────────────
if (isset($_POST['save_class'])) {
    $cid = (int)($_POST['class_id'] ?? 0);
    $cnom = trim($_POST['class_nom'] ?? '');
    $ccode = trim($_POST['class_code'] ?? '');
    $cdesc = trim($_POST['class_desc'] ?? '');
    $cordre = (int)($_POST['class_ordre'] ?? 0) ?: 99;

    if (!$cnom || !$ccode) {
        flash('Nom et code obligatoires.', 'danger');
    } else {
        $ccode = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $ccode));
        if ($cid) {
            $pdo->prepare("UPDATE ticket_classes SET nom=?,code=?,description=?,ordre=? WHERE id=?")->execute([$cnom, $ccode, $cdesc, $cordre, $cid]);
            flash('Classe modifiée.');
        } else {
            $pdo->prepare("INSERT INTO ticket_classes (nom,code,description,ordre) VALUES (?,?,?,?)")->execute([$cnom, $ccode, $cdesc, $cordre]);
            flash('Classe ajoutée.');
        }
        redirect(BASE_URL . 'modules/parametres/index.php#classes');
    }
}
if (isset($_GET['del_class']) && isSuperAdmin()) {
    $pdo->prepare("DELETE FROM ticket_classes WHERE id=?")->execute([(int)$_GET['del_class']]);
    flash('Classe supprimée.', 'warning');
    redirect(BASE_URL . 'modules/parametres/index.php#classes');
}
if (isset($_GET['toggle_class'])) {
    $pdo->prepare("UPDATE ticket_classes SET actif=NOT actif WHERE id=?")->execute([(int)$_GET['toggle_class']]);
    flash('Statut modifié.');
    redirect(BASE_URL . 'modules/parametres/index.php#classes');
}

// ── Paramètres généraux ────────────────────────────────────
if (isset($_POST['save_params'])) {
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'param_') === 0) {
            $key = substr($k, 6);
            $val = trim($v);
            $pdo->prepare("INSERT INTO parametres (cle,valeur,modifie_par) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valeur=?,modifie_par=?")->execute([$key, $val, $_SESSION['user_id'], $val, $_SESSION['user_id']]);
        }
    }
    logAction($pdo, 'update_parametres', 'parametres', 'Paramètres mis à jour');
    flash('Paramètres sauvegardés.');
    redirect(BASE_URL . 'modules/parametres/index.php');
}

$params = $pdo->query("SELECT * FROM parametres ORDER BY cle")->fetchAll();
$pMap = [];
foreach ($params as $p) $pMap[$p['cle']] = $p;
$classes = $pdo->query("SELECT * FROM ticket_classes ORDER BY ordre, nom")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Paramètres</div>

<div style="display:flex;gap:12px;margin-bottom:16px;">
  <a href="#general" class="btn btn-primary btn-sm"><i class="fas fa-cog"></i> Général</a>
  <a href="#classes" class="btn btn-info btn-sm"><i class="fas fa-tags"></i> Classes de tickets</a>
</div>

<!-- ═══ PARAMÈTRES GÉNÉRAUX ═══ -->
<div id="general" class="card" style="max-width:800px;margin:0 auto 20px;">
  <div class="card-header"><h3><i class="fas fa-cog"></i> Paramètres généraux</h3></div>
  <form method="POST">
    <?= csrfField() ?>
    <div class="card-body">
      <?php
      $groups = [
          'Entreprise' => ['nom_entreprise' => 'Nom de l\'entreprise', 'slogan' => 'Slogan', 'adresse_siege' => 'Adresse siège', 'telephone_siege' => 'Téléphone', 'email_contact' => 'Email contact', 'monnaie' => 'Monnaie (ex: FCFA)'],
          'Numérotation' => ['prefix_ticket' => 'Préfixe ticket (TKT)', 'prefix_bordereau' => 'Préfixe bordereau (BRD)', 'prefix_voyage' => 'Préfixe voyage (VOY)'],
          'Règles métier' => ['limite_depense' => 'Limite dépense chef guichet (FCFA)', 'delai_reservation' => 'Délai expiration réservation (heures)'],
      ];
      foreach ($groups as $gname => $gfields): ?>
      <div class="fsec" style="margin-bottom:16px;">
        <div class="fsec-t"><i class="fas fa-folder"></i> <?= $gname ?></div>
        <div class="form-grid">
          <?php foreach ($gfields as $key => $label): $val = $pMap[$key]['valeur'] ?? ''; ?>
          <div class="fg"><label class="flbl"><?= $label ?></label><input type="text" name="param_<?= $key ?>" class="fc" value="<?= sanitize($val) ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:flex-end;margin-top:8px;">
        <button type="submit" name="save_params" value="1" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Sauvegarder</button>
      </div>
    </div>
  </form>
</div>

<!-- ═══ CLASSES DE TICKETS ═══ -->
<div id="classes" class="card" style="max-width:800px;margin:0 auto;">
  <div class="card-header">
    <h3><i class="fas fa-tags"></i> Classes de tickets</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('class-modal');resetClassForm()"><i class="fas fa-plus"></i> Ajouter</button>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Ordre</th><th>Nom</th><th>Code</th><th>Description</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($classes as $c): ?>
        <tr>
          <td style="text-align:center;font-weight:700;"><?= $c['ordre'] ?></td>
          <td><strong><?= sanitize($c['nom']) ?></strong></td>
          <td><code><?= sanitize($c['code']) ?></code></td>
          <td style="font-size:12px;color:var(--text2);"><?= sanitize($c['description'] ?? '—') ?></td>
          <td><?= $c['actif'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-red">Inactif</span>' ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <button class="btn btn-xs btn-warning" onclick='editClass(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
              <a href="?toggle_class=<?= $c['id'] ?>#classes" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $c['actif'] ? 'on text-success' : 'off' ?>"></i></a>
              <?php if (isSuperAdmin()): ?>
              <a href="?del_class=<?= $c['id'] ?>#classes" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette classe ?')"><i class="fas fa-trash"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($classes)): ?>
        <tr><td colspan="6" class="t-empty"><i class="fas fa-tags"></i>Aucune classe définie</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CLASSE -->
<div class="modal-over" id="class-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-tags"></i> Classe de ticket</h3><button class="modal-x" onclick="closeModal('class-modal')">✕</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <input type="hidden" name="class_id" id="cl-id">
        <input type="hidden" name="save_class" value="1">
        <div class="form-grid">
          <div class="fg"><label class="flbl">Nom <span class="freq">*</span></label><input type="text" name="class_nom" id="cl-nom" class="fc" required></div>
          <div class="fg"><label class="flbl">Code <span class="freq">*</span></label><input type="text" name="class_code" id="cl-code" class="fc" required style="text-transform:lowercase;" placeholder="ex: premiere"></div>
          <div class="fg"><label class="flbl">Ordre d'affichage</label><input type="number" name="class_ordre" id="cl-ordre" class="fc" min="0" value="99"></div>
          <div class="fg full"><label class="flbl">Description</label><input type="text" name="class_desc" id="cl-desc" class="fc" placeholder="Description courte..."></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('class-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function resetClassForm() {
  document.getElementById('cl-id').value = '';
  document.getElementById('cl-nom').value = '';
  document.getElementById('cl-code').value = '';
  document.getElementById('cl-ordre').value = '99';
  document.getElementById('cl-desc').value = '';
}
function editClass(c) {
  document.getElementById('cl-id').value = c.id;
  document.getElementById('cl-nom').value = c.nom || '';
  document.getElementById('cl-code').value = c.code || '';
  document.getElementById('cl-ordre').value = c.ordre || 99;
  document.getElementById('cl-desc').value = c.description || '';
  openModal('class-modal');
}
</script>
<?php include '../../includes/footer.php'; ?>