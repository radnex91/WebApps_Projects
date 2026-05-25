<?php
$pageTitle = 'Bordereaux de transport';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('bordereaux_imprimer');

$dateDebut  = $_GET['date_debut'] ?? date('Y-m-d');
$dateFin    = $_GET['date_fin']   ?? date('Y-m-d');
$agenceId   = intval($_GET['agence'] ?? 0);
$voyageId   = intval($_GET['voyage'] ?? 0);
$agences    = getAgences();
$voyages    = getVoyagesOuverts();

// Build query
$where = ['1=1'];
$params = [];
if ($voyageId) { $where[] = "c.voyage_id=?"; $params[] = $voyageId; }
else {
    if ($dateDebut) { $where[] = "DATE(c.date_expedition)>=?"; $params[] = $dateDebut; }
    if ($dateFin)   { $where[] = "DATE(c.date_expedition)<=?"; $params[] = $dateFin; }
    if ($agenceId)  { $where[] = "c.agence_depart_id=?"; $params[] = $agenceId; }
}
$whereStr = implode(' AND ', $where);

$colis = Database::fetchAll(
    "SELECT c.id, c.numero_colis, c.expediteur_nom, c.destinataire_nom, c.date_expedition,
            c.montant_total, c.statut, c.type_expedition,
            ad.nom vd, aa.nom va
     FROM colis c
     JOIN agences ad ON c.agence_depart_id=ad.id
     JOIN agences aa ON c.agence_arrivee_id=aa.id
     WHERE $whereStr ORDER BY c.created_at DESC", $params
);

$selectedIds = isset($_POST['selected']) ? array_map('intval', $_POST['selected']) : [];
?>

<div style="display:grid;grid-template-columns:320px 1fr;gap:24px">
    <!-- FILTRES -->
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-filter"></i> Filtres</div></div>
            <form method="get">
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Voyage</label>
                    <select name="voyage" class="form-control">
                        <option value="">— Par date/agence —</option>
                        <?php foreach ($voyages as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $voyageId==$v['id']?'selected':'' ?>>
                            <?= $v['numero_voyage'] ?> (<?= $v['nom_depart'] ?>→<?= $v['nom_arrivee'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Du</label>
                    <input type="date" name="date_debut" class="form-control" value="<?= $dateDebut ?>">
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Au</label>
                    <input type="date" name="date_fin" class="form-control" value="<?= $dateFin ?>">
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Agence départ</label>
                    <select name="agence" class="form-control">
                        <option value="">Toutes agences</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $agenceId==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Rechercher</button>
            </form>
        </div>

        <?php if ($voyageId): ?>
        <div class="card mt-2">
            <div class="card-header"><div class="card-title">Actions voyage</div></div>
            <a href="bordereau_print.php?voyage=<?= $voyageId ?>" target="_blank" class="btn btn-success w-100">
                <i class="fas fa-file-invoice"></i> Manifeste du voyage
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- LISTE -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Colis sélectionnables</div>
                <div class="card-subtitle"><?= count($colis) ?> résultats</div>
            </div>
            <div style="display:flex;gap:8px" id="bulk-actions" style="display:none">
                <button class="btn btn-secondary btn-sm" onclick="selectAll(true)">Tout sélectionner</button>
                <button class="btn btn-ghost btn-sm" onclick="selectAll(false)">Désélectionner</button>
                <button class="btn btn-primary btn-sm" onclick="printSelected()">
                    <i class="fas fa-print"></i> Imprimer sélection
                </button>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="data-table" id="colisTable">
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" id="checkAll" onchange="selectAll(this.checked)"></th>
                    <th>N° Colis</th><th>Expéditeur</th><th>Destinataire</th>
                    <th>Trajet</th><th>Type</th><th>Montant</th><th>Date</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($colis as $c): ?>
                <tr>
                    <td><input type="checkbox" class="colis-check" value="<?= $c['id'] ?>"></td>
                    <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                    <td style="font-size:.85rem"><?= htmlspecialchars($c['expediteur_nom']) ?></td>
                    <td style="font-size:.85rem"><?= htmlspecialchars($c['destinataire_nom']) ?></td>
                    <td style="font-size:.78rem"><?= $c['vd'] ?> → <?= $c['va'] ?></td>
                    <td><?= statutBadge($c['type_expedition']) ?></td>
                    <td style="font-family:var(--font-mono);font-size:.8rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                    <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($c['date_expedition'],'d/m/Y') ?></td>
                    <td>
                        <a href="bordereau_print.php?id=<?= $c['id'] ?>" target="_blank" class="action-btn print" title="Imprimer">
                            <i class="fas fa-print"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$colis): ?>
                <tr><td colspan="9">
                    <div class="empty-state"><i class="fas fa-file-invoice"></i><h3>Aucun colis</h3><p>Modifiez les filtres</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($colis): ?>
        <div style="padding:16px;border-top:1px solid var(--border);display:flex;align-items:center;gap:12px">
            <span id="selected-count" style="font-size:.82rem;color:var(--text-muted)">0 sélectionné(s)</span>
            <button class="btn btn-primary btn-sm" onclick="printSelected()">
                <i class="fas fa-print"></i> Imprimer la sélection
            </button>
            <button class="btn btn-secondary btn-sm" onclick="selectAll(true)">Tout sélectionner</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function selectAll(checked) {
    document.querySelectorAll('.colis-check').forEach(c => c.checked = checked);
    document.getElementById('checkAll').checked = checked;
    updateCount();
}
function updateCount() {
    const n = document.querySelectorAll('.colis-check:checked').length;
    document.getElementById('selected-count').textContent = n + ' sélectionné(s)';
}
document.querySelectorAll('.colis-check').forEach(c => c.addEventListener('change', updateCount));

function printSelected() {
    const ids = [...document.querySelectorAll('.colis-check:checked')].map(c => c.value);
    if (!ids.length) { alert('Sélectionnez au moins un colis'); return; }
    if (ids.length === 1) {
        window.open('bordereau_print.php?id=' + ids[0], '_blank');
    } else {
        window.open('bordereau_print.php?multi=' + ids.join(','), '_blank');
    }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
