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

    // ── Rate limit POS : max 20 ventes / 60s par utilisateur ──
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $rlKey = 'pos.vente:' . $uid;
    $rl = rateLimitConsume($rlKey, 20, 60);
    if (!$rl['allowed']) {
        $retry = max(1, (int)$rl['retry']);
        flash('Trop de ventes enregistrées (limite anti-abus). Réessayez dans '
            . $retry . ' s.', 'error');
        auditLog('vente.ratelimit', sprintf('Vente bloquée (rate limit) user #%d, retry %ds', $uid, $retry));
        header('Location: ' . APP_URL . '/modules/vente.php'); exit;
    }

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

    // ── Client : saisie libre ou client existant ──
    $clientId     = null;
    $clientNom    = '';
    $clientTel    = '';
    $clientMode   = $_POST['client_mode'] ?? 'simple';  // 'simple' | 'existant'

    if ($clientMode === 'existant' && !empty($_POST['client_id'])) {
        $clientId = (int)$_POST['client_id'];
        $stmtC = $db->prepare("SELECT nom, telephone FROM clients WHERE id = ?");
        $stmtC->execute([$clientId]);
        $cData = $stmtC->fetch();
        if ($cData) {
            $clientNom = $cData['nom'];
            $clientTel = $cData['telephone'];
        } else {
            $clientId = null;  // client supprimé entre-temps
        }
    } elseif ($clientMode === 'simple') {
        $clientNom = trim($_POST['client_nom_saisie'] ?? '');
        if ($clientNom === '') {
            flash('Le nom du client est obligatoire en vente libre.', 'error');
            header('Location: ' . APP_URL . '/modules/vente.php'); exit;
        }
    }

    // Si crédit sans client enregistré → repasse en espèces
    if ($modePaiement === 'crédit' && !$clientId) {
        flash('Un client enregistré est requis pour une vente à crédit.', 'error');
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
            trim($clientNom),
            trim($clientTel),
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
        auditLog('vente.create', sprintf('Vente %s : %d articles, %s %s (%s)', $ref, count($cartRaw), fmtMoney($total), $modePaiement, $clientNom ?: '—'), (int)$vid, $ref);
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

<style>
  .cart-panel {
    background: #0f172a !important;
    border-right: 1px solid #1e293b !important;
    display: flex;
    flex-direction: column;
    color: #f8fafc;
  }
  .cart-items {
    flex: 1;
    overflow-y: auto;
    background: #0f172a;
  }
  .cart-item-pro {
    display: grid;
    grid-template-columns: 2fr 1fr 100px 1fr 30px;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid #1e293b;
    background: #0f172a;
    font-size: 13px;
    transition: background 0.15s;
  }
  .cart-item-pro:nth-child(even) {
    background: #1e293b;
  }
  .cart-item-pro:hover {
    background: #334155;
  }
  .cip-name {
    font-weight: 500;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .cip-price, .cip-subtotal {
    font-family: 'DM Mono', monospace;
    color: #94a3b8;
    text-align: right;
  }
  .cip-subtotal {
    font-weight: 600;
    color: #10b981;
  }
  .cip-qty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
  }
  .qty-btn {
    width: 24px;
    height: 24px;
    border: 1px solid #334155;
    background: #1e293b;
    color: #f8fafc;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transition: all 0.1s;
  }
  .qty-btn:hover {
    background: #475569;
    border-color: #64748b;
  }
  .qty-val {
    font-family: 'DM Mono', monospace;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
    color: #f8fafc;
  }
  .btn-remove-pro {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 18px;
    transition: color 0.15s;
  }
  .btn-remove-pro:hover {
    color: #ef4444;
  }
  .cart-footer {
    background: #1e293b;
    padding: 20px;
    border-top: 2px solid #334155;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.3);
    color: #f8fafc;
  }
  .totals-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
  }
  .total-box {
    padding: 8px;
    border-radius: 6px;
    background: #0f172a;
    border: 1px solid #334155;
  }
  .total-box label {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .total-box span {
    font-family: 'DM Mono', monospace;
    font-size: 14px;
    font-weight: 600;
    color: #f8fafc;
  }
  .total-main-pro {
    grid-column: span 2;
    background: #064e3b;
    border-color: #10b981;
    padding: 12px;
    text-align: right;
  }
  .total-main-pro label {
    color: #a7f3d0;
  }
  .total-main-pro span {
    font-size: 20px;
    color: #34d399;
  }
  .btn-validate-pro {
    background: #059669 !important;
    color: white !important;
    border: none !important;
    padding: 16px !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    cursor: pointer;
    transition: transform 0.1s, background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
  }
  .btn-validate-pro:hover {
    background: #047857 !important;
    transform: translateY(-1px);
  }
  .btn-validate-pro:active {
    transform: translateY(0);
  }
</style>


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

  <!-- ── Panier Pro ── -->
  <div class="cart-panel">
    <div class="card-header">
      <div class="card-title">Nouvelle vente</div>
      <span id="cart-item-count" style="font-size:12px;color:#94a3b8;">0 article</span>
    </div>
    <?php if ($sessionActive): ?>
    <div style="padding:6px 16px;background:var(--teal-dim);border-bottom:1px solid var(--border2);font-size:12px;display:flex;justify-content:space-between;align-items:center;">
      <span style="display:flex;align-items:center;gap:4px;"><?= icon('money',14) ?> <?= e($sessionActive['caisse_nom']) ?></span>
      <span style="font-weight:600;color:var(--teal2);">Solde : <?= fmtMoney(soldeSessionCaisse($db, (int)$sessionActive['id'])) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Panier (vente libre par défaut à l'ouverture) ── -->

    <div id="cart-body" style="display:flex;flex:1;flex-direction:column;">
      <div class="cart-items" id="cart-items">
        <!-- Rendu par JS -->
      </div>

    <div class="cart-footer">
      <div class="totals-grid">
        <div class="total-box">
          <label>Sous-total HT</label>
          <span id="pos-subtotal">0 <?= e($devSym) ?></span>
        </div>
        <div class="total-box">
          <label>TVA (<?= e($tvaTaux) ?>%)</label>
          <span id="pos-tva">0 <?= e($devSym) ?></span>
        </div>
        <div class="total-box total-main-pro">
          <label>Total TTC</label>
          <span id="pos-total" class="c-teal">0 <?= e($devSym) ?></span>
        </div>
      </div>

      <div style="height:12px;"></div>

      <!-- ── Mode client : saisie libre ou existant ── -->
      <div class="form-group" style="margin-bottom:6px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          <label style="margin:0;">Client</label>
          <div style="display:flex;gap:0;border-radius:6px;overflow:hidden;border:1px solid #334155;">
            <button type="button" id="client-mode-simple" class="client-mode-toggle active"
                    onclick="setClientMode('simple')"
                    style="padding:4px 12px;font-size:11px;font-weight:600;border:none;cursor:pointer;
                           background:#334155;color:#f8fafc;transition:all 0.15s;">
              Libre
            </button>
            <button type="button" id="client-mode-existant" class="client-mode-toggle"
                    onclick="setClientMode('existant')"
                    style="padding:4px 12px;font-size:11px;font-weight:600;border:none;cursor:pointer;
                           background:transparent;color:#94a3b8;transition:all 0.15s;">
              Fidèle
            </button>
          </div>
        </div>
        <input type="hidden" name="client_mode" id="client-mode-hdn" value="simple">
        <!-- Saisie libre -->
        <input type="text" name="client_nom_saisie" id="client-nom-saisie" required
               placeholder="Nom du client *"
               style="width:100%;font-family:'DM Mono',monospace;">
        <!-- Client existant -->
        <select name="client_id" id="client-select" style="width:100%;display:none;"
                onchange="onClientSelectChange()">
          <option value="">— Sélectionner —</option>
          <?php
          $allClients = $db->query("SELECT id, nom, telephone FROM clients WHERE actif=1 ORDER BY nom")->fetchAll();
          foreach ($allClients as $cl):
          ?>
          <option value="<?= $cl['id'] ?>"><?= e($cl['nom']) . ($cl['telephone'] ? ' — ' . $cl['telephone'] : '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="mode_paiement" id="mode-paiement" value="espèces">
      <div id="mode-credit" style="display:none;margin-bottom:15px; background:rgba(255,255,255,0.05); padding:10px; border-radius:6px; border:1px solid #334155;">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:#f8fafc;font-size:14px;font-weight:500;user-select:none;">
          <input type="checkbox" id="credit-checkbox" onchange="toggleCreditMode(this)"
                 style="width:18px;height:18px;cursor:pointer;accent-color:var(--teal2);">
          Vendre à crédit
        </label>
      </div>
      <div class="form-group" style="margin-bottom:6px;" id="montant-recu-group">
        <label>Montant reçu (<?= e($devSym) ?>)</label>
        <input type="number" id="montant-recu" name="montant_recu"
               placeholder="0" step="1" min="0" style="font-size:15px;">
      </div>
      <div class="total-line" style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;" id="monnaie-display">
        <span style="font-size:13px; color:var(--text2);">Monnaie à rendre</span>
        <span id="monnaie" class="fw-mono c-teal" style="font-size:16px; font-weight:700;">0 <?= e($devSym) ?></span>
      </div>

      <button type="submit" class="btn-validate-pro" id="btn-validate" disabled style="opacity:0.5;cursor:not-allowed;">
        <?= icon('check',18) ?> Valider la vente
      </button>
    </div>
    </div><!-- /#cart-body -->
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
              data-stock="<?= (int)$p['stock'] ?>"
              data-stock-orig="<?= (int)$p['stock'] ?>"
              data-seuil="<?= (int)$p['seuil_alerte'] ?>">
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
           data-stock-orig="<?= (int)$p['stock'] ?>"
           data-seuil="<?= (int)$p['seuil_alerte'] ?>"
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
  addToCart(parseInt(tile.dataset.id), tile.dataset.pname, parseFloat(tile.dataset.price), parseInt(tile.dataset.stockOrig));
});
// Lignes (tableau)
listView.addEventListener('click', function(e) {
  const row = e.target.closest('.product-row');
  if (!row || row.classList.contains('out')) return;
  addToCart(parseInt(row.dataset.id), row.dataset.pname, parseFloat(row.dataset.price), parseInt(row.dataset.stockOrig));
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
        addToCart(parseInt(found.dataset.id), found.dataset.pname, parseFloat(found.dataset.price), parseInt(found.dataset.stockOrig));
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

var CLIENT_MODE = 'simple'; // Vente libre par défaut à l'ouverture du POS

function chooseClientMode(mode) {
  CLIENT_MODE = mode;
  var screen  = document.getElementById('client-choice-screen'); // peut être absent (écran retiré)
  var cartBody = document.getElementById('cart-body');
  var header  = document.querySelector('.cart-panel .card-header .card-title');
  var count   = document.getElementById('cart-item-count');

  // Cacher l'écran de choix (s'il existe encore), afficher le panier
  if (screen) screen.style.display = 'none';
  if (cartBody) cartBody.style.display = 'flex';
  if (count) count.style.display = '';
  if (header) header.textContent = 'Panier';

  // Ajouter bouton "vider" dans le header
  var headerDiv = document.querySelector('.cart-panel .card-header');
  if (!document.getElementById('btn-clear-cart')) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'btn-clear-cart';
    btn.className = 'btn btn-ghost btn-xs';
    btn.textContent = 'Vider';
    btn.onclick = clearCart;
    headerDiv.appendChild(btn);
    
    // Bouton pour changer de mode
    var btnMode = document.createElement('button');
    btnMode.type = 'button';
    btnMode.id = 'btn-switch-mode';
    btnMode.className = 'btn btn-ghost btn-xs';
    btnMode.textContent = mode === 'simple' ? '→ Fidèle' : '→ Libre';
    btnMode.onclick = function(){ switchClientMode(); };
    btnMode.style.marginRight = '4px';
    headerDiv.appendChild(btnMode);
  }

  // Appliquer le mode
  setClientMode(mode);

  // Bloquer l'ajout au panier tant que mode non choisi
  window._cartReady = true;

  // Mettre le focus sur le champ de recherche
  setTimeout(function(){
    var barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) barcodeInput.focus();
  }, 100);
}

function switchClientMode() {
  var newMode = (CLIENT_MODE === 'simple') ? 'existant' : 'simple';
  CLIENT_MODE = newMode;
  setClientMode(newMode);
  var btnSwitch = document.getElementById('btn-switch-mode');
  if (btnSwitch) btnSwitch.textContent = newMode === 'simple' ? '→ Fidèle' : '→ Libre';
}

// Bloquer addToCart tant que le mode n'est pas choisi
var _originalAddToCart = null;
var _guardTimer = null;
function _guardAddToCart() {
  if (typeof window.addToCart === 'function' && !_originalAddToCart) {
    _originalAddToCart = window.addToCart;
    window.addToCart = function(id, name, price, stock) {
      if (!CLIENT_MODE) {
        showNotif('Choisissez d\'abord le type de vente : Libre ou Fidèle', 'error');
        return;
      }
      _originalAddToCart(id, name, price, stock);
    };
    return;
  }
  // Réessayer jusqu'à ce que addToCart soit défini
  if (!_guardTimer) _guardTimer = setInterval(function() {
    if (typeof window.addToCart === 'function') {
      clearInterval(_guardTimer);
      _guardAddToCart();
    }
  }, 100);
}
_guardAddToCart();

function setClientMode(mode) {
  const btnSimple    = document.getElementById('client-mode-simple');
  const btnExistant  = document.getElementById('client-mode-existant');
  const inputSimple  = document.getElementById('client-nom-saisie');
  const selectExist  = document.getElementById('client-select');
  const creditBlock  = document.getElementById('mode-credit');
  const creditCb     = document.getElementById('credit-checkbox');
  const hdnMode      = document.getElementById('client-mode-hdn');

  if (mode === 'simple') {
    btnSimple.style.background = '#334155'; btnSimple.style.color = '#f8fafc';
    btnExistant.style.background = 'transparent'; btnExistant.style.color = '#94a3b8';
    inputSimple.style.display = ''; selectExist.style.display = 'none';
    hdnMode.value = 'simple';
    selectExist.value = '';
    // Cacher le bloc crédit en mode libre
    if (creditBlock) creditBlock.style.display = 'none';
    if (creditCb) { creditCb.checked = false; toggleCreditMode(creditCb); }
  } else {
    btnExistant.style.background = '#334155'; btnExistant.style.color = '#f8fafc';
    btnSimple.style.background = 'transparent'; btnSimple.style.color = '#94a3b8';
    selectExist.style.display = ''; inputSimple.style.display = 'none';
    hdnMode.value = 'existant';
    inputSimple.value = '';
  }
}

function onClientSelectChange() {
  const selectExist  = document.getElementById('client-select');
  const creditBlock  = document.getElementById('mode-credit');
  const creditCb     = document.getElementById('credit-checkbox');
  if (selectExist.value) {
    if (creditBlock) creditBlock.style.display = '';
  } else {
    if (creditBlock) creditBlock.style.display = 'none';
    if (creditCb) { creditCb.checked = false; toggleCreditMode(creditCb); }
  }
}

function toggleCreditMode(checkbox) {
  const paymentModeInput = document.getElementById('mode-paiement');
  const recuGroup = document.getElementById('montant-recu-group');
  const monnaieDisplay = document.getElementById('monnaie-display');

  if (checkbox.checked) {
    paymentModeInput.value = 'crédit';
    recuGroup.style.display = 'none';
    monnaieDisplay.style.display = 'none';
  } else {
    paymentModeInput.value = 'espèces';
    recuGroup.style.display = '';
    monnaieDisplay.style.display = '';
  }
}
</script>

<?php if ($receiptData): ?>
<!-- ── Modal Ticket Thermique ── -->
<div class="modal-overlay open" id="modal-receipt">
  <div class="modal" style="width:440px;">
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
          <div style="display:flex;justify-content:space-between;margin-bottom:1px;">
            <span><?= e($l['produit_nom']) ?></span>
            <span><?= fmtMoney((float)$l['total_ligne']) ?></span>
          </div>
          <div style="font-size:10px;color:#64748b;margin-bottom:3px;">
            <?= fmtMoney((float)$l['prix_unitaire']) ?> &times; <?= (int)$l['quantite'] ?>
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
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-receipt')">
        Fermer
      </button>
      <a href="<?= APP_URL ?>/modules/ventes_hist.php" class="btn btn-ghost btn-sm">
        <?= icon('history',13) ?> Historique
      </a>
      <button class="btn btn-outline-teal btn-sm" onclick="printReceipt80()">
        🧾 Ticket
      </button>
      <button class="btn btn-primary btn-sm" onclick="printReceiptA4()">
        📄 Facture A4
      </button>
    </div>
  </div>
</div>

<!-- ── Données ticket cachées pour A4 ── -->
<script id="receipt-a4-data" type="application/json"><?= json_encode($receiptData, JSON_UNESCAPED_UNICODE) ?></script>

<script>
const modeLabels = <?= json_encode([
    'espèces' => 'Espèces', 'carte' => 'Carte bancaire',
    'chèque' => 'Chèque', 'assurance' => 'Assurance'
], JSON_UNESCAPED_UNICODE) ?>;
const TVA_RATE = <?= (float)$tvaTaux ?>;
const DEV_SYM  = <?= json_encode($devSym) ?>;
const PHARM_NAME = <?= json_encode($appNom) ?>;
const PHARM_ADDR = <?= json_encode($pharmAdresse ?? '') ?>;
const PHARM_TEL  = <?= json_encode($pharmTel ?? '') ?>;
const PHARM_NIF  = <?= json_encode($pharmNif ?? '') ?>;
const TICKET_TITLE = <?= json_encode($ticketSousTitre) ?>;
const TICKET_FOOT  = <?= json_encode($ticketPied) ?>;

function printReceipt80() {
  if (!rateLimitClick('print.ticket80', 15, 60000)) { rateLimitWarn('print.ticket80', 15, 60000); return; }
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

function printReceiptA4() {
  if (!rateLimitClick('print.ticketA4', 15, 60000)) { rateLimitWarn('print.ticketA4', 15, 60000); return; }
  const d = JSON.parse(document.getElementById('receipt-a4-data').textContent);
  if (!d) return;
  const fmt = (n) => Number(n).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2});
  const modePmt = modeLabels[d.mode_paiement] || d.mode_paiement;
  
  let rows = '';
  d.lignes.forEach(function(l){
    rows += `
      <tr>
        <td style="text-align:left;padding:8px 10px;">${escHtml(l.produit_nom)}</td>
        <td style="text-align:right;padding:8px 10px;font-family:'DM Mono',monospace;">${fmt(l.prix_unitaire)}</td>
        <td style="text-align:center;padding:8px 10px;">${l.quantite}</td>
        <td style="text-align:right;padding:8px 10px;font-family:'DM Mono',monospace;font-weight:600;">${fmt(l.total_ligne)}</td>
      </tr>`;
  });

  const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture ${escHtml(d.reference)}</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{font-family:'Segoe UI',system-ui,sans-serif;font-size:14px;color:#1e293b;padding:40px;max-width:210mm;margin:0 auto;-webkit-print-color-adjust:exact;}
      @media print{body{padding:15mm;}}
      .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;padding-bottom:20px;border-bottom:2px solid #0f172a;}
      .header h1{font-size:26px;font-weight:700;color:#0f172a;margin:0 0 4px;}
      .header .sub{font-size:12px;color:#64748b;}
      .header .infos{text-align:right;font-size:12px;color:#475569;line-height:1.7;}
      .meta{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:25px;font-size:13px;}
      .meta-box{padding:14px 18px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;}
      .meta-box label{font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;display:block;margin-bottom:4px;}
      .meta-box strong{font-size:15px;color:#0f172a;}
      table{width:100%;border-collapse:collapse;margin-bottom:25px;}
      thead th{background:#0f172a;color:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:1px;padding:10px;}
      thead th:first-child{border-radius:6px 0 0 0;}
      thead th:last-child{border-radius:0 6px 0 0;}
      tbody td{border-bottom:1px solid #e2e8f0;}
      tbody tr:nth-child(even) td{background:#f8fafc;}
      tbody tr:last-child td{border-bottom:2px solid #0f172a;}
      .totals{text-align:right;font-size:14px;line-height:2.4;}
      .totals div{display:flex;justify-content:flex-end;gap:80px;}
      .totals .grand{font-size:20px;font-weight:700;color:#059669;padding-top:6px;margin-top:6px;border-top:2px solid #0f172a;}
      .footer{text-align:center;margin-top:30px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;line-height:1.8;}
      @media print{@page{size:A4;margin:10mm;}}
    </style>
  </head><body>
    <div class="header">
      <div>
        <h1>${escHtml(PHARM_NAME)}</h1>
        <div class="sub">${escHtml(TICKET_TITLE)}</div>
      </div>
      <div class="infos">
        ${PHARM_ADDR ? escHtml(PHARM_ADDR)+'<br>' : ''}
        ${PHARM_TEL  ? escHtml(PHARM_TEL)+'<br>'  : ''}
        ${PHARM_NIF  ? escHtml(PHARM_NIF) : ''}
      </div>
    </div>
    <div class="meta">
      <div class="meta-box">
        <label>Facture</label>
        <strong>${escHtml(d.reference)}</strong>
      </div>
      <div class="meta-box">
        <label>Date</label>
        <strong>${d.created_at ? new Date(d.created_at.replace(' ','T')).toLocaleString('fr-FR') : ''}</strong>
      </div>
      ${d.client_nom ? '<div class="meta-box"><label>Client</label><strong>'+escHtml(d.client_nom)+'</strong></div>' : ''}
      <div class="meta-box">
        <label>Caissier</label>
        <strong>${escHtml((d.prenom||'')+' '+(d.u_nom||''))}</strong>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th style="text-align:left;width:40%;">Produit</th>
          <th style="text-align:right;width:20%;">Prix unitaire</th>
          <th style="text-align:center;width:10%;">Qté</th>
          <th style="text-align:right;width:30%;">Total</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
    <div class="totals">
      <div><span>Sous-total HT</span><span style="font-family:'DM Mono',monospace;">${fmt(d.sous_total)} ${DEV_SYM}</span></div>
      <div><span>TVA (${TVA_RATE}%)</span><span style="font-family:'DM Mono',monospace;">${fmt(d.tva_total)} ${DEV_SYM}</span></div>
      <div class="grand"><span>TOTAL TTC</span><span>${fmt(d.total)} ${DEV_SYM}</span></div>
      ${d.mode_paiement==='espèces' && d.montant_recu>0 ? `
        <div><span>Reçu</span><span style="font-family:'DM Mono',monospace;">${fmt(d.montant_recu)} ${DEV_SYM}</span></div>
        <div><span>Monnaie</span><span style="font-family:'DM Mono',monospace;">${fmt(d.monnaie)} ${DEV_SYM}</span></div>
      ` : ''}
    </div>
    <div class="footer">
      Mode : ${escHtml(modePmt)}<br>
      ${escHtml(TICKET_FOOT)}
    </div>
    <script>window.onload=function(){window.print();}<\/script>
  </body></html>`;

  const win = window.open('', '_blank', 'width=900,height=700');
  win.document.write(html);
  win.document.close();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Vente libre prioritaire : démarre directement en mode Libre ──
// La caisse s'ouvre prête à encaisser un client de passage, sans écran de
// choix. Le bouton « → Fidèle » reste dispo pour basculer vers un client
// enregistré en cours de vente si besoin.
(function autoStartLibre(){
  // configure le panier (boutons Vider / → Fidèle) + verrouille le mode Libre
  if (typeof chooseClientMode === 'function') {
    chooseClientMode('simple');
  }
  // focus sur la recherche/code-barres pour enchaîner la saisie
  setTimeout(function(){
    var bc = document.getElementById('barcode-input');
    if (bc) bc.focus();
  }, 120);
})();
</script>
</script>
<?php endif; ?>

<?php layout_foot(); ?>
