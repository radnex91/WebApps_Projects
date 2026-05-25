<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('referentiels');
$pageTitle = 'Référentiels';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_beneficiaire') {
        try {
            $db->prepare("INSERT INTO beneficiaires (type,code,nom,adresse,telephone,email,rib) VALUES (?,?,?,?,?,?,?)")
               ->execute([$_POST['type'],$_POST['code']??null,$_POST['nom'],$_POST['adresse']??'',$_POST['telephone']??'',$_POST['email']??'',$_POST['rib']??'']);
            flash('success','Bénéficiaire ajouté.');
        } catch(PDOException $e) { flash('danger','Code déjà existant.'); }
        header('Location: index.php?tab=beneficiaires'); exit;
    }
    if ($action === 'add_fournisseur') {
        try {
            $db->prepare("INSERT INTO fournisseurs (code,nom,adresse,telephone,email,rib,delai_paiement) VALUES (?,?,?,?,?,?,?)")
               ->execute([$_POST['code']??null,$_POST['nom'],$_POST['adresse']??'',$_POST['telephone']??'',$_POST['email']??'',$_POST['rib']??'',(int)($_POST['delai']??30)]);
            flash('success','Fournisseur ajouté.');
        } catch(PDOException $e) { flash('danger','Erreur: '.$e->getMessage()); }
        header('Location: index.php?tab=fournisseurs'); exit;
    }
    if ($action === 'add_type_op') {
        try {
            $db->prepare("INSERT INTO types_operations (code,libelle,sens,categorie) VALUES (?,?,?,?)")
               ->execute([strtoupper(trim($_POST['code'])),trim($_POST['libelle']),$_POST['sens'],$_POST['categorie']]);
            flash('success','Type d\'opération ajouté.');
        } catch(PDOException $e) { flash('danger','Code déjà existant.'); }
        header('Location: index.php?tab=types'); exit;
    }
    if ($action === 'update_type_op') {
        $db->prepare("UPDATE types_operations SET code=?, libelle=?, sens=?, categorie=? WHERE id=?")
           ->execute([strtoupper(trim($_POST['code'])),trim($_POST['libelle']),$_POST['sens'],$_POST['categorie'],(int)$_POST['type_id']]);
        flash('success','Type d\'opération mis à jour.');
        header('Location: index.php?tab=types'); exit;
    }
    if ($action === 'toggle_type_op') {
        $id = (int)$_POST['id'];
        $curr = $db->prepare("SELECT statut FROM types_operations WHERE id=?"); $curr->execute([$id]); $curr=$curr->fetch();
        $db->prepare("UPDATE types_operations SET statut=? WHERE id=?")->execute([$curr['statut']==='actif'?'inactif':'actif',$id]);
        header('Location: index.php?tab=types'); exit;
    }
    if ($action === 'add_compte') {
        try {
            $db->prepare("INSERT INTO plan_comptable (compte,libelle,classe,type_compte,sens_normal) VALUES (?,?,?,?,?)")
               ->execute([$_POST['compte'],$_POST['libelle'],(int)$_POST['classe'],$_POST['type_compte'],$_POST['sens_normal']]);
            flash('success','Compte ajouté au plan comptable.');
        } catch(PDOException $e) { flash('danger','Numéro de compte déjà existant.'); }
        header('Location: index.php?tab=plan'); exit;
    }

    // ── Destinations ──
    if ($action === 'add_destination') {
        try {
            $db->prepare("INSERT INTO destinations (code, libelle) VALUES (?,?)")
               ->execute([strtoupper(trim($_POST['code'])), trim($_POST['libelle'])]);
            flash('success','Destination créée.');
        } catch(PDOException $e) { flash('danger','Code déjà existant.'); }
        header('Location: index.php?tab=destinations'); exit;
    }
    if ($action === 'update_destination') {
        $dstId = (int)$_POST['destination_id'];
        try {
            $db->prepare("UPDATE destinations SET code=?, libelle=? WHERE id=?")
               ->execute([strtoupper(trim($_POST['code'])), trim($_POST['libelle']), $dstId]);
            flash('success','Destination mise à jour.');
        } catch(PDOException $e) { flash('danger','Erreur : code déjà utilisé.'); }
        header('Location: index.php?tab=destinations'); exit;
    }
    if ($action === 'toggle_destination') {
        $dstId = (int)$_POST['id'];
        $curr = $db->prepare("SELECT statut FROM destinations WHERE id=?"); $curr->execute([$dstId]); $curr=$curr->fetch();
        $db->prepare("UPDATE destinations SET statut=? WHERE id=?")->execute([$curr['statut']==='actif'?'inactif':'actif',$dstId]);
        flash('success','Statut de la destination mis à jour.');
        header('Location: index.php?tab=destinations'); exit;
    }
    if ($action === 'delete_destination') {
        $dstId = (int)$_POST['id'];
        // Vérifier si la destination est utilisée
        $usedInOps = $db->prepare("SELECT COUNT(*) FROM operations_caisse WHERE destination_id=?"); $usedInOps->execute([$dstId]); $countOps = (int)$usedInOps->fetchColumn();
        $usedInEng = $db->prepare("SELECT COUNT(*) FROM demandes_engagement WHERE destination_id=?"); $usedInEng->execute([$dstId]); $countEng = (int)$usedInEng->fetchColumn();
        if ($countOps > 0 || $countEng > 0) {
            flash('danger','Impossible de supprimer : cette destination est utilisée dans ' . $countOps . ' opération(s) caisse et ' . $countEng . ' engagement(s). Désactivez-la plutôt.');
        } else {
            $db->prepare("DELETE FROM destinations WHERE id=?")->execute([$dstId]);
            flash('success','Destination supprimée.');
        }
        header('Location: index.php?tab=destinations'); exit;
    }

    // ── Groupes propriétaires ──
    if ($action === 'add_groupe_proprietaire') {
        try {
            $db->prepare("INSERT INTO groupes_proprietaires (code, libelle) VALUES (?,?)")
               ->execute([strtoupper(trim($_POST['code'])), trim($_POST['libelle'])]);
            flash('success','Groupe propriétaire créé.');
        } catch(PDOException $e) { flash('danger','Code déjà existant.'); }
        header('Location: index.php?tab=groupes_proprietaires'); exit;
    }
    if ($action === 'update_groupe_proprietaire') {
        $gpId = (int)$_POST['groupe_proprietaire_id'];
        try {
            $db->prepare("UPDATE groupes_proprietaires SET code=?, libelle=? WHERE id=?")
               ->execute([strtoupper(trim($_POST['code'])), trim($_POST['libelle']), $gpId]);
            flash('success','Groupe propriétaire mis à jour.');
        } catch(PDOException $e) { flash('danger','Erreur : code déjà utilisé.'); }
        header('Location: index.php?tab=groupes_proprietaires'); exit;
    }
    if ($action === 'toggle_groupe_proprietaire') {
        $gpId = (int)$_POST['id'];
        $curr = $db->prepare("SELECT statut FROM groupes_proprietaires WHERE id=?"); $curr->execute([$gpId]); $curr=$curr->fetch();
        $db->prepare("UPDATE groupes_proprietaires SET statut=? WHERE id=?")->execute([$curr['statut']==='actif'?'inactif':'actif',$gpId]);
        flash('success','Statut du groupe propriétaire mis à jour.');
        header('Location: index.php?tab=groupes_proprietaires'); exit;
    }
    if ($action === 'delete_groupe_proprietaire') {
        $gpId = (int)$_POST['id'];
        $usedInOps = $db->prepare("SELECT COUNT(*) FROM operations_caisse WHERE groupe_proprietaire_id=?"); $usedInOps->execute([$gpId]); $countOps = (int)$usedInOps->fetchColumn();
        $usedInEng = $db->prepare("SELECT COUNT(*) FROM demandes_engagement WHERE groupe_proprietaire_id=?"); $usedInEng->execute([$gpId]); $countEng = (int)$usedInEng->fetchColumn();
        if ($countOps > 0 || $countEng > 0) {
            flash('danger','Impossible de supprimer : ce groupe est utilisé dans ' . $countOps . ' opération(s) caisse et ' . $countEng . ' engagement(s). Désactivez-le plutôt.');
        } else {
            $db->prepare("DELETE FROM groupes_proprietaires WHERE id=?")->execute([$gpId]);
            flash('success','Groupe propriétaire supprimé.');
        }
        header('Location: index.php?tab=groupes_proprietaires'); exit;
    }

    if ($action === 'toggle_benef') {
        $id = (int)$_POST['id'];
        $curr = $db->prepare("SELECT statut FROM beneficiaires WHERE id=?"); $curr->execute([$id]); $curr=$curr->fetch();
        $db->prepare("UPDATE beneficiaires SET statut=? WHERE id=?")->execute([$curr['statut']==='actif'?'inactif':'actif',$id]);
        header('Location: index.php?tab=beneficiaires'); exit;
    }
}

$activeTab = $_GET['tab'] ?? 'beneficiaires';

$beneficiaires = $db->query("SELECT * FROM beneficiaires ORDER BY nom")->fetchAll();
$fournisseurs  = $db->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();
$typesOps      = $db->query("SELECT * FROM types_operations ORDER BY categorie,libelle")->fetchAll();
$modesPaiement = $db->query("SELECT * FROM modes_paiement ORDER BY libelle")->fetchAll();
$planComptable = $db->query("SELECT * FROM plan_comptable ORDER BY compte")->fetchAll();
$destinations  = $db->query("SELECT d.*, (SELECT COUNT(*) FROM operations_caisse WHERE destination_id=d.id) AS nb_ops, (SELECT COUNT(*) FROM demandes_engagement WHERE destination_id=d.id) AS nb_eng FROM destinations d ORDER BY d.libelle")->fetchAll();
$groupesProprietaires = $db->query("SELECT gp.*, (SELECT COUNT(*) FROM demandes_engagement WHERE groupe_proprietaire_id=gp.id) AS nb_eng, (SELECT COUNT(*) FROM operations_caisse WHERE groupe_proprietaire_id=gp.id) AS nb_ops FROM groupes_proprietaires gp ORDER BY gp.libelle")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header"><h1>Référentiels</h1><p>Paramétrage des données de base : tiers, types d'opérations, destinations, groupes propriétaires, plan comptable</p></div>

<div class="tab-wrapper">
  <div class="tabs">
    <button class="tab <?= $activeTab==='beneficiaires'?'active':'' ?>" data-tab="tab-benef">Bénéficiaires</button>
    <button class="tab <?= $activeTab==='fournisseurs'?'active':'' ?>"  data-tab="tab-fourn">Fournisseurs</button>
    <button class="tab <?= $activeTab==='types'?'active':'' ?>"         data-tab="tab-types">Types d'opérations</button>
    <button class="tab <?= $activeTab==='modes'?'active':'' ?>"         data-tab="tab-modes">Modes de paiement</button>
    <button class="tab <?= $activeTab==='destinations'?'active':'' ?>" data-tab="tab-dest">Destinations</button>
    <button class="tab <?= $activeTab==='groupes_proprietaires'?'active':'' ?>" data-tab="tab-gp">Groupes propriétaires</button>
    <button class="tab <?= $activeTab==='plan'?'active':'' ?>"          data-tab="tab-plan">Plan comptable</button>
  </div>

  <!-- BÉNÉFICIAIRES -->
  <div class="tab-content <?= $activeTab==='beneficiaires'?'active':'' ?>" id="tab-benef">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($beneficiaires) ?> bénéficiaire(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-benef')">+ Ajouter</button>
    </div>
    <div class="card">
      <div class="card-header">
        <input type="text" id="search-benef" class="form-control" placeholder="Rechercher..." style="width:220px">
      </div>
      <div class="table-wrap">
        <table id="tbl-benef">
          <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Téléphone</th><th>Email</th><th>RIB</th><th>Statut</th><th></th></tr></thead>
          <tbody>
            <?php if(empty($beneficiaires)): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:24px">Aucun bénéficiaire</td></tr>
            <?php else: foreach($beneficiaires as $b): ?>
            <tr>
              <td><code><?= sanitize($b['code']??'—') ?></code></td>
              <td style="font-weight:600"><?= sanitize($b['nom']) ?></td>
              <td><span class="badge <?= $b['type']==='interne'?'badge-info':'badge-gray' ?>"><?= ucfirst($b['type']) ?></span></td>
              <td><?= sanitize($b['telephone']??'—') ?></td>
              <td><?= sanitize($b['email']??'—') ?></td>
              <td><?= sanitize($b['rib']??'—') ?></td>
              <td><span class="badge <?= $b['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($b['statut']) ?></span></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_benef">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $b['statut']==='actif'?'Désact.':'Activer' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- FOURNISSEURS -->
  <div class="tab-content <?= $activeTab==='fournisseurs'?'active':'' ?>" id="tab-fourn">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($fournisseurs) ?> fournisseur(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-fourn')">+ Ajouter</button>
    </div>
    <div class="card">
      <div class="card-header">
        <input type="text" id="search-fourn" class="form-control" placeholder="Rechercher..." style="width:220px">
      </div>
      <div class="table-wrap">
        <table id="tbl-fourn">
          <thead><tr><th>Code</th><th>Nom</th><th>Téléphone</th><th>Email</th><th>RIB</th><th>Délai paiement</th><th>Statut</th></tr></thead>
          <tbody>
            <?php if(empty($fournisseurs)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Aucun fournisseur</td></tr>
            <?php else: foreach($fournisseurs as $f): ?>
            <tr>
              <td><code><?= sanitize($f['code']??'—') ?></code></td>
              <td style="font-weight:600"><?= sanitize($f['nom']) ?></td>
              <td><?= sanitize($f['telephone']??'—') ?></td>
              <td><?= sanitize($f['email']??'—') ?></td>
              <td><?= sanitize($f['rib']??'—') ?></td>
              <td><?= $f['delai_paiement'] ?> jours</td>
              <td><span class="badge <?= $f['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($f['statut']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TYPES D'OPÉRATIONS -->
  <div class="tab-content <?= $activeTab==='types'?'active':'' ?>" id="tab-types">
    <?php
    $editType = isset($_GET['edit_type']) ? (int)$_GET['edit_type'] : null;
    $editTypeData = null;
    if ($editType) {
        $etR = $db->prepare("SELECT * FROM types_operations WHERE id=?");
        $etR->execute([$editType]);
        $editTypeData = $etR->fetch();
        if (!$editTypeData) $editType = null;
    }
    ?>
    <?php if ($editType && $editTypeData): ?>
    <!-- Edit view -->
    <a href="index.php?tab=types" class="btn btn-outline btn-sm mb-16" style="text-decoration:none">&larr; Retour à la liste</a>
    <div class="card" style="max-width:500px">
      <div class="card-header"><span class="card-title">Modifier le type : <?= sanitize($editTypeData['libelle']) ?></span></div>
      <form method="post">
        <input type="hidden" name="action" value="update_type_op">
        <input type="hidden" name="type_id" value="<?= $editTypeData['id'] ?>">
        <div class="card-body">
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Code <span class="req">*</span></label>
              <input type="text" name="code" class="form-control" value="<?= sanitize($editTypeData['code']) ?>" style="text-transform:uppercase" required>
            </div>
            <div class="form-group">
              <label class="form-label">Catégorie</label>
              <select name="categorie" class="form-control">
                <option value="caisse" <?= $editTypeData['categorie']==='caisse'?'selected':'' ?>>Caisse</option>
                <option value="banque" <?= $editTypeData['categorie']==='banque'?'selected':'' ?>>Banque</option>
                <option value="virement" <?= $editTypeData['categorie']==='virement'?'selected':'' ?>>Virement</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Libellé <span class="req">*</span></label>
            <input type="text" name="libelle" class="form-control" value="<?= sanitize($editTypeData['libelle']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Sens <span class="req">*</span></label>
            <select name="sens" class="form-control" required>
              <option value="credit" <?= $editTypeData['sens']==='credit'?'selected':'' ?>>Crédit (Entrée)</option>
              <option value="debit" <?= $editTypeData['sens']==='debit'?'selected':'' ?>>Débit (Sortie)</option>
            </select>
          </div>
        </div>
        <div class="card-footer">
          <a href="index.php?tab=types" class="btn btn-outline">Annuler</a>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <!-- List view -->
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($typesOps) ?> type(s) d'opération</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-type')">+ Ajouter</button>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Libellé</th><th>Sens</th><th>Catégorie</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($typesOps as $t): ?>
            <tr>
              <td><code><?= sanitize($t['code']) ?></code></td>
              <td><?= sanitize($t['libelle']) ?></td>
              <td><span class="badge <?= $t['sens']==='credit'?'badge-success':'badge-danger' ?>"><?= $t['sens']==='credit'?'Crédit ↑':'Débit ↓' ?></span></td>
              <td><?= ucfirst($t['categorie']) ?></td>
              <td><span class="badge <?= $t['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($t['statut']) ?></span></td>
              <td>
                <a href="index.php?tab=types&edit_type=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_type_op">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $t['statut']==='actif'?'Désact.':'Activer' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- MODES DE PAIEMENT -->
  <div class="tab-content <?= $activeTab==='modes'?'active':'' ?>" id="tab-modes">
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Libellé</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach($modesPaiement as $m): ?>
            <tr>
              <td><code><?= sanitize($m['code']) ?></code></td>
              <td><?= sanitize($m['libelle']) ?></td>
              <td><span class="badge <?= $m['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($m['statut']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- DESTINATIONS -->
  <div class="tab-content <?= $activeTab==='destinations'?'active':'' ?>" id="tab-dest">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($destinations) ?> destination(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-destination')">+ Nouvelle destination</button>
    </div>
    <div class="card">
      <div class="card-header">
        <input type="text" id="search-dest" class="form-control" placeholder="Rechercher une destination..." style="width:260px">
      </div>
      <div class="table-wrap">
        <table id="tbl-dest">
          <thead><tr><th>Code</th><th>Libellé</th><th>Utilisations</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if(empty($destinations)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:24px">Aucune destination</td></tr>
            <?php else: foreach($destinations as $d): ?>
            <tr>
              <td><code><?= sanitize($d['code']) ?></code></td>
              <td style="font-weight:600"><?= sanitize($d['libelle']) ?></td>
              <td>
                <?php if($d['nb_ops'] > 0 || $d['nb_eng'] > 0): ?>
                <span class="badge badge-info" title="<?= $d['nb_ops'] ?> opération(s) caisse, <?= $d['nb_eng'] ?> engagement(s)"><?= (int)$d['nb_ops'] + (int)$d['nb_eng'] ?> utilisation(s)</span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $d['statut']==='actif'?'badge-success':'badge-danger' ?>"><?= $d['statut']==='actif'?'Actif':'Inactif' ?></span></td>
              <td>
                <button class="btn btn-ghost btn-sm" onclick="editDestination(<?= $d['id'] ?>,'<?= sanitize($d['code']) ?>','<?= sanitize($d['libelle']) ?>')">Modifier</button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_destination">
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $d['statut']==='actif'?'Désact.':'Activer' ?></button>
                </form>
                <?php if($d['nb_ops'] == 0 && $d['nb_eng'] == 0): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette destination ?')">
                  <input type="hidden" name="action" value="delete_destination">
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Supprimer</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- GROUPES PROPRIÉTAIRES -->
  <div class="tab-content <?= $activeTab==='groupes_proprietaires'?'active':'' ?>" id="tab-gp">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($groupesProprietaires) ?> groupe(s) propriétaire(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-groupe-proprietaire')">+ Nouveau groupe</button>
    </div>
    <div class="card">
      <div class="card-header">
        <input type="text" id="search-gp" class="form-control" placeholder="Rechercher un groupe..." style="width:260px">
      </div>
      <div class="table-wrap">
        <table id="tbl-gp">
          <thead><tr><th>Code</th><th>Libellé</th><th>Utilisations</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if(empty($groupesProprietaires)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:24px">Aucun groupe propriétaire</td></tr>
            <?php else: foreach($groupesProprietaires as $gp): ?>
            <tr>
              <td><code><?= sanitize($gp['code']) ?></code></td>
              <td style="font-weight:600"><?= sanitize($gp['libelle']) ?></td>
              <td>
                <?php if($gp['nb_ops'] > 0 || $gp['nb_eng'] > 0): ?>
                <span class="badge badge-info" title="<?= $gp['nb_ops'] ?> opération(s) caisse, <?= $gp['nb_eng'] ?> engagement(s)"><?= (int)$gp['nb_ops'] + (int)$gp['nb_eng'] ?> utilisation(s)</span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $gp['statut']==='actif'?'badge-success':'badge-danger' ?>"><?= $gp['statut']==='actif'?'Actif':'Inactif' ?></span></td>
              <td>
                <button class="btn btn-ghost btn-sm" onclick="editGroupeProprietaire(<?= $gp['id'] ?>,'<?= sanitize($gp['code']) ?>','<?= sanitize($gp['libelle']) ?>')">Modifier</button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_groupe_proprietaire">
                  <input type="hidden" name="id" value="<?= $gp['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $gp['statut']==='actif'?'Désact.':'Activer' ?></button>
                </form>
                <?php if($gp['nb_ops'] == 0 && $gp['nb_eng'] == 0): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce groupe propriétaire ?')">
                  <input type="hidden" name="action" value="delete_groupe_proprietaire">
                  <input type="hidden" name="id" value="<?= $gp['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Supprimer</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PLAN COMPTABLE -->
  <div class="tab-content <?= $activeTab==='plan'?'active':'' ?>" id="tab-plan">
    <div class="d-flex justify-between align-center mb-16">
      <span style="font-weight:600"><?= count($planComptable) ?> compte(s)</span>
      <button class="btn btn-primary" onclick="openModal('modal-new-compte-pc')">+ Ajouter un compte</button>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="d-flex gap-8 align-center" style="flex-wrap:wrap">
          <?php for($cl=1;$cl<=7;$cl++): ?>
          <a href="?tab=plan&classe=<?= $cl ?>" class="btn btn-sm <?= ($_GET['classe']??'')==$cl?'btn-primary':'btn-outline' ?>">Classe <?= $cl ?></a>
          <?php endfor; ?>
          <a href="?tab=plan" class="btn btn-sm btn-ghost">Tous</a>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Compte</th><th>Libellé</th><th>Classe</th><th>Type</th><th>Sens normal</th><th>Statut</th></tr></thead>
          <tbody>
            <?php
            $filteredPC = isset($_GET['classe']) ? array_filter($planComptable, fn($pc) => $pc['classe'] == $_GET['classe']) : $planComptable;
            if(empty($filteredPC)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">Aucun compte</td></tr>
            <?php else: foreach($filteredPC as $pc): ?>
            <tr>
              <td><code style="font-weight:700"><?= sanitize($pc['compte']) ?></code></td>
              <td><?= sanitize($pc['libelle']) ?></td>
              <td><?= $pc['classe'] ?></td>
              <td><?= ucfirst($pc['type_compte']) ?></td>
              <td><span class="badge <?= $pc['sens_normal']==='debit'?'badge-danger':'badge-success' ?>"><?= ucfirst($pc['sens_normal']) ?></span></td>
              <td><span class="badge <?= $pc['statut']==='actif'?'badge-success':'badge-gray' ?>"><?= ucfirst($pc['statut']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nouveau bénéficiaire -->
<div class="modal-overlay" id="modal-new-benef">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title">Nouveau bénéficiaire</div>
      <button class="modal-close" onclick="closeModal('modal-new-benef')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_beneficiaire">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code</label>
            <input type="text" name="code" class="form-control" placeholder="BEN-001">
          </div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="externe">Externe</option>
              <option value="interne">Interne</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nom / Raison sociale <span class="req">*</span></label>
          <input type="text" name="nom" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea name="adresse" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">RIB / Coordonnées bancaires</label>
          <input type="text" name="rib" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-benef')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouveau fournisseur -->
<div class="modal-overlay" id="modal-new-fourn">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title">Nouveau fournisseur</div>
      <button class="modal-close" onclick="closeModal('modal-new-fourn')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_fournisseur">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code</label>
            <input type="text" name="code" class="form-control" placeholder="FRN-001">
          </div>
          <div class="form-group">
            <label class="form-label">Délai de paiement (jours)</label>
            <input type="number" name="delai" class="form-control" value="30" min="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nom / Raison sociale <span class="req">*</span></label>
          <input type="text" name="nom" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea name="adresse" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">RIB</label>
          <input type="text" name="rib" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-fourn')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouveau type d'opération -->
<div class="modal-overlay" id="modal-new-type">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Nouveau type d'opération</div>
      <button class="modal-close" onclick="closeModal('modal-new-type')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_type_op">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code <span class="req">*</span></label>
            <input type="text" name="code" class="form-control" placeholder="EX_CODE" style="text-transform:uppercase" required>
          </div>
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select name="categorie" class="form-control">
              <option value="caisse">Caisse</option>
              <option value="banque">Banque</option>
              <option value="virement">Virement</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sens <span class="req">*</span></label>
          <select name="sens" class="form-control" required>
            <option value="credit">Crédit (Entrée)</option>
            <option value="debit">Débit (Sortie)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-type')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouveau compte plan comptable -->
<div class="modal-overlay" id="modal-new-compte-pc">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Ajouter un compte au plan comptable</div>
      <button class="modal-close" onclick="closeModal('modal-new-compte-pc')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_compte">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">N° de compte <span class="req">*</span></label>
            <input type="text" name="compte" class="form-control" placeholder="601000" required maxlength="20">
          </div>
          <div class="form-group">
            <label class="form-label">Classe <span class="req">*</span></label>
            <select name="classe" class="form-control" required>
              <?php for($i=1;$i<=7;$i++): ?>
              <option value="<?= $i ?>">Classe <?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Type de compte</label>
            <select name="type_compte" class="form-control">
              <option value="actif">Actif</option>
              <option value="passif">Passif</option>
              <option value="charge">Charge</option>
              <option value="produit">Produit</option>
              <option value="bilan">Bilan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sens normal</label>
            <select name="sens_normal" class="form-control">
              <option value="debit">Débit</option>
              <option value="credit">Crédit</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-compte-pc')">Annuler</button>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouvelle destination -->
<div class="modal-overlay" id="modal-new-destination">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">Nouvelle destination</div>
      <button class="modal-close" onclick="closeModal('modal-new-destination')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_destination">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Code <span class="req">*</span></label>
          <input type="text" name="code" class="form-control" placeholder="Ex: SIEGE" style="text-transform:uppercase" required>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" placeholder="Ex: Siège social" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-destination')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Modifier destination -->
<div class="modal-overlay" id="modal-edit-destination">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">Modifier la destination</div>
      <button class="modal-close" onclick="closeModal('modal-edit-destination')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="update_destination">
      <input type="hidden" name="destination_id" id="edit-dst-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Code <span class="req">*</span></label>
          <input type="text" name="code" id="edit-dst-code" class="form-control" style="text-transform:uppercase" required>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" id="edit-dst-libelle" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-destination')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Nouveau groupe propriétaire -->
<div class="modal-overlay" id="modal-new-groupe-proprietaire">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">Nouveau groupe propriétaire</div>
      <button class="modal-close" onclick="closeModal('modal-new-groupe-proprietaire')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_groupe_proprietaire">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Code <span class="req">*</span></label>
          <input type="text" name="code" class="form-control" placeholder="Ex: GP_A" style="text-transform:uppercase" required>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" placeholder="Ex: Groupe A" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-groupe-proprietaire')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Modifier groupe propriétaire -->
<div class="modal-overlay" id="modal-edit-groupe-proprietaire">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">Modifier le groupe propriétaire</div>
      <button class="modal-close" onclick="closeModal('modal-edit-groupe-proprietaire')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="update_groupe_proprietaire">
      <input type="hidden" name="groupe_proprietaire_id" id="edit-gp-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Code <span class="req">*</span></label>
          <input type="text" name="code" id="edit-gp-code" class="form-control" style="text-transform:uppercase" required>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" id="edit-gp-libelle" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-groupe-proprietaire')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
tableSearch('search-benef','tbl-benef');
tableSearch('search-fourn','tbl-fourn');
tableSearch('search-dest','tbl-dest');
tableSearch('search-gp','tbl-gp');

function editGroupeProprietaire(id, code, libelle) {
  document.getElementById('edit-gp-id').value = id;
  document.getElementById('edit-gp-code').value = code;
  document.getElementById('edit-gp-libelle').value = libelle;
  openModal('modal-edit-groupe-proprietaire');
}

function editDestination(id, code, libelle) {
  document.getElementById('edit-dst-id').value = id;
  document.getElementById('edit-dst-code').value = code;
  document.getElementById('edit-dst-libelle').value = libelle;
  openModal('modal-edit-destination');
}

const urlTab2 = new URLSearchParams(location.search).get('tab');
if(urlTab2){
  document.querySelectorAll('.tabs .tab').forEach(t=>{
    const map={beneficiaires:'tab-benef',fournisseurs:'tab-fourn',types:'tab-types',modes:'tab-modes',destinations:'tab-dest',groupes_proprietaires:'tab-gp',plan:'tab-plan'};
    t.classList.toggle('active', t.dataset.tab===map[urlTab2]);
  });
  document.querySelectorAll('.tab-content').forEach(c=>{
    const map={beneficiaires:'tab-benef',fournisseurs:'tab-fourn',types:'tab-types',modes:'tab-modes',destinations:'tab-dest',groupes_proprietaires:'tab-gp',plan:'tab-plan'};
    c.classList.toggle('active', c.id===map[urlTab2]);
  });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
