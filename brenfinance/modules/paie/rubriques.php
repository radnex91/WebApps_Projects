<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('paie');
$pageTitle = 'Rubriques de paie';

$db = getDB();

// Handle export
if (isset($_GET['export'])) {
    $rows = $db->query("SELECT code, libelle, type, calcul, valeur, actif FROM rubriques_paie ORDER BY FIELD(type,'gain','indemnite','retenue','cotisation_patronale'), ordre_affichage")->fetchAll();
    exportData($rows, ['code'=>'Code','libelle'=>'Libellé','type'=>'Type','calcul'=>'Calcul','valeur'=>'Valeur','actif'=>'Actif'], 'rubriques_paie', $_GET['export']);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $code = sanitize($_POST['code'] ?? '');
        $libelle = sanitize($_POST['libelle'] ?? '');
        $type = $_POST['type'] ?? 'gain';
        $calcul = $_POST['calcul'] ?? 'montant_fixe';
        $valeur = $_POST['valeur'] !== '' ? (float)$_POST['valeur'] : null;
        $formule = $_POST['formule'] ?? null;
        $imposable = isset($_POST['imposable']) ? 1 : 0;
        $cotisable = isset($_POST['cotisable']) ? 1 : 0;
        $actif = isset($_POST['actif_rub']) ? 1 : 0;
        $ordre = (int)($_POST['ordre_affichage'] ?? 0);

        if ($action === 'create') {
            try {
                $db->prepare("INSERT INTO rubriques_paie (code, libelle, type, calcul, valeur, formule, imposable, cotisable_cnps, actif, ordre_affichage)
                    VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$code, $libelle, $type, $calcul, $valeur, $formule, $imposable, $cotisable, $actif, $ordre]);
                flash('success', 'Rubrique créée.');
            } catch (Exception $e) { flash('danger', $e->getMessage()); }
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE rubriques_paie SET code=?, libelle=?, type=?, calcul=?, valeur=?, formule=?, imposable=?, cotisable_cnps=?, actif=?, ordre_affichage=? WHERE id=?")
               ->execute([$code, $libelle, $type, $calcul, $valeur, $formule, $imposable, $cotisable, $actif, $ordre, $id]);
            flash('success', 'Rubrique mise à jour.');
        }
        header('Location: rubriques.php'); exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $r = $db->prepare("SELECT actif FROM rubriques_paie WHERE id=?")->execute([$id]) ? $db->query("SELECT actif FROM rubriques_paie WHERE id=$id")->fetch() : null;
        if ($r) {
            $new = $r['actif'] ? 0 : 1;
            $db->prepare("UPDATE rubriques_paie SET actif=? WHERE id=?")->execute([$new, $id]);
        }
        header('Location: rubriques.php'); exit;
    }
}

$rubriques = $db->query("SELECT * FROM rubriques_paie ORDER BY FIELD(type,'gain','indemnite','retenue','cotisation_patronale'), ordre_affichage")->fetchAll();
$typeLabels = ['gain'=>'Gain','retenue'=>'Retenue','cotisation_patronale'=>'Cotisation patronale','indemnite'=>'Indemnité'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <div>
        <h2 style="margin:0"><i class="fa-solid fa-sliders"></i> Rubriques de paie</h2>
        <p style="color:var(--text3);margin:4px 0 0">Configurer les éléments de calcul des bulletins</p>
    </div>
    <div style="display:flex;gap:8px">
        <?= exportButtons() ?>
        <button class="btn btn-primary" onclick="editRubrique(null)"><i class="fa-solid fa-plus"></i> Nouvelle rubrique</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Calcul</th><th>Valeur</th><th>Ordre</th><th>Actif</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rubriques as $r): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($r['code']) ?></td>
                    <td><?= htmlspecialchars($r['libelle']) ?></td>
                    <td><span class="badge"><?= $typeLabels[$r['type']] ?? $r['type'] ?></span></td>
                    <td><?= $r['calcul'] === 'formule' ? '<em>Formule</em>' : htmlspecialchars($r['calcul']) ?></td>
                    <td><?= $r['valeur'] !== null ? $r['valeur'] : ($r['formule'] ? htmlspecialchars($r['formule']) : '—') ?></td>
                    <td><?= $r['ordre_affichage'] ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm <?= $r['actif'] ? 'btn-success' : 'btn-danger' ?>" title="<?= $r['actif'] ? 'Actif' : 'Inactif' ?>">
                                <i class="fa-solid <?= $r['actif'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                            </button>
                        </form>
                    </td>
                    <td><button class="btn btn-outline btn-sm" onclick='editRubrique(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-circle-info"></i> Guide des calculs</span></div>
    <div class="card-body" style="font-size:13px;color:var(--text2);line-height:1.8">
        <p><strong>Montant fixe :</strong> valeur fixe en FCFA renseignée dans « Valeur ».</p>
        <p><strong>% du salaire de base :</strong> pourcentage appliqué au salaire de base uniquement.</p>
        <p><strong>% du brut :</strong> pourcentage appliqué sur (salaire de base + total des gains). Utilisé pour CNPS.</p>
        <p><strong>Formule :</strong> expression PHP évaluable. Variables disponibles : <code>$salaire_base</code>, <code>$brut</code> (base + gains), <code>$anciennete</code> (années). Exemple : <code>$brut * 0.042</code></p>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modal-rubrique">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title" id="rubrique-modal-title">Nouvelle rubrique</span>
            <button class="modal-close" onclick="closeModal('modal-rubrique')">&times;</button>
        </div>
        <form method="POST" id="rubrique-form">
            <input type="hidden" name="action" id="rub-action" value="create">
            <input type="hidden" name="id" id="rub-id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Code</label><input type="text" name="code" id="rub-code" class="form-control" required maxlength="20"></div>
                    <div class="form-group"><label>Ordre</label><input type="number" name="ordre_affichage" id="rub-ordre" class="form-control" value="0"></div>
                </div>
                <div class="form-group"><label>Libellé</label><input type="text" name="libelle" id="rub-libelle" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" id="rub-type" class="form-control" required>
                            <option value="gain">Gain</option><option value="indemnite">Indemnité</option>
                            <option value="retenue">Retenue</option><option value="cotisation_patronale">Cotisation patronale</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode de calcul</label>
                        <select name="calcul" id="rub-calcul" class="form-control" onchange="toggleFormule(this.value)">
                            <option value="montant_fixe">Montant fixe</option>
                            <option value="pourcentage_base">% du salaire de base</option>
                            <option value="pourcentage_brut">% du brut</option>
                            <option value="formule">Formule</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="rub-valeur-group"><label>Valeur (montant fixe ou %)</label><input type="number" name="valeur" id="rub-valeur" class="form-control" step="0.01"></div>
                <div class="form-group" id="rub-formule-group" style="display:none"><label>Formule PHP</label><input type="text" name="formule" id="rub-formule" class="form-control" placeholder="ex: $brut * 0.042"></div>
                <div style="display:flex;gap:16px;margin-top:8px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="imposable" id="rub-imposable" checked> Imposable
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="cotisable" id="rub-cotisable" checked> Cotisable CNPS
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="actif_rub" id="rub-actif" checked> Actif
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-rubrique')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
var rubriquesData = <?= json_encode($rubriques) ?>;

function editRubrique(data) {
    var form = document.getElementById('rubrique-form');
    if (data) {
        document.getElementById('rubrique-modal-title').textContent = 'Modifier rubrique';
        document.getElementById('rub-action').value = 'update';
        document.getElementById('rub-id').value = data.id;
        document.getElementById('rub-code').value = data.code;
        document.getElementById('rub-libelle').value = data.libelle;
        document.getElementById('rub-type').value = data.type;
        document.getElementById('rub-calcul').value = data.calcul;
        document.getElementById('rub-valeur').value = data.valeur || '';
        document.getElementById('rub-formule').value = data.formule || '';
        document.getElementById('rub-ordre').value = data.ordre_affichage;
        document.getElementById('rub-imposable').checked = data.imposable == 1;
        document.getElementById('rub-cotisable').checked = data.cotisable_cnps == 1;
        document.getElementById('rub-actif').checked = data.actif == 1;
        toggleFormule(data.calcul);
    } else {
        document.getElementById('rubrique-modal-title').textContent = 'Nouvelle rubrique';
        document.getElementById('rub-action').value = 'create';
        document.getElementById('rub-id').value = '';
        form.reset();
        document.getElementById('rub-imposable').checked = true;
        document.getElementById('rub-cotisable').checked = true;
        document.getElementById('rub-actif').checked = true;
        toggleFormule('montant_fixe');
    }
    openModal('modal-rubrique');
}

function toggleFormule(calcul) {
    document.getElementById('rub-valeur-group').style.display = calcul === 'formule' ? 'none' : '';
    document.getElementById('rub-formule-group').style.display = calcul === 'formule' ? '' : 'none';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
