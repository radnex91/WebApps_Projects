<?php
$pageTitle = 'Manifest complément';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('manifest_gerer');

$agences = getAgences();
$errors = [];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voyage_id'])) {
    $voyageId = intval($_POST['voyage_id']);
    $selectedIds = array_map('intval', $_POST['colis_ids'] ?? []);

    if (!$voyageId) $errors[] = "Selectionnez un voyage en transit.";
    if (empty($selectedIds)) $errors[] = "Selectionnez au moins un colis a charger.";

    if (!$errors) {
        $voyage = Database::fetchOne(
            "SELECT v.*, ad.nom AS nom_depart FROM voyages v JOIN agences ad ON v.agence_depart_id=ad.id WHERE v.id=?",
            [$voyageId]
        );

        // Verifier que les colis appartiennent a l'agence du voyage, a l'agence de l'utilisateur, ou sont en transit
        if ($voyage) {
            $agDepart = $voyage['agence_depart_id'];
            $myAgence = $user['agence_id'] ?? 0;
            $in = implode(',', array_map('intval', $selectedIds));
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

        $numVoyage = $voyage ? $voyage['numero_voyage'] : '';
        $myAgenceNom = '';
        if (!empty($user['agence_id'])) {
            $ag = Database::fetchOne("SELECT nom FROM agences WHERE id=?", [$user['agence_id']]);
            $myAgenceNom = $ag ? $ag['nom'] : '';
        }
        $localisation = $myAgenceNom ?: ($voyage ? $voyage['nom_depart'] : '');

        if (!$errors) {
            $in = implode(',', array_map('intval', $selectedIds));
            Database::execute(
                "UPDATE colis SET statut='en_transit', voyage_id=?, escale_actuelle_id=NULL WHERE id IN ($in) AND statut IN ('enregistre','en_transit','en_livraison') AND (voyage_id IS NULL OR voyage_id != ?)",
                [$voyageId, $voyageId]
            );
            foreach ($selectedIds as $cid) {
                Database::execute(
                    "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
                    [$cid, 'en_transit', $localisation, "Complement charge dans le voyage $numVoyage", $user['id']]
                );
            }
            Auth::logAction('MANIFEST_COMPLEMENT', 'voyages', $voyageId, count($selectedIds) . " colis ajoutes");
            $_SESSION['flash'] = ['type' => 'success', 'message' => count($selectedIds) . " colis ajoutes au voyage $numVoyage."];
            header("Location: manifest_complement.php?voyage=$voyageId");
            exit;
        }
    }
}

$userAgenceId = $user['agence_id'] ?? 0;
$isAgenceAdmin = in_array($user['role'], ['admin', 'superviseur']);

// Liste des voyages en cours
$voyagesEnCours = Database::fetchAll(
    "SELECT v.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
            COUNT(c.id) AS nb_colis, COALESCE(SUM(c.poids),0) AS total_poids,
            vh.immatriculation, vh.designation AS vehicule_designation
     FROM voyages v
     JOIN agences ad ON v.agence_depart_id=ad.id
     JOIN agences aa ON v.agence_arrivee_id=aa.id
     LEFT JOIN vehicules vh ON v.vehicule_id=vh.id
     LEFT JOIN colis c ON c.voyage_id=v.id
     WHERE v.statut IN ('en_cours','planifie')"
     . (!$isAgenceAdmin && $userAgenceId ? " AND (v.agence_depart_id = ? OR v.agence_arrivee_id = ? OR v.trajet_id IN (SELECT te.trajet_id FROM trajet_escales te WHERE te.agence_id=?))" : "")
     . " GROUP BY v.id
     ORDER BY v.date_depart DESC",
     !$isAgenceAdmin && $userAgenceId ? [$userAgenceId, $userAgenceId, $userAgenceId] : []
);

// Voyage selectionne
$selectedVoyageId = intval($_GET['voyage'] ?? 0);
$selectedVoyage = null;
$colisDuVoyage = [];
$colisDispo = [];

if ($selectedVoyageId) {
    $selectedVoyage = Database::fetchOne(
        "SELECT v.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
                ad.nom AS agence_depart_nom, aa.nom AS agence_arrivee_nom,
                vh.immatriculation, vh.designation AS vehicule_designation
         FROM voyages v
         JOIN agences ad ON v.agence_depart_id=ad.id
         JOIN agences aa ON v.agence_arrivee_id=aa.id
         LEFT JOIN vehicules vh ON v.vehicule_id=vh.id
         WHERE v.id=?", [$selectedVoyageId]
    );
    if (!$selectedVoyage) $selectedVoyageId = 0;
}

if ($selectedVoyageId && $selectedVoyage) {
    // Colis deja dans ce voyage
    $colisDuVoyage = Database::fetchAll(
        "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
         FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id
         JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.voyage_id=? AND c.statut NOT IN ('livre','retourne','perdu')
         ORDER BY c.created_at DESC", [$selectedVoyageId]
    );

    // Colis disponibles pour complement : a cette agence, statut enregistre, sans voyage
    $agArr = $selectedVoyage['agence_arrivee_id'];
    $myAgence = $isAgenceAdmin ? $selectedVoyage['agence_depart_id'] : $userAgenceId;
    $colisDispo = Database::fetchAll(
        "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
         FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id
         JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.statut = 'enregistre' AND c.voyage_id IS NULL
           AND c.agence_depart_id = ? AND c.agence_arrivee_id = ?
         ORDER BY c.created_at DESC",
        [$myAgence, $agArr]
    );

    // Colis en transit/en_livraison a cette agence (decharges d'un voyage ou en attente)
    $colisTransit = Database::fetchAll(
        "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
         FROM colis c
         JOIN agences ad ON c.agence_depart_id=ad.id
         JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.voyage_id IS NULL AND c.statut IN ('en_transit','en_livraison')
           AND (c.escale_actuelle_id = ? OR (c.agence_arrivee_id = ? AND c.statut = 'en_transit'))
           AND c.agence_depart_id != ?
         ORDER BY c.created_at DESC",
        [$myAgence, $myAgence, $myAgence]
    );
}
?>

<div class="d-flex justify-between align-center mb-3">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700"><i class="fas fa-boxes-stacked" style="color:var(--purple);margin-right:8px"></i>Manifest complément</h2>
        <p class="text-muted" style="font-size:.85rem">Ajouter des colis a un voyage en transit</p>
    </div>
    <a href="voyages.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error" style="margin-bottom:20px">
    <i class="fas fa-exclamation-triangle"></i>
    <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if (empty($voyagesEnCours)): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-truck" style="font-size:2rem;color:var(--text-muted)"></i>
        <h3>Aucun voyage actif</h3>
        <p>Il n'y a aucun voyage en cours ou planifie pour ajouter des colis.</p>
        <a href="manifest.php" class="btn btn-primary" style="margin-top:12px"><i class="fas fa-truck-loading"></i> Creer un nouveau manifest</a>
    </div>
</div>
<?php else: ?>

<!-- SELECTION DU VOYAGE -->
<div class="card mb-2">
    <div class="card-header"><div class="card-title"><i class="fas fa-route" style="margin-right:6px;color:var(--purple)"></i>Selection du voyage</div></div>
    <div style="padding:16px 20px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php foreach ($voyagesEnCours as $v): ?>
            <a href="?voyage=<?= $v['id'] ?>"
               class="voyage-card"
               style="display:block;padding:14px 18px;border-radius:var(--radius);border:2px solid <?= $selectedVoyageId==$v['id'] ? 'var(--purple)' : 'var(--border)' ?>;background:<?= $selectedVoyageId==$v['id'] ? 'rgba(124,58,237,0.06)' : 'var(--bg-card2)' ?>;text-decoration:none;color:var(--text);transition:all 0.2s;cursor:pointer">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-family:var(--font-mono);font-weight:700;font-size:.95rem;color:var(--purple)"><?= $v['numero_voyage'] ?></span>
                    <?= statutBadge($v['statut']) ?>
                </div>
                <div style="font-weight:600;font-size:.9rem;margin-bottom:4px">
                    <?= htmlspecialchars($v['nom_depart']) ?> → <?= htmlspecialchars($v['nom_arrivee']) ?>
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);display:flex;gap:16px;flex-wrap:wrap">
                    <span><i class="fas fa-truck" style="margin-right:4px"></i><?= htmlspecialchars($v['vehicule_designation'] ?? $v['transporteur'] ?? '—') ?></span>
                    <span><i class="fas fa-box" style="margin-right:4px"></i><?= $v['nb_colis'] ?> colis</span>
                    <span><i class="fas fa-weight-hanging" style="margin-right:4px"></i><?= number_format((float)$v['total_poids'],1,',','') ?> kg</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($selectedVoyageId && $selectedVoyage): ?>

<!-- COLIS DEJA CHARGES -->
<div class="card mb-2">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-box" style="margin-right:6px;color:var(--purple)"></i>Colis dans ce voyage</div>
        <div style="font-size:.85rem;color:var(--text-muted)"><?= count($colisDuVoyage) ?> colis</div>
    </div>
    <?php if (!empty($colisDuVoyage)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>N° Colis</th><th>Expediteur</th><th>Destinataire</th><th>Trajet</th><th>Poids</th><th>Montant</th><th>Statut</th>
            </tr></thead>
            <tbody>
            <?php foreach ($colisDuVoyage as $c): ?>
            <tr>
                <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                <td style="font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></td>
                <td style="font-size:.82rem"><?= htmlspecialchars($c['destinataire_nom']) ?></td>
                <td style="font-size:.8rem"><?= htmlspecialchars($c['nom_depart']) ?> → <?= htmlspecialchars($c['nom_arrivee']) ?></td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</td>
                <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                <td><?= statutBadge($c['statut']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.85rem">Aucun colis charge dans ce voyage</div>
    <?php endif; ?>
</div>

<!-- COLIS DISPONIBLES POUR COMPLEMENT -->
<?php if (!empty($colisDispo) || !empty($colisTransit)): ?>
<form method="post" id="complementForm">
    <input type="hidden" name="voyage_id" value="<?= $selectedVoyageId ?>">

    <?php if (!empty($colisDispo)): ?>
    <div class="card mb-2">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <div class="card-title"><i class="fas fa-plus-circle" style="margin-right:6px;color:var(--success)"></i>Colis sur le meme trajet (<?= count($colisDispo) ?>)</div>
            <button type="button" class="btn btn-sm" style="background:var(--success);color:#fff;font-size:.78rem;padding:5px 12px;border-radius:6px;border:none;cursor:pointer" onclick="toggleGroup('same',true)"><i class="fas fa-check-double"></i> Tout cocher</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" onchange="toggleGroup('same',this.checked)"></th>
                    <th>N° Colis</th><th>Expediteur</th><th>Destinataire</th><th>Poids</th><th>Montant</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($colisDispo as $c): ?>
                <tr>
                    <td><input type="checkbox" name="colis_ids[]" value="<?= $c['id'] ?>" class="colis-check-same" onchange="updateResume()"></td>
                    <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                    <td style="font-size:.82rem">
                        <div style="font-weight:600"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                    </td>
                    <td style="font-size:.82rem">
                        <div style="font-weight:600"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</td>
                    <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                    <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($c['date_expedition'],'d/m/Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($colisTransit)): ?>
    <div class="card mb-2">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <div class="card-title"><i class="fas fa-arrows-turn-right" style="margin-right:6px;color:var(--accent)"></i>Colis en transit (<?= count($colisTransit) ?>)</div>
            <button type="button" class="btn btn-sm" style="background:var(--accent);color:#fff;font-size:.78rem;padding:5px 12px;border-radius:6px;border:none;cursor:pointer" onclick="toggleGroup('other',true)"><i class="fas fa-check-double"></i> Tout cocher</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" onchange="toggleGroup('other',this.checked)"></th>
                    <th>N° Colis</th><th>Expediteur</th><th>Destinataire</th><th>Trajet</th><th>Poids</th><th>Montant</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($colisTransit as $c): ?>
                <tr>
                    <td><input type="checkbox" name="colis_ids[]" value="<?= $c['id'] ?>" class="colis-check-other" onchange="updateResume()"></td>
                    <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars($c['destinataire_nom']) ?></td>
                    <td style="font-size:.8rem"><?= htmlspecialchars($c['nom_depart']) ?> → <?= htmlspecialchars($c['nom_arrivee']) ?></td>
                    <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format((float)$c['poids'],2,',',' ') ?> kg</td>
                    <td style="font-family:var(--font-mono);font-size:.82rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                    <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($c['date_expedition'],'d/m/Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resume + bouton -->
    <div class="card">
        <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-top:2px solid var(--purple);background:rgba(124,58,237,0.04)">
            <div style="font-size:.85rem;color:var(--text-muted)">
                <span id="resumeCount">0</span> colis selectionne(s) —
                <span id="resumePoids" style="font-family:var(--font-mono)">0</span> kg —
                <span id="resumeMontant" style="font-family:var(--font-mono)">0</span> F
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="background:var(--purple)">
                <i class="fas fa-boxes-stacked"></i> Ajouter au voyage
            </button>
        </div>
    </div>
</form>

<?php else: ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-box-open" style="font-size:1.5rem;color:var(--text-muted)"></i>
        <h3>Aucun colis disponible</h3>
        <p>Il n'y a pas de colis enregistre sans voyage a affecter a ce trajet.</p>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

<script>
function toggleGroup(group, checked) {
    const cls = group === 'same' ? '.colis-check-same' : '.colis-check-other';
    document.querySelectorAll(cls).forEach(cb => cb.checked = checked);
    updateResume();
}

function updateResume() {
    let count = 0, poids = 0, montant = 0;
    document.querySelectorAll('input[name="colis_ids[]"]:checked').forEach(cb => {
        count++;
        const row = cb.closest('tr');
        const cells = row.querySelectorAll('td');
        poids += parseFloat(cells[cells.length - 3].textContent.replace(/[^\d,]/g, '').replace(',','.')) || 0;
        montant += parseInt(cells[cells.length - 2].textContent.replace(/[^\d]/g, '')) || 0;
    });
    document.getElementById('resumeCount').textContent = count;
    document.getElementById('resumePoids').textContent = poids.toFixed(2);
    document.getElementById('resumeMontant').textContent = montant.toLocaleString('fr-FR');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>