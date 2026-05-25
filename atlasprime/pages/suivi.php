<?php
$pageTitle = 'Suivi de colis';
require_once __DIR__ . '/../includes/header.php';

$numero = trim($_GET['numero'] ?? $_POST['numero'] ?? '');
$colis  = null;
$historique = [];

if ($numero) {
    $colis = Database::fetchOne(
        "SELECT c.*, ad.nom vd, ad.nom agd, aa.nom va, aa.nom aga,
                v.numero_voyage, v.transporteur, v.date_depart,
                CONCAT(u.prenom,' ',u.nom) AS operateur
         FROM colis c JOIN agences ad ON c.agence_depart_id=ad.id JOIN agences aa ON c.agence_arrivee_id=aa.id
         LEFT JOIN voyages v ON c.voyage_id=v.id JOIN utilisateurs u ON c.cree_par=u.id
         WHERE c.numero_colis=?", [strtoupper($numero)]
    );
    if ($colis) {
        $historique = Database::fetchAll(
            "SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS agent FROM suivi_colis s
             JOIN utilisateurs u ON s.cree_par=u.id WHERE s.colis_id=? ORDER BY s.created_at DESC",
            [$colis['id']]
        );
        // Parcours des agences (localisation unique par ordre chronologique)
        $parcours = Database::fetchAll(
            "SELECT s.localisation, MIN(s.created_at) AS date_passage, MIN(s.statut) AS premier_statut
             FROM suivi_colis s WHERE s.colis_id=? AND s.localisation IS NOT NULL AND s.localisation != ''
             GROUP BY s.localisation ORDER BY MIN(s.created_at) ASC",
            [$colis['id']]
        );
        // Nom de l'escale actuelle
        $escaleActuelleNom = '';
        if (!empty($colis['escale_actuelle_id'])) {
            $escaleAg = Database::fetchOne("SELECT nom FROM agences WHERE id=?", [$colis['escale_actuelle_id']]);
            $escaleActuelleNom = $escaleAg ? $escaleAg['nom'] : '';
        }
    }
}

$statuts = ['enregistre','en_transit','en_livraison','livre','retourne','perdu'];
$statutColors = ['enregistre'=>'#0EA5E9','en_transit'=>'#D97706','en_livraison'=>'#7C3AED',
                 'livre'=>'#16A34A','retourne'=>'#6B7280','perdu'=>'#DC2626'];
$statutIcons  = ['enregistre'=>'fa-box','en_transit'=>'fa-truck-moving','en_livraison'=>'fa-person-biking',
                 'livre'=>'fa-circle-check','retourne'=>'fa-rotate-left','perdu'=>'fa-circle-question'];
?>

<div style="max-width:800px;margin:0 auto">
    <div class="card mb-3">
        <div style="text-align:center;padding:8px 0 20px">
            <i class="fas fa-map-location-dot" style="font-size:2rem;color:var(--primary);margin-bottom:10px;display:block"></i>
            <h2 style="font-size:1.4rem;font-weight:700">Suivi de colis</h2>
            <p style="color:var(--text-muted);font-size:.85rem">Entrez le numéro de colis pour suivre votre expédition</p>
        </div>
        <form method="get">
            <div style="display:flex;gap:10px">
                <input type="text" name="numero" class="form-control" placeholder="Ex: APL202501000001"
                       value="<?= htmlspecialchars($numero) ?>" style="flex:1;text-transform:uppercase"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Suivre</button>
            </div>
        </form>
    </div>

    <?php if ($numero && !$colis): ?>
    <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> Aucun colis trouvé avec le numéro <strong><?= htmlspecialchars($numero) ?></strong></div>
    <?php endif; ?>

    <?php if ($colis): ?>
    <!-- RÉSULTAT -->
    <div class="card mb-2">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Numéro de colis</div>
                <div class="colis-num" style="font-size:1.1rem;margin-top:4px"><?= $colis['numero_colis'] ?></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?= statutBadge($colis['type_expedition']) ?>
                <?= statutBadge($colis['statut']) ?>
            </div>
        </div>

        <!-- Progress -->
        <?php $currentStep = array_search($colis['statut'], $statuts); ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;padding:8px 0;margin-bottom:20px">
            <div style="position:absolute;top:18px;left:4%;right:4%;height:2px;background:var(--border);z-index:0"></div>
            <div style="position:absolute;top:18px;left:4%;height:2px;background:var(--primary);z-index:0;
                 width:<?= $currentStep>=0?min(92,($currentStep/5)*92):0 ?>%;transition:width 0.5s"></div>
            <?php foreach ($statuts as $i => $s):
                $done = $i <= $currentStep; $cur = $i === $currentStep;
                $col  = $statutColors[$s];
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;z-index:1">
                <div style="width:32px;height:32px;border-radius:50%;
                     background:<?= $done?$col:'var(--bg-card2)' ?>;
                     border:2px solid <?= $done?$col:'var(--border)' ?>;
                     display:flex;align-items:center;justify-content:center;font-size:.75rem;
                     color:<?= $done?'white':'var(--text-dim)' ?>;
                     box-shadow:<?= $cur?"0 0 0 4px rgba(0,0,0,0.3),0 0 0 6px $col":'none' ?>">
                    <i class="fas <?= $statutIcons[$s] ?>"></i>
                </div>
                <div style="font-size:.65rem;color:<?= $done?'var(--text)':'var(--text-dim)' ?>;margin-top:5px;text-align:center;font-weight:<?= $cur?700:400 ?>">
                    <?= ucfirst(str_replace('_',' ',$s)) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Infos -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Expéditeur</div>
                <div style="font-weight:600"><?= htmlspecialchars($colis['expediteur_nom']) ?></div>
                <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($colis['vd']) ?></div>
            </div>
            <div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Destinataire</div>
                <div style="font-weight:600"><?= htmlspecialchars($colis['destinataire_nom']) ?></div>
                <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($colis['va']) ?></div>
            </div>
            <div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Description</div>
                <div style="font-size:.85rem"><?= htmlspecialchars($colis['description']) ?></div>
            </div>
            <div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Date expédition</div>
                <div style="font-size:.85rem"><?= formatDate($colis['date_expedition'],'d/m/Y H:i') ?></div>
            </div>
        </div>
    </div>

    <!-- PARCOURS DES AGENCES -->
    <?php if (!empty($parcours)): ?>
    <div class="card mb-2">
        <div class="card-header"><div class="card-title"><i class="fas fa-route" style="margin-right:6px;color:var(--primary)"></i>Parcours du colis</div></div>
        <div style="padding:12px 20px">
            <?php
            $totalSteps = count($parcours);
            $lastStep = $totalSteps - 1;
            foreach ($parcours as $pi => $p):
                $isDepart = ($pi === 0);
                $isArrivee = ($pi === $lastStep);
                $isCurrent = ($escaleActuelleNom && $p['localisation'] === $escaleActuelleNom) ||
                             (!$colis['voyage_id'] && $escaleActuelleNom === '' && $pi === $lastStep && $colis['statut'] !== 'livre' && $colis['statut'] !== 'enregistre');
                $stepColor = $isArrivee && $colis['statut'] === 'livre' ? 'var(--success)' : ($isCurrent ? 'var(--accent)' : 'var(--primary)');
                $stepIcon = $isDepart ? 'fa-play' : ($isArrivee && $colis['statut'] === 'livre' ? 'fa-flag-checkered' : 'fa-map-pin');
            ?>
            <div style="display:flex;gap:12px;align-items:flex-start">
                <!-- Ligne verticale + point -->
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                    <div style="width:28px;height:28px;border-radius:50%;
                         background:<?= $stepColor ?>;
                         display:flex;align-items:center;justify-content:center;font-size:.72rem;color:white;
                         box-shadow:0 0 0 3px <?= $stepColor ?>33">
                        <i class="fas <?= $stepIcon ?>"></i>
                    </div>
                    <?php if ($pi < $lastStep): ?>
                    <div style="width:2px;height:24px;background:var(--border);margin:2px 0"></div>
                    <div style="width:2px;flex:1;min-height:12px;background:repeating-linear-gradient(to bottom,var(--border) 0 4px,transparent 4px 8px)"></div>
                    <?php endif; ?>
                </div>
                <!-- Contenu -->
                <div style="padding-bottom:<?= $pi < $lastStep ? '16px' : '0' ?>;flex:1">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <span style="font-weight:700;font-size:.9rem;color:var(--text)"><?= htmlspecialchars($p['localisation']) ?></span>
                            <?php if ($isDepart): ?>
                            <span style="font-size:.65rem;background:var(--primary);color:white;padding:1px 6px;border-radius:10px;margin-left:6px">Départ</span>
                            <?php elseif ($isArrivee): ?>
                            <span style="font-size:.65rem;background:var(--success);color:white;padding:1px 6px;border-radius:10px;margin-left:6px">Arrivée</span>
                            <?php elseif ($isCurrent && $colis['statut'] !== 'livre'): ?>
                            <span style="font-size:.65rem;background:var(--accent);color:white;padding:1px 6px;border-radius:10px;margin-left:6px">Position actuelle</span>
                            <?php else: ?>
                            <span style="font-size:.65rem;background:var(--bg-card2);color:var(--text-muted);padding:1px 6px;border-radius:10px;margin-left:6px">Escale</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.72rem;color:var(--text-muted);font-family:var(--font-mono)"><?= formatDate($p['date_passage'],'d/m/Y H:i') ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- HISTORIQUE -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-timeline" style="margin-right:6px;color:var(--primary)"></i>Historique</div></div>
        <?php foreach ($historique as $i => $h): ?>
        <div style="display:flex;gap:14px;margin-bottom:16px;position:relative">
            <?php if ($i < count($historique)-1): ?>
            <div style="position:absolute;left:14px;top:28px;bottom:-16px;width:2px;background:var(--border)"></div>
            <?php endif; ?>
            <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;z-index:1;
                 background:<?= $statutColors[$h['statut']] ?? '#555' ?>;
                 display:flex;align-items:center;justify-content:center;font-size:.72rem;color:white">
                <i class="fas <?= $statutIcons[$h['statut']] ?? 'fa-circle' ?>"></i>
            </div>
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px">
                    <?= statutBadge($h['statut']) ?>
                    <?php if ($h['localisation']): ?>
                    <span style="font-size:.75rem;color:var(--text-muted)"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($h['localisation']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($h['commentaire']): ?>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:3px"><?= htmlspecialchars($h['commentaire']) ?></div>
                <?php endif; ?>
                <div style="font-size:.72rem;color:var(--text-dim);margin-top:3px"><?= formatDate($h['created_at'],'d/m/Y à H:i') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Suggestions rapides -->
    <?php if (!$colis): ?>
    <div style="text-align:center;margin-top:32px;padding:24px;background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius)">
        <i class="fas fa-lightbulb" style="color:var(--accent);font-size:1.5rem;margin-bottom:10px;display:block"></i>
        <p style="font-size:.85rem;color:var(--text-muted)">Le numéro de colis est au format <strong style="color:var(--text);font-family:var(--font-mono)">APL#K3$7&M2P5</strong><br>
        Vous le trouverez sur votre bordereau de transport.</p>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
