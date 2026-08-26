<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
require_once __DIR__ . '/../config/licence.php';
requirePermission('vente.creer');
$db = getDB();

// Module « client fidèle » désactivé partout pour l'instant (paramètre fidelite_active).
$fideliteActive = fideliteActive();
// Vente à crédit / dettes suspendues pour l'instant (paramètre credit_active).
$creditActive   = creditActive();

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
        header('Location: ' . url('caisse', ['action' => 'ouvrir'])); exit;
    }
}

// ── Pharmacie de la session ouverte (stock à débiter) ─────────
// Fallback sur la pharmacie principale (1) si la session n'en précise pas.
$pharmacieId = (int)($sessionActive['pharmacie_id'] ?? 0);
if ($pharmacieId <= 0) $pharmacieId = 1;
// Nom de la pharmacie (affiché sur le ticket de caisse)
$pharmacieNom = $db->query("SELECT nom FROM pharmacies WHERE id=" . (int)$pharmacieId)->fetchColumn() ?: '';

// ── AJAX : vérifier un code de remise (retourne le taux lié) ──
// Le caissier saisit un code → on renvoie {ok, pct, auteur_id} ou {ok:false, error}
if (isset($_GET['ajax_remise']) && $_GET['ajax_remise'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $code   = trim(strtoupper($_GET['code'] ?? ''));
    $auteur = (int)($_GET['auteur'] ?? 0);
    if ($code === '') { echo json_encode(['ok' => false, 'error' => 'Code vide']); exit; }
    $st = $db->prepare("SELECT c.id, c.remise_pct, c.created_by, c.used, c.expires_at,
                               u.prenom, u.nom AS u_nom
                        FROM codes_remise c
                        JOIN utilisateurs u ON u.id = c.created_by
                        WHERE c.code = ? LIMIT 1");
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['ok' => false, 'error' => 'Code introuvable']); exit; }
    if ((int)$row['used'] === 1) { echo json_encode(['ok' => false, 'error' => 'Code déjà utilisé']); exit; }
    if (strtotime($row['expires_at']) <= time()) { echo json_encode(['ok' => false, 'error' => 'Code expiré']); exit; }
    echo json_encode([
        'ok'        => true,
        'pct'       => (float)$row['remise_pct'],
        'auteur_id' => (int)$row['created_by'],
        'auteur'    => trim($row['prenom'] . ' ' . $row['u_nom']),
    ]);
    exit;
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
        header('Location: ' . url('vente')); exit;
    }

    // ── Licence : bloquer si le plafond de lignes de vente est atteint ──
    $licCap = licence_current()['cap'];
    $licUsage = licence_usage();
    if ($licUsage >= $licCap) {
        flash('Limite de lignes de vente atteinte (' . $licUsage . '/' . $licCap
            . '). Contactez votre fournisseur pour étendre la licence.', 'error');
        auditLog('vente.licence_cap', sprintf('Vente bloquée : %d/%d vente_lignes', $licUsage, $licCap));
        header('Location: ' . url('vente')); exit;
    }

    $cartRaw = json_decode($_POST['cart_data'], true);

    if (!$cartRaw || count($cartRaw) === 0) {
        flash('Le panier est vide.', 'error');
        header('Location: ' . url('vente')); exit;
    }

    // Vérifier que le mode de paiement est valide
    $modePaiement = $_POST['mode_paiement'] ?? 'espèces';
    if (!isset($modeLabels[$modePaiement])) {
        $modePaiement = 'espèces';
    }

    // Vente à crédit désactivée (dettes suspendues) : on rejette toute vente
    // crédit soumise, même si le client est enregistré. La vérification se
    // fait ici, serveur, pour fermer la porte quelle que soit l'UI.
    if ($modePaiement === 'crédit' && !creditActive()) {
        flash('La vente à crédit est actuellement désactivée.', 'error');
        header('Location: ' . url('vente')); exit;
    }

    // ── Client : saisie libre ou client existant ──
    $clientId     = null;
    $clientNom    = '';
    $clientTel    = '';
    $clientMode   = $_POST['client_mode'] ?? 'simple';  // 'simple' | 'existant'
    // Mode « client fidèle » désactivé : force toujours la vente libre.
    if (!$fideliteActive) $clientMode = 'simple';

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
            header('Location: ' . url('vente')); exit;
        }
    }

    // Une vente à crédit exige un client enregistré (sinon impossible de constater
    // la créance en comptabilité : on bloquerait en caisse ce qui n'est pas encaissé).
    if ($modePaiement === 'crédit' && !$clientId) {
        flash('Un client enregistré est requis pour une vente à crédit.', 'error');
        header('Location: ' . url('vente')); exit;
    }

    $db->beginTransaction();
    try {
        $tvaRate   = (float)getParam('tva', '19.25') / 100;
        $tvaPct    = (float)getParam('tva', '19.25');
        $subtotal  = 0;
        $tva_total = 0;
        $coutAchat = 0;  // coût d'achat des marchandises vendues (pour sortie de stock)

        // Vérifier les prix côté serveur (sécurité anti-manipulation)
        $pids = array_values(array_map(fn($i) => (int)$i['id'], $cartRaw));
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        $stmtP = $db->prepare("SELECT id, prix_vente, prix_achat, nom, stock FROM produits WHERE id IN ($ph) AND actif = 1");
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
            $coutAchat += (float)$realPrices[$pid]['prix_achat'] * $qty;  // COGS au coût d'achat
        }

        $total   = $subtotal + $tva_total;
        $ref     = genRef(getParam('prefix_vente', 'VNT'));

        // ── Remise % (méthode brute : remise rendue en espèces) ──
        $remisePct    = 0;
        $remiseMontant = 0;
        $autorisePar  = null;
        $code         = '';  // initialisé pour éviter un undefined hors du bloc if
        if (isset($_POST['remise_pct']) && (float)$_POST['remise_pct'] > 0) {
            $remisePct = (float)$_POST['remise_pct'];
            $maxPct    = (float)getParam('remise_max_pct', '100');
            if ($remisePct < 0)      $remisePct = 0;
            if ($remisePct > $maxPct) $remisePct = $maxPct;

            // Vérifier l'autorisation : code + auteur
            $code    = trim($_POST['code_remise'] ?? '');
            $auteur  = (int)($_POST['autorise_par'] ?? 0);
            if ($remisePct > 0 && ($code === '' || $auteur <= 0)) {
                throw new Exception('Remise : code d\'autorisation et auteur requis.');
            }
            if ($remisePct > 0) {
                // Valider le code : non utilisé, non expiré, émis par l'auteur indiqué
                $stCode = $db->prepare("SELECT id, remise_pct, expires_at, used FROM codes_remise
                                        WHERE code = ? AND created_by = ?
                                        AND used = 0 AND expires_at > NOW() LIMIT 1");
                $stCode->execute([$code, $auteur]);
                $codeRow = $stCode->fetch();
                if (!$codeRow) {
                    throw new Exception('Code de remise invalide, expiré ou déjà utilisé.');
                }
                // Le taux fait foi : c'est celui choisi par l'approbateur à la génération.
                // On ignore la valeur saisie par le caissier et on prend celle du code.
                $remisePct     = (float)$codeRow['remise_pct'];
                $remiseMontant = round($subtotal * $remisePct / 100, 2);  // HT
                $autorisePar   = $auteur;
            }
        }

        // Recalcul avec remise (net HT → TVA → net TTC)
        $netHt     = $subtotal - $remiseMontant;
        $tva_total = round($netHt * $tvaRate, 2);
        $total     = $netHt + $tva_total;        // net encaissé
        $recuRaw   = (float)($_POST['montant_recu'] ?? 0);
        $monnaie   = max(0, $recuRaw - $total);

        $stmt = $db->prepare("
            INSERT INTO ventes
                (reference, client_nom, client_telephone, client_id, caissier_id,
                 sous_total, tva_total, total, mode_paiement, statut_paiement,
                 montant_recu, monnaie, note, remise_pct, remise_montant, autorise_par, pharmacie_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
            trim($_POST['note'] ?? ''),
            $remisePct,
            $remiseMontant,
            $autorisePar,
            $pharmacieId,
        ]);
        $vid = $db->lastInsertId();

        $stmtL = $db->prepare("
            INSERT INTO vente_lignes
                (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne)
            VALUES (?,?,?,?,?,?,?)
        ");
        // Débit du stock de la pharmacie de la session (méthode brute : 1 ligne par produit)
        $stmtS = $db->prepare("UPDATE produit_pharmacie
            SET stock = stock - ? WHERE produit_id = ? AND pharmacie_id = ? AND stock >= ?");
        // Sync produits.stock pour la pharmacie principale (modules existants lisent produits.stock)
        $stmtSMain = ($pharmacieId === 1)
            ? $db->prepare("UPDATE produits p
                            JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                            SET p.stock = pp.stock WHERE p.id = ?")
            : null;

        foreach ($cartRaw as $item) {
            $pid   = (int)$item['id'];
            $qty   = max(1, (int)$item['qty']);
            $price = (float)$realPrices[$pid]['prix_vente'];
            $nom   = $realPrices[$pid]['nom'];
            $stmtL->execute([$vid, $pid, $nom, $qty, $price, $tvaPct, round($price * $qty, 2)]);
            $stmtS->execute([$qty, $pid, $pharmacieId, $qty]);
            if ($stmtS->rowCount() === 0) {
                throw new Exception('Stock insuffisant pour le produit : ' . $nom);
            }
            if ($stmtSMain) $stmtSMain->execute([$pid]);  // resync produits.stock = pp.stock
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
            $lignesCredit = [
                [$compteClient, round($total, 2), 0, 'Crédit client ' . $clientNom . ' - ' . $ref],
                [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
            ];
            // Méthode brute : 7011 au brut, 7119 débité du montant HT de la remise
            // pour équilibrer (débit client = net TTC, crédit 7011 = brut HT).
            if ($remiseMontant > 0) {
                $compteRemise = compteFindOrCreate($db, '7119', 'Rabais, remises et ristournes accordés', 7, 'debit');
                $lignesCredit[] = [$compteRemise, round($remiseMontant, 2), 0, 'Remise ' . $remisePct . '% ' . $ref];
            }
            ecritureCreate($db,
                'Vente ' . $ref . ' - Crédit ' . $clientNom,
                date('Y-m-d'),
                $lignesCredit,
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
            $lignes = [
                [$caisseCompte, round($total, 2), 0, 'Vente ' . $ref],
                [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
            ];
            // Méthode brute : 7011 est crédité du HT brut, on débite 7119 du
            // montant HT de la remise pour équilibrer (débit caisse = net TTC).
            if ($remiseMontant > 0) {
                $compteRemise = compteFindOrCreate($db, '7119', 'Rabais, remises et ristournes accordés', 7, 'debit');
                $lignes[] = [$compteRemise, round($remiseMontant, 2), 0, 'Remise ' . $remisePct . '% ' . $ref];
            }
            ecritureCreate($db,
                'Vente ' . $ref . ' - ' . $modeLabels[$modePaiement],
                date('Y-m-d'),
                $lignes,
                'vente', $ref, currentUser()['id']
            );
        }

        // ── Marquer le code de remise comme utilisé (si remise appliquée) ──
        if ($remisePct > 0 && $autorisePar && !empty($code)) {
            $updCode = $db->prepare("UPDATE codes_remise
                          SET used = 1, used_at = NOW(), used_vente_id = ?, used_remise_pct = ?
                          WHERE code = ? AND used = 0");
            $updCode->execute([$vid, $remisePct, $code]);
            if ($updCode->rowCount() === 0) {
                // Le code vient d'être consommé par une vente concurrente :
                // on refuse la vente entière (rollback) plutôt que d'appliquer
                // la remise deux fois sur un code à usage unique.
                throw new Exception('Code de remise déjà utilisé ou expiré.');
            }
        }

        // ── Sortie de stock au coût d'achat (inventaire intermittent OHADA) ──
        // Débit 6031 (variation de stock = charge) / Crédit 3111 (stock).
        // Synchronise le stock comptable avec le stock physique mouvementé.
        if ($coutAchat > 0) {
            $compteStock  = compteFindOrCreate($db, '3111', 'Médicaments en stock', 3, 'debit');
            $compteVarStk = compteFindOrCreate($db, '6031', 'Variation stocks marchandises', 6, 'debit');
            ecritureCreate($db,
                'Sortie stock vente ' . $ref,
                date('Y-m-d'),
                [
                    [$compteVarStk, round($coutAchat, 2), 0, 'Sortie stock ' . $ref],
                    [$compteStock,  0, round($coutAchat, 2), 'Stock vendu ' . $ref],
                ],
                'vente', $ref, currentUser()['id']
            );
        }

        $db->commit();
        auditLog('vente.create', sprintf('Vente %s : %d articles, %s %s (%s)', $ref, count($cartRaw), fmtMoney($total), $modePaiement, $clientNom ?: '—'), (int)$vid, $ref);
        header('Location: ' . url('vente', ['receipt' => $ref])); exit;

    } catch (Exception $e) {
        $db->rollBack();
        flashError($e, 'vente');
        header('Location: ' . url('vente')); exit;
    }
}

// ── Données catalogue ──────────────────────────────────────
// Projection explicite : on évite p.* (qui charge la colonne description TEXT
// et d'autres colonnes inutiles) sur tout le catalogue à chaque affichage POS.
$produits = $db->prepare("
    SELECT p.id, p.nom, p.reference, p.unite, p.prix_vente, p.seuil_alerte,
           c.nom AS cat,
           COALESCE(pp.stock, 0) AS stock,
           COALESCE(pp.seuil_alerte, p.seuil_alerte) AS seuil_alerte
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    LEFT JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = ?
    WHERE p.actif = 1
    ORDER BY c.nom, p.nom
");
$produits = $produits->execute([$pharmacieId]) ? $produits->fetchAll() : [];

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
$ticketCopies    = max(1, (int)getParam('ticket_nb_copies', '2'));
$pharmAdresse    = getParam('pharmacie_adresse', '');
$pharmTel        = getParam('pharmacie_telephone', '');
$pharmNif        = getParam('pharmacie_nif', '');
$pharmLogoUrl    = pharmacieLogoUrl();

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

// ── Licence : état du plafond pour le blocage POS ───────────
$licState        = licence_current();
$licCap          = $licState['cap'];
$licUsage        = licence_usage();
$licLimitReached = $licUsage >= $licCap;

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
    min-height: 0; /* sinon min-height:auto empêche le scroll et pousse le footer/bouton Valider hors du panneau */
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
    flex: 0 1 auto;
    min-height: 0; /* permet au footer de rétrécir et scroller si totaux+remise+client dépassent */
    overflow-y: auto;
  }
  /* Barre du bouton Valider : ancrée en bas du panier, ne bouge plus quand
     les lignes de remise apparaissent (le cart-footer scroll au-dessus). */
  .cart-validate-bar {
    flex-shrink: 0;
    padding: 12px 20px 14px;
    background: #1e293b;
    border-top: 2px solid #334155;
  }
  /* Bouton déroulant du bloc remise (replié par défaut) */
  .remise-toggle {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; margin-top: 12px; padding: 9px 12px;
    background: rgba(148,163,184,.05); border: 1px solid #334155; border-radius: 8px;
    color: var(--text2); font-size: 13px; font-weight: 600; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
  }
  .remise-toggle:hover { background: rgba(148,163,184,.12); border-color: var(--teal2); color: var(--text); }
  #remise-block { margin-top: 8px; }
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
      <div style="display:flex;align-items:center;gap:8px;">
        <span id="cart-item-count" style="font-size:12px;color:#94a3b8;">0 article</span>
        <button type="button" id="btn-clear-cart" class="btn btn-ghost btn-xs"
                onclick="clearCart()" title="Vider le panier">Vider</button>
      </div>
    </div>
    <?php if ($sessionActive): ?>
    <div style="padding:6px 16px;background:var(--teal-dim);border-bottom:1px solid var(--border2);font-size:12px;display:flex;justify-content:space-between;align-items:center;">
      <span style="display:flex;align-items:center;gap:4px;"><?= icon('money',14) ?> <?= e($sessionActive['caisse_nom']) ?></span>
      <span style="font-weight:600;color:var(--teal2);">Solde : <?= fmtMoney(soldeSessionCaisse($db, (int)$sessionActive['id'])) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Panier (vente libre par défaut à l'ouverture) ── -->

    <div id="cart-body" style="display:flex;flex:1;flex-direction:column;min-height:0;">
      <div class="cart-items" id="cart-items">
        <!-- Rendu par JS -->
      </div>

    <div class="cart-footer">
      <div class="totals-grid">
        <div class="total-box">
          <label>Sous-total TTC</label>
          <span id="pos-subtotal">0 <?= e($devSym) ?></span>
        </div>
        <div class="total-box" id="tva-display" style="display:none;">
          <label>TVA (<?= e($tvaTaux) ?>%)</label>
          <span id="pos-tva">0 <?= e($devSym) ?></span>
        </div>
      <div class="total-box total-main-pro">
        <label>Total TTC</label>
        <span id="pos-total" class="c-teal">0 <?= e($devSym) ?></span>
      </div>

      <!-- Ligne remise (masquée si remise = 0) -->
      <div class="total-box" id="remise-display" style="display:none;">
        <label>Remise HT</label>
        <span id="pos-remise" class="c-red">0 <?= e($devSym) ?></span>
      </div>
      <div class="total-box" id="remise-ttc-display" style="display:none;">
        <label>Remise TTC</label>
        <span id="pos-remise-ttc" class="c-red">0 <?= e($devSym) ?></span>
      </div>
      <div class="total-box total-main-pro" id="net-display" style="display:none;">
        <label>Net à encaisser</label>
        <span id="pos-net" class="c-teal">0 <?= e($devSym) ?></span>
      </div>
    </div>

    <!-- ── Bloc remise (visible par tous, caissier ne saisit que le code) ── -->
    <?php
    // Approbateur = peut saisir taux + générer code.
    $stAppr = $db->prepare("SELECT 1 FROM remise_approbateurs WHERE utilisateur_id=? AND actif=1");
    $stAppr->execute([currentUser()['id']]);
    $estApprobateur = $stAppr->fetch() ? true : false;
    $maxRemise  = (float)getParam('remise_max_pct', '100');
    ?>
    <button type="button" id="remise-toggle" class="remise-toggle"
            onclick="toggleRemiseBlock()" aria-expanded="false" aria-controls="remise-block">
      Remise <span id="remise-toggle-icon">▸</span>
    </button>
    <div id="remise-block" style="display:none;">
    <div style="margin-top:12px;padding:10px;border-radius:8px;border:1px solid #334155;background:rgba(148,163,184,.05);">
      <!-- Ligne 1 : Auteur (pleine largeur pour les noms longs) -->
      <div style="margin-bottom:6px;">
        <label style="display:block;margin:0 0 4px;font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;">Autorisé par</label>
        <?php if ($estApprobateur): ?>
          <input type="text" id="autorise-par-nom" value="<?= e(currentUser()['prenom'].' '.currentUser()['nom']) ?> (moi)"
                 readonly
                 style="width:100%;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#1E293B;color:#94a3b8;font-size:13px;">
          <input type="hidden" id="autorise-par" name="autorise_par" value="<?= (int)currentUser()['id'] ?>">
        <?php else: ?>
          <select id="autorise-par" name="autorise_par" disabled
                  style="width:100%;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#1E293B;color:#94a3b8;font-size:13px;cursor:not-allowed;">
            <option value="">— Saisir le code remise —</option>
            <?php
            $approuveurs = $db->query("SELECT u.id, u.prenom, u.nom FROM remise_approbateurs r
                                      JOIN utilisateurs u ON u.id = r.utilisateur_id
                                      WHERE r.actif = 1 AND u.actif = 1 ORDER BY u.nom, u.prenom")->fetchAll();
            foreach ($approuveurs as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= e($a['prenom'] . ' ' . $a['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <!-- Ligne 2 : Remise % + Code côte à côte -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <label style="display:block;margin:0 0 4px;font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;">Remise %</label>
          <?php if ($estApprobateur): ?>
            <input type="number" id="remise-pct" name="remise_pct" min="0" max="<?= $maxRemise ?>"
                   step="0.01" value="0" placeholder="0"
                   style="width:100%;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#1E293B;color:#f8fafc;font-family:'DM Mono',monospace;text-align:center;">
          <?php else: ?>
            <input type="text" id="remise-pct-display" value="—" readonly
                   style="width:100%;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#1E293B;color:#94a3b8;font-family:'DM Mono',monospace;text-align:center;">
            <input type="hidden" id="remise-pct" name="remise_pct" value="0">
            <div style="font-size:9px;color:var(--text3);text-align:center;margin-top:2px;">via code</div>
          <?php endif; ?>
        </div>
        <div>
          <label style="display:block;margin:0 0 4px;font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;">Code remise</label>
          <input type="text" id="code-remise" name="code_remise" maxlength="10"
                 placeholder="ABC123"
                 style="width:100%;padding:8px 10px;border:1px solid #334155;border-radius:6px;background:#1E293B;color:#f8fafc;font-family:'DM Mono',monospace;font-size:13px;text-transform:uppercase;text-align:center;">
        </div>
      </div>

      <div style="margin-top:6px;font-size:10px;color:var(--text3);">
        <?php if ($estApprobateur): ?>
          <?= icon('info', 10) ?> Saisissez le % + générez un code via <a href="<?= url('remise_codes') ?>" target="_blank" style="color:var(--teal2);">Codes de remise</a>
        <?php else: ?>
          <?= icon('info', 10) ?> Saisissez le code fourni par l'autorité. Le % s'applique automatiquement.
        <?php endif; ?>
      </div>
    </div>
    </div><!-- /#remise-block -->

      <div style="height:12px;"></div>

      <!-- ── Mode client : saisie libre ou existant ── -->
      <!-- (La bascule Libre/Fidèle se fait via le bouton du header du panier ; on garde ici uniquement le libellé.) -->
      <div class="form-group" style="margin-bottom:6px;">
        <div style="margin-bottom:6px;">
          <label style="margin:0;">Client</label>
        </div>
        <input type="hidden" name="client_mode" id="client-mode-hdn" value="simple">
        <!-- Saisie libre -->
        <input type="text" name="client_nom_saisie" id="client-nom-saisie" required
               placeholder="Nom du client *"
               style="width:100%;font-family:'DM Mono',monospace;">
        <!-- Client existant -->
        <?php if ($fideliteActive): ?>
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
        <?php endif; ?>
      </div>
      <input type="hidden" name="mode_paiement" id="mode-paiement" value="espèces">
      <div id="mode-credit" style="display:none;margin-bottom:15px; background:rgba(255,255,255,0.05); padding:10px; border-radius:6px; border:1px solid #334155;<?= ($fideliteActive && $creditActive) ? '' : ' display:none;' ?>">
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
        <span style="font-size:13px; color:var(--text2);">Reliquat à rendre</span>
        <span id="monnaie" class="fw-mono c-teal" style="font-size:16px; font-weight:700;">0 <?= e($devSym) ?></span>
      </div>
    </div><!-- /cart-footer -->

    <div class="cart-validate-bar">
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
            <th>Unité</th>
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
            <td><span class="p-unit-tag"><?= e($p['unite'] ?? '—') ?></span></td>
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

<?php if ($licLimitReached): ?>
<!-- ── Modal : limite de licence atteinte (plein écran, centré) ── -->
<div class="modal-overlay open" id="modal-licence-limit" style="z-index:2000;">
  <div class="modal" style="width:460px;max-width:92vw;text-align:center;">
    <div style="padding:30px 28px 22px;">
      <div style="width:64px;height:64px;margin:0 auto 16px;border-radius:50%;background:var(--red-dim);border:2px solid var(--red);display:flex;align-items:center;justify-content:center;">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--red)"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <h2 style="font-size:20px;font-weight:700;margin:0 0 8px;color:var(--red);">Limite de lignes atteinte</h2>
      <p style="font-size:14px;color:var(--text2);margin:0 0 6px;">
        Vous avez utilisé <strong style="color:var(--text);"><?= number_format($licUsage, 0, ',', ' ') ?></strong>
        lignes de vente sur un plafond de <strong style="color:var(--text);"><?= number_format($licCap, 0, ',', ' ') ?></strong>.
      </p>
      <p style="font-size:13px;color:var(--text2);margin:0 0 18px;">
        Les nouvelles ventes sont bloquées. Contactez votre fournisseur pour étendre la licence
        (la consultation, le stock et la comptabilité restent accessibles).
      </p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <?php if (hasPermission('parametres.gerer')): ?>
        <a href="<?= url('licence') ?>" class="btn btn-primary btn-sm">
          <?= icon('key',14) ?> Activer un code
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-licence-limit')">
          Fermer
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Licence : empêcher la validation quand le plafond est atteint ──
const POS_LICENCE_BLOCKED = <?= $licLimitReached ? 'true' : 'false' ?>;
(function(){
  var form = document.getElementById('pos-form');
  if (!form) return;
  form.addEventListener('submit', function(e){
    if (!POS_LICENCE_BLOCKED) return;
    e.preventDefault();
    var m = document.getElementById('modal-licence-limit');
    if (m) { m.classList.add('open'); m.style.display = ''; }
    showNotif && showNotif('Limite de lignes de vente atteinte — vente bloquée.', 'error');
  }, true);
})();

// Bouton « Valider » maintenu désactivé tant que le plafond est atteint
// (app.js le réactive à chaque ajout au panier — on force le verrou).
if (POS_LICENCE_BLOCKED) {
  var _btnVal = document.getElementById('btn-validate');
  if (_btnVal) {
    setInterval(function(){
      _btnVal.disabled = true;
      _btnVal.style.opacity = '0.5';
      _btnVal.style.cursor = 'not-allowed';
      _btnVal.title = 'Limite de lignes atteinte — vente bloquée';
    }, 400);
  }
}
</script>

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

// ── Plier/déplier le bloc remise (replié par défaut) ──
function toggleRemiseBlock() {
  var block = document.getElementById('remise-block');
  var icon  = document.getElementById('remise-toggle-icon');
  var btn   = document.getElementById('remise-toggle');
  if (!block) return;
  var open = block.style.display !== 'none';
  block.style.display = open ? 'none' : 'block';
  if (icon) icon.textContent = open ? '▸' : '▾';
  if (btn)  btn.setAttribute('aria-expanded', open ? 'false' : 'true');
}

var CLIENT_MODE = 'simple'; // Vente libre par défaut à l'ouverture du POS
var FIDELITE_ACTIVE = <?= $fideliteActive ? 'true' : 'false' ?>;
var CREDIT_ACTIVE   = <?= $creditActive ? 'true' : 'false' ?>;

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

  // Bouton "Vider" : présent en dur dans le header HTML, on ne fait que
  // brancher le handler si l'élément existe sans onclick (robuste à l'ordre
  // de chargement des scripts).
  var btnVider = document.getElementById('btn-clear-cart');
  if (btnVider && !btnVider.onclick) {
    btnVider.onclick = clearCart;
  }

  // Bouton pour changer de mode (→ Fidèle) — créé par JS si nécessaire.
  var headerDiv = document.querySelector('.cart-panel .card-header');
  var btnMode = document.getElementById('btn-switch-mode');
  if (!btnMode) {
    btnMode = document.createElement('button');
    btnMode.type = 'button';
    btnMode.id = 'btn-switch-mode';
    btnMode.className = 'btn btn-ghost btn-xs';
    btnMode.style.marginRight = '4px';
    btnMode.onclick = function(){ switchClientMode(); };
    headerDiv.appendChild(btnMode);
  }
  if (!FIDELITE_ACTIVE) {
    btnMode.style.display = 'none';
  } else {
    btnMode.style.display = '';
    btnMode.textContent = mode === 'simple' ? '→ Fidèle' : '→ Libre';
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
  if (!FIDELITE_ACTIVE) { return; } // mode « client fidèle » désactivé
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
  // Mode « client fidèle » désactivé : force toujours la vente libre.
  if (!FIDELITE_ACTIVE) mode = 'simple';
  const btnSimple    = document.getElementById('client-mode-simple');
  const btnExistant  = document.getElementById('client-mode-existant');
  const inputSimple  = document.getElementById('client-nom-saisie');
  const selectExist  = document.getElementById('client-select');
  const creditBlock  = document.getElementById('mode-credit');
  const creditCb     = document.getElementById('credit-checkbox');
  const hdnMode      = document.getElementById('client-mode-hdn');

  if (mode === 'simple') {
    if (btnSimple)   { btnSimple.style.background = '#334155';   btnSimple.style.color = '#f8fafc'; }
    if (btnExistant) { btnExistant.style.background = 'transparent'; btnExistant.style.color = '#94a3b8'; }
    if (inputSimple) inputSimple.style.display = '';
    if (selectExist) selectExist.style.display = 'none';
    if (hdnMode) hdnMode.value = 'simple';
    if (selectExist) selectExist.value = '';
    // Cacher le bloc crédit en mode libre
    if (creditBlock) creditBlock.style.display = 'none';
    if (creditCb) { creditCb.checked = false; toggleCreditMode(creditCb); }
  } else {
    if (btnExistant) { btnExistant.style.background = '#334155'; btnExistant.style.color = '#f8fafc'; }
    if (btnSimple)   { btnSimple.style.background = 'transparent'; btnSimple.style.color = '#94a3b8'; }
    if (selectExist) selectExist.style.display = '';
    if (inputSimple) inputSimple.style.display = 'none';
    if (hdnMode) hdnMode.value = 'existant';
    if (inputSimple) inputSimple.value = '';
  }
}

function onClientSelectChange() {
  const selectExist  = document.getElementById('client-select');
  const creditBlock  = document.getElementById('mode-credit');
  const creditCb     = document.getElementById('credit-checkbox');
  if (selectExist.value && CREDIT_ACTIVE) {
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
          <?php if ($pharmLogoUrl): ?>
          <div style="margin-bottom:6px;"><img src="<?= e($pharmLogoUrl) ?>" alt="" style="max-height:60px;max-width:90%;"></div>
          <?php endif; ?>
          <div style="font-family:var(--font-title);font-size:16px;font-weight:600;"><?= e($appNom) ?></div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px;"><?= e($ticketSousTitre) ?></div>
          <?php if ($pharmacieNom): ?>
          <div style="font-size:11px;font-weight:600;margin-top:3px;color:var(--teal2);"><?= e($pharmacieNom) ?></div>
          <?php endif; ?>
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
        <?php if ((float)($receiptData['remise_pct'] ?? 0) > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--red);">
          <span>Remise <?= (float)$receiptData['remise_pct'] ?>% (HT)</span><span>-<?= fmtMoney((float)$receiptData['remise_montant']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ((float)$receiptData['tva_total'] > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);">
          <span>TVA (<?= e($tvaTaux) ?>%)</span><span><?= fmtMoney((float)$receiptData['tva_total']) ?></span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:6px;padding-top:6px;border-top:1px dashed var(--border2);">
          <span>TOTAL TTC</span><span class="c-teal"><?= fmtMoney((float)$receiptData['total']) ?></span>
        </div>
        <?php if ($receiptData['mode_paiement'] === 'espèces' && $receiptData['montant_recu'] > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);margin-top:4px;">
          <span>Reçu</span><span><?= fmtMoney((float)$receiptData['montant_recu']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);">
          <span>Reliquat</span><span><?= fmtMoney((float)$receiptData['monnaie']) ?></span>
        </div>
        <?php endif; ?>
        <div style="text-align:center;margin-top:12px;padding-top:8px;border-top:1px dashed var(--border2);font-size:10px;color:var(--text3);">
          <?= e($modeLabels[$receiptData['mode_paiement']] ?? $receiptData['mode_paiement']) ?><br>
          Caissier : <?= e(trim($receiptData['prenom'] . ' ' . $receiptData['u_nom'])) ?><br>
          <?= e($ticketPied) ?><br>
          <span style="color:var(--text3);">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-receipt')">
        Fermer
      </button>
      <a href="<?= url('ventes_hist') ?>" class="btn btn-ghost btn-sm">
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
const PHARM_SERVED = <?= json_encode($pharmacieNom ?? '') ?>;
const PHARM_LOGO   = <?= json_encode($pharmLogoUrl) ?>;
const APP_BRAND    = <?= json_encode(defined('APP_NAME') ? APP_NAME : 'PharmaCare') ?>;
const TICKET_COPIES = <?= (int)$ticketCopies ?>;
function ticketCopyLabel(i){ return i===0 ? 'Exemplaire Client' : (i===1 ? 'Exemplaire Caisse' : ('Exemplaire '+(i+1))); }

function printReceipt80() {
  if (!rateLimitClick('print.ticket80', 15, 60000)) { rateLimitWarn('print.ticket80', 15, 60000); return; }
  const content = document.getElementById('receipt-content').innerHTML;
  let copies = '';
  for (let i = 0; i < TICKET_COPIES; i++) {
    copies += `<div class="ticket-copy">
      <div class="copy-label">${escHtml(ticketCopyLabel(i))}</div>
      ${content}
    </div>`;
  }
  const win = window.open('', '_blank', 'width=320,height=600');
  win.document.write(`<!DOCTYPE html><html><head><title>Ticket</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{font-family:'DM Mono',monospace;font-size:12px;line-height:1.5;padding:8px;max-width:280px;margin:0 auto;}
      .ticket-copy{page-break-after:always;}
      .ticket-copy:last-child{page-break-after:auto;}
      .copy-label{text-align:center;font-size:10px;font-weight:700;letter-spacing:1px;color:#0d9488;border:1px dashed #0d9488;border-radius:4px;padding:3px 0;margin-bottom:6px;text-transform:uppercase;}
      @media print{body{margin:0;}@page{margin:0;size:80mm auto;}}
    </style>
    <link href="<?= APP_URL ?>/assets/fonts/fonts.css" rel="stylesheet">
  </head><body>${copies}<script>window.onload=function(){window.print();}<\/script></body></html>`);
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

  const pageBody = (label) => `
    <div class="copy-badge">${escHtml(label)}</div>
    <div class="header">
      <div>
        ${PHARM_LOGO ? '<img src="'+PHARM_LOGO+'" alt="" style="max-height:70px;max-width:220px;margin-bottom:8px;"><br>' : ''}
        <h1>${escHtml(PHARM_NAME)}</h1>
        <div class="sub">${escHtml(TICKET_TITLE)}${PHARM_SERVED ? ' — ' + escHtml(PHARM_SERVED) : ''}</div>
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
      ${Number(d.tva_total) > 0 ? `<div><span>TVA (${TVA_RATE}%)</span><span style="font-family:'DM Mono',monospace;">${fmt(d.tva_total)} ${DEV_SYM}</span></div>` : ''}
      <div class="grand"><span>TOTAL TTC</span><span>${fmt(d.total)} ${DEV_SYM}</span></div>
      ${d.mode_paiement==='espèces' && d.montant_recu>0 ? `
        <div><span>Reçu</span><span style="font-family:'DM Mono',monospace;">${fmt(d.montant_recu)} ${DEV_SYM}</span></div>
        <div><span>Reliquat</span><span style="font-family:'DM Mono',monospace;">${fmt(d.monnaie)} ${DEV_SYM}</span></div>
      ` : ''}
    </div>
    <div class="footer">
      Mode : ${escHtml(modePmt)}<br>
      ${escHtml(TICKET_FOOT)}<br>
      &copy; ${new Date().getFullYear()} ${escHtml(APP_BRAND)}
    </div>`;

  let copies = '';
  for (let i = 0; i < TICKET_COPIES; i++) {
    copies += `<div class="a4-copy">${pageBody(ticketCopyLabel(i))}</div>`;
  }

  const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture ${escHtml(d.reference)}</title>
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      body{font-family:'Segoe UI',system-ui,sans-serif;font-size:14px;color:#1e293b;padding:40px;max-width:210mm;margin:0 auto;-webkit-print-color-adjust:exact;}
      @media print{body{padding:15mm;}}
      .a4-copy{page-break-after:always;}
      .a4-copy:last-child{page-break-after:auto;}
      .copy-badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:1px;color:#fff;background:#0d9488;padding:3px 12px;border-radius:4px;margin-bottom:14px;text-transform:uppercase;}
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
  </head><body>${copies}
    <script>window.onload=function(){window.print();}<\/script>
  </body></html>`;

  const win = window.open('', '_blank', 'width=900,height=700');
  win.document.write(html);
  win.document.close();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?php endif; ?>

<script>
// ── Vente libre prioritaire : démarre directement en mode Libre ──
// La caisse s'ouvre prête à encaisser un client de passage, sans écran de
// choix. Le bouton « → Fidèle » reste dispo pour basculer vers un client
// enregistré en cours de vente si besoin.
//
// NB : ce bloc DOIT rester hors du bloc « receiptData » ci-dessus :
// celui-ci ne s'exécute qu'après validation d'une vente, or on doit configurer
// le panier (boutons Vider / → Fidèle) à chaque ouverture du POS.
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

<?php layout_foot(); ?>
