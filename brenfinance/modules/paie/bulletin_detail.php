<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('paie');
$pageTitle = 'Détail bulletin';

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$bulletin = $db->prepare("SELECT bp.*, pp.mois, e.code as exercice_code, CONCAT(u.nom,' ',u.prenom) as employe_nom, u.matricule
    FROM bulletins_paie bp
    JOIN periodes_paie pp ON bp.periode_id = pp.id
    JOIN exercices e ON pp.exercice_id = e.id
    JOIN utilisateurs u ON bp.utilisateur_id = u.id
    WHERE bp.id=?");
$bulletin->execute([$id]);
$bulletin = $bulletin->fetch();
if (!$bulletin) { flash('danger', 'Bulletin introuvable.'); header('Location: index.php'); exit; }

$lignes = $db->prepare("SELECT * FROM lignes_bulletin WHERE bulletin_id=? ORDER BY FIELD(type,'gain','indemnite','retenue','cotisation_patronale'), ordre");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll();

$entreprise = getEntreprise();
$moisLabels = ['', 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="bulletins.php?periode_id=<?= $bulletin['periode_id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
</div>

<div class="card" id="bulletin-print" style="max-width:800px;margin:0 auto">
    <!-- En-tête bulletin -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:20px;border-bottom:2px solid var(--primary)">
        <div>
            <?php if ($entreprise['logo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($entreprise['logo']) ?>" height="40" alt="Logo">
            <?php endif; ?>
            <strong><?= htmlspecialchars($entreprise['nom'] ?? 'BrenFinance') ?></strong>
        </div>
        <div style="text-align:right">
            <h3 style="margin:0;color:var(--primary)">BULLETIN DE PAIE</h3>
            <div style="font-size:13px"><?= $moisLabels[(int)$bulletin['mois']] ?> <?= htmlspecialchars($bulletin['exercice_code']) ?></div>
        </div>
    </div>

    <div style="padding:20px">
        <!-- Infos employé -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;font-size:13px">
            <div>
                <div style="color:var(--text3);font-size:11px;text-transform:uppercase">Employé</div>
                <strong><?= htmlspecialchars($bulletin['employe_nom']) ?></strong>
            </div>
            <div>
                <div style="color:var(--text3);font-size:11px;text-transform:uppercase">Matricule</div>
                <?= htmlspecialchars($bulletin['matricule'] ?? '—') ?>
            </div>
        </div>

        <!-- Lignes -->
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--surface2)">
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid var(--border);font-size:12px;text-transform:uppercase">Rubrique</th>
                    <th style="padding:8px 10px;text-align:right;border-bottom:2px solid var(--border);font-size:12px;width:130px">Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sections = ['gain'=>'Gains','indemnite'=>'Indemnités','retenue'=>'Retenues','cotisation_patronale'=>'Cotisations patronales'];
                $currentSection = '';
                foreach ($lignes as $l):
                    if ($currentSection !== $l['type'] && $currentSection !== ''): ?>
                    <tr><td colspan="2" style="height:12px"></td></tr>
                    <?php endif;
                    $currentSection = $l['type'];
                ?>
                <tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:6px 10px;font-size:13px"><?= htmlspecialchars($l['libelle']) ?></td>
                    <td style="padding:6px 10px;text-align:right;font-weight:600;font-size:13px;
                        <?= $l['type'] === 'gain' || $l['type'] === 'indemnite' ? 'color:var(--success)' : ($l['type'] === 'cotisation_patronale' ? 'color:var(--text2)' : 'color:var(--danger)') ?>">
                        <?= in_array($l['type'], ['gain','indemnite']) ? '+' : '-' ?><?= number_format($l['montant'],0,',',' ') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totaux -->
        <div style="margin-top:16px;border-top:2px solid var(--border);padding-top:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:14px">
                <span style="color:var(--text3)">Total gains</span>
                <strong style="color:var(--success)"><?= formatMontant($bulletin['total_gains']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:14px">
                <span style="color:var(--text3)">Total retenues</span>
                <strong style="color:var(--danger)">-<?= formatMontant($bulletin['total_retenues']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin:12px 0;padding:12px;background:var(--primary);color:#fff;border-radius:var(--radius);font-size:16px">
                <span>NET À PAYER</span>
                <strong><?= formatMontant($bulletin['net_a_payer']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:13px">
                <span style="color:var(--text3)">Charges patronales</span>
                <strong><?= formatMontant($bulletin['charges_patronales']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px">
                <span style="color:var(--text3)">Coût total employeur</span>
                <strong><?= formatMontant($bulletin['cout_total']) ?></strong>
            </div>
        </div>

        <div style="margin-top:12px;font-size:12px;color:var(--text2);font-style:italic">
            Net à payer en lettres: <strong><?= htmlspecialchars(montantEnLettres($bulletin['net_a_payer'], 'francs CFA', 'centimes')) ?></strong>
        </div>

        <?php if ($bulletin['statut'] === 'paye'): ?>
        <div style="margin-top:12px;padding:10px;background:var(--success);color:#fff;border-radius:var(--radius);font-size:13px;text-align:center">
            Payé le <?= date('d/m/Y', strtotime($bulletin['date_paiement'] ?? '')) ?>
            <?php if ($bulletin['reference_paiement']): ?> — Réf: <?= htmlspecialchars($bulletin['reference_paiement']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paiement form -->
    <?php if ($bulletin['statut'] === 'valide'): ?>
    <div style="padding:20px;border-top:1px solid var(--border);background:var(--surface2)">
        <h4><i class="fa-solid fa-coins"></i> Enregistrer le paiement</h4>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="payer_employe">
            <input type="hidden" name="bulletin_id" value="<?= $bulletin['id'] ?>">
            <input type="hidden" name="redirect" value="bulletin_detail.php?id=<?= $bulletin['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label>Net en faveur</label>
                    <input type="number" name="net_en_faveur" class="form-control" value="<?= $bulletin['net_a_payer'] ?>" step="0.01" required>
                </div>
                <div class="form-group"><label>Date paiement</label>
                    <input type="date" name="date_paiement" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group"><label>Référence</label>
                    <input type="text" name="reference_paiement" class="form-control" placeholder="N° virement / chèque">
                </div>
            </div>
            <button class="btn btn-success"><i class="fa-solid fa-check"></i> Confirmer le paiement</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
