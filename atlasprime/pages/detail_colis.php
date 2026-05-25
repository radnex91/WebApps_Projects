<?php
$pageTitle = 'Détail du colis';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('colis_voir');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: colis.php'); exit; }

$colis = Database::fetchOne(
    "SELECT c.*, 
            ad.nom agd, ad.nom vd, ad.adresse aad, ad.telephone td, ad.code cod,
            aa.nom aga, aa.nom va, aa.adresse aaa, aa.telephone ta, aa.code coa,
            v.numero_voyage, v.transporteur, v.date_depart, v.matricule_vehicule, v.chauffeur, v.statut AS statut_voyage,
            CONCAT(u.prenom,' ',u.nom) AS operateur, u.role AS operateur_role
     FROM colis c 
     JOIN agences ad ON c.agence_depart_id=ad.id 
     JOIN agences aa ON c.agence_arrivee_id=aa.id
     LEFT JOIN voyages v ON c.voyage_id=v.id 
     JOIN utilisateurs u ON c.cree_par=u.id
     WHERE c.id=?", [$id]
);

if (!$colis) { $_SESSION['flash']=['type'=>'error','message'=>'Colis introuvable']; header('Location: colis.php'); exit; }

$historique = Database::fetchAll(
    "SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS agent
     FROM suivi_colis s JOIN utilisateurs u ON s.cree_par=u.id
     WHERE s.colis_id=? ORDER BY s.created_at DESC", [$id]
);

$detailsColis = Database::fetchAll(
    "SELECT * FROM colis_details WHERE colis_id=? ORDER BY id", [$id]
);

// POST : actions de livraison
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Auth::requirePermission('colis_livrer');
    $action = $_POST['action'];
    $localisation = $colis['va'] ?? '';
    if ($action === 'livrer') {
        transitionColisStatut($id, 'en_livraison', $localisation, 'Colis arrive a destination', $user['id']);
    } elseif ($action === 'confirmer_livre') {
        transitionColisStatut($id, 'livre', $localisation, 'Colis livre au destinataire', $user['id'], true);
    } elseif ($action === 'retourner') {
        transitionColisStatut($id, 'retourne', $localisation, 'Colis retourne', $user['id']);
    } elseif ($action === 'perdu') {
        transitionColisStatut($id, 'perdu', $localisation, 'Colis declare perdu', $user['id']);
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Statut mis à jour.'];
    header("Location: detail_colis.php?id=$id");
    exit;
}

$statuts = ['enregistre','en_transit','en_livraison','livre','retourne','perdu'];
$statutIcons = ['enregistre'=>'fa-box','en_transit'=>'fa-truck-moving','en_livraison'=>'fa-person-biking',
                'livre'=>'fa-circle-check','retourne'=>'fa-rotate-left','perdu'=>'fa-circle-question'];
$statutColors = ['enregistre'=>'#0EA5E9','en_transit'=>'#D97706','en_livraison'=>'#7C3AED',
                 'livre'=>'#16A34A','retourne'=>'#6B7280','perdu'=>'#DC2626'];
$currentStep = array_search($colis['statut'], $statuts);
?>

<div style="display:flex;gap:12px;align-items:center;margin-bottom:24px">
    <a href="colis.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    <h2 style="font-size:1.2rem;font-weight:700;flex:1">
        Colis <span class="colis-num"><?= $colis['numero_colis'] ?></span>
    </h2>
    <a href="bordereau_print.php?id=<?= $id ?>" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-print"></i> Bordereau
    </a>
    <a href="edit_colis.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-pencil"></i> Modifier
    </a>
</div>

<!-- PROGRESS BAR -->
<div class="card mb-3">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;padding:8px 0">
        <!-- Line -->
        <div style="position:absolute;top:22px;left:5%;right:5%;height:2px;background:var(--border);z-index:0"></div>
        <div style="position:absolute;top:22px;left:5%;height:2px;background:var(--primary);z-index:0;
             width:<?= max(0, min(100, ($currentStep / (count($statuts)-1)) * 90)) ?>%;transition:width 0.5s"></div>
        
        <?php foreach ($statuts as $i => $s): 
            $done    = $i <= $currentStep;
            $current = $i === $currentStep;
            $color   = $statutColors[$s];
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;position:relative;z-index:1">
            <div style="width:36px;height:36px;border-radius:50%;
                 background:<?= $done ? $color : 'var(--bg-card2)' ?>;
                 border:2px solid <?= $done ? $color : 'var(--border)' ?>;
                 display:flex;align-items:center;justify-content:center;
                 font-size:.85rem;color:<?= $done ? 'white' : 'var(--text-dim)' ?>;
                 box-shadow:<?= $current ? "0 0 0 4px rgba(0,0,0,0.3), 0 0 0 6px $color" : 'none' ?>">
                <i class="fas <?= $statutIcons[$s] ?>"></i>
            </div>
            <div style="font-size:.68rem;color:<?= $done ? 'var(--text)' : 'var(--text-dim)' ?>;
                 margin-top:6px;text-align:center;font-weight:<?= $current?'700':'400' ?>">
                <?= ucfirst(str_replace('_',' ',$s)) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px">
    <!-- LEFT COLUMN -->
    <div>
        <!-- EXPÉDITEUR / DESTINATAIRE -->
        <div class="card mb-2">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
                <div style="padding:16px 20px;border-right:1px solid var(--border)">
                    <div style="font-size:.72rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
                        <i class="fas fa-user-circle"></i> Expéditeur
                    </div>
                    <div style="font-size:1rem;font-weight:700"><?= htmlspecialchars($colis['expediteur_nom']) ?></div>
                    <div style="font-family:var(--font-mono);font-size:.85rem;color:var(--text-muted);margin-top:4px">
                        <?= htmlspecialchars($colis['expediteur_telephone']) ?>
                    </div>
                    <?php if ($colis['expediteur_ville']): ?>
                    <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px">
                        <i class="fas fa-location-dot" style="margin-right:4px;color:var(--primary)"></i>
                        <?= htmlspecialchars($colis['expediteur_ville']) ?>
                        <?= $colis['expediteur_adresse'] ? ', '.htmlspecialchars($colis['expediteur_adresse']) : '' ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
                        <div style="font-size:.72rem;font-weight:700;color:var(--primary);text-transform:uppercase;margin-bottom:4px">
                            <i class="fas fa-building" style="margin-right:3px"></i> Agence de départ
                        </div>
                        <div style="font-size:.9rem;font-weight:600"><?= htmlspecialchars($colis['agd']) ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted)">
                            <i class="fas fa-location-dot" style="margin-right:3px"></i><?= htmlspecialchars($colis['vd']) ?><?= $colis['aad'] ? ' — '.htmlspecialchars($colis['aad']) : '' ?>
                        </div>
                        <?php if ($colis['td']): ?>
                        <div style="font-size:.78rem;color:var(--text-muted);font-family:var(--font-mono)">
                            <i class="fas fa-phone" style="margin-right:3px"></i><?= htmlspecialchars($colis['td']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="padding:16px 20px">
                    <div style="font-size:.72rem;font-weight:700;color:#16A34A;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
                        <i class="fas fa-user-check"></i> Destinataire
                    </div>
                    <div style="font-size:1rem;font-weight:700"><?= htmlspecialchars($colis['destinataire_nom']) ?></div>
                    <div style="font-family:var(--font-mono);font-size:.85rem;color:var(--text-muted);margin-top:4px">
                        <?= htmlspecialchars($colis['destinataire_telephone']) ?>
                    </div>
                    <?php if ($colis['destinataire_ville']): ?>
                    <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px">
                        <i class="fas fa-location-dot" style="margin-right:4px;color:#16A34A"></i>
                        <?= htmlspecialchars($colis['destinataire_ville']) ?>
                        <?= $colis['destinataire_adresse'] ? ', '.htmlspecialchars($colis['destinataire_adresse']) : '' ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
                        <div style="font-size:.72rem;font-weight:700;color:#16A34A;text-transform:uppercase;margin-bottom:4px">
                            <i class="fas fa-building" style="margin-right:3px"></i> Agence d'arrivée
                        </div>
                        <div style="font-size:.9rem;font-weight:600"><?= htmlspecialchars($colis['aga']) ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted)">
                            <i class="fas fa-location-dot" style="margin-right:3px"></i><?= htmlspecialchars($colis['va']) ?><?= $colis['aaa'] ? ' — '.htmlspecialchars($colis['aaa']) : '' ?>
                        </div>
                        <?php if ($colis['ta']): ?>
                        <div style="font-size:.78rem;color:var(--text-muted);font-family:var(--font-mono)">
                            <i class="fas fa-phone" style="margin-right:3px"></i><?= htmlspecialchars($colis['ta']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DÉTAILS COLIS — LIGNES -->
        <div class="card mb-2">
            <div class="card-header"><div class="card-title"><i class="fas fa-cube" style="margin-right:6px;color:var(--primary)"></i>Contenu du colis</div></div>
            <?php if (!empty($detailsColis)): ?>
            <div style="overflow-x:auto">
                <table class="data-table" style="font-size:.85rem">
                    <thead><tr>
                        <th>#</th>
                        <th>Description</th>
                        <th style="text-align:right">Poids</th>
                        <th style="text-align:right">Pièces</th>
                        <th style="text-align:right">Valeur</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($detailsColis as $i => $d): ?>
                    <tr>
                        <td style="color:var(--text-muted)"><?= $i + 1 ?></td>
                        <td style="font-weight:600"><?= htmlspecialchars($d['description']) ?></td>
                        <td style="text-align:right;font-family:var(--font-mono)"><?= $d['poids'] > 0 ? number_format($d['poids'],2,',',' ').' kg' : '—' ?></td>
                        <td style="text-align:right;font-family:var(--font-mono)"><?= intval($d['nombre_pieces']) ?></td>
                        <td style="text-align:right;font-family:var(--font-mono)"><?= $d['valeur_declaree'] > 0 ? number_format($d['valeur_declaree'],0,',',' ').' F' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr style="border-top:2px solid var(--border-strong)">
                        <td colspan="2" style="font-weight:700">Total</td>
                        <td style="text-align:right;font-family:var(--font-mono);font-weight:700"><?= number_format($colis['poids'],2,',',' ') ?> kg</td>
                        <td style="text-align:right;font-family:var(--font-mono);font-weight:700"><?= intval($colis['nombre_pieces']) ?></td>
                        <td style="text-align:right;font-family:var(--font-mono);font-weight:700"><?= number_format((float)$colis['valeur_declaree'],0,',',' ') ?> F</td>
                    </tr></tfoot>
                </table>
            </div>
            <?php else: ?>
            <div style="margin-bottom:12px">
                <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px">Description</div>
                <div style="font-size:.9rem"><?= htmlspecialchars($colis['description']) ?></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center">
                    <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Poids</div>
                    <div style="font-family:var(--font-mono);font-size:1.1rem;font-weight:700"><?= htmlspecialchars($colis['poids'] ?: '—') ?> <span style="font-size:.75rem">kg</span></div>
                </div>
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center">
                    <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Pièces</div>
                    <div style="font-family:var(--font-mono);font-size:1.1rem;font-weight:700"><?= htmlspecialchars($colis['nombre_pieces']) ?></div>
                </div>
                <div style="background:var(--bg-card2);border-radius:8px;padding:12px;text-align:center">
                    <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Valeur déclarée</div>
                    <div style="font-family:var(--font-mono);font-size:1rem;font-weight:700"><?= number_format((float)$colis['valeur_declaree'],0,',',' ') ?> <span style="font-size:.72rem">F</span></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($colis['notes']): ?>
            <div style="margin-top:12px;background:rgba(245,166,35,0.08);border:1px solid rgba(245,166,35,0.15);border-radius:8px;padding:10px">
                <div style="font-size:.72rem;font-weight:700;color:var(--accent);margin-bottom:4px"><i class="fas fa-note-sticky"></i> Notes</div>
                <div style="font-size:.82rem"><?= nl2br(htmlspecialchars($colis['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- VOYAGE (si accompagné) -->
        <?php 
// Récupérer tous les colis du même voyage si applicable
$colisDuVoyage = [];
if (!empty($colis['voyage_id'])) {
    $colisDuVoyage = Database::fetchAll(
        "SELECT c.*, 
                ad.nom agd_nom, ad.nom agd_ville,
                aa.nom aga_nom, aa.nom aga_ville
         FROM colis c 
         JOIN agences ad ON c.agence_depart_id=ad.id 
         JOIN agences aa ON c.agence_arrivee_id=aa.id
         WHERE c.voyage_id=?", 
        [$colis['voyage_id']]
    );
} else {
    // Si pas de voyage, on ne montre que ce colis
    $colisDuVoyage = [$colis];
}
?>
<?php if (!empty($colisDuVoyage)): ?>
        <?php if (count($colisDuVoyage) > 1): ?>
        <div class="card mb-2">
            <div class="card-header"><div class="card-title"><i class="fas fa-boxes" style="margin-right:6px;color:var(--primary)"></i>Colis du voyage #<?= $colis['numero_voyage'] ?? 'N/A' ?></div></div>
            <div style="overflow-x:auto;">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:8px;">N° Colis</th>
                            <th style="text-align:left;padding:8px;">Expéditeur</th>
                            <th style="text-align:left;padding:8px;">Destinataire</th>
                            <th style="text-align:left;padding:8px;">Poids (kg)</th>
                            <th style="text-align:left;padding:8px;">Pièces</th>
                            <th style="text-align:left;padding:8px;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colisDuVoyage as $c): ?>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid var(--border);"><span class="colis-num"><?= $c['numero_colis'] ?></span></td>
                            <td style="padding:8px;border-bottom:1px solid var(--border);"><?= htmlspecialchars($c['expediteur_nom']) ?></td>
                            <td style="padding:8px;border-bottom:1px solid var(--border);"><?= htmlspecialchars($c['destinataire_nom']) ?></td>
                            <td style="padding:8px;border-bottom:1px solid var(--border);font-family:var(--font-mono);"><?= htmlspecialchars($c['poids'] ?: '—') ?></td>
                            <td style="padding:8px;border-bottom:1px solid var(--border);"><?= htmlspecialchars($c['nombre_pieces']) ?></td>
                            <td style="padding:8px;border-bottom:1px solid var(--border);"><?= statutBadge($c['statut']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:8px;text-align:right;font-size:.85rem;color:var(--text-muted);">
                Total : <?= count($colisDuVoyage) ?> colis
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($colis['voyage_id'])): ?>
        <div class="card mb-2" style="border-color:rgba(124,58,237,0.3);background:rgba(124,58,237,0.04)">
            <div class="card-header">
                <div class="card-title" style="color:#A78BFA"><i class="fas fa-person-walking-luggage" style="margin-right:6px"></i>Voyage associé</div>
                <?= statutBadge($colis['statut_voyage']) ?>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:.85rem">
                <div>
                    <div style="color:var(--text-muted);font-size:.75rem">Numéro voyage</div>
                    <div class="colis-num" style="color:#A78BFA"><?= $colis['numero_voyage'] ?></div>
                </div>
                <div>
                    <div style="color:var(--text-muted);font-size:.75rem">Transporteur</div>
                    <div style="font-weight:600"><?= htmlspecialchars($colis['transporteur']) ?></div>
                </div>
                <?php if ($colis['matricule_vehicule']): ?>
                <div>
                    <div style="color:var(--text-muted);font-size:.75rem">Véhicule</div>
                    <div style="font-family:var(--font-mono)"><?= htmlspecialchars($colis['matricule_vehicule']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($colis['chauffeur']): ?>
                <div>
                    <div style="color:var(--text-muted);font-size:.75rem">Chauffeur</div>
                    <div><?= htmlspecialchars($colis['chauffeur']) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div style="color:var(--text-muted);font-size:.75rem">Date départ</div>
                    <div><?= formatDate($colis['date_depart'],'d/m/Y H:i') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- HISTORIQUE SUIVI -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-timeline" style="margin-right:6px;color:var(--primary)"></i>Historique de suivi</div>
            </div>
            <div style="position:relative">
                <?php foreach ($historique as $i => $h): ?>
                <div style="display:flex;gap:14px;margin-bottom:16px;position:relative">
                    <!-- Ligne verticale -->
                    <?php if ($i < count($historique)-1): ?>
                    <div style="position:absolute;left:14px;top:28px;bottom:-16px;width:2px;background:var(--border)"></div>
                    <?php endif; ?>
                    <!-- Icône -->
                    <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;
                         background:<?= $statutColors[$h['statut']] ?? '#555' ?>;
                         display:flex;align-items:center;justify-content:center;font-size:.72rem;color:white;z-index:1">
                        <i class="fas <?= $statutIcons[$h['statut']] ?? 'fa-circle' ?>"></i>
                    </div>
                    <!-- Contenu -->
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
                            <?= statutBadge($h['statut']) ?>
                            <?php if ($h['localisation']): ?>
                            <span style="font-size:.75rem;color:var(--text-muted)"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($h['localisation']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($h['commentaire']): ?>
                        <div style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($h['commentaire']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:.72rem;color:var(--text-dim);margin-top:3px">
                            <?= formatDate($h['created_at'],'d/m/Y à H:i') ?> — <?= htmlspecialchars($h['agent']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$historique): ?>
                <div class="empty-state"><i class="fas fa-timeline"></i><p>Aucun historique</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
        <!-- TARIFICATION -->
        <div class="card mb-2">
            <div class="card-header"><div class="card-title"><i class="fas fa-coins" style="margin-right:6px;color:var(--accent)"></i>Paiement</div></div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;justify-content:space-between;font-size:.85rem">
                    <span style="color:var(--text-muted)">Tarif de base</span>
                    <span style="font-family:var(--font-mono)"><?= number_format($colis['tarif'],0,',',' ') ?> FCFA</span>
                </div>
                <?php if ($colis['remise'] > 0): ?>
                <div style="display:flex;justify-content:space-between;font-size:.85rem">
                    <span style="color:var(--text-muted)">Remise</span>
                    <span style="font-family:var(--font-mono);color:#F87171">-<?= number_format($colis['remise'],0,',',' ') ?> FCFA</span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:700;
                     padding-top:10px;border-top:1px solid var(--border)">
                    <span>Total</span>
                    <span style="font-family:var(--font-mono);color:var(--accent)"><?= number_format($colis['montant_total'],0,',',' ') ?> FCFA</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.8rem;color:var(--text-muted)">Mode</span>
                    <span style="font-size:.82rem"><?= ucfirst(str_replace('_',' ',$colis['mode_paiement'])) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.8rem;color:var(--text-muted)">Statut paiement</span>
                    <?= statutBadge($colis['statut_paiement']) ?>
                </div>
            </div>
        </div>

        <!-- INFOS -->
        <div class="card mb-2">
            <div class="card-header"><div class="card-title">Informations</div></div>
            <div style="display:flex;flex-direction:column;gap:10px;font-size:.83rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Type</span>
                    <?= statutBadge($colis['type_expedition']) ?>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Statut actuel</span>
                    <?= statutBadge($colis['statut']) ?>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Enregistré le</span>
                    <span><?= formatDate($colis['date_expedition'],'d/m/Y H:i') ?></span>
                </div>
                <?php if ($colis['date_livraison_prevue']): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Livraison prévue</span>
                    <span><?= formatDate($colis['date_livraison_prevue'],'d/m/Y') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($colis['date_livraison_reelle']): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Livré le</span>
                    <span style="color:#4ADE80"><?= formatDate($colis['date_livraison_reelle'],'d/m/Y H:i') ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Opérateur</span>
                    <span><?= htmlspecialchars($colis['operateur']) ?></span>
                </div>
            </div>
        </div>

        <!-- SUIVI + ACTIONS -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-timeline" style="margin-right:6px;color:var(--primary)"></i>Suivi</div></div>
            <div style="padding:16px 20px">
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:10px">
                    <span style="color:var(--text-muted);font-size:.9rem">Statut actuel :</span>
                    <?= statutBadge($colis['statut']) ?>
                </div>

                <!-- Actions selon le statut -->
                <?php if (Auth::hasPermission('colis_livrer')): ?>
                <?php if ($colis['statut'] === 'en_transit'): ?>
                <form method="post" style="margin-bottom:16px">
                    <input type="hidden" name="action" value="livrer">
                    <button type="submit" class="btn btn-primary w-100" data-confirm="Marquer ce colis comme arrivé à destination ?">
                        <i class="fas fa-building"></i> Arrivé à l'agence
                    </button>
                </form>
                <?php elseif ($colis['statut'] === 'en_livraison'): ?>
                <div style="display:flex;gap:8px;margin-bottom:16px">
                    <form method="post" style="flex:1">
                        <input type="hidden" name="action" value="confirmer_livre">
                        <button type="submit" class="btn btn-success w-100" data-confirm="Confirmer la livraison de ce colis ?">
                            <i class="fas fa-check"></i> Livré
                        </button>
                    </form>
                    <form method="post" style="flex:1">
                        <input type="hidden" name="action" value="retourner">
                        <button type="submit" class="btn btn-secondary w-100" data-confirm="Marquer ce colis comme retourné ?">
                            <i class="fas fa-undo"></i> Retourné
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                <?php if (!in_array($colis['statut'], ['livre', 'perdu'])): ?>
                <form method="post" style="margin-bottom:16px">
                    <input type="hidden" name="action" value="perdu">
                    <button type="submit" class="btn btn-danger btn-sm w-100" data-confirm="Déclarer ce colis comme perdu ?">
                        <i class="fas fa-exclamation-triangle"></i> Déclarer perdu
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Timeline -->
                <?php if (!empty($historique)): ?>
                <div style="border-left:2px solid var(--primary);padding-left:16px">
                    <?php foreach ($historique as $d): ?>
                    <div style="margin-bottom:14px;position:relative">
                        <div style="position:absolute;left:-22px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--primary)"></div>
                        <div style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($d['created_at'],'d/m/Y H:i') ?></div>
                        <div style="font-weight:600;font-size:.85rem"><?= statutBadge($d['statut']) ?></div>
                        <?php if (!empty($d['localisation'])): ?>
                        <div style="font-size:.8rem"><i class="fas fa-map-pin" style="color:var(--accent);margin-right:4px"></i><?= htmlspecialchars($d['localisation']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($d['commentaire'])): ?>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($d['commentaire']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($d['agent'])): ?>
                        <div style="font-size:.72rem;color:var(--text-muted)">Par <?= htmlspecialchars($d['agent']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="font-size:.85rem;color:var(--text-muted)">Aucun suivi enregistré.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
