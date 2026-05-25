<?php
$pageTitle = 'Manifeste — Chargement';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('manifest_gerer');

$agences = getAgences();
$userAgenceId = $user['agence_id'] ?? 0;
$isAgenceAdmin = in_array($user['role'], ['admin', 'superviseur']);
$vehicules = getVehiculesDisponibles();
$trajetsActifs = getTrajetsActifs();
// Fetch escales for each trajet
foreach ($trajetsActifs as &$t) {
    $t['escales'] = Database::fetchAll(
        "SELECT te.ordre, te.duree_arret, a.id AS agence_id, a.nom
         FROM trajet_escales te JOIN agences a ON te.agence_id = a.id
         WHERE te.trajet_id = ? ORDER BY te.ordre", [$t['id']]
    );
}
unset($t);
$voyages = getVoyagesOuverts();
if (!$isAgenceAdmin && $userAgenceId) {
    $voyages = array_filter($voyages, function($v) use ($userAgenceId) {
        if ($v['agence_depart_id'] == $userAgenceId || $v['agence_arrivee_id'] == $userAgenceId) return true;
        // Verifier si le trajet du voyage passe par l'agence de l'utilisateur
        if ($v['trajet_id']) {
            $escale = Database::fetchOne(
                "SELECT id FROM trajet_escales WHERE trajet_id=? AND agence_id=? LIMIT 1",
                [$v['trajet_id'], $userAgenceId]
            );
            if ($escale) return true;
        }
        return false;
    });
}
$errors = [];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ========== CREER UN NOUVEAU VOYAGE + CHARGER ==========
    if ($_POST['action'] === 'create_and_load') {
        $required = ['vehicule_id','trajet_id','date_depart'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) $errors[] = "Le champ $f est requis pour le voyage.";
        }
        $selectedIds = array_map('intval', $_POST['colis_ids'] ?? []);
        if (empty($selectedIds)) {
            $errors[] = "Selectionnez au moins un colis a charger.";
        }

        if (!$errors) {
            $trajet = Database::fetchOne("SELECT * FROM trajets WHERE id=?", [intval($_POST['trajet_id'])]);
            $vehicule = Database::fetchOne("SELECT * FROM vehicules WHERE id=?", [intval($_POST['vehicule_id'])]);
            if (!$trajet || !$vehicule) {
                $errors[] = "Trajet ou vehicule invalide.";
            }
        }

        // Verifier que les colis appartiennent a l'agence de depart, a l'agence de l'utilisateur, ou sont en transit
        if (!$errors && $trajet) {
            $agDepart = $trajet['agence_depart_id'];
            $myAgence = $user['agence_id'] ?? 0;
            $in = implode(',', $selectedIds);
            $invalidColis = Database::fetchAll(
                "SELECT c.numero_colis FROM colis c WHERE c.id IN ($in)
                 AND NOT (c.agence_depart_id = ? OR c.escale_actuelle_id = ? OR (c.statut IN ('en_transit','en_livraison') AND c.agence_arrivee_id = ?)
                          OR c.agence_depart_id = ? OR c.escale_actuelle_id = ?)",
                [$agDepart, $agDepart, $agDepart, $myAgence, $myAgence]
            );
            if ($invalidColis) {
                $nums = implode(', ', array_column($invalidColis, 'numero_colis'));
                $errors[] = "Colis non autorises (autre agence) : $nums";
            }
        }

        if (!$errors) {
            $num = genererNumeroVoyage();
            $voyageId = Database::insert(
                "INSERT INTO voyages (numero_voyage,vehicule_id,trajet_id,transporteur,matricule_vehicule,chauffeur,telephone_chauffeur,
                 agence_depart_id,agence_arrivee_id,date_depart,date_arrivee_prevue,notes,cree_par)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $num, intval($_POST['vehicule_id']), intval($_POST['trajet_id']),
                    $vehicule['designation'], $vehicule['immatriculation'],
                    trim($_POST['chauffeur'] ?? ''), trim($_POST['telephone_chauffeur'] ?? ''),
                    $trajet['agence_depart_id'], $trajet['agence_arrivee_id'],
                    $_POST['date_depart'],
                    !empty($_POST['date_arrivee_prevue']) ? $_POST['date_arrivee_prevue'] : null,
                    trim($_POST['notes'] ?? ''),
                    $user['id']
                ]
            );
            Auth::logAction('VOYAGE_CREE', 'voyages', $voyageId, "N°: $num");

            // Charger les colis
            $agenceDepart = Database::fetchOne("SELECT nom FROM agences WHERE id=?", [$trajet['agence_depart_id']]);
            $localisation = $agenceDepart ? $agenceDepart['nom'] : '';
            $in = implode(',', array_map('intval', $selectedIds));
            Database::execute("UPDATE colis SET statut='en_transit', voyage_id=?, escale_actuelle_id=NULL WHERE id IN ($in) AND statut='enregistre'", [$voyageId]);
            foreach ($selectedIds as $cid) {
                Database::execute(
                    "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
                    [$cid, 'en_transit', $localisation, "Charge dans le voyage $num", $user['id']]
                );
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Manifeste cree ! " . count($selectedIds) . " colis charges dans le voyage $num."];
            header("Location: voyages.php");
            exit;
        }
    }

    // ========== CHARGER DANS UN VOYAGE EXISTANT ==========
    if ($_POST['action'] === 'load_existing') {
        $voyageId = intval($_POST['voyage_id'] ?? 0);
        $selectedIds = array_map('intval', $_POST['colis_ids'] ?? []);
        if (!$voyageId) $errors[] = "Selectionnez un voyage.";
        if (empty($selectedIds)) $errors[] = "Selectionnez au moins un colis a charger.";

        // Verifier que les colis appartiennent a l'agence du voyage, a l'agence de l'utilisateur, ou y sont en escale
        if (!$errors && $voyageId) {
            $voyage = Database::fetchOne("SELECT * FROM voyages WHERE id=?", [$voyageId]);
            if ($voyage) {
                $agDepart = $voyage['agence_depart_id'];
                $myAgence = $user['agence_id'] ?? 0;
                $in = implode(',', $selectedIds);
                $invalidColis = Database::fetchAll(
                    "SELECT c.numero_colis FROM colis c WHERE c.id IN ($in)
                     AND NOT (c.agence_depart_id = ? OR c.escale_actuelle_id = ? OR (c.statut IN ('en_transit','en_livraison') AND c.agence_arrivee_id = ?)
                              OR c.agence_depart_id = ? OR c.escale_actuelle_id = ?)",
                    [$agDepart, $agDepart, $agDepart, $myAgence, $myAgence]
                );
                if ($invalidColis) {
                    $nums = implode(', ', array_column($invalidColis, 'numero_colis'));
                    $errors[] = "Colis non autorises (autre agence) : $nums";
                }
            }
        }

        if (!$errors) {
            $voyage = Database::fetchOne("SELECT v.*, ad.nom AS nom_depart FROM voyages v JOIN agences ad ON v.agence_depart_id=ad.id WHERE v.id=?", [$voyageId]);
            $localisation = $voyage ? $voyage['nom_depart'] : '';
            $numVoyage = $voyage ? $voyage['numero_voyage'] : '';
            $in = implode(',', array_map('intval', $selectedIds));
            Database::execute("UPDATE colis SET statut='en_transit', voyage_id=?, escale_actuelle_id=NULL WHERE id IN ($in) AND statut='enregistre'", [$voyageId]);
            foreach ($selectedIds as $cid) {
                Database::execute(
                    "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
                    [$cid, 'en_transit', $localisation, "Charge dans le voyage $numVoyage", $user['id']]
                );
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => count($selectedIds) . " colis charges dans le voyage $numVoyage."];
            header("Location: voyages.php");
            exit;
        }
    }
}

// Filtre agence : par defaut l'agence de l'utilisateur, admin/superviseur peut changer
$filterAgence = $isAgenceAdmin ? intval($_GET['agence'] ?? 0) : $userAgenceId;
if (!$filterAgence && !$isAgenceAdmin) $filterAgence = $userAgenceId;

$where = ["(c.statut = 'enregistre' AND c.voyage_id IS NULL)"];
$params = [];
if ($filterAgence) {
    $where[] = "(c.agence_depart_id = ?)";
    $params[] = $filterAgence;
}
$whereStr = implode(' AND ', $where);

// Colis directs : depart de l'agence du voyage
$colisDispo = Database::fetchAll(
    "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
            ad.nom AS agence_depart_nom, aa.nom AS agence_arrivee_nom,
            'direct' AS type_chargement
     FROM colis c
     JOIN agences ad ON c.agence_depart_id=ad.id
     JOIN agences aa ON c.agence_arrivee_id=aa.id
     WHERE $whereStr
     ORDER BY c.created_at DESC", $params);

// Colis transit : en_transit ou en_livraison a cette agence (decharges d'un voyage ou en attente)
$colisTransit = [];
if ($filterAgence) {
    $colisTransit = Database::fetchAll(
        "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
                ad.nom AS agence_depart_nom, aa.nom AS agence_arrivee_nom,
                'transit' AS type_chargement
         FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id
         JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.voyage_id IS NULL AND c.statut IN ('en_transit','en_livraison')
           AND (c.escale_actuelle_id = ? OR (c.agence_arrivee_id = ? AND c.statut = 'en_transit'))
           AND c.agence_depart_id != ?
         ORDER BY c.created_at DESC", [$filterAgence, $filterAgence, $filterAgence]);
}
?>

<div class="d-flex justify-between align-center mb-3">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700"><i class="fas fa-truck-loading" style="color:var(--primary);margin-right:8px"></i>Manifeste — Chargement</h2>
        <p class="text-muted" style="font-size:.85rem">Selectionnez les colis a charger sur un voyage</p>
    </div>
    <a href="voyages.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error" style="margin-bottom:20px">
    <i class="fas fa-exclamation-triangle"></i>
    <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if (empty($colisDispo) && empty($colisTransit)): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-box-open" style="font-size:2rem;color:var(--text-muted)"></i>
        <h3>Aucun colis disponible</h3>
        <p>Tous les colis enregistres sont deja assignes ou aucun colis n'a ete cree.</p>
        <a href="nouveau_colis.php?type=normal" class="btn btn-primary" style="margin-top:12px"><i class="fas fa-plus"></i> Creer un colis</a>
    </div>
</div>
<?php else: ?>

<form method="post" id="manifestForm">
<input type="hidden" name="action" id="manifest_action" value="load_existing">

<!-- SECTION VOYAGE -->
<div class="card mb-2">
    <div class="card-header"><div class="card-title"><i class="fas fa-route" style="margin-right:6px;color:var(--primary)"></i>Voyage</div></div>
    <div style="padding:16px 20px">
        <!-- Choix du type -->
        <div style="display:flex;gap:12px;margin-bottom:16px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:8px;border:2px solid var(--primary);background:rgba(232,80,10,0.06)">
                <input type="radio" name="voyage_type" value="existing" checked onchange="toggleVoyageType()" style="accent-color:var(--primary)">
                <span style="font-weight:600">Voyage existant</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:8px;border:2px solid var(--border);background:var(--bg-card2)">
                <input type="radio" name="voyage_type" value="new" onchange="toggleVoyageType()" style="accent-color:var(--purple)">
                <span style="font-weight:600">Nouveau voyage</span>
            </label>
        </div>

        <!-- Voyage existant -->
        <div id="voyage-existing">
            <select name="voyage_id" class="form-control" required>
                <option value="">— Selectionner un voyage —</option>
                <?php foreach ($voyages as $v): ?>
                <option value="<?= $v['id'] ?>">
                    <?= $v['numero_voyage'] ?> — <?= $v['nom_depart'] ?> → <?= $v['nom_arrivee'] ?>
                    (<?= formatDate($v['date_depart'],'d/m/Y H:i') ?>) — <?= htmlspecialchars($v['vehicule_designation'] ?? $v['transporteur'] ?? '—') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Nouveau voyage -->
        <div id="voyage-new" style="display:none">
            <div class="form-grid" style="margin-top:8px">
                <div class="form-group">
                    <label class="form-label">Vehicule <span class="req">*</span></label>
                    <select name="vehicule_id" class="form-control" required>
                        <option value="">— Selectionner un vehicule —</option>
                        <?php foreach ($vehicules as $vh): ?>
                        <option value="<?= $vh['id'] ?>"><?= htmlspecialchars($vh['immatriculation']) ?> — <?= htmlspecialchars($vh['designation']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Trajet <span class="req">*</span></label>
                    <select name="trajet_id" class="form-control" required id="manifest_trajet_select">
                        <option value="">— Selectionner un trajet —</option>
                        <?php foreach ($trajetsActifs as $t): ?>
                        <option value="<?= $t['id'] ?>"
                                data-depart="<?= htmlspecialchars($t['nom_depart']) ?>"
                                data-arrivee="<?= htmlspecialchars($t['nom_arrivee']) ?>"
                                data-escales="<?= htmlspecialchars(json_encode($t['escales'])) ?>">
                            <?= htmlspecialchars($t['designation']) ?> (<?= htmlspecialchars($t['nom_depart']) ?> → <?= htmlspecialchars($t['nom_arrivee']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Chauffeur</label>
                    <input type="text" name="chauffeur" class="form-control" placeholder="Nom du chauffeur">
                </div>
                <div class="form-group">
                    <label class="form-label">Telephone chauffeur</label>
                    <input type="tel" name="telephone_chauffeur" class="form-control" placeholder="+237...">
                </div>
                <div class="form-group">
                    <label class="form-label">Date depart <span class="req">*</span></label>
                    <input type="datetime-local" name="date_depart" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Arrivee prevue</label>
                    <input type="datetime-local" name="date_arrivee_prevue" class="form-control">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" style="min-height:50px" placeholder="Notes..."></textarea>
                </div>
            </div>
            <!-- Escales display for manifest new voyage -->
            <div id="manifest-escales-display" style="display:none;margin-top:12px;padding:10px 14px;background:var(--bg-card2);border-radius:8px;border-left:3px solid var(--accent)">
                <div style="font-size:.85rem;font-weight:600;margin-bottom:6px"><i class="fas fa-map-pin" style="color:var(--accent);margin-right:6px"></i>Itineraire du trajet</div>
                <div id="manifest-escales-list" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap"></div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION COLIS -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div class="card-title"><i class="fas fa-box" style="margin-right:6px;color:var(--primary)"></i>Colis disponibles (<?= count($colisDispo) ?>)</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if ($isAgenceAdmin): ?>
            <select name="agence_filter" id="agence_filter_select" class="form-control" style="width:auto;padding:6px 10px;font-size:.85rem" onchange="filterAgence(this.value)">
                <option value="">— Filtrer par agence —</option>
                <?php foreach ($agences as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $filterAgence==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="agence_filter" id="agence_filter_select" value="<?= $filterAgence ?>">
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAll(true)"><i class="fas fa-check-double"></i> Tout cocher</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAll(false)"><i class="fas fa-times"></i> Decocher</button>
        </div>
    </div>

    <?php if (!empty($colisDispo)): ?>
    <div style="padding:8px 16px;font-size:.85rem;font-weight:600;color:var(--primary)"><i class="fas fa-box" style="margin-right:4px"></i>Colis en depart de cette agence</div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)"></th>
                <th>N° Colis</th>
                <th>Type</th>
                <th>Expediteur</th>
                <th>Destinataire</th>
                <th>Trajet</th>
                <th>Poids</th>
                <th>Montant</th>
                <th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($colisDispo as $c): ?>
            <tr data-agence="<?= $c['agence_depart_id'] ?>" data-type="direct">
                <td><input type="checkbox" name="colis_ids[]" value="<?= $c['id'] ?>" class="colis-check" onchange="updateResume()"></td>
                <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                <td><?= statutBadge($c['type_expedition']) ?></td>
                <td>
                    <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                </td>
                <td>
                    <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                </td>
                <td style="font-size:.8rem">
                    <div><?= htmlspecialchars($c['nom_depart']) ?></div>
                    <div style="color:var(--primary)">↓</div>
                    <div><?= htmlspecialchars($c['nom_arrivee']) ?></div>
                </td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($c['date_expedition'],'d/m/Y') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($colisTransit)): ?>
    <div style="padding:12px 16px 4px;font-size:.85rem;font-weight:600;color:var(--accent);border-top:1px solid var(--border)"><i class="fas fa-arrows-turn-right" style="margin-right:4px"></i>Colis en transit (arrives a cette agence)</div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th style="width:40px"></th>
                <th>N° Colis</th>
                <th>Type</th>
                <th>Expediteur</th>
                <th>Destinataire</th>
                <th>Trajet</th>
                <th>Poids</th>
                <th>Montant</th>
                <th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($colisTransit as $c): ?>
            <tr data-agence="<?= $c['agence_arrivee_id'] ?>" data-type="transit">
                <td><input type="checkbox" name="colis_ids[]" value="<?= $c['id'] ?>" class="colis-check" onchange="updateResume()"></td>
                <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                <td><span class="badge" style="background:rgba(232,160,10,0.15);color:var(--accent);font-size:.72rem">Transit</span></td>
                <td>
                    <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                </td>
                <td>
                    <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                </td>
                <td style="font-size:.8rem">
                    <div><?= htmlspecialchars($c['nom_depart']) ?></div>
                    <div style="color:var(--accent)">↓</div>
                    <div><?= htmlspecialchars($c['nom_arrivee']) ?></div>
                </td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($c['date_expedition'],'d/m/Y') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($colisDispo) && empty($colisTransit)): ?>
    <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.85rem">Aucun colis disponible pour cette agence</div>
    <?php endif; ?>

    <!-- Resume + bouton -->
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-top:2px solid var(--primary);background:rgba(232,80,10,0.04)">
        <div id="manifestResume" style="font-size:.85rem;color:var(--text-muted)">
            <span id="resumeCount">0</span> colis selectionne(s) —
            <span id="resumePoids" style="font-family:var(--font-mono)">0</span> kg —
            <span id="resumeMontant" style="font-family:var(--font-mono)">0</span> F
        </div>
        <button type="submit" class="btn btn-primary btn-lg" id="btnCharger">
            <i class="fas fa-truck-loading"></i> Charger dans le voyage
        </button>
    </div>
</div>

</form>

<script>
function toggleVoyageType() {
    const type = document.querySelector('input[name="voyage_type"]:checked').value;
    document.getElementById('voyage-existing').style.display = type === 'existing' ? 'block' : 'none';
    document.getElementById('voyage-new').style.display = type === 'new' ? 'block' : 'none';
    document.getElementById('manifest_action').value = type === 'existing' ? 'load_existing' : 'create_and_load';

    // Activer/desactiver required selon la section visible
    document.querySelectorAll('#voyage-existing [required]').forEach(el => el.required = (type === 'existing'));
    document.querySelectorAll('#voyage-new [required]').forEach(el => el.required = (type === 'new'));
}

function toggleAll(checked) {
    document.querySelectorAll('.colis-check').forEach(cb => {
        if (!cb.closest('tr').style.display || cb.closest('tr').style.display !== 'none') {
            cb.checked = checked;
        }
    });
    updateResume();
}

function updateResume() {
    let count = 0, poids = 0, montant = 0;
    document.querySelectorAll('.colis-check:checked').forEach(cb => {
        count++;
        const row = cb.closest('tr');
        poids += parseFloat(row.cells[6].textContent.replace(/[^\d,]/g, '').replace(',','.')) || 0;
        montant += parseInt(row.cells[7].textContent.replace(/[^\d]/g, '')) || 0;
    });
    document.getElementById('resumeCount').textContent = count;
    document.getElementById('resumePoids').textContent = poids.toFixed(2);
    document.getElementById('resumeMontant').textContent = montant.toLocaleString('fr-FR');
}

function filterAgence(agenceId) {
    const url = new URL(window.location);
    if (agenceId) url.searchParams.set('agence', agenceId);
    else url.searchParams.delete('agence');
    window.location.href = url.toString();
}

// Auto-reload when admin/superviseur changes agency filter
const agenceSelect = document.getElementById('agence_filter_select');
if (agenceSelect && agenceSelect.tagName === 'SELECT') {
    agenceSelect.addEventListener('change', function() {
        filterAgence(this.value);
    });
}

// Escales display for manifest new voyage
const manifestTrajetSelect = document.getElementById('manifest_trajet_select');
if (manifestTrajetSelect) {
    manifestTrajetSelect.addEventListener('change', function() {
        const display = document.getElementById('manifest-escales-display');
        const list = document.getElementById('manifest-escales-list');
        if (!this.value) { display.style.display = 'none'; return; }
        const opt = this.options[this.selectedIndex];
        const depart = opt.dataset.depart;
        const arrivee = opt.dataset.arrivee;
        const escales = JSON.parse(opt.dataset.escales || '[]');

        let html = `<span style="font-weight:700">${depart}</span>`;
        escales.forEach(e => {
            html += ` <i class="fas fa-arrow-right" style="font-size:.65rem;color:var(--text-muted)"></i> `;
            html += `<span style="color:var(--accent);font-weight:600">${e.nom}</span>`;
            if (e.duree_arret > 0) html += ` <span style="font-size:.7rem;color:var(--text-muted)">(${e.duree_arret}min)</span>`;
        });
        html += ` <i class="fas fa-arrow-right" style="font-size:.65rem;color:var(--text-muted)"></i> `;
        html += `<span style="font-weight:700">${arrivee}</span>`;

        list.innerHTML = html;
        display.style.display = 'block';
    });
}

document.addEventListener('change', function(e) {
    if (e.target.id === 'selectAll') {
        toggleAll(e.target.checked);
    }
});

// Initialiser required au chargement
toggleVoyageType();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>