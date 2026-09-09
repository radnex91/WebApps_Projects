<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('retours.gerer');
$db = getDB();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte',
    'chèque'    => 'Chèque',
    'mobile'    => 'Mobile',
    'assurance' => 'Assurance',
    'crédit'    => 'Crédit client',
];

// ── Session caisse active du caissier (pour le mouvement de remboursement) ──
function sessionActiveCaisse(PDO $db): ?array {
    if (!hasPermission('caisse.ouvrir')) return null;
    $stmt = $db->prepare("SELECT s.*, c.nom AS caisse_nom
                          FROM sessions_caisse s JOIN caisses c ON s.caisse_id = c.id
                          WHERE s.caissier_id = ? AND s.statut = 'ouverte'");
    $stmt->execute([currentUser()['id']]);
    $r = $stmt->fetch();
    return $r ?: null;
}

// ── POST : enregistrer un retour ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    verifyCsrf();
    requirePermission('retours.gerer');

    $venteId = (int)($_POST['vente_id'] ?? 0);
    $note    = trim($_POST['note'] ?? '');
    $lignesInput = $_POST['lignes'] ?? [];   // [vente_ligne_id => quantite]

    // Nettoyer : ne garder que qte > 0
    $demandees = [];
    foreach ($lignesInput as $vlid => $qte) {
        $qte = (int)$qte;
        if ($qte > 0) $demandees[(int)$vlid] = $qte;
    }

    $redir = url('retours', ['action'=>'new','id'=>$venteId]);

    if ($venteId <= 0 || !$demandees) {
        flash('Aucun article à retourner.', 'error');
        header('Location: ' . $redir); exit;
    }

    try {
        $db->beginTransaction();

        // Charger la vente + ses lignes + prix_achat
        $vStmt = $db->prepare("SELECT * FROM ventes WHERE id = ?");
        $vStmt->execute([$venteId]);
        $vente = $vStmt->fetch();
        if (!$vente) throw new Exception('Vente introuvable.');

        $lStmt = $db->prepare("SELECT vl.*, p.prix_achat
                               FROM vente_lignes vl
                               LEFT JOIN produits p ON vl.produit_id = p.id
                               WHERE vl.vente_id = ? ORDER BY vl.id");
        $lStmt->execute([$venteId]);
        $venteLignes = [];
        foreach ($lStmt->fetchAll() as $l) $venteLignes[(int)$l['id']] = $l;

        // Remise éventuelle de la vente d'origine : le retour se fait au PRIX NET
        // (le client n'a payé que le net). On n'annule pas le compte 7119 — la
        // remise sur les articles conservés reste enregistrée.
        $remisePctVente = (float)($vente['remise_pct'] ?? 0);
        $facteurRemise  = 1 - ($remisePctVente / 100);   // 1.0 si pas de remise

        // Quantités déjà retournées par ligne
        $dejaStmt = $db->prepare("SELECT vente_ligne_id, COALESCE(SUM(quantite),0) AS qte
                                  FROM retour_vente_lignes WHERE vente_ligne_id IN ("
            . implode(',', array_fill(0, count($venteLignes) ?: 1, '?')) . ")
                                  GROUP BY vente_ligne_id");
        $dejaStmt->execute(array_map('intval', array_keys($venteLignes)) ?: [0]);
        $dejaRetour = [];
        foreach ($dejaStmt->fetchAll() as $d) $dejaRetour[(int)$d['vente_ligne_id']] = (int)$d['qte'];

        // Valider chaque ligne demandée
        $lignesRetour = [];
        $montantHt = 0; $montantTva = 0; $coutAchatTotal = 0;
        $tvaRate = (float)getParam('tva', '19.25') / 100;
        foreach ($demandees as $vlid => $qte) {
            if (!isset($venteLignes[$vlid])) throw new Exception('Ligne de vente introuvable.');
            $vl = $venteLignes[$vlid];
            $reste = (int)$vl['quantite'] - (int)($dejaRetour[$vlid] ?? 0);
            if ($qte > $reste) {
                throw new Exception('Quantité retournée supérieure au reste pour : ' . $vl['produit_nom']);
            }
            $prixUnit = (float)$vl['prix_unitaire'];
            // TVA ligne : on reprend le taux de la ligne si dispo, sinon taux global
            $tvaLigne = (float)$vl['tva'];
            // Prix NET si la vente d'origine avait une remise (% sur l'ensemble)
            $puNet  = round($prixUnit * $facteurRemise, 2);
            $tlHt   = round($puNet * $qte, 2);
            $tlTva  = round($tlHt * ($tvaLigne > 0 ? $tvaLigne/100 : $tvaRate), 2);
            $coutAchat = round((float)$vl['prix_achat'] * $qte, 2);   // stock au coût, inchangé

            $lignesRetour[] = [
                'vente_ligne_id' => $vlid,
                'produit_id'      => $vl['produit_id'],
                'produit_nom'     => $vl['produit_nom'],
                'quantite'        => $qte,
                'prix_unitaire'   => $puNet,           // P.U. net (ce qui est remboursé)
                'tva'             => $tvaLigne,
                'total_ligne'     => $tlHt,             // HT net (cohérent avec montant_ht)
                'cout_achat'      => $coutAchat,
            ];
            $montantHt       += $tlHt;
            $montantTva      += $tlTva;
            $coutAchatTotal  += $coutAchat;
        }
        $montantTotal = round($montantHt + $montantTva, 2);

        // Référence + en-tête du retour
        $ref = genRef('RET');
        $modeRemb = $vente['mode_paiement'];
        $stmtR = $db->prepare("INSERT INTO retours_vente
            (reference, vente_id, utilisateur_id, date_retour, montant_ht, montant_tva,
             montant_total, cout_achat_total, mode_remboursement, note)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmtR->execute([
            $ref, $venteId, currentUser()['id'], date('Y-m-d'),
            round($montantHt, 2), round($montantTva, 2), $montantTotal,
            round($coutAchatTotal, 2), $modeRemb, $note ?: null,
        ]);
        $retourId = (int)$db->lastInsertId();

        // Lignes + remise en stock + mouvements_stock
        $stmtL = $db->prepare("INSERT INTO retour_vente_lignes
            (retour_id, vente_ligne_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne, cout_achat)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmtS = $db->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?");
        $stmtM = $db->prepare("INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id)
                               VALUES (?, 'entrée', ?, ?, ?)");
        foreach ($lignesRetour as $lr) {
            $stmtL->execute([
                $retourId, $lr['vente_ligne_id'], $lr['produit_id'], $lr['produit_nom'],
                $lr['quantite'], $lr['prix_unitaire'], $lr['tva'], $lr['total_ligne'], $lr['cout_achat'],
            ]);
            if ($lr['produit_id']) {
                $stmtS->execute([$lr['quantite'], (int)$lr['produit_id']]);
            }
            $stmtM->execute([
                $lr['produit_id'] ? (int)$lr['produit_id'] : null,
                $lr['quantite'],
                'Retour vente ' . $vente['reference'] . ' → ' . $ref,
                currentUser()['id'],
            ]);
        }

        // ── Contrepassation comptable OHADA ──────────────────
        $compteVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
        $compteTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');

        $mapContrepartie = [
            'espèces'   => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
            'carte'     => compteFindOrCreate($db, '512',  'Banque', 5, 'debit'),
            'chèque'    => compteFindOrCreate($db, '511',  'Chèques à encaisser', 5, 'debit'),
            'assurance' => compteFindOrCreate($db, '4111', 'Clients - Assurance', 4, 'debit'),
            'crédit'    => compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit'),
        ];
        $compteContre = $mapContrepartie[$modeRemb] ?? $mapContrepartie['espèces'];

        // Annulation vente : D 7011 (HT) / D 4411 (TVA) / C contrepartie (total)
        $lignesEcriture = [
            [$compteVente, round($montantHt, 2), 0, 'Annulation vente ' . $vente['reference'] . ' - retour ' . $ref],
            [$compteTva,   round($montantTva, 2), 0, 'Annulation TVA ' . $vente['reference'] . ' - retour ' . $ref],
            [$compteContre, 0, $montantTotal,       'Remboursement ' . $vente['reference'] . ' - retour ' . $ref],
        ];
        ecritureCreate($db, 'Retour ' . $ref . ' - ' . ($modeLabels[$modeRemb] ?? $modeRemb),
            date('Y-m-d'), $lignesEcriture, 'retour', $ref, currentUser()['id']);

        // Annulation sortie stock : D 3111 (coût) / C 6031 (coût)
        if ($coutAchatTotal > 0) {
            $compteStock  = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
            $compteVarStk = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
            ecritureCreate($db, 'Retour stock vente ' . $ref, date('Y-m-d'),
                [
                    [$compteStock,  round($coutAchatTotal, 2), 0, 'Retour stock ' . $ref],
                    [$compteVarStk, 0, round($coutAchatTotal, 2), 'Annulation variation ' . $ref],
                ],
                'retour', $ref, currentUser()['id']);
        }

        // ── Remboursement cash (mouvement caisse) ───────────
        $avertissementCaisse = false;
        if ($modeRemb !== 'crédit') {
            $session = sessionActiveCaisse($db);
            if ($session) {
                $stmtCaisse = $db->prepare("INSERT INTO mouvements_caisse
                    (session_id, type, montant, motif, moyen, reference_vente)
                    VALUES (?, 'sortie', ?, ?, ?, ?)");
                $stmtCaisse->execute([
                    (int)$session['id'],
                    $montantTotal,
                    'Remboursement retour ' . $ref,
                    $modeRemb,
                    $ref,
                ]);
            } else {
                $avertissementCaisse = true;  // écriture compta OK, mais pas de mouvement caisse physique
            }
        }

        $db->commit();
        auditLog('retour.create',
            sprintf('Retour %s sur vente %s : %d ligne(s), %s (%s)', $ref, $vente['reference'], count($lignesRetour), fmtMoney($montantTotal), $modeLabels[$modeRemb] ?? $modeRemb),
            $retourId, $ref);

        $msg = 'Retour ' . $ref . ' enregistré : ' . fmtMoney($montantTotal) . ' remboursé.';
        if ($avertissementCaisse) {
            $msg .= ' ⚠️ Aucune caisse ouverte : mouvement de caisse non enregistré (écriture comptable OK).';
            flash($msg, 'warning');
        } else {
            flash($msg, 'success');
        }
        header('Location: ' . url('retours', ['action'=>'detail','id'=>$retourId])); exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flashError($e, 'retour');
        header('Location: ' . $redir); exit;
    }
}

// ── Données pour les vues ───────────────────────────────────
$title = 'Retours caisse';
if ($action === 'detail' && $id) {
    $stmt = $db->prepare("SELECT rv.*, v.reference AS vente_ref, v.mode_paiement AS vente_mode,
                                 v.remise_pct AS vente_remise_pct,
                                 u.prenom, u.nom AS u_nom
                          FROM retours_vente rv
                          JOIN ventes v ON rv.vente_id = v.id
                          LEFT JOIN utilisateurs u ON rv.utilisateur_id = u.id
                          WHERE rv.id = ?");
    $stmt->execute([$id]);
    $retour = $stmt->fetch();
    if ($retour) {
        $stmtL = $db->prepare("SELECT * FROM retour_vente_lignes WHERE retour_id = ? ORDER BY id");
        $stmtL->execute([$id]);
        $retour['lignes'] = $stmtL->fetchAll();
        $title = 'Retour ' . $retour['reference'];
    }
}

layout_head($title, 'retours');
showFlash();
?>

<div style="max-width:1500px;margin:0 auto;">

<?php if ($action === 'detail' && !empty($retour)): ?>
  <!-- ── Détail d'un retour ── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><?= icon('refresh',18) ?> Retour <?= e($retour['reference']) ?></div>
      <div style="display:flex;gap:8px;">
        <a href="<?= url('retours') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Liste</a>
        <a href="<?= url('ventes_hist', ['action'=>'detail','id'=>(int)$retour['vente_id']]) ?>" class="btn btn-ghost btn-sm"><?= icon('eye',14) ?> Voir la vente</a>
      </div>
    </div>
    <div class="form-grid" style="padding:22px;">
      <div class="form-group"><label>Vente d'origine</label><div class="fw-mono"><?= e($retour['vente_ref']) ?></div></div>
      <div class="form-group"><label>Date retour</label><div><?= e(date('d/m/Y', strtotime($retour['date_retour']))) ?></div></div>
      <div class="form-group"><label>Caissier</label><div><?= e(trim(($retour['prenom'] ?? '').' '.($retour['u_nom'] ?? ''))) ?></div></div>
      <div class="form-group"><label>Mode de remboursement</label><div><?= e($modeLabels[$retour['mode_remboursement']] ?? $retour['mode_remboursement']) ?></div></div>
      <?php if ((float)($retour['vente_remise_pct'] ?? 0) > 0): ?>
      <div class="form-group"><label>Remise vente d'origine</label><div style="color:var(--red);font-weight:600"><?= (float)$retour['vente_remise_pct'] ?>% (retour au prix net)</div></div>
      <?php endif; ?>
    </div>
    <div class="table-wrap" style="padding:0 22px 22px;">
      <table>
        <thead><tr><th>Produit</th><th style="text-align:right">Qté</th><th style="text-align:right">P.U.</th><th style="text-align:right">Coût achat</th><th style="text-align:right">Total HT</th></tr></thead>
        <tbody>
        <?php foreach ($retour['lignes'] as $l): ?>
          <tr>
            <td><?= e($l['produit_nom']) ?></td>
            <td style="text-align:right"><?= (int)$l['quantite'] ?></td>
            <td style="text-align:right"><?= fmtMoney((float)$l['prix_unitaire']) ?></td>
            <td style="text-align:right"><?= fmtMoney((float)$l['cout_achat']) ?></td>
            <td style="text-align:right"><?= fmtMoney((float)$l['total_ligne']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <td colspan="4" style="text-align:right;font-weight:600">Montant HT / TVA / Total</td>
          <td style="text-align:right;font-weight:700;color:var(--teal2)"><?= fmtMoney((float)$retour['montant_ht']) ?> / <?= fmtMoney((float)$retour['montant_tva']) ?> / <?= fmtMoney((float)$retour['montant_total']) ?></td>
        </tr></tfoot>
      </table>
    </div>
    <?php if ($retour['note']): ?>
      <div style="padding:0 22px 22px;color:var(--text2);font-size:13px;"><strong>Note :</strong> <?= e($retour['note']) ?></div>
    <?php endif; ?>
  </div>

<?php elseif ($action === 'new' && $id): ?>
  <!-- ── Formulaire de retour sur une vente ── -->
  <?php
    $vStmt = $db->prepare("SELECT * FROM ventes WHERE id = ?");
    $vStmt->execute([$id]);
    $vente = $vStmt->fetch();
    $lignes = [];
    $dejaRetour = [];
    if ($vente) {
        $lStmt = $db->prepare("SELECT vl.*, p.prix_achat FROM vente_lignes vl LEFT JOIN produits p ON vl.produit_id = p.id WHERE vl.vente_id = ? ORDER BY vl.id");
        $lStmt->execute([$id]);
        $lignes = $lStmt->fetchAll();
        if ($lignes) {
            $ids = array_map(fn($l) => (int)$l['id'], $lignes);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $dStmt = $db->prepare("SELECT vente_ligne_id, COALESCE(SUM(quantite),0) AS qte FROM retour_vente_lignes WHERE vente_ligne_id IN ($ph) GROUP BY vente_ligne_id");
            $dStmt->execute($ids);
            foreach ($dStmt->fetchAll() as $d) $dejaRetour[(int)$d['vente_ligne_id']] = (int)$d['qte'];
        }
    }
  ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><?= icon('refresh',18) ?> Retour sur la vente <?= e($vente['reference'] ?? '') ?></div>
      <a href="<?= url('retours', ['action'=>'new']) ?>" class="btn btn-ghost btn-sm"><?= icon('search',14) ?> Autre vente</a>
    </div>
    <?php if (!$vente): ?>
      <div class="card-pad"><div class="empty">Vente introuvable.</div></div>
    <?php else: ?>
      <div style="padding:22px;">
        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;color:var(--text2);font-size:13px;">
          <span><strong>Date :</strong> <?= e(date('d/m/Y H:i', strtotime($vente['created_at']))) ?></span>
          <span><strong>Client :</strong> <?= e($vente['client_nom'] ?: '—') ?></span>
          <span><strong>Mode :</strong> <?= e($modeLabels[$vente['mode_paiement']] ?? $vente['mode_paiement']) ?></span>
          <span><strong>Total initial :</strong> <?= fmtMoney((float)$vente['total']) ?></span>
        </div>
        <form method="POST" action="?action=create">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="vente_id" value="<?= (int)$id ?>">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Produit</th><th style="text-align:right">Qté vendue</th><th style="text-align:right">Déjà retournée</th><th style="text-align:right">Reste</th><th>Qté à retourner</th></tr></thead>
              <tbody>
              <?php $totalPossible = 0; foreach ($lignes as $l):
                $deja = $dejaRetour[(int)$l['id']] ?? 0;
                $reste = (int)$l['quantite'] - $deja;
                if ($reste <= 0) continue;
                $totalPossible += (float)$l['prix_unitaire'] * $reste;
              ?>
                <tr>
                  <td><?= e($l['produit_nom']) ?></td>
                  <td style="text-align:right"><?= (int)$l['quantite'] ?></td>
                  <td style="text-align:right"><?= $deja ?></td>
                  <td style="text-align:right;font-weight:600;color:var(--teal2)"><?= $reste ?></td>
                  <td style="text-align:center">
                    <input type="number" name="lignes[<?= (int)$l['id'] ?>]" min="0" max="<?= $reste ?>" value="0"
                           style="width:70px;text-align:center;font-family:'DM Mono',monospace;">
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$lignes || $totalPossible <= 0): ?>
                <tr><td colspan="5"><div class="empty">Aucun article retournable sur cette vente.</div></td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="form-group" style="margin-top:16px;max-width:480px;">
            <label>Note (optionnel)</label>
            <input type="text" name="note" maxlength="255" placeholder="Motif du retour…">
          </div>
          <div style="display:flex;gap:12px;margin-top:16px;">
            <a href="<?= url('retours') ?>" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= icon('check',14) ?> Valider le retour</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($action === 'new'): ?>
  <!-- ── Sélection d'une vente ── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><?= icon('search',18) ?> Sélectionner la vente à rembourser</div></div>
    <div class="table-wrap" style="padding:22px;">
      <table>
        <thead><tr><th>Référence</th><th>Date</th><th>Client</th><th>Mode</th><th style="text-align:right">Total</th><th></th></tr></thead>
        <tbody>
        <?php
          $ventes = $db->query("SELECT v.*, u.prenom, u.nom AS u_nom
                                FROM ventes v LEFT JOIN utilisateurs u ON v.caissier_id = u.id
                                ORDER BY v.created_at DESC LIMIT 30")->fetchAll();
          foreach ($ventes as $v):
        ?>
          <tr>
            <td class="fw-mono"><?= e($v['reference']) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($v['created_at']))) ?></td>
            <td><?= e($v['client_nom'] ?: '—') ?></td>
            <td><?= e($modeLabels[$v['mode_paiement']] ?? $v['mode_paiement']) ?></td>
            <td style="text-align:right"><?= fmtMoney((float)$v['total']) ?></td>
            <td style="text-align:right">
              <a href="<?= url('retours', ['action'=>'new','id'=>(int)$v['id']]) ?>" class="btn btn-ghost btn-sm"><?= icon('refresh',14) ?> Retourner</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ventes): ?>
          <tr><td colspan="6"><div class="empty">Aucune vente.</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- ── Liste des retours ── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icon('refresh',18) ?> Retours caisse</div>
      <?php if (hasPermission('retours.gerer')): ?>
        <a href="<?= url('retours', ['action'=>'new']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Nouveau retour</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap" style="padding:22px;">
      <table>
        <thead><tr><th>Réf. retour</th><th>Vente</th><th>Date</th><th>Caissier</th><th>Mode</th><th style="text-align:right">Montant</th><th></th></tr></thead>
        <tbody>
        <?php
          $retours = $db->query("SELECT rv.*, v.reference AS vente_ref, u.prenom, u.nom AS u_nom
                                 FROM retours_vente rv
                                 JOIN ventes v ON rv.vente_id = v.id
                                 LEFT JOIN utilisateurs u ON rv.utilisateur_id = u.id
                                 ORDER BY rv.created_at DESC LIMIT 100")->fetchAll();
          foreach ($retours as $r):
        ?>
          <tr>
            <td class="fw-mono"><?= e($r['reference']) ?></td>
            <td class="fw-mono"><?= e($r['vente_ref']) ?></td>
            <td><?= e(date('d/m/Y', strtotime($r['date_retour']))) ?></td>
            <td><?= e(trim($r['prenom'].' '.$r['u_nom'])) ?></td>
            <td><?= e($modeLabels[$r['mode_remboursement']] ?? $r['mode_remboursement']) ?></td>
            <td style="text-align:right;font-weight:700;color:var(--red)"><?= fmtMoney((float)$r['montant_total']) ?></td>
            <td style="text-align:right">
              <a href="<?= url('retours', ['action'=>'detail','id'=>(int)$r['id']]) ?>" class="btn btn-ghost btn-sm"><?= icon('eye',14) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$retours): ?>
          <tr><td colspan="7"><div class="empty">Aucun retour enregistré.</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

</div>
<?php layout_foot(); ?>