<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('commandes.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST : création / mise à jour de commande ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit') && (hasPermission('commandes.creer') || hasPermission('commandes.modifier'))) {
    verifyCsrf();
    // Interdire la modification d'une commande livrée ou annulée
    if ($id) {
        $check = $db->prepare("SELECT statut FROM commandes WHERE id=?");
        $check->execute([$id]);
        $existing = $check->fetch();
        if ($existing && in_array($existing['statut'], ['livrée', 'annulée'])) {
            flash('Cette commande ne peut plus être modifiée.', 'error');
            header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
        }
    }
    $fourn_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
    $statut   = $_POST['statut'] ?? 'en_attente';
    $date_cmd = $_POST['date_commande'] ?: null;
    $date_liv = $_POST['date_livraison'] ?: null;
    $note     = trim($_POST['note'] ?? '');
    $user_id  = currentUser()['id'];

    $produits_ids = $_POST['produit_id'] ?? [];
    $designations = $_POST['designation'] ?? [];
    $quantites    = $_POST['quantite'] ?? [];
    $prix         = $_POST['prix_unitaire'] ?? [];

    try {
        $db->beginTransaction();

        if ($id) {
            $db->prepare("UPDATE commandes SET fournisseur_id=?,statut=?,date_commande=?,date_livraison=?,note=? WHERE id=?")
               ->execute([$fourn_id, $statut, $date_cmd, $date_liv, $note, $id]);
            $db->prepare("DELETE FROM commande_lignes WHERE commande_id=?")->execute([$id]);
        } else {
            $ref = genRef('CMD');
            $db->prepare("INSERT INTO commandes (reference,fournisseur_id,utilisateur_id,statut,date_commande,date_livraison,note) VALUES (?,?,?,?,?,?,?)")
               ->execute([$ref, $fourn_id, $user_id, $statut, $date_cmd, $date_liv, $note]);
            $id = $db->lastInsertId();
        }

        $stmtLigne = $db->prepare("INSERT INTO commande_lignes (commande_id,produit_id,designation,quantite,prix_unitaire) VALUES (?,?,?,?,?)");
        for ($i = 0; $i < count($designations); $i++) {
            $designation = trim($designations[$i]);
            $qte = max(1, (int)($quantites[$i] ?? 1));
            $pu  = (float)str_replace(',', '.', $prix[$i] ?? 0);
            if ($designation === '' && !empty($produits_ids[$i])) {
                $p = $db->prepare("SELECT nom FROM produits WHERE id=?");
                $p->execute([$produits_ids[$i]]);
                if ($row = $p->fetch()) $designation = $row['nom'];
            }
            if ($designation === '') continue;
            $pid = !empty($produits_ids[$i]) ? (int)$produits_ids[$i] : null;
            $stmtLigne->execute([$id, $pid, $designation, $qte, $pu]);
        }

        $db->commit();
        flash($id ? 'Commande mise à jour.' : "Commande créée.");
    } catch (Exception $e) {
        $db->rollBack();
        flash('Erreur : ' . $e->getMessage(), 'error');
    }
    header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
}

$statutMap = [
    'en_attente' => ['badge-blue',  'En attente'],
    'en_cours'   => ['badge-gold',  'En cours'],
    'livrée'     => ['badge-green', 'Livrée'],
    'annulée'    => ['badge-red',   'Annulée'],
];

// ── Livrer une commande (POST avec note) ──────────────────────
if ($action === 'livrer' && $id && hasPermission('commandes.modifier') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $noteLivraison = trim($_POST['note_livraison'] ?? '');
    $checks = $_POST['checks'] ?? [];
    if (empty($checks)) {
        flash('Veuillez cocher au moins une case de vérification.', 'error');
        header('Location: ' . APP_URL . '/modules/commandes.php?action=livrer_form&id=' . $id); exit;
    }
    $validationNote = implode(' ; ', $checks);
    if ($noteLivraison) $validationNote .= ' — ' . $noteLivraison;
    $stmt = $db->prepare("SELECT c.*, f.nom AS fourn FROM commandes c LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if ($cmd && in_array($cmd['statut'], ['en_attente', 'en_cours'])) {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE commandes SET statut = 'livrée', date_livraison = CURDATE(), note = CONCAT(IFNULL(note,''), ?) WHERE id = ?")
               ->execute(["\n[Validation] " . $validationNote, $id]);

            $stmtM = $db->prepare("SELECT SUM(quantite * prix_unitaire) AS total FROM commande_lignes WHERE commande_id=?");
            $stmtM->execute([$id]);
            $montantTtc = (float)$stmtM->fetchColumn();

            if ($montantTtc > 0) {
                $tvaPct = (float)getParam('tva', '19.25') / 100;
                $montantHt = round($montantTtc / (1 + $tvaPct), 2);
                $montantTva = round($montantTtc - $montantHt, 2);
                $compteAchat = compteFindOrCreate($db, '6011', 'Achats de médicaments', 6, 'debit');
                $compteTva   = compteFindOrCreate($db, '4451', 'TVA récupérable 19.25%', 4, 'debit');
                $compteFourn = compteFindOrCreate($db, '4011', 'Fournisseurs achats', 4, 'credit');
                ecritureCreate($db,
                    'Achat CMD ' . $cmd['reference'] . ' - ' . e($cmd['fourn'] ?? ''),
                    date('Y-m-d'),
                    [
                        [$compteAchat, $montantHt, 0, 'Achat médicaments ' . $cmd['reference']],
                        [$compteTva,   $montantTva, 0, 'TVA récupérable ' . $cmd['reference']],
                        [$compteFourn, 0, $montantTtc, 'Fournisseur ' . e($cmd['fourn'] ?? '')],
                    ],
                    'commande', $cmd['reference'], currentUser()['id']
                );
            }

            // Mettre à jour le stock du MAGASIN (dépôt central).
            // La livraison fournisseur alimente le magasin, qui ravitalle
            // ensuite la pharmacie via des transferts (module Magasin).
            $lignes = $db->prepare("SELECT produit_id, quantite FROM commande_lignes WHERE commande_id=?");
            $lignes->execute([$id]);
            foreach ($lignes as $l) {
                if ($l['produit_id']) {
                    $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?")->execute([$l['quantite'], $l['produit_id']]);
                    $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)")
                       ->execute([$l['produit_id'], $l['quantite'], 'Livraison CMD ' . $cmd['reference'] . ' — ' . $validationNote, currentUser()['id']]);
                }
            }

            $db->commit();
            flash('Commande livrée — stock magasin mis à jour. Pensez à transférer vers la pharmacie.');
        } catch (Exception $e) {
            $db->rollBack();
            flash('Erreur : ' . $e->getMessage(), 'error');
        }
    } else {
        flash('Impossible de valider cette commande.', 'error');
    }
    header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
}

// ── Formulaire de validation livraison ─────────────────────────
if ($action === 'livrer_form' && $id && hasPermission('commandes.modifier')) {
    $stmt = $db->prepare("SELECT c.*, f.nom AS fourn FROM commandes c LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if (!$cmd || !in_array($cmd['statut'], ['en_attente', 'en_cours'])) {
        flash('Impossible de valider cette commande.', 'error');
        header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
    }
    $lignes = $db->prepare("SELECT cl.*, p.nom AS pnom FROM commande_lignes cl LEFT JOIN produits p ON cl.produit_id = p.id WHERE cl.commande_id=? ORDER BY cl.id");
    $lignes->execute([$id]);
    $cmdLignes = $lignes->fetchAll();
    $stmtT = $db->prepare("SELECT COALESCE(SUM(quantite * prix_unitaire),0) FROM commande_lignes WHERE commande_id=?");
    $stmtT->execute([$id]);
    $total = (float)$stmtT->fetchColumn();

    layout_head('Valider livraison — ' . e($cmd['reference']), 'commandes');
    showFlash();
    ?>
    <div class="card" style="max-width:600px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Valider la livraison</div>
        <a href="<?= APP_URL ?>/modules/commandes.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <div class="card-pad">
        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
          <div><span style="color:var(--text3);font-size:12px;">Commande</span><div style="font-weight:600;"><?= e($cmd['reference']) ?></div></div>
          <div><span style="color:var(--text3);font-size:12px;">Fournisseur</span><div style="font-weight:600;"><?= e($cmd['fourn'] ?? '—') ?></div></div>
          <div><span style="color:var(--text3);font-size:12px;">Date</span><div style="font-weight:600;"><?= date('d/m/Y', strtotime($cmd['date_commande'])) ?></div></div>
        </div>
        <div class="table-wrap" style="margin-bottom:16px;">
          <table>
            <thead><tr><th>Produit</th><th style="text-align:right;">Qté</th><th style="text-align:right;">Prix achat</th><th style="text-align:right;">Total</th></tr></thead>
            <tbody>
              <?php foreach ($cmdLignes as $l): ?>
              <tr>
                <td><?= e($l['designation']) ?></td>
                <td style="text-align:right;"><?= $l['quantite'] ?></td>
                <td class="fw-mono" style="text-align:right;"><?= fmtMoney((float)$l['prix_unitaire']) ?></td>
                <td class="fw-mono" style="text-align:right;"><?= fmtMoney($l['quantite'] * (float)$l['prix_unitaire']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg2);">
                <td colspan="3">Total</td>
                <td style="text-align:right;"><?= fmtMoney($total) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <form method="POST" action="?action=livrer&id=<?= $id ?>" onsubmit="return checkValidation(this)">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <div style="font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text3);margin-bottom:10px;">Vérification de réception</div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Colis en bon état" style="margin-top:2px;">
              <div><strong>Colis en bon état</strong><div style="font-size:12px;color:var(--text3);">Aucun dégât visible sur l'emballage</div></div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Quantité conforme" style="margin-top:2px;">
              <div><strong>Quantité conforme</strong><div style="font-size:12px;color:var(--text3);">Le nombre d'articles correspond à la commande</div></div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Produits conformes" style="margin-top:2px;">
              <div><strong>Produits conformes</strong><div style="font-size:12px;color:var(--text3);">Les médicaments correspondent à la commande (nom, dosage)</div></div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Dates de validité correctes" style="margin-top:2px;">
              <div><strong>Dates de validité correctes</strong><div style="font-size:12px;color:var(--text3);">Les dates d'expiration sont acceptables</div></div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Bon de réception signé" style="margin-top:2px;">
              <div><strong>Bon de réception signé</strong><div style="font-size:12px;color:var(--text3);">Le bon de livraison fournisseur a été vérifié et signé</div></div>
            </label>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-sm);cursor:pointer;transition:.15s;" onmouseover="this.style.borderColor='var(--teal)'" onmouseout="this.style.borderColor='var(--border2)'">
              <input type="checkbox" name="checks[]" value="Facture reçue" style="margin-top:2px;">
              <div><strong>Facture reçue</strong><div style="font-size:12px;color:var(--text3);">La facture du fournisseur a été reçue et vérifiée</div></div>
            </label>
          </div>
          <div class="form-group">
            <label>Commentaire supplémentaire</label>
            <textarea name="note_livraison" rows="2" placeholder="Remarque éventuelle..." style="width:100%;"></textarea>
          </div>
          <div style="background:var(--red-dim);border:1px solid var(--red-glow);border-radius:var(--radius-sm);padding:10px 14px;font-size:13px;color:var(--red);margin-bottom:14px;">
            ⚠️ La validation entraîne l'entrée en stock des produits commandés et ne peut pas être annulée.
          </div>
          <div class="modal-footer" style="padding:0;">
            <a href="<?= APP_URL ?>/modules/commandes.php" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary" id="btn-livrer"><?= icon('check',14) ?> Confirmer la livraison</button>
          </div>
        </form>
        <script>
        function checkValidation(form) {
          var boxes = document.querySelectorAll('input[name="checks[]"]:checked');
          if (boxes.length === 0) {
            alert('Veuillez cocher au moins une case de vérification.');
            return false;
          }
          showConfirm('Confirmer la livraison ?','Le stock sera mis à jour pour les produits commandés.',function(){ form.submit(); });
          return false;
        }
        </script>
      </div>
    </div>
    <?php layout_foot(); exit;
}

// ── Supprimer une commande ──────────────────────────────────
if ($action === 'delete' && $id && hasPermission('commandes.modifier')) {
    $db->prepare("DELETE FROM commande_lignes WHERE commande_id=?")->execute([$id]);
    $db->prepare("DELETE FROM commandes WHERE id=?")->execute([$id]);
    flash('Commande supprimée.');
    header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
}

// ── Formulaire ajout / modification ─────────────────────────
if (in_array($action, ['add', 'edit'])) {
    if ($id) {
        $stmtCheck = $db->prepare("SELECT statut FROM commandes WHERE id=?");
        $stmtCheck->execute([$id]);
        $checkRow = $stmtCheck->fetch();
        if ($checkRow && in_array($checkRow['statut'], ['livrée', 'annulée'])) {
            flash('Cette commande ne peut plus être modifiée.', 'error');
            header('Location: ' . APP_URL . '/modules/commandes.php'); exit;
        }
    }
    $c = ['id'=>'','fournisseur_id'=>'','statut'=>'en_attente','date_commande'=>date('Y-m-d'),'date_livraison'=>'','note'=>''];
    $lignes = [];
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM commandes WHERE id=?");
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) $c = $row;
        $stmtL = $db->prepare("SELECT cl.*, p.nom AS pnom FROM commande_lignes cl LEFT JOIN produits p ON cl.produit_id = p.id WHERE cl.commande_id=? ORDER BY cl.id");
        $stmtL->execute([$id]);
        $lignes = $stmtL->fetchAll();
    }
    $fournisseurs = $db->query("SELECT * FROM fournisseurs WHERE actif=1 ORDER BY nom")->fetchAll();
    $produits = $db->query("SELECT id, nom, prix_achat FROM produits WHERE actif=1 ORDER BY nom")->fetchAll();
    layout_head(($id ? 'Modifier' : 'Nouvelle') . ' commande', 'commandes');
    showFlash();
    ?>
    <div class="card" style="max-width:780px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title"><?= $id ? 'Modifier commande' : 'Nouvelle commande' ?></div>
        <a href="<?= APP_URL ?>/modules/commandes.php" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
      </div>
      <form method="POST" id="cmd-form">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Fournisseur *</label>
            <select name="fournisseur_id" required>
              <option value="">— Choisir —</option>
              <?php foreach ($fournisseurs as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $c['fournisseur_id']==$f['id']?'selected':'' ?>><?= e($f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select name="statut">
              <?php foreach ($statutMap as $v => [$badge, $lbl]): ?>
              <option value="<?= $v ?>" <?= ($c['statut']??'en_attente')===$v?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date de commande</label>
            <input type="date" name="date_commande" value="<?= e($c['date_commande'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="form-group">
            <label>Livraison prévue</label>
            <input type="date" name="date_livraison" value="<?= e($c['date_livraison'] ?? '') ?>">
          </div>
        </div>

        <!-- Produits commandés (sélection via modale multi-sélection) -->
        <div style="padding:16px 20px;border-top:1px solid var(--border);">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text3);">Produits commandés</div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openCmdProduitModal()"><?= icon('plus',15) ?> Choisir les produits</button>
          </div>
          <div class="table-wrap" style="margin-bottom:8px;">
            <table id="cmd-summary-table">
              <thead>
                <tr>
                  <th>Produit</th>
                  <th style="text-align:right;">Qté</th>
                  <th style="text-align:right;">Prix achat</th>
                  <th style="text-align:right;">Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div id="cmd-total" style="font-size:14px;font-weight:600;padding:10px;border-radius:var(--radius-sm);margin-top:8px;background:var(--bg2);color:var(--text3);text-align:center;">
            Total : <span id="total-cmd">0</span> FCFA
          </div>
        </div>

        <div class="form-grid" style="padding:0 20px;">
          <div class="form-group full">
            <label>Notes</label>
            <textarea name="note" rows="2"><?= e($c['note'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= APP_URL ?>/modules/commandes.php" class="btn btn-ghost">Annuler</a>
          <?php if ($id && hasPermission('commandes.modifier')): ?>
          <a href="?action=delete&id=<?= $id ?>" class="btn btn-danger" onclick="showConfirm('Supprimer cette commande ?','Cette action est irréversible.',function(){window.location.href=this.href;}.bind(this));return false;"><?= icon('trash',14) ?> Supprimer</a>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
        </div>
      </form>
    </div>

    <!-- ═══ Modale SÉLECTION PRODUITS (multi-sélection) ═══ -->
    <div class="modal-overlay" id="modal-cmd-produits">
      <div class="modal" style="width:920px;max-width:94vw;">
        <div class="modal-header" style="padding:22px 28px;">
          <div class="modal-title"><?= icon('clipboard',16) ?> Produits à commander</div>
          <button class="modal-close" onclick="closeModal('modal-cmd-produits')">✕</button>
        </div>
        <div class="card-pad" style="padding:20px 28px;">
          <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
            <div class="search-box" style="flex:1;min-width:220px;">
              <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
              <input type="text" id="cmd-prod-search" placeholder="Filtrer les produits..." oninput="filterCmdList()">
            </div>
            <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" id="cmd-prod-selectall" onchange="toggleAllCmd(this.checked)">
              <span>Tout sélectionner</span>
            </label>
          </div>
          <style>
            #cmd-prod-table th{padding:12px 14px;}
            #cmd-prod-table td{padding:11px 14px;}
            #cmd-prod-table tbody tr:hover{background:var(--glass);}
            #cmd-prod-table .cmd-qte,#cmd-prod-table .cmd-prix{padding:7px 10px;}
          </style>
          <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
            <table id="cmd-prod-table">
              <thead>
                <tr>
                  <th style="width:42px;"></th><th>Médicament</th>
                  <th style="text-align:right;">Qté</th>
                  <th style="text-align:right;">Prix achat</th>
                </tr>
              </thead>
              <tbody id="cmd-prod-tbody"></tbody>
            </table>
          </div>
          <div id="cmd-prod-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
        </div>
        <div class="modal-footer" style="padding:16px 28px;">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-cmd-produits')">Annuler</button>
          <button type="button" class="btn btn-primary" onclick="confirmCmdSelection()"><?= icon('check',14) ?> Valider la sélection</button>
        </div>
      </div>
    </div>

    <script>
    var produitsData = <?= json_encode(array_map(function($p){ return ['id'=>(int)$p['id'],'nom'=>$p['nom'],'prix'=>(float)$p['prix_achat']]; }, $produits), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var cmdInitial = <?= json_encode(array_map(function($l){ return ['pid'=>(int)$l['produit_id'],'nom'=>$l['pnom'] ?? $l['designation'],'qte'=>(int)$l['quantite'],'prix'=>(float)$l['prix_unitaire']]; }, $lignes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var cmdSelected = {}; // pid -> {nom, qte, prix}
    cmdInitial.forEach(function(it){ if (it.pid > 0) cmdSelected[it.pid] = {nom: it.nom, qte: it.qte, prix: it.prix}; });

    function esc(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function fmtPrix(n){ return Math.round(n).toLocaleString('fr-FR'); }

    function openCmdProduitModal() {
      var tbody = document.getElementById('cmd-prod-tbody');
      tbody.innerHTML = '';
      produitsData.forEach(function(p) {
        var sel = cmdSelected[p.id];
        var tr = document.createElement('tr');
        tr.setAttribute('data-nom', p.nom.toLowerCase());
        tr.innerHTML =
          '<td style="text-align:center;"><input type="checkbox" class="cmd-check" data-pid="'+p.id+'" onchange="onCmdCheck(this)" '+(sel?'checked':'')+'></td>' +
          '<td class="td-name">'+esc(p.nom)+'</td>' +
          '<td style="text-align:right;"><input type="number" class="cmd-qte" data-pid="'+p.id+'" min="1" value="'+(sel?sel.qte:1)+'" style="width:80px;text-align:right;" '+(sel?'':'disabled')+' oninput="updateCmdModalSummary()"></td>' +
          '<td style="text-align:right;"><input type="number" class="cmd-prix" data-pid="'+p.id+'" min="0" step="1" value="'+(sel?sel.prix:p.prix)+'" style="width:110px;text-align:right;" '+(sel?'':'disabled')+' oninput="updateCmdModalSummary()"></td>';
        tbody.appendChild(tr);
      });
      document.getElementById('cmd-prod-search').value = '';
      document.getElementById('cmd-prod-selectall').checked = false;
      filterCmdList();
      updateCmdModalSummary();
      openModal('modal-cmd-produits');
    }

    function onCmdCheck(cb) {
      var pid = cb.getAttribute('data-pid');
      var qte = document.querySelector('#cmd-prod-table .cmd-qte[data-pid="'+pid+'"]');
      var prix = document.querySelector('#cmd-prod-table .cmd-prix[data-pid="'+pid+'"]');
      qte.disabled = !cb.checked; prix.disabled = !cb.checked;
      if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); }
      updateCmdModalSummary();
    }

    function toggleAllCmd(checked) {
      document.querySelectorAll('#cmd-prod-tbody .cmd-check').forEach(function(c){
        if (c.disabled) return;
        c.checked = checked; onCmdCheck(c);
      });
      updateCmdModalSummary();
    }

    function filterCmdList() {
      var q = document.getElementById('cmd-prod-search').value.toLowerCase();
      document.querySelectorAll('#cmd-prod-tbody tr').forEach(function(r){
        r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
      });
    }

    function updateCmdModalSummary() {
      var checks = document.querySelectorAll('#cmd-prod-tbody .cmd-check:checked');
      var total = 0;
      checks.forEach(function(cb){
        var pid = cb.getAttribute('data-pid');
        var q = parseInt(document.querySelector('#cmd-prod-table .cmd-qte[data-pid="'+pid+'"]').value, 10) || 0;
        var p = parseFloat(String(document.querySelector('#cmd-prod-table .cmd-prix[data-pid="'+pid+'"]').value).replace(',', '.')) || 0;
        total += q * p;
      });
      document.getElementById('cmd-prod-summary').textContent = checks.length + ' produit(s) sélectionné(s) — ' + total.toLocaleString('fr-FR') + ' FCFA';
    }

    function confirmCmdSelection() {
      var next = {};
      document.querySelectorAll('#cmd-prod-tbody .cmd-check:checked').forEach(function(cb){
        var pid = cb.getAttribute('data-pid');
        var p = produitsData.find(function(x){ return String(x.id) === pid; });
        var qte = parseInt(document.querySelector('#cmd-prod-table .cmd-qte[data-pid="'+pid+'"]').value, 10) || 0;
        var prix = parseFloat(String(document.querySelector('#cmd-prod-table .cmd-prix[data-pid="'+pid+'"]').value).replace(',', '.')) || 0;
        next[pid] = {nom: p ? p.nom : '', qte: qte, prix: prix};
      });
      cmdSelected = next;
      closeModal('modal-cmd-produits');
      renderCmdSummary();
      calcTotal();
    }

    function renderCmdSummary() {
      var tbody = document.querySelector('#cmd-summary-table tbody');
      tbody.innerHTML = '';
      var keys = Object.keys(cmdSelected);
      if (!keys.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Aucun produit sélectionné — cliquez sur « Choisir les produits ».</div></td></tr>';
        return;
      }
      keys.forEach(function(pid){
        var it = cmdSelected[pid];
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="td-name">'+esc(it.nom)+'</td>' +
          '<td class="fw-mono" style="text-align:right;">'+it.qte+'</td>' +
          '<td class="fw-mono" style="text-align:right;">'+fmtPrix(it.prix)+'</td>' +
          '<td class="fw-mono c-teal" style="text-align:right;">'+fmtPrix(it.qte * it.prix)+'</td>' +
          '<td style="text-align:right;"><button type="button" class="btn btn-ghost btn-xs" onclick="removeCmdLine('+pid+')">✕</button></td>';
        tbody.appendChild(tr);
      });
    }

    function removeCmdLine(pid) {
      delete cmdSelected[pid];
      renderCmdSummary();
      calcTotal();
    }

    function calcTotal() {
      var total = 0;
      Object.keys(cmdSelected).forEach(function(pid){ var it = cmdSelected[pid]; total += it.qte * it.prix; });
      document.getElementById('total-cmd').textContent = total.toLocaleString('fr-FR');
      var el = document.getElementById('cmd-total');
      if (total > 0) { el.style.background = 'var(--teal-dim)'; el.style.color = 'var(--teal2)'; }
      else { el.style.background = 'var(--bg2)'; el.style.color = 'var(--text3)'; }
    }

    document.getElementById('cmd-form').addEventListener('submit', function(e){
      var keys = Object.keys(cmdSelected);
      if (!keys.length) { e.preventDefault(); alert('Sélectionnez au moins un produit à commander.'); return; }
      this.querySelectorAll('input.cmd-hidden').forEach(function(i){ i.remove(); });
      var form = this;
      keys.forEach(function(pid){
        var it = cmdSelected[pid];
        [['produit_id', pid], ['designation', it.nom], ['quantite', it.qte], ['prix_unitaire', it.prix]].forEach(function(pair){
          var h = document.createElement('input'); h.type = 'hidden'; h.className = 'cmd-hidden'; h.name = pair[0] + '[]'; h.value = pair[1]; form.appendChild(h);
        });
      });
    });

    renderCmdSummary();
    calcTotal();
    </script>
    <?php layout_foot(); exit;
}

// ── Bon de livraison (impression) ──────────────────────────────
if ($action === 'bon' && $id) {
    $stmt = $db->prepare("SELECT c.*, f.nom AS fourn, f.telephone AS fourn_tel, f.adresse AS fourn_adresse, u.prenom, u.nom AS u_nom
        FROM commandes c
        LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
        LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.id = ?");
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if (!$cmd) { flash('Commande introuvable.', 'error'); header('Location: ' . APP_URL . '/modules/commandes.php'); exit; }

    $lignes = $db->prepare("SELECT cl.*, p.nom AS pnom FROM commande_lignes cl LEFT JOIN produits p ON cl.produit_id = p.id WHERE cl.commande_id=? ORDER BY cl.id");
    $lignes->execute([$id]);
    $cmdLignes = $lignes->fetchAll();

    $stmtT = $db->prepare("SELECT COALESCE(SUM(quantite * prix_unitaire),0) FROM commande_lignes WHERE commande_id=?");
    $stmtT->execute([$id]);
    $total = (float)$stmtT->fetchColumn();

    $appNom = getParam('app_nom', 'PharmaCare');
    $appSousTitre = getParam('ticket_sous_titre', 'Gestion Pharmacie');
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <title>Bon de livraison — <?= e($cmd['reference']) ?></title>
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: 'Manrope', -apple-system, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; }
      .page { max-width: 800px; margin: 0 auto; padding: 40px 48px; }
      .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 2px solid #0d9488; padding-bottom: 20px; }
      .brand h1 { font-size: 22px; color: #0d9488; font-weight: 700; }
      .brand .sub { font-size: 11px; color: #64748b; letter-spacing: 2px; text-transform: uppercase; }
      .ref { text-align: right; }
      .ref .num { font-size: 20px; font-weight: 700; color: #0d9488; }
      .ref .date { font-size: 12px; color: #64748b; margin-top: 4px; }
      .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
      .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
      .info-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }
      .info-box .value { font-weight: 600; color: #1e293b; }
      .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
      .status-livree { background: #d1fae5; color: #065f46; }
      .status-attente { background: #dbeafe; color: #1e40af; }
      .status-en_cours { background: #fef3c7; color: #92400e; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
      thead th { background: #0d9488; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; text-align: left; }
      thead th:last-child, thead th:nth-child(3), thead th:nth-child(4) { text-align: right; }
      tbody td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
      tbody td:last-child, tbody td:nth-child(3), tbody td:nth-child(4) { text-align: right; }
      tfoot td { padding: 10px 14px; font-weight: 700; background: #f1f5f9; }
      .mono { font-family: 'DM Mono', monospace; font-size: 12px; }
      .total-row td { font-size: 14px; background: #0d9488; color: #fff; }
      .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 48px; padding-top: 32px; }
      .sig-box { text-align: center; }
      .sig-line { border-top: 1px solid #94a3b8; margin-top: 60px; padding-top: 8px; font-size: 12px; color: #64748b; }
      .sig-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
      .no-print { margin-bottom: 16px; text-align: right; }
      .btn-print { background: #0d9488; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
      .btn-print:hover { background: #0f766e; }
      @media print {
        .no-print { display: none; }
        body { font-size: 12px; }
        .page { padding: 20px 32px; }
      }
    </style>
    </head>
    <body>
    <div class="page">
      <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
        <a href="<?= APP_URL ?>/modules/commandes.php" style="margin-left:8px;font-size:13px;color:#64748b;">← Retour</a>
      </div>

      <div class="header">
        <div class="brand">
          <h1><?= e($appNom) ?></h1>
          <div class="sub"><?= e($appSousTitre) ?></div>
        </div>
        <div class="ref">
          <div class="num"><?= e($cmd['reference']) ?></div>
          <div class="date">
            <?php
            $statutLiv = $cmd['statut'];
            $statutLabel = ['en_attente'=>'En attente','en_cours'=>'En cours','livrée'=>'Livrée','annulée'=>'Annulée'];
            $statutClass = ['en_attente'=>'status-attente','en_cours'=>'status-en_cours','livrée'=>'status-livree','annulée'=>'status-attente'];
            ?>
            <span class="status <?= $statutClass[$statutLiv] ?? 'status-attente' ?>"><?= $statutLabel[$statutLiv] ?? $statutLiv ?></span>
            <span style="margin-left:8px;"><?= $cmd['date_commande'] ? date('d/m/Y', strtotime($cmd['date_commande'])) : '' ?></span>
          </div>
        </div>
      </div>

      <div class="info-grid">
        <div class="info-box">
          <div class="label">Fournisseur</div>
          <div class="value"><?= e($cmd['fourn'] ?? '—') ?></div>
          <?php if (!empty($cmd['fourn_tel'])): ?>
          <div style="font-size:12px;color:#64748b;margin-top:2px;">📞 <?= e($cmd['fourn_tel']) ?></div>
          <?php endif; ?>
          <?php if (!empty($cmd['fourn_adresse'])): ?>
          <div style="font-size:12px;color:#64748b;"><?= e($cmd['fourn_adresse']) ?></div>
          <?php endif; ?>
        </div>
        <div class="info-box">
          <div class="label">Informations commande</div>
          <div class="value">Créée par <?= e(trim($cmd['prenom'] . ' ' . $cmd['u_nom'])) ?></div>
          <?php if ($cmd['date_livraison']): ?>
          <div style="font-size:12px;color:#64748b;margin-top:2px;">Livraison : <?= date('d/m/Y', strtotime($cmd['date_livraison'])) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Produit</th>
            <th>Désignation</th>
            <th>Qté</th>
            <th>Prix achat</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cmdLignes as $l): ?>
          <tr>
            <td class="mono"><?= e($l['pnom'] ?? '—') ?></td>
            <td><?= e($l['designation']) ?></td>
            <td style="text-align:right;"><?= $l['quantite'] ?></td>
            <td class="mono" style="text-align:right;"><?= fmtMoney((float)$l['prix_unitaire']) ?></td>
            <td class="mono" style="text-align:right;"><?= fmtMoney($l['quantite'] * (float)$l['prix_unitaire']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cmdLignes)): ?>
          <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">Aucun produit</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td colspan="4" style="text-align:right;">TOTAL</td>
            <td style="text-align:right;"><?= fmtMoney($total) ?> FCFA</td>
          </tr>
        </tfoot>
      </table>

      <?php if ($cmd['note']): ?>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:4px;">Notes</div>
        <div style="font-size:13px;white-space:pre-wrap;"><?= e($cmd['note']) ?></div>
      </div>
      <?php endif; ?>

      <div class="signatures">
        <div class="sig-box">
          <div class="sig-label">Fournisseur</div>
          <div class="sig-line">Signature &amp; Cachet</div>
        </div>
        <div class="sig-box">
          <div class="sig-label">Réceptionnaire</div>
          <div class="sig-line">Signature</div>
        </div>
      </div>
    </div>
    </body>
    </html>
    <?php exit;
}

// ── Liste des commandes ──────────────────────────────────────
$commandes = $db->query("
    SELECT c.*, f.nom AS fourn, u.prenom, u.nom AS u_nom
    FROM commandes c
    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
    LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
    ORDER BY c.created_at DESC
")->fetchAll();
// Calculer le montant total depuis les lignes pour chaque commande
foreach ($commandes as &$c) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantite * prix_unitaire),0) FROM commande_lignes WHERE commande_id=?");
    $stmt->execute([$c['id']]);
    $c['montant_total'] = $stmt->fetchColumn();
}
unset($c);

layout_head('Commandes', 'commandes');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Commandes fournisseurs</div>
    <?php if (hasPermission('commandes.creer')): ?>
    <a href="?action=add" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Nouvelle commande</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>N°</th><th>Fournisseur</th><th>Produits</th><th>Montant</th>
          <th>Statut</th><th>Date</th><th>Créé par</th><?php if(hasPermission('commandes.modifier')):?><th>Actions</th><?php endif;?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($commandes as $c):
          [$badge, $label] = $statutMap[$c['statut']] ?? ['badge-gray', $c['statut']];
          $nbLignes = $db->prepare("SELECT COUNT(*) FROM commande_lignes WHERE commande_id=?");
          $nbLignes->execute([$c['id']]);
          $nb = $nbLignes->fetchColumn();
        ?>
        <tr>
          <td class="td-mono"><?= e($c['reference']) ?></td>
          <td class="td-name"><?= e($c['fourn'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= $nb ?> ligne<?= $nb > 1 ? 's' : '' ?></span></td>
          <td class="fw-mono c-teal"><?= fmtMoney((float)$c['montant_total']) ?></td>
          <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
          <td class="text-sm"><?= $c['date_commande'] ? date('d/m/Y', strtotime($c['date_commande'])) : '—' ?></td>
          <td class="text-sm"><?= e(trim($c['prenom'].' '.$c['u_nom'])) ?></td>
          <?php if (hasPermission('commandes.modifier')): ?>
          <td>
            <div class="flex gap-8">
              <a href="?action=bon&id=<?= $c['id'] ?>" class="btn btn-ghost btn-xs" title="Bon de livraison">🖨️</a>
              <?php if (in_array($c['statut'], ['en_attente', 'en_cours'])): ?>
              <a href="?action=livrer_form&id=<?= $c['id'] ?>" class="btn btn-primary btn-xs"><?= icon('check',13) ?> Livrer</a>
              <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?></a>
              <?php elseif ($c['statut'] === 'livrée'): ?>
              <span class="badge badge-green">Terminée</span>
              <?php else: ?>
              <span class="badge badge-red">Annulée</span>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$commandes): ?>
        <tr><td colspan="8">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('clipboard',36) ?></div>
            <div>Aucune commande</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_foot(); ?>