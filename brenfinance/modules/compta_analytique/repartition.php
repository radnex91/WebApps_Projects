<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('compta_analytique');
$pageTitle = 'Répartitions par clé — Compta analytique';

$db = getDB();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'repartir') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $montantTotal = (float)($_POST['montant_total'] ?? 0);
        $libelle = sanitize($_POST['libelle'] ?? '');
        $axeIds = $_POST['axe_ids'] ?? [];
        $pourcentages = $_POST['pourcentages'] ?? [];
        $montants = $_POST['montants'] ?? [];

        if ($sectionId && $montantTotal > 0) {
            foreach ($axeIds as $i => $axeId) {
                $axeId = (int)$axeId;
                $pct = (float)($pourcentages[$i] ?? 0);
                $montant = $montantTotal * $pct / 100;
                if ($axeId && $montant > 0) {
                    $db->prepare("INSERT INTO affectations_analytiques (source_type, source_id, axe_id, montant, pourcentage)
                                  VALUES ('repartition_key', ?, ?, ?, ?)")
                       ->execute([$sectionId, $axeId, round($montant, 2), $pct]);
                }
            }
            // Log the repartition
            $db->prepare("INSERT INTO repartitions_cles (section_id, libelle, montant_total, cree_par)
                          VALUES (?,?,?,?)")
               ->execute([$sectionId, $libelle, $montantTotal, $_SESSION['user_id']]);
            auditLog('repartir', 'compta_analytique', 'affectations_analytiques', null, null, ['montant_total' => $montantTotal]);
            flash('success', 'Répartition enregistrée et affectations créées.');
        }
        header('Location: repartition.php'); exit;
    }
}

$sections = getSectionsAnalytiques();
$axes = getAxesAnalytiques();

// History
$history = $db->query("SELECT r.*, s.libelle as section_libelle, CONCAT(u.nom,' ',u.prenom) as cree_par_nom
    FROM repartitions_cles r
    JOIN sections_analytiques s ON r.section_id = s.id
    LEFT JOIN utilisateurs u ON r.cree_par = u.id
    ORDER BY r.created_at DESC LIMIT 20")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="index.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<div class="d-flex justify-between align-center mb-20">
    <div>
        <h2 style="margin:0"><i class="fa-solid fa-share-nodes"></i> Répartition par clé</h2>
        <p style="color:var(--text3);margin:4px 0 0">Répartir des montants entre plusieurs axes analytiques</p>
    </div>
</div>

<div class="dashboard-split mb-20" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <!-- Répartition form -->
    <div class="card">
        <div class="card-header"><span class="card-title">Nouvelle répartition</span></div>
        <div class="card-body">
            <form method="POST" id="repart-form">
                <input type="hidden" name="action" value="repartir">

                <div class="form-group">
                    <label>Section / Clé</label>
                    <select name="section_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['code'] . ' — ' . $sec['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Libellé de l'opération</label>
                    <input type="text" name="libelle" class="form-control" required placeholder="Ex: Charges locatives Q1">
                </div>
                <div class="form-group">
                    <label>Montant total à répartir (FCFA)</label>
                    <input type="number" name="montant_total" id="montant-total" class="form-control" step="0.01" required onchange="updateAllMontants()">
                </div>

                <div style="margin-bottom:12px">
                    <strong style="font-size:13px;color:var(--text2)">Affectations</strong>
                </div>

                <div id="repart-rows">
                    <div class="repart-row" style="display:flex;gap:6px;margin-bottom:8px;align-items:center">
                        <select name="axe_ids[]" class="form-control form-control-sm" style="flex:2" required>
                            <option value="">— Axe —</option>
                            <?php foreach ($axes as $ax): ?>
                            <option value="<?= $ax['id'] ?>"><?= htmlspecialchars($ax['code'] . ' — ' . $ax['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="pourcentages[]" class="form-control form-control-sm pct" style="width:80px" value="0" step="0.01" min="0" max="100" onchange="updateMontant(this);checkTotal()">
                        <span style="font-size:12px;width:16px">%</span>
                        <input type="text" class="form-control form-control-sm montant-calc" style="width:120px" readonly placeholder="0 FCFA">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRepartRow(this)" title="Retirer"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addRepartRow()"><i class="fa-solid fa-plus"></i> Ajouter un axe</button>
                </div>

                <div style="background:var(--surface2);padding:8px 12px;border-radius:var(--radius);font-size:13px;margin-bottom:12px">
                    <span id="total-pct">0</span>% alloué —
                    <span id="total-montant" style="font-weight:600">0 FCFA</span> /
                    <span id="montant-ref">0 FCFA</span>
                    <span id="pct-warning" style="color:var(--danger);display:none;margin-left:8px">
                        <i class="fa-solid fa-triangle-exclamation"></i> Le total doit être 100%
                    </span>
                </div>

                <button type="submit" class="btn btn-primary" id="btn-repartir" disabled>
                    <i class="fa-solid fa-share-alt"></i> Répartir
                </button>
            </form>
        </div>
    </div>

    <!-- Historique -->
    <div class="card">
        <div class="card-header"><span class="card-title">Historique des répartitions</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Section</th><th>Libellé</th><th>Montant</th><th>Par</th></tr></thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:30px">Aucune répartition enregistrée.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
                        <td><?= htmlspecialchars($h['section_libelle']) ?></td>
                        <td><?= htmlspecialchars($h['libelle']) ?></td>
                        <td class="amount"><?= formatMontant($h['montant_total']) ?></td>
                        <td><?= htmlspecialchars($h['cree_par_nom']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function addRepartRow() {
    var container = document.getElementById('repart-rows');
    var div = document.createElement('div');
    div.className = 'repart-row';
    div.style.cssText = 'display:flex;gap:6px;margin-bottom:8px;align-items:center';
    div.innerHTML = '<select name="axe_ids[]" class="form-control form-control-sm" style="flex:2" required>' +
        '<option value="">— Axe —</option>' +
        <?php foreach ($axes as $ax): ?>
        '<option value="<?= $ax['id'] ?>"><?= htmlspecialchars($ax['code'] . ' — ' . $ax['libelle']) ?></option>' +
        <?php endforeach; ?>
        '</select>' +
        '<input type="number" name="pourcentages[]" class="form-control form-control-sm pct" style="width:80px" value="0" step="0.01" min="0" max="100" onchange="updateMontant(this);checkTotal()">' +
        '<span style="font-size:12px;width:16px">%</span>' +
        '<input type="text" class="form-control form-control-sm montant-calc" style="width:120px" readonly placeholder="0 FCFA">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="removeRepartRow(this)" title="Retirer"><i class="fa-solid fa-xmark"></i></button>';
    container.appendChild(div);
}

function removeRepartRow(btn) {
    var container = document.getElementById('repart-rows');
    if (container.querySelectorAll('.repart-row').length <= 1) return;
    btn.closest('.repart-row').remove();
    checkTotal();
}

function updateMontant(el) {
    var row = el.closest('.repart-row');
    var pct = parseFloat(el.value) || 0;
    var total = parseFloat(document.getElementById('montant-total').value) || 0;
    var montant = (total * pct / 100);
    row.querySelector('.montant-calc').value = new Intl.NumberFormat('fr-CM').format(montant) + ' FCFA';
}

function updateAllMontants() {
    document.querySelectorAll('.pct').forEach(function(inp) { updateMontant(inp); });
    checkTotal();
}

function checkTotal() {
    var pcts = document.querySelectorAll('.pct');
    var total = 0;
    pcts.forEach(function(inp) { total += parseFloat(inp.value) || 0; });
    document.getElementById('total-pct').textContent = Math.round(total * 100) / 100;
    var montantTotal = parseFloat(document.getElementById('montant-total').value) || 0;
    document.getElementById('total-montant').textContent = new Intl.NumberFormat('fr-CM').format(montantTotal * total / 100) + ' FCFA';
    document.getElementById('montant-ref').textContent = new Intl.NumberFormat('fr-CM').format(montantTotal) + ' FCFA';

    var valid = Math.abs(total - 100) < 0.01;
    document.getElementById('pct-warning').style.display = valid ? 'none' : 'inline';
    document.getElementById('btn-repartir').disabled = !valid || montantTotal <= 0;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
