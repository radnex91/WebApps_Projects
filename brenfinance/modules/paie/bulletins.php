<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('paie');
$pageTitle = 'Bulletins de paie';

$db = getDB();
$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$periode = $db->prepare("SELECT pp.*, e.code as exercice_code FROM periodes_paie pp JOIN exercices e ON pp.exercice_id=e.id WHERE pp.id=?");
$periode->execute([$periodeId]);
$periode = $periode->fetch();
if (!$periode) { flash('danger', 'Période introuvable.'); header('Location: index.php'); exit; }

// Handle export
if (isset($_GET['export'])) {
    $rows = $db->prepare("SELECT e.nom, e.prenom, bp.salaire_base, bp.total_gains, bp.total_retenues, bp.net_a_payer, bp.charges_patronales, bp.cout_total, bp.statut
        FROM bulletins_paie bp JOIN employes e ON bp.employe_id=e.id WHERE bp.periode_id=? ORDER BY e.nom, e.prenom");
    $rows->execute([$periodeId]);
    $rows = $rows->fetchAll();
    exportData($rows, ['nom'=>'Nom','prenom'=>'Prénom','salaire_base'=>'Base','total_gains'=>'Gains','total_retenues'=>'Retenues','net_a_payer'=>'Net','charges_patronales'=>'Charges pat.','cout_total'=>'Coût total','statut'=>'Statut'], 'bulletins_paie', $_GET['export']);
}

// Fetch bulletins
$bulletins = $db->prepare("SELECT bp.*, CONCAT(e.nom,' ',e.prenom) as employe_nom, e.matricule
    FROM bulletins_paie bp JOIN employes e ON bp.employe_id=e.id
    WHERE bp.periode_id=? ORDER BY e.nom, e.prenom");
$bulletins->execute([$periodeId]);
$bulletins = $bulletins->fetchAll();

$totalNet = array_sum(array_column($bulletins, 'net_a_payer'));
$totalCharges = array_sum(array_column($bulletins, 'charges_patronales'));
$totalCout = $totalNet + $totalCharges;

$moisLabels = ['', 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="index.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
</div>

<div class="d-flex justify-between align-center mb-20">
    <div>
        <h2 style="margin:0"><i class="fa-solid fa-file-invoice-dollar"></i>
            Bulletins — <?= $moisLabels[(int)$periode['mois']] ?> <?= htmlspecialchars($periode['exercice_code']) ?></h2>
        <p style="color:var(--text3);margin:4px 0 0">
            Statut: <?= ucfirst($periode['statut']) ?> |
            <?= count($bulletins) ?> bulletin(s)
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <?= exportButtons() ?>
        <?php if ($periode['statut'] === 'brouillon' && hasPermission('paie','gerer_bulletins')): ?>
        <form method="POST" action="index.php" style="display:inline">
            <input type="hidden" name="action" value="generate">
            <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
            <button class="btn btn-primary"><i class="fa-solid fa-gears"></i> Régénérer</button>
        </form>
        <?php endif; ?>
        <?php if ($periode['statut'] === 'brouillon' && count($bulletins) > 0): ?>
        <form method="POST" action="index.php" style="display:inline">
            <input type="hidden" name="action" value="valider_periode">
            <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
            <button class="btn btn-warning" onclick="return confirm('Fermer la période ? Les bulletins seront gelés.')">
                <i class="fa-solid fa-lock"></i> Clôturer période
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Récapitulatif -->
<div class="stats-grid mb-20">
    <div class="stat-card info">
        <div class="stat-label">Total net à payer</div>
        <div class="stat-value"><?= formatMontant($totalNet) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Charges patronales</div>
        <div class="stat-value"><?= formatMontant($totalCharges) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-label">Coût total employeur</div>
        <div class="stat-value"><?= formatMontant($totalCout) ?></div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Employé</th>
                <th class="hide-mobile">Salaire base</th>
                <th>Gains</th>
                <th>Retenues</th>
                <th>Net à payer</th>
                <th class="hide-mobile">Ch. patronales</th>
                <th class="hide-mobile">Coût total</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
                <?php if (empty($bulletins)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text3);padding:40px">
                    Aucun bulletin. Cliquez « Générer » pour créer les bulletins automatiquement.
                </td></tr>
                <?php else: foreach ($bulletins as $b): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($b['employe_nom']) ?></td>
                    <td class="amount hide-mobile"><?= formatMontant($b['salaire_base']) ?></td>
                    <td class="amount amount-credit">+<?= formatMontant($b['total_gains']) ?></td>
                    <td class="amount amount-debit">-<?= formatMontant($b['total_retenues']) ?></td>
                    <td class="amount" style="font-weight:700"><?= formatMontant($b['net_a_payer']) ?></td>
                    <td class="amount hide-mobile"><?= formatMontant($b['charges_patronales']) ?></td>
                    <td class="amount hide-mobile"><?= formatMontant($b['cout_total']) ?></td>
                    <td><span class="badge badge-<?= $b['statut']==='paye'?'success':($b['statut']==='valide'?'info':'gray') ?>"><?= ucfirst($b['statut']) ?></span></td>
                    <td>
                        <a href="bulletin_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
