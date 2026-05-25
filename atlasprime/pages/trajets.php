<?php
$pageTitle = 'Gestion des trajets';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('trajets_gerer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $errors = [];
        if (empty($_POST['designation'])) $errors[] = 'Designation requise';
        if (empty($_POST['agence_depart_id'])) $errors[] = 'Agence de depart requise';
        if (empty($_POST['agence_arrivee_id'])) $errors[] = 'Agence d\'arrivee requise';
        if (!empty($_POST['agence_depart_id']) && !empty($_POST['agence_arrivee_id'])
            && $_POST['agence_depart_id'] === $_POST['agence_arrivee_id']) {
            $errors[] = 'Depart et arrivee doivent etre differents';
        }

        // Validate escales
        $escales = $_POST['escales'] ?? [];
        $agDep = intval($_POST['agence_depart_id'] ?? 0);
        $agArr = intval($_POST['agence_arrivee_id'] ?? 0);
        $validEscaleAgences = [];
        $escaleOrder = 1;
        foreach ($escales as $key => $esc) {
            $escAgId = intval($esc['agence_id'] ?? 0);
            if ($escAgId === 0) continue;
            if ($escAgId === $agDep || $escAgId === $agArr) {
                $errors[] = 'Une escale ne peut pas etre l\'agence de depart ou d\'arrivee';
                break;
            }
            if (in_array($escAgId, $validEscaleAgences)) {
                $errors[] = 'Doublon d\'escale detecte';
                break;
            }
            $validEscaleAgences[] = $escAgId;
        }

        if (!$errors) {
            $num = genererNumeroTrajet();
            $trajetId = Database::insert(
                "INSERT INTO trajets (numero_trajet,designation,agence_depart_id,agence_arrivee_id,distance_km,duree_estimee,statut)
                 VALUES (?,?,?,?,?,?,?)",
                [$num, trim($_POST['designation']), $agDep, $agArr,
                 !empty($_POST['distance_km']) ? floatval($_POST['distance_km']) : null,
                 !empty($_POST['duree_estimee']) ? intval($_POST['duree_estimee']) : null,
                 'actif']
            );
            // Insert escales
            $escaleOrder = 1;
            foreach ($escales as $esc) {
                $escAgId = intval($esc['agence_id'] ?? 0);
                if ($escAgId === 0) continue;
                Database::execute(
                    "INSERT INTO trajet_escales (trajet_id,agence_id,ordre,duree_arret) VALUES (?,?,?,?)",
                    [$trajetId, $escAgId, $escaleOrder, intval($esc['duree_arret'] ?? 0)]
                );
                $escaleOrder++;
            }
            Auth::logAction('TRAJET_CREE', 'trajets', $trajetId, "N°: $num");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Trajet $num cree !"];
            header('Location: trajets.php');
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
        }
    } elseif ($action === 'update') {
        $trajetId = intval($_POST['id']);
        $errors = [];
        if (empty($_POST['designation'])) $errors[] = 'Designation requise';
        $agDep = intval($_POST['agence_depart_id'] ?? 0);
        $agArr = intval($_POST['agence_arrivee_id'] ?? 0);
        if ($agDep && $agArr && $agDep === $agArr) {
            $errors[] = 'Depart et arrivee doivent etre differents';
        }

        $escales = $_POST['escales'] ?? [];
        $validEscaleAgences = [];
        foreach ($escales as $esc) {
            $escAgId = intval($esc['agence_id'] ?? 0);
            if ($escAgId === 0) continue;
            if ($escAgId === $agDep || $escAgId === $agArr) {
                $errors[] = 'Une escale ne peut pas etre l\'agence de depart ou d\'arrivee';
                break;
            }
            if (in_array($escAgId, $validEscaleAgences)) {
                $errors[] = 'Doublon d\'escale detecte';
                break;
            }
            $validEscaleAgences[] = $escAgId;
        }

        if (!$errors) {
            Database::execute(
                "UPDATE trajets SET designation=?, agence_depart_id=?, agence_arrivee_id=?,
                 distance_km=?, duree_estimee=?, actif=? WHERE id=?",
                [trim($_POST['designation']), $agDep, $agArr,
                 !empty($_POST['distance_km']) ? floatval($_POST['distance_km']) : null,
                 !empty($_POST['duree_estimee']) ? intval($_POST['duree_estimee']) : null,
                 isset($_POST['actif']) ? 1 : 0, $trajetId]
            );
            // Delete + re-insert escales
            Database::execute("DELETE FROM trajet_escales WHERE trajet_id=?", [$trajetId]);
            $escaleOrder = 1;
            foreach ($escales as $esc) {
                $escAgId = intval($esc['agence_id'] ?? 0);
                if ($escAgId === 0) continue;
                Database::execute(
                    "INSERT INTO trajet_escales (trajet_id,agence_id,ordre,duree_arret) VALUES (?,?,?,?)",
                    [$trajetId, $escAgId, $escaleOrder, intval($esc['duree_arret'] ?? 0)]
                );
                $escaleOrder++;
            }
            Auth::logAction('TRAJET_MODIFIE', 'trajets', $trajetId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Trajet mis a jour.'];
            header('Location: trajets.php');
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
        }
    } elseif ($action === 'toggle') {
        Database::execute("UPDATE trajets SET actif = NOT actif WHERE id=?", [intval($_POST['id'])]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Statut trajet modifie.'];
        header('Location: trajets.php');
        exit;
    }
}

$agences = getAgences();

$trajets = Database::fetchAll(
    "SELECT t.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
     (SELECT COUNT(*) FROM trajet_escales WHERE trajet_id = t.id) AS nb_escales,
     (SELECT COUNT(*) FROM voyages WHERE trajet_id = t.id) AS nb_voyages
     FROM trajets t
     JOIN agences ad ON t.agence_depart_id = ad.id
     JOIN agences aa ON t.agence_arrivee_id = aa.id
     ORDER BY t.actif DESC, t.designation"
);

// Enrich with escales for JS edit
foreach ($trajets as &$t) {
    $t['escales'] = Database::fetchAll(
        "SELECT te.*, a.nom FROM trajet_escales te JOIN agences a ON te.agence_id = a.id
         WHERE te.trajet_id = ? ORDER BY te.ordre", [$t['id']]
    );
}
unset($t);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700">Trajets</h2>
        <p class="text-muted" style="font-size:.85rem"><?= count($trajets) ?> trajets configures</p>
    </div>
    <button class="btn btn-primary" data-modal="modal-add-trajet">
        <i class="fas fa-plus"></i> Nouveau trajet
    </button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>N° Trajet</th><th>Designation</th><th>Itineraire</th><th>Distance</th><th>Duree</th><th>Voyages</th><th>Statut</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($trajets as $t): ?>
            <tr>
                <td><span class="colis-num"><?= $t['numero_trajet'] ?></span></td>
                <td style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($t['designation']) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span style="font-weight:700"><?= htmlspecialchars($t['nom_depart']) ?></span>
                        <?php foreach ($t['escales'] as $esc): ?>
                        <i class="fas fa-arrow-right" style="font-size:.6rem;color:var(--text-muted)"></i>
                        <span style="color:var(--accent);font-weight:600"><?= htmlspecialchars($esc['nom']) ?></span>
                        <?php if ($esc['duree_arret'] > 0): ?>
                        <span style="font-size:.65rem;color:var(--text-muted)">(<?= $esc['duree_arret'] ?>min)</span>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <i class="fas fa-arrow-right" style="font-size:.6rem;color:var(--text-muted)"></i>
                        <span style="font-weight:700"><?= htmlspecialchars($t['nom_arrivee']) ?></span>
                    </div>
                    <?php if ($t['nb_escales'] > 0): ?>
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:2px"><?= $t['nb_escales'] ?> escale(s)</div>
                    <?php endif; ?>
                </td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= $t['distance_km'] ? number_format($t['distance_km'], 0, ',', ' ') . ' km' : '—' ?></td>
                <td style="font-size:.82rem"><?= $t['duree_estimee'] ? intdiv($t['duree_estimee'], 60) . 'h' . str_pad($t['duree_estimee'] % 60, 2, '0', STR_PAD_LEFT) : '—' ?></td>
                <td>
                    <span style="background:rgba(14,165,233,0.15);color:#38BDF8;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700">
                        <?= $t['nb_voyages'] ?>
                    </span>
                </td>
                <td><?= statutBadge($t['actif'] ? 'actif' : 'inactif') ?></td>
                <td>
                    <div class="action-btns">
                        <button class="action-btn edit" title="Modifier"
                                onclick='openEditTrajet(<?= htmlspecialchars(json_encode($t)) ?>)'>
                            <i class="fas fa-pencil"></i>
                        </button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="action-btn <?= $t['actif'] ? 'del' : 'view' ?>"
                                    title="<?= $t['actif'] ? 'Desactiver' : 'Activer' ?>"
                                    data-confirm="Confirmer ?">
                                <i class="fas fa-<?= $t['actif'] ? 'ban' : 'check' ?>"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($trajets)): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-route"></i><h3>Aucun trajet</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ADD TRAJET -->
<div class="modal-backdrop" id="modal-add-trajet">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <div class="modal-title">Nouveau trajet</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post" id="form-add-trajet">
            <input type="hidden" name="action" value="create">
            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Designation <span class="req">*</span></label>
                <input type="text" name="designation" class="form-control" required placeholder="EX: Yaounde - Douala via Bafoussam">
            </div>
            <div class="form-grid-2" style="margin-bottom:12px">
                <div class="form-group">
                    <label class="form-label">Agence de depart <span class="req">*</span></label>
                    <select name="agence_depart_id" class="form-control" required id="add_agence_depart">
                        <option value="">— Selectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Agence d'arrivee <span class="req">*</span></label>
                    <select name="agence_arrivee_id" class="form-control" required id="add_agence_arrivee">
                        <option value="">— Selectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Distance (km)</label>
                    <input type="number" name="distance_km" class="form-control" placeholder="260" min="0" step="0.1">
                </div>
                <div class="form-group">
                    <label class="form-label">Duree estimee (min)</label>
                    <input type="number" name="duree_estimee" class="form-control" placeholder="300" min="0">
                </div>
            </div>

            <!-- ESCALES -->
            <div style="border-top:1px solid var(--border);padding-top:12px;margin-bottom:8px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <div style="font-weight:700;font-size:.9rem"><i class="fas fa-map-pin" style="color:var(--accent);margin-right:6px"></i>Escales</div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addEscale('add')"><i class="fas fa-plus"></i> Ajouter une escale</button>
                </div>
                <div id="escales-container-add" style="display:flex;flex-direction:column;gap:8px">
                    <div style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:8px">Aucune escale — cliquez "Ajouter" pour definir des etapes intermediaires</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Creer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT TRAJET -->
<div class="modal-backdrop" id="modal-edit-trajet">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <div class="modal-title">Modifier le trajet</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post" id="form-edit-trajet">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="et_id">
            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Designation <span class="req">*</span></label>
                <input type="text" name="designation" id="et_designation" class="form-control" required>
            </div>
            <div class="form-grid-2" style="margin-bottom:12px">
                <div class="form-group">
                    <label class="form-label">Agence de depart <span class="req">*</span></label>
                    <select name="agence_depart_id" class="form-control" required id="et_agence_depart">
                        <option value="">— Selectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Agence d'arrivee <span class="req">*</span></label>
                    <select name="agence_arrivee_id" class="form-control" required id="et_agence_arrivee">
                        <option value="">— Selectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Distance (km)</label>
                    <input type="number" name="distance_km" id="et_distance_km" class="form-control" min="0" step="0.1">
                </div>
                <div class="form-group">
                    <label class="form-label">Duree estimee (min)</label>
                    <input type="number" name="duree_estimee" id="et_duree_estimee" class="form-control" min="0">
                </div>
            </div>

            <!-- ESCALES EDIT -->
            <div style="border-top:1px solid var(--border);padding-top:12px;margin-bottom:8px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <div style="font-weight:700;font-size:.9rem"><i class="fas fa-map-pin" style="color:var(--accent);margin-right:6px"></i>Escales</div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addEscale('edit')"><i class="fas fa-plus"></i> Ajouter une escale</button>
                </div>
                <div id="escales-container-edit" style="display:flex;flex-direction:column;gap:8px"></div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="actif" id="et_actif" value="1"> Trajet actif
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
const agencesData = <?= json_encode(array_values($agences)) ?>;
let escaleCounters = { add: 0, edit: 0 };

function buildAgenceOptions(selectedId) {
    let opts = '<option value="">-- Agence escale --</option>';
    agencesData.forEach(a => {
        const sel = (a.id == selectedId) ? ' selected' : '';
        opts += `<option value="${a.id}"${sel}>${a.nom}</option>`;
    });
    return opts;
}

function addEscale(mode, agenceId = null, dureeArret = 0) {
    escaleCounters[mode]++;
    const container = document.getElementById(`escales-container-${mode}`);
    // Remove placeholder text if present
    const placeholder = container.querySelector('div[style*="text-align:center"]');
    if (placeholder) placeholder.remove();

    const row = document.createElement('div');
    row.style.cssText = 'display:flex;gap:10px;align-items:center;padding:8px 10px;background:var(--bg-card2);border-radius:8px';
    row.dataset.escaleKey = escaleCounters[mode];

    row.innerHTML = `
        <i class="fas fa-map-pin" style="color:var(--accent);flex-shrink:0"></i>
        <select name="escales[${escaleCounters[mode]}][agence_id]" class="form-control" style="flex:2" required>
            ${buildAgenceOptions(agenceId)}
        </select>
        <input type="number" name="escales[${escaleCounters[mode]}][duree_arret]" class="form-control"
               style="flex:0 0 90px" placeholder="Arrêt min" value="${dureeArret}" min="0">
        <input type="hidden" name="escales[${escaleCounters[mode]}][ordre]" value="${escaleCounters[mode]}">
        <button type="button" onclick="removeEscale(this)" class="btn btn-ghost btn-sm" style="flex-shrink:0;padding:4px 8px">
            <i class="fas fa-times" style="color:var(--danger)"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeEscale(btn) {
    btn.closest('div[data-escale-key]').remove();
}

function openEditTrajet(data) {
    document.getElementById('et_id').value = data.id;
    document.getElementById('et_designation').value = data.designation;
    document.getElementById('et_agence_depart').value = data.agence_depart_id;
    document.getElementById('et_agence_arrivee').value = data.agence_arrivee_id;
    document.getElementById('et_distance_km').value = data.distance_km || '';
    document.getElementById('et_duree_estimee').value = data.duree_estimee || '';
    document.getElementById('et_actif').checked = data.actif == 1;

    // Reset escales
    const container = document.getElementById('escales-container-edit');
    container.innerHTML = '';
    escaleCounters.edit = 0;
    if (data.escales && data.escales.length) {
        data.escales.forEach(e => addEscale('edit', e.agence_id, e.duree_arret));
    }

    document.getElementById('modal-edit-trajet').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>