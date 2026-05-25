<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('engagements');
$pageTitle = 'Détail de l\'engagement';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

// Bénéficiaire par défaut du demandeur
$userBenefR = $db->prepare("SELECT id FROM beneficiaires WHERE user_id=? AND statut='actif'");
$userBenefR->execute([$userId]);
$userBenefRow = $userBenefR->fetch();
$userBenefId = $userBenefRow ? (int)$userBenefRow['id'] : null;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Fetch engagement with all related data
$engR = $db->prepare("SELECT de.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, s.nom as service_nom, c.libelle as caisse_nom, f.nom as fournisseur_nom, b.nom as beneficiaire_nom, mp.libelle as mode_nom, to2.libelle as type_operation_nom, to2.code as type_operation_code, gp.libelle as groupe_proprietaire_nom, dst.libelle as destination_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id JOIN services s ON de.service_id=s.id LEFT JOIN caisses c ON de.caisse_id=c.id LEFT JOIN fournisseurs f ON de.fournisseur_id=f.id LEFT JOIN beneficiaires b ON de.beneficiaire_id=b.id LEFT JOIN modes_paiement mp ON de.mode_paiement_id=mp.id LEFT JOIN types_operations to2 ON de.type_operation_id=to2.id LEFT JOIN groupes_proprietaires gp ON de.groupe_proprietaire_id=gp.id LEFT JOIN destinations dst ON de.destination_id=dst.id WHERE de.id=?");
$engR->execute([$id]);
$eng = $engR->fetch();

if (!$eng) { flash('danger', 'Engagement introuvable.'); header('Location: index.php'); exit; }

// Handle modification of brouillon/renvoye engagement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier_engagement') {
    $engId = (int)$_POST['engagement_id'];
    // Only allow modification of own brouillon or renvoye
    $checkR = $db->prepare("SELECT demandeur_id, statut FROM demandes_engagement WHERE id=?");
    $checkR->execute([$engId]);
    $check = $checkR->fetch();
    if (!$check || (int)$check['demandeur_id'] !== $userId || !in_array($check['statut'], ['brouillon','renvoye'])) {
        flash('danger', 'Vous ne pouvez modifier que vos propres brouillons ou engagements renvoyés.');
        header('Location: detail.php?id=' . $engId); exit;
    }

    $serviceId = (int)($_POST['service_id']??1);
    $typeOperationId = (int)($_POST['type_operation_id']??1);
    $groupeProprietaireId = !empty($_POST['groupe_proprietaire_id'])?(int)$_POST['groupe_proprietaire_id']:null;
    $destinationId = !empty($_POST['destination_id'])?(int)$_POST['destination_id']:null;
    $objet = trim($_POST['objet']);
    $montant = abs((float)$_POST['montant']);
    $dateBesoin = $_POST['date_besoin']??null;
    $priorite = $_POST['priorite']??'normale';
    $benId = !empty($_POST['beneficiaire_id'])?(int)$_POST['beneficiaire_id']:null;
    $fournId = !empty($_POST['fournisseur_id'])?(int)$_POST['fournisseur_id']:null;
    $modeId = !empty($_POST['mode_paiement_id'])?(int)$_POST['mode_paiement_id']:null;
    $caisseId = !empty($_POST['caisse_id'])?(int)$_POST['caisse_id']:null;

    // If no beneficiary specified, auto-link to demandeur's beneficiary record
    if (!$benId) {
        $benCheck = $db->prepare("SELECT id FROM beneficiaires WHERE user_id=? AND statut='actif'");
        $benCheck->execute([$userId]);
        $benRow = $benCheck->fetch();
        if ($benRow) {
            $benId = (int)$benRow['id'];
        } else {
            $db->prepare("INSERT INTO beneficiaires (type,code,nom,user_id,statut) VALUES ('interne',?,?,?,'actif')")
               ->execute(['USR' . $userId, $user['nom'] . ' ' . $user['prenom'], $userId]);
            $benId = (int)$db->lastInsertId();
        }
    }

    $statut = isset($_POST['soumettre']) ? 'soumis' : 'brouillon';

    $db->prepare("UPDATE demandes_engagement SET service_id=?,fournisseur_id=?,beneficiaire_id=?,mode_paiement_id=?,type_operation_id=?,groupe_proprietaire_id=?,destination_id=?,objet=?,montant=?,date_besoin=?,priorite=?,statut=?,caisse_id=? WHERE id=?")
       ->execute([$serviceId,$fournId,$benId,$modeId,$typeOperationId,$groupeProprietaireId,$destinationId,$objet,$montant,$dateBesoin,$priorite,$statut,$caisseId,$engId]);
    auditLog('modifier_engagement','engagements','demandes_engagement',$engId);

    // Notify responsable N+1 when resubmitted
    if ($statut === 'soumis') {
        $numR = $db->prepare("SELECT numero FROM demandes_engagement WHERE id=?");
        $numR->execute([$engId]);
        $numero = $numR->fetchColumn();
        $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
        $respR->execute([$serviceId]);
        $respId = $respR->fetchColumn();
        if ($respId) {
            notify((int)$respId, $engId, 'soumis', 'Engagement resoumis', 'L\'engagement ' . $numero . ' (' . $objet . ') a été resoumis pour validation hiérarchique.');
        }
        notifyUsersWithPermission('engagements', 'valider_hierarchie', $engId, 'soumis', 'Engagement resoumis', 'L\'engagement ' . $numero . ' est de nouveau en attente de validation.', [$userId]);
    }

    flash('success', 'Engagement modifié' . ($statut==='soumis'?' et soumis':'') . '.');
    header('Location: detail.php?id=' . $engId); exit;
}

// Fetch lignes descriptives
$lignesR = $db->prepare("SELECT * FROM lignes_engagement WHERE engagement_id=? ORDER BY ordre");
$lignesR->execute([$id]);
$lignesEng = $lignesR->fetchAll();

// Fetch validation history
$valR = $db->prepare("SELECT ve.*, CONCAT(u.nom,' ',u.prenom) as valideur_nom FROM validations_engagement ve JOIN utilisateurs u ON ve.valideur_id=u.id WHERE ve.engagement_id=? ORDER BY ve.date_validation");
$valR->execute([$id]);
$validations = $valR->fetchAll();

$badges = ['brouillon'=>'gray','soumis'=>'info','valide_hierarchie'=>'purple','valide_comptable'=>'purple','valide_daf'=>'teal','approuve'=>'teal','execution_partielle'=>'info','execute'=>'success','solde'=>'warning','rejete'=>'danger','renvoye'=>'warning','annule'=>'danger'];
$labels = ['brouillon'=>'Brouillon','soumis'=>'Soumis','valide_hierarchie'=>'Val. Hiérarchie','valide_comptable'=>'Val. Comptable','valide_daf'=>'Val. DAF','approuve'=>'Approuvé','execution_partielle'=>'Exéc. partielle','execute'=>'Exécuté','solde'=>'Soldé','rejete'=>'Rejeté','renvoye'=>'Renvoyé','annule'=>'Annulé'];
$actionLabels = ['approuve'=>'Approuvé','rejete'=>'Rejeté','renvoi'=>'Renvoyé'];
$etapeLabels = ['hierarchie'=>'Hiérarchie N+1','comptable'=>'Comptable','daf'=>'DAF','execution'=>'Exécution caisse','execution_partielle'=>'Exéc. partielle','solder'=>'Solder'];
$prioriteLabels = ['normale'=>'Normale','urgente'=>'Urgente','tres_urgente'=>'Très urgente'];
$prioriteBadges = ['normale'=>'gray','urgente'=>'warning','tres_urgente'=>'danger'];

// Check if user can validate at current step
$canValidateN1 = $eng['statut']==='soumis' && hasPermission('engagements', 'valider_hierarchie');
$canValidateCompta = $eng['statut']==='valide_hierarchie' && hasPermission('engagements', 'valider_comptable');
$canValidateDAF = $eng['statut']==='valide_comptable' && hasPermission('engagements', 'valider_daf');

if ($canValidateN1) {
    $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
    $respR->execute([$eng['service_id']]);
    $respId = $respR->fetchColumn();
    if ($respId && (int)$respId !== $userId && !hasPermission('engagements', 'all') && !hasPermission('all', 'all')) {
        $canValidateN1 = false;
    }
}

// Check if current user can edit (own brouillon or renvoye)
$canEdit = in_array($eng['statut'], ['brouillon','renvoye']) && (int)$eng['demandeur_id'] === $userId && hasPermission('engagements', 'creer');

// Fetch dropdown data for edit form
$services = $db->query("SELECT * FROM services WHERE statut='actif' ORDER BY nom")->fetchAll();
$beneficiaires = $db->query("SELECT * FROM beneficiaires WHERE statut='actif' ORDER BY nom")->fetchAll();
$fournisseurs = $db->query("SELECT * FROM fournisseurs WHERE statut='actif' ORDER BY nom")->fetchAll();
$modesPaiement = $db->query("SELECT * FROM modes_paiement WHERE code='ESP' AND statut='actif'")->fetchAll();
$caisses = $db->query("SELECT * FROM caisses WHERE statut != 'suspendue' ORDER BY libelle")->fetchAll();
$typesOperations = $db->query("SELECT * FROM types_operations WHERE statut='actif' ORDER BY libelle")->fetchAll();
$destinations = $db->query("SELECT * FROM destinations WHERE statut='actif' ORDER BY libelle")->fetchAll();
$groupesProprietaires = $db->query("SELECT * FROM groupes_proprietaires WHERE statut='actif' ORDER BY libelle")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center no-print">
  <div>
    <a href="index.php" class="btn btn-ghost btn-sm no-print" style="margin-bottom:8px">&larr; Retour aux engagements</a>
    <h1>Engagement <?= sanitize($eng['numero']) ?></h1>
    <p>Créé le <?= date('d/m/Y H:i', strtotime($eng['created_at'])) ?></p>
  </div>
  <div class="d-flex gap-8">
    <span class="badge badge-<?= $badges[$eng['statut']]??'gray' ?>" style="font-size:13px;padding:6px 14px"><?= $labels[$eng['statut']]??$eng['statut'] ?></span>
    <button class="btn btn-ghost btn-sm no-print" onclick="window.print()" title="Imprimer l'engagement"><i class="fa-solid fa-print"></i> Imprimer</button>
  </div>
</div>

<!-- Workflow stepper -->
<div class="card mb-16 no-print">
  <div class="card-body" style="padding:16px 24px">
    <div class="workflow">
      <?php
      $workflowSteps = [
        ['soumis','Soumis'],
        ['valide_hierarchie','Val. N1'],
        ['valide_comptable','Val. Compta'],
        ['approuve','Approuvé'],
        ['execution_partielle','Exéc. partielle'],
        ['execute','Exécuté'],
      ];
      $allStatuses = array_column($workflowSteps, 0);
      $currentIdx = array_search($eng['statut'], $allStatuses);
      if ($currentIdx === false) $currentIdx = -1;
      if ($eng['statut'] === 'brouillon' || $eng['statut'] === 'renvoye') $currentIdx = -1;
      if ($eng['statut'] === 'valide_daf') $currentIdx = 3;
      if ($eng['statut'] === 'solde') $currentIdx = 4;
      if ($eng['statut'] === 'rejete' || $eng['statut'] === 'annule') $currentIdx = -1;
      foreach ($workflowSteps as $i => [$status, $label]):
        $done = $i <= $currentIdx;
      ?>
      <div class="workflow-step"><div class="workflow-step-inner"><div class="step-circle <?= $done?'done':'' ?>"><?= $i+1 ?></div><div class="step-label"><?= $label ?></div></div></div>
      <?php if ($i < count($workflowSteps)-1): ?>
      <div class="workflow-line <?= $done?'done':'' ?>"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (in_array($eng['statut'], ['execution_partielle', 'execute', 'solde'])): ?>
<?php
  $montantExecute = (float)($eng['montant_execute'] ?? 0);
  $montantTotal = (float)$eng['montant'];
  $montantRestant = $montantTotal - $montantExecute;
  $pctExecute = $montantTotal > 0 ? round(($montantExecute / $montantTotal) * 100) : 0;
  $badgeClass = $eng['statut'] === 'execute' ? 'success' : ($eng['statut'] === 'solde' ? 'warning' : 'info');
?>
<div class="card mb-16">
  <div class="card-header">
    <span class="card-title">Avancement de l'exécution</span>
    <span class="badge badge-<?= $badgeClass ?>" style="font-size:12px"><?= $pctExecute ?>%</span>
  </div>
  <div class="card-body">
    <div style="background:var(--border);border-radius:4px;height:12px;overflow:hidden;margin-bottom:12px">
      <div style="background:<?= $pctExecute>=100?'var(--success)':'var(--primary)' ?>;height:100%;border-radius:4px;width:<?= $pctExecute ?>%;transition:width .3s"></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:14px">
      <div><span style="color:var(--text3)">Montant total:</span> <strong><?= formatMontant($montantTotal) ?></strong></div>
      <div><span style="color:var(--success)">Exécuté:</span> <strong style="color:var(--success)"><?= formatMontant($montantExecute) ?></strong></div>
      <div><span style="color:var(--danger)">Restant:</span> <strong style="color:var(--danger)"><?= formatMontant($montantRestant) ?></strong></div>
    </div>
    <?php if ($eng['statut'] === 'solde'): ?>
    <div style="margin-top:12px;padding:10px;background:#fef3d8;border:1px solid #f59e0b;border-radius:var(--radius);font-size:13px">
      <strong>Engagement soldé</strong> — Montant soldé: <?= formatMontant($montantRestant) ?><br>
      <span style="color:var(--text3)">Motif: <?= sanitize($eng['motif_solder'] ?? '—') ?></span><br>
      <span style="color:var(--text3)">Date: <?= $eng['date_solder'] ? date('d/m/Y H:i', strtotime($eng['date_solder'])) : '—' ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-16">
  <div class="card-header"><span class="card-title">Historique des paiements</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>N° Pièce</th><th>Date</th><th>Montant</th><th>Caissier</th><th>Libellé</th></tr>
      </thead>
      <tbody>
        <?php
        $payR = $db->prepare("SELECT oc.*, CONCAT(u.nom,' ',u.prenom) as caissier_nom FROM operations_caisse oc JOIN utilisateurs u ON oc.saisi_par=u.id WHERE oc.engagement_id=? AND oc.annule=0 AND oc.sens='debit' ORDER BY oc.date_operation ASC, oc.heure_operation ASC");
        $payR->execute([$id]);
        $paiements = $payR->fetchAll();
        ?>
        <?php if (empty($paiements)): ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:16px">Aucun paiement enregistré</td></tr>
        <?php else: foreach($paiements as $p): ?>
        <tr>
          <td><code><?= sanitize($p['numero_piece']) ?></code></td>
          <td><?= date('d/m/Y H:i', strtotime($p['date_operation'] . ' ' . $p['heure_operation'])) ?></td>
          <td class="amount fw-bold"><?= formatMontant($p['montant']) ?></td>
          <td><?= sanitize($p['caissier_nom']) ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($p['libelle']) ?>"><?= sanitize($p['libelle']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Edit engagement (brouillon/renvoye) -->
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Modifier et resoumettre</span></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--text3);margin-bottom:16px"><?= $eng['statut']==='renvoye' ? 'Cet engagement a été renvoyé pour révision. Modifiez-le puis soumettez-le à nouveau.' : 'Cet engagement est en brouillon. Vous pouvez le modifier puis le soumettre pour validation.' ?></p>
    <form method="post">
      <input type="hidden" name="action" value="modifier_engagement">
      <input type="hidden" name="engagement_id" value="<?= $eng['id'] ?>">
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Service <span class="req">*</span></label>
          <select name="service_id" class="form-control" required>
            <?php foreach($services as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id']==$eng['service_id']?'selected':'' ?>><?= sanitize($s['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select name="priorite" class="form-control">
            <option value="normale" <?= $eng['priorite']==='normale'?'selected':'' ?>>Normale</option>
            <option value="urgente" <?= $eng['priorite']==='urgente'?'selected':'' ?>>Urgente</option>
            <option value="tres_urgente" <?= $eng['priorite']==='tres_urgente'?'selected':'' ?>>Très urgente</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Type d'opération <span class="req">*</span></label>
        <select name="type_operation_id" id="detail-type-operation" class="form-control" required onchange="toggleGroupeProprietaire('detail-type-operation','detail-groupe-proprietaire-div','detail-groupe-proprietaire')">
          <option value="" data-code="">-- Choisir --</option>
          <?php foreach($typesOperations as $to): ?>
          <option value="<?= $to['id'] ?>" data-code="<?= sanitize($to['code']) ?>" <?= $to['id']==$eng['type_operation_id']?'selected':'' ?>>[<?= $to['sens']==='credit'?'+':'-' ?>] <?= sanitize($to['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="detail-groupe-proprietaire-div" style="display:<?= ($eng['type_operation_code'] ?? '') === 'BON_PROPRIETAIRE' ? '' : 'none' ?>">
        <label class="form-label">Groupe propriétaire <span class="req">*</span></label>
        <select name="groupe_proprietaire_id" id="detail-groupe-proprietaire" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($groupesProprietaires as $gp): ?>
          <option value="<?= $gp['id'] ?>" <?= $gp['id']==$eng['groupe_proprietaire_id']?'selected':'' ?>><?= sanitize($gp['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Destination</label>
        <select name="destination_id" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($destinations as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $d['id']==$eng['destination_id']?'selected':'' ?>><?= sanitize($d['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Objet de la demande <span class="req">*</span></label>
        <textarea name="objet" class="form-control" rows="3" required><?= sanitize($eng['objet']) ?></textarea>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Montant (FCFA) <span class="req">*</span></label>
          <input type="number" name="montant" class="form-control amount-input" step="1" min="1" value="<?= $eng['montant'] ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Date de besoin</label>
          <input type="date" name="date_besoin" class="form-control" value="<?= $eng['date_besoin'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Mode de paiement</label>
          <select name="mode_paiement_id" class="form-control" readonly>
            <?php foreach($modesPaiement as $m): ?>
            <option value="<?= $m['id'] ?>" selected><?= sanitize($m['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Caisse de paiement</label>
        <select name="caisse_id" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($caisses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $c['id']==$eng['caisse_id']?'selected':'' ?>><?= sanitize($c['libelle']) ?> (<?= sanitize($c['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Fournisseur</label>
          <select name="fournisseur_id" class="form-control">
            <option value="">-- Choisir --</option>
            <?php foreach($fournisseurs as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $f['id']==$eng['fournisseur_id']?'selected':'' ?>><?= sanitize($f['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Bénéficiaire</label>
          <input type="hidden" name="beneficiaire_id" value="<?= $userBenefId ?>">
          <input type="text" class="form-control" value="<?= sanitize($user['nom'] . ' ' . $user['prenom']) ?>" readonly style="background:var(--bg-tertiary,#f0f0f0);opacity:.75;cursor:not-allowed">
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <button type="submit" name="brouillon" class="btn btn-ghost">Enregistrer brouillon</button>
        <button type="submit" name="soumettre" class="btn btn-primary">Soumettre pour validation</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Print header -->
<div class="print-only" style="margin-bottom:16px">
  <h2 style="margin-bottom:10px;font-size:16px">Engagement <?= sanitize($eng['numero']) ?></h2>

  <!-- Bloc 1: Infos clés -->
  <table class="print-header-table" style="margin-bottom:16px">
    <tr><th>N° Engagement</th><td><code><?= sanitize($eng['numero']) ?></code></td><th>Montant</th><td class="amount fw-bold"><?= formatMontant($eng['montant']) ?></td></tr>
    <tr><th>Date</th><td><?= date('d/m/Y', strtotime($eng['created_at'])) ?></td><th>Type d'opération</th><td><?= sanitize($eng['type_operation_nom'] ?? '—') ?></td></tr>
  </table>

  <!-- Bloc 2: Demandeur / Service / Motif -->
  <table class="print-header-table" style="margin-bottom:16px">
    <tr><th>Demandeur</th><td><?= sanitize($eng['demandeur_nom']) ?></td></tr>
    <tr><th>Service</th><td><?= sanitize($eng['service_nom']) ?></td></tr>
    <tr><th>Motif</th><td><?= sanitize($eng['objet']) ?></td></tr>
  </table>

  <!-- Bloc 3: Lignes des dépenses -->
  <?php if (!empty($lignesEng)): ?>
  <h3 style="font-size:14px;margin-bottom:8px">Détail des dépenses</h3>
  <table class="print-header-table" style="margin-bottom:16px">
    <thead>
      <tr><th style="text-align:center">N°</th><th>Libellé</th><th style="text-align:right">Quantité</th><th style="text-align:right">Coût unitaire</th><th style="text-align:right">Montant</th></tr>
    </thead>
    <tbody>
      <?php foreach ($lignesEng as $i => $l): ?>
      <tr>
        <td style="text-align:center"><?= $i + 1 ?></td>
        <td><?= sanitize($l['libelle']) ?></td>
        <td style="text-align:right"><?= number_format($l['quantite'], 2, ',', ' ') ?></td>
        <td style="text-align:right"><?= formatMontant($l['cout_unitaire']) ?></td>
        <td style="text-align:right;font-weight:600"><?= formatMontant($l['montant']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr style="border-top:2px solid #333"><td colspan="4" style="text-align:right;font-weight:700">Total</td><td style="text-align:right;font-weight:700"><?= formatMontant($eng['montant']) ?></td></tr>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Bloc 4: Signatures -->
  <?php
    $signN1 = ''; $signCompta = ''; $signDAF = '';
    foreach ($validations as $v) {
      if ($v['action'] === 'approuve') {
        if ($v['etape'] === 'hierarchie') $signN1 = $v['valideur_nom'];
        elseif ($v['etape'] === 'comptable') $signCompta = $v['valideur_nom'];
        elseif ($v['etape'] === 'daf') $signDAF = $v['valideur_nom'];
      }
    }
  ?>
  <h3 style="font-size:14px;margin-bottom:8px">Signatures</h3>
  <table class="print-header-table">
    <thead>
      <tr><th style="text-align:center;width:25%">Demandeur</th><th style="text-align:center;width:25%">Directeur Hiérarchique</th><th style="text-align:center;width:25%">Directeur Comptable</th><th style="text-align:center;width:25%">Directeur DAF</th></tr>
    </thead>
    <tbody>
      <tr>
        <td style="text-align:center;height:80px;vertical-align:top;padding-top:4px"><?= sanitize($eng['demandeur_nom']) ?></td>
        <td style="text-align:center;height:80px;vertical-align:top;padding-top:4px"><?= $signN1 ? sanitize($signN1) : '—' ?></td>
        <td style="text-align:center;height:80px;vertical-align:top;padding-top:4px"><?= $signCompta ? sanitize($signCompta) : '—' ?></td>
        <td style="text-align:center;height:80px;vertical-align:top;padding-top:4px"><?= $signDAF ? sanitize($signDAF) : '—' ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Objet -->
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Objet de la demande</span></div>
  <div class="card-body">
    <p style="font-size:14px;line-height:1.6"><?= nl2br(sanitize($eng['objet'])) ?></p>
  </div>
</div>

<?php if (!empty($lignesEng)): ?>
<!-- Lignes descriptives -->
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Détail des dépenses</span></div>
  <div class="table-wrap">
    <table style="width:100%;font-size:13.5px">
      <thead>
        <tr>
          <th style="width:40px;text-align:center">N°</th>
          <th>Libellé</th>
          <th style="text-align:right;width:90px">Quantité</th>
          <th style="text-align:right;width:130px">Coût unitaire</th>
          <th style="text-align:right;width:130px">Montant</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lignesEng as $i => $l): ?>
        <tr>
          <td style="text-align:center"><?= $i + 1 ?></td>
          <td><?= sanitize($l['libelle']) ?></td>
          <td style="text-align:right"><?= number_format($l['quantite'], 2, ',', ' ') ?></td>
          <td style="text-align:right"><?= formatMontant($l['cout_unitaire']) ?></td>
          <td style="text-align:right;font-weight:600"><?= formatMontant($l['montant']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--border)">
          <td colspan="4" style="text-align:right;font-weight:700">Total</td>
          <td style="text-align:right;font-weight:700;font-size:15px"><?= formatMontant($eng['montant']) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Pièces jointes -->
<?php
$detailPieces = json_decode($eng['pieces_jointes'] ?? '', true) ?: [];
?>
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Pièces jointes <?= !empty($detailPieces) ? '('.count($detailPieces).')' : '' ?></span></div>
  <div class="card-body">
    <?php if (empty($detailPieces)): ?>
    <p class="text-muted">Aucune pièce jointe</p>
    <?php else: ?>
    <div>
      <?php foreach ($detailPieces as $p): ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
        <span style="font-size:18px"><?= fileIcon($p['type'] ?? '') ?></span>
        <div style="flex:1">
          <a href="<?= BASE_URL ?>/uploads/engagements/<?= sanitize($p['fichier']) ?>" target="_blank" style="font-weight:500;color:var(--primary)"><?= sanitize($p['nom']) ?></a>
          <br><small style="color:var(--text3)"><?= formatFileSize($p['taille'] ?? 0) ?></small>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Validation actions -->
<?php if ($canValidateN1 || $canValidateCompta || $canValidateDAF): ?>
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Action de validation</span></div>
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/modules/engagements/index.php" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <input type="hidden" name="action" value="valider">
      <input type="hidden" name="engagement_id" value="<?= $eng['id'] ?>">
      <input type="hidden" name="from_detail" value="1">
      <?php if ($canValidateN1): ?>
        <input type="hidden" name="etape" value="hierarchie">
        <span style="font-size:13px;font-weight:600;color:var(--text2)">Validation hiérarchique N+1</span>
      <?php elseif ($canValidateCompta): ?>
        <input type="hidden" name="etape" value="comptable">
        <span style="font-size:13px;font-weight:600;color:var(--text2)">Validation comptable</span>
      <?php elseif ($canValidateDAF): ?>
        <input type="hidden" name="etape" value="daf">
        <span style="font-size:13px;font-weight:600;color:var(--text2)">Validation DAF</span>
      <?php endif; ?>
      <select name="decision" class="form-control" style="width:auto">
        <option value="approuve">Approuver</option>
        <option value="renvoi">Renvoyer pour révision</option>
        <option value="rejete">Rejeter</option>
      </select>
      <input type="text" name="commentaire" class="form-control" placeholder="Commentaire..." style="flex:1;min-width:200px">
      <button type="submit" class="btn btn-primary">Valider</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (in_array($eng['statut'], ['approuve', 'execution_partielle']) && $eng['caisse_id']): ?>
<div class="card mb-16 no-print">
  <div class="card-body" style="display:flex;align-items:center;gap:16px">
    <span>Cet engagement est <?= $eng['statut'] === 'execution_partielle' ? 'partiellement exécuté' : 'approuvé' ?> et prêt pour exécution.</span>
    <a href="<?= BASE_URL ?>/modules/operations_caisse/index.php?caisse=<?= $eng['caisse_id'] ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Exécuter en caisse</a>
  </div>
</div>
<?php endif; ?>

<!-- Validation history -->
<div class="card mb-16 no-print">
  <div class="card-header"><span class="card-title">Historique des validations</span></div>
  <?php if (empty($validations)): ?>
  <div class="card-body"><p class="text-muted">Aucune validation enregistrée.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Étape</th><th>Valideur</th><th>Action</th><th>Commentaire</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($validations as $v): ?>
        <tr>
          <td><span class="badge badge-<?= $v['action']==='approuve'?'success':($v['action']==='rejete'?'danger':'warning') ?>"><?= $etapeLabels[$v['etape']]??$v['etape'] ?></span></td>
          <td><?= sanitize($v['valideur_nom']) ?></td>
          <td><span class="badge badge-<?= $v['action']==='approuve'?'badge-success':($v['action']==='rejete'?'badge-danger':'badge-warning') ?>"><?= $actionLabels[$v['action']]??$v['action'] ?></span></td>
          <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sanitize($v['commentaire']??'') ?>"><?= sanitize($v['commentaire']) ?: '—' ?></td>
          <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($v['date_validation'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleGroupeProprietaire(selectId, divId, fieldId) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  const show = opt && opt.getAttribute('data-code') === 'BON_PROPRIETAIRE';
  document.getElementById(divId).style.display = show ? '' : 'none';
  if (!show) document.getElementById(fieldId).value = '';
}
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleGroupeProprietaire('detail-type-operation','detail-groupe-proprietaire-div','detail-groupe-proprietaire');
  <?php if (!empty($_GET['print'])): ?>
  window.print();
  <?php endif; ?>
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>