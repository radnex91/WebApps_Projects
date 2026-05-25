<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('compta_analytique');
$pageTitle = 'Comptabilité analytique';

$db = getDB();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_axe') {
        $type = $_POST['type'] ?? 'centre_cout';
        $code = sanitize($_POST['code'] ?? '');
        $libelle = sanitize($_POST['libelle'] ?? '');
        $parentId = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;

        try {
            $db->prepare("INSERT INTO axes_analytiques (type, code, libelle, parent_id) VALUES (?,?,?,?)")
               ->execute([$type, $code, $libelle, $parentId]);
            flash('success', 'Axe analytique créé.');
        } catch (Exception $e) { flash('danger', $e->getMessage()); }
        header('Location: index.php'); exit;
    }

    if ($action === 'toggle_axe') {
        $id = (int)($_POST['id'] ?? 0);
        $axe = $db->query("SELECT actif FROM axes_analytiques WHERE id=$id")->fetch();
        if ($axe) {
            $new = $axe['actif'] ? 0 : 1;
            $db->prepare("UPDATE axes_analytiques SET actif=? WHERE id=?")->execute([$new, $id]);
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'affecter') {
        $sourceType = $_POST['source_type'] ?? '';
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $axeId = (int)($_POST['axe_id'] ?? 0);
        $montant = (float)($_POST['montant'] ?? 0);
        $pourcentage = $_POST['pourcentage'] ? (float)$_POST['pourcentage'] : null;
        $ecritureId = $_POST['ecriture_id'] ? (int)$_POST['ecriture_id'] : null;

        if ($sourceType && $sourceId && $axeId && $montant > 0) {
            affecterAnalytique($sourceType, $sourceId, $axeId, $montant, $pourcentage, $ecritureId);
            flash('success', 'Affectation analytique enregistrée.');
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'create_section') {
        $code = sanitize($_POST['code'] ?? '');
        $libelle = sanitize($_POST['libelle'] ?? '');
        $type = $_POST['type'] ?? 'pourcentage';
        $unite = $_POST['unite_oeuvre'] ?? '';

        try {
            $db->prepare("INSERT INTO sections_analytiques (code, libelle, type, unite_oeuvre) VALUES (?,?,?,?)")
               ->execute([$code, $libelle, $type, $unite ?: null]);
            flash('success', 'Section analytique créée.');
        } catch (Exception $e) { flash('danger', $e->getMessage()); }
        header('Location: index.php'); exit;
    }
}

$tab = $_GET['tab'] ?? 'axes';

$axes = getAxesAnalytiques();
$sections = getSectionsAnalytiques();
$affectations = $db->query("SELECT aa.*, ax.libelle as axe_libelle, ax.type as axe_type
    FROM affectations_analytiques aa JOIN axes_analytiques ax ON aa.axe_id = ax.id
    ORDER BY aa.created_at DESC LIMIT 50")->fetchAll();

$typeLabels = ['centre_cout'=>'Centre de coût','projet'=>'Projet','activite'=>'Activité','produit'=>'Produit','region'=>'Région','client_interne'=>'Client interne'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <h2 style="margin:0"><i class="fa-solid fa-chart-pie"></i> Comptabilité analytique</h2>
    <button class="btn btn-primary" onclick="openModal('modal-axe')"><i class="fa-solid fa-plus"></i> Nouvel axe</button>
</div>

<!-- Onglets -->
<div class="mb-20" style="display:flex;gap:4px">
    <a href="?tab=axes" class="btn <?= $tab === 'axes' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Axes analytiques</a>
    <a href="?tab=sections" class="btn <?= $tab === 'sections' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Sections / Clés</a>
    <a href="?tab=affectations" class="btn <?= $tab === 'affectations' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Affectations</a>
    <a href="repartition.php" class="btn btn-outline btn-sm">Répartitions par clé</a>
</div>

<?php if ($tab === 'axes'): ?>
<!-- Liste des axes -->
<?php foreach (['centre_cout','projet','activite','produit','region','client_interne'] as $typ):
    $filtres = array_filter($axes, fn($a) => $a['type'] === $typ);
    if (empty($filtres)) continue;
?>
<div class="card mb-20">
    <div class="card-header"><span class="card-title" style="font-size:14px;text-transform:uppercase"><?= $typeLabels[$typ] ?></span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Libellé</th><th>Parent</th><th>Actif</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($filtres as $ax): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($ax['code']) ?></td>
                    <td><?= htmlspecialchars($ax['libelle']) ?></td>
                    <td style="color:var(--text3);font-size:12px"><?= $ax['parent_id'] ? '↳ Hiérarchie' : '—' ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_axe"><input type="hidden" name="id" value="<?= $ax['id'] ?>">
                            <button class="btn btn-sm <?= $ax['actif'] ? 'btn-success' : 'btn-danger' ?>">
                                <i class="fa-solid <?= $ax['actif'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                            </button>
                        </form>
                    </td>
                    <td><button class="btn btn-outline btn-sm" onclick="openAffectation('<?= $ax['id'] ?>')"><i class="fa-solid fa-link"></i> Affecter</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($tab === 'sections'): ?>
<div class="card mb-20">
    <div class="card-header"><span class="card-title">Sections / Clés de répartition</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-section')"><i class="fa-solid fa-plus"></i> Ajouter</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Unité d'œuvre</th></tr></thead>
            <tbody>
                <?php foreach ($sections as $sec): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($sec['code']) ?></td>
                    <td><?= htmlspecialchars($sec['libelle']) ?></td>
                    <td><span class="badge"><?= $sec['type'] ?></span></td>
                    <td><?= htmlspecialchars($sec['unite_oeuvre'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'affectations'): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Affectations récentes</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Axe</th><th>Source</th><th>Montant</th></tr></thead>
            <tbody>
                <?php if (empty($affectations)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:40px">Aucune affectation.</td></tr>
                <?php else: foreach ($affectations as $aff): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($aff['created_at'])) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($aff['axe_libelle']) ?></span></td>
                    <td><?= htmlspecialchars($aff['source_type']) ?> #<?= $aff['source_id'] ?></td>
                    <td class="amount"><?= formatMontant($aff['montant']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Create Axe -->
<div class="modal-overlay" id="modal-axe">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Nouvel axe analytique</span><button class="modal-close" onclick="closeModal('modal-axe')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_axe">
            <div class="modal-body">
                <div class="form-group"><label>Type</label>
                    <select name="type" class="form-control" required>
                        <?php foreach ($typeLabels as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Code</label><input type="text" name="code" class="form-control" required maxlength="20"></div>
                    <div class="form-group"><label>Parent (optionnel)</label>
                        <select name="parent_id" class="form-control">
                            <option value="">— Aucun —</option>
                            <?php foreach ($axes as $ax): ?><option value="<?= $ax['id'] ?>"><?= htmlspecialchars($ax['code'].' — '.$ax['libelle']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Libellé</label><input type="text" name="libelle" class="form-control" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-axe')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create Section -->
<div class="modal-overlay" id="modal-section">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Nouvelle section / clé</span><button class="modal-close" onclick="closeModal('modal-section')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_section">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Code</label><input type="text" name="code" class="form-control" required></div>
                    <div class="form-group"><label>Type</label>
                        <select name="type" class="form-control" required>
                            <option value="pourcentage">Pourcentage</option>
                            <option value="montant">Montant</option>
                            <option value="unite_oeuvre">Unité d'œuvre</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Libellé</label><input type="text" name="libelle" class="form-control" required></div>
                <div class="form-group"><label>Unité d'œuvre (si applicable)</label><input type="text" name="unite_oeuvre" class="form-control" placeholder="m², heures, km, tonnes..."></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-section')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Quick affectation -->
<div class="modal-overlay" id="modal-affecter">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Affectation analytique</span><button class="modal-close" onclick="closeModal('modal-affecter')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="affecter">
            <input type="hidden" name="axe_id" id="aff-axe-id">
            <div class="modal-body">
                <div class="form-group"><label>Type de source</label>
                    <select name="source_type" class="form-control" required>
                        <option value="ecriture">Écriture comptable</option><option value="operation_caisse">Opération caisse</option>
                        <option value="operation_bancaire">Opération bancaire</option><option value="ligne_bulletin">Ligne bulletin paie</option>
                        <option value="engagement_ligne">Ligne engagement</option>
                    </select>
                </div>
                <div class="form-group"><label>ID Source</label><input type="number" name="source_id" class="form-control" required></div>
                <div class="form-group"><label>Montant (FCFA)</label><input type="number" name="montant" class="form-control" step="0.01" required></div>
                <div class="form-group"><label>Pourcentage (%)</label><input type="number" name="pourcentage" class="form-control" step="0.01" placeholder="Optionnel"></div>
                <div class="form-group"><label>ID Écriture comptable (si lié)</label><input type="number" name="ecriture_id" class="form-control" placeholder="Optionnel"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-affecter')">Annuler</button>
                <button type="submit" class="btn btn-primary">Affecter</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAffectation(axeId) {
    document.getElementById('aff-axe-id').value = axeId;
    openModal('modal-affecter');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
