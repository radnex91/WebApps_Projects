<?php
$page_title = 'Tableau de bord';
$page_id = 'dashboard';
require_once '../includes/header.php';

$db = getDB();

// Stats
$total_produits     = $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn();
$total_stock_val    = $db->query("SELECT SUM(quantite*prix_achat) FROM produits WHERE actif=1")->fetchColumn() ?: 0;
$total_stock_vente  = $db->query("SELECT SUM(quantite*prix_vente) FROM produits WHERE actif=1")->fetchColumn() ?: 0;
$stock_faible       = $db->query("SELECT COUNT(*) FROM produits WHERE quantite <= quantite_min AND actif=1")->fetchColumn();
$rupture            = $db->query("SELECT COUNT(*) FROM produits WHERE quantite=0 AND actif=1")->fetchColumn();
$total_fournisseurs = $db->query("SELECT COUNT(*) FROM fournisseurs WHERE actif=1")->fetchColumn();
$mouvements_today   = $db->query("SELECT COUNT(*) FROM mouvements WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$mouvements_mois    = $db->query("SELECT COUNT(*) FROM mouvements WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Mouvements 7 derniers jours (pour graphique)
$mvt_7j = $db->query("
    SELECT DATE(created_at) as jour,
           SUM(CASE WHEN type='entree' THEN quantite ELSE 0 END) as entrees,
           SUM(CASE WHEN type='sortie' THEN quantite ELSE 0 END) as sorties
    FROM mouvements
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY jour
")->fetchAll();

// Remplir les jours manquants
$chart_labels = [];
$chart_entrees = [];
$chart_sorties = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($day));
    $found_e = 0; $found_s = 0;
    foreach ($mvt_7j as $m) {
        if ($m['jour'] === $day) { $found_e = $m['entrees']; $found_s = $m['sorties']; break; }
    }
    $chart_entrees[] = (int)$found_e;
    $chart_sorties[] = (int)$found_s;
}

// Derniers mouvements
$derniers_mvt = $db->query("
    SELECT m.*, p.nom as produit_nom, p.reference, u.prenom, u.nom as user_nom
    FROM mouvements m
    JOIN produits p ON m.produit_id=p.id
    JOIN utilisateurs u ON m.utilisateur_id=u.id
    ORDER BY m.created_at DESC LIMIT 8
")->fetchAll();

// Produits stock faible
$alertes_produits = $db->query("
    SELECT p.*, c.nom as cat_nom, c.couleur as cat_couleur
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id=c.id
    WHERE p.quantite <= p.quantite_min AND p.actif=1
    ORDER BY p.quantite ASC LIMIT 6
")->fetchAll();

// Top produits (valeur stock)
$top_produits = $db->query("
    SELECT p.nom, p.quantite, p.prix_vente, (p.quantite*p.prix_vente) as valeur_totale, c.couleur
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id=c.id
    WHERE p.actif=1 ORDER BY valeur_totale DESC LIMIT 5
")->fetchAll();
$max_val = $top_produits[0]['valeur_totale'] ?? 1;
$marge_potentielle = $total_stock_vente - $total_stock_val;
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div>
            <div class="stat-val"><?= number_format($total_produits) ?></div>
            <div class="stat-label">Produits actifs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-val" style="font-size:17px"><?= formatMoney($total_stock_val) ?></div>
            <div class="stat-label">Valeur stock (achat)</div>
            <div style="font-size:12px;color:var(--success);margin-top:2px">Marge potentielle: <?= formatMoney($marge_potentielle) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <div class="stat-val"><?= $stock_faible ?></div>
            <div class="stat-label">Stock faible</div>
            <?php if ($rupture > 0): ?>
            <div class="stat-badge badge-red"><?= $rupture ?> en rupture</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </div>
        <div>
            <div class="stat-val"><?= $mouvements_mois ?></div>
            <div class="stat-label">Mouvements ce mois</div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= $mouvements_today ?> aujourd'hui</div>
        </div>
    </div>
</div>

<!-- Graphique + alertes -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:20px">
    <!-- Graphique 7 jours -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Activité — 7 derniers jours</div>
            <div style="display:flex;align-items:center;gap:12px;font-size:12px">
                <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;border-radius:3px;background:var(--success);display:inline-block"></span>Entrées</span>
                <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;border-radius:3px;background:var(--danger);display:inline-block"></span>Sorties</span>
            </div>
        </div>
        <div class="card-body">
            <canvas id="chart-mvt" height="160"></canvas>
        </div>
    </div>

    <!-- Alertes stock -->
    <div class="card">
        <div class="card-header">
            <div class="card-title" style="color:var(--warning)">
                ⚠️ Alertes stock (<?= count($alertes_produits) ?>)
            </div>
            <?php if (!empty($alertes_produits)): ?>
            <a href="<?= BASE_URL ?>/pages/exports.php" class="btn btn-secondary btn-sm">Voir tout</a>
            <?php endif; ?>
        </div>
        <div style="max-height:300px;overflow-y:auto">
            <?php if (empty($alertes_produits)): ?>
            <div style="padding:20px;text-align:center;color:var(--success);font-size:13px">✅ Tout est OK</div>
            <?php else: ?>
            <?php foreach ($alertes_produits as $p): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-bottom:1px solid var(--border)">
                <div>
                    <div style="font-size:13px;font-weight:600"><?= htmlspecialchars(mb_substr($p['nom'],0,24,'UTF-8')) ?></div>
                    <div style="font-size:11px;color:var(--muted)"><?= $p['reference'] ?></div>
                </div>
                <a href="<?= BASE_URL ?>/pages/mouvements.php?produit=<?= $p['id'] ?>" class="badge <?= $p['quantite']==0?'badge-red':'badge-orange' ?>" style="text-decoration:none">
                    <?= $p['quantite']==0 ? 'Rupture' : $p['quantite'].' '.$p['unite'] ?>
                </a>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mouvements + Top produits -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Derniers mouvements</div>
            <a href="<?= BASE_URL ?>/pages/mouvements.php" class="btn btn-secondary btn-sm">Voir tout</a>
        </div>
        <div style="overflow-x:auto">
            <?php if (empty($derniers_mvt)): ?>
            <div class="empty-state"><p>Aucun mouvement</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>Produit</th><th>Type</th><th>Qté</th><th>Par</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($derniers_mvt as $m):
                    $types=['entree'=>['Entrée','badge-green'],'sortie'=>['Sortie','badge-red'],'ajustement'=>['Ajust.','badge-blue'],'retour'=>['Retour','badge-orange']];
                    $t=$types[$m['type']]??[$m['type'],'badge-gray'];
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars(mb_substr($m['produit_nom'],0,28,'UTF-8')) ?></div>
                        <div style="font-size:11px;color:var(--muted)"><?= $m['reference'] ?></div>
                    </td>
                    <td><span class="badge <?= $t[1] ?>"><?= $t[0] ?></span></td>
                    <td style="font-weight:700;color:<?= in_array($m['type'],['entree','retour'])?'var(--success)':'var(--danger)' ?>">
                        <?= in_array($m['type'],['entree','retour'])?'+':'-' ?><?= $m['quantite'] ?>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($m['prenom']) ?></td>
                    <td style="font-size:12px;color:var(--muted)"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title">Top valeur stock</div></div>
        <div class="card-body">
            <?php foreach ($top_produits as $p): ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px"><?= htmlspecialchars($p['nom']) ?></span>
                    <span style="color:var(--muted);font-size:12px;flex-shrink:0;margin-left:4px"><?= formatMoney($p['valeur_totale']) ?></span>
                </div>
                <div class="progress">
                    <div class="progress-fill" style="width:<?= round($p['valeur_totale']/$max_val*100) ?>%;background:<?= $p['couleur']??'var(--primary)' ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
                <a href="<?= BASE_URL ?>/pages/rapports.php" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center">Voir les rapports →</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('chart-mvt');
if (ctx) {
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: 'Entrées',
                    data: <?= json_encode($chart_entrees, JSON_UNESCAPED_UNICODE) ?>,
                    backgroundColor: 'rgba(16,185,129,.7)',
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Sorties',
                    data: <?= json_encode($chart_sorties, JSON_UNESCAPED_UNICODE) ?>,
                    backgroundColor: 'rgba(239,68,68,.7)',
                    borderRadius: 6,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } }, beginAtZero: true }
            }
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
