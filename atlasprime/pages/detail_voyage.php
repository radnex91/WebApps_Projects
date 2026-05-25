<?php
$pageTitle = 'Détail voyage';
require_once __DIR__ . '/../includes/header.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: voyages.php'); exit; }

// POST : actions du cycle de vie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Auth::requirePermission('voyage_avancer');
    $action = $_POST['action'];
    $escaleId = intval($_POST['escale_id'] ?? 0);
    transitionVoyageStatut($id, $action, $escaleId ?: null, $user['id']);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Voyage mis à jour.'];
    header("Location: detail_voyage.php?id=$id");
    exit;
}

$voyage = Database::fetchOne(
    "SELECT v.*, ad.nom agd, ad.nom vd, aa.nom aga, aa.nom va,
            CONCAT(u.prenom,' ',u.nom) AS operateur,
            vh.immatriculation, vh.designation AS vehicule_designation,
            t.designation AS trajet_designation, t.numero_trajet
     FROM voyages v JOIN agences ad ON v.agence_depart_id=ad.id JOIN agences aa ON v.agence_arrivee_id=aa.id
     JOIN utilisateurs u ON v.cree_par=u.id
     LEFT JOIN vehicules vh ON v.vehicule_id=vh.id
     LEFT JOIN trajets t ON v.trajet_id=t.id
     WHERE v.id=?", [$id]
);
if (!$voyage) { header('Location: voyages.php'); exit; }

// Escales du trajet
$escales = [];
if (!empty($voyage['trajet_id'])) {
    $escales = Database::fetchAll(
        "SELECT te.*, a.nom FROM trajet_escales te JOIN agences a ON te.agence_id=a.id
         WHERE te.trajet_id=? ORDER BY te.ordre", [$voyage['trajet_id']]
    );
}

// Determiner quelles escales ont ete visitees (via suivi_colis)
$escalesVisitees = [];
if ($voyage['statut'] === 'en_cours') {
    $visited = Database::fetchAll(
        "SELECT DISTINCT localisation FROM suivi_colis
         WHERE colis_id IN (SELECT id FROM colis WHERE voyage_id=?)
         AND commentaire LIKE '%escale%'", [$id]
    );
    foreach ($visited as $v) {
        $escalesVisitees[] = $v['localisation'];
    }
}

// Colis du voyage + colis decharges
$colis = Database::fetchAll(
    "SELECT c.*, aa.nom va FROM colis c JOIN agences aa ON c.agence_arrivee_id=aa.id
     WHERE c.voyage_id=? ORDER BY c.created_at", [$id]
);

$colisDecharges = Database::fetchAll(
    "SELECT c.*, aa.nom va FROM colis c JOIN agences aa ON c.agence_arrivee_id=aa.id
     WHERE c.escale_actuelle_id=? AND c.voyage_id IS NULL AND c.statut IN ('en_transit','en_livraison')
     ORDER BY c.created_at", [$voyage['agence_arrivee_id']]
);

$totaux = Database::fetchOne(
    "SELECT COUNT(*) nb, COALESCE(SUM(montant_total),0) ca, COALESCE(SUM(poids),0) pds
     FROM colis WHERE voyage_id=?", [$id]
);

// Escales restantes pour le formulaire d'arrivee
$escalesRestantes = [];
if ($voyage['statut'] === 'en_cours') {
    foreach ($escales as $esc) {
        $dejaVisitee = ($voyage['escale_actuelle_id'] && $esc['agence_id'] == $voyage['escale_actuelle_id']);
        // On garde les escales apres la position actuelle
        if ($voyage['escale_actuelle_id']) {
            $currentOrdre = 0;
            foreach ($escales as $e2) { if ($e2['agence_id'] == $voyage['escale_actuelle_id']) $currentOrdre = $e2['ordre']; }
            if ($esc['ordre'] > $currentOrdre) $escalesRestantes[] = $esc;
        } else {
            $escalesRestantes[] = $esc;
        }
    }
}
?>

<div style="display:flex;gap:12px;align-items:center;margin-bottom:24px">
    <a href="voyages.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    <h2 style="font-size:1.2rem;font-weight:700;flex:1">Voyage <span class="colis-num"><?= $voyage['numero_voyage'] ?></span></h2>
    <?= statutBadge($voyage['statut']) ?>
    <a href="bordereau_print.php?voyage=<?= $id ?>" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-print"></i> Manifeste
    </a>
</div>

<!-- ACTIONS DU CYCLE DE VIE -->
<?php if (Auth::hasPermission('voyage_avancer')): ?>
<?php if ($voyage['statut'] === 'planifie'): ?>
<form method="post" style="margin-bottom:20px">
    <input type="hidden" name="action" value="demarrer">
    <button type="submit" class="btn btn-primary btn-lg w-100" data-confirm="Demarrer ce voyage ?">
        <i class="fas fa-play"></i> Demarrer le voyage
    </button>
</form>
<?php elseif ($voyage['statut'] === 'en_cours'): ?>
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <?php if (!empty($escalesRestantes)): ?>
    <form method="post" style="flex:1;min-width:200px">
        <input type="hidden" name="action" value="arrivee_escale">
        <div style="display:flex;gap:8px">
            <select name="escale_id" class="form-control" required>
                <option value="">— Arrivée escale —</option>
                <?php foreach ($escalesRestantes as $esc): ?>
                <option value="<?= $esc['agence_id'] ?>"><?= htmlspecialchars($esc['nom']) ?><?php if ($esc['duree_arret']>0): ?> (<?= $esc['duree_arret'] ?>min)<?php endif; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" data-confirm="Confirmer l'arrivée à cette escale ?">
                <i class="fas fa-map-pin"></i> Arrivée escale
            </button>
        </div>
    </form>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="arrivee_finale">
        <button type="submit" class="btn btn-success btn-lg" data-confirm="Confirmer l'arrivée finale ?">
            <i class="fas fa-flag-checkered"></i> Arrivée finale
        </button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="annuler">
        <button type="submit" class="btn btn-danger" data-confirm="Annuler ce voyage ? Les colis retourneront à enregistré.">
            <i class="fas fa-times"></i> Annuler
        </button>
    </form>
</div>
<?php elseif ($voyage['statut'] === 'planifie' || $voyage['statut'] === 'en_cours'): ?>
<form method="post" style="margin-bottom:20px">
    <input type="hidden" name="action" value="annuler">
    <button type="submit" class="btn btn-danger" data-confirm="Annuler ce voyage ?">
        <i class="fas fa-times"></i> Annuler
    </button>
</form>
<?php endif; ?>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px">
    <!-- Info voyage -->
    <div>
        <div class="card mb-2">
            <div class="card-header"><div class="card-title"><i class="fas fa-route" style="margin-right:6px;color:var(--primary)"></i>Trajet</div></div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <div>
                    <div style="font-size:.72rem;color:var(--text-muted)">Départ</div>
                    <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($voyage['vd']) ?></div>
                    <div style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($voyage['date_depart'],'d/m/Y H:i') ?></div>
                </div>
                <?php foreach ($escales as $esc):
                    $isCurrent = ($voyage['escale_actuelle_id'] && $esc['agence_id'] == $voyage['escale_actuelle_id']);
                    $isVisited = in_array($esc['nom'], $escalesVisitees) || $isCurrent;
                ?>
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="flex:1;height:2px;background:<?= $isVisited ? 'var(--success)' : 'var(--accent)' ?>"></div>
                    <i class="fas fa-<?= $isCurrent ? 'circle-dot' : ($isVisited ? 'check-circle' : 'map-pin') ?>" style="color:<?= $isCurrent ? 'var(--success)' : ($isVisited ? 'var(--success)' : 'var(--accent)') ?>"></i>
                    <div style="flex:1;height:2px;background:<?= $isVisited ? 'var(--success)' : 'var(--accent)' ?>"></div>
                </div>
                <div style="<?= $isCurrent ? 'background:rgba(34,197,94,0.08);padding:8px;border-radius:8px;border-left:3px solid var(--success)' : '' ?>">
                    <div style="font-size:.72rem;color:<?= $isCurrent ? 'var(--success)' : 'var(--accent)' ?>"><?= $isCurrent ? 'Position actuelle' : 'Escale' ?></div>
                    <div style="font-weight:700;font-size:.95rem;color:<?= $isVisited ? 'var(--success)' : 'var(--accent)' ?>"><?= htmlspecialchars($esc['nom']) ?></div>
                    <?php if ($esc['duree_arret'] > 0): ?>
                    <div style="font-size:.75rem;color:var(--text-muted)">Arrêt: <?= $esc['duree_arret'] ?> min</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="flex:1;height:2px;background:var(--primary)"></div>
                    <i class="fas fa-truck" style="color:var(--primary)"></i>
                    <div style="flex:1;height:2px;background:var(--primary)"></div>
                </div>
                <div>
                    <div style="font-size:.72rem;color:var(--text-muted)">Arrivée</div>
                    <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($voyage['va']) ?></div>
                    <?php if ($voyage['date_arrivee_prevue']): ?>
                    <div style="font-size:.8rem;color:var(--text-muted)">Prévue: <?= formatDate($voyage['date_arrivee_prevue'],'d/m/Y H:i') ?></div>
                    <?php endif; ?>
                    <?php if ($voyage['date_derniere_escale']): ?>
                    <div style="font-size:.8rem;color:var(--success)">Dernière escale: <?= formatDate($voyage['date_derniere_escale'],'d/m/Y H:i') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($voyage['trajet_designation'])): ?>
            <div style="margin-top:8px;font-size:.8rem;color:var(--accent);border-top:1px solid var(--border);padding-top:8px">
                <i class="fas fa-route" style="margin-right:4px"></i><?= htmlspecialchars($voyage['trajet_designation']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card mb-2">
            <div class="card-header"><div class="card-title"><i class="fas fa-truck" style="margin-right:6px;color:var(--primary)"></i>Véhicule</div></div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
                <?php if (!empty($voyage['immatriculation'])): ?>
                <div><span style="color:var(--text-muted)">Véhicule:</span> <strong><?= htmlspecialchars($voyage['vehicule_designation']) ?></strong></div>
                <div><span style="color:var(--text-muted)">Immatriculation:</span> <span style="font-family:var(--font-mono)"><?= htmlspecialchars($voyage['immatriculation']) ?></span></div>
                <?php elseif (!empty($voyage['transporteur'])): ?>
                <div><span style="color:var(--text-muted)">Compagnie:</span> <strong><?= htmlspecialchars($voyage['transporteur']) ?></strong></div>
                <?php if ($voyage['matricule_vehicule']): ?><div><span style="color:var(--text-muted)">Matricule:</span> <span style="font-family:var(--font-mono)"><?= htmlspecialchars($voyage['matricule_vehicule']) ?></span></div><?php endif; ?>
                <?php else: ?>
                <div style="color:var(--text-muted)">—</div>
                <?php endif; ?>
                <?php if ($voyage['chauffeur']): ?><div><span style="color:var(--text-muted)">Chauffeur:</span> <?= htmlspecialchars($voyage['chauffeur']) ?></div><?php endif; ?>
                <?php if ($voyage['telephone_chauffeur']): ?><div><span style="color:var(--text-muted)">Tél:</span> <?= htmlspecialchars($voyage['telephone_chauffeur']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Statistiques</div></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center">
                    <div style="font-size:1.4rem;font-weight:700;font-family:var(--font-mono);color:var(--primary)"><?= $totaux['nb'] ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)">Colis</div>
                </div>
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center">
                    <div style="font-size:1.4rem;font-weight:700;font-family:var(--font-mono);color:var(--accent)"><?= number_format($totaux['pds'],1) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)">kg</div>
                </div>
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center;grid-column:span 2">
                    <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-mono);color:#4ADE80"><?= number_format($totaux['ca'],0,',',' ') ?> F</div>
                    <div style="font-size:.72rem;color:var(--text-muted)">Chiffre d'affaires</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Colis du voyage -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-boxes-stacked" style="margin-right:6px;color:var(--primary)"></i>Colis du voyage (<?= count($colis) ?>)</div>
                <?php if ($voyage['statut'] !== 'arrive' && $voyage['statut'] !== 'annule'): ?>
                <a href="manifest_complement.php?voyage=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter des colis</a>
                <?php endif; ?>
            </div>
            <?php if ($colis): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr>
                        <th>N° Colis</th><th>Expéditeur</th><th>Destinataire</th><th>Description</th>
                        <th>Poids</th><th>Montant</th><th>Statut</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($colis as $c): ?>
                    <tr>
                        <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                        <td style="font-size:.82rem">
                            <div style="font-weight:500"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                        </td>
                        <td style="font-size:.82rem">
                            <div style="font-weight:500"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                            <div style="color:var(--text-muted)"><?= htmlspecialchars($c['va']) ?></div>
                        </td>
                        <td style="font-size:.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['description']) ?></td>
                        <td style="font-family:var(--font-mono);font-size:.8rem"><?= $c['poids'] ?>kg</td>
                        <td style="font-family:var(--font-mono);font-size:.8rem"><?= number_format($c['montant_total'],0,',',' ') ?> F</td>
                        <td><?= statutBadge($c['statut']) ?></td>
                        <td>
                            <a href="detail_colis.php?id=<?= $c['id'] ?>" class="action-btn view"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucun colis dans ce voyage</h3>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($colisDecharges)): ?>
        <div class="card" style="margin-top:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-boxes-stacked" style="margin-right:6px;color:var(--accent)"></i>Colis déchargés en attente (<?= count($colisDecharges) ?>)</div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr>
                        <th>N° Colis</th><th>Expéditeur</th><th>Destination</th><th>Statut</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($colisDecharges as $c): ?>
                    <tr>
                        <td><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                        <td style="font-size:.82rem"><?= htmlspecialchars($c['expediteur_nom']) ?></td>
                        <td style="font-size:.82rem"><?= htmlspecialchars($c['va']) ?></td>
                        <td><?= statutBadge($c['statut']) ?></td>
                        <td><a href="detail_colis.php?id=<?= $c['id'] ?>" class="action-btn view"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>