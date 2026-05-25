<?php
$pageTitle = 'Gestion des voyages';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$statut = $_GET['statut'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(v.numero_voyage LIKE ? OR v.transporteur LIKE ? OR v.matricule_vehicule LIKE ? OR vh.immatriculation LIKE ? OR vh.designation LIKE ? OR t.designation LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s, $s);
}
if ($statut) { $where[] = "v.statut=?"; $params[] = $statut; }

$whereStr = implode(' AND ', $where);
$total    = Database::fetchOne("SELECT COUNT(*) c FROM voyages v WHERE $whereStr", $params)['c'];
$totalPages = max(1, ceil($total / $perPage));
$offset   = ($page-1)*$perPage;

$voyages = Database::fetchAll(
    "SELECT v.*, ad.nom AS dep, ad.nom AS agence_dep, aa.nom AS arr, aa.nom AS agence_arr,
     COUNT(c.id) AS nb_colis, COALESCE(SUM(c.montant_total),0) AS ca_colis,
     CONCAT(u.prenom,' ',u.nom) AS createur,
     vh.immatriculation, vh.designation AS vehicule_designation,
     t.designation AS trajet_designation, t.numero_trajet
     FROM voyages v
     JOIN agences ad ON v.agence_depart_id=ad.id
     JOIN agences aa ON v.agence_arrivee_id=aa.id
     LEFT JOIN vehicules vh ON v.vehicule_id=vh.id
     LEFT JOIN trajets t ON v.trajet_id=t.id
     LEFT JOIN colis c ON c.voyage_id=v.id
     JOIN utilisateurs u ON v.cree_par=u.id
     WHERE $whereStr
     GROUP BY v.id
     ORDER BY v.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);
?>

<div class="filter-bar">
    <form method="get" style="display:contents">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Numéro, transporteur..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="statut" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            <?php foreach (['planifie','en_cours','arrive','annule'] as $s): ?>
            <option value="<?= $s ?>" <?= $statut===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i></button>
        <a href="voyages.php" class="btn btn-ghost"><i class="fas fa-times"></i></a>
    </form>
    <div style="margin-left:auto">
        <a href="nouveau_voyage.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau voyage</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Voyages (<?= number_format($total) ?>)</div>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>N° Voyage</th><th>Trajet</th><th>Véhicule</th><th>Chauffeur</th>
                <th>Départ</th><th>Colis</th><th>CA</th><th>Statut</th><th>Créé par</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($voyages as $v): ?>
            <tr>
                <td><span class="colis-num"><?= $v['numero_voyage'] ?></span></td>
                <td>
                    <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($v['dep']) ?> → <?= htmlspecialchars($v['arr']) ?></div>
                    <?php if (!empty($v['trajet_designation'])): ?>
                    <div style="font-size:.75rem;color:var(--accent)"><?= htmlspecialchars($v['trajet_designation']) ?></div>
                    <?php elseif (!empty($v['transporteur'])): ?>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($v['transporteur']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem">
                    <?php if (!empty($v['immatriculation'])): ?>
                        <span style="font-family:var(--font-mono);font-weight:600"><?= htmlspecialchars($v['immatriculation']) ?></span><br>
                        <span style="color:var(--text-muted)"><?= htmlspecialchars($v['vehicule_designation']) ?></span>
                    <?php elseif (!empty($v['matricule_vehicule'])): ?>
                        <span style="font-family:var(--font-mono)"><?= htmlspecialchars($v['matricule_vehicule']) ?></span><br>
                        <span style="color:var(--text-muted)"><?= htmlspecialchars($v['transporteur'] ?: '—') ?></span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem;color:var(--text-muted)">
                    <?= htmlspecialchars($v['chauffeur'] ?: '—') ?>
                </td>
                <td style="font-size:.8rem;white-space:nowrap"><?= formatDate($v['date_depart'],'d/m/Y H:i') ?></td>
                <td>
                    <span style="background:rgba(14,165,233,0.15);color:#38BDF8;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700">
                        <?= $v['nb_colis'] ?>
                    </span>
                </td>
                <td style="font-family:var(--font-mono);font-size:.8rem"><?= number_format($v['ca_colis'],0,',',' ') ?> F</td>
                <td><?= statutBadge($v['statut']) ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($v['createur']) ?></td>
                <td>
                    <div class="action-btns">
                        <a href="detail_voyage.php?id=<?= $v['id'] ?>" class="action-btn view" title="Détails"><i class="fas fa-eye"></i></a>
                        <a href="bordereau_print.php?voyage=<?= $v['id'] ?>" class="action-btn print" title="Bordereau" target="_blank"><i class="fas fa-print"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$voyages): ?>
            <tr><td colspan="10"><div class="empty-state"><i class="fas fa-route"></i><h3>Aucun voyage</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=<?=$i?>&statut=<?=$statut?>&search=<?=urlencode($search)?>"
           class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
