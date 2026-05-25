<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('engagements');
if (!hasPermission('engagements', 'creer')) { flash('danger','Vous n\'avez pas le droit de modifier un engagement.'); header('Location: '.BASE_URL.'/modules/engagements/index.php'); exit; }

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Fetch engagement
$engR = $db->prepare("SELECT de.*, to2.libelle as type_operation_nom, to2.code as type_operation_code FROM demandes_engagement de LEFT JOIN types_operations to2 ON de.type_operation_id=to2.id WHERE de.id=?");
$engR->execute([$id]);
$eng = $engR->fetch();

if (!$eng) { flash('danger','Engagement introuvable.'); header('Location: index.php'); exit; }
if ((int)$eng['demandeur_id'] !== $userId || !in_array($eng['statut'], ['brouillon','renvoye'])) {
    flash('danger','Vous ne pouvez modifier que vos propres brouillons ou engagements renvoyés.');
    header('Location: index.php'); exit;
}

$pageTitle = 'Modifier l\'engagement ' . sanitize($eng['numero']);

// Fetch existing lignes
$lignesR = $db->prepare("SELECT * FROM lignes_engagement WHERE engagement_id=? ORDER BY ordre");
$lignesR->execute([$id]);
$existingLignes = $lignesR->fetchAll();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'modifier_engagement') {
        $engId = (int)$_POST['engagement_id'];
        $checkR = $db->prepare("SELECT demandeur_id, statut FROM demandes_engagement WHERE id=?");
        $checkR->execute([$engId]);
        $check = $checkR->fetch();
        if (!$check || (int)$check['demandeur_id'] !== $userId || !in_array($check['statut'], ['brouillon','renvoye'])) {
            flash('danger', 'Vous ne pouvez modifier que vos propres brouillons ou engagements renvoyés.');
            header('Location: index.php'); exit;
        }

        $serviceId = (int)($_POST['service_id']??1);
        $typeOperationId = (int)($_POST['type_operation_id']??1);
        $groupeProprietaireId = !empty($_POST['groupe_proprietaire_id'])?(int)$_POST['groupe_proprietaire_id']:null;
        $destinationId = !empty($_POST['destination_id'])?(int)$_POST['destination_id']:null;
        $objet = trim($_POST['objet']);
        $dateBesoin = $_POST['date_besoin']??null;
        $priorite = $_POST['priorite']??'normale';
        $benId = !empty($_POST['beneficiaire_id'])?(int)$_POST['beneficiaire_id']:null;
        $fournId = !empty($_POST['fournisseur_id'])?(int)$_POST['fournisseur_id']:null;
        $modeId = !empty($_POST['mode_paiement_id'])?(int)$_POST['mode_paiement_id']:null;
        $caisseId = !empty($_POST['caisse_id'])?(int)$_POST['caisse_id']:null;

        // Calculate montant from lignes
        $lignes = $_POST['lignes'] ?? [];
        $montant = 0;
        $validLignes = [];
        foreach ($lignes as $l) {
            $libelle = trim($l['libelle'] ?? '');
            if ($libelle === '') continue;
            $qte = abs((float)($l['quantite'] ?? 1));
            $cu = abs((float)($l['cout_unitaire'] ?? 0));
            $ligneMontant = $qte * $cu;
            $montant += $ligneMontant;
            $validLignes[] = ['libelle' => $libelle, 'quantite' => $qte, 'cout_unitaire' => $cu, 'montant' => $ligneMontant];
        }

        if (empty($validLignes)) {
            flash('danger', 'Veuillez ajouter au moins une ligne descriptive.');
            header('Location: modifier.php?id=' . $engId); exit;
        }

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

        // Replace lignes
        $db->prepare("DELETE FROM lignes_engagement WHERE engagement_id=?")->execute([$engId]);
        $ordre = 1;
        foreach ($validLignes as $vl) {
            $db->prepare("INSERT INTO lignes_engagement (engagement_id,ordre,libelle,quantite,cout_unitaire,montant) VALUES (?,?,?,?,?,?)")
               ->execute([$engId, $ordre++, $vl['libelle'], $vl['quantite'], $vl['cout_unitaire'], $vl['montant']]);
        }

        // Handle attachments: load existing, add new
        $existingPieces = json_decode($eng['pieces_jointes'] ?? '', true) ?: [];

        // Add new files
        $newPieces = handleUploads('pieces', $eng['numero']);
        if (!empty($newPieces)) {
            $existingPieces = array_merge($existingPieces, $newPieces);
        }

        // Save updated pieces
        $db->prepare("UPDATE demandes_engagement SET pieces_jointes=? WHERE id=?")
           ->execute([json_encode($existingPieces), $engId]);

        auditLog('modifier_engagement','engagements','demandes_engagement',$engId);

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
        header('Location: ' . BASE_URL . '/modules/engagements/detail.php?id=' . $engId);
        exit;
    }
}

// Data for form
$services = $db->query("SELECT * FROM services WHERE statut='actif' ORDER BY nom")->fetchAll();
$fournisseurs = $db->query("SELECT * FROM fournisseurs WHERE statut='actif' ORDER BY nom")->fetchAll();
$modesPaiement = $db->query("SELECT * FROM modes_paiement WHERE code='ESP' AND statut='actif'")->fetchAll();
$caisses = $db->query("SELECT * FROM caisses WHERE statut != 'suspendue' ORDER BY libelle")->fetchAll();
$typesOperations = $db->query("SELECT * FROM types_operations WHERE statut='actif' ORDER BY libelle")->fetchAll();
$destinations = $db->query("SELECT * FROM destinations WHERE statut='actif' ORDER BY libelle")->fetchAll();
$groupesProprietaires = $db->query("SELECT * FROM groupes_proprietaires WHERE statut='actif' ORDER BY libelle")->fetchAll();

$userServiceId = $user['service_id'] ?? null;
$userServiceNom = '';
if ($userServiceId) {
    $svcR = $db->prepare("SELECT nom FROM services WHERE id=?");
    $svcR->execute([$userServiceId]);
    $svcRow = $svcR->fetch();
    $userServiceNom = $svcRow ? $svcRow['nom'] : '';
}
$userBenefR = $db->prepare("SELECT id FROM beneficiaires WHERE user_id=? AND statut='actif'");
$userBenefR->execute([$userId]);
$userBenefRow = $userBenefR->fetch();
$userBenefId = $userBenefRow ? (int)$userBenefRow['id'] : null;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <a href="detail.php?id=<?= $id ?>" class="btn btn-ghost btn-sm" style="margin-bottom:8px">&larr; Retour à l'engagement</a>
    <h1>Modifier l'engagement <?= sanitize($eng['numero']) ?></h1>
    <p>Statut actuel : <span class="badge badge-<?= $eng['statut']==='renvoye'?'warning':'gray' ?>"><?= ucfirst($eng['statut']) ?></span></p>
  </div>
</div>

<div class="card" style="max-width:900px;margin:0 auto">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="modifier_engagement">
    <input type="hidden" name="engagement_id" value="<?= $eng['id'] ?>">
    <input type="hidden" name="beneficiaire_id" value="<?= $userBenefId ?>">
    <input type="hidden" name="montant" id="montant-hidden" value="<?= $eng['montant'] ?>">
    <div class="card-body">

      <!-- 1. Date de besoin -->
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Date de besoin</label>
          <input type="date" name="date_besoin" class="form-control" value="<?= $eng['date_besoin'] ?>">
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

      <!-- 2. Type d'opération -->
      <div class="form-group">
        <label class="form-label">Type d'opération <span class="req">*</span></label>
        <select name="type_operation_id" id="edit-type-operation" class="form-control" required onchange="toggleGroupeProprietaire('edit-type-operation','edit-groupe-proprietaire-div','edit-groupe-proprietaire')">
          <option value="" data-code="">-- Choisir --</option>
          <?php foreach($typesOperations as $to): ?>
          <option value="<?= $to['id'] ?>" data-code="<?= sanitize($to['code']) ?>" <?= $to['id']==$eng['type_operation_id']?'selected':'' ?>>[<?= $to['sens']==='credit'?'+':'-' ?>] <?= sanitize($to['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="edit-groupe-proprietaire-div" style="display:<?= ($eng['type_operation_code'] ?? '') === 'BON_PROPRIETAIRE' ? '' : 'none' ?>">
        <label class="form-label">Groupe propriétaire <span class="req">*</span></label>
        <select name="groupe_proprietaire_id" id="edit-groupe-proprietaire" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($groupesProprietaires as $gp): ?>
          <option value="<?= $gp['id'] ?>" <?= $gp['id']==$eng['groupe_proprietaire_id']?'selected':'' ?>><?= sanitize($gp['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 3. Demandeur -->
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Demandeur</label>
          <input type="text" class="form-control" value="<?= sanitize($user['nom'] . ' ' . $user['prenom']) ?>" readonly style="background:var(--bg-tertiary,#f0f0f0);opacity:.75;cursor:not-allowed">
        </div>
        <div class="form-group">
          <label class="form-label">Poste / Service</label>
          <input type="text" class="form-control" value="<?= sanitize($userServiceNom) ?>" readonly style="background:var(--bg-tertiary,#f0f0f0);opacity:.75;cursor:not-allowed">
        </div>
      </div>

      <!-- 4. Destination -->
      <div class="form-group">
        <label class="form-label">Destination</label>
        <select name="destination_id" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($destinations as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $d['id']==$eng['destination_id']?'selected':'' ?>><?= sanitize($d['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 5. Motif de l'engagement -->
      <div class="form-group">
        <label class="form-label">Motif de l'engagement <span class="req">*</span></label>
        <textarea name="objet" class="form-control" rows="3" placeholder="Description détaillée de la dépense..." required><?= sanitize($eng['objet']) ?></textarea>
      </div>

      <!-- 6. Lignes descriptives -->
      <div class="form-group">
        <label class="form-label">Lignes descriptives <span style="font-weight:400;color:var(--text3)">(détail des dépenses)</span></label>
        <table id="lignes-table" style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:2px solid var(--border)">
              <th style="padding:8px 4px;text-align:center;width:40px">N°</th>
              <th style="padding:8px 4px;text-align:left">Libellé</th>
              <th style="padding:8px 4px;text-align:right;width:90px">Quantité</th>
              <th style="padding:8px 4px;text-align:right;width:130px">Coût unitaire</th>
              <th style="padding:8px 4px;text-align:right;width:130px">Montant</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="lignes-body"></tbody>
          <tfoot>
            <tr style="border-top:2px solid var(--border)">
              <td colspan="4" style="padding:8px 4px;text-align:right;font-weight:600">Total</td>
              <td style="padding:8px 4px;text-align:right;font-weight:700" id="lignes-total">0 FCFA</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="addLigne()">+ Ajouter une ligne</button>
      </div>

      <!-- 7. Autres champs -->
      <div class="form-group">
        <label class="form-label">Service <span class="req">*</span></label>
        <select name="service_id" class="form-control" required>
          <?php foreach($services as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id']==$eng['service_id']?'selected':'' ?>><?= sanitize($s['nom']) ?></option>
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

      <!-- Pièces jointes existantes -->
      <?php
      $existingPieces = json_decode($eng['pieces_jointes'] ?? '', true) ?: [];
      ?>
      <div class="form-group">
        <label class="form-label">Documents justificatifs <span style="font-weight:400;color:var(--text3)">(facultatif)</span></label>
        <?php if (!empty($existingPieces)): ?>
        <div style="margin-bottom:8px">
          <?php foreach ($existingPieces as $i => $p): ?>
          <div class="d-flex justify-between align-center" style="padding:6px 0;border-bottom:1px solid var(--border)">
            <span><?= fileIcon($p['type'] ?? '') ?> <a href="<?= BASE_URL ?>/uploads/engagements/<?= sanitize($p['fichier']) ?>" target="_blank"><?= sanitize($p['nom']) ?></a> <small style="color:var(--text3)">(<?= formatFileSize($p['taille'] ?? 0) ?>)</small></span>
            <form method="post" action="supprimer_piece.php" style="display:inline" onsubmit="return confirm('Supprimer ce fichier ?')">
              <input type="hidden" name="engagement_id" value="<?= $eng['id'] ?>">
              <input type="hidden" name="delete_piece" value="<?= sanitize($p['fichier']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Supprimer">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="file" name="pieces[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods">
        <small style="color:var(--text3)">PDF, images, documents Office — 10 Mo max par fichier</small>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="detail.php?id=<?= $id ?>" class="btn btn-outline">Annuler</a>
      <div style="display:flex;gap:8px">
        <button type="submit" name="brouillon" class="btn btn-ghost">Enregistrer brouillon</button>
        <button type="submit" name="soumettre" class="btn btn-primary">Soumettre pour validation</button>
      </div>
    </div>
  </form>
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
  toggleGroupeProprietaire('edit-type-operation','edit-groupe-proprietaire-div','edit-groupe-proprietaire');
});

let ligneCounter = 0;

function addLigne(data) {
  ligneCounter++;
  const n = ligneCounter;
  const libelle = data ? data.libelle : '';
  const qte = data ? data.quantite : 1;
  const cu = data ? data.cout_unitaire : 0;
  const tr = document.createElement('tr');
  tr.id = 'ligne-' + n;
  tr.style.borderBottom = '1px solid var(--border)';
  tr.innerHTML = `
    <td style="padding:6px 4px;text-align:center;color:var(--text3)" class="ligne-ordre">${n}</td>
    <td style="padding:6px 4px"><input type="text" name="lignes[${n}][libelle]" class="form-control" placeholder="Libellé de la dépense" style="font-size:13px" value="${escapeHtml(libelle)}" required></td>
    <td style="padding:6px 4px"><input type="number" name="lignes[${n}][quantite]" class="form-control ligne-quantite" min="0.01" step="0.01" value="${qte}" style="font-size:13px;text-align:right" oninput="calcLigne(${n})"></td>
    <td style="padding:6px 4px"><input type="number" name="lignes[${n}][cout_unitaire]" class="form-control ligne-cu" min="0" step="1" value="${cu}" style="font-size:13px;text-align:right" oninput="calcLigne(${n})"></td>
    <td style="padding:6px 4px;text-align:right;font-weight:600" class="ligne-montant">${formatMontantJS(qte * cu)}</td>
    <td style="padding:6px 4px;text-align:center"><button type="button" class="btn btn-ghost btn-sm" onclick="removeLigne(${n})" style="color:var(--danger);padding:2px 6px" title="Supprimer">✕</button></td>
  `;
  document.getElementById('lignes-body').appendChild(tr);
  calcTotal();
}

function removeLigne(n) {
  const tr = document.getElementById('ligne-' + n);
  if (tr) tr.remove();
  renumberLignes();
  calcTotal();
}

function renumberLignes() {
  const rows = document.querySelectorAll('#lignes-body tr');
  rows.forEach((row, i) => {
    row.querySelector('.ligne-ordre').textContent = i + 1;
  });
}

function calcLigne(n) {
  const row = document.getElementById('ligne-' + n);
  if (!row) return;
  const qte = parseFloat(row.querySelector('.ligne-quantite').value) || 0;
  const cu = parseFloat(row.querySelector('.ligne-cu').value) || 0;
  const montant = qte * cu;
  row.querySelector('.ligne-montant').textContent = formatMontantJS(montant);
  calcTotal();
}

function calcTotal() {
  let total = 0;
  document.querySelectorAll('#lignes-body tr').forEach(row => {
    const qte = parseFloat(row.querySelector('.ligne-quantite').value) || 0;
    const cu = parseFloat(row.querySelector('.ligne-cu').value) || 0;
    total += qte * cu;
  });
  document.getElementById('lignes-total').textContent = formatMontantJS(total);
  document.getElementById('montant-hidden').value = total;
}

function formatMontantJS(n) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' FCFA';
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Pre-fill existing lignes
<?php foreach ($existingLignes as $ligne): ?>
addLigne({libelle: <?= json_encode($ligne['libelle']) ?>, quantite: <?= $ligne['quantite'] ?>, cout_unitaire: <?= $ligne['cout_unitaire'] ?>});
<?php endforeach; ?>
<?php if (empty($existingLignes)): ?>
addLigne();
<?php endif; ?>

// Validate at least one line before submit
document.querySelector('form').addEventListener('submit', function(e) {
  const rows = document.querySelectorAll('#lignes-body tr');
  let hasValid = false;
  rows.forEach(row => {
    const libelle = row.querySelector('input[name$="[libelle]"]');
    if (libelle && libelle.value.trim() !== '') hasValid = true;
  });
  if (!hasValid) {
    e.preventDefault();
    alert('Veuillez ajouter au moins une ligne descriptive.');
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>