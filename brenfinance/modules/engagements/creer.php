<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('engagements');
if (!hasPermission('engagements', 'creer')) { flash('danger','Vous n\'avez pas le droit de créer un engagement.'); header('Location: '.BASE_URL.'/modules/engagements/index.php'); exit; }
$pageTitle = 'Nouvelle demande d\'engagement';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'creer_demande') {
        $numero = generateNumero('ENG');
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
            header('Location: ' . BASE_URL . '/modules/engagements/creer.php'); exit;
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

        $db->prepare("INSERT INTO demandes_engagement (numero,demandeur_id,service_id,fournisseur_id,beneficiaire_id,mode_paiement_id,type_operation_id,groupe_proprietaire_id,destination_id,objet,montant,date_besoin,priorite,statut,caisse_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$numero,$userId,$serviceId,$fournId,$benId,$modeId,$typeOperationId,$groupeProprietaireId,$destinationId,$objet,$montant,$dateBesoin,$priorite,$statut,$caisseId]);
        $engId = $db->lastInsertId();

        // Insert lignes
        $ordre = 1;
        foreach ($validLignes as $vl) {
            $db->prepare("INSERT INTO lignes_engagement (engagement_id,ordre,libelle,quantite,cout_unitaire,montant) VALUES (?,?,?,?,?,?)")
               ->execute([$engId, $ordre++, $vl['libelle'], $vl['quantite'], $vl['cout_unitaire'], $vl['montant']]);
        }

        // Handle file uploads
        $pieces = handleUploads('pieces', $numero);
        if (!empty($pieces)) {
            $db->prepare("UPDATE demandes_engagement SET pieces_jointes=? WHERE id=?")
               ->execute([json_encode($pieces), $engId]);
        }

        auditLog('creer_engagement','engagements','demandes_engagement');

        // Notify responsable N+1 when submitted
        if ($statut === 'soumis') {
            $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
            $respR->execute([$serviceId]);
            $resp = $respR->fetch();
            if ($resp && $resp['responsable_id']) {
                notify((int)$resp['responsable_id'], $engId, 'soumis', 'Nouvelle demande d\'engagement', 'Demande N° ' . $numero . ' soumise pour validation hiérarchique');
            }
        }

        flash('success', 'Demande d\'engagement N° ' . $numero . ' créée (' . ($statut==='soumis'?'soumise pour validation':'brouillon') . ').');
        header('Location: ' . BASE_URL . '/modules/engagements/detail.php?id=' . $engId);
        exit;
    }
}

// Data for form
$services = $db->query("SELECT * FROM services WHERE statut='actif' ORDER BY nom")->fetchAll();
$beneficiaires = $db->query("SELECT * FROM beneficiaires WHERE statut='actif' ORDER BY nom")->fetchAll();
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
$defaultCaisse = $db->query("SELECT id FROM caisses WHERE statut='ouverte' ORDER BY id LIMIT 1")->fetch();
$defaultCaisseId = $defaultCaisse ? (int)$defaultCaisse['id'] : null;
$userBenefR = $db->prepare("SELECT id FROM beneficiaires WHERE user_id=? AND statut='actif'");
$userBenefR->execute([$userId]);
$userBenefRow = $userBenefR->fetch();
$userBenefId = $userBenefRow ? (int)$userBenefRow['id'] : null;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <a href="index.php" class="btn btn-ghost btn-sm" style="margin-bottom:8px">&larr; Retour aux engagements</a>
    <h1>Nouvelle demande d'engagement</h1>
    <p>Remplissez le formulaire ci-dessous pour créer une demande</p>
  </div>
</div>

<div class="card" style="max-width:900px;margin:0 auto">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="creer_demande">
    <input type="hidden" name="beneficiaire_id" value="<?= $userBenefId ?>">
    <div class="card-body">

      <!-- 1. Date de besoin -->
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Date de besoin</label>
          <input type="date" name="date_besoin" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select name="priorite" class="form-control">
            <option value="normale">Normale</option>
            <option value="urgente">Urgente</option>
            <option value="tres_urgente">Très urgente</option>
          </select>
        </div>
      </div>

      <!-- 2. Type d'opération -->
      <div class="form-group">
        <label class="form-label">Type d'opération <span class="req">*</span></label>
        <select name="type_operation_id" id="create-type-operation" class="form-control" required onchange="toggleGroupeProprietaire('create-type-operation','create-groupe-proprietaire-div','create-groupe-proprietaire')">
          <option value="" data-code="">-- Choisir --</option>
          <?php foreach($typesOperations as $to): ?>
          <option value="<?= $to['id'] ?>" data-code="<?= sanitize($to['code']) ?>">[<?= $to['sens']==='credit'?'+':'-' ?>] <?= sanitize($to['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="create-groupe-proprietaire-div" style="display:none">
        <label class="form-label">Groupe propriétaire <span class="req">*</span></label>
        <select name="groupe_proprietaire_id" id="create-groupe-proprietaire" class="form-control">
          <option value="">-- Choisir --</option>
          <?php foreach($groupesProprietaires as $gp): ?>
          <option value="<?= $gp['id'] ?>"><?= sanitize($gp['libelle']) ?></option>
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
          <option value="<?= $d['id'] ?>"><?= sanitize($d['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 5. Motif de l'engagement -->
      <div class="form-group">
        <label class="form-label">Motif de l'engagement <span class="req">*</span></label>
        <textarea name="objet" class="form-control" rows="3" placeholder="Description détaillée de la dépense..." required></textarea>
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

      <!-- Montant total (hidden field, auto-calculated) -->
      <input type="hidden" name="montant" id="montant-hidden" value="0">

      <!-- 7. Autres champs -->
      <div class="form-group">
        <label class="form-label">Service <span class="req">*</span></label>
        <select name="service_id" class="form-control" required>
          <?php foreach($services as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id']==$userServiceId?'selected':'' ?>><?= sanitize($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Fournisseur</label>
          <select name="fournisseur_id" class="form-control">
            <option value="">-- Choisir --</option>
            <?php foreach($fournisseurs as $f): ?>
            <option value="<?= $f['id'] ?>"><?= sanitize($f['nom']) ?></option>
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
          <option value="<?= $c['id'] ?>" <?= $c['id']==$defaultCaisseId?'selected':'' ?>><?= sanitize($c['libelle']) ?> (<?= sanitize($c['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Documents justificatifs <span style="font-weight:400;color:var(--text3)">(facultatif — plusieurs fichiers possibles)</span></label>
        <div id="drop-zone" style="border:2px dashed var(--border);border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s" onclick="document.getElementById('file-input').click()">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px;color:var(--text3)"></i>
          <p style="margin:8px 0 4px;color:var(--text2)">Cliquez ou glissez-déposez vos fichiers ici</p>
          <small style="color:var(--text3)">PDF, images, documents Office — 10 Mo max par fichier</small>
        </div>
        <input type="file" id="file-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods" style="display:none" onchange="addFiles(this.files)">
        <div id="file-list" style="margin-top:8px"></div>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-outline">Annuler</a>
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
  toggleGroupeProprietaire('create-type-operation','create-groupe-proprietaire-div','create-groupe-proprietaire');
});

let ligneCounter = 0;

function addLigne() {
  ligneCounter++;
  const n = ligneCounter;
  const tr = document.createElement('tr');
  tr.id = 'ligne-' + n;
  tr.style.borderBottom = '1px solid var(--border)';
  tr.innerHTML = `
    <td style="padding:6px 4px;text-align:center;color:var(--text3)" class="ligne-ordre">${n}</td>
    <td style="padding:6px 4px"><input type="text" name="lignes[${n}][libelle]" class="form-control" placeholder="Libellé de la dépense" style="font-size:13px" required></td>
    <td style="padding:6px 4px"><input type="number" name="lignes[${n}][quantite]" class="form-control ligne-quantite" min="0.01" step="0.01" value="1" style="font-size:13px;text-align:right" oninput="calcLigne(${n})"></td>
    <td style="padding:6px 4px"><input type="number" name="lignes[${n}][cout_unitaire]" class="form-control ligne-cu" min="0" step="1" value="0" style="font-size:13px;text-align:right" oninput="calcLigne(${n})"></td>
    <td style="padding:6px 4px;text-align:right;font-weight:600" class="ligne-montant">0 FCFA</td>
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

// Start with one empty line
addLigne();

// Multi-file upload with drag & drop
let selectedFiles = [];

const dropZone = document.getElementById('drop-zone');
const fileList = document.getElementById('file-list');
const fileInput = document.getElementById('file-input');

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' o';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
  return (bytes / 1048576).toFixed(1) + ' Mo';
}

function getFileIcon(name) {
  const ext = name.split('.').pop().toLowerCase();
  const icons = {pdf:'fa-file-pdf',jpg:'fa-file-image',jpeg:'fa-file-image',png:'fa-file-image',gif:'fa-file-image',
    doc:'fa-file-word',docx:'fa-file-word',xls:'fa-file-excel',xlsx:'fa-file-excel',ppt:'fa-file-powerpoint',pptx:'fa-file-powerpoint',odt:'fa-file-lines',ods:'fa-file-excel'};
  return icons[ext] || 'fa-file';
}

function addFiles(files) {
  for (const f of files) {
    if (selectedFiles.some(sf => sf.name === f.name && sf.size === f.size)) continue;
    selectedFiles.push(f);
  }
  renderFileList();
  fileInput.value = '';
}

function removeFile(index) {
  selectedFiles.splice(index, 1);
  renderFileList();
}

function renderFileList() {
  if (selectedFiles.length === 0) {
    fileList.innerHTML = '';
    return;
  }
  let html = '<div style="display:flex;flex-direction:column;gap:6px">';
  selectedFiles.forEach((f, i) => {
    html += `<div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--bg-secondary,var(--bg-tertiary,#f5f5f5));border-radius:6px;font-size:13px">
      <i class="fa-solid ${getFileIcon(f.name)}" style="color:var(--primary);font-size:16px"></i>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${f.name}</span>
      <span style="color:var(--text3);font-size:12px">${formatSize(f.size)}</span>
      <button type="button" onclick="removeFile(${i})" style="background:none;border:none;cursor:pointer;color:var(--danger,#e74c3c);font-size:14px;padding:0 4px" title="Retirer">&times;</button>
    </div>`;
  });
  html += '</div>';
  fileList.innerHTML = html;
}

// Drag & drop events
['dragenter','dragover'].forEach(evt => {
  dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dropZone.style.borderColor='var(--primary)'; dropZone.style.background='var(--bg-secondary,var(--bg-tertiary,#f0f0f0))'; });
});
['dragleave','drop'].forEach(evt => {
  dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dropZone.style.borderColor=''; dropZone.style.background=''; });
});
dropZone.addEventListener('drop', e => { addFiles(e.dataTransfer.files); });

// Inject selected files into form on submit
document.querySelector('form').addEventListener('submit', function() {
  if (selectedFiles.length === 0) return;
  const dt = new DataTransfer();
  selectedFiles.forEach(f => dt.items.add(f));
  fileInput.files = dt.files;
  fileInput.name = 'pieces[]';
  fileInput.style.display = 'none';
  this.appendChild(fileInput);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>