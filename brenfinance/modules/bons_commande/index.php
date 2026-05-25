<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('bons_commande');
$pageTitle = 'Bons de commande';

$db = getDB();
$user = currentUser();

// Handle export
if (isset($_GET['export'])) {
    $rows = $db->query("SELECT bc.numero, de.numero as eng_numero, f.nom as fournisseur, bc.date_emission, bc.montant_total, bc.statut FROM bons_commande bc JOIN demandes_engagement de ON bc.engagement_id=de.id JOIN fournisseurs f ON bc.fournisseur_id=f.id ORDER BY bc.created_at DESC")->fetchAll();
    $headers = ['numero'=>'N° BC','eng_numero'=>'Engagement','fournisseur'=>'Fournisseur','date_emission'=>'Date','montant_total'=>'Montant','statut'=>'Statut'];
    exportData($rows, $headers, 'bons_commande_' . date('Y-m-d'), $_GET['export']);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'validate' && $id && hasPermission('bons_commande', 'valider')) {
        $db->prepare("UPDATE bons_commande SET statut='emis', valide_par=?, date_validation=NOW() WHERE id=? AND statut='brouillon'")
           ->execute([$_SESSION['user_id'], $id]);
        auditLog('valider_bc', 'bons_commande', 'bons_commande', $id);
        flash('success', 'Bon de commande validé et émis.');
    }

    if ($action === 'recevoir' && $id) {
        $quantites = $_POST['quantite_recue'] ?? [];
        foreach ($quantites as $ligneId => $qte) {
            $qte = (int)$qte;
            $ligne = $db->prepare("SELECT * FROM lignes_bon_commande WHERE id=?")->execute([$ligneId]) ? $db->query("SELECT * FROM lignes_bon_commande WHERE id=" . (int)$ligneId)->fetch() : null;
            if ($ligne) {
                $db->prepare("UPDATE lignes_bon_commande SET quantite_recue=? WHERE id=?")->execute([min($qte, $ligne['quantite']), $ligneId]);
            }
        }
        // Check if fully received
        $bc = $db->prepare("SELECT bc.id, COUNT(l.id) as total, SUM(CASE WHEN l.quantite_recue >= l.quantite THEN 1 ELSE 0 END) as recu FROM bons_commande bc JOIN lignes_bon_commande l ON l.bon_commande_id=bc.id WHERE bc.id=? GROUP BY bc.id");
        $bc->execute([$id]);
        $bcData = $bc->fetch();
        if ($bcData && $bcData['total'] == $bcData['recu']) {
            $db->prepare("UPDATE bons_commande SET statut='recu_total' WHERE id=?")->execute([$id]);
        } elseif ($bcData && $bcData['recu'] > 0) {
            $db->prepare("UPDATE bons_commande SET statut='recu_partiel' WHERE id=?")->execute([$id]);
        }
        auditLog('recevoir_bc', 'bons_commande', 'bons_commande', $id);
        flash('success', 'Réception mise à jour.');
    }

    if ($action === 'cancel' && $id) {
        $db->prepare("UPDATE bons_commande SET statut='annule' WHERE id=?")->execute([$id]);
        auditLog('annuler_bc', 'bons_commande', 'bons_commande', $id);
        flash('warning', 'Bon de commande annulé.');
    }

    if ($action === 'to_invoice' && $id) {
        $db->prepare("UPDATE bons_commande SET statut='facture' WHERE id=?")->execute([$id]);
        flash('success', 'Bon marqué comme facturé.');
    }

    header('Location: ' . ($_POST['redirect'] ?? 'index.php'));
    exit;
}

$filterStatus = $_GET['statut'] ?? '';
$bons = getBonsCommande($filterStatus ?: null);

$badges = ['brouillon'=>'gray','emis'=>'info','recu_partiel'=>'warning','recu_total'=>'success','facture'=>'teal','annule'=>'danger'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-20">
    <div>
        <h2 style="margin:0"><i class="fa-solid fa-file-invoice"></i> Bons de commande</h2>
        <p style="color:var(--text3);margin:4px 0 0">Gestion des commandes fournisseurs</p>
    </div>
    <div style="display:flex;gap:8px">
        <?= exportButtons() ?>
        <a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau BC</a>
    </div>
</div>

<!-- Filtres statut -->
<div class="mb-20" style="display:flex;gap:6px;flex-wrap:wrap">
    <a href="index.php" class="btn <?= $filterStatus ? 'btn-outline' : 'btn-primary' ?> btn-sm">Tous</a>
    <?php foreach (['brouillon','emis','recu_partiel','recu_total','facture','annule'] as $s): ?>
    <a href="?statut=<?= $s ?>" class="btn <?= $filterStatus === $s ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <?= ucfirst(str_replace('_',' ',$s)) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>N° BC</th>
                    <th>Engagement</th>
                    <th>Fournisseur</th>
                    <th class="hide-mobile">Date</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bons)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:40px">Aucun bon de commande.</td></tr>
                <?php else: foreach ($bons as $bc): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($bc['numero']) ?></td>
                    <td><?= htmlspecialchars($bc['eng_numero']) ?></td>
                    <td><?= htmlspecialchars($bc['fournisseur_nom']) ?></td>
                    <td class="hide-mobile"><?= date('d/m/Y', strtotime($bc['date_emission'])) ?></td>
                    <td class="amount"><?= formatMontant($bc['montant_total']) ?></td>
                    <td><span class="badge badge-<?= $badges[$bc['statut']] ?? 'gray' ?>"><?= ucfirst(str_replace('_',' ',$bc['statut'])) ?></span></td>
                    <td>
                        <a href="detail.php?id=<?= $bc['id'] ?>" class="btn btn-outline btn-sm" title="Détail"><i class="fa-solid fa-eye"></i></a>
                        <?php if ($bc['statut'] === 'brouillon' && hasPermission('bons_commande','valider')): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="validate">
                            <input type="hidden" name="id" value="<?= $bc['id'] ?>">
                            <button class="btn btn-success btn-sm" title="Valider"><i class="fa-solid fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
