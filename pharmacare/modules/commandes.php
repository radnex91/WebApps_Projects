<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagination.php';
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
            header('Location: ' . url('commandes')); exit;
        }
    }
    $fourn_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
    $statut   = $_POST['statut'] ?? 'en_attente';
    $date_cmd = $_POST['date_commande'] ?? null;
    $date_liv = $_POST['date_livraison'] ?? null;
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
        flashError($e, 'commande');
    }
    header('Location: ' . url('commandes')); exit;
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
        header('Location: ' . url('commandes', ['action'=>'livrer_form','id'=>$id])); exit;
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

                // Entrée en stock (inventaire intermittent OHADA) :
                // le stock (3111) est mouvementé HT en contrepartie de la variation
                // de stock (6031), qui neutralise la charge 6011 à l'inventaire final.
                if ($montantHt > 0) {
                    $compteStock  = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
                    $compteVarStk = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
                    ecritureCreate($db,
                        'Entrée stock CMD ' . $cmd['reference'],
                        date('Y-m-d'),
                        [
                            [$compteStock,  $montantHt, 0, 'Entrée stock ' . $cmd['reference']],
                            [$compteVarStk, 0, $montantHt, 'Variation stock ' . $cmd['reference']],
                        ],
                        'commande', $cmd['reference'], currentUser()['id']
                    );
                }
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
            flashError($e, 'livraison commande');
        }
    } else {
        flash('Impossible de valider cette commande.', 'error');
    }
    header('Location: ' . url('commandes')); exit;
}

// ── Formulaire de validation livraison ─────────────────────────
if ($action === 'livrer_form' && $id && hasPermission('commandes.modifier')) {
    $stmt = $db->prepare("SELECT c.*, f.nom AS fourn FROM commandes c LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if (!$cmd || !in_array($cmd['statut'], ['en_attente', 'en_cours'])) {
        flash('Impossible de valider cette commande.', 'error');
        header('Location: ' . url('commandes')); exit;
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
        <a href="<?= url('commandes') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
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
            <tbody id="livr-lignes">
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
        <div id="livr-pager" class="pg-bar" style="display:none;"></div>
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
            <a href="<?= url('commandes') ?>" class="btn btn-ghost">Annuler</a>
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

        // ── Pagination des lignes de livraison (maxi 20 / page) ──
        var LIVR_PAGE_SIZE = 20;
        var livrPage = 1;
        function pagerHtml(page, pages, total, pageSize, fn) {
          if (total <= pageSize) return '';
          var start = (page - 1) * pageSize + 1;
          var end = Math.min(page * pageSize, total);
          var h = '<span class="pg-info">' + start + '–' + end + ' / ' + total + '</span>';
          h += '<button type="button" class="pg-btn"' + (page <= 1 ? ' disabled' : '') + ' onclick="' + fn + '(' + (page - 1) + ')">‹</button>';
          var from = Math.max(1, page - 2), to = Math.min(pages, page + 2);
          if (from > 1) { h += '<button type="button" class="pg-btn" onclick="' + fn + '(1)">1</button>'; if (from > 2) h += '<span class="pg-ellipsis">…</span>'; }
          for (var p = from; p <= to; p++) h += '<button type="button" class="pg-btn' + (p === page ? ' active' : '') + '" onclick="' + fn + '(' + p + ')">' + p + '</button>';
          if (to < pages) { if (to < pages - 1) h += '<span class="pg-ellipsis">…</span>'; h += '<button type="button" class="pg-btn" onclick="' + fn + '(' + pages + ')">' + pages + '</button>'; }
          h += '<button type="button" class="pg-btn"' + (page >= pages ? ' disabled' : '') + ' onclick="' + fn + '(' + (page + 1) + ')">›</button>';
          return h;
        }
        function renderLivrPage() {
          var tbody = document.getElementById('livr-lignes');
          if (!tbody) return;
          var rows = tbody.rows, total = rows.length;
          var pages = Math.max(1, Math.ceil(total / LIVR_PAGE_SIZE));
          if (livrPage > pages) livrPage = pages;
          if (livrPage < 1) livrPage = 1;
          var start = (livrPage - 1) * LIVR_PAGE_SIZE, end = start + LIVR_PAGE_SIZE;
          for (var i = 0; i < total; i++) rows[i].style.display = (i >= start && i < end) ? '' : 'none';
          var el = document.getElementById('livr-pager');
          var html = pagerHtml(livrPage, pages, total, LIVR_PAGE_SIZE, 'livrGoPage');
          el.innerHTML = html; el.style.display = html ? '' : 'none';
        }
        function livrGoPage(pg) {
          livrPage = pg; renderLivrPage();
          var wrap = document.querySelector('.card .table-wrap');
          if (wrap) wrap.scrollTop = 0;
        }
        renderLivrPage();
        </script>
      </div>
    </div>
    <?php layout_foot(); exit;
}

// ── Supprimer une commande (POST + CSRF) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && hasPermission('commandes.modifier')) {
    verifyCsrf();
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId) {
        $db->prepare("DELETE FROM commande_lignes WHERE commande_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM commandes WHERE id=?")->execute([$delId]);
        flash('Commande supprimée.');
    }
    header('Location: ' . url('commandes')); exit;
}

// ── Formulaire ajout / modification ─────────────────────────
if (in_array($action, ['add', 'edit'])) {
    if ($id) {
        $stmtCheck = $db->prepare("SELECT statut FROM commandes WHERE id=?");
        $stmtCheck->execute([$id]);
        $checkRow = $stmtCheck->fetch();
        if ($checkRow && in_array($checkRow['statut'], ['livrée', 'annulée'])) {
            flash('Cette commande ne peut plus être modifiée.', 'error');
            header('Location: ' . url('commandes')); exit;
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
        <a href="<?= url('commandes') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Retour</a>
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
          <div id="cmd-summary-pager" class="pg-bar" style="display:none;margin-top:8px;"></div>
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
          <a href="<?= url('commandes') ?>" class="btn btn-ghost">Annuler</a>
          <?php if ($id && hasPermission('commandes.modifier')): ?>
          <button type="button" class="btn btn-danger" onclick="confirmDeletePost('delete','<?= (int)$id ?>','Supprimer cette commande ?')"><?= icon('trash',14) ?> Supprimer</button>
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
          <div id="cmd-prod-pager" class="pg-bar" style="display:none;margin-top:12px;"></div>
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

    // Map pid -> produit (recherche O(1) au lieu de find() O(N) sur 400+ lignes).
    var pidToProd = {};
    produitsData.forEach(function(p){ pidToProd[p.id] = p; });

    // ── Pagination de la liste (maxi 20 produits / page) ──
    var CMD_PAGE_SIZE = 20;
    var cmdFilteredRows = []; // <tr> correspondant au filtre courant
    var cmdPage = 1;

    function pagerHtml(page, pages, total, pageSize, fn) {
      if (total <= pageSize) return '';
      var start = (page - 1) * pageSize + 1;
      var end = Math.min(page * pageSize, total);
      var h = '<span class="pg-info">' + start + '–' + end + ' / ' + total + '</span>';
      h += '<button type="button" class="pg-btn"' + (page <= 1 ? ' disabled' : '') + ' onclick="' + fn + '(' + (page - 1) + ')">‹</button>';
      var from = Math.max(1, page - 2), to = Math.min(pages, page + 2);
      if (from > 1) { h += '<button type="button" class="pg-btn" onclick="' + fn + '(1)">1</button>'; if (from > 2) h += '<span class="pg-ellipsis">…</span>'; }
      for (var p = from; p <= to; p++) h += '<button type="button" class="pg-btn' + (p === page ? ' active' : '') + '" onclick="' + fn + '(' + p + ')">' + p + '</button>';
      if (to < pages) { if (to < pages - 1) h += '<span class="pg-ellipsis">…</span>'; h += '<button type="button" class="pg-btn" onclick="' + fn + '(' + pages + ')">' + pages + '</button>'; }
      h += '<button type="button" class="pg-btn"' + (page >= pages ? ' disabled' : '') + ' onclick="' + fn + '(' + (page + 1) + ')">›</button>';
      return h;
    }

    var _escDiv = document.createElement('div');
    function esc(s){ _escDiv.textContent = s == null ? '' : s; return _escDiv.innerHTML; }
    function fmtPrix(n){ return Math.round(n).toLocaleString('fr-FR'); }

    // Construit la liste en UNE seule chaine -> un seul reflow (au lieu de N appendChild).
    function openCmdProduitModal() {
      var tbody = document.getElementById('cmd-prod-tbody');
      var html = '';
      for (var i = 0; i < produitsData.length; i++) {
        var p = produitsData[i];
        var sel = cmdSelected[p.id];
        var nomAttr = p.nom.toLowerCase().replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        html += '<tr data-nom="'+nomAttr+'">' +
          '<td style="text-align:center;"><input type="checkbox" class="cmd-check" data-pid="'+p.id+'" onchange="onCmdCheck(this)" '+(sel?'checked':'')+'></td>' +
          '<td class="td-name">'+esc(p.nom)+'</td>' +
          '<td style="text-align:right;"><input type="number" class="cmd-qte" data-pid="'+p.id+'" min="1" value="'+(sel?sel.qte:1)+'" style="width:80px;text-align:right;" '+(sel?'':'disabled')+' oninput="updateCmdModalSummary()"></td>' +
          '<td style="text-align:right;"><input type="number" class="cmd-prix" data-pid="'+p.id+'" min="0" step="1" value="'+(sel?sel.prix:p.prix)+'" style="width:110px;text-align:right;" '+(sel?'':'disabled')+' oninput="updateCmdModalSummary()"></td>' +
          '</tr>';
      }
      tbody.innerHTML = html;
      document.getElementById('cmd-prod-search').value = '';
      document.getElementById('cmd-prod-selectall').checked = false;
      filterCmdList();
      updateCmdModalSummary();
      openModal('modal-cmd-produits');
    }

    // Resume en UNE seule passe sur les lignes (O(N)) — pas de querySelector par case.
    function updateCmdModalSummary() {
      var rows = document.getElementById('cmd-prod-tbody').rows;
      var count = 0, total = 0;
      for (var i = 0; i < rows.length; i++) {
        var cb = rows[i].querySelector('.cmd-check');
        if (!cb || !cb.checked) continue;
        var q = parseInt(rows[i].querySelector('.cmd-qte').value, 10) || 0;
        var p = parseFloat(String(rows[i].querySelector('.cmd-prix').value).replace(',', '.')) || 0;
        count++; total += q * p;
      }
      document.getElementById('cmd-prod-summary').textContent = count + ' produit(s) sélectionné(s) — ' + total.toLocaleString('fr-FR') + ' FCFA';
    }

    function onCmdCheck(cb) {
      var row = cb.closest('tr');
      var qte = row.querySelector('.cmd-qte');
      var prix = row.querySelector('.cmd-prix');
      qte.disabled = !cb.checked; prix.disabled = !cb.checked;
      if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); }
      updateCmdModalSummary();
    }

    // Tout selectionner : O(N) sur les lignes FILTREES (visibles via recherche).
    // On modifie les cases en place et on recalcule le resume UNE seule fois a la
    // fin (plus de onCmdCheck par case => fini le O(N²)). Coche toutes les pages
    // du filtre, pas seulement la page courante.
    function toggleAllCmd(checked) {
      for (var i = 0; i < cmdFilteredRows.length; i++) {
        var cb = cmdFilteredRows[i].querySelector('.cmd-check');
        if (!cb || cb.disabled) continue;
        if (cb.checked === checked) continue;
        cb.checked = checked;
        var qte = cmdFilteredRows[i].querySelector('.cmd-qte');
        var prix = cmdFilteredRows[i].querySelector('.cmd-prix');
        qte.disabled = !checked; prix.disabled = !checked;
        if (checked && !qte.value) qte.value = '1';
      }
      updateCmdModalSummary();
    }

    // Filtre + pagination : calcule les lignes correspondantes, revient a la
    // page 1, puis n'affiche que la tranche de la page courante.
    function filterCmdList() {
      var q = document.getElementById('cmd-prod-search').value.toLowerCase();
      var rows = document.getElementById('cmd-prod-tbody').rows;
      cmdFilteredRows = [];
      for (var i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute('data-nom').indexOf(q) > -1) cmdFilteredRows.push(rows[i]);
      }
      cmdPage = 1;
      renderCmdPage();
    }

    function renderCmdPage() {
      var allRows = document.getElementById('cmd-prod-tbody').rows;
      var total = cmdFilteredRows.length;
      var pages = Math.max(1, Math.ceil(total / CMD_PAGE_SIZE));
      if (cmdPage > pages) cmdPage = pages;
      if (cmdPage < 1) cmdPage = 1;
      var start = (cmdPage - 1) * CMD_PAGE_SIZE, end = start + CMD_PAGE_SIZE;
      for (var i = 0; i < allRows.length; i++) allRows[i].style.display = 'none';
      for (var i = start; i < end && i < total; i++) cmdFilteredRows[i].style.display = '';
      var el = document.getElementById('cmd-prod-pager');
      var html = pagerHtml(cmdPage, pages, total, CMD_PAGE_SIZE, 'cmdGoPage');
      el.innerHTML = html; el.style.display = html ? '' : 'none';
    }

    function cmdGoPage(pg) {
      cmdPage = pg; renderCmdPage();
      var wrap = document.querySelector('#modal-cmd-produits .table-wrap');
      if (wrap) wrap.scrollTop = 0;
    }

    function confirmCmdSelection() {
      var next = {};
      var rows = document.getElementById('cmd-prod-tbody').rows;
      for (var i = 0; i < rows.length; i++) {
        var cb = rows[i].querySelector('.cmd-check');
        if (!cb || !cb.checked) continue;
        var pid = cb.getAttribute('data-pid');
        var qte = parseInt(rows[i].querySelector('.cmd-qte').value, 10) || 0;
        var prix = parseFloat(String(rows[i].querySelector('.cmd-prix').value).replace(',', '.')) || 0;
        var prod = pidToProd[pid];
        next[pid] = {nom: prod ? prod.nom : '', qte: qte, prix: prix};
      }
      cmdSelected = next;
      closeModal('modal-cmd-produits');
      renderCmdSummary();
      calcTotal();
    }

    // ── Pagination du récapitulatif des produits sélectionnés (maxi 20 / page) ──
    var CMD_SUM_PAGE_SIZE = 20;
    var cmdSumPage = 1;

    function renderCmdSummary() {
      var tbody = document.querySelector('#cmd-summary-table tbody');
      tbody.innerHTML = '';
      var keys = Object.keys(cmdSelected);
      if (!keys.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Aucun produit sélectionné — cliquez sur « Choisir les produits ».</div></td></tr>';
        var pgr = document.getElementById('cmd-summary-pager');
        if (pgr) { pgr.innerHTML = ''; pgr.style.display = 'none'; }
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
      renderCmdSummaryPage();
    }

    function renderCmdSummaryPage() {
      var tbody = document.querySelector('#cmd-summary-table tbody');
      var rows = tbody.rows, total = rows.length;
      var pages = Math.max(1, Math.ceil(total / CMD_SUM_PAGE_SIZE));
      if (cmdSumPage > pages) cmdSumPage = pages;
      if (cmdSumPage < 1) cmdSumPage = 1;
      var start = (cmdSumPage - 1) * CMD_SUM_PAGE_SIZE, end = start + CMD_SUM_PAGE_SIZE;
      for (var i = 0; i < total; i++) rows[i].style.display = (i >= start && i < end) ? '' : 'none';
      var el = document.getElementById('cmd-summary-pager');
      var html = pagerHtml(cmdSumPage, pages, total, CMD_SUM_PAGE_SIZE, 'cmdSumGoPage');
      el.innerHTML = html; el.style.display = html ? '' : 'none';
    }

    function cmdSumGoPage(pg) {
      cmdSumPage = pg; renderCmdSummaryPage();
      var wrap = document.querySelector('#cmd-summary-table').closest('.table-wrap');
      if (wrap) wrap.scrollTop = 0;
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

// ── Bon de livraison de commande (impression MINSANTÉ) ────────
if ($action === 'bon' && $id) {
    $stmt = $db->prepare("SELECT c.*, f.nom AS fourn, f.telephone AS fourn_tel, f.adresse AS fourn_adresse, u.prenom, u.nom AS u_nom
        FROM commandes c
        LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
        LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.id = ?");
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if (!$cmd) { flash('Commande introuvable.', 'error'); header('Location: ' . url('commandes')); exit; }

    $lignes = $db->prepare("SELECT cl.*, p.nom AS pnom, p.reference AS pref FROM commande_lignes cl LEFT JOIN produits p ON cl.produit_id = p.id WHERE cl.commande_id=? ORDER BY cl.id");
    $lignes->execute([$id]);
    $cmdLignes = $lignes->fetchAll();

    $bonEtsNom = getParam('app_nom', 'PharmaCare');
    $bonEtsAdr = getParam('pharmacie_adresse', '');
    $bonEtsTel = getParam('pharmacie_telephone', '');
    $bonEtsNif = getParam('pharmacie_nif', '');
    $bonDevSym = getParam('devise_symbole', 'FCFA');
    $bonLogoUrl = pharmacieLogoUrl();

    $bonTotalCmd  = 0;
    $bonTotalLiv  = 0;
    $bonTotalMont = 0.0;
    foreach ($cmdLignes as $bl) {
        $bonTotalCmd  += (int)$bl['quantite'];
        // Qté livrée = qté commandée tant que la commande n'est pas encore
        // livrée avec constat d'écart ; on affiche donc la même valeur,
        // la colonne « Observations » servant à noter les écarts éventuels.
        $bonTotalLiv  += (int)$bl['quantite'];
        $bonTotalMont += (float)$bl['prix_unitaire'] * (int)$bl['quantite'];
    }

    $bonDateCmd   = $cmd['date_commande']   ? date('d/m/Y', strtotime($cmd['date_commande']))   : '—';
    $bonDateLiv   = $cmd['date_livraison']  ? date('d/m/Y', strtotime($cmd['date_livraison']))  : '—';
    $bonStatutLbl = ['en_attente'=>'En attente','en_cours'=>'En cours','livrée'=>'Livrée','annulée'=>'Annulée'][$cmd['statut']] ?? $cmd['statut'];
    // Référence du bon de livraison dérivée de la commande (BL-<ref cmd sans préfixe>).
    $bonRef = 'BL-' . preg_replace('/^CMD-/', '', $cmd['reference']);

    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<title>Bon de livraison fournisseur <?= e($cmd['reference']) ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 12px; margin: 0; }
  /* Bandeau d'identification du document (distinct du bon interne de ravitaillement) */
  .bandeau { display: flex; justify-content: space-between; align-items: stretch; border: 2px solid #0f172a; margin-bottom: 14px; }
  .bandeau .gauche { padding: 12px 18px; }
  .bandeau .gauche .t { font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #0f172a; }
  .bandeau .gauche .s { font-size: 11px; color: #475569; margin-top: 2px; }
  .bandeau .droite { padding: 10px 18px; text-align: right; border-left: 1px solid #94a3b8; }
  .bandeau .droite .r { font-size: 15px; font-weight: 700; color: #0f172a; }
  .bandeau .droite .d { font-size: 11px; color: #475569; margin-top: 3px; }
  /* Bloc Expéditeur / Destinataire (propre au bon de livraison fournisseur) */
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
  .partie { border: 1px solid #0f172a; padding: 10px 12px; }
  .partie .cap { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-bottom: 8px; display: block; }
  .partie .nom { font-weight: 700; font-size: 13px; }
  .partie .ligne { font-size: 11px; color: #475569; margin-top: 2px; }
  .meta { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px 18px; margin-bottom: 14px; font-size: 12px; }
  .meta .lbl { color: #64748b; display: inline-block; min-width: 120px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  th, td { border: 1px solid #94a3b8; padding: 6px 7px; vertical-align: top; }
  th { background: #0f172a; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
  td.right, th.right { text-align: right; }
  td.center, th.center { text-align: center; }
  tfoot td { font-weight: 700; background: #f1f5f9; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 46px; text-align: center; }
  .signatures .role { font-weight: 600; font-size: 11px; margin-bottom: 26px; }
  .signatures .sig { border-top: 1px solid #475569; padding-top: 5px; font-size: 10px; color: #64748b; }
  .pied { margin-top: 26px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
  .toolbar { text-align: center; margin-bottom: 10px; }
  .toolbar button { padding: 8px 18px; font-size: 13px; cursor: pointer; border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 6px; }
  @media print { .toolbar { display: none; } body { font-size: 11px; } }
</style></head>
<body>
  <div class="toolbar"><button onclick="window.print()">🖨️ Imprimer le bon de livraison</button></div>

  <div class="bandeau">
    <div class="gauche">
      <?php if ($bonLogoUrl): ?>
      <div style="margin-bottom:6px;"><img src="<?= e($bonLogoUrl) ?>" alt="" style="max-height:54px;max-width:220px;"></div>
      <?php endif; ?>
      <div class="t">Bon de Livraison</div>
      <div class="s">Réception de livraison fournisseur</div>
    </div>
    <div class="droite">
      <div class="r"><?= e($bonRef) ?></div>
      <div class="d">Commande : <?= e($cmd['reference']) ?> — Statut : <?= e($bonStatutLbl) ?></div>
    </div>
  </div>

  <div class="parties">
    <div class="partie">
      <span class="cap">Expéditeur — Fournisseur</span>
      <div class="nom"><?= e($cmd['fourn'] ?? '—') ?></div>
      <?php if (!empty($cmd['fourn_adresse'])): ?><div class="ligne"><?= e($cmd['fourn_adresse']) ?></div><?php endif; ?>
      <?php if (!empty($cmd['fourn_tel'])): ?><div class="ligne">Tél : <?= e($cmd['fourn_tel']) ?></div><?php endif; ?>
    </div>
    <div class="partie">
      <span class="cap">Destinataire — Établissement</span>
      <div class="nom"><?= e($bonEtsNom) ?></div>
      <?php if ($bonEtsAdr): ?><div class="ligne"><?= e($bonEtsAdr) ?></div><?php endif; ?>
      <?php if ($bonEtsTel): ?><div class="ligne">Tél : <?= e($bonEtsTel) ?></div><?php endif; ?>
      <?php if ($bonEtsNif): ?><div class="ligne">NIF : <?= e($bonEtsNif) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="meta">
    <div><span class="lbl">Date de commande :</span> <?= $bonDateCmd ?></div>
    <div><span class="lbl">Date de livraison :</span> <?= $bonDateLiv ?></div>
    <div><span class="lbl">Réceptionné par :</span> <?= e(trim($cmd['prenom'] . ' ' . $cmd['u_nom'])) ?: '—' ?></div>
    <?php if (trim($cmd['note'] ?? '')): ?><div style="grid-column:1/-1;"><span class="lbl">Observations commande :</span> <?= e($cmd['note']) ?></div><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th class="center" style="width:5%;">N°</th>
        <th>Désignation</th>
        <th style="width:11%;">Réf. produit</th>
        <th class="center" style="width:11%;">Qté commandée</th>
        <th class="center" style="width:11%;">Qté livrée</th>
        <th class="right" style="width:12%;">P.U. (<?= e($bonDevSym) ?>)</th>
        <th class="right" style="width:13%;">Montant (<?= e($bonDevSym) ?>)</th>
        <th style="width:13%;">Observations</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($cmdLignes as $bl):
        $pu = (float)($bl['prix_unitaire'] ?? 0);
        $mt = $pu * (int)$bl['quantite'];
      ?>
      <tr>
        <td class="center"><?= $i++ ?></td>
        <td><?= e($bl['designation']) ?></td>
        <td><?= e($bl['pref'] ?? '—') ?></td>
        <td class="center"><?= (int)$bl['quantite'] ?></td>
        <td class="center"><?= (int)$bl['quantite'] ?></td>
        <td class="right"><?= $pu > 0 ? fmtMoney($pu) : '—' ?></td>
        <td class="right"><?= $pu > 0 ? fmtMoney($mt) : '—' ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$cmdLignes): ?>
      <tr><td colspan="8" class="center" style="padding:14px;color:#94a3b8;">Aucune ligne</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="right">TOTAUX</td>
        <td class="center"><?= $bonTotalCmd ?></td>
        <td class="center"><?= $bonTotalLiv ?></td>
        <td></td>
        <td class="right"><?= $bonTotalMont > 0 ? fmtMoney($bonTotalMont) : '—' ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div><div class="role">Le Fournisseur<br><small style="font-weight:400;color:#64748b">(livreur / émetteur)</small></div><div class="sig">Signature &amp; cachet</div></div>
    <div><div class="role">Le Magasinier<br><small style="font-weight:400;color:#64748b">(réceptionnaire)</small></div><div class="sig">Signature &amp; cachet</div></div>
  </div>

  <div class="pied">Document généré électroniquement par <?= e($bonEtsNom) ?> le <?= date('d/m/Y à H:i') ?> — Bon de livraison fournisseur (commande <?= e($cmd['reference']) ?>).<br>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></div>

  <script>
    window.onafterprint = function(){ window.location.href = <?= json_encode(url('commandes')) ?>; };
    window.onload = function(){ setTimeout(function(){ window.print(); }, 300); };
  </script>
</body></html>
<?php
    exit;
}

// ── Liste des commandes (pagination serveur + batch des agrégats) ──
// Avant : fetch de TOUTES les commandes + 1 SUM par commande + 1 COUNT
// par ligne de rendu → N+1+N requêtes. Désormais : 1 COUNT total, 1 page
// de lignes, et 2 requêtes groupées (SUM, COUNT) sur les IDs de la page.

// ── Export Excel de toutes les commandes ────────────────────
if ($action === 'export') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $statuts = ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'livrée' => 'Livrée', 'annulée' => 'Annulée'];
    $rowsX = [];
    $stX = $db->query("
        SELECT c.reference, f.nom AS fourn, CONCAT(u.prenom, ' ', u.nom) AS cree_par,
               c.created_at, c.statut,
               COUNT(cl.id) AS nb_lignes,
               COALESCE(SUM(cl.quantite * cl.prix_unitaire), 0) AS montant
        FROM commandes c
        LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
        LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
        LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    foreach ($stX->fetchAll() as $c) {
        $rowsX[] = [
            $c['reference'], $c['fourn'], $c['cree_par'],
            date('d/m/Y H:i', strtotime($c['created_at'])),
            $statuts[$c['statut']] ?? $c['statut'],
            (int)$c['nb_lignes'], (float)$c['montant'],
        ];
    }
    export_xlsx_send('commandes_' . date('Y-m-d'), 'Commandes',
        ['Référence', 'Fournisseur', 'Créée par', 'Date', 'Statut', 'Nb lignes', 'Montant'], $rowsX);
}

$perPage = 25; // section Gestion : pagination uniforme à 25/page
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = paginateOffset($page, $perPage);

$total = (int)$db->query("SELECT COUNT(*) FROM commandes")->fetchColumn();

$commandes = $db->prepare("
    SELECT c.*, f.nom AS fourn, u.prenom, u.nom AS u_nom
    FROM commandes c
    LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
    LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$commandes->execute();
$commandes = $commandes->fetchAll();

// Batch des agrégats : 1 SUM groupé + 1 COUNT groupé sur la page entière,
// au lieu de 2 requêtes par commande → fini le N+1.
$montantsParCmd = [];
$nbLignesParCmd = [];
if ($commandes) {
    $ids = array_column($commandes, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    $stSum = $db->prepare("SELECT commande_id, COALESCE(SUM(quantite * prix_unitaire),0) AS mt
                            FROM commande_lignes WHERE commande_id IN ($ph) GROUP BY commande_id");
    $stSum->execute($ids);
    foreach ($stSum->fetchAll(PDO::FETCH_ASSOC) as $r) $montantsParCmd[(int)$r['commande_id']] = (float)$r['mt'];

    $stCnt = $db->prepare("SELECT commande_id, COUNT(*) AS nb
                            FROM commande_lignes WHERE commande_id IN ($ph) GROUP BY commande_id");
    $stCnt->execute($ids);
    foreach ($stCnt->fetchAll(PDO::FETCH_ASSOC) as $r) $nbLignesParCmd[(int)$r['commande_id']] = (int)$r['nb'];
}
foreach ($commandes as &$c) {
    $c['montant_total'] = $montantsParCmd[(int)$c['id']] ?? 0.0;
}
unset($c);

layout_head('Commandes', 'commandes');
showFlash();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Commandes fournisseurs</div>
    <a href="<?= url('commandes', ['action'=>'export']) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a>
    <?php if (hasPermission('commandes.creer')): ?>
    <a href="<?= url('commandes', ['action'=>'add']) ?>" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Nouvelle commande</a>
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
          $nb = $nbLignesParCmd[(int)$c['id']] ?? 0;
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
              <a href="<?= url('commandes', ['action'=>'bon','id'=>$c['id']]) ?>" class="btn btn-ghost btn-xs" title="Bon de livraison">🖨️</a>
              <?php if (in_array($c['statut'], ['en_attente', 'en_cours'])): ?>
              <a href="<?= url('commandes', ['action'=>'livrer_form','id'=>$c['id']]) ?>" class="btn btn-primary btn-xs"><?= icon('check',13) ?> Livrer</a>
              <a href="<?= url('commandes', ['action'=>'edit','id'=>$c['id']]) ?>" class="btn btn-ghost btn-xs"><?= icon('edit',13) ?></a>
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
  <?= renderPagination($page, $perPage, $total, []) ?>
</div>
<?php layout_foot(); ?>