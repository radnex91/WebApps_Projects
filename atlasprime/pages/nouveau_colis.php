<?php
$typeExpedition = ($_GET['type'] ?? '') === 'accompagne' ? 'accompagne' : 'normal';
$pageTitle = $typeExpedition === 'accompagne' ? 'Colis accompagné' : 'Expédition normale';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('colis_creer');

$agences = getAgences();
$voyages = getVoyagesOuverts();
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-set agence_depart_id from user's agency
    if (!empty($user['agence_id'])) {
        $_POST['agence_depart_id'] = $user['agence_id'];
    }
    // Validation
    $required = ['expediteur_nom','expediteur_telephone','destinataire_nom','destinataire_telephone',
                 'agence_depart_id','agence_arrivee_id','tarif'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) $errors[] = "Le champ « $field » est requis.";
    }
    $type = $_POST['type_expedition'] ?? 'normal';
    if ($type === 'accompagne' && empty($_POST['voyage_id'])) {
        $errors[] = "Veuillez sélectionner un voyage pour un colis accompagné.";
    }
    if ($_POST['agence_depart_id'] === $_POST['agence_arrivee_id']) {
        $errors[] = "L'agence de départ et d'arrivée doivent être différentes.";
    }

    // Valider les lignes de détail
    $lignes = $_POST['lignes'] ?? [];
    $lignesValides = array_filter($lignes, fn($l) => !empty(trim($l['description'] ?? '')));
    if (empty($lignesValides)) {
        $errors[] = "Au moins une ligne de détail est requise.";
    }

    if (!$errors) {
        $numeroColis = genererNumeroColis();
        $tarif  = floatval($_POST['tarif']);
        $remise = floatval($_POST['remise'] ?? 0);
        $total  = max(0, $tarif - $remise);

        // Calculer les totaux depuis les lignes
        $totalPoids = array_sum(array_map(fn($l) => floatval($l['poids'] ?? 0), $lignesValides));
        $totalPieces = array_sum(array_map(fn($l) => intval($l['nombre_pieces'] ?? 1), $lignesValides));
        $totalValeur = array_sum(array_map(fn($l) => floatval($l['valeur_declaree'] ?? 0), $lignesValides));
        $descGenerale = implode(', ', array_map(fn($l) => trim($l['description']), $lignesValides));

        $id = Database::insert(
            "INSERT INTO colis (
                numero_colis, type_expedition,
                expediteur_nom, expediteur_telephone, expediteur_ville, expediteur_adresse,
                destinataire_nom, destinataire_telephone, destinataire_ville, destinataire_adresse,
                description, poids, nombre_pieces, valeur_declaree,
                tarif, remise, montant_total, mode_paiement, statut_paiement,
                agence_depart_id, agence_arrivee_id, voyage_id,
                date_livraison_prevue, notes, cree_par
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $numeroColis, $type,
                trim($_POST['expediteur_nom']), trim($_POST['expediteur_telephone']),
                trim($_POST['expediteur_ville'] ?? ''), trim($_POST['expediteur_adresse'] ?? ''),
                trim($_POST['destinataire_nom']), trim($_POST['destinataire_telephone']),
                trim($_POST['destinataire_ville'] ?? ''), trim($_POST['destinataire_adresse'] ?? ''),
                $descGenerale, $totalPoids,
                $totalPieces, $totalValeur,
                $tarif, $remise, $total,
                $_POST['mode_paiement'] ?? 'especes',
                $_POST['statut_paiement'] ?? 'en_attente',
                intval($_POST['agence_depart_id']), intval($_POST['agence_arrivee_id']),
                !empty($_POST['voyage_id']) ? intval($_POST['voyage_id']) : null,
                !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null,
                trim($_POST['notes'] ?? ''),
                $user['id']
            ]
        );

        // Insérer les lignes de détail
        foreach ($lignesValides as $ligne) {
            Database::insert(
                "INSERT INTO colis_details (colis_id, description, poids, nombre_pieces, valeur_declaree) VALUES (?,?,?,?,?)",
                [
                    $id,
                    trim($ligne['description']),
                    floatval($ligne['poids'] ?? 0),
                    intval($ligne['nombre_pieces'] ?? 1),
                    floatval($ligne['valeur_declaree'] ?? 0)
                ]
            );
        }

        // Log suivi initial
        Database::execute(
            "INSERT INTO suivi_colis (colis_id, statut, localisation, commentaire, cree_par)
             VALUES (?, 'enregistre', ?, 'Colis enregistré', ?)",
            [$id, Database::fetchOne("SELECT nom FROM agences WHERE id=?", [$_POST['agence_depart_id']])['nom'] ?? '', $user['id']]
        );

        Auth::logAction('COLIS_CREE', 'colis', $id, "N°: $numeroColis");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Colis $numeroColis enregistré avec succès !"];
        header("Location: colis.php?highlight=$id");
        exit;
    }
}
?>

<div style="max-width:900px">
    <div class="d-flex justify-between align-center mb-3">
        <div>
            <h2 style="font-size:1.3rem;font-weight:700">
                <?php if ($typeExpedition === 'accompagne'): ?>
                <i class="fas fa-person-walking-luggage" style="color:var(--purple);margin-right:8px"></i>Colis accompagné
                <?php else: ?>
                <i class="fas fa-paper-plane" style="color:var(--primary);margin-right:8px"></i>Expédition normale
                <?php endif; ?>
            </h2>
            <p class="text-muted" style="font-size:.85rem">
                <?php if ($typeExpedition === 'accompagne'): ?>
                Rattacher un colis à un voyage avec chauffeur identifié
                <?php else: ?>
                Envoi standard par le réseau de transport
                <?php endif; ?>
            </p>
        </div>
        <a href="colis.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($errors): ?>
    <div class="flash flash-error" style="margin:0 0 20px">
        <i class="fas fa-exclamation-triangle"></i>
        <div><?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <form method="post" id="colisForm">
        <input type="hidden" name="type_expedition" value="<?= htmlspecialchars($typeExpedition) ?>">

        <!-- VOYAGE (si accompagné) -->
        <?php if ($typeExpedition === 'accompagne'): ?>
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-route"></i> Voyage associé</div>
            <div class="form-group">
                <label class="form-label">Sélectionner un voyage <span class="req">*</span></label>
                <select name="voyage_id" id="voyage_id" class="form-control">
                    <option value="">— Choisir un voyage —</option>
                    <?php foreach ($voyages as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= ($_POST['voyage_id']??'')==$v['id']?'selected':'' ?>>
                        <?= $v['numero_voyage'] ?> — <?= $v['nom_depart'] ?> → <?= $v['nom_arrivee'] ?>
                        (<?= formatDate($v['date_depart'],'d/m/Y H:i') ?>) — <?= htmlspecialchars($v['transporteur']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        </div>

<?php
// Définir la ville par défaut pour l'expéditeur (ville de l'opérateur connecté)
$defaultExpediteurVille = '';
if (!empty($user['agence_id'])) {
    $agenceInfo = Database::fetchOne("SELECT nom FROM agences WHERE id = ?", [$user['agence_id']]);
    $defaultExpediteurVille = $agenceInfo ? $agenceInfo['nom'] : '';
}
?>
<!-- EXPÉDITEUR -->
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-user-circle"></i> Expéditeur</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom complet <span class="req">*</span></label>
                    <input type="text" name="expediteur_nom" class="form-control" required
                            placeholder="Jean Dupont" value="<?= htmlspecialchars($_POST['expediteur_nom']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone <span class="req">*</span></label>
                    <input type="tel" name="expediteur_telephone" class="form-control" required
                            placeholder="+237 6XX XX XX XX" value="<?= htmlspecialchars($_POST['expediteur_telephone']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ville</label>
                    <input type="text" name="expediteur_ville" class="form-control" readonly
                            value="<?= htmlspecialchars($_POST['expediteur_ville'] ?? $defaultExpediteurVille) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="expediteur_adresse" class="form-control"
                            placeholder="Quartier, rue..." value="<?= htmlspecialchars($_POST['expediteur_adresse']??'') ?>">
                </div>
            </div>
        </div>

        <!-- DESTINATAIRE -->
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-user-check"></i> Destinataire</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom complet <span class="req">*</span></label>
                    <input type="text" name="destinataire_nom" class="form-control" required
                           placeholder="Marie Martin" value="<?= htmlspecialchars($_POST['destinataire_nom']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone <span class="req">*</span></label>
                    <input type="tel" name="destinataire_telephone" class="form-control" required
                           placeholder="+237 6XX XX XX XX" value="<?= htmlspecialchars($_POST['destinataire_telephone']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ville de livraison</label>
                    <select name="destinataire_ville" class="form-control" id="destinataire_ville">
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <?php if (!empty($user['agence_id']) && $a['id'] == $user['agence_id']) continue; ?>
                        <option value="<?= htmlspecialchars($a['nom']) ?>" data-agence-id="<?= $a['id'] ?>" <?= ($_POST['destinataire_ville']??'')==$a['nom']?'selected':'' ?>>
                            <?= htmlspecialchars($a['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse de livraison</label>
                    <input type="text" name="destinataire_adresse" class="form-control"
                           placeholder="Adresse précise..." value="<?= htmlspecialchars($_POST['destinataire_adresse']??'') ?>">
                </div>
            </div>
        </div>

        <!-- DÉTAILS COLIS — LIGNES DYNAMIQUES -->
        <div class="form-section">
            <div class="form-section-title" style="display:flex;align-items:center;justify-content:space-between">
                <span><i class="fas fa-cube"></i> Détails du colis</span>
                <button type="button" onclick="addLigne()" class="btn btn-sm" style="background:var(--primary);color:#fff;font-size:.78rem;padding:5px 12px;border-radius:6px;border:none;cursor:pointer">
                    <i class="fas fa-plus"></i> Ajouter une ligne
                </button>
            </div>
            <div id="lignes-container">
                <?php
                $postLignes = $_POST['lignes'] ?? [];
                if (empty($postLignes)) $postLignes = [['description'=>'','poids'=>0,'nombre_pieces'=>1,'valeur_declaree'=>0]];
                foreach ($postLignes as $i => $ligne): ?>
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

        <!-- TRAJET -->
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-map-signs"></i> Trajet</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Agence de départ</label>
                    <?php if (!empty($user['agence_id'])): ?>
                    <?php
                    $userAgence = Database::fetchOne("SELECT id, nom FROM agences WHERE id=?", [$user['agence_id']]);
                    ?>
                    <input type="hidden" name="agence_depart_id" value="<?= $user['agence_id'] ?>">
                    <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;font-weight:600">
                        <i class="fas fa-building" style="color:var(--primary);margin-right:6px"></i><?= htmlspecialchars($userAgence['nom'] ?? '') ?>
                    </div>
                    <?php else: ?>
                    <select name="agence_depart_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($agences as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Agence d'arrivée</label>
                    <input type="hidden" name="agence_arrivee_id" id="agence_arrivee_id" value="<?= htmlspecialchars($_POST['agence_arrivee_id']??'') ?>">
                    <div id="agence_arrivee_display" style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;font-weight:600;color:var(--text-muted)">
                        Sélectionnez la ville de livraison
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Date de livraison prévue</label>
                    <input type="text" id="date_livraison_display" class="form-control" readonly
                           style="background:var(--bg-card2);font-weight:600;color:var(--text-muted)"
                           value="">
                    <input type="hidden" name="date_livraison_prevue" id="date_livraison_prevue" value="">
                </div>
            </div>
        </div>

        <!-- TARIFICATION -->
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-coins"></i> Tarification & Paiement</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tarif (FCFA) <span class="req">*</span></label>
                    <input type="number" name="tarif" id="tarif" class="form-control" min="0" required
                           placeholder="5000" value="<?= htmlspecialchars($_POST['tarif']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Remise (FCFA)</label>
                    <input type="number" name="remise" id="remise" class="form-control" min="0"
                           placeholder="0" value="<?= htmlspecialchars($_POST['remise']??'0') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Montant total</label>
                    <input type="hidden" name="montant_total" id="montant_total">
                    <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;
                                font-family:var(--font-mono);font-size:1.1rem;font-weight:700;color:var(--accent);"
                         id="montant_total_display">0 FCFA</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Mode de paiement</label>
                    <select name="mode_paiement" class="form-control">
                        <option value="especes" <?= ($_POST['mode_paiement']??'especes')==='especes'?'selected':'' ?>>Espèces</option>
                        <option value="mobile_money" <?= ($_POST['mode_paiement']??'')==='mobile_money'?'selected':'' ?>>Mobile Money</option>
                        <option value="virement" <?= ($_POST['mode_paiement']??'')==='virement'?'selected':'' ?>>Virement</option>
                        <option value="a_la_livraison" <?= ($_POST['mode_paiement']??'')==='a_la_livraison'?'selected':'' ?>>À la livraison</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut paiement</label>
                    <input type="hidden" name="statut_paiement" value="paye">
                    <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;font-weight:600;color:#4ADE80">
                        <i class="fas fa-check-circle" style="margin-right:6px"></i>Payé
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" style="min-height:60px"
                              placeholder="Instructions particulières..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="colis.php" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Enregistrer le colis
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    let ligneIndex = <?= count($postLignes ?? [['description'=>'']]) ?>;

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

    // Ville de livraison → agence d'arrivée + date de livraison auto
    const villeSelect = document.getElementById('destinataire_ville');
    const agenceIdInput = document.getElementById('agence_arrivee_id');
    const agenceDisplay = document.getElementById('agence_arrivee_display');
    const dateDisplay = document.getElementById('date_livraison_display');
    const dateInput = document.getElementById('date_livraison_prevue');

    function updateArriveeAndDate() {
        const opt = villeSelect.options[villeSelect.selectedIndex];
        const agId = opt ? opt.getAttribute('data-agence-id') : null;
        const ville = opt ? opt.value : '';

        if (agId && ville) {
            agenceIdInput.value = agId;
            agenceDisplay.innerHTML = '<i class="fas fa-building" style="color:var(--accent);margin-right:6px"></i>' + opt.text.trim();
            agenceDisplay.style.color = 'var(--text)';

            // Calcul date livraison : 5 à 7 jours aléatoires
            const delai = 5 + Math.floor(Math.random() * 3); // 5, 6 ou 7
            const dateLiv = new Date();
            dateLiv.setDate(dateLiv.getDate() + delai);
            const yyyy = dateLiv.getFullYear();
            const mm = String(dateLiv.getMonth() + 1).padStart(2, '0');
            const dd = String(dateLiv.getDate()).padStart(2, '0');
            dateInput.value = yyyy + '-' + mm + '-' + dd;
            dateDisplay.value = dd + '/' + mm + '/' + yyyy + ' (J+' + delai + ')';
        } else {
            agenceIdInput.value = '';
            agenceDisplay.innerHTML = 'Sélectionnez la ville de livraison';
            agenceDisplay.style.color = 'var(--text-muted)';
            dateInput.value = '';
            dateDisplay.value = '';
        }
    }

    if (villeSelect) {
        villeSelect.addEventListener('change', updateArriveeAndDate);
        // Initialiser si déjà sélectionné (repost)
        if (villeSelect.value) updateArriveeAndDate();
    }

    updateTotaux();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
