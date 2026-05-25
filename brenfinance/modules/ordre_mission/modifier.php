<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('ordre_mission');

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
$om = $db->prepare("SELECT * FROM ordres_mission WHERE id=?");
$om->execute([$id]);
$om = $om->fetch();

if (!$om) { flash('danger', 'Ordre de mission introuvable.'); header('Location: index.php'); exit; }
if (!in_array($om['statut'], ['brouillon','renvoye'])) { flash('danger', 'Cet ordre de mission ne peut plus être modifié.'); header('Location: detail.php?id=' . $id); exit; }
if ((int)$om['demandeur_id'] !== $userId && !hasPermission('ordre_mission', 'all')) { flash('danger', "Vous n'êtes pas l'auteur de cet ordre de mission."); header('Location: index.php'); exit; }

$pageTitle = 'Modifier — ' . $om['numero'];

$existingLignes = $db->prepare("SELECT * FROM lignes_ordre_mission WHERE ordre_mission_id=? ORDER BY ordre");
$existingLignes->execute([$id]);
$existingLignes = $existingLignes->fetchAll();

$destinations = $db->query("SELECT id, libelle FROM destinations WHERE statut='actif' ORDER BY libelle")->fetchAll();
$caisses = $db->query("SELECT id, libelle FROM caisses WHERE statut='ouverte' ORDER BY libelle")->fetchAll();
$modesPaie = $db->query("SELECT id, libelle FROM modes_paiement WHERE statut='actif' ORDER BY libelle")->fetchAll();
$entreprise = getEntreprise();
$userService = $db->prepare("SELECT service_id FROM utilisateurs WHERE id=?");
$userService->execute([$userId]);
$serviceId = $userService->fetchColumn();
$srvNom = '';
if ($serviceId) { $sR = $db->prepare("SELECT nom FROM services WHERE id=?"); $sR->execute([$serviceId]); $srvNom = $sR->fetchColumn(); }
$userFonction = $user['fonction'] ?? $user['role_nom'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'brouillon';
    $objet = trim($_POST['objet'] ?? '');
    $lieuMission = trim($_POST['lieu_mission'] ?? '');
    $adresseMission = trim($_POST['adresse_mission'] ?? '');
    $dateDepart = $_POST['date_depart'] ?? '';
    $heureDepart = trim($_POST['heure_depart'] ?? '');
    $dateRetour = $_POST['date_retour'] ?? '';
    $heureRetour = trim($_POST['heure_retour'] ?? '');
    $moyenTransport = trim($_POST['moyen_transport'] ?? '');
    $personneUrgence = trim($_POST['personne_urgence'] ?? '');
    $telUrgence = trim($_POST['tel_urgence'] ?? '');
    $destinationId = (int)($_POST['destination_id'] ?? 0) ?: null;
    $modePaieId = (int)($_POST['mode_paiement_id'] ?? 0) ?: null;
    $caisseId = (int)($_POST['caisse_id'] ?? 0) ?: null;
    $plafondHebergement = ($_POST['plafond_hebergement'] ?? '') !== '' ? (float)$_POST['plafond_hebergement'] : null;
    $plafondRepas = ($_POST['plafond_repas'] ?? '') !== '' ? (float)$_POST['plafond_repas'] : null;
    $priorite = in_array($_POST['priorite'] ?? '', ['normale','urgente','tres_urgente']) ? $_POST['priorite'] : 'normale';

    $lignes = $_POST['lignes'] ?? [];
    $montant = 0;
    $validLignes = [];
    foreach ($lignes as $l) {
        $lib = trim($l['libelle'] ?? '');
        if ($lib === '') continue;
        $qte = max(0, (float)($l['quantite'] ?? 1));
        $cu = max(0, (float)($l['cout_unitaire'] ?? 0));
        $mt = $qte * $cu;
        $montant += $mt;
        $validLignes[] = ['libelle' => $lib, 'quantite' => $qte, 'cout_unitaire' => $cu, 'montant' => $mt];
    }

    if (empty($objet) || empty($dateDepart) || empty($dateRetour)) {
        flash('danger', 'Veuillez remplir tous les champs obligatoires.');
    } elseif (strtotime($dateRetour) < strtotime($dateDepart)) {
        flash('danger', 'La date de retour doit être postérieure à la date de départ.');
    } else {
        $statut = $action === 'soumettre' ? 'soumis' : $om['statut'];

        $db->prepare("UPDATE ordres_mission SET objet=?,lieu_mission=?,adresse_mission=?,date_depart=?,heure_depart=?,date_retour=?,heure_retour=?,moyen_transport=?,personne_urgence=?,tel_urgence=?,montant=?,destination_id=?,mode_paiement_id=?,caisse_id=?,plafond_hebergement=?,plafond_repas=?,priorite=?,statut=?,commentaire_rejet=NULL WHERE id=?")
           ->execute([$objet, $lieuMission ?: null, $adresseMission ?: null, $dateDepart, $heureDepart ?: null, $dateRetour, $heureRetour ?: null, $moyenTransport ?: null, $personneUrgence ?: null, $telUrgence ?: null, $montant, $destinationId, $modePaieId, $caisseId, $plafondHebergement, $plafondRepas, $priorite, $statut, $id]);

        $db->prepare("DELETE FROM lignes_ordre_mission WHERE ordre_mission_id=?")->execute([$id]);
        $ordre = 1;
        foreach ($validLignes as $l) {
            $db->prepare("INSERT INTO lignes_ordre_mission (ordre_mission_id,ordre,libelle,quantite,cout_unitaire,montant) VALUES (?,?,?,?,?,?)")->execute([$id, $ordre++, $l['libelle'], $l['quantite'], $l['cout_unitaire'], $l['montant']]);
        }

        $pieces = handleUploads('pieces', $om['numero'], 'ordres_mission');
        $existingPieces = json_decode($om['pieces_jointes'] ?? '[]', true);
        if (!empty($pieces)) {
            $db->prepare("UPDATE ordres_mission SET pieces_jointes=? WHERE id=?")->execute([json_encode(array_merge($existingPieces, $pieces)), $id]);
        }

        auditLog('modifier_ordre_mission', 'ordre_mission', 'ordres_mission', $id);

        if ($statut === 'soumis') {
            $srvR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
            $srvR->execute([$om['service_id']]);
            $respId = $srvR->fetchColumn();
            if ($respId) notify((int)$respId, null, 'soumis', 'Ordre de mission à valider', "L'ordre de mission {$om['numero']} a été modifié et resoumis.", $id);
            notifyUsersWithPermission('ordre_mission', 'valider_hierarchie', $id, 'soumis', 'Ordre de mission à valider', "L'ordre de mission {$om['numero']} attend une validation.", [$userId]);
            flash('success', "Ordre de mission {$om['numero']} soumis pour validation.");
        } else {
            flash('success', "Ordre de mission {$om['numero']} modifié.");
        }
        header('Location: detail.php?id=' . $id);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1>Modifier — <code><?= sanitize($om['numero']) ?></code></h1>
  <p>Ordre de mission en cours de modification</p>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Modifier la mission</span></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" id="form-om">
      <input type="hidden" name="action" id="form-action" value="brouillon">
      <input type="hidden" name="montant" id="montant-total" value="<?= $om['montant'] ?>">

      <!-- En-tête identification -->
      <div style="background:var(--surface2);padding:14px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:13px">
        <div class="d-flex justify-between" style="flex-wrap:wrap;gap:8px">
          <div><strong style="color:var(--primary)"><?= sanitize($entreprise['nom'] ?? 'BrenFinance') ?></strong><br><span style="color:var(--text3)"><?= sanitize($entreprise['adresse'] ?? '') ?></span></div>
          <div style="text-align:right"><span style="color:var(--text3)">Référence :</span> <strong style="color:var(--primary)"><?= sanitize($om['numero']) ?></strong><br><span style="color:var(--text3)">Date d'émission :</span> <strong><?= date('d/m/Y', strtotime($om['created_at'])) ?></strong></div>
        </div>
      </div>

      <!-- 1. Identification du collaborateur -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">1. Identification du collaborateur</div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Nom & Prénom</label>
          <input type="text" class="form-control" value="<?= sanitize($user['prenom'] . ' ' . $user['nom']) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Fonction</label>
          <input type="text" class="form-control" value="<?= sanitize($userFonction) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Service / Direction</label>
          <input type="text" class="form-control" value="<?= sanitize($srvNom ?: '—') ?>" disabled>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Personne à prévenir (urgence)</label>
          <input type="text" name="personne_urgence" class="form-control" value="<?= sanitize($om['personne_urgence'] ?? '') ?>" placeholder="Nom de la personne à contacter">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone d'urgence</label>
          <input type="tel" name="tel_urgence" class="form-control" value="<?= sanitize($om['tel_urgence'] ?? '') ?>" placeholder="+237 6XX XXX XXX">
        </div>
      </div>

      <!-- 2. Objet et lieu de la mission -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">2. Objet et lieu de la mission</div>
      <div class="form-group">
        <label class="form-label">Objet de la mission <span class="req">*</span></label>
        <textarea name="objet" class="form-control" rows="3" required><?= sanitize($om['objet']) ?></textarea>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Lieu / Ville de la mission</label>
          <input type="text" name="lieu_mission" class="form-control" value="<?= sanitize($om['lieu_mission'] ?? '') ?>" placeholder="Ex: Douala, Yaoundé, Bafoussam...">
        </div>
        <div class="form-group">
          <label class="form-label">Destination</label>
          <select name="destination_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($destinations as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $om['destination_id'] == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Adresse complète du lieu de déplacement</label>
        <input type="text" name="adresse_mission" class="form-control" value="<?= sanitize($om['adresse_mission'] ?? '') ?>" placeholder="Adresse précise du site, bâtiment, bureau...">
      </div>

      <!-- 3. Dates, durée et transport -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">3. Dates, durée et transport</div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Date de départ <span class="req">*</span></label>
          <input type="date" name="date_depart" class="form-control" required value="<?= sanitize($om['date_depart']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Heure de départ</label>
          <input type="time" name="heure_depart" class="form-control" value="<?= !empty($om['heure_depart']) ? sanitize($om['heure_depart']) : '08:00' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Moyen de transport</label>
          <select name="moyen_transport" class="form-control">
            <option value="">— Sélectionner —</option>
            <option value="Véhicule de service" <?= ($om['moyen_transport'] ?? '') === 'Véhicule de service' ? 'selected' : '' ?>>Véhicule de service</option>
            <option value="Véhicule personnel" <?= ($om['moyen_transport'] ?? '') === 'Véhicule personnel' ? 'selected' : '' ?>>Véhicule personnel</option>
            <option value="Avion" <?= ($om['moyen_transport'] ?? '') === 'Avion' ? 'selected' : '' ?>>Avion</option>
            <option value="Train" <?= ($om['moyen_transport'] ?? '') === 'Train' ? 'selected' : '' ?>>Train / Bus</option>
            <option value="Taxi" <?= ($om['moyen_transport'] ?? '') === 'Taxi' ? 'selected' : '' ?>>Taxi</option>
            <option value="Location véhicule" <?= ($om['moyen_transport'] ?? '') === 'Location véhicule' ? 'selected' : '' ?>>Location véhicule</option>
          </select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Date de retour <span class="req">*</span></label>
          <input type="date" name="date_retour" class="form-control" required value="<?= sanitize($om['date_retour']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Heure de retour</label>
          <input type="time" name="heure_retour" class="form-control" value="<?= !empty($om['heure_retour']) ? sanitize($om['heure_retour']) : '18:00' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select name="priorite" class="form-control">
            <option value="normale" <?= $om['priorite'] === 'normale' ? 'selected' : '' ?>>Normale</option>
            <option value="urgente" <?= $om['priorite'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
            <option value="tres_urgente" <?= $om['priorite'] === 'tres_urgente' ? 'selected' : '' ?>>Très urgente</option>
          </select>
        </div>
      </div>

      <!-- 4. Prise en charge des frais -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">4. Prise en charge des frais</div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Mode de paiement</label>
          <select name="mode_paiement_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($modesPaie as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $om['mode_paiement_id'] == $m['id'] ? 'selected' : '' ?>><?= sanitize($m['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Caisse</label>
          <select name="caisse_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($caisses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $om['caisse_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div></div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Plafond hébergement (par nuit)</label>
          <input type="number" name="plafond_hebergement" class="form-control" min="0" step="1" value="<?= !empty($om['plafond_hebergement']) ? $om['plafond_hebergement'] : '' ?>" placeholder="Ex: 25000">
        </div>
        <div class="form-group">
          <label class="form-label">Plafond repas (par jour)</label>
          <input type="number" name="plafond_repas" class="form-control" min="0" step="1" value="<?= !empty($om['plafond_repas']) ? $om['plafond_repas'] : '' ?>" placeholder="Ex: 10000">
        </div>
      </div>

      <!-- 5. Budget prévisionnel -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">5. Budget prévisionnel</div>
      <div class="form-group">
        <div class="d-flex justify-between align-center mb-0" style="margin-bottom:8px">
          <label class="form-label" style="margin:0">Détail des frais estimés</label>
          <button type="button" class="btn btn-outline btn-sm" onclick="addLigne()">+ Ajouter une ligne</button>
        </div>
        <div class="table-wrap">
          <table id="tbl-lignes">
            <thead>
              <tr>
                <th>Nature de la dépense</th>
                <th style="width:90px">Quantité</th>
                <th style="width:150px">Coût unitaire</th>
                <th style="width:150px">Montant</th>
                <th style="width:40px"></th>
              </tr>
            </thead>
            <tbody id="lignes-body"></tbody>
            <tfoot>
              <tr>
                <td colspan="3" style="text-align:right;font-weight:600">Total estimé</td>
                <td class="amount fw-bold" id="total-affiche"><?= formatMontant($om['montant']) ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- 6. Pièces jointes -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">6. Pièces jointes</div>
      <div class="form-group">
        <?php $existingPieces = json_decode($om['pieces_jointes'] ?? '[]', true); ?>
        <?php if (!empty($existingPieces)): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
          <?php foreach ($existingPieces as $p): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border:1px solid var(--border);border-radius:var(--radius);font-size:12px"><?= fileIcon($p['type'] ?? '') ?> <?= sanitize($p['nom']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="file" name="pieces[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
        <small style="color:var(--text3)">Ajouter de nouveaux fichiers (les existants sont conservés) — max 10 Mo par fichier</small>
      </div>

      <!-- Boutons -->
      <div style="margin-top:24px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:20px">
        <a href="detail.php?id=<?= $id ?>" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-ghost" onclick="document.getElementById('form-action').value='brouillon'">Enregistrer brouillon</button>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('form-action').value='soumettre'">Soumettre pour validation</button>
      </div>
    </form>
  </div>
</div>

<script>
var ligneCount = 0;
function addLigne(data) {
    var n = ligneCount++;
    var tr = document.createElement('tr');
    tr.id = 'ligne-' + n;
    tr.innerHTML = '<td><input type="text" name="lignes[' + n + '][libelle]" class="form-control" placeholder="Ex: Transport, Hébergement, Repas, Per diem..." value="' + (data ? data.libelle : '') + '" required></td>' +
        '<td><input type="number" name="lignes[' + n + '][quantite]" class="form-control" min="0" step="0.01" value="' + (data ? data.quantite : 1) + '" onchange="calcLigne(' + n + ')" oninput="calcLigne(' + n + ')"></td>' +
        '<td><input type="number" name="lignes[' + n + '][cout_unitaire]" class="form-control" min="0" step="1" value="' + (data ? data.cout_unitaire : 0) + '" onchange="calcLigne(' + n + ')" oninput="calcLigne(' + n + ')"></td>' +
        '<td class="amount" id="ligne-montant-' + n + '">' + (data ? formatMontantJS(data.montant) : '0') + ' FCFA<input type="hidden" name="lignes[' + n + '][montant]" value="' + (data ? data.montant : 0) + '"></td>' +
        '<td><button type="button" class="btn btn-ghost btn-sm" onclick="removeLigne(' + n + ')" title="Supprimer">✕</button></td>';
    document.getElementById('lignes-body').appendChild(tr);
    if (data) calcLigne(n);
}
function calcLigne(n) {
    var q = parseFloat(document.querySelector('[name="lignes[' + n + '][quantite]"]').value) || 0;
    var cu = parseFloat(document.querySelector('[name="lignes[' + n + '][cout_unitaire]"]').value) || 0;
    var m = q * cu;
    document.getElementById('ligne-montant-' + n).innerHTML = formatMontantJS(m) + ' FCFA<input type="hidden" name="lignes[' + n + '][montant]" value="' + m + '">';
    calcTotal();
}
function calcTotal() {
    var total = 0;
    document.querySelectorAll('[name$="][montant]"]').forEach(function(el) { total += parseFloat(el.value) || 0; });
    document.getElementById('total-affiche').textContent = formatMontantJS(total) + ' FCFA';
    document.getElementById('montant-total').value = total;
}
function removeLigne(n) {
    var row = document.getElementById('ligne-' + n);
    if (row) row.remove();
    calcTotal();
}
function formatMontantJS(v) { return new Intl.NumberFormat('fr-CM').format(v); }

<?php foreach ($existingLignes as $l): ?>
addLigne({libelle: <?= json_encode($l['libelle']) ?>, quantite: <?= $l['quantite'] ?>, cout_unitaire: <?= $l['cout_unitaire'] ?>, montant: <?= $l['montant'] ?>});
<?php endforeach; ?>
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>