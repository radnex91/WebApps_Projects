<?php
$pageTitle = 'Liste des colis';
require_once __DIR__ . '/../includes/header.php';

$agenceFilter = Auth::getAgenceFilter();
$isAdmin = $agenceFilter && $agenceFilter['is_admin'];

// Filters
$search     = trim($_GET['search'] ?? '');
$statut     = $_GET['statut'] ?? '';
$type       = $_GET['type'] ?? '';
$agence     = intval($_GET['agence'] ?? 0);
$dateDebut  = $_GET['date_debut'] ?? '';
$dateFin    = $_GET['date_fin'] ?? '';
$page       = max(1, intval($_GET['page'] ?? 1));
$perPage    = 20;

// Build query
$where = ['1=1'];
$params = [];

// Filter by user's agency (non-admin only)
if (!$isAdmin && $agenceFilter && $agenceFilter['agence_id']) {
    $userAg = $agenceFilter['agence_id'];
    $where[] = "(
        (c.agence_depart_id = ? AND c.statut IN ('enregistre','en_transit','en_livraison'))
        OR 
        (c.agence_arrivee_id = ? AND c.statut IN ('en_transit','en_livraison','livre'))
        OR
        (c.statut = 'retourne' AND c.agence_arrivee_id = ?)
    )";
    array_push($params, $userAg, $userAg, $userAg);
}

if ($search) {
    $where[] = "(c.numero_colis LIKE ? OR c.expediteur_nom LIKE ? OR c.destinataire_nom LIKE ?
                 OR c.expediteur_telephone LIKE ? OR c.destinataire_telephone LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
}
if ($statut) { $where[] = "c.statut = ?"; $params[] = $statut; }
if ($type)   { $where[] = "c.type_expedition = ?"; $params[] = $type; }
if ($agence) { $where[] = "(c.agence_depart_id = ? OR c.agence_arrivee_id = ?)"; array_push($params, $agence, $agence); }
if ($dateDebut) { $where[] = "DATE(c.date_expedition) >= ?"; $params[] = $dateDebut; }
if ($dateFin)   { $where[] = "DATE(c.date_expedition) <= ?"; $params[] = $dateFin; }

$whereStr = implode(' AND ', $where);

$total = Database::fetchOne(
    "SELECT COUNT(*) c FROM colis c WHERE $whereStr", $params
)['c'];

$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$colis = Database::fetchAll(
    "SELECT c.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
     FROM colis c
     JOIN agences ad ON c.agence_depart_id=ad.id
     JOIN agences aa ON c.agence_arrivee_id=aa.id
     WHERE $whereStr
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);
$agences = getAgences();
?>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Colis enregistrés</div>
            <div class="card-subtitle"><?= number_format($total) ?> colis trouvés</div>
        </div>
        <div style="display:flex;gap:10px;margin-left:auto">
            <?php if (Auth::hasPermission('colis_creer')): ?>
            <a href="nouveau_colis.php?type=normal" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter expedition</a>
            <a href="nouveau_colis.php?type=accompagne" class="btn btn-secondary"><i class="fas fa-person-walking-luggage"></i> Ajouter accompagnent</a>
            <?php endif; ?>
            <a href="bordereaux.php" class="btn btn-ghost"><i class="fas fa-file-invoice"></i> Bordereau</a>
        </div>
    </div>
    <form method="get" id="filterForm">
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" id="tableSearch" class="form-control"
                   placeholder="Rechercher N°, nom, téléphone..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="statut" class="form-control" style="width:auto" onchange="document.getElementById('filterForm').submit()">
            <option value="">Tous statuts</option>
            <?php foreach (['enregistre','en_transit','en_livraison','livre','retourne','perdu'] as $s): ?>
            <option value="<?= $s ?>" <?= $statut===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-control" style="width:auto" onchange="document.getElementById('filterForm').submit()">
            <option value="">Tous types</option>
            <option value="normal" <?= $type==='normal'?'selected':'' ?>>Expédition</option>
            <option value="accompagne" <?= $type==='accompagne'?'selected':'' ?>>Accompagné</option>
        </select>
        <?php if ($isAdmin): ?>
        <select name="agence" class="form-control" style="width:auto" onchange="document.getElementById('filterForm').submit()">
            <option value="">Toutes agences</option>
            <?php foreach ($agences as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $agence==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="date" name="date_debut" class="form-control" style="width:auto" value="<?= $dateDebut ?>" placeholder="Du">
        <input type="date" name="date_fin"   class="form-control" style="width:auto" value="<?= $dateFin ?>" placeholder="Au">
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filtrer</button>
        <a href="colis.php" class="btn btn-ghost"><i class="fas fa-times"></i> Reset</a>
    </div>
    </form>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr>
                <th>N° Colis</th>
                <th>Type</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Trajet</th>
                <th>Description</th>
                <th>Montant</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($colis as $c): ?>
            <tr class="<?= isset($_GET['highlight']) && $_GET['highlight']==$c['id'] ? 'highlighted' : '' ?>">
                <td>
                    <span class="colis-num"><?= $c['numero_colis'] ?></span>
                </td>
                <td><?= statutBadge($c['type_expedition']) ?></td>
                <td>
                    <div style="font-weight:500;font-size:.85rem"><?= htmlspecialchars($c['expediteur_nom']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($c['expediteur_telephone']) ?></div>
                </td>
                <td>
                    <div style="font-weight:500;font-size:.85rem"><?= htmlspecialchars($c['destinataire_nom']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($c['destinataire_telephone']) ?></div>
                </td>
                <td style="font-size:.8rem">
                    <div><?= htmlspecialchars($c['nom_depart']) ?></div>
                    <div style="color:var(--primary)">↓</div>
                    <div><?= htmlspecialchars($c['nom_arrivee']) ?></div>
                </td>
                <td style="font-size:.82rem;max-width:160px">
                    <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($c['description']) ?>
                    </div>
                    <?php if ($c['poids'] > 0): ?>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= $c['poids'] ?>kg — <?= $c['nombre_pieces'] ?> pce(s)</div>
                    <?php endif; ?>
                </td>
                <td style="font-family:var(--font-mono);font-size:.82rem;white-space:nowrap">
                    <?= number_format($c['montant_total'],0,',',' ') ?> F
                </td>
                <td><?= statutBadge($c['statut']) ?></td>
                <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap">
                    <?= formatDate($c['date_expedition'],'d/m/Y') ?>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="detail_colis.php?id=<?= $c['id'] ?>" class="action-btn view" title="Voir"><i class="fas fa-eye"></i></a>
                        <a href="bordereau_print.php?id=<?= $c['id'] ?>" class="action-btn print" title="Bordereau" target="_blank"><i class="fas fa-print"></i></a>
                        <a href="edit_colis.php?id=<?= $c['id'] ?>" class="action-btn edit" title="Modifier"><i class="fas fa-pencil"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$colis): ?>
            <tr><td colspan="10">
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Aucun colis trouvé</h3>
                    <p>Modifiez vos filtres ou enregistrez un nouveau colis</p>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>max(1,$page-1)])) ?>"
           class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"
           class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>min($totalPages,$page+1)])) ?>"
           class="page-btn <?= $page>=$totalPages?'disabled':'' ?>"><i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<style>
tr.highlighted td { background: rgba(232,80,10,0.08) !important; border-color: rgba(232,80,10,0.2) !important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
