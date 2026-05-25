<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('paie');
$pageTitle = 'Déclarations sociales';

$db = getDB();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_declaration') {
        $periodeId = (int)($_POST['periode_id'] ?? 0);
        $type = $_POST['type_declaration'] ?? 'cnps';
        $reference = sanitize($_POST['reference'] ?? '');
        $dateDeclaration = $_POST['date_declaration'] ?? date('Y-m-d');
        $dateEcheance = $_POST['date_echeance'] ?? '';

        // Calculate total for this type
        if ($type === 'cnps') {
            $montant = $db->prepare("SELECT COALESCE(SUM(lb.montant),0) FROM lignes_bulletin lb JOIN bulletins_paie bp ON lb.bulletin_id=bp.id WHERE bp.periode_id=? AND lb.type IN ('retenue','cotisation_patronale') AND lb.libelle LIKE '%CNPS%'");
            $montant->execute([$periodeId]);
            $montant = $montant->fetchColumn();
        } elseif ($type === 'dgi_irpp') {
            $montant = $db->prepare("SELECT COALESCE(SUM(lb.montant),0) FROM lignes_bulletin lb JOIN bulletins_paie bp ON lb.bulletin_id=bp.id WHERE bp.periode_id=? AND lb.libelle LIKE '%IRPP%'");
            $montant->execute([$periodeId]);
            $montant = $montant->fetchColumn();
        } else {
            $montant = $db->prepare("SELECT COALESCE(SUM(bp.net_a_payer + bp.charges_patronales),0) FROM bulletins_paie bp WHERE bp.periode_id=?");
            $montant->execute([$periodeId]);
            $montant = $montant->fetchColumn();
        }

        $db->prepare("INSERT INTO declarations_paie (periode_id, type, reference, montant_declare, date_declaration, date_echeance, statut)
                      VALUES (?,?,?,?,?,?,'declaree')")
           ->execute([$periodeId, $type, $reference, $montant, $dateDeclaration, $dateEcheance ?: null]);
        flash('success', 'Déclaration enregistrée.');
        header('Location: declarations.php'); exit;
    }

    if ($action === 'mark_paid') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE declarations_paie SET statut='payee' WHERE id=?")->execute([$id]);
        flash('success', 'Déclaration marquée comme payée.');
        header('Location: declarations.php'); exit;
    }
}

$periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$declarations = $db->query("SELECT d.*, pp.mois, e.code as exercice_code
    FROM declarations_paie d
    JOIN periodes_paie pp ON d.periode_id = pp.id
    JOIN exercices e ON pp.exercice_id = e.id
    ORDER BY d.date_declaration DESC LIMIT 50")->fetchAll();

$periodesValides = $db->query("SELECT pp.*, e.code as exercice_code FROM periodes_paie pp JOIN exercices e ON pp.exercice_id=e.id WHERE pp.statut IN ('cloture','valide') ORDER BY e.date_debut DESC, pp.mois DESC")->fetchAll();
$moisLabels = ['', 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

$typeLabels = ['cnps'=>'Déclaration CNPS','dgi_irpp'=>'IRPP (Retenues salariales)','dgi_cac'=>'CAC / Impôt société','etat_paie'=>'État de paie global'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="index.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour au module paie</a>
</div>

<div class="d-flex justify-between align-center mb-20">
    <h2 style="margin:0"><i class="fa-solid fa-landmark"></i> Déclarations sociales & fiscales</h2>
    <button class="btn btn-primary" onclick="openModal('modal-declaration')"><i class="fa-solid fa-plus"></i> Nouvelle déclaration</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Type</th><th>Référence</th><th>Période</th><th>Montant</th><th>Date déclaration</th><th>Échéance</th><th>Statut</th><th></th>
            </tr></thead>
            <tbody>
                <?php if (empty($declarations)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:40px">Aucune déclaration enregistrée.</td></tr>
                <?php else: foreach ($declarations as $d): ?>
                <tr>
                    <td><span class="badge"><?= $typeLabels[$d['type']] ?? $d['type'] ?></span></td>
                    <td><?= htmlspecialchars($d['reference'] ?? '—') ?></td>
                    <td><?= $moisLabels[(int)$d['mois']] ?> <?= htmlspecialchars($d['exercice_code']) ?></td>
                    <td class="amount"><?= formatMontant($d['montant_declare']) ?></td>
                    <td><?= date('d/m/Y', strtotime($d['date_declaration'])) ?></td>
                    <td><?= $d['date_echeance'] ? date('d/m/Y', strtotime($d['date_echeance'])) : '—' ?></td>
                    <td>
                        <?php $cols = ['brouillon'=>'gray','declaree'=>'info','payee'=>'success']; ?>
                        <span class="badge badge-<?= $cols[$d['statut']] ?? 'gray' ?>"><?= ucfirst($d['statut']) ?></span>
                    </td>
                    <td>
                        <?php if ($d['statut'] === 'declaree'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button class="btn btn-success btn-sm" title="Marquer comme payée"><i class="fa-solid fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal-declaration">
    <div class="modal">
        <div class="modal-header"><span class="modal-title">Nouvelle déclaration</span><button class="modal-close" onclick="closeModal('modal-declaration')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_declaration">
            <div class="modal-body">
                <div class="form-group"><label>Type</label>
                    <select name="type_declaration" class="form-control" required>
                        <option value="cnps">Déclaration CNPS</option>
                        <option value="dgi_irpp">IRPP / Retenues à la source</option>
                        <option value="dgi_cac">CAC / Impôt sur les sociétés</option>
                        <option value="etat_paie">État de paie global</option>
                    </select>
                </div>
                <div class="form-group"><label>Période</label>
                    <select name="periode_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($periodesValides as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $moisLabels[(int)$p['mois']] ?> <?= htmlspecialchars($p['exercice_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Référence</label><input type="text" name="reference" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label>Date déclaration</label><input type="date" name="date_declaration" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Date échéance</label><input type="date" name="date_echeance" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-declaration')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
