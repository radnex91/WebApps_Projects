<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('cloture');
$pageTitle = 'Clôture d\'exercice';

$db = getDB();
$user = currentUser();

// Select exercice (current or selected)
$exerciceId = isset($_GET['exercice_id']) ? (int)$_GET['exercice_id'] : 0;
if (!$exerciceId) {
    $current = getExerciceCourant();
    $exerciceId = $current ? (int)$current['id'] : 0;
}

$exercice = $db->prepare("SELECT * FROM exercices WHERE id=?");
$exercice->execute([$exerciceId]);
$exercice = $exercice->fetch();
$exercices = getExercices();

// Active step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_inventaire') {
        $type = $_POST['type'] ?? '';
        $libelle = sanitize($_POST['libelle'] ?? '');
        $compteDebit = $_POST['compte_debit'] ?? '';
        $compteCredit = $_POST['compte_credit'] ?? '';
        $montant = (float)($_POST['montant'] ?? 0);
        $dateEcriture = $_POST['date_ecriture'] ?? date('Y-m-d');

        if ($libelle && $compteDebit && $compteCredit && $montant > 0) {
            $db->prepare("INSERT INTO ecritures_inventaire (exercice_id, type, libelle, compte_debit, compte_credit, montant, date_ecriture, cree_par)
                          VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$exerciceId, $type, $libelle, $compteDebit, $compteCredit, $montant, $dateEcriture, $_SESSION['user_id']]);
            auditLog('create', 'cloture', 'ecritures_inventaire', $db->lastInsertId());
            flash('success', 'Écriture d\'inventaire enregistrée.');
        }
        header("Location: index.php?exercice_id=$exerciceId&step=$step"); exit;
    }

    if ($action === 'delete_inventaire') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM ecritures_inventaire WHERE id=? AND ecriture_comptable_id IS NULL")->execute([$id]);
        flash('warning', 'Écriture d\'inventaire supprimée.');
        header("Location: index.php?exercice_id=$exerciceId&step=$step"); exit;
    }

    if ($action === 'generate_amortissements') {
        $immos = $db->query("SELECT * FROM immobilisations WHERE statut='actif'")->fetchAll();
        $count = 0;
        foreach ($immos as $immo) {
            $annuite = calculerAmortissementAnnuel($immo);
            if ($annuite <= 0) continue;
            // Check if already amortized for this exercice
            $already = $db->prepare("SELECT COUNT(*) FROM dotations_amortissement WHERE immobilisation_id=? AND exercice_id=?");
            $already->execute([$immo['id'], $exerciceId]);
            if ((int)$already->fetchColumn() > 0) continue;

            $db->prepare("INSERT INTO dotations_amortissement (immobilisation_id, exercice_id, montant, date_dotation) VALUES (?,?,?,CURDATE())")
               ->execute([$immo['id'], $exerciceId, round($annuite, 2)]);
            $db->prepare("UPDATE immobilisations SET cumul_amortissement = cumul_amortissement + ?, date_dernier_amortissement = CURDATE() WHERE id=?")
               ->execute([round($annuite, 2), $immo['id']]);
            $count++;
        }
        flash('success', "$count dotations aux amortissements générées.");
        header("Location: index.php?exercice_id=$exerciceId&step=2"); exit;
    }

    if ($action === 'cloture') {
        $db->prepare("UPDATE exercices SET statut='cloture_definitive', date_cloture_definitive=NOW(), cloture_par=? WHERE id=?")
           ->execute([$_SESSION['user_id'], $exerciceId]);
        auditLog('cloture', 'cloture', 'exercices', $exerciceId);
        flash('success', 'Exercice clôturé définitivement.');
        header("Location: index.php?exercice_id=$exerciceId"); exit;
    }
}

// Fetch data
$ecrituresInv = $db->prepare("SELECT * FROM ecritures_inventaire WHERE exercice_id=? ORDER BY date_ecriture DESC");
$ecrituresInv->execute([$exerciceId]);
$ecrituresInv = $ecrituresInv->fetchAll();

$immobilisations = $db->query("SELECT * FROM immobilisations ORDER BY code")->fetchAll();
$dotations = $db->prepare("SELECT da.*, i.libelle as immo_libelle FROM dotations_amortissement da JOIN immobilisations i ON da.immobilisation_id = i.id WHERE da.exercice_id=?");
$dotations->execute([$exerciceId]);
$dotations = $dotations->fetchAll();

$balanceGen = $db->prepare("SELECT compte, SUM(debit) as td, SUM(credit) as tc FROM ecritures_comptables WHERE exercice_id=? GROUP BY compte ORDER BY compte");
$balanceGen->execute([$exerciceId]);
$balanceGen = $balanceGen->fetchAll();

$totalDebitBal = array_sum(array_column($balanceGen, 'td'));
$totalCreditBal = array_sum(array_column($balanceGen, 'tc'));

$sig = calculerSIG($exerciceId);
$resultat = $sig[6]['montant'] ?? 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <h2 style="margin:0"><i class="fa-solid fa-door-closed"></i> Clôture d'exercice</h2>
    <form method="GET" style="display:inline-flex;gap:8px;align-items:center">
        <select name="exercice_id" class="form-control form-control-sm" onchange="this.form.submit()" style="width:150px">
            <?php foreach ($exercices as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $ex['id'] == $exerciceId ? 'selected' : '' ?>><?= htmlspecialchars($ex['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
    </form>
</div>

<?php if ($exercice): ?>
<!-- Info exercice -->
<div class="card mb-20" style="background:var(--primary);color:#fff">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px">
        <div>
            <strong style="font-size:18px">Exercice <?= htmlspecialchars($exercice['code']) ?></strong>
            <div style="opacity:0.8;font-size:13px"><?= date('d/m/Y', strtotime($exercice['date_debut'])) ?> — <?= date('d/m/Y', strtotime($exercice['date_fin'])) ?></div>
        </div>
        <span class="badge" style="background:rgba(255,255,255,0.2)"><?= htmlspecialchars(str_replace('_',' ',ucfirst($exercice['statut']))) ?></span>
    </div>
</div>

<!-- Stepper -->
<div style="display:flex;gap:0;margin-bottom:20px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border)">
    <?php
    $steps = [
        1 => ['icon' => 'fa-calculator', 'label' => '1. Écritures d\'inventaire'],
        2 => ['icon' => 'fa-gear', 'label' => '2. Amortissements'],
        3 => ['icon' => 'fa-scale-balanced', 'label' => '3. Balance & Résultat'],
        4 => ['icon' => 'fa-lock', 'label' => '4. Clôture'],
    ];
    foreach ($steps as $sn => $sd): ?>
    <a href="?exercice_id=<?= $exerciceId ?>&step=<?= $sn ?>"
       style="flex:1;text-align:center;padding:12px;text-decoration:none;color:<?= $sn == $step ? '#fff' : 'var(--text2)' ?>;background:<?= $sn == $step ? 'var(--primary)' : 'transparent' ?>;font-size:13px;font-weight:600;transition:all var(--transition)">
        <i class="fa-solid <?= $sd['icon'] ?>"></i> <?= $sd['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Étape 1: Écritures d'inventaire -->
<?php if ($step == 1): ?>
<div class="card mb-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-list-check"></i> Écritures d'inventaire</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-inventaire')"><i class="fa-solid fa-plus"></i> Ajouter</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Libellé</th><th>Débit</th><th>Crédit</th><th>Montant</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($ecrituresInv)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:30px">Aucune écriture d'inventaire.</td></tr>
                <?php else: foreach ($ecrituresInv as $ei): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($ei['date_ecriture'])) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($ei['type']) ?></span></td>
                    <td><?= htmlspecialchars($ei['libelle']) ?></td>
                    <td><?= htmlspecialchars($ei['compte_debit']) ?></td>
                    <td><?= htmlspecialchars($ei['compte_credit']) ?></td>
                    <td class="amount"><?= formatMontant($ei['montant']) ?></td>
                    <td>
                        <?php if (!$ei['ecriture_comptable_id']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete_inventaire">
                            <input type="hidden" name="id" value="<?= $ei['id'] ?>">
                            <button class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ?')"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--success);font-size:12px"><i class="fa-solid fa-check"></i> Comptabilisé</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="<?= $step == 2 ? 'generate_amortissements' : 'cloture' ?>">
    <input type="hidden" name="exercice_id" value="<?= $exerciceId ?>">
</form>

<!-- Étape 2: Immobilisations & Amortissements -->
<?php if ($step == 2): ?>
<div class="card mb-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-building"></i> Immobilisations</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Libellé</th><th>Valeur</th><th>Amort. cumulé</th><th>VNC</th><th>Annuité estimée</th></tr></thead>
            <tbody>
                <?php foreach ($immobilisations as $immo):
                    $vnc = $immo['valeur_acquisition'] - $immo['cumul_amortissement'];
                    $annuite = calculerAmortissementAnnuel($immo);
                ?>
                <tr>
                    <td><?= htmlspecialchars($immo['code']) ?></td>
                    <td><?= htmlspecialchars($immo['libelle']) ?></td>
                    <td class="amount"><?= formatMontant($immo['valeur_acquisition']) ?></td>
                    <td class="amount"><?= formatMontant($immo['cumul_amortissement']) ?></td>
                    <td class="amount"><?= formatMontant($vnc) ?></td>
                    <td class="amount"><?= formatMontant(round($annuite,2)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-20">
    <div class="card-header"><span class="card-title">Dotations générées pour <?= htmlspecialchars($exercice['code']) ?></span>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-gears"></i> Générer les dotations</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Immobilisation</th><th>Montant</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($dotations)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:30px">Aucune dotation générée.</td></tr>
                <?php else: foreach ($dotations as $dot): ?>
                <tr>
                    <td><?= htmlspecialchars($dot['immo_libelle']) ?></td>
                    <td class="amount"><?= formatMontant($dot['montant']) ?></td>
                    <td><?= date('d/m/Y', strtotime($dot['date_dotation'])) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Étape 3: Balance + Résultat -->
<?php if ($step == 3): ?>
<div class="card mb-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-scale-balanced"></i> Balance générale — Exercice <?= htmlspecialchars($exercice['code']) ?></span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Compte</th><th>Débit</th><th>Crédit</th><th>Solde D</th><th>Solde C</th></tr></thead>
            <tbody>
                <?php foreach ($balanceGen as $b):
                    $sd = max(0, $b['td'] - $b['tc']);
                    $sc = max(0, $b['tc'] - $b['td']);
                ?>
                <tr>
                    <td><?= htmlspecialchars($b['compte']) ?></td>
                    <td class="amount"><?= $b['td'] > 0 ? formatMontant($b['td']) : '' ?></td>
                    <td class="amount"><?= $b['tc'] > 0 ? formatMontant($b['tc']) : '' ?></td>
                    <td class="amount"><?= $sd > 0 ? formatMontant($sd) : '' ?></td>
                    <td class="amount"><?= $sc > 0 ? formatMontant($sc) : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight:700;background:var(--surface2)">
                    <td style="text-align:right">TOTAUX</td>
                    <td class="amount"><?= formatMontant($totalDebitBal) ?></td>
                    <td class="amount"><?= formatMontant($totalCreditBal) ?></td>
                    <td class="amount"><?= formatMontant(max(0,$totalDebitBal-$totalCreditBal)) ?></td>
                    <td class="amount"><?= formatMontant(max(0,$totalCreditBal-$totalDebitBal)) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-20">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-chart-line"></i> Soldes Intermédiaires de Gestion</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>SIG</th><th>Libellé</th><th>Montant</th></tr></thead>
            <tbody>
                <?php foreach ($sig as $s): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($s['code']) ?></td>
                    <td><?= htmlspecialchars($s['libelle']) ?></td>
                    <td class="amount <?= $s['montant'] >= 0 ? 'amount-credit' : 'amount-debit' ?>">
                        <?= formatMontant(abs($s['montant'])) ?> <?= $s['montant'] < 0 ? '(perte)' : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body" style="text-align:center;padding:20px">
        <div style="font-size:12px;color:var(--text3);margin-bottom:4px">RÉSULTAT NET DE L'EXERCICE</div>
        <div style="font-size:28px;font-weight:700;color:<?= $resultat >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
            <?= $resultat >= 0 ? '+' : '-' ?><?= formatMontant(abs($resultat)) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Étape 4: Clôture définitive -->
<?php if ($step == 4): ?>
<div class="card">
    <div class="card-body" style="padding:40px;text-align:center">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:48px;color:var(--warning);margin-bottom:16px;display:block"></i>
        <h3>Confirmer la clôture définitive</h3>
        <p style="color:var(--text2);max-width:500px;margin:0 auto 24px">
            La clôture définitive de l'exercice <strong><?= htmlspecialchars($exercice['code']) ?></strong> est irréversible.
            Toutes les saisies seront bloquées pour cet exercice. Vérifiez que toutes les écritures d'inventaire sont passées
            et que la balance est correcte.
        </p>
        <p style="color:var(--text3);margin-bottom:24px">
            Résultat net : <strong style="color:<?= $resultat >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-size:20px">
                <?= $resultat >= 0 ? '+' : '-' ?><?= formatMontant(abs($resultat)) ?>
            </strong>
        </p>
        <?php if ($exercice['statut'] === 'ouvert' || $exercice['statut'] === 'cloture_provisoire'): ?>
        <button class="btn btn-danger btn-lg" onclick="if(confirm('ATTENTION: Action irréversible. Confirmer la clôture ?')) document.forms['cloture-final'].submit()">
            <i class="fa-solid fa-lock"></i> Clôturer définitivement l'exercice <?= htmlspecialchars($exercice['code']) ?>
        </button>
        <form name="cloture-final" method="POST">
            <input type="hidden" name="action" value="cloture">
        </form>
        <?php else: ?>
        <div class="badge badge-danger" style="font-size:16px;padding:10px 20px">Exercice déjà clôturé</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // $exercice ?>

<!-- Modal: Add inventaire entry -->
<div class="modal-overlay" id="modal-inventaire">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Nouvelle écriture d'inventaire</span><button class="modal-close" onclick="closeModal('modal-inventaire')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="save_inventaire">
            <div class="modal-body">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control" required>
                        <option value="amortissement">Amortissement</option>
                        <option value="provision">Provision</option>
                        <option value="can">C.A.N. (Charges constatées d'avance)</option>
                        <option value="cca">C.C.A. (Charges à payer)</option>
                        <option value="charge_a_payer">Charge à payer</option>
                        <option value="produit_a_recevoir">Produit à recevoir</option>
                        <option value="stock">Variation de stock</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="form-group"><label>Libellé</label><input type="text" name="libelle" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Compte débité</label><input type="text" name="compte_debit" class="form-control" required placeholder="ex: 6811"></div>
                    <div class="form-group"><label>Compte crédité</label><input type="text" name="compte_credit" class="form-control" required placeholder="ex: 2841"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Montant</label><input type="number" name="montant" class="form-control" step="0.01" required></div>
                    <div class="form-group"><label>Date</label><input type="date" name="date_ecriture" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-inventaire')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
