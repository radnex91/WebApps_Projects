<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('vente.creer');
$db = getDB();

$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte bancaire',
    'chèque'    => 'Chèque',
    'assurance' => 'Assurance',
    'crédit'    => 'Crédit client',
];

// ── Helper solde session caisse ────────────────────────────
function soldeSessionCaisse(PDO $db, int $sessionId): float {
    $stmt = $db->prepare("
        SELECT s.fond_initial,
               COALESCE((SELECT SUM(montant) FROM mouvements_caisse WHERE session_id = s.id AND type = 'entrée'), 0) AS entrees,
               COALESCE((SELECT SUM(montant) FROM mouvements_caisse WHERE session_id = s.id AND type = 'sortie'), 0) AS sorties
        FROM sessions_caisse s WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $r = $stmt->fetch();
    return (float)$r['fond_initial'] + (float)$r['entrees'] - (float)$r['sorties'];
}

// ── Vérification session caisse ouverte ─────────────────────
$sessionActive = null;
if (hasPermission('caisse.ouvrir')) {
    $stmtSession = $db->prepare("
        SELECT s.*, c.nom AS caisse_nom
        FROM sessions_caisse s
        JOIN caisses c ON s.caisse_id = c.id
        WHERE s.caissier_id = ? AND s.statut = 'ouverte'
    ");
    $stmtSession->execute([currentUser()['id']]);
    $sessionActive = $stmtSession->fetch();

    if (!$sessionActive) {
        flash('Vous devez ouvrir une caisse avant de pouvoir vendre.', 'error');
        header('Location: ' . APP_URL . '/modules/caisse.php?action=ouvrir'); exit;
    }
}

// ── Traitement vente POST ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    verifyCsrf();
    $cartRaw = json_decode($_POST['cart_data'], true);

    if (!$cartRaw || count($cartRaw) === 0) {
        flash('Le panier est vide.', 'error');
        header('Location: ' . APP_URL . '/modules/vente.php'); exit;
    }

    // Vérifier que le mode de paiement est valide
    $modePaiement = $_POST['mode_paiement'] ?? 'espèces';
    if (!isset($modeLabels[$modePaiement])) {
        $modePaiement = 'espèces';
    }

    // Client pour credit obligatoire
    $clientId = null;
    if ($modePaiement === 'crédit') {
        $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    }
    // Si credit sans client, on repasse en especes
    if ($modePaiement === 'crédit' && !$clientId) {
        flash('Un client est requis pour une vente à crédit.', 'error');
        header('Location: ' . APP_URL . '/modules/vente.php'); exit;
    }

    $db->beginTransaction();
    try {
        $tvaRate   = (float)getParam('tva', '19.25') / 100;
        $tvaPct    = (float)getParam('tva', '19.25');
        $subtotal  = 0;
        $tva_total = 0;

        // Vérifier les prix côté serveur (sécurité anti-manipulation)
        $pids = array_values(array_map(fn($i) => (int)$i['id'], $cartRaw));
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        $stmtP = $db->prepare("SELECT id, prix_vente, nom, stock FROM produits WHERE id IN ($ph) AND actif = 1");
        $stmtP->execute($pids);
        $realPrices = [];
        while ($row = $stmtP->fetch()) {
            $realPrices[$row['id']] = $row;
        }

        foreach ($cartRaw as $item) {
            $pid  = (int)$item['id'];
            if (!isset($realPrices[$pid])) {
                throw new Exception('Produit introuvable : ' . ($item['name'] ?? 'ID ' . $pid));
            }
            $price = (float)$realPrices[$pid]['prix_vente']; // prix serveur, pas client
            $qty   = max(1, (int)$item['qty']);
            $sub   = $price * $qty;
            $subtotal  += $sub;
            $tva_total += $sub * $tvaRate;
        }

        $total   = $subtotal + $tva_total;
        $ref     = genRef(getParam('prefix_vente', 'VNT'));
        $recuRaw = (float)($_POST['montant_recu'] ?? 0);
        $monnaie = max(0, $recuRaw - $total);

        $stmt = $db->prepare("
            INSERT INTO ventes
                (reference, client_nom, client_telephone, client_id, caissier_id,
                 sous_total, tva_total, total, mode_paiement, statut_paiement,
                 montant_recu, monnaie, note)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $ref,
            trim($_POST['client_nom'] ?? ''),
            trim($_POST['client_tel']  ?? ''),
            $clientId,
            currentUser()['id'],
            round($subtotal,  2),
            round($tva_total, 2),
            round($total,     2),
            $modePaiement,
            $modePaiement === 'crédit' ? 'en_attente' : 'payé',
            $modePaiement === 'crédit' ? 0 : $recuRaw,
            $modePaiement === 'crédit' ? 0 : round($monnaie, 2),
            trim($_POST['note'] ?? '')
        ]);
        $vid = $db->lastInsertId();

        $stmtL = $db->prepare("
            INSERT INTO vente_lignes
                (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmtS = $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");

        foreach ($cartRaw as $item) {
            $pid   = (int)$item['id'];
            $qty   = max(1, (int)$item['qty']);
            $price = (float)$realPrices[$pid]['prix_vente'];
            $nom   = $realPrices[$pid]['nom'];
            $stmtL->execute([$vid, $pid, $nom, $qty, $price, $tvaPct, round($price * $qty, 2)]);
            $stmtS->execute([$qty, $pid, $qty]);
            if ($stmtS->rowCount() === 0) {
                throw new Exception('Stock insuffisant pour le produit : ' . $nom);
            }
        }

        // Enregistrement des mouvements de stock
        $stmtM = $db->prepare("
            INSERT INTO mouvements_stock (produit_id, type, quantite, motif, utilisateur_id)
            VALUES (?, 'sortie', ?, ?, ?)
        ");
        foreach ($cartRaw as $item) {
            $stmtM->execute([
                (int)$item['id'],
                max(1, (int)$item['qty']),
                'Vente ' . $ref,
                currentUser()['id']
            ]);
        }

        // Mouvement de caisse (ventes en espèces uniquement, pas de credit)
        if ($modePaiement === 'espèces' && $sessionActive) {
            $stmtCaisse = $db->prepare("
                INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen, reference_vente)
                VALUES (?, 'entrée', ?, ?, 'espèces', ?)
            ");
            $stmtCaisse->execute([
                (int)$sessionActive['id'],
                round($total, 2),
                'Vente ' . $ref,
                $ref
            ]);
        }

        // Mouvement de caisse a 0 pour les ventes a credit (tracabilite)
        if ($modePaiement === 'crédit' && $sessionActive) {
            $stmtCaisse = $db->prepare("
                INSERT INTO mouvements_caisse (session_id, type, montant, motif, moyen, reference_vente)
                VALUES (?, 'entrée', 0, ?, 'crédit', ?)
            ");
            $stmtCaisse->execute([
                (int)$sessionActive['id'],
                'Vente crédit ' . $ref,
                $ref
            ]);
        }

        // ── Écriture comptable automatique ────────────────
        if ($modePaiement === 'crédit' && $clientId) {
            // Vente a credit : debit compte client au lieu de caisse
            $clientInfo = $db->prepare("SELECT nom FROM clients WHERE id=?");
            $clientInfo->execute([$clientId]);
            $clientNom = $clientInfo->fetchColumn() ?: 'Client #'.$clientId;
            $compteClient = compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit');
            $compteVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
            $compteTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
            ecritureCreate($db,
                'Vente ' . $ref . ' - Crédit ' . $clientNom,
                date('Y-m-d'),
                [
                    [$compteClient, round($total, 2), 0, 'Crédit client ' . $clientNom . ' - ' . $ref],
                    [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                    [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
                ],
                'vente', $ref, currentUser()['id']
            );
        } else {
            $mapCompte = [
                'espèces'   => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
                'carte'     => compteFindOrCreate($db, '512', 'Banque', 5, 'debit'),
                'chèque'    => compteFindOrCreate($db, '511', 'Chèques à encaisser', 5, 'debit'),
                'assurance' => compteFindOrCreate($db, '4111', 'Clients - Assurance', 4, 'debit'),
            ];
            $caisseCompte  = $mapCompte[$modePaiement] ?? $mapCompte['espèces'];
            $compteVente   = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
            $compteTva     = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
            ecritureCreate($db,
                'Vente ' . $ref . ' - ' . $modeLabels[$modePaiement],
                date('Y-m-d'),
                [
                    [$caisseCompte, round($total, 2), 0, 'Vente ' . $ref],
                    [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                    [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
                ],
                'vente', $ref, currentUser()['id']
            );
        }

        $db->commit();
        header('Location: ' . APP_URL . '/modules/vente.php?receipt=' . urlencode($ref)); exit;

    } catch (Exception $e) {
        $db->rollBack();
        flash('Erreur : ' . $e->getMessage(), 'error');
        header('Location: ' . APP_URL . '/modules/vente.php'); exit;
    }
}

// ── Données catalogue ──────────────────────────────────────
$produits = $db->query("
    SELECT p.*, c.nom AS cat
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    WHERE p.actif = 1
    ORDER BY c.nom, p.nom
")->fetchAll();

$categories = $db->query("
    SELECT DISTINCT c.nom
    FROM categories c
    JOIN produits p ON p.categorie_id = c.id
    WHERE p.actif = 1
    ORDER BY c.nom
")->fetchAll(PDO::FETCH_COLUMN);

$tvaTaux  = getParam('tva',           '19.25');
$devSym   = getParam('devise_symbole','FCFA');
$devPos   = getParam('devise_pos',    'after');
$appNom   = getParam('app_nom',       'PharmaCare');
$ticketSousTitre = getParam('ticket_sous_titre', 'Gestion Pharmacie');
$ticketPied      = getParam('ticket_pied', 'Merci pour votre achat !');
$pharmAdresse    = getParam('pharmacie_adresse', '');
$pharmTel        = getParam('pharmacie_telephone', '');
$pharmNif        = getParam('pharmacie_nif', '');

// ── Données ticket (si on vient de valider une vente) ──────
$receiptRef = $_GET['receipt'] ?? '';
$receiptData = null;
if ($receiptRef) {
    $stmtR = $db->prepare("
        SELECT v.*, u.prenom, u.nom AS u_nom
        FROM ventes v
        LEFT JOIN utilisateurs u ON v.caissier_id = u.id
        WHERE v.reference = ?
    ");
    $stmtR->execute([$receiptRef]);
    $receiptData = $stmtR->fetch();
    if ($receiptData) {
        $stmtRL = $db->prepare("SELECT * FROM vente_lignes WHERE vente_id = ? ORDER BY id");
        $stmtRL->execute([$receiptData['id']]);
        $receiptData['lignes'] = $stmtRL->fetchAll();
    }
}

layout_head('Point de Vente', 'vente');
showFlash();
?>

<?php /* Variables JS injectées depuis PHP pour le panier */ ?>
<script>
const POS_TVA_RATE = <?= (float)$tvaTaux / 100 ?>;
const POS_DEV_SYM  = <?= json_encode($devSym) ?>;
const POS_DEV_POS  = <?= json_encode($devPos) ?>;
</script>

<form method="POST" id="pos-form">
<input type="hidden" name="csrf" value="<?= csrf() ?>">
<input type="hidden" name="cart_data" id="cart-data" value="{}">

<div class="pos-layout">

  <!-- ── Panier ── -->
  <div class="cart-panel">
    <div class="card-header">
      <div class="card-title">Panier</div>
      <button type="button" class="btn btn-ghost btn-xs" onclick="clearCart()">Vider</button>
    </div>
    <?php if ($sessionActive): ?>
    <div style="padding:6px 16px;background:var(--teal-dim);border-bottom:1px solid var(--border2);font-size:12px;display:flex;justify-content:space-between;align-items:center;">
      <span style="display:flex;align-items:center;gap:4px;"><?= icon('money',14) ?> <?= e($sessionActive['caisse_nom']) ?></span>
      <span style="font-weight:600;color:var(--teal2);">Solde : <?= fmtMoney(soldeSessionCaisse($db, (int)$sessionActive['id'])) ?></span>
    </div>
    <?php endif; ?>

    <div class="cart-items" id="cart-items">
      <div class="empty" style="padding:30px 20px;">
        <div style="color:var(--text3);margin-bottom:10px;display:flex;justify-content:center;"><?= icon('cart',40) ?></div>
        <div style="color:var(--text3);font-size:13px;text-align:center;">Panier vide — cliquez sur un produit</div>
      </div>
    </div>

    <div class="cart-footer">
      <div class="total-line">
        <span>Sous-total HT</span>
        <span id="pos-subtotal">0 <?= e($devSym) ?></span>
      </div>
      <div class="total-line">
        <span>TVA (<?= e($tvaTaux) ?>%)</span>
        <span id="pos-tva">0 <?= e($devSym) ?></span>
      </div>
      <div class="divider"></div>
      <div class="total-main">
        <span>Total TTC</span>
        <span id="pos-total" class="c-teal">0 <?= e($devSym) ?></span>
      </div>

      <div style="height:12px;"></div>

      <div class="form-group" style="margin-bottom:8px;">
        <label>Client (optionnel)</label>
        <select name="client_id" id="client-select" style="width:100%;" onchange="document.getElementById('mode-credit').style.display=this.value?'':'none'">
          <option value="">— Sélectionner —</option>
          <?php
          $allClients = $db->query("SELECT id, nom, telephone FROM clients WHERE actif=1 ORDER BY nom")->fetchAll();
          foreach ($allClients as $cl):
          ?>
          <option value="<?= $cl['id'] ?>"><?= e($cl['nom']) . ($cl['telephone'] ? ' — ' . $cl['telephone'] : '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="mode_paiement" value="espèces">
        <span id="mode-credit" style="display:none;">
          <label class="radio-label" style="border-color:var(--blue);">
            <input type="radio" name="mode_paiement" value="crédit" onchange="handleCreditMode(this)">
            <span class="radio-mark" style="background:var(--blue-dim);color:var(--blue);">C</span>
            Crédit
          </label>
        </span>
      <div class="form-group" style="margin-bottom:6px;" id="montant-recu-group">
        <label>Montant reçu (<?= e($devSym) ?>)</label>
        <input type="number" id="montant-recu" name="montant_recu"
               placeholder="0" step="1" min="0" style="font-size:15px;">
      </div>
      <div class="total-line" style="margin-bottom:14px;" id="monnaie-display">
        <span>Monnaie à rendre</span>
        <span id="monnaie" class="fw-mono c-teal">0 <?= e($devSym) ?></span>
      </div>

      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center;padding:13px;font-size:14px;font-weight:600;gap:8px;">
        <?= icon('check',16) ?> Valider la vente
      </button>
    </div>
  </div>

  <!-- ── Catalogue ── -->
  <div class="card" style="overflow:hidden;display:flex;flex-direction:column;margin-bottom:0;">
    <div class="card-header">
      <div class="card-title">Catalogue médicaments</div>
      <div class="flex gap-8" style="flex:1;">
        <div class="search-box" style="flex:1;border-color:var(--teal);box-shadow:0 0 10px var(--teal-glow);" id="barcode-box">
          <span style="color:var(--teal2);display:flex;"><?= icon('search',15) ?></span>
          <input type="text" id="barcode-input" placeholder="Rechercher ou scanner un code-barres..." autofocus style="font-family:'DM Mono',monospace;letter-spacing:.5px;">
        </div>
        <select onchange="filterCat(this.value)" style="padding:6px 12px;font-size:12px;width:auto;">
          <option value="">Toutes catégories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= e($c) ?>"><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- ── Vue liste (Toutes catégories) ── -->
    <div class="products-list-pos" id="products-list">
      <table>
        <thead>
          <tr>
            <th>Réf.</th>
            <th>Médicament</th>
            <th>Catégorie</th>
            <th style="text-align:right;">Prix</th>
            <th style="text-align:center;">Stock</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $catColors = [
            'Antalgiques'                => '#00c9a7',
            'Antibiotiques'              => '#4895ef',
            'Anti-inflammatoires'        => '#f0b429',
            'Antipaludiques'             => '#ef4444',
            'Vitamines & Compléments'    => '#9b59b6',
            'Cardiologie & Hypertension' => '#e74c3c',
            'Diabétologie'              => '#e67e22',
            'Respiratoire'               => '#1abc9c',
            'Gastro-entérologie'        => '#3498db',
            'Allergologie'               => '#e91e63',
            'Dermatologie'               => '#795548',
            'Antiparasitaires'           => '#2ecc71',
        ];
        foreach ($produits as $p):
          $disabled = $p['stock'] == 0 ? 'out' : '';
          $catColor = $catColors[$p['cat'] ?? ''] ?? 'var(--teal)';
        ?>
          <tr class="product-row <?= $disabled ?>"
              data-id="<?= (int)$p['id'] ?>"
              data-cat="<?= e($p['cat'] ?? '') ?>"
              data-search="<?= e(mb_strtolower($p['nom'])) ?>"
              data-ref="<?= e(mb_strtolower($p['reference'] ?? '')) ?>"
              data-pname="<?= e($p['nom']) ?>"
              data-price="<?= (float)$p['prix_vente'] ?>"
              data-stock="<?= (int)$p['stock'] ?>">
            <td class="td-mono"><?= e($p['reference']) ?></td>
            <td class="td-name"><?= e($p['nom']) ?></td>
            <td><span class="p-cat-tag" style="--cat-color:<?= e($catColor) ?>"><?= e($p['cat'] ?? '—') ?></span></td>
            <td style="text-align:right;" class="p-price-cell"><?= fmtMoney((float)$p['prix_vente']) ?></td>
            <td style="text-align:center;">
              <?php if ($p['stock'] == 0): ?>
                <span class="badge badge-red">Rupture</span>
              <?php elseif ($p['stock'] <= $p['seuil_alerte']): ?>
                <span><?= (int)$p['stock'] ?> <span class="badge badge-gold" style="font-size:9px;">Bas</span></span>
              <?php else: ?>
                <?= (int)$p['stock'] ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
          <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--text3);">Aucun médicament disponible</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ── Vue cartes (catégorie filtrée) ── -->
    <div class="products-grid-pos" id="products-grid" style="display:none;">
      <?php foreach ($produits as $p):
        $disabled = $p['stock'] == 0 ? 'out' : '';
        $catColor = $catColors[$p['cat'] ?? ''] ?? 'var(--teal)';
      ?>
      <div class="product-tile <?= $disabled ?>"
           data-id="<?= (int)$p['id'] ?>"
           data-cat="<?= e($p['cat'] ?? '') ?>"
           data-search="<?= e(mb_strtolower($p['nom'])) ?>"
           data-ref="<?= e(mb_strtolower($p['reference'] ?? '')) ?>"
           data-pname="<?= e($p['nom']) ?>"
           data-price="<?= (float)$p['prix_vente'] ?>"
           data-stock="<?= (int)$p['stock'] ?>"
           style="--cat-color:<?= e($catColor) ?>">
        <div class="p-cat"><?= e($p['cat'] ?? '—') ?></div>
        <div class="p-name"><?= e($p['nom']) ?></div>
        <div class="p-bottom">
          <span class="p-price"><?= fmtMoney((float)$p['prix_vente']) ?></span>
          <?php if ($p['stock'] == 0): ?>
            <span class="p-stock"><span class="badge badge-red">Rupture</span></span>
          <?php elseif ($p['stock'] <= $p['seuil_alerte']): ?>
            <span class="p-stock">Stk <strong><?= (int)$p['stock'] ?></strong> <span class="badge badge-gold">Bas</span></span>
          <?php else: ?>
            <span class="p-stock">Stk <strong><?= (int)$p['stock'] ?></strong></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$produits): ?>
      <div style="grid-column:1/-1;padding:40px;text-align:center;color:var(--text3);">
        Aucun médicament disponible
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</form>

<script>
// ── Vue liste / cartes ──
const listView  = document.getElementById('products-list');
const gridView  = document.getElementById('products-grid');

function showView(isList) {
  listView.style.display  = isList ? '' : 'none';
  gridView.style.display = isList ? 'none' : '';
}

// ── Clic produit → ajouter au panier ──
// Cartes (grille)
gridView.addEventListener('click', function(e) {
  const tile = e.target.closest('.product-tile');
  if (!tile || tile.classList.contains('out')) return;
  addToCart(parseInt(tile.dataset.id), tile.dataset.pname, parseFloat(tile.dataset.price), parseInt(tile.dataset.stock));
});
// Lignes (tableau)
listView.addEventListener('click', function(e) {
  const row = e.target.closest('.product-row');
  if (!row || row.classList.contains('out')) return;
  addToCart(parseInt(row.dataset.id), row.dataset.pname, parseFloat(row.dataset.price), parseInt(row.dataset.stock));
  // Flash sur la ligne
  row.style.background = 'var(--teal-dim)';
  setTimeout(function() { row.style.background = ''; }, 350);
});

// ── Recherche + Scan code-barres (champ unique) ──
(function() {
  var input = document.getElementById('barcode-input');
  if (!input) return;

  // Filtrage en temps réel à chaque frappe
  input.addEventListener('input', function() {
    filterProd(this.value);
  });

  // Entrée = scan code-barres (réf exacte → ajout direct au panier)
  input.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var code = this.value.trim().toLowerCase();
    if (!code) return;
    var all = document.querySelectorAll('.product-row, .product-tile');
    var found = null;
    for (var i = 0; i < all.length; i++) {
      if (all[i].dataset.ref === code) { found = all[i]; break; }
    }
    if (found) {
      if (found.classList.contains('out')) {
        showNotif('Produit en rupture de stock : ' + found.dataset.pname, 'error');
      } else {
        addToCart(parseInt(found.dataset.id), found.dataset.pname, parseFloat(found.dataset.price), parseInt(found.dataset.stock));
        found.style.borderColor = 'var(--teal2)';
        found.style.background = 'var(--teal-dim)';
        setTimeout(function() { found.style.borderColor = ''; found.style.background = ''; }, 400);
      }
      this.value = '';
      filterProd('');
    } else {
      showNotif('Aucun produit trouvé pour : ' + this.value.trim(), 'error');
    }
    this.focus();
  });
})();

function filterProd(q) {
  q = (q || '').toLowerCase().trim();
  document.querySelectorAll('.product-row, .product-tile').forEach(el => {
    var matchName = !q || el.dataset.search.includes(q);
    var matchRef  = !q || (el.dataset.ref && el.dataset.ref.includes(q));
    el.style.display = (matchName || matchRef) ? '' : 'none';
  });
}

function filterCat(cat) {
  var isAll = !cat;
  showView(isAll);
  document.querySelectorAll('.product-row, .product-tile').forEach(el => {
    el.style.display = (isAll || el.dataset.cat === cat) ? '' : 'none';
  });
}

// Afficher la vue liste par défaut (toutes catégories)
showView(true);

function handleCreditMode(radio) {
  if (radio.checked) {
    document.getElementById('montant-recu-group').style.display = 'none';
    document.getElementById('monnaie-display').style.display = 'none';
  } else {
    document.getElementById('montant-recu-group').style.display = '';
    document.getElementById('monnaie-display').style.display = '';
  }
}
</script>

<?php if ($receiptData): ?>
<!-- ── Modal Ticket Thermique ── -->
<div class="modal-overlay open" id="modal-receipt">
  <div class="modal" style="width:380px;">
    <div class="modal-header">
      <div class="modal-title">Ticket de caisse</div>
      <button class="modal-close" onclick="closeModal('modal-receipt')">✕</button>
    </div>
    <div class="card-pad" id="receipt-content">
      <div id="thermal-ticket" style="font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;max-width:280px;margin:0 auto;padding:10px 0;">
        <div style="text-align:center;border-bottom:1px dashed var(--border2);padding-bottom:10px;margin-bottom:10px;">
          <div style="font-family:var(--font-title);font-size:16px;font-weight:600;"><?= e($appNom) ?></div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px;"><?= e($ticketSousTitre) ?></div>
          <?php if ($pharmAdresse): ?>
          <div style="font-size:10px;color:var(--text3);margin-top:2px;"><?= e($pharmAdresse) ?></div>
          <?php endif; ?>
          <?php if ($pharmTel): ?>
          <div style="font-size:10px;color:var(--text3);margin-top:1px;"><?= e($pharmTel) ?></div>
          <?php endif; ?>
          <?php if ($pharmNif): ?>
          <div style="font-size:10px;color:var(--text3);margin-top:1px;"><?= e($pharmNif) ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-bottom:8px;">
          <span><?= e($receiptData['reference']) ?></span>
          <span><?= date('d/m/Y H:i', strtotime($receiptData['created_at'])) ?></span>
        </div>
        <?php if ($receiptData['client_nom']): ?>
        <div style="font-size:11px;color:var(--text3);margin-bottom:8px;">Client : <?= e($receiptData['client_nom']) ?></div>
        <?php endif; ?>
        <div style="border-top:1px dashed var(--border2);border-bottom:1px dashed var(--border2);padding:6px 0;margin-bottom:8px;">
          <?php foreach ($receiptData['lignes'] as $l): ?>
          <div style="display:flex;justify-content:space-between;">
            <span><?= e($l['produit_nom']) ?> x<?= (int)$l['quantite'] ?></span>
            <span><?= fmtMoney((float)$l['total_ligne']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);">
          <span>Sous-total HT</span><span><?= fmtMoney((float)$receiptData['sous_total']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);">
          <span>TVA (<?= e($tvaTaux) ?>%)</span><span><?= fmtMoney((float)$receiptData['tva_total']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:6px;padding-top:6px;border-top:1px dashed var(--border2);">
          <span>TOTAL TTC</span><span class="c-teal"><?= fmtMoney((float)$receiptData['total']) ?></span>
        </div>
        <?php if ($receiptData['mode_paiement'] === 'espèces' && $receiptData['montant_recu'] > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);margin-top:4px;">
          <span>Reçu</span><span><?= fmtMoney((float)$receiptData['montant_recu']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);">
          <span>Monnaie</span><span><?= fmtMoney((float)$receiptData['monnaie']) ?></span>
        </div>
        <?php endif; ?>
        <div style="text-align:center;margin-top:12px;padding-top:8px;border-top:1px dashed var(--border2);font-size:10px;color:var(--text3);">
          <?= e($modeLabels[$receiptData['mode_paiement']] ?? $receiptData['mode_paiement']) ?><br>
          Caissier : <?= e(trim($receiptData['prenom'] . ' ' . $receiptData['u_nom'])) ?><br>
          <?= e($ticketPied) ?>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="gap:10px;">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-receipt')">
        Fermer
      </button>
      <a href="<?= APP_URL ?>/modules/ventes_hist.php" class="btn btn-ghost btn-sm">
        <?= icon('history',13) ?> Historique
      </a>
      <button class="btn btn-primary btn-sm" onclick="printReceipt()">
        <?= icon('receipt',13) ?> Imprimer
      </button>
    </div>
  </div>
</div>
<script>
const modeLabels = <?= json_encode([
    'espèces' => 'Espèces', 'carte' => 'Carte bancaire',
    'chèque' => 'Chèque', 'assurance' => 'Assurance'
], JSON_UNESCAPED_UNICODE) ?>;

function printReceipt() {
  const content = document.getElementById('receipt-content').innerHTML;
  const win = window.open('', '_blank', 'width=320,height=600');
  win.document.write(`<!DOCTYPE html><html><head><title>Ticket</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;padding:8px;max-width:280px;margin:0 auto;}
      @media print{body{margin:0;}@page{margin:0;size:80mm auto;}}
    </style>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  </head><body>${content}<script>window.onload=function(){window.print();}<\/script></body></html>`);
  win.document.close();
}
</script>
<?php endif; ?>

<?php layout_foot(); ?>
