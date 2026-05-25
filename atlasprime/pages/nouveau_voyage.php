<?php
$pageTitle = 'Nouveau voyage';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('voyages_gerer');

$agences = getAgences();
$vehicules = getVehiculesDisponibles();
$userAgenceId = $user['agence_id'] ?? null;
$isAgenceAdmin = in_array($user['role'], ['admin', 'superviseur']);
$trajetsActifs = $userAgenceId && !$isAgenceAdmin
    ? Database::fetchAll(
        "SELECT t.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
         FROM trajets t
         JOIN agences ad ON t.agence_depart_id = ad.id
         JOIN agences aa ON t.agence_arrivee_id = aa.id
         WHERE t.actif = 1 AND t.agence_depart_id = ?
         ORDER BY ad.nom, aa.nom", [$userAgenceId])
    : getTrajetsActifs();
// Fetch escales for each trajet
foreach ($trajetsActifs as &$t) {
    $t['escales'] = Database::fetchAll(
        "SELECT te.ordre, te.duree_arret, a.id AS agence_id, a.nom
         FROM trajet_escales te JOIN agences a ON te.agence_id = a.id
         WHERE te.trajet_id = ? ORDER BY te.ordre", [$t['id']]
    );
}
unset($t);
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['vehicule_id','trajet_id','date_depart'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) $errors[] = "Le champ $f est requis.";
    }

    if (!$errors) {
        $trajet = Database::fetchOne("SELECT * FROM trajets WHERE id=?", [intval($_POST['trajet_id'])]);
        $vehicule = Database::fetchOne("SELECT * FROM vehicules WHERE id=?", [intval($_POST['vehicule_id'])]);
        if (!$trajet || !$vehicule) {
            $errors[] = "Trajet ou vehicule invalide.";
        }
        // Verifier que le trajet part de l'agence de l'operateur
        if ($trajet && $userAgenceId && !$isAgenceAdmin && $trajet['agence_depart_id'] != $userAgenceId) {
            $errors[] = "Le trajet doit partir de votre agence.";
        }
        // Verifier que le vehicule n'est pas deja dans un voyage en cours
        $voyageActif = Database::fetchOne(
            "SELECT v.numero_voyage FROM voyages v WHERE v.vehicule_id=? AND v.statut IN ('planifie','en_cours')",
            [intval($_POST['vehicule_id'])]
        );
        if ($voyageActif) {
            $errors[] = "Ce vehicule est deja en voyage ({$voyageActif['numero_voyage']}). Attendez l'arrivee ou declarez-le en panne.";
        }
    }

    if (!$errors) {
        $num = genererNumeroVoyage();
        $id  = Database::insert(
            "INSERT INTO voyages (numero_voyage,vehicule_id,trajet_id,transporteur,matricule_vehicule,chauffeur,telephone_chauffeur,
             agence_depart_id,agence_arrivee_id,date_depart,date_arrivee_prevue,notes,cree_par)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $num,
                intval($_POST['vehicule_id']),
                intval($_POST['trajet_id']),
                $vehicule['designation'],
                $vehicule['immatriculation'],
                trim($_POST['chauffeur'] ?? ''),
                trim($_POST['telephone_chauffeur'] ?? ''),
                $trajet['agence_depart_id'],
                $trajet['agence_arrivee_id'],
                $_POST['date_depart'],
                !empty($_POST['date_arrivee_prevue']) ? $_POST['date_arrivee_prevue'] : null,
                trim($_POST['notes'] ?? ''),
                $user['id']
            ]
        );
        Auth::logAction('VOYAGE_CREE','voyages',$id,"N°: $num");
        $_SESSION['flash'] = ['type'=>'success','message'=>"Voyage $num créé !"];
        header("Location: voyages.php"); exit;
    }
}
?>
<div style="max-width:700px">
    <div class="d-flex justify-between align-center mb-3">
        <h2 style="font-size:1.3rem;font-weight:700">Créer un voyage</h2>
        <a href="voyages.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <?php if ($errors): ?>
    <div class="flash flash-error" style="margin-bottom:20px">
        <?php foreach ($errors as $e): ?><div><i class="fas fa-times"></i> <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post">
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-truck"></i> Vehicule & Trajet</div>
            <?php if ($userAgenceId && !$isAgenceAdmin): ?>
            <?php $userAgence = Database::fetchOne("SELECT id, nom FROM agences WHERE id=?", [$userAgenceId]); ?>
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label">Agence de départ</label>
                <div style="background:var(--bg-card2);border:1px solid var(--border-strong);border-radius:8px;padding:10px 14px;font-weight:600">
                    <i class="fas fa-building" style="color:var(--primary);margin-right:6px"></i><?= htmlspecialchars($userAgence['nom'] ?? '') ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Vehicule <span class="req">*</span></label>
                    <select name="vehicule_id" class="form-control" required>
                        <option value="">-- Selectionner un vehicule --</option>
                        <?php foreach ($vehicules as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['immatriculation']) ?> — <?= htmlspecialchars($v['designation']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Trajet <span class="req">*</span></label>
                    <select name="trajet_id" class="form-control" required id="trajet_select">
                        <option value="">-- Selectionner un trajet --</option>
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
                    <input type="text" name="chauffeur" class="form-control"
                           placeholder="Nom du chauffeur" value="<?= htmlspecialchars($_POST['chauffeur']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone chauffeur</label>
                    <input type="tel" name="telephone_chauffeur" class="form-control"
                           placeholder="+237..." value="<?= htmlspecialchars($_POST['telephone_chauffeur']??'') ?>">
                </div>
            </div>
            <!-- Escales display -->
            <div id="trajet-escales-display" style="display:none;margin-top:12px;padding:10px 14px;background:var(--bg-card2);border-radius:8px;border-left:3px solid var(--accent)">
                <div style="font-size:.85rem;font-weight:600;margin-bottom:6px"><i class="fas fa-map-pin" style="color:var(--accent);margin-right:6px"></i>Itineraire du trajet</div>
                <div id="escales-list" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap"></div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-section-title"><i class="fas fa-map-signs"></i> Horaire</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Date et heure de départ <span class="req">*</span></label>
                    <input type="datetime-local" name="date_depart" class="form-control" required
                           value="<?= htmlspecialchars($_POST['date_depart']??date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Arrivée prévue</label>
                    <input type="datetime-local" name="date_arrivee_prevue" class="form-control"
                           value="<?= htmlspecialchars($_POST['date_arrivee_prevue']??'') ?>">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" style="min-height:50px"><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:12px">
            <a href="voyages.php" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Créer le voyage</button>
        </div>
    </form>
</div>
<script>
document.getElementById('trajet_select').addEventListener('change', function() {
    const display = document.getElementById('trajet-escales-display');
    const list = document.getElementById('escales-list');
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
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>