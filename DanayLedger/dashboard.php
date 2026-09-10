<?php
$pageTitle = 'Tableau de bord';
$loadChart = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$db = getDB();
$isAdmin = hasPermission('admin');
$userId = (int)$_SESSION['user_id'];
$userPerms = $_SESSION['user_permissions'] ?? [];
$userRole = $_SESSION['user_role'] ?? '';

// Stats : admin voit tout, les autres voient leurs propres données
$stats = getDashboardStats($db, $isAdmin ? null : $userId);

// Données graphiques mensuels (filtrées par utilisateur si non-admin)
$monthLabels = [];
$recettesData = [];
$depensesData = [];
for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");
    $monthLabels[] = $date->format('M Y');
    $mStart = $date->format('Y-m-01');
    $mEnd = $date->format('Y-m-t');

    $userSql = $isAdmin ? '' : ' AND created_by = ' . $userId;

    $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement),0) FROM recette WHERE date BETWEEN ? AND ? AND statut='validee'" . $userSql);
    $stmt->execute([$mStart, $mEnd]);
    $recettesData[] = (float) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM depenses WHERE date_depense BETWEEN ? AND ? AND statut='validee'" . $userSql);
    $stmt->execute([$mStart, $mEnd]);
    $depensesData[] = (float) $stmt->fetchColumn();
}

// Données supplémentaires pour admin (comparaison globale vs perso)
$globalStats = null;
if ($isAdmin) {
    $globalStats = $stats; // déjà global
} else {
    $globalStats = getDashboardStats($db, null); // stats globales pour contexte
}

// Dernières transactions (filtrées selon permissions + utilisateur)
$canRecettes = in_array('recettes', $userPerms);
$canDepenses = in_array('depenses', $userPerms);
$canCamions = in_array('recettes_camions', $userPerms);
$canVersements = in_array('versements', $userPerms);
$canValidate = in_array('recettes_validate', $userPerms) || in_array('depenses_validate', $userPerms) || in_array('versements_validate', $userPerms);

// Construire les queries "mes transactions récentes"
$recentRecettes = [];
$recentDepenses = [];
if ($canRecettes) {
    $sql = "SELECT r.*, (r.montantexpedition + r.montantAccompagnement) as montant, a.nomagence FROM recette r LEFT JOIN agence a ON r.agence_id=a.id";
    if (!$isAdmin) $sql .= " WHERE r.created_by = " . $userId;
    $sql .= " ORDER BY r.created_at DESC LIMIT 5";
    $recentRecettes = $db->query($sql)->fetchAll();
}
if ($canDepenses) {
    $sql = "SELECT d.*, a.nomagence, c.nom as cat_nom FROM depenses d LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN categories c ON d.categorie_id=c.id";
    if (!$isAdmin) $sql .= " WHERE d.created_by = " . $userId;
    $sql .= " ORDER BY d.created_at DESC LIMIT 5";
    $recentDepenses = $db->query($sql)->fetchAll();
}

// Transactions en attente (filtrées selon permissions)
$enAttente = [];
if ($canValidate) {
    $userSql = $isAdmin ? '' : ' AND created_by = ' . $userId;
    $sql = "SELECT 'recette' as type, id, reference, montantexpedition + montantAccompagnement as montant, created_at FROM recette WHERE statut='en_attente'" . ($canRecettes ? $userSql : ' AND 1=0');
    $sql .= " UNION ALL SELECT 'depense', id, reference, montant, created_at FROM depenses WHERE statut='en_attente'" . ($canDepenses ? $userSql : ' AND 1=0');
    if ($canCamions) $sql .= " UNION ALL SELECT 'recette_camion', id, reference, montant, created_at FROM recettes_camions WHERE statut='en_attente'" . $userSql;
    if ($canVersements) $sql .= " UNION ALL SELECT 'versement', id, reference, sommeverse as montant, created_at FROM versement WHERE statut='en_attente'" . $userSql;
    $sql .= " ORDER BY created_at DESC LIMIT 10";
    $enAttente = $db->query($sql)->fetchAll();
}

// Message de bienvenue personnalisé
$greeting = match(true) {
    date('H') < 12 => 'Bonjour',
    date('H') < 18 => 'Bon après-midi',
    default => 'Bonsoir'
};
$userName = $_SESSION['full_name'] ?? 'Utilisateur';
$roleLabel = $_SESSION['user_role_label'] ?? ($userRole === 'admin' ? 'Administrateur' : 'Agent');
?>

<!-- Main Content -->
<div class="main-content" id="main-content" role="main">
    <!-- Header -->
    <header class="main-header" role="banner">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button>
            <span class="mb-0 fw-bold"><?php echo e($pageTitle); ?></span>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="notif-btn" aria-label="Notifications" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotifs > 0): ?>
                    <span class="notif-badge"><?php echo $unreadNotifs; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <h6 class="dropdown-header">Notifications</h6>
                    <?php
                    $notifs = $db->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 10");
                    $notifs->execute([$_SESSION['user_id']]);
                    $notifRows = $notifs->fetchAll();
                    if (!empty($notifRows)):
                        foreach ($notifRows as $n): ?>
                    <a class="dropdown-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>" href="<?php echo e($n['lien'] ?? '#'); ?>">
                        <div class="fw-600"><?php echo e($n['titre']); ?></div>
                        <div class="small text-muted"><?php echo e($n['message']); ?></div>
                        <div class="notif-time"><?php echo formatDate($n['created_at']); ?></div>
                    </a>
                    <?php endforeach;
                    else: ?>
                    <div class="dropdown-item text-muted text-center py-3">Aucune notification</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>

        <!-- Stats Cards — adaptatives selon permissions -->
        <div class="row g-3 mb-4">
            <?php if ($canRecettes): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-cash-coin me-1"></i> Recettes du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['recettes_jour']; ?>"><?php echo formatMoney($stats['recettes_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canDepenses): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-danger-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-receipt me-1"></i> Dépenses du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['depenses_jour']; ?>"><?php echo formatMoney($stats['depenses_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canCamions): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-truck me-1"></i> Recettes camions</div>
                            <div class="stat-value" data-animate="<?php echo $stats['recettes_camions_jour']; ?>"><?php echo formatMoney($stats['recettes_camions_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-truck"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canRecettes || $canDepenses || $canCamions): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-info-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-wallet2 me-1"></i> Solde du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['solde_jour']; ?>"><?php echo formatMoney($stats['solde_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Stats Row — adaptatives -->
        <div class="row g-3 mb-4">
            <?php if ($canRecettes): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-graph-up-arrow me-1"></i> Recettes du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['recettes_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canDepenses): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-danger-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-graph-down-arrow me-1"></i> Dépenses du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['depenses_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-graph-down-arrow"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canVersements): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-bank2 me-1"></i> Versements du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['versements_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-bank2"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canRecettes || $canDepenses): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card <?php echo $stats['solde_mois'] >= 0 ? 'bg-info-gradient' : 'bg-danger-gradient'; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label"><i class="bi bi-balanced-scale me-1"></i> Solde du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['solde_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-balanced-scale"></i></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Si l'utilisateur a très peu de permissions : message adapté -->
        <?php if (!$canRecettes && !$canDepenses && !$canCamions && !$canVersements): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Votre rôle <strong><?php echo e($roleLabel); ?></strong> n'a pas accès aux modules financiers.
            <?php if (in_array('users', $userPerms)): ?>
            Vous pouvez gérer les <a href="<?php echo APP_URL; ?>/users/">utilisateurs</a> et les <a href="<?php echo APP_URL; ?>/settings/roles.php">rôles</a>.
            <?php elseif (in_array('audit', $userPerms)): ?>
            Consultez le <a href="<?php echo APP_URL; ?>/audit/">journal d'audit</a> pour suivre les activités.
            <?php else: ?>
            Contactez un administrateur pour ajuster vos permissions.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Charts & En attente -->
        <div class="row g-3 mb-4">
            <!-- Chart (visible si recettes OU depenses) -->
            <?php if ($canRecettes || $canDepenses): ?>
            <div class="<?php echo ($canValidate && !empty($enAttente)) ? 'col-lg-8' : 'col-lg-12'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-bar-chart-line me-1"></i> Évolution mensuelle<?php echo $isAdmin ? '' : ' — Vos données'; ?></span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active" onclick="chartType='bar'; updateChart()">Barres</button>
                            <button class="btn btn-outline-secondary" onclick="chartType='line'; updateChart()">Lignes</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart" role="img" aria-label="Graphique des recettes et dépenses mensuelles"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- En attente (visible si permission de validation) -->
            <?php if ($canValidate): ?>
            <div class="<?php echo ($canRecettes || $canDepenses) ? 'col-lg-4' : 'col-lg-6'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-hourglass-split me-1"></i> En attente de validation</span>
                        <span class="badge bg-warning text-dark"><?php echo $stats['en_attente']; ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($enAttente)): ?>
                        <div class="empty-state py-4">
                            ✅
                            <p class="mb-0 small">Aucune transaction en attente</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($enAttente as $ea): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-warning text-dark me-1"><?php echo e($ea['type']); ?></span>
                                    <small class="fw-600"><?php echo e($ea['reference']); ?></small>
                                </div>
                                <span class="fw-bold"><?php echo formatMoney($ea['montant']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions — adaptatives -->
        <div class="row g-3">
            <?php if ($canRecettes): ?>
            <div class="col-lg-<?php echo $canDepenses ? '6' : '12'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cash-coin me-1"></i> Dernières recettes<?php echo $isAdmin ? '' : ' (mes)'; ?></span>
                        <a href="<?php echo APP_URL; ?>/recettes/" class="btn btn-sm btn-outline-success">Voir tout</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm" aria-label="Dernières recettes en attente">
                            <tbody>
                            <?php foreach ($recentRecettes as $r): ?>
                                <tr>
                                    <td><small><?php echo e($r['reference']); ?></small></td>
                                    <td><small><?php echo e($r['nomagence'] ?? '-'); ?></small></td>
                                    <td class="fw-bold text-success"><?php echo formatMoney($r['montant']); ?></td>
                                    <td><?php echo getStatusBadge($r['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentRecettes)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Aucune recette</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canDepenses): ?>
            <div class="col-lg-<?php echo $canRecettes ? '6' : '12'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-receipt me-1"></i> Dernières dépenses<?php echo $isAdmin ? '' : ' (mes)'; ?></span>
                        <a href="<?php echo APP_URL; ?>/depenses/" class="btn btn-sm btn-outline-danger">Voir tout</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm" aria-label="Dernières dépenses en attente">
                            <thead><tr><th>Réf</th><th>Catégorie</th><th>Montant</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentDepenses as $d): ?>
                                <tr>
                                    <td><small><?php echo e($d['reference']); ?></small></td>
                                    <td><small><?php echo e($d['cat_nom'] ?? '-'); ?></small></td>
                                    <td class="fw-bold text-danger"><?php echo formatMoney($d['montant']); ?></td>
                                    <td><?php echo getStatusBadge($d['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentDepenses)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Aucune dépense</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canRecettes || $canDepenses): ?>
<script>
const monthLabels = <?php echo json_encode($monthLabels); ?>;
const recettesData = <?php echo json_encode($recettesData); ?>;
const depensesData = <?php echo json_encode($depensesData); ?>;
let chartType = 'bar';

function updateChart() {
    const ctx = document.getElementById('monthlyChart');
    if (window.monthlyChartInstance) window.monthlyChartInstance.destroy();

    window.monthlyChartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Recettes',
                    data: recettesData,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: '#198754',
                    borderWidth: 2,
                    borderRadius: chartType === 'bar' ? 6 : 0,
                    tension: 0.4,
                    fill: chartType === 'line',
                },
                {
                    label: 'Dépenses',
                    data: depensesData,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 2,
                    borderRadius: chartType === 'bar' ? 6 : 0,
                    tension: 0.4,
                    fill: chartType === 'line',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) { return new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(val); }
                    }
                }
            }
        }
    });
}
updateChart();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
