<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('paie');
$pageTitle = 'Gestion de la paie';

$db = getDB();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_periode') {
        $exerciceId = (int)($_POST['exercice_id'] ?? 0);
        $mois = (int)($_POST['mois'] ?? 0);
        try {
            $db->prepare("INSERT INTO periodes_paie (exercice_id, mois, statut) VALUES (?,?,'brouillon')")
               ->execute([$exerciceId, $mois]);
            flash('success', 'Période de paie créée.');
        } catch (Exception $e) {
            flash('danger', 'Cette période existe déjà ou erreur: ' . $e->getMessage());
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'generate' && hasPermission('paie', 'gerer_bulletins')) {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        $employes = $db->query("SELECT ce.employe_id, COALESCE(e.utilisateur_id,0) as utilisateur_id FROM contrats_employes ce JOIN employes e ON ce.employe_id=e.id WHERE ce.statut='actif' AND e.statut='actif'")->fetchAll();

        $count = 0;
        foreach ($employes as $emp) {
            $uid = (int)$emp['utilisateur_id'];
            // Check if bulletin already exists
            $exists = $db->prepare("SELECT COUNT(*) FROM bulletins_paie WHERE periode_id=? AND utilisateur_id=?");
            $exists->execute([$periodeId, $uid]);
            if ((int)$exists->fetchColumn() > 0) continue;

            try {
                $bulletin = calculerBulletin($uid, $periodeId);
                $db->prepare("INSERT INTO bulletins_paie (periode_id, utilisateur_id, salaire_base, total_gains, total_retenues, net_a_payer, charges_patronales, cout_total, statut)
                              VALUES (?,?,?,?,?,?,?,?,'brouillon')")
                   ->execute([$periodeId, $uid, $bulletin['salaire_base'], $bulletin['total_gains'], $bulletin['total_retenues'],
                              $bulletin['net_a_payer'], $bulletin['charges_patronales'], $bulletin['cout_total']]);
                $bulletinId = $db->lastInsertId();

                foreach ($bulletin['lignes'] as $ligne) {
                    $db->prepare("INSERT INTO lignes_bulletin (bulletin_id, rubrique_id, libelle, type, montant, ordre)
                                  VALUES (?,?,?,?,?,?)")
                       ->execute([$bulletinId, $ligne['rubrique_id'], $ligne['libelle'], $ligne['type'], $ligne['montant'], $ligne['ordre']]);
                }
                $count++;
            } catch (Exception $e) { /* skip employees without contract */ }
        }
        flash('success', "$count bulletins générés.");
        header('Location: bulletins.php?periode_id=' . $periodeId); exit;
    }

    if ($action === 'valider_periode' && hasPermission('paie', 'valider_paie')) {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        $db->prepare("UPDATE periodes_paie SET statut='valide', cloture_par=? WHERE id=?")->execute([$_SESSION['user_id'], $periodeId]);
        $db->prepare("UPDATE bulletins_paie SET statut='valide' WHERE periode_id=? AND statut='brouillon'")->execute([$periodeId]);
        auditLog('valider_paie', 'paie', 'periodes_paie', $periodeId);
        flash('success', 'Période de paie validée.');
        header('Location: index.php'); exit;
    }

    if ($action === 'payer_employe') {
        $bulletinId = (int)($_POST['bulletin_id'] ?? 0);
        $datePaiement = $_POST['date_paiement'] ?? date('Y-m-d');
        $reference = sanitize($_POST['reference_paiement'] ?? '');
        $net = (float)($_POST['net_en_faveur'] ?? 0);
        $db->prepare("UPDATE bulletins_paie SET statut='paye', net_en_faveur=?, date_paiement=?, reference_paiement=? WHERE id=?")
           ->execute([$net, $datePaiement, $reference, $bulletinId]);
        flash('success', 'Paiement enregistré.');
        header('Location: ' . ($_POST['redirect'] ?? 'index.php')); exit;
    }
}

// Fetch data
$periodes = $db->query("SELECT pp.*, e.code as exercice_code FROM periodes_paie pp JOIN exercices e ON pp.exercice_id=e.id ORDER BY e.date_debut DESC, pp.mois DESC LIMIT 24")->fetchAll();
$exercices = getExercices();
$employesActifs = $db->query("SELECT COUNT(*) FROM contrats_employes ce JOIN employes e ON ce.employe_id=e.id WHERE ce.statut='actif' AND e.statut='actif'")->fetchColumn();
$masseSalariale = $db->query("SELECT COALESCE(SUM(ce.salaire_base),0) FROM contrats_employes ce JOIN employes e ON ce.employe_id=e.id WHERE ce.statut='actif' AND e.statut='actif'")->fetchColumn();

$moisLabels = ['', 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <h2 style="margin:0"><i class="fa-solid fa-sack-dollar"></i> Module de Paie</h2>
    <button class="btn btn-primary" onclick="openModal('modal-periode')"><i class="fa-solid fa-plus"></i> Nouvelle période</button>
</div>

<!-- KPIs -->
<div class="stats-grid mb-20">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Employés actifs</div>
        <div class="stat-value"><?= $employesActifs ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        <div class="stat-label">Masse salariale mensuelle</div>
        <div class="stat-value"><?= formatMontant($masseSalariale) ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-label">Périodes générées</div>
        <div class="stat-value"><?= count($periodes) ?></div>
    </div>
</div>

<!-- Périodes -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-calendar-days"></i> Périodes de paie</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Période</th><th>Exercice</th><th>Statut</th><th>Employés</th><th>Net total</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($periodes)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:40px">Aucune période. Créez-en une pour commencer.</td></tr>
                <?php else: foreach ($periodes as $p):
                    $stats = $db->prepare("SELECT COUNT(*) as n, COALESCE(SUM(net_a_payer),0) as total FROM bulletins_paie WHERE periode_id=?");
                    $stats->execute([$p['id']]);
                    $stats = $stats->fetch();
                ?>
                <tr>
                    <td style="font-weight:700"><?= $moisLabels[(int)$p['mois']] ?></td>
                    <td><?= htmlspecialchars($p['exercice_code']) ?></td>
                    <td>
                        <?php $sB = ['brouillon'=>'gray','cloture'=>'info','valide'=>'success']; ?>
                        <span class="badge badge-<?= $sB[$p['statut']] ?? 'gray' ?>"><?= ucfirst($p['statut']) ?></span>
                    </td>
                    <td><?= (int)($stats['n'] ?? 0) ?></td>
                    <td class="amount"><?= formatMontant($stats['total'] ?? 0) ?></td>
                    <td>
                        <a href="bulletins.php?periode_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i> Bulletins</a>
                        <?php if ($p['statut'] === 'brouillon' && hasPermission('paie','gerer_bulletins')): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="generate">
                            <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-gears"></i> Générer</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($p['statut'] === 'cloture' && hasPermission('paie','valider_paie')): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Valider cette période ? Les bulletins seront figés.')">
                            <input type="hidden" name="action" value="valider_periode">
                            <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Valider</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($p['statut'] === 'valide'): ?>
                        <a href="declarations.php?periode_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-lines"></i> Déclarations</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Rubriques quick link -->
<div class="mt-20">
    <a href="rubriques.php" class="btn btn-outline"><i class="fa-solid fa-sliders"></i> Gérer les rubriques de paie</a>
    <a href="declarations.php" class="btn btn-outline"><i class="fa-solid fa-file-invoice-dollar"></i> Déclarations CNPS / DGI</a>
</div>

<!-- Modal: Create periode -->
<div class="modal-overlay" id="modal-periode">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Nouvelle période de paie</span><button class="modal-close" onclick="closeModal('modal-periode')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_periode">
            <div class="modal-body">
                <div class="form-group"><label>Exercice</label>
                    <select name="exercice_id" class="form-control" required>
                        <?php foreach ($exercices as $ex): ?>
                        <?php if ($ex['statut'] === 'ouvert'): ?>
                        <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['code']) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Mois</label>
                    <select name="mois" class="form-control" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>><?= $moisLabels[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-periode')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
