<?php
$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$db = getDB();
$stats = getDashboardStats($db);

// Données graphiques mensuels
$monthLabels = [];
$recettesData = [];
$depensesData = [];
for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");
    $monthLabels[] = $date->format('M Y');
    $mStart = $date->format('Y-m-01');
    $mEnd = $date->format('Y-m-t');

    $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement),0) FROM recette WHERE date BETWEEN ? AND ? AND statut='validee'");
    $stmt->execute([$mStart, $mEnd]);
    $recettesData[] = (float) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM depenses WHERE date_depense BETWEEN ? AND ? AND statut='validee'");
    $stmt->execute([$mStart, $mEnd]);
    $depensesData[] = (float) $stmt->fetchColumn();
}

// Dernières transactions
$recentRecettes = $db->query("SELECT r.*, (r.montantexpedition + r.montantAccompagnement) as montant, a.nomagence FROM recette r LEFT JOIN agence a ON r.agence_id=a.id ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
$recentDepenses = $db->query("SELECT d.*, a.nomagence, c.nom as cat_nom FROM depenses d LEFT JOIN agence a ON d.agence_id=a.id LEFT JOIN categories c ON d.categorie_id=c.id ORDER BY d.created_at DESC LIMIT 5")->fetchAll();

// Versements en attente
$enAttente = $db->query("SELECT 'recette' as type, id, reference, montantexpedition + montantAccompagnement as montant, created_at FROM recette WHERE statut='en_attente' UNION ALL SELECT 'depense', id, reference, montant, created_at FROM depenses WHERE statut='en_attente' UNION ALL SELECT 'recette_camion', id, reference, montant, created_at FROM recettes_camions WHERE statut='en_attente' UNION ALL SELECT 'versement', id, reference, sommeverse as montant, created_at FROM versement WHERE statut='en_attente' ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h6 class="mb-0 fw-bold"><?php echo e($pageTitle); ?></h6>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="notif-btn" data-bs-toggle="dropdown">
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
                    foreach ($notifs->fetchAll() as $n): ?>
                    <a class="dropdown-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>" href="<?php echo e($n['lien'] ?? '#'); ?>">
                        <div class="fw-600"><?php echo e($n['titre']); ?></div>
                        <div class="small text-muted"><?php echo e($n['message']); ?></div>
                        <div class="notif-time"><?php echo formatDate($n['created_at']); ?></div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($notifs)): ?>
                    <div class="dropdown-item text-muted text-center py-3">Aucune notification</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <div class="header-user" data-bs-toggle="dropdown">
                    <div class="avatar"><?php echo e($userInitials ?? 'U'); ?></div>
                    <div class="user-info d-none d-sm-block">
                        <div class="user-name"><?php echo e($_SESSION['full_name'] ?? ''); ?></div>
                        <div class="user-role"><?php echo e($roleLabel ?? ''); ?></div>
                    </div>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content fade-in">
        <?php echo displayFlashMessages(); ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Recettes du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['recettes_jour']; ?>"><?php echo formatMoney($stats['recettes_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-danger-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Dépenses du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['depenses_jour']; ?>"><?php echo formatMoney($stats['depenses_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-cart-dash"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Recettes camions</div>
                            <div class="stat-value" data-animate="<?php echo $stats['recettes_camions_jour']; ?>"><?php echo formatMoney($stats['recettes_camions_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-truck"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-info-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Solde du jour</div>
                            <div class="stat-value" data-animate="<?php echo $stats['solde_jour']; ?>"><?php echo formatMoney($stats['solde_jour']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-success-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Recettes du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['recettes_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-danger-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Dépenses du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['depenses_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-graph-down-arrow"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-primary-gradient">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Versements du mois</div>
                            <div class="stat-value"><?php echo formatMoney($stats['versements_mois']); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-bank2"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card <?php echo $stats['solde_mois'] >= 0 ? 'bg-info-gradient' : 'bg-danger-gradient'; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Solde du mois</div>
                            <div class="stat-value"><?php echo formatMoney(abs($stats['solde_mois'])); ?></div>
                        </div>
                        <div class="stat-icon"><i class="bi bi-scale"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts & Tables -->
        <div class="row g-3 mb-4">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-bar-chart me-2"></i>Évolution mensuelle</span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active" onclick="chartType='bar'; updateChart()">Barres</button>
                            <button class="btn btn-outline-secondary" onclick="chartType='line'; updateChart()">Lignes</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- En attente -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock me-2"></i>En attente de validation</span>
                        <span class="badge bg-warning text-dark"><?php echo $stats['en_attente']; ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($enAttente)): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
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
        </div>

        <!-- Recent Transactions -->
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cash-coin me-2 text-success"></i>Dernières recettes</span>
                        <a href="<?php echo APP_URL; ?>/recettes/" class="btn btn-sm btn-outline-success">Voir tout</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead><tr><th>Réf</th><th>Agence</th><th>Montant</th><th>Statut</th></tr></thead>
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
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cart-dash me-2 text-danger"></i>Dernières dépenses</span>
                        <a href="<?php echo APP_URL; ?>/depenses/" class="btn btn-sm btn-outline-danger">Voir tout</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
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
        </div>
    </div>
</div>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>