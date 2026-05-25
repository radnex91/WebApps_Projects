<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('bons_commande');
$pageTitle = 'Détail bon de commande';

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$bc = $db->prepare("SELECT bc.*, de.numero as eng_numero, de.objet as eng_objet, de.montant as eng_montant,
    f.nom as fournisseur_nom, f.adresse as fournisseur_adr, f.telephone as fournisseur_tel, f.email as fournisseur_email,
    CONCAT(u.nom,' ',u.prenom) as cree_par_nom
    FROM bons_commande bc
    JOIN demandes_engagement de ON bc.engagement_id = de.id
    JOIN fournisseurs f ON bc.fournisseur_id = f.id
    LEFT JOIN utilisateurs u ON bc.cree_par = u.id
    WHERE bc.id=?");
$bc->execute([$id]);
$bc = $bc->fetch();
if (!$bc) { flash('danger', 'Bon de commande introuvable.'); header('Location: index.php'); exit; }

$lignes = $db->prepare("SELECT * FROM lignes_bon_commande WHERE bon_commande_id=? ORDER BY ordre");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll();

$entreprise = getEntreprise();
$badges = ['brouillon'=>'gray','emis'=>'info','recu_partiel'=>'warning','recu_total'=>'success','facture'=>'teal','annule'=>'danger'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-20">
    <a href="index.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
</div>

<div class="card mb-20" id="bc-print">
    <!-- En-tête BC -->
    <div style="display:flex;justify-content:space-between;align-items:start;padding:20px;border-bottom:2px solid var(--primary)">
        <div>
            <?php if ($entreprise['logo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($entreprise['logo']) ?>" height="50" alt="Logo">
            <?php endif; ?>
            <h3 style="margin:4px 0 0"><?= htmlspecialchars($entreprise['nom'] ?? 'BrenFinance') ?></h3>
            <div style="font-size:12px;color:var(--text3)">
                <?php if ($entreprise['registre_commerce']): ?>RC: <?= htmlspecialchars($entreprise['registre_commerce']) ?> | <?php endif; ?>
                <?php if ($entreprise['numero_contribuable']): ?>NIU: <?= htmlspecialchars($entreprise['numero_contribuable']) ?><?php endif; ?>
            </div>
        </div>
        <div style="text-align:right">
            <h2 style="margin:0;color:var(--primary)">BON DE COMMANDE</h2>
            <div style="font-size:22px;font-weight:700;margin:8px 0"><?= htmlspecialchars($bc['numero']) ?></div>
            <div style="font-size:12px">Date: <?= date('d/m/Y', strtotime($bc['date_emission'])) ?></div>
        </div>
    </div>

    <div style="padding:20px">
        <!-- Fournisseur -->
        <div class="mb-20">
            <strong style="color:var(--text3);text-transform:uppercase;font-size:11px">Fournisseur</strong>
            <div style="font-weight:600;font-size:15px"><?= htmlspecialchars($bc['fournisseur_nom']) ?></div>
            <div style="font-size:13px;color:var(--text2)"><?= htmlspecialchars($bc['fournisseur_adr'] ?? '') ?></div>
            <?php if ($bc['fournisseur_tel']): ?><div style="font-size:13px;color:var(--text2)">Tél: <?= htmlspecialchars($bc['fournisseur_tel']) ?></div><?php endif; ?>
        </div>

        <!-- Référence engagement -->
        <div class="mb-20">
            <strong style="color:var(--text3);text-transform:uppercase;font-size:11px">Engagement</strong>
            <div><?= htmlspecialchars($bc['eng_numero'] . ' — ' . $bc['eng_objet']) ?></div>
            <div style="font-size:12px;color:var(--text3)">Montant engagé: <?= formatMontant($bc['eng_montant']) ?></div>
        </div>

        <?php if ($bc['date_livraison_prevue']): ?>
        <div class="mb-20"><strong style="color:var(--text3);font-size:11px;text-transform:uppercase">Livraison prévue</strong>
            <div><?= date('d/m/Y', strtotime($bc['date_livraison_prevue'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($bc['commentaire']): ?>
        <div class="mb-20"><strong style="color:var(--text3);font-size:11px;text-transform:uppercase">Commentaire</strong>
            <div><?= htmlspecialchars($bc['commentaire']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Lignes -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
            <thead>
                <tr style="background:var(--surface2)">
                    <th style="padding:10px;text-align:left;border-bottom:2px solid var(--border)">Désignation</th>
                    <th style="padding:10px;text-align:center;border-bottom:2px solid var(--border);width:70px">Qté</th>
                    <th style="padding:10px;text-align:center;border-bottom:2px solid var(--border);width:70px">Unité</th>
                    <th style="padding:10px;text-align:right;border-bottom:2px solid var(--border);width:110px">Prix unitaire</th>
                    <th style="padding:10px;text-align:right;border-bottom:2px solid var(--border);width:120px">Montant</th>
                    <?php if ($bc['statut'] != 'brouillon'): ?>
                    <th style="padding:10px;text-align:center;border-bottom:2px solid var(--border);width:90px">Reçu</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                <tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:8px 10px"><?= htmlspecialchars($l['libelle']) ?></td>
                    <td style="padding:8px 10px;text-align:center"><?= (int)$l['quantite'] ?></td>
                    <td style="padding:8px 10px;text-align:center"><?= htmlspecialchars($l['unite']) ?></td>
                    <td style="padding:8px 10px;text-align:right"><?= number_format($l['prix_unitaire'],0,',',' ') ?></td>
                    <td style="padding:8px 10px;text-align:right;font-weight:600"><?= formatMontant($l['montant_ligne']) ?></td>
                    <?php if ($bc['statut'] != 'brouillon'): ?>
                    <td style="padding:8px 10px;text-align:center">
                        <?php if ($l['quantite_recue'] >= $l['quantite']): ?>
                        <span style="color:var(--success)"><i class="fa-solid fa-check"></i> <?= $l['quantite_recue'] ?>/<?= $l['quantite'] ?></span>
                        <?php else: ?>
                        <span><?= $l['quantite_recue'] ?>/<?= $l['quantite'] ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;font-size:16px">
                    <td colspan="<?= $bc['statut'] != 'brouillon' ? '4' : '4' ?>" style="padding:12px 10px;text-align:right;border-top:2px solid var(--border)">TOTAL</td>
                    <td style="padding:12px 10px;text-align:right;border-top:2px solid var(--border)"><?= formatMontant($bc['montant_total']) ?></td>
                    <?php if ($bc['statut'] != 'brouillon'): ?><td style="border-top:2px solid var(--border)"></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>

        <div style="font-size:13px;color:var(--text2);font-style:italic;margin-bottom:20px">
            Arrêté le présent bon de commande à la somme de :
            <strong><?= htmlspecialchars(montantEnLettres($bc['montant_total'], 'francs CFA', 'centimes')) ?></strong>
        </div>

        <!-- Statut badge -->
        <div style="margin-bottom:16px">
            <span class="badge badge-<?= $badges[$bc['statut']] ?? 'gray' ?>" style="font-size:14px;padding:6px 14px">
                Statut: <?= ucfirst(str_replace('_',' ',$bc['statut'])) ?>
            </span>
        </div>
    </div>

    <!-- Réception form -->
    <?php if (in_array($bc['statut'], ['emis', 'recu_partiel'])): ?>
    <div style="padding:20px;border-top:1px solid var(--border);background:var(--surface2)">
        <h4><i class="fa-solid fa-boxes-stacked"></i> Réception de marchandises</h4>
        <form method="POST">
            <input type="hidden" name="action" value="recevoir">
            <input type="hidden" name="id" value="<?= $bc['id'] ?>">
            <input type="hidden" name="redirect" value="detail.php?id=<?= $bc['id'] ?>">
            <table style="width:100%;margin-bottom:12px">
                <?php foreach ($lignes as $l): ?>
                <tr>
                    <td style="padding:4px 8px"><?= htmlspecialchars($l['libelle']) ?></td>
                    <td style="width:140px">
                        <input type="number" name="quantite_recue[<?= $l['id'] ?>]" class="form-control form-control-sm"
                               value="<?= $l['quantite_recue'] ?>" min="0" max="<?= $l['quantite'] ?>">
                    </td>
                    <td style="font-size:12px;color:var(--text3)">/ <?= $l['quantite'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i> Mettre à jour la réception</button>
                <?php if (hasPermission('bons_commande','valider')): ?>
                <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('form-to-invoice').submit()">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Marquer comme facturé
                </button>
                <?php endif;  ?>
            </div>
        </form>
        <form id="form-to-invoice" method="POST" style="display:none">
            <input type="hidden" name="action" value="to_invoice">
            <input type="hidden" name="id" value="<?= $bc['id'] ?>">
            <input type="hidden" name="redirect" value="detail.php?id=<?= $bc['id'] ?>">
        </form>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <?php if (in_array($bc['statut'], ['brouillon'])): ?>
    <div style="padding:20px;border-top:1px solid var(--border);display:flex;gap:8px">
        <?php if (hasPermission('bons_commande','valider')): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="validate">
            <input type="hidden" name="id" value="<?= $bc['id'] ?>">
            <input type="hidden" name="redirect" value="detail.php?id=<?= $bc['id'] ?>">
            <button class="btn btn-success"><i class="fa-solid fa-check"></i> Valider et émettre</button>
        </form>
        <?php endif; ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?= $bc['id'] ?>">
            <input type="hidden" name="redirect" value="detail.php?id=<?= $bc['id'] ?>">
            <button class="btn btn-danger" onclick="return confirm('Annuler ce bon de commande ?')"><i class="fa-solid fa-ban"></i> Annuler</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
