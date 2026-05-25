<?php
$pageTitle = 'Modifier le colis';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('colis_modifier');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: colis.php'); exit; }

$colis = Database::fetchOne("SELECT * FROM colis WHERE id=?", [$id]);
if (!$colis) { header('Location: colis.php'); exit; }

$details = Database::fetchAll("SELECT * FROM colis_details WHERE colis_id=? ORDER BY id", [$id]);

$agences = getAgences();
$voyages = getVoyagesOuverts();
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-set agence_depart_id from user's agency
    if (!empty($user['agence_id'])) {
        $_POST['agence_depart_id'] = $user['agence_id'];
    }
    $required = ['expediteur_nom','expediteur_telephone','destinataire_nom','destinataire_telephone',
                 'agence_depart_id','agence_arrivee_id','tarif'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) $errors[] = "Le champ $f est requis.";
    }
    if (!$errors) {
        // Valider les lignes de détail
        $lignes = $_POST['lignes'] ?? [];
        $lignesValides = array_filter($lignes, fn($l) => !empty(trim($l['description'] ?? '')));
        if (empty($lignesValides)) {
            $errors[] = "Au moins une ligne de détail est requise.";
        }
    }

    if (!$errors) {
        $tarif  = floatval($_POST['tarif']);
        $remise = floatval($_POST['remise']??0);
        $total  = max(0, $tarif - $remise);
        // Calculer les totaux depuis les lignes
        $totalPoids = array_sum(array_map(fn($l) => floatval($l['poids'] ?? 0), $lignesValides));
        $totalPieces = array_sum(array_map(fn($l) => intval($l['nombre_pieces'] ?? 1), $lignesValides));
        $totalValeur = array_sum(array_map(fn($l) => floatval($l['valeur_declaree'] ?? 0), $lignesValides));
        $descGenerale = implode(', ', array_map(fn($l) => trim($l['description']), $lignesValides));

        Database::execute(
            "UPDATE colis SET
                type_expedition=?, expediteur_nom=?, expediteur_telephone=?, expediteur_ville=?, expediteur_adresse=?,
                destinataire_nom=?, destinataire_telephone=?, destinataire_ville=?, destinataire_adresse=?,
                description=?, poids=?, nombre_pieces=?, valeur_declaree=?,
                tarif=?, remise=?, montant_total=?, mode_paiement=?, statut_paiement=?,
                agence_depart_id=?, agence_arrivee_id=?, voyage_id=?,
                date_livraison_prevue=?, notes=?
             WHERE id=?",
            [
                $_POST['type_expedition']??'normal',
                trim($_POST['expediteur_nom']), trim($_POST['expediteur_telephone']),
                trim($_POST['expediteur_ville']??''), trim($_POST['expediteur_adresse']??''),
                trim($_POST['destinataire_nom']), trim($_POST['destinataire_telephone']),
                trim($_POST['destinataire_ville']??''), trim($_POST['destinataire_adresse']??''),
                $descGenerale, $totalPoids,
                $totalPieces, $totalValeur,
                $tarif, $remise, $total,
                $_POST['mode_paiement']??'especes', $_POST['statut_paiement']??'en_attente',
                intval($_POST['agence_depart_id']), intval($_POST['agence_arrivee_id']),
                !empty($_POST['voyage_id']) ? intval($_POST['voyage_id']) : null,
                !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null,
                trim($_POST['notes']??''),
                $id
            ]
        );

        // Remplacer les lignes de détail
        Database::execute("DELETE FROM colis_details WHERE colis_id=?", [$id]);
        foreach ($lignesValides as $ligne) {
            Database::insert(
                "INSERT INTO colis_details (colis_id, description, poids, nombre_pieces, valeur_declaree) VALUES (?,?,?,?,?)",
                [$id, trim($ligne['description']), floatval($ligne['poids']??0), intval($ligne['nombre_pieces']??1), floatval($ligne['valeur_declaree']??0)]
            );
        }
        Auth::logAction('COLIS_MODIFIE','colis',$id);
        $_SESSION['flash'] = ['type'=>'success','message'=>'Colis '.$colis['numero_colis'].' mis à jour.'];
        header("Location: detail_colis.php?id=$id"); exit;
    }
    // Merge POST into colis for re-display
    $colis = array_merge($colis, $_POST);
    $details = [];
    $postLignes = $_POST['lignes'] ?? [];
    foreach ($postLignes as $l) {
        if (!empty(trim($l['description'] ?? ''))) $details[] = $l;
    }
    if (empty($details)) $details = [['description'=>'','poids'=>0,'nombre_pieces'=>1,'valeur_declaree'=>0]];
}
?>

<div style="max-width:900px">
    <div class="d-flex justify-between align-center mb-3">
        <div>
            <h2 style="font-size:1.3rem;font-weight:700">Modifier le colis <span class="colis-num"><?= $colis['numero_colis'] ?></span></h2>
        </div>
        <a href="detail_colis.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($errors): ?>
    <div class="flash flash-error mb-2"><?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="type_expedition" value="<?= htmlspecialchars($colis['type_expedition'] ?? 'normal') ?>">

        <?php if (($colis['type_expedition'] ?? 'normal') === 'accompagne'): ?>
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-route"></i> Voyage associé</div>
            <div class="form-group">
                <label class="form-label">Voyage</label>
                <select name="voyage_id" id="voyage_id" class="form-control">
                    <option value="">— Choisir —</option>
                    <?php foreach ($voyages as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= ($colis['voyage_id']??'')==$v['id']?'selected':'' ?>>
                        <?= $v['numero_voyage'] ?> — <?= $v['nom_depart'] ?> → <?= $v['nom_arrivee'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-user-circle"></i> Expéditeur</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom <span class="req">*</span></label>
                    <input type="text" name="expediteur_nom" class="form-control" required value="<?= htmlspecialchars($colis['expediteur_nom']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone <span class="req">*</span></label>
                    <input type="tel" name="expediteur_telephone" class="form-control" required value="<?= htmlspecialchars($colis['expediteur_telephone']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ville</label>
                    <input type="text" name="expediteur_ville" class="form-control" value="<?= htmlspecialchars($colis['expediteur_ville']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="expediteur_adresse" class="form-control" value="<?= htmlspecialchars($colis['expediteur_adresse']??'') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-user-check"></i> Destinataire</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom <span class="req">*</span></label>
                    <input type="text" name="destinataire_nom" class="form-control" required value="<?= htmlspecialchars($colis['destinataire_nom']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone <span class="req">*</span></label>
                    <input type="tel" name="destinataire_telephone" class="form-control" required value="<?= htmlspecialchars($colis['destinataire_telephone']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ville</label>
                    <input type="text" name="destinataire_ville" class="form-control" value="<?= htmlspecialchars($colis['destinataire_ville']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="destinataire_adresse" class="form-control" value="<?= htmlspecialchars($colis['destinataire_adresse']??'') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title" style="display:flex;align-items:center;justify-content:space-between">
                <span><i class="fas fa-cube"></i> Détails du colis</span>
                <button type="button" onclick="addLigne()" class="btn btn-sm" style="background:var(--primary);color:#fff;font-size:.78rem;padding:5px 12px;border-radius:6px;border:none;cursor:pointer">
                    <i class="fas fa-plus"></i> Ajouter une ligne
                </button>
            </div>
            <div id="lignes-container">
                <?php foreach ($details as $i => $ligne): ?>
                <div class="ligne-row" data-index="<?= $i ?>" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 40px;gap:10px;align-items:end;margin-bottom:10px;padding:12px;background:var(--bg-card2);border-radius:var(--radius-sm);border:1px solid var(--border)">
                    <div class="form-group" style="margin:0">
                        <label class="form-label" style="font-size:.72rem">Description <span class="req">*</span></label>
                        <input type="text" name="lignes[<?= $i ?>][description]" class="form-control" required
                               placeholder="Ex: Valise, Carton..."
                               value="<?= htmlspecialchars($ligne['description'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label" style="font-size:.72rem">Poids (kg)</label>
                        <input type="number" name="lignes[<?= $i ?>][poids]" class="form-control ligne-poids" step="0.01" min="0"
                               value="<?= htmlspecialchars($ligne['poids'] ?? 0) ?>">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label" style="font-size:.72rem">Pièces</label>
                        <input type="number" name="lignes[<?= $i ?>][nombre_pieces]" class="form-control ligne-pieces" min="1"
                               value="<?= htmlspecialchars($ligne['nombre_pieces'] ?? 1) ?>">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label" style="font-size:.72rem">Valeur (F)</label>
                        <input type="number" name="lignes[<?= $i ?>][valeur_declaree]" class="form-control ligne-valeur" min="0"
                               value="<?= htmlspecialchars($ligne['valeur_declaree'] ?? 0) ?>">
                    </div>
                    <button type="button" onclick="removeLigne(this)" class="btn-remove-ligne" title="Supprimer"
                            style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;padding:8px 0">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="lignes-totaux" style="display:flex;gap:20px;padding:10px 12px;background:rgba(232,80,10,0.06);border-radius:var(--radius-sm);font-size:.85rem;margin-top:4px">
                <span>Total : <strong id="total-poids">0</strong> kg</span>
                <span>|</span>
                <span><strong id="total-pieces">0</strong> pièce(s)</span>
                <span>|</span>
                <span>Valeur : <strong id="total-valeur">0</strong> F</span>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-route"></i> Trajet</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Agence de départ</label>
                    <?php if (!empty($user['agence_id'])): ?>
                    <?php $userAgence = Database::fetchOne("SELECT id, nom FROM agences WHERE id=?", [$user['agence_id']]); ?>
                    <input type="hidden" name="agence_depart_id" value="<?= $user['agence_id'] ?>">
                    <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;font-weight:600">
                        <i class="fas fa-building" style="color:var(--primary);margin-right:6px"></i><?= htmlspecialchars($userAgence['nom'] ?? '') ?>
                    </div>
                    <?php else: ?>
                    <select name="agence_depart_id" class="form-control" required>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $colis['agence_depart_id']==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Agence d'arrivée <span class="req">*</span></label>
                    <select name="agence_arrivee_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <?php if (!empty($user['agence_id']) && $a['id'] == $user['agence_id']) continue; ?>
                        <option value="<?= $a['id'] ?>" <?= $colis['agence_arrivee_id']==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tarif (FCFA) <span class="req">*</span></label>
                    <input type="number" name="tarif" id="tarif" class="form-control" required value="<?= $colis['tarif'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Remise</label>
                    <input type="number" name="remise" id="remise" class="form-control" value="<?= $colis['remise'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Total</label>
                    <input type="hidden" name="montant_total" id="montant_total">
                    <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;
                         padding:10px 14px;font-family:var(--font-mono);font-size:1rem;font-weight:700;color:var(--accent)"
                         id="montant_total_display"><?= number_format($colis['montant_total'],0,',',' ') ?> FCFA</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Mode paiement</label>
                    <select name="mode_paiement" class="form-control">
                        <?php foreach (['especes'=>'Espèces','mobile_money'=>'Mobile Money','virement'=>'Virement','a_la_livraison'=>'À la livraison'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $colis['mode_paiement']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut paiement</label>
                    <select name="statut_paiement" class="form-control">
                        <?php foreach (['en_attente'=>'En attente','paye'=>'Payé','partiel'=>'Partiel'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $colis['statut_paiement']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Livraison prévue</label>
                    <input type="date" name="date_livraison_prevue" class="form-control" value="<?= $colis['date_livraison_prevue']??'' ?>">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"><?= htmlspecialchars($colis['notes']??'') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px">
            <a href="detail_colis.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Enregistrer</button>
        </div>
    </form>
</div>

<script>
(function() {
    let ligneIndex = <?= count($details) ?>;

    function updateTotaux() {
        let totalPoids = 0, totalPieces = 0, totalValeur = 0;
        document.querySelectorAll('.ligne-row').forEach(row => {
            totalPoids  += parseFloat(row.querySelector('.ligne-poids')?.value  || 0);
            totalPieces += parseInt(row.querySelector('.ligne-pieces')?.value  || 0);
            totalValeur += parseFloat(row.querySelector('.ligne-valeur')?.value || 0);
        });
        document.getElementById('total-poids').textContent  = totalPoids.toFixed(2);
        document.getElementById('total-pieces').textContent = totalPieces;
        document.getElementById('total-valeur').textContent = totalValeur.toLocaleString('fr-FR');
    }

    window.addLigne = function() {
        const container = document.getElementById('lignes-container');
        const idx = ligneIndex++;
        const html = `
        <div class="ligne-row" data-index="${idx}" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 40px;gap:10px;align-items:end;margin-bottom:10px;padding:12px;background:var(--bg-card2);border-radius:var(--radius-sm);border:1px solid var(--border)">
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.72rem">Description <span class="req">*</span></label>
                <input type="text" name="lignes[${idx}][description]" class="form-control" required placeholder="Ex: Valise, Carton...">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.72rem">Poids (kg)</label>
                <input type="number" name="lignes[${idx}][poids]" class="form-control ligne-poids" step="0.01" min="0" value="0">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.72rem">Pièces</label>
                <input type="number" name="lignes[${idx}][nombre_pieces]" class="form-control ligne-pieces" min="1" value="1">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="font-size:.72rem">Valeur (F)</label>
                <input type="number" name="lignes[${idx}][valeur_declaree]" class="form-control ligne-valeur" min="0" value="0">
            </div>
            <button type="button" onclick="removeLigne(this)" class="btn-remove-ligne" title="Supprimer"
                    style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;padding:8px 0">
                <i class="fas fa-trash-can"></i>
            </button>
        </div>`;
        container.insertAdjacentHTML('beforeend', html);
        updateTotaux();
    };

    window.removeLigne = function(btn) {
        const rows = document.querySelectorAll('.ligne-row');
        if (rows.length <= 1) { alert('Au moins une ligne est requise.'); return; }
        btn.closest('.ligne-row').remove();
        reindexLignes();
        updateTotaux();
    };

    function reindexLignes() {
        document.querySelectorAll('.ligne-row').forEach((row, i) => {
            row.querySelectorAll('input').forEach(inp => {
                inp.name = inp.name.replace(/lignes\[\d+\]/, `lignes[${i}]`);
            });
        });
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('ligne-poids') || e.target.classList.contains('ligne-pieces') || e.target.classList.contains('ligne-valeur')) {
            updateTotaux();
        }
    });

    updateTotaux();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
