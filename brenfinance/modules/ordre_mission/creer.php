<?php
require_once __DIR__ . '/../../includes/functions.php';
if (!hasPermission('ordre_mission', 'creer') && !hasPermission('ordre_mission', 'all')) {
    flash('danger', "Vous n'avez pas la permission de créer un ordre de mission.");
    header('Location: ' . BASE_URL . '/modules/ordre_mission/index.php');
    exit;
}
$pageTitle = 'Nouvel ordre de mission';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();

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
        $numero = generateNumero('OM');
        $statut = $action === 'soumettre' ? 'soumis' : 'brouillon';

        $stmt = $db->prepare("INSERT INTO ordres_mission (numero,demandeur_id,service_id,destination_id,objet,lieu_mission,adresse_mission,date_depart,heure_depart,date_retour,heure_retour,moyen_transport,personne_urgence,tel_urgence,montant,mode_paiement_id,caisse_id,plafond_hebergement,plafond_repas,priorite,statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$numero, $userId, $serviceId ?: null, $destinationId, $objet, $lieuMission ?: null, $adresseMission ?: null, $dateDepart, $heureDepart ?: null, $dateRetour, $heureRetour ?: null, $moyenTransport ?: null, $personneUrgence ?: null, $telUrgence ?: null, $montant, $modePaieId, $caisseId, $plafondHebergement, $plafondRepas, $priorite, $statut]);
        $omId = $db->lastInsertId();

        $ordre = 1;
        foreach ($validLignes as $l) {
            $db->prepare("INSERT INTO lignes_ordre_mission (ordre_mission_id,ordre,libelle,quantite,cout_unitaire,montant) VALUES (?,?,?,?,?,?)")->execute([$omId, $ordre++, $l['libelle'], $l['quantite'], $l['cout_unitaire'], $l['montant']]);
        }

        $pieces = handleUploads('pieces', $numero, 'ordres_mission');
        if (!empty($pieces)) {
            $db->prepare("UPDATE ordres_mission SET pieces_jointes=? WHERE id=?")->execute([json_encode($pieces), $omId]);
        }

        auditLog('creer_ordre_mission', 'ordre_mission', 'ordres_mission', $omId);

        if ($statut === 'soumis') {
            $respR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
            $respR->execute([$serviceId ?: 0]);
            $respId = $respR->fetchColumn();
            if ($respId) {
                notify((int)$respId, null, 'soumis', 'Ordre de mission à valider', "L'ordre de mission $numero soumis par {$user['prenom']} {$user['nom']} attend votre validation hiérarchique.", $omId);
            }
            notifyUsersWithPermission('ordre_mission', 'valider_hierarchie', $omId, 'soumis', 'Ordre de mission à valider', "L'ordre de mission $numero attend une validation hiérarchique.", [$userId]);
            flash('success', "Ordre de mission $numero soumis pour validation.");
        } else {
            flash('success', "Ordre de mission $numero enregistré comme brouillon.");
        }
        header('Location: detail.php?id=' . $omId);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1>Nouvel ordre de mission</h1>
  <p>Fiche d'autorisation de déplacement professionnel</p>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-plane"></i> Ordre de Mission — Création</span></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" id="form-om">
      <input type="hidden" name="action" id="form-action" value="brouillon">
      <input type="hidden" name="montant" id="montant-total" value="0">

      <!-- En-tête identification -->
      <div style="background:var(--surface2);padding:14px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:13px">
        <div class="d-flex justify-between" style="flex-wrap:wrap;gap:8px">
          <div><strong style="color:var(--primary)"><?= sanitize($entreprise['nom'] ?? 'BrenFinance') ?></strong><br><span style="color:var(--text3)"><?= sanitize($entreprise['adresse'] ?? '') ?></span></div>
          <div style="text-align:right"><span style="color:var(--text3)">Référence :</span> <strong style="color:var(--primary)">OM-<?= date('Y') ?>-...</strong><br><span style="color:var(--text3)">Date d'émission :</span> <strong><?= date('d/m/Y') ?></strong></div>
        </div>
      </div>

      <!-- Identification du collaborateur -->
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
          <input type="text" name="personne_urgence" class="form-control" placeholder="Nom de la personne à contacter">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone d'urgence</label>
          <input type="tel" name="tel_urgence" class="form-control" placeholder="+237 6XX XXX XXX">
        </div>
      </div>

      <!-- Objet et lieu de la mission -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">2. Objet et lieu de la mission</div>
      <div class="form-group">
        <label class="form-label">Objet de la mission <span class="req">*</span></label>
        <textarea name="objet" class="form-control" rows="3" required placeholder="Décrivez les objectifs et le but de la mission..."></textarea>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Lieu / Ville de la mission</label>
          <input type="text" name="lieu_mission" class="form-control" placeholder="Ex: Douala, Yaoundé, Bafoussam...">
        </div>
        <div class="form-group">
          <label class="form-label">Destination</label>
          <select name="destination_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($destinations as $d): ?>
            <option value="<?= $d['id'] ?>"><?= sanitize($d['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Adresse complète du lieu de déplacement</label>
        <input type="text" name="adresse_mission" class="form-control" placeholder="Adresse précise du site, bâtiment, bureau...">
      </div>

      <!-- Dates et transport -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">3. Dates, durée et transport</div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Date de départ <span class="req">*</span></label>
          <input type="date" name="date_depart" class="form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Heure de départ</label>
          <input type="time" name="heure_depart" class="form-control" value="08:00">
        </div>
        <div class="form-group">
          <label class="form-label">Moyen de transport</label>
          <select name="moyen_transport" class="form-control">
            <option value="">— Sélectionner —</option>
            <option value="Véhicule de service">Véhicule de service</option>
            <option value="Véhicule personnel">Véhicule personnel</option>
            <option value="Avion">Avion</option>
            <option value="Train">Train / Bus</option>
            <option value="Taxi">Taxi</option>
            <option value="Location véhicule">Location véhicule</option>
          </select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Date de retour <span class="req">*</span></label>
          <input type="date" name="date_retour" class="form-control" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Heure de retour</label>
          <input type="time" name="heure_retour" class="form-control" value="18:00">
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

      <!-- Frais et budget -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">4. Prise en charge des frais</div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Mode de paiement</label>
          <select name="mode_paiement_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($modesPaie as $m): ?>
            <option value="<?= $m['id'] ?>"><?= sanitize($m['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Caisse</label>
          <select name="caisse_id" class="form-control">
            <option value="">— Sélectionner —</option>
            <?php foreach ($caisses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div></div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Plafond hébergement (par nuit)</label>
          <input type="number" name="plafond_hebergement" class="form-control" min="0" step="1" placeholder="Ex: 25000">
        </div>
        <div class="form-group">
          <label class="form-label">Plafond repas (par jour)</label>
          <input type="number" name="plafond_repas" class="form-control" min="0" step="1" placeholder="Ex: 10000">
        </div>
      </div>

      <!-- Détail des frais -->
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
                <td class="amount fw-bold" id="total-affiche">0 FCFA</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Pièces jointes -->
      <div style="font-size:13px;font-weight:600;color:var(--primary);margin:24px 0 8px;text-transform:uppercase;letter-spacing:0.5px">6. Pièces jointes</div>
      <div class="form-group">
        <input type="file" name="pieces[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
        <small style="color:var(--text3)">PDF, Word, Excel, images — max 10 Mo par fichier</small>
      </div>

      <!-- Boutons -->
      <div style="margin-top:24px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:20px">
        <a href="index.php" class="btn btn-outline">Annuler</a>
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
        '<td class="amount" id="ligne-montant-' + n + '">' + (data ? formatMontantJS(data.montant) : '0 FCFA') + '<input type="hidden" name="lignes[' + n + '][montant]" value="' + (data ? data.montant : 0) + '"></td>' +
        '<td><button type="button" class="btn btn-ghost btn-sm" onclick="removeLigne(' + n + ')" title="Supprimer">✕</button></td>';
    document.getElementById('lignes-body').appendChild(tr);
    calcLigne(n);
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
function removeLigne(n) { var row = document.getElementById('ligne-' + n); if (row) row.remove(); calcTotal(); }
function formatMontantJS(v) { return new Intl.NumberFormat('fr-CM').format(v); }
addLigne();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>