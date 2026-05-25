<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('bons_commande');
$pageTitle = 'Créer un bon de commande';

$db = getDB();
$user = currentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $engagementId = (int)($_POST['engagement_id'] ?? 0);
        $fournisseurId = (int)($_POST['fournisseur_id'] ?? 0);
        $dateLivraison = $_POST['date_livraison_prevue'] ?? null;
        $commentaire = sanitize($_POST['commentaire'] ?? '');

        $eng = $db->prepare("SELECT * FROM demandes_engagement WHERE id=?");
        $eng->execute([$engagementId]);
        $eng = $eng->fetch();
        if (!$eng) { flash('danger', 'Engagement introuvable.'); header('Location: creer.php'); exit; }

        $lignes = $_POST['lignes'] ?? [];
        $montantTotal = 0;
        foreach ($lignes as $l) {
            $montantTotal += (float)($l['montant'] ?? (float)($l['quantite'] ?? 0) * (float)($l['prix_unitaire'] ?? 0));
        }

        $numero = generateNumeroSequentiel('BC', 'bons_commande');

        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO bons_commande (numero, engagement_id, fournisseur_id, date_emission, date_livraison_prevue, montant_total, commentaire, statut, cree_par)
                          VALUES (?,?,?,CURDATE(),?,?,?,'brouillon',?)")
               ->execute([$numero, $engagementId, $fournisseurId, $dateLivraison, $montantTotal, $commentaire, $_SESSION['user_id']]);
            $bcId = $db->lastInsertId();

            foreach ($lignes as $i => $l) {
                if (empty($l['libelle'])) continue;
                $qte = (int)($l['quantite'] ?? 0);
                $pu = (float)($l['prix_unitaire'] ?? 0);
                $ml = (float)($l['montant'] ?? ($qte * $pu));
                $db->prepare("INSERT INTO lignes_bon_commande (bon_commande_id, libelle, quantite, unite, prix_unitaire, montant_ligne, ordre)
                              VALUES (?,?,?,?,?,?,?)")
                   ->execute([$bcId, sanitize($l['libelle']), $qte, sanitize($l['unite'] ?? 'U'), $pu, $ml, $i + 1]);
            }
            $db->commit();
            auditLog('create', 'bons_commande', 'bons_commande', $bcId);
            flash('success', "Bon de commande $numero créé.");
            header('Location: detail.php?id=' . $bcId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('danger', 'Erreur: ' . $e->getMessage());
        }
    }
}

// Pre-load from engagement if selected
$preselectedEng = isset($_GET['engagement_id']) ? (int)$_GET['engagement_id'] : 0;

// Available engagements (with supplier info)
$engagements = $db->query("SELECT de.id, de.numero, de.objet, de.montant, f.id as fournisseur_id, f.nom as fournisseur_nom
    FROM demandes_engagement de
    LEFT JOIN fournisseurs f ON de.fournisseur_id = f.id
    WHERE de.statut IN ('approuve','execution_partielle') AND de.fournisseur_id IS NOT NULL
    ORDER BY de.created_at DESC")->fetchAll();

$fournisseurs = $db->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="index.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<h2 style="margin:0 0 4px"><i class="fa-solid fa-file-invoice"></i> Créer un bon de commande</h2>
<p style="color:var(--text3);margin:0 0 20px">Établir un bon de commande à partir d'un engagement validé</p>

<form method="POST" id="bc-form">
    <input type="hidden" name="action" value="create">

    <div class="card mb-20">
        <div class="card-body">
            <div class="form-group">
                <label>Engagement de référence <span style="color:var(--danger)">*</span></label>
                <select name="engagement_id" id="engagement_id" class="form-control" required onchange="onEngagementChange()">
                    <option value="">— Sélectionner un engagement —</option>
                    <?php foreach ($engagements as $eng): ?>
                    <option value="<?= $eng['id'] ?>" <?= $eng['id'] == $preselectedEng ? 'selected' : '' ?>
                        data-fournisseur="<?= $eng['fournisseur_id'] ?>"
                        data-fournisseur-nom="<?= htmlspecialchars($eng['fournisseur_nom']) ?>"
                        data-montant="<?= $eng['montant'] ?>">
                        <?= htmlspecialchars($eng['numero'] . ' — ' . $eng['objet'] . ' (' . formatMontant($eng['montant']) . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fournisseur <span style="color:var(--danger)">*</span></label>
                    <select name="fournisseur_id" id="fournisseur_id" class="form-control" required>
                        <option value="">— D\'abord sélectionner un engagement —</option>
                        <?php foreach ($fournisseurs as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de livraison prévue</label>
                    <input type="date" name="date_livraison_prevue" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Commentaire</label>
                <textarea name="commentaire" class="form-control" rows="2" placeholder="Conditions de livraison, remarques..."></textarea>
            </div>
        </div>
    </div>

    <!-- Lignes -->
    <div class="card mb-20">
        <div class="card-header">
            <span class="card-title">Lignes de commande</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addLigne()"><i class="fa-solid fa-plus"></i> Ajouter une ligne</button>
        </div>
        <div class="table-wrap">
            <table id="lignes-table">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th style="width:80px">Qté</th>
                        <th style="width:80px">Unité</th>
                        <th style="width:110px">Prix unitaire</th>
                        <th style="width:120px">Montant</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="lignes-body">
                    <tr class="ligne-row">
                        <td><input type="text" name="lignes[0][libelle]" class="form-control form-control-sm" placeholder="Libellé" required></td>
                        <td><input type="number" name="lignes[0][quantite]" class="form-control form-control-sm qte" value="1" min="1" onchange="calcLigne(this)"></td>
                        <td><input type="text" name="lignes[0][unite]" class="form-control form-control-sm" value="U"></td>
                        <td><input type="number" name="lignes[0][prix_unitaire]" class="form-control form-control-sm pu" value="0" step="0.01" onchange="calcLigne(this)"></td>
                        <td><input type="number" name="lignes[0][montant]" class="form-control form-control-sm montant-ligne" value="0" step="0.01" readonly></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeLigne(this)" title="Supprimer"><i class="fa-solid fa-xmark"></i></button></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;font-size:15px">
                        <td colspan="4" style="text-align:right;padding:12px 10px">TOTAL</td>
                        <td style="padding:12px 10px" id="total-display">0 FCFA</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
        <a href="index.php" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-save"></i> Créer le bon de commande</button>
    </div>
</form>

<script>
var ligneCount = 1;

function onEngagementChange() {
    var sel = document.getElementById('engagement_id');
    var opt = sel.options[sel.selectedIndex];
    var fournisseurSelect = document.getElementById('fournisseur_id');
    var fid = opt.getAttribute('data-fournisseur');
    if (fid) {
        // Set the matching fournisseur
        for (var i = 0; i < fournisseurSelect.options.length; i++) {
            if (fournisseurSelect.options[i].value === fid) {
                fournisseurSelect.selectedIndex = i;
                break;
            }
        }
    }
}

function addLigne() {
    var tbody = document.getElementById('lignes-body');
    var tr = document.createElement('tr');
    tr.className = 'ligne-row';
    tr.innerHTML = '<td><input type="text" name="lignes[' + ligneCount + '][libelle]" class="form-control form-control-sm" placeholder="Libellé" required></td>' +
        '<td><input type="number" name="lignes[' + ligneCount + '][quantite]" class="form-control form-control-sm qte" value="1" min="1" onchange="calcLigne(this)"></td>' +
        '<td><input type="text" name="lignes[' + ligneCount + '][unite]" class="form-control form-control-sm" value="U"></td>' +
        '<td><input type="number" name="lignes[' + ligneCount + '][prix_unitaire]" class="form-control form-control-sm pu" value="0" step="0.01" onchange="calcLigne(this)"></td>' +
        '<td><input type="number" name="lignes[' + ligneCount + '][montant]" class="form-control form-control-sm montant-ligne" value="0" step="0.01" readonly></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeLigne(this)" title="Supprimer"><i class="fa-solid fa-xmark"></i></button></td>';
    tbody.appendChild(tr);
    ligneCount++;
    updateTotal();
}

function removeLigne(btn) {
    var tbody = document.getElementById('lignes-body');
    if (tbody.querySelectorAll('.ligne-row').length <= 1) return;
    btn.closest('tr').remove();
    updateTotal();
}

function calcLigne(el) {
    var row = el.closest('tr');
    var qte = parseFloat(row.querySelector('.qte').value) || 0;
    var pu = parseFloat(row.querySelector('.pu').value) || 0;
    row.querySelector('.montant-ligne').value = (qte * pu).toFixed(2);
    updateTotal();
}

function updateTotal() {
    var montants = document.querySelectorAll('.montant-ligne');
    var total = 0;
    montants.forEach(function(m) { total += parseFloat(m.value) || 0; });
    document.getElementById('total-display').textContent = new Intl.NumberFormat('fr-CM').format(total) + ' FCFA';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
