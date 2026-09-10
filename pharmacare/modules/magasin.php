<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('magasin.voir');
$db     = getDB();
$uid    = currentUser()['id'];
$onglet = $_GET['onglet'] ?? 'stock';

// ════════════════════════════════════════════════════════════
//  Import CSV d'articles → stock magasin (dépôt central)
// ════════════════════════════════════════════════════════════
function magasin_csv_normalize(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, [
        'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}
function magasin_csv_cell(string $s): string {
    $s = trim($s);
    if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
        $conv = @mb_convert_encoding($s, 'UTF-8', 'CP1252');
        if ($conv !== false && $conv !== '') $s = $conv;
    }
    return $s;
}
function magasin_csv_num(string $s): float {
    $s = str_replace([' ', "\xc2\xa0"], '', trim($s));
    $s = str_replace(',', '.', $s);
    return (float)$s;
}
function magasin_csv_parse_date(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $s);
        if ($d instanceof DateTime && $d->format($fmt) === $s) return $d->format('Y-m-d');
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}
$MAGASIN_CSV_FIELDS = [
    'nom'             => ['nom', 'designation', 'design', 'libelle', 'produit', 'medicament', 'name'],
    'reference'       => ['reference', 'ref', 'code', 'codebarres', 'code barres', 'ean', 'cip'],
    'unite'           => ['unite', 'unite de vente', 'conditionnement', 'forme', 'presentation'],
    'categorie'       => ['categorie', 'category', 'rayon', 'famille'],
    'fournisseur'     => ['fournisseur', 'supplier', 'labo', 'laboratoire', 'fabriquant'],
    'stock'           => ['stock', 'quantite', 'qte', 'dispo'],
    'seuil_alerte'    => ['seuil', 'seuil alerte', 'alerte'],
    'prix_achat'      => ['prix achat', 'pa', 'cout', 'prixa'],
    'prix_vente'      => ['prix vente', 'pv', 'prix', 'prixv'],
    'tva'             => ['tva', 'taux tva'],
    'date_expiration' => ['expiration', 'peremption', 'dlc', 'dluo', 'date expiration'],
    'description'     => ['description', 'notes', 'remarque', 'commentaire'],
];

// ── Téléchargement du modèle CSV ────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'import_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modele_import_magasin.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock', 'seuil_alerte', 'prix_achat', 'prix_vente', 'tva', 'date_expiration', 'description'], ';');
    fputcsv($out, ['Paracétamol 500mg', 'MED-001', 'comprimé', 'Antalgiques', 'PharmaPlus', '100', '10', '150', '200', '9', '31/12/2027', 'Boîte de 16 comprimés'], ';');
    fputcsv($out, ['Amoxicilline 1g', 'MED-002', 'comprimé', 'Antibiotiques', '', '50', '10', '320', '450', '9', '', ''], ';');
    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════
// POST — actions de gestion (magasin.gerer)
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('magasin.gerer')) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Transfert Magasin → Pharmacie ───────────────────────
    if ($action === 'transfert') {
        $produits_ids = $_POST['produit_id'] ?? [];
        $quantites    = $_POST['quantite']    ?? [];
        $note         = trim($_POST['note'] ?? '');
        $pharmacieId  = (int)($_POST['pharmacie_id'] ?? 0);

        // Valider la pharmacie cible (doit être active)
        $stmtPh = $db->prepare("SELECT id, nom FROM pharmacies WHERE id=? AND actif=1");
        $stmtPh->execute([$pharmacieId]);
        $pharmacie = $stmtPh->fetch();
        if (!$pharmacie) {
            flash('Pharmacie cible invalide.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        $lignesValides = [];
        for ($i = 0; $i < count($produits_ids); $i++) {
            $pid = (int)$produits_ids[$i];
            $qte = (int)$quantites[$i];
            if ($pid > 0 && $qte > 0) $lignesValides[] = [$pid, $qte];
        }

        if (!$lignesValides) {
            flash('Aucune ligne valide pour le transfert.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        try {
            $db->beginTransaction();

            // Vérifier la disponibilité du stock magasin pour chaque ligne
            $stmtStock = $db->prepare("SELECT nom, stock_magasin FROM produits WHERE id=?");
            $insuffisants = [];
            foreach ($lignesValides as [$pid, $qte]) {
                $stmtStock->execute([$pid]);
                $p = $stmtStock->fetch();
                if (!$p) {
                    $insuffisants[] = "Produit #$pid introuvable";
                } elseif ((int)$p['stock_magasin'] < $qte) {
                    $insuffisants[] = e($p['nom']) . " (dispo: {$p['stock_magasin']}, demandé: $qte)";
                }
            }
            if ($insuffisants) {
                $db->rollBack();
                flash('Stock magasin insuffisant : ' . implode(' ; ', $insuffisants), 'error');
                header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
            }

            // Créer l'entête de transfert (la note mentionne la pharmacie cible)
            $ref = genRef('TRF');
            $noteFinale = trim($note . ' → ' . $pharmacie['nom']);
            $db->prepare("INSERT INTO transferts_magasin (reference,utilisateur_id,note,pharmacie_id) VALUES (?,?,?,?)")
               ->execute([$ref, $uid, $noteFinale, $pharmacieId]);
            $transfertId = (int)$db->lastInsertId();

            $stmtNom = $db->prepare("SELECT nom FROM produits WHERE id=?");
            // Garde atomique : le décrément ne passe que si le stock suffit
            // (protège contre la concurrence entre deux transferts simultanés).
            $stmtDecMag   = $db->prepare("UPDATE produits SET stock_magasin = stock_magasin - ? WHERE id = ? AND stock_magasin >= ?");
            // Crédit du stock de la pharmacie cible (crée la ligne si nécessaire)
            $stmtIncPharm = $db->prepare("
                INSERT INTO produit_pharmacie (produit_id, pharmacie_id, stock, seuil_alerte)
                VALUES (?, ?, ?, 10)
                ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)
            ");
            // Sync produits.stock pour la pharmacie principale (modules existants lisent produits.stock)
            $stmtSyncMain = ($pharmacieId === 1)
                ? $db->prepare("UPDATE produits p
                                JOIN produit_pharmacie pp ON pp.produit_id = p.id AND pp.pharmacie_id = 1
                                SET p.stock = pp.stock WHERE p.id = ?")
                : null;
            $stmtLigne    = $db->prepare("INSERT INTO transfert_lignes (transfert_id,produit_id,produit_nom,quantite) VALUES (?,?,?,?)");
            $stmtMvtMag   = $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id,transfert_id) VALUES (?,'sortie',?,?,?,?)");
            $stmtMvtPharm = $db->prepare("INSERT INTO mouvements_stock (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");

            foreach ($lignesValides as [$pid, $qte]) {
                $stmtNom->execute([$pid]);
                $nom = $stmtNom->fetchColumn() ?: ('Produit #' . $pid);

                // Décrémenter le magasin (avec garde), créditer la pharmacie cible
                $stmtDecMag->execute([$qte, $pid, $qte]);
                if ($stmtDecMag->rowCount() === 0) {
                    throw new Exception('Stock magasin devenu insuffisant pour ' . $nom . ' (demandé : ' . $qte . ').');
                }
                $stmtIncPharm->execute([$pid, $pharmacieId, $qte]);
                if ($stmtSyncMain) $stmtSyncMain->execute([$pid]);

                // Ligne de transfert
                $stmtLigne->execute([$transfertId, $pid, $nom, $qte]);

                // Mouvement magasin (sortie)
                $stmtMvtMag->execute([$pid, $qte, 'Transfert ' . $ref . ' vers ' . $pharmacie['nom'], $uid, $transfertId]);
                // Mouvement pharmacie (entrée — traçabilité)
                $stmtMvtPharm->execute([$pid, $qte, 'Transfert ' . $ref . ' du magasin (' . $pharmacie['nom'] . ')', $uid]);
            }

            $db->commit();
            auditLog('magasin.transfert', sprintf('Transfert %s : %d ligne(s) vers %s', $ref, count($lignesValides), $pharmacie['nom']));
            flash('Transfert ' . $ref . ' effectué — stock de « ' . $pharmacie['nom'] . ' » approvisionné.');
            // Émet le bon de ravitaillement (imprimable) pour ce transfert.
            header('Location: ' . url('magasin', ['bon' => $transfertId])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'transfert magasin');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }
    }

    // ── Retour Pharmacie → Magasin (transfert inverse) ─────
    if ($action === 'retour') {
        $produits_ids = $_POST['produit_id'] ?? [];
        $quantites    = $_POST['quantite']    ?? [];
        $note         = trim($_POST['note'] ?? '');

        $lignesValides = [];
        for ($i = 0; $i < count($produits_ids); $i++) {
            $pid = (int)$produits_ids[$i];
            $qte = (int)$quantites[$i];
            if ($pid > 0 && $qte > 0) $lignesValides[] = [$pid, $qte];
        }

        if (!$lignesValides) {
            flash('Aucune ligne valide pour le retour.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        try {
            $db->beginTransaction();

            // Vérifier la disponibilité du stock pharmacie pour chaque ligne
            $stmtStock = $db->prepare("SELECT nom, stock FROM produits WHERE id=?");
            $insuffisants = [];
            foreach ($lignesValides as [$pid, $qte]) {
                $stmtStock->execute([$pid]);
                $p = $stmtStock->fetch();
                if (!$p) {
                    $insuffisants[] = "Produit #$pid introuvable";
                } elseif ((int)$p['stock'] < $qte) {
                    $insuffisants[] = e($p['nom']) . " (pharmacie: {$p['stock']}, demandé: $qte)";
                }
            }
            if ($insuffisants) {
                $db->rollBack();
                flash('Stock pharmacie insuffisant : ' . implode(' ; ', $insuffisants), 'error');
                header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
            }

            $stmtNom        = $db->prepare("SELECT nom FROM produits WHERE id=?");
            $stmtDecPharm   = $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmtIncMag     = $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?");
            $stmtMvtPharm   = $db->prepare("INSERT INTO mouvements_stock (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'sortie',?,?,?)");
            $stmtMvtMag     = $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");

            $libelle = 'Retour pharmacie → magasin' . ($note ? ' — ' . $note : '');

            foreach ($lignesValides as [$pid, $qte]) {
                $stmtDecPharm->execute([$qte, $pid, $qte]);
                if ($stmtDecPharm->rowCount() === 0) {
                    // garde-fou concurrence
                    $db->rollBack();
                    flash('Stock pharmacie modifié entre-temps pour un produit. Réessayez.', 'error');
                    header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
                }
                $stmtIncMag->execute([$qte, $pid]);

                $stmtMvtPharm->execute([$pid, $qte, $libelle, $uid]);
                $stmtMvtMag->execute([$pid, $qte, $libelle, $uid]);
            }

            $db->commit();
            auditLog('magasin.retour', sprintf('Retour pharmacie→magasin : %d ligne(s)%s', count($lignesValides), $note ? ' (' . $note . ')' : ''));
            flash('Retour vers magasin effectué (' . count($lignesValides) . ' produit(s)).');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'retour magasin');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }
    }

    // ── Réception / Ajustement manuel du magasin ───────────
    if ($action === 'reception') {
        $pid  = (int)($_POST['produit_id'] ?? 0);
        $qte  = (int)($_POST['quantite'] ?? 0);
        $type = ($_POST['type'] ?? 'entrée') === 'ajustement' ? 'ajustement' : 'entrée';
        $motif = trim($_POST['motif'] ?? '');

        if ($pid <= 0 || $qte === 0) {
            flash('Produit ou quantité invalide.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
        }

        try {
            $db->beginTransaction();
            // Quantité négative autorisée pour un ajustement de retrait
            $delta = $type === 'ajustement' ? $qte : abs($qte);
            if ($delta < 0) {
                // Vérifier qu'on ne descend pas sous zéro
                $stmtStock = $db->prepare("SELECT stock_magasin FROM produits WHERE id=?");
                $stmtStock->execute([$pid]);
                $cur = (int)$stmtStock->fetchColumn();
                if ($cur + $delta < 0) {
                    $db->rollBack();
                    flash('Ajustement impossible : stock magasin négatif.', 'error');
                    header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
                }
            }
            $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?")->execute([$delta, $pid]);
            $libelle = $type === 'ajustement'
                ? ('Ajustement magasin' . ($motif ? ' — ' . $motif : ''))
                : ('Réception magasin' . ($motif ? ' — ' . $motif : ''));
            $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, $type, $delta, $libelle, $uid]);
            $db->commit();
            auditLog('magasin.' . $type, sprintf('Produit #%d, delta %+d (%s)', $pid, $delta, $libelle));
            flash('Mouvement magasin enregistré (' . $type . ').');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'mouvement magasin');
            header('Location: ' . url('magasin', ['onglet'=>'reception'])); exit;
        }
    }

    // ── Réception multi-produits par scanner (codes-barres / QR) ──
    // Le scanner (clavier USB ou douchette) envoie le code + Entrée ; chaque
    // scan ajoute +1 ligne/produit. Les quantités sont éditables avant validation.
    if ($action === 'reception_scan') {
        $pids  = is_array($_POST['scan_pid'] ?? null) ? $_POST['scan_pid'] : [];
        $qtes  = is_array($_POST['scan_qte'] ?? null) ? $_POST['scan_qte'] : [];
        $motif = trim((string)($_POST['motif'] ?? ''));

        // Déduplication + addition des doublons (produits scannés plusieurs fois)
        $lines = [];
        foreach ($pids as $i => $pid) {
            $pid = (int)$pid;
            $qte = (int)($qtes[$i] ?? 0);
            if ($pid > 0 && $qte > 0) $lines[$pid] = ($lines[$pid] ?? 0) + $qte;
        }

        if (!$lines) {
            flash('Aucune ligne de scan valide (produit ou quantité).', 'error');
            header('Location: ' . url('magasin', ['onglet' => 'reception'])); exit;
        }

        try {
            $db->beginTransaction();
            $stUp = $db->prepare("UPDATE produits SET stock_magasin = stock_magasin + ? WHERE id = ?");
            $stIn = $db->prepare("INSERT INTO mouvements_magasin (produit_id, type, quantite, motif, utilisateur_id) VALUES (?, 'entrée', ?, ?, ?)");
            $libelle = 'Réception scan' . ($motif !== '' ? ' — ' . $motif : '');
            foreach ($lines as $pid => $qte) {
                $stUp->execute([$qte, $pid]);
                $stIn->execute([$pid, $qte, $libelle, $uid]);
            }
            $db->commit();
            auditLog('magasin.entrée', sprintf('Réception scanner : %d produit(s), %d unité(s)%s', count($lines), array_sum($lines), $motif !== '' ? ' — ' . $motif : ''));
            flash('Réception scanner enregistrée : ' . count($lines) . ' produit(s), ' . array_sum($lines) . ' unité(s).');
            header('Location: ' . url('magasin', ['onglet' => 'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'réception scanner');
            header('Location: ' . url('magasin', ['onglet' => 'reception'])); exit;
        }
    }

    // ── Ajustement en lot du magasin (remise à niveau absolue) ─
    // Une seule quantité cible appliquée à tous les produits sélectionnés.
    // Un mouvement 'ajustement' signé est journalisé pour chaque produit
    // dont le stock change réellement (delta ≠ 0).
    if ($action === 'ajustement_lot') {
        $produits_ids = $_POST['produit_id'] ?? [];
        $qte          = (int)($_POST['quantite'] ?? -1);
        $motif        = trim($_POST['motif'] ?? '');

        // Filtrer les IDs valides (déduplication)
        $ids = [];
        foreach ($produits_ids as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) $ids[$pid] = true;
        }

        if (!$ids || $qte < 0) {
            flash('Sélection ou quantité invalide.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        try {
            $db->beginTransaction();
            // FOR UPDATE : la relecture doit prendre le verrou de ligne, sinon
            // (REPEATABLE READ) une vente ou un autre ajustement simultané sur le
            // même produit serait écrasé par l'écriture de la valeur absolue.
            $stmtStock = $db->prepare("SELECT nom, stock_magasin FROM produits WHERE id=? FOR UPDATE");
            $stmtSet   = $db->prepare("UPDATE produits SET stock_magasin=? WHERE id=?");
            $stmtMvt   = $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'ajustement',?,?,?)");

            $appliques = 0;
            $total     = count($ids);
            $libelle = 'Ajustement en lot' . ($motif ? ' — ' . $motif : '');
            // Ordre de verrouillage déterministe (tri par id) : évite les
            // interblocages entre deux ajustements en lot simultanés.
            ksort($ids);
            foreach (array_keys($ids) as $pid) {
                $stmtStock->execute([$pid]);
                $p = $stmtStock->fetch();
                if (!$p) continue; // produit disparu (supprimé entre-temps) → on ignore
                $ancien = (int)$p['stock_magasin'];
                $delta  = $qte - $ancien;
                if ($delta === 0) continue; // inchangé → rien à journaliser
                // qte >= 0 garantit stock_magasin final >= 0
                $stmtSet->execute([$qte, $pid]);
                $stmtMvt->execute([$pid, $delta, $libelle, $uid]);
                $appliques++;
            }

            $db->commit();
            auditLog('magasin.ajustement_lot', sprintf('Ajustement en lot : %d/%d produit(s) mis à %d%s', $appliques, $total, $qte, $motif ? ' (' . $motif . ')' : ''));
            flash('Ajustement en lot : ' . $appliques . '/' . $total . ' produit(s) mis à ' . $qte . ' unités.');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'ajustement en lot');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }
    }

    // ── Ajustement unitaire (double-clic sur une ligne du stock) ──
    // Valeur cible absolue pour UN produit ; mouvement 'ajustement' signé
    // journalisé uniquement si la quantité change réellement (delta ≠ 0).
    if ($action === 'ajuster') {
        $pid   = (int)($_POST['produit_id'] ?? 0);
        $qte   = (int)($_POST['quantite'] ?? -1);
        $motif = trim($_POST['motif'] ?? '');

        if ($pid <= 0 || $qte < 0) {
            flash('Produit ou quantité invalide.', 'error');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }

        try {
            $db->beginTransaction();
            // FOR UPDATE : voir commentaire de l'ajustement en lot (même raison).
            $stmtStock = $db->prepare("SELECT nom, stock_magasin FROM produits WHERE id=? FOR UPDATE");
            $stmtStock->execute([$pid]);
            $p = $stmtStock->fetch();
            if (!$p) {
                $db->rollBack();
                flash('Produit introuvable.', 'error');
                header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
            }
            $ancien = (int)$p['stock_magasin'];
            $delta  = $qte - $ancien;
            if ($delta !== 0) {
                $db->prepare("UPDATE produits SET stock_magasin=? WHERE id=?")->execute([$qte, $pid]);
                $libelle = 'Ajustement ligne' . ($motif ? ' — ' . $motif : '');
                $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'ajustement',?,?,?)")
                   ->execute([$pid, $delta, $libelle, $uid]);
            }
            $db->commit();
            auditLog('magasin.ajuster', sprintf('Produit #%d (%s) : %d → %d%s', $pid, $p['nom'], $ancien, $qte, $motif ? ' (' . $motif . ')' : ''));
            flash($delta === 0
                ? 'Quantité inchangée pour « ' . $p['nom'] . ' ».'
                : 'Quantité magasin mise à jour pour « ' . $p['nom'] . ' » : ' . $ancien . ' → ' . $qte . '.');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        } catch (Exception $e) {
            $db->rollBack();
            flashError($e, 'ajustement ligne');
            header('Location: ' . url('magasin', ['onglet'=>'stock'])); exit;
        }
    }

    // ── Import CSV → stock magasin ─────────────────────────
    if ($action === 'import') {
        $mode     = (($_POST['doublons'] ?? 'skip') === 'update') ? 'update' : 'skip';
        $file     = $_FILES['file'] ?? null;
        $ajoutes  = 0; $maj = 0; $ignores = 0; $avert = 0;
        $errors   = [];
        $warnings = [];

        $aliasMap = [];
        foreach ($MAGASIN_CSV_FIELDS as $field => $aliases) {
            foreach ($aliases as $a) $aliasMap[magasin_csv_normalize($a)] = $field;
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            $errors[] = 'Aucun fichier reçu ou upload échoué.';
        } elseif (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') {
            $errors[] = 'Le fichier doit avoir l\'extension .csv.';
        } elseif ((int)($file['size'] ?? 0) > 40 * 1024 * 1024) {
            $errors[] = 'Le fichier dépasse 40 Mo.';
        } else {
            $fh = @fopen($file['tmp_name'], 'r');
            if (!$fh) {
                $errors[] = 'Impossible de lire le fichier.';
            } else {
                if (fread($fh, 3) !== "\xEF\xBB\xBF") fseek($fh, 0);
                $pos = ftell($fh);
                $firstLine = (string)fgets($fh);
                fseek($fh, $pos);
                $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                $header = fgetcsv($fh, 0, $delim);
                if (!$header) {
                    $errors[] = 'Fichier vide ou illisible.';
                } else {
                    $colMap = [];
                    foreach ($header as $i => $h) {
                        $key = magasin_csv_normalize(magasin_csv_cell((string)$h));
                        if (isset($aliasMap[$key])) $colMap[$aliasMap[$key]] = (int)$i;
                    }
                    if (!isset($colMap['nom'])) {
                        $errors[] = 'Colonne « nom » manquante. En-têtes détectés : ' . implode(', ', array_map('trim', $header));
                    } else {
                        $canProcess = true;
                    }
                }
                if ($canProcess) {
                    $stmtCat   = $db->prepare("SELECT id FROM categories WHERE LOWER(nom)=LOWER(?) LIMIT 1");
                    $stmtFourn = $db->prepare("SELECT id FROM fournisseurs WHERE LOWER(nom)=LOWER(?) ORDER BY actif DESC LIMIT 1");
                    $stmtFind  = $db->prepare("SELECT id FROM produits WHERE reference=? LIMIT 1");
                    $IMPORT_PALETTE = ['#00c9a7','#4895ef','#f0b429','#ef4444','#9b59b6','#e74c3c','#e67e22','#1abc9c','#3498db','#e91e63'];
                    $importCatIdx    = 0;
                    $importCatsCrees = [];
                    $stmtIns   = $db->prepare("INSERT INTO produits (nom,reference,unite,categorie_id,fournisseur_id,description,stock,stock_magasin,seuil_alerte,prix_achat,prix_vente,tva,date_expiration) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmtUpd   = $db->prepare("UPDATE produits SET nom=?,unite=?,categorie_id=?,fournisseur_id=?,description=?,seuil_alerte=?,prix_achat=?,prix_vente=?,tva=?,date_expiration=?,stock_magasin=stock_magasin+? WHERE id=?");
                    $stmtMvtMag= $db->prepare("INSERT INTO mouvements_magasin (produit_id,type,quantite,motif,utilisateur_id) VALUES (?,'entrée',?,?,?)");
                    $stmtCatIns= $db->prepare("INSERT INTO categories (nom,couleur) VALUES (?,?)");
                    $ligne = 1;
                    $db->beginTransaction();
                    try {
                    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                        $ligne++;
                        $nonEmpty = false;
                        foreach ($row as $c) if (trim((string)$c) !== '') { $nonEmpty = true; break; }
                        if (!$nonEmpty) continue;
                        $cell = function (int $i) use ($row): string {
                            return isset($row[$i]) ? magasin_csv_cell((string)$row[$i]) : '';
                        };
                        $nom = $cell($colMap['nom']);
                        if ($nom === '') { $errors[] = "Ligne $ligne : nom vide, ligne ignorée."; continue; }
                        $reference = $cell($colMap['reference'] ?? -1);
                        $reference = $reference !== '' ? $reference : null;
                        $unite = isset($colMap['unite']) ? $cell($colMap['unite']) : '';
                        $unite = $unite !== '' ? $unite : null;
                        $existingId = null;
                        if ($reference !== null) {
                            $stmtFind->execute([$reference]); $r = $stmtFind->fetch();
                            if ($r) $existingId = (int)$r['id'];
                        }
                        if ($existingId !== null && $mode === 'skip') { $ignores++; continue; }
                        $catId = null;
                        if (isset($colMap['categorie'])) {
                            $cn = $cell($colMap['categorie']);
                            if ($cn !== '') {
                                $stmtCat->execute([$cn]); $r = $stmtCat->fetch();
                                if ($r) {
                                    $catId = (int)$r['id'];
                                } else {
                                    $stmtCatIns->execute([$cn, $IMPORT_PALETTE[$importCatIdx % count($IMPORT_PALETTE)]]);
                                    $catId = (int)$db->lastInsertId();
                                    $importCatIdx++;
                                    $importCatsCrees[] = $cn;
                                }
                            }
                        }
                        $fournId = null;
                        if (isset($colMap['fournisseur'])) {
                            $fn = $cell($colMap['fournisseur']);
                            if ($fn !== '') {
                                $stmtFourn->execute([$fn]); $r = $stmtFourn->fetch();
                                if ($r) $fournId = (int)$r['id'];
                                else { $avert++; $warnings[] = "Ligne $ligne : fournisseur « $fn » introuvable → vide."; }
                            }
                        }
                        $stockCell = isset($colMap['stock']) ? $cell($colMap['stock']) : '';
                        $stock = $stockCell !== '' ? max(0, (int)magasin_csv_num($stockCell)) : 0;
                        $seuilCell = isset($colMap['seuil_alerte']) ? $cell($colMap['seuil_alerte']) : '';
                        $seuil = $seuilCell !== '' ? max(0, (int)magasin_csv_num($seuilCell)) : 10;
                        $paCell = isset($colMap['prix_achat']) ? $cell($colMap['prix_achat']) : '';
                        $pa = $paCell !== '' ? magasin_csv_num($paCell) : 0.0;
                        $pvCell = isset($colMap['prix_vente']) ? $cell($colMap['prix_vente']) : '';
                        $pv = $pvCell !== '' ? magasin_csv_num($pvCell) : 0.0;
                        $tvaCell = isset($colMap['tva']) ? $cell($colMap['tva']) : '';
                        $tva = $tvaCell !== '' ? magasin_csv_num($tvaCell) : 9.00;
                        $desc = isset($colMap['description']) ? $cell($colMap['description']) : '';
                        $exp = null;
                        if (isset($colMap['date_expiration'])) {
                            $dCell = $cell($colMap['date_expiration']);
                            if ($dCell !== '') {
                                $exp = magasin_csv_parse_date($dCell);
                                if ($exp === null) { $avert++; $warnings[] = "Ligne $ligne : date « $dCell » invalide → vide."; }
                            }
                        }
                        if ($existingId !== null) {
                            $stmtUpd->execute([$nom, $unite, $catId, $fournId, $desc, $seuil, $pa, $pv, $tva, $exp, $stock, $existingId]);
                            if ($stock > 0) $stmtMvtMag->execute([$existingId, $stock, 'Import CSV magasin — ravitaillement', $uid]);
                            $maj++;
                        } else {
                            $stmtIns->execute([$nom, $reference, $unite, $catId, $fournId, $desc, 0, $stock, $seuil, $pa, $pv, $tva, $exp]);
                            if ($stock > 0) {
                                $newId = (int)$db->lastInsertId();
                                $stmtMvtMag->execute([$newId, $stock, 'Import CSV magasin — création + stock initial', $uid]);
                            }
                            $ajoutes++;
                        }
                    }
                    $db->commit();
                    } catch (Throwable $e) {
                        $db->rollBack();
                        if (!defined('IS_PROD') || !IS_PROD) {
                            $errors[] = 'Erreur BDD — import annulé : ' . $e->getMessage();
                        } else {
                            error_log('PharmaCare import magasin: ' . $e->getMessage());
                            $errors[] = 'Erreur BDD — import annulé. Contactez un administrateur.';
                        }
                        $ajoutes = 0; $maj = 0;
                    }
                }
                fclose($fh);
            }
        }

        auditLog('magasin.import', "CSV ($mode) : $ajoutes ajoutés, $maj mis à jour, $ignores ignorés, $avert avert., " . count($importCatsCrees) . " catégories créées, " . count($errors) . " erreurs");

        layout_head('Import magasin', 'magasin');
        showFlash();
        ?>
        <div class="card" style="max-width:860px;margin:0 auto;">
          <div class="card-header">
            <div class="card-title">Résultat de l'importation</div>
            <a href="<?= url('magasin', ['action' => 'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Nouvel import</a>
          </div>
          <div class="flex gap-8" style="flex-wrap:wrap;margin:12px 0;">
            <span class="badge badge-green">Ajoutés : <?= $ajoutes ?></span>
            <span class="badge badge-blue">Mis à jour : <?= $maj ?></span>
            <span class="badge badge-gray">Ignorés : <?= $ignores ?></span>
            <span class="badge badge-gold">Avertissements : <?= $avert ?></span>
            <span class="badge badge-purple">Catégories créées : <?= count($importCatsCrees) ?></span>
            <span class="badge badge-red">Erreurs : <?= count($errors) ?></span>
          </div>
          <?php if ($importCatsCrees): ?>
            <div class="table-wrap"><table>
              <thead><tr><th>Catégories créées automatiquement</th></tr></thead>
              <tbody>
              <?php foreach (array_slice(array_unique($importCatsCrees), 0, 50) as $cn): ?>
                <tr><td class="text-sm"><?= e($cn) ?></td></tr>
              <?php endforeach; ?>
              <?php if (count(array_unique($importCatsCrees)) > 50): ?>
                <tr><td class="text-sm" style="color:var(--text3);">… et <?= count(array_unique($importCatsCrees)) - 50 ?> autres.</td></tr>
              <?php endif; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
          <?php if ($errors): ?>
            <div class="table-wrap"><table>
              <thead><tr><th>Erreurs</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($errors, 0, 50) as $err): ?>
                <tr><td class="text-sm" style="color:var(--text2);"><?= e($err) ?></td></tr>
              <?php endforeach; ?>
              <?php if (count($errors) > 50): ?>
                <tr><td class="text-sm" style="color:var(--text3);">… et <?= count($errors) - 50 ?> autres.</td></tr>
              <?php endif; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
          <?php if ($warnings): ?>
            <div class="table-wrap" style="margin-top:12px;"><table>
              <thead><tr><th>Avertissements</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($warnings, 0, 50) as $w): ?>
                <tr><td class="text-sm" style="color:var(--text2);"><?= e($w) ?></td></tr>
              <?php endforeach; ?>
              <?php if (count($warnings) > 50): ?>
                <tr><td class="text-sm" style="color:var(--text3);">… et <?= count($warnings) - 50 ?> autres.</td></tr>
              <?php endif; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
          <div class="modal-footer">
            <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" class="btn btn-ghost">Retour au magasin</a>
            <a href="<?= url('magasin', ['action' => 'import']) ?>" class="btn btn-primary"><?= icon('upload', 14) ?> Importer un autre fichier</a>
          </div>
        </div>
        <?php layout_foot();
        exit;
    }

    flash('Action inconnue.', 'error');
    header('Location: ' . url('magasin')); exit;
}

// ── Import CSV (GET) : formulaire ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'import') {
    if (!hasPermission('magasin.gerer')) {
        flash('Accès refusé.', 'error');
        header('Location: ' . url('magasin')); exit;
    }
    layout_head('Importer dans le magasin', 'magasin');
    showFlash();
    ?>
    <div class="card" style="max-width:820px;margin:0 auto;">
      <div class="card-header">
        <div class="card-title">Importer des articles (CSV) — Magasin</div>
        <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left', 14) ?> Retour</a>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="import">
        <div class="form-grid">
          <div class="form-group full">
            <label>Fichier CSV *</label>
            <input type="file" name="file" accept=".csv,text/csv" required>
            <small>UTF-8 de préférence. Délimiteur virgule ou point-virgule (auto). Une ligne d'en-tête est attendue.</small>
          </div>
          <div class="form-group full">
            <label>Doublons (référence déjà existante)</label>
            <label style="display:flex;gap:8px;align-items:center;font-weight:normal;margin:4px 0;">
              <input type="radio" name="doublons" value="skip" checked> Ignorer les doublons (défaut)
            </label>
            <label style="display:flex;gap:8px;align-items:center;font-weight:normal;margin:4px 0;">
              <input type="radio" name="doublons" value="update"> Mettre à jour les articles existants
            </label>
          </div>
          <div class="form-group full">
            <small>
              Colonnes reconnues : <strong>nom</strong>* (requis), reference, unite, categorie, fournisseur,
              stock, seuil_alerte, prix_achat, prix_vente, tva, date_expiration (jj/mm/aaaa), description.
              <strong>stock</strong> = quantité livrée au <strong>magasin</strong> (dépôt central).
              Mode « mettre à jour » : la quantité s'<em>ajoute</em> au stock magasin existant.
              Catégorie inconnue → créée automatiquement. Fournisseur inconnu → laissé vide.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('magasin', ['action' => 'import_template']) ?>" class="btn btn-ghost"><?= icon('download', 14) ?> Télécharger le modèle</a>
          <button type="submit" class="btn btn-primary"><?= icon('upload', 14) ?> Importer</button>
        </div>
      </form>
    </div>
    <?php layout_foot();
    exit;
}

// ════════════════════════════════════════════════════════════
// BON DE RAVITAILLEMENT (vue imprimable A4 — modèle MINSANTÉ)
// Accessible via ?bon=<id>. Rendu standalone (sans sidebar) + auto-impression.
// ════════════════════════════════════════════════════════════
if (isset($_GET['bon'])) {
    $bonId = (int)$_GET['bon'];
    $stT = $db->prepare("SELECT t.*, u.prenom, u.nom AS u_nom
                         FROM transferts_magasin t
                         LEFT JOIN utilisateurs u ON t.utilisateur_id = u.id
                         WHERE t.id = ?");
    $stT->execute([$bonId]);
    $bonT = $stT->fetch();

    if (!$bonT) {
        http_response_code(404);
        exit('Transfert introuvable.');
    }

    $stL = $db->prepare("SELECT tl.*, p.reference, p.prix_vente
                         FROM transfert_lignes tl
                         LEFT JOIN produits p ON p.id = tl.produit_id
                         WHERE tl.transfert_id = ? ORDER BY tl.id");
    $stL->execute([$bonId]);
    $bonLignes = $stL->fetchAll();

    // Pharmacie destinataire : priorité à pharmacie_id, sinon parsing de la note
    $bonPharmacie = '';
    if (!empty($bonT['pharmacie_id'])) {
        $stP = $db->prepare("SELECT nom FROM pharmacies WHERE id = ?");
        $stP->execute([$bonT['pharmacie_id']]);
        $bonPharmacie = $stP->fetchColumn();
    }
    if (!$bonPharmacie) {
        $parts = explode('→', $bonT['note'] ?? '');
        $bonPharmacie = count($parts) >= 2 ? trim(array_pop($parts)) : '';
    }
    // Motif = note sans le suffixe pharmacie
    $bonParts = explode('→', $bonT['note'] ?? '');
    $bonMotif = count($bonParts) >= 2 ? trim(implode('→', $bonParts)) : trim($bonT['note'] ?? '');

    $bonEtsNom    = getParam('app_nom', 'PharmaCare');
    $bonEtsAdr    = getParam('pharmacie_adresse', '');
    $bonEtsTel    = getParam('pharmacie_telephone', '');
    $bonEtsNif    = getParam('pharmacie_nif', '');
    $bonTotalQte  = 0;
    $bonTotalMont = 0.0;
    foreach ($bonLignes as $bl) {
        $bonTotalQte  += (int)$bl['quantite'];
        $bonTotalMont += (float)$bl['prix_vente'] * (int)$bl['quantite'];
    }
    $bonDate = date('d/m/Y', strtotime($bonT['created_at']));
    $bonHeure = date('H:i', strtotime($bonT['created_at']));

    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<title>Bon de ravitaillement <?= e($bonT['reference']) ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 12px; margin: 0; }
  .entete { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
  .pays { font-weight: 700; font-size: 13px; }
  .devise { font-style: italic; font-size: 11px; }
  .ministere { font-weight: 600; font-size: 12px; }
  .etablissement { font-weight: 700; font-size: 15px; }
  .titre-doc { text-align: center; font-size: 20px; font-weight: 700; margin: 22px 0 2px; text-transform: uppercase; letter-spacing: 2px; }
  .ref-doc { text-align: center; font-size: 12px; color: #475569; margin-bottom: 16px; }
  .infos { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 14px; font-size: 12px; }
  .infos .lbl { color: #64748b; display: inline-block; min-width: 130px; }
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
  <div class="toolbar"><button onclick="window.print()">🖨️ Imprimer le bon</button></div>

  <div class="entete">
    <div>
      <div class="pays">RÉPUBLIQUE DU CAMEROUN</div>
      <div class="devise">Paix – Travail – Patrie</div>
      <div class="ministere">MINISTÈRE DE LA SANTÉ PUBLIQUE</div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;">Dépôt central / Établissement pharmaceutique</div>
    </div>
    <div style="text-align:right;">
      <div class="etablissement"><?= e($bonEtsNom) ?></div>
      <?php if ($bonEtsAdr): ?><div style="font-size:11px;"><?= e($bonEtsAdr) ?></div><?php endif; ?>
      <?php if ($bonEtsTel): ?><div style="font-size:11px;">Tél : <?= e($bonEtsTel) ?></div><?php endif; ?>
      <?php if ($bonEtsNif): ?><div style="font-size:11px;">NIF : <?= e($bonEtsNif) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="titre-doc">Bon de Ravitaillement</div>
  <div class="ref-doc">Réf. <?= e($bonT['reference']) ?> &mdash; émis le <?= $bonDate ?> à <?= $bonHeure ?></div>

  <div class="infos">
    <div><span class="lbl">Dépôt émetteur :</span> <strong><?= e($bonEtsNom) ?> (Magasin central)</strong></div>
    <div><span class="lbl">Pharmacie destinataire :</span> <strong><?= e($bonPharmacie ?: '—') ?></strong></div>
    <div><span class="lbl">Magasinier / Opérateur :</span> <?= e(trim($bonT['prenom'] . ' ' . $bonT['u_nom'])) ?: '—' ?></div>
    <div><span class="lbl">Date du transfert :</span> <?= $bonDate ?> à <?= $bonHeure ?></div>
    <?php if ($bonMotif): ?><div style="grid-column:1/-1;"><span class="lbl">Motif / Observation :</span> <?= e($bonMotif) ?></div><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th class="center" style="width:5%;">N°</th>
        <th>Désignation</th>
        <th style="width:11%;">Réf. produit</th>
        <th class="center" style="width:11%;">Qté demandée</th>
        <th class="center" style="width:11%;">Qté livrée</th>
        <th class="right" style="width:12%;">P.U.</th>
        <th class="right" style="width:13%;">Montant</th>
        <th style="width:13%;">Observations</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($bonLignes as $bl):
        $pu = (float)($bl['prix_vente'] ?? 0);
        $mt = $pu * (int)$bl['quantite'];
      ?>
      <tr>
        <td class="center"><?= $i++ ?></td>
        <td><?= e($bl['produit_nom']) ?></td>
        <td><?= e($bl['reference'] ?? '—') ?></td>
        <td class="center"><?= (int)$bl['quantite'] ?></td>
        <td class="center"><?= (int)$bl['quantite'] ?></td>
        <td class="right"><?= $pu > 0 ? fmtInt((int) round($pu)) : '—' ?></td>
        <td class="right"><?= $pu > 0 ? fmtInt((int) round($mt)) : '—' ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$bonLignes): ?>
      <tr><td colspan="8" class="center" style="padding:14px;color:#94a3b8;">Aucune ligne</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="right">TOTAUX</td>
        <td class="center"><?= $bonTotalQte ?></td>
        <td class="center"><?= $bonTotalQte ?></td>
        <td></td>
        <td class="right"><?= $bonTotalMont > 0 ? fmtInt((int) round($bonTotalMont)) : '—' ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div><div class="role">Le Magasinier<br><small style="font-weight:400;color:#64748b">(émetteur)</small></div><div class="sig">Signature &amp; cachet</div></div>
    <div><div class="role">Le Pharmacien<br><small style="font-weight:400;color:#64748b">(vérification)</small></div><div class="sig">Signature &amp; cachet</div></div>
  </div>

  <div class="pied">Document généré électroniquement par <?= e($bonEtsNom) ?> le <?= date('d/m/Y à H:i') ?> — Bon de ravitaillement (modèle MINSANTÉ).</div>

  <script>
    window.onafterprint = function(){ window.location.href = <?= json_encode(url('magasin')) ?>; };
    window.onload = function(){ setTimeout(function(){ window.print(); }, 300); };
  </script>
</body></html>
<?php
    exit;
}

// ════════════════════════════════════════════════════════════
// Données pour les vues — chargées par onglet (ne plus tout charger
// sur chaque onglet : le stock est paginé, les modales passent en ajax).
// ════════════════════════════════════════════════════════════
require_once __DIR__ . '/../includes/pagination.php';

// Pharmacies actives : communes à stock (colonnes + modale), etat-date
// (périmètre) et l'endpoint ajax. Léger (quelques lignes).
$pharmacies = $db->query("SELECT id, nom FROM pharmacies WHERE actif=1 ORDER BY id")->fetchAll();

// ── Endpoint ajax : produits filtrés pour les modales de transfert /
// retour / ajustement (onglet stock). Évite d'embarquer tout le catalogue
// dans le HTML ; renvoie 50 résultats max + leurs stocks par pharmacie.
if (($_GET['ajax'] ?? '') === 'produits' && hasPermission('magasin.voir')) {
    $aq = trim($_GET['q'] ?? '');
    $aWhere = "p.actif = 1";
    $aParams = [];
    if ($aq !== '') {
        $qEsc = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $aq);
        $aWhere .= " AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')";
        $aParams[] = "%$qEsc%"; $aParams[] = "%$qEsc%";
    }
    $st = $db->prepare("SELECT p.id, p.nom, p.reference, p.stock, p.stock_magasin
                        FROM produits p WHERE $aWhere ORDER BY p.nom LIMIT 50");
    $st->execute($aParams);
    $rows = $st->fetchAll();
    $app = [];
    $aIds = array_map(function($r){ return (int)$r['id']; }, $rows);
    if ($aIds) {
        $phL = implode(',', array_fill(0, count($aIds), '?'));
        $stpp = $db->prepare("SELECT produit_id, pharmacie_id, stock FROM produit_pharmacie WHERE produit_id IN ($phL)");
        $stpp->execute($aIds);
        foreach ($stpp->fetchAll() as $r) {
            $app[(int)$r['produit_id']][(int)$r['pharmacie_id']] = (int)$r['stock'];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['produits' => $rows, 'pp' => $app, 'pharmacies' => $pharmacies], JSON_UNESCAPED_UNICODE);
    exit;
}

$ppMap = [];
$produits = [];
$produitsSelect = [];
$transferts = [];
$mouvements = [];
$trfLignes = [];
$stats = ['refs'=>0,'alerte'=>0,'total_mag'=>0,'total_ph'=>0];
$total = 0;
$page = 1;
$perPage = 25; // pagination limitée à 25 éléments par page (demande utilisateur)

// ── Stats générales (1 seule requête au lieu de 3, affichées sur tous les onglets) ──
$statsRow = $db->query("SELECT COUNT(*) AS refs, SUM(CASE WHEN stock_magasin <= seuil_magasin THEN 1 ELSE 0 END) AS alerte, COALESCE(SUM(stock_magasin),0) AS total_mag FROM produits WHERE actif=1")->fetch();
$totalPh = $db->query("SELECT COALESCE(SUM(pp.stock),0) FROM produit_pharmacie pp JOIN pharmacies ph ON ph.id = pp.pharmacie_id WHERE ph.actif = 1")->fetchColumn();
$stats = [
    'refs'      => (int)$statsRow['refs'],
    'alerte'    => (int)$statsRow['alerte'],
    'total_mag' => (int)$statsRow['total_mag'],
    'total_ph'  => (int)$totalPh,
];

if ($onglet === 'stock') {
    // ── Liste paginée + ppMap scopé aux produits de la page ──
    $q = trim($_GET['q'] ?? '');
    $where = "p.actif = 1";
    $params = [];
    // ── Export Excel (mêmes filtres) ──
    if (($_GET['export'] ?? '') === '1') {
        require_once __DIR__ . '/../includes/export_xlsx.php';
        $stX = $db->prepare("SELECT p.reference, p.nom, c.nom AS cat, p.stock_magasin, p.seuil_magasin, p.stock
                             FROM produits p
                             LEFT JOIN categories c ON p.categorie_id = c.id
                             WHERE $where
                             ORDER BY p.nom");
        $stX->execute($params);
        $rowsX = [];
        foreach ($stX->fetchAll() as $p) {
            $sm   = (int)$p['stock_magasin'];
            $etat = $sm <= 0 ? 'Rupture' : ($sm <= (int)$p['seuil_magasin'] ? 'Stock bas' : 'OK');
            $rowsX[] = [$p['reference'], $p['nom'], $p['cat'], $sm, (int)$p['seuil_magasin'], $etat];
        }
        export_xlsx_send('stock_magasin_' . date('Y-m-d'), 'Stock magasin',
            ['Référence', 'Nom', 'Catégorie', 'Stock magasin', 'Seuil magasin', 'État'], $rowsX);
    }
    if ($q !== '') {
        $qEsc = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $q);
        $where .= " AND (p.nom LIKE ? ESCAPE '\\\\' OR p.reference LIKE ? ESCAPE '\\\\')";
        $params[] = "%$qEsc%"; $params[] = "%$qEsc%";
    }
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM produits p WHERE $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = paginateOffset($page, $perPage);

    $st = $db->prepare("SELECT p.id, p.nom, p.reference, p.stock, p.stock_magasin, p.seuil_magasin,
                               c.nom AS cat
                        FROM produits p
                        LEFT JOIN categories c ON p.categorie_id = c.id
                        WHERE $where
                        ORDER BY p.nom
                        LIMIT $perPage OFFSET $offset");
    $st->execute($params);
    $produits = $st->fetchAll();

    // ppMap limité aux produits de la page courante
    $ids = array_map(function($p){ return (int)$p['id']; }, $produits);
    if ($ids) {
        $phL = implode(',', array_fill(0, count($ids), '?'));
        $stpp = $db->prepare("SELECT produit_id, pharmacie_id, stock FROM produit_pharmacie WHERE produit_id IN ($phL)");
        $stpp->execute($ids);
        foreach ($stpp->fetchAll() as $r) {
            $ppMap[(int)$r['produit_id']][(int)$r['pharmacie_id']] = (int)$r['stock'];
        }
    }
} elseif ($onglet === 'historique') {
    // Fenêtre glissante 90 jours par défaut (extensible via ?hist_jours) pour
    // garder COUNT + listes à coût constant quand mouvements_magasin grossit.
    $histJours = max(1, min(3650, (int)($_GET['hist_jours'] ?? 90)));
    $histDebut = date('Y-m-d 00:00:00', strtotime("-{$histJours} days"));
    $whereHist = "created_at >= " . $db->quote($histDebut);

    $perPageTrf = 25;
    $pageTrf    = max(1, (int)($_GET['page_trf'] ?? 1));
    $offsetTrf  = paginateOffset($pageTrf, $perPageTrf);

    $totalTrf = (int)$db->query("SELECT COUNT(*) FROM transferts_magasin WHERE $whereHist")->fetchColumn();

    // ── Transferts (sous-requête dérivée au lieu de 2 corrélées par ligne) ──
    $transferts = $db->prepare("
        SELECT t.*, u.prenom, u.nom AS u_nom,
               COALESCE(tl_stats.nb_lignes, 0) AS nb_lignes,
               COALESCE(tl_stats.total_qte, 0) AS total_qte
        FROM transferts_magasin t
        LEFT JOIN utilisateurs u ON t.utilisateur_id = u.id
        LEFT JOIN (
            SELECT transfert_id, COUNT(*) AS nb_lignes, SUM(quantite) AS total_qte
            FROM transfert_lignes GROUP BY transfert_id
        ) tl_stats ON tl_stats.transfert_id = t.id
        WHERE t.$whereHist
        ORDER BY t.created_at DESC
        LIMIT $perPageTrf OFFSET $offsetTrf
    ");
    $transferts->execute();
    $transferts = $transferts->fetchAll();

    $perPageMvt = 25;
    $pageMvt    = max(1, (int)($_GET['page_mvt'] ?? 1));
    $offsetMvt  = paginateOffset($pageMvt, $perPageMvt);

    $totalMvt = (int)$db->query("SELECT COUNT(*) FROM mouvements_magasin WHERE $whereHist")->fetchColumn();

    // Mouvements magasin récents
    $mouvements = $db->prepare("
        SELECT m.*, p.nom AS pnom, u.prenom, u.nom AS u_nom
        FROM mouvements_magasin m
        LEFT JOIN produits p ON m.produit_id = p.id
        LEFT JOIN utilisateurs u ON m.utilisateur_id = u.id
        WHERE $whereHist
        ORDER BY m.created_at DESC
        LIMIT $perPageMvt OFFSET $offsetMvt
    ");
    $mouvements->execute();
    $mouvements = $mouvements->fetchAll();

    // Lignes des transferts (détail dépliable)
    $trfIds = array_column($transferts, 'id');
    if ($trfIds) {
        $phL = implode(',', array_fill(0, count($trfIds), '?'));
        $stmtL = $db->prepare("SELECT * FROM transfert_lignes WHERE transfert_id IN ($phL) ORDER BY id");
        $stmtL->execute($trfIds);
        foreach ($stmtL->fetchAll() as $l) {
            $trfLignes[$l['transfert_id']][] = $l;
        }
    }
} elseif ($onglet === 'reception' && hasPermission('magasin.gerer')) {
    // Liste pour le <select> de réception + carte de scan : tout le catalogue,
    // colonnes minimales (référence incluse = code-barres scannable).
    $produitsSelect = $db->query("SELECT id, nom, reference, stock_magasin FROM produits WHERE actif=1 ORDER BY nom")->fetchAll();
} elseif ($onglet === 'etat-date') {
    // Reconstitution rétroactive : a besoin de TOUS les produits + ppMap.
    $produits = $db->query("
        SELECT p.id, p.nom, p.reference, p.stock_magasin, c.nom AS cat
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.actif = 1
        ORDER BY p.nom
    ")->fetchAll();
    // ppMap complet nécessaire uniquement si le scope cible une pharmacie ou
    // « toutes ». En scope=magasin, on évite le scan complet de produit_pharmacie.
    $ppMap = [];
    $edScope = $_GET['scope'] ?? 'magasin';
    if ($edScope !== 'magasin') {
        foreach ($db->query("SELECT produit_id, pharmacie_id, stock FROM produit_pharmacie")->fetchAll() as $r) {
            $ppMap[(int)$r['produit_id']][(int)$r['pharmacie_id']] = (int)$r['stock'];
        }
    }
}

layout_head('Magasin — dépôt central', 'magasin');
showFlash();
?>

<div class="stats-grid no-print" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('box',28) ?></div>
    <div class="stat-label">Références</div>
    <div class="stat-value c-blue"><?= fmtInt((int)$stats['refs']) ?></div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple,#9b59b6);opacity:.25;"><?= icon('truck',28) ?></div>
    <div class="stat-label">Total unités magasin</div>
    <div class="stat-value c-purple"><?= fmtInt((int)$stats['total_mag']) ?></div>
  </div>
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('pill',28) ?></div>
    <div class="stat-label">Total unités pharmacie</div>
    <div class="stat-value c-teal"><?= fmtInt((int)$stats['total_ph']) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('alert',28) ?></div>
    <div class="stat-label">Alertes magasin</div>
    <div class="stat-value c-gold"><?= fmtInt((int)$stats['alerte']) ?></div>
  </div>
</div>

<!-- Onglets -->
<div class="card no-print" style="margin-bottom:16px;">
  <div class="card-pad" style="padding:6px 12px;">
    <div class="flex gap-8" style="flex-wrap:wrap;">
      <a href="<?= url('magasin', ['onglet'=>'stock']) ?>"      class="btn btn-sm <?= $onglet==='stock'?'btn-primary':'btn-ghost' ?>"><?= icon('box',14) ?> Stock magasin</a>
      <?php if (hasPermission('magasin.gerer')): ?>
      <a href="<?= url('magasin', ['onglet'=>'reception']) ?>"  class="btn btn-sm <?= $onglet==='reception'?'btn-primary':'btn-ghost' ?>"><?= icon('plus',14) ?> Réception / Ajustement</a>
      <?php endif; ?>
      <a href="<?= url('magasin', ['onglet'=>'historique']) ?>" class="btn btn-sm <?= $onglet==='historique'?'btn-primary':'btn-ghost' ?>"><?= icon('history',14) ?> Historique</a>
      <a href="<?= url('magasin', ['onglet'=>'etat-date']) ?>"  class="btn btn-sm <?= $onglet==='etat-date'?'btn-primary':'btn-ghost' ?>"><?= icon('calendar',14) ?> État à une date</a>
    </div>
  </div>
</div>

<?php if ($onglet === 'stock'): ?>
<!-- ═══ Onglet STOCK MAGASIN ═══════════════════════════════ -->
<div class="card no-print">
  <div class="card-header">
    <div class="card-title">Stock du dépôt central (magasin)</div>
    <a href="<?= url('magasin', ['onglet'=>'stock', 'export'=>'1', 'q'=>$q]) ?>" class="btn btn-ghost btn-sm" title="Exporter au format Excel (.xlsx)"><?= icon('download',14) ?> Exporter</a>
    <?php if (hasPermission('magasin.gerer')): ?>
    <a href="<?= url('magasin', ['action'=>'import']) ?>" class="btn btn-ghost btn-sm"><?= icon('upload',14) ?> Importer</a>
    <?php endif; ?>
    <div class="flex gap-8" style="flex-wrap:wrap;align-items:center;">
      <form method="GET" action="<?= url('magasin') ?>" style="display:flex;flex:1;min-width:300px;max-width:100%;">
        <input type="hidden" name="onglet" value="stock">
        <div class="search-box" style="flex:1;">
          <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
          <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Rechercher par nom ou référence...">
        </div>
      </form>
      <button type="button" class="btn btn-ghost" id="btn-edit-sel" onclick="printSelectionMagasin()">
        <?= icon('report',16) ?> Éditer les quantités
      </button>
      <?php if (hasPermission('magasin.gerer')): ?>
      <button type="button" class="btn btn-primary" onclick="openTransfertModal()">
        <?= icon('truck',16) ?> Transfert vers pharmacie
      </button>
      <button type="button" class="btn btn-ghost" onclick="openRetourModal()">
        <?= icon('refresh',16) ?> Retour vers magasin
      </button>
      <button type="button" class="btn btn-ghost" onclick="openAjustLotModal()">
        <?= icon('edit',16) ?> Ajustement en lot
      </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table id="table-mag">
      <thead>
        <tr>
          <th style="width:32px;text-align:center;">#</th><th>Médicament</th><th>Référence</th><th>Catégorie</th>
          <th style="text-align:right;">Stock magasin</th>
          <th style="text-align:right;">Seuil mag.</th>
          <th style="text-align:center;color:var(--teal2);">Pharmacies</th>
          <th>État</th>
          <?php if (hasPermission('magasin.gerer')): ?><th style="text-align:right;">Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php $num = $offset + 1; foreach ($produits as $p):
          $alerte = (int)$p['stock_magasin'] <= (int)$p['seuil_magasin'];
          $totalPh = 0;
          foreach ($pharmacies as $ph) { $totalPh += (int)($ppMap[(int)$p['id']][(int)$ph['id']] ?? 0); }
        ?>
        <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>" data-pid="<?= (int)$p['id'] ?>"
            data-noml="<?= e($p['nom'] . ($p['reference'] ? ' (' . e($p['reference']) . ')' : '')) ?>"
            data-ref="<?= e($p['reference']) ?>" data-cat="<?= e($p['cat'] ?? '') ?>"
            data-stock="<?= (int)$p['stock_magasin'] ?>" data-totalph="<?= $totalPh ?>">
          <td style="text-align:center;color:var(--text3);font-size:12px;"><?= $num++ ?></td>
          <td class="td-name"><?= e($p['nom']) ?></td>
          <td class="text-sm td-mono"><?= $p['reference'] ? e($p['reference']) : '—' ?></td>
          <td class="text-sm"><?= e($p['cat'] ?? '—') ?></td>
          <td class="fw-mono text-right <?= $alerte ? 'c-gold' : '' ?>" style="text-align:right;<?= hasPermission('magasin.gerer') ? 'cursor:ns-resize;' : '' ?>"
              <?php if (hasPermission('magasin.gerer')): ?>ondblclick="startInlineEdit(this, <?= (int)$p['id'] ?>, <?= (int)$p['stock_magasin'] ?>)"
              title="Double-cliquez pour modifier la quantité"<?php endif; ?>><?= fmtInt((int)$p['stock_magasin']) ?></td>
          <td class="fw-mono text-sm" style="text-align:right;color:var(--text3);"><?= fmtInt((int)$p['seuil_magasin']) ?></td>
          <td style="text-align:center;">
            <button type="button" class="btn btn-ghost btn-sm" title="Voir la répartition par pharmacie" onclick="openStockPharmaModal(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['nom']), ENT_QUOTES) ?>)">
              <?= icon('eye',14) ?> <span class="fw-mono"><?= fmtInt($totalPh) ?></span>
            </button>
          </td>
          <td>
            <?php if ($alerte): ?>
              <span class="badge badge-gold"><?= (int)$p['stock_magasin'] === 0 ? 'Rupture mag.' : 'Stock bas' ?></span>
            <?php else: ?>
              <span class="badge badge-green">OK</span>
            <?php endif; ?>
          </td>
          <?php if (hasPermission('magasin.gerer')): ?>
          <td style="text-align:right;">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openTransfertModal(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['nom']), ENT_QUOTES) ?>)"><?= icon('truck',14) ?> Transférer</button>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$produits): ?>
        <tr><td colspan="<?= 7 + (hasPermission('magasin.gerer') ? 1 : 0) ?>">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('box',36) ?></div>
            <div>Aucun produit</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($page, $perPage, $total, ['onglet'=>'stock','q'=>$_GET['q'] ?? '','perPage'=>$perPage]) ?>
</div>

<!-- ═══ ÉDITION DES QUANTITÉS (sélection, impression A4) ═══ -->
<div id="print-mag-sel" class="print-only">
  <div style="text-align:center;margin-bottom:8px;">
    <div style="font-size:18px;font-weight:700;"><?= e(getParam('app_nom', 'PharmaCare')) ?> — État des quantités magasin</div>
    <div style="font-size:12px;color:#555;">Édité le <?= date('d/m/Y à H:i') ?> — dépôt central (magasin)</div>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
      <tr>
        <th style="border:1px solid #999;padding:5px 7px;text-align:center;background:#eee;width:26px;">N°</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Article</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Réf.</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Catégorie</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Stock magasin</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Total pharmacies</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:center;background:#eee;">Qté constatée</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Observations</th>
      </tr>
    </thead>
    <tbody id="pm-tbody"></tbody>
    <tfoot>
      <tr>
        <td colspan="4" style="border:1px solid #999;padding:5px 7px;text-align:right;font-weight:600;background:#eee;">TOTAUX</td>
        <td id="pm-tot-mag" style="border:1px solid #999;padding:5px 7px;text-align:right;font-weight:600;background:#eee;"></td>
        <td id="pm-tot-ph" style="border:1px solid #999;padding:5px 7px;text-align:right;font-weight:600;background:#eee;"></td>
        <td style="border:1px solid #999;padding:5px 7px;background:#eee;"></td>
        <td style="border:1px solid #999;padding:5px 7px;background:#eee;"></td>
      </tr>
    </tfoot>
  </table>
  <div style="display:flex;justify-content:space-between;margin-top:40px;text-align:center;font-size:11px;">
    <div style="width:40%;"><div style="border-top:1px solid #555;padding-top:4px;">Le Magasinier</div></div>
    <div style="width:40%;"><div style="border-top:1px solid #555;padding-top:4px;">Le Pharmacien</div></div>
  </div>
</div>
<script>
// Édition des quantités : remplit le bloc print-only avec les articles
// affichés (page courante, filtre de recherche inclus) puis imprime.
function magEscSel(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
function printSelectionMagasin() {
  var rows = document.querySelectorAll('#table-mag tbody tr[data-pid]');
  if (!rows.length) { alert('Aucun article à éditer.'); return; }
  var tbody = document.getElementById('pm-tbody');
  var totMag = 0, totPh = 0, html = '';
  rows.forEach(function(r, i) {
    var stock = parseInt(r.getAttribute('data-stock'), 10) || 0;
    var totph = parseInt(r.getAttribute('data-totalph'), 10) || 0;
    totMag += stock; totPh += totph;
    html += '<tr>'
      + '<td style="border:1px solid #999;padding:5px 7px;text-align:center;">' + (i + 1) + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;">' + magEscSel(r.getAttribute('data-noml')) + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;">' + magEscSel(r.getAttribute('data-ref')) + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;">' + magEscSel(r.getAttribute('data-cat')) + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;text-align:right;">' + stock + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;text-align:right;">' + totph + '</td>'
      + '<td style="border:1px solid #999;padding:5px 7px;"></td>'
      + '<td style="border:1px solid #999;padding:5px 7px;"></td>'
      + '</tr>';
  });
  tbody.innerHTML = html;
  document.getElementById('pm-tot-mag').textContent = totMag;
  document.getElementById('pm-tot-ph').textContent = totPh;
  window.print();
}
// ── Édition inline de la quantité (double-clic sur la ligne) ──
// La cellule « Stock magasin » devient un champ de saisie ; Entrée valide
// (POST action=ajuster, valeur cible absolue), Échap annule.
var magInlineForm = null;
var MAG_CSRF = <?= json_encode(csrf()) ?>;
var MAG_APP_URL = <?= json_encode(APP_URL) ?>;
function startInlineEdit(td, pid, current) {
  // Un seul champ actif à la fois : valider/annuler l'édition en cours
  cancelInlineEdit(true);
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = MAG_APP_URL + '/magasin?onglet=stock';
  form.style.display = 'inline';
  form.innerHTML =
    '<input type="hidden" name="csrf" value="' + MAG_CSRF + '">' +
    '<input type="hidden" name="action" value="ajuster">' +
    '<input type="hidden" name="produit_id" value="' + pid + '">' +
    '<input type="number" name="quantite" min="0" value="' + current + '" style="width:84px;text-align:right;font-family:var(--font-mono,monospace);padding:2px 6px;" ' +
    'onkeydown="if(event.key===\'Enter\'){event.preventDefault();this.form.submit();}' +
    'else if(event.key===\'Escape\'){event.preventDefault();cancelInlineEdit();}">';
  td.innerHTML = '';
  td.appendChild(form);
  td.ondblclick = null;
  magInlineForm = { td: td, pid: pid, current: current };
  var inp = form.querySelector('input[name=quantite]');
  inp.focus(); inp.select();
}
function cancelInlineEdit() {
  if (!magInlineForm) return;
  var f = magInlineForm;
  magInlineForm = null;
  f.td.innerHTML = fmtIntStr(f.current);
  f.td.ondblclick = function(){ startInlineEdit(f.td, f.pid, f.current); };
  // Remove cursor change until re-render
  f.td.style.cursor = 'ns-resize';
}
function fmtIntStr(n) {
  try { return fmtInt(parseInt(n, 10)); } catch (err) { return String(n); }
}
</script>

<!-- ═══ Modale RÉPARTITION PAR PHARMACIE (produit) ═══ -->
<div class="modal-overlay" id="modal-stock-pharma">
  <div class="modal" style="width:560px;max-width:94vw;">
    <div class="modal-header" style="padding:18px 24px;">
      <div class="modal-title"><?= icon('building',16) ?> <span id="sp-title">Répartition par pharmacie</span></div>
      <button class="modal-close" onclick="closeModal('modal-stock-pharma')">✕</button>
    </div>
    <div class="card-pad" style="padding:18px 24px;">
      <div id="sp-summary" class="text-sm" style="color:var(--text3);margin-bottom:14px;"></div>
      <div class="table-wrap" style="max-height:380px;overflow-y:auto;">
        <table id="sp-table">
          <thead>
            <tr>
              <th>Pharmacie</th>
              <th style="text-align:right;">Stock</th>
              <th style="text-align:center;">État</th>
            </tr>
          </thead>
          <tbody id="sp-tbody"></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer" style="padding:14px 24px;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('modal-stock-pharma')">Fermer</button>
    </div>
  </div>
</div>
<script>
// Répartition par pharmacie d'un produit — ppMap + liste des pharmacies
// partagés avec les modales de transfert (magasin.gerer) ci-dessous.
var MAG_ppMap = <?= json_encode($ppMap, JSON_UNESCAPED_UNICODE) ?>;
var MAG_pharmacies = <?= json_encode(array_values($pharmacies), JSON_UNESCAPED_UNICODE) ?>;

function openStockPharmaModal(pid, nom) {
  pid = parseInt(pid, 10) || 0;
  var title   = document.getElementById('sp-title');
  var tbody   = document.getElementById('sp-tbody');
  var summary = document.getElementById('sp-summary');
  title.textContent = 'Répartition — ' + nom;
  function esc(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  var stocks = (MAG_ppMap && MAG_ppMap[pid]) ? MAG_ppMap[pid] : {};
  var html = '', total = 0;
  for (var i = 0; i < MAG_pharmacies.length; i++) {
    var ph = MAG_pharmacies[i];
    var s = (stocks[ph.id] !== undefined) ? parseInt(stocks[ph.id], 10) : 0;
    total += s;
    var etat = s <= 0
      ? '<span class="badge badge-red">Rupture</span>'
      : '<span class="badge badge-green">OK</span>';
    html += '<tr>'
      + '<td>' + esc(ph.nom) + '</td>'
      + '<td class="fw-mono" style="text-align:right;' + (s <= 0 ? 'color:var(--text3);' : '') + '">' + s + '</td>'
      + '<td style="text-align:center;">' + etat + '</td>'
      + '</tr>';
  }
  if (!MAG_pharmacies.length) {
    html = '<tr><td colspan="3"><div class="empty">Aucune pharmacie active.</div></td></tr>';
  }
  tbody.innerHTML = html;
  summary.textContent = MAG_pharmacies.length + ' pharmacie(s) — ' + total + ' unité(s) au total';
  openModal('modal-stock-pharma');
}
</script>

<?php if (hasPermission('magasin.gerer')): ?>
<!-- ═══ Modale TRANSFERT VERS PHARMACIE (multi-sélection) ═══ -->
<div class="modal-overlay" id="modal-transfert">
  <div class="modal" style="width:920px;max-width:94vw;">
    <div class="modal-header" style="padding:22px 28px;">
      <div class="modal-title"><?= icon('truck',16) ?> Transfert Magasin → Pharmacie</div>
      <button class="modal-close" onclick="closeModal('modal-transfert')">✕</button>
    </div>
    <form method="POST" action="?onglet=stock" id="trf-form" onsubmit="return submitTransfert(event)">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="transfert">
      <div class="card-pad" style="padding:20px 28px;">
        <div class="form-group" style="margin-bottom:16px;">
          <label>Pharmacie de destination *</label>
          <select name="pharmacie_id" id="trf-pharmacie" required onchange="updateDispoPharmacie()">
            <?php foreach ($pharmacies as $ph): ?>
            <option value="<?= (int)$ph['id'] ?>"><?= e($ph['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex-between" style="margin-bottom:14px;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div class="form-group" style="margin:0;flex:0 0 auto;">
            <label>Quantité globale <span class="text-sm" style="color:var(--text3);">(remplit les lignes cochées)</span></label>
            <div class="flex gap-8" style="align-items:center;">
              <input type="number" id="trf-qte-global" min="1" placeholder="ex: 50" style="width:130px;text-align:right;font-family:'DM Mono',monospace;" onkeydown="if(event.key==='Enter'){event.preventDefault();applyTrfGlobalQte();}">
              <button type="button" class="btn btn-ghost btn-sm" onclick="applyTrfGlobalQte()"><?= icon('check',14) ?> Appliquer à la sélection</button>
            </div>
          </div>
          <div id="trf-global-msg" class="text-sm" style="color:var(--text3);"></div>
        </div>
        <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:220px;">
            <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
            <input type="text" id="trf-search" placeholder="Rechercher un produit..." oninput="onTrfSearchInput()">
          </div>
          <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="trf-select-all" onchange="toggleAllTrf(this.checked)">
            <span>Tout sélectionner</span>
          </label>
        </div>
        <style>
          #trf-table th{padding:12px 14px;}
          #trf-table td{padding:11px 14px;}
          #trf-table tbody tr:hover{background:var(--glass);}
          #trf-table .trf-qte{padding:7px 10px;}
        </style>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
          <table id="trf-table">
            <thead>
              <tr>
                <th style="width:42px;"></th><th>Médicament</th>
                <th style="text-align:right;">Dispo magasin</th>
                <th style="text-align:right;">Dispo pharmacie</th>
                <th style="text-align:right;">Qté à transférer</th>
              </tr>
            </thead>
            <tbody id="trf-tbody">
              <tr><td colspan="5"><div class="empty" id="trf-placeholder">Tapez pour rechercher un produit…</div></td></tr>
            </tbody>
          </table>
        </div>
        <div id="trf-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
        <div class="form-group" style="margin-top:14px;">
          <label>Note (optionnel)</label>
          <input type="text" name="note" placeholder="Motif du transfert..." style="width:100%;">
        </div>
      </div>
      <div class="modal-footer" style="padding:16px 28px;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-transfert')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('truck',14) ?> Valider le transfert</button>
      </div>
    </form>
  </div>
</div>
<script>
var ppMap = <?= json_encode($ppMap, JSON_UNESCAPED_UNICODE) ?>;
var trfPharmacies = <?= json_encode(array_values($pharmacies), JSON_UNESCAPED_UNICODE) ?>;

// Échappement HTML pour les cellules construites en JS (défense XSS).
function magEsc(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

// ── Chargement ajax de la liste des produits (endpoint ?ajax=produits) ──
// La modale ne contient aucune ligne au chargement de la page : on ne charge
// que les produits correspondant à la recherche (50 max), pour ne pas embarquer
// tout le catalogue dans le HTML.
var trfSearchTimer = null;
var trfPendingPid = 0;

function onTrfSearchInput() {
  clearTimeout(trfSearchTimer);
  var q = document.getElementById('trf-search').value;
  trfSearchTimer = setTimeout(function(){ loadTrfProducts(q); }, 250);
}

function loadTrfProducts(q) {
  var tbody = document.getElementById('trf-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Chargement…</div></td></tr>';
  var url = (window.APP_URL || '') + '/modules/magasin.php?ajax=produits&q=' + encodeURIComponent(q || '');
  fetch(url, { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
      // Fusionner les stocks par pharmacie reçus dans ppMap (utilisé par
      // updateDispoPharmacie()) — les produits chargés peuvent être hors page.
      if (data && data.pp) {
        for (var pid in data.pp) {
          ppMap[pid] = ppMap[pid] || {};
          for (var phid in data.pp[pid]) { ppMap[pid][phid] = data.pp[pid][phid]; }
        }
      }
      buildTrfRows((data && data.produits) ? data.produits : []);
    })
    .catch(function(){
      tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Erreur de chargement. Réessayez.</div></td></tr>';
    });
}

function buildTrfRows(produits) {
  var tbody = document.getElementById('trf-tbody');
  if (!produits.length) {
    tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Aucun produit trouvé.</div></td></tr>';
    document.getElementById('trf-select-all').checked = false;
    updateTrfSummary();
    return;
  }
  var html = '';
  for (var i = 0; i < produits.length; i++) {
    var p = produits[i];
    var pid = parseInt(p.id, 10);
    var dispo = parseInt(p.stock_magasin, 10) || 0;
    var disabled = dispo <= 0 ? 'disabled' : '';
    var refHtml = p.reference ? '<div class="text-sm td-mono" style="color:var(--text3);">' + magEsc(p.reference) + '</div>' : '';
    html += '<tr data-nom="' + magEsc((p.nom + ' ' + (p.reference || '')).toLowerCase()) + '" data-pid="' + pid + '">'
      + '<td style="text-align:center;"><input type="checkbox" class="trf-check" data-pid="' + pid + '" data-dispo="' + dispo + '" ' + disabled + ' onchange="onTrfCheck(this)"></td>'
      + '<td class="td-name">' + magEsc(p.nom) + refHtml + '</td>'
      + '<td class="fw-mono text-right" style="text-align:right;' + (dispo <= 0 ? 'color:var(--text3);' : '') + '">' + dispo + '</td>'
      + '<td class="fw-mono trf-dispo-ph" data-pid="' + pid + '" style="text-align:right;color:var(--text3);">0</td>'
      + '<td style="text-align:right;"><input type="number" class="trf-qte" data-pid="' + pid + '" data-dispo="' + dispo + '" min="1" max="' + Math.max(1, dispo) + '" value="1" disabled style="width:80px;text-align:right;" oninput="onTrfQte(this)"></td>'
      + '</tr>';
  }
  tbody.innerHTML = html;
  document.getElementById('trf-select-all').checked = false;
  updateDispoPharmacie();
  // pré-cocher le produit demandé (bouton « Transférer » d'une ligne)
  if (trfPendingPid) {
    var cb = document.querySelector('#trf-table .trf-check[data-pid="' + trfPendingPid + '"]');
    if (cb && !cb.disabled) {
      cb.checked = true; onTrfCheck(cb);
      var row = cb.closest('tr'); if (row) row.scrollIntoView({block:'center'});
    }
    trfPendingPid = 0;
  }
  updateTrfSummary();
}

function updateDispoPharmacie() {
  var sel = document.getElementById('trf-pharmacie');
  if (!sel) return;
  var phId = parseInt(sel.value, 10) || 0;
  document.querySelectorAll('#trf-table .trf-dispo-ph').forEach(function(td){
    var pid = parseInt(td.getAttribute('data-pid'), 10);
    var stock = (ppMap[pid] && ppMap[pid][phId] !== undefined) ? ppMap[pid][phId] : 0;
    td.textContent = stock;
  });
}

function openTransfertModal(pid, nom) {
  // réinitialiser la sélection
  document.querySelectorAll('#trf-table .trf-check').forEach(function(c){ c.checked = false; });
  document.querySelectorAll('#trf-table .trf-qte').forEach(function(q){ q.value = '1'; q.disabled = true; q.style.borderColor = ''; });
  document.getElementById('trf-select-all').checked = false;
  var g = document.getElementById('trf-qte-global'); if (g) g.value = '';
  var gm = document.getElementById('trf-global-msg'); if (gm) gm.textContent = '';
  // pharmacie par défaut = la première (souvent la principale, id=1)
  var sel = document.getElementById('trf-pharmacie');
  if (sel && sel.options.length) sel.selectedIndex = 0;
  updateDispoPharmacie();
  // (Re)charger la liste via ajax — pré-remplir la recherche avec le nom du
  // produit demandé pour garantir sa présence dans les résultats.
  var search = document.getElementById('trf-search');
  trfPendingPid = parseInt(pid, 10) || 0;
  if (search) search.value = nom || '';
  loadTrfProducts(nom || '');
  openModal('modal-transfert');
}

function onTrfCheck(cb) {
  var qte = cb.closest('tr').querySelector('.trf-qte');
  if (qte) {
    qte.disabled = !cb.checked;
    if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); onTrfQte(qte); }
    else qte.style.borderColor = '';
  }
  updateTrfSummary();
}

function onTrfQte(inp) {
  var dispo = parseInt(inp.getAttribute('data-dispo'), 10);
  var q = parseInt(inp.value, 10) || 0;
  if (q > dispo) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Dépasse le stock disponible'); }
  else if (q <= 0) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Quantité invalide'); }
  else { inp.style.borderColor = ''; inp.setCustomValidity(''); }
  updateTrfSummary();
}

// Quantité globale : remplit toutes les lignes cochées avec la même quantité,
// clampée au stock dispo de chaque ligne. O(N) sur les lignes cochées.
function applyTrfGlobalQte() {
  var inp = document.getElementById('trf-qte-global');
  var gm = document.getElementById('trf-global-msg');
  var q = parseInt(inp.value, 10);
  if (isNaN(q) || q <= 0) {
    if (gm) { gm.textContent = 'Saisissez une quantité globale valide (> 0).'; gm.style.color = 'var(--gold)'; }
    inp.focus();
    return;
  }
  var checks = document.querySelectorAll('#trf-table .trf-check:checked');
  if (checks.length === 0) {
    if (gm) { gm.textContent = 'Cochez d\'abord les articles à remplir.'; gm.style.color = 'var(--gold)'; }
    return;
  }
  var clamped = 0;
  checks.forEach(function(c){
    var row = c.closest('tr');
    var qte = row ? row.querySelector('.trf-qte') : null;
    if (!qte) return;
    var dispo = parseInt(c.getAttribute('data-dispo'), 10);
    var v = Math.min(q, dispo);
    qte.value = v;
    onTrfQte(qte);          // re-valide + updateTrfSummary()
    if (v < q) clamped++;
  });
  if (gm) {
    gm.textContent = 'Appliqué à ' + checks.length + ' ligne(s)' +
      (clamped > 0 ? ' — ' + clamped + ' limitée(s) au stock disponible.' : '.');
    gm.style.color = clamped > 0 ? 'var(--gold)' : 'var(--teal2)';
  }
}

// Resume en UNE seule passe sur les lignes (O(N)) — fini le querySelector par case.
function updateTrfSummary() {
  var rows = document.querySelectorAll('#trf-table tbody tr');
  var total = 0, bad = 0, count = 0;
  for (var i = 0; i < rows.length; i++) {
    var cb = rows[i].querySelector('.trf-check');
    if (!cb || !cb.checked) continue;
    var qte = rows[i].querySelector('.trf-qte');
    var q = parseInt(qte.value, 10) || 0;
    total += q; count++;
    if (q <= 0 || q > parseInt(cb.getAttribute('data-dispo'), 10)) bad++;
  }
  var s = document.getElementById('trf-summary');
  s.textContent = count + ' produit(s) sélectionné(s) — ' + total + ' unité(s)';
  s.style.color = bad > 0 ? 'var(--red)' : 'var(--text3)';
}

// Tout sélectionner : O(N) — modifications en place + un seul recalcu du résumé.
function toggleAllTrf(checked) {
  var rows = document.querySelectorAll('#trf-table tbody tr');
  for (var i = 0; i < rows.length; i++) {
    var cb = rows[i].querySelector('.trf-check');
    if (!cb || cb.disabled) continue;
    if (cb.checked === checked) continue;
    cb.checked = checked;
    var qte = rows[i].querySelector('.trf-qte');
    if (qte) {
      qte.disabled = !checked;
      if (checked) { if (!qte.value) qte.value = '1'; }
      else qte.style.borderColor = '';
    }
  }
  updateTrfSummary();
}

function submitTransfert(e) {
  e.preventDefault();
  var form = document.getElementById('trf-form');
  var checks = document.querySelectorAll('#trf-table .trf-check:checked');
  if (checks.length === 0) { alert('Sélectionnez au moins un produit à transférer.'); return false; }
  var bad = false;
  checks.forEach(function(c){
    var pid = c.getAttribute('data-pid');
    var dispo = parseInt(c.getAttribute('data-dispo'), 10);
    var qte = document.querySelector('#trf-table .trf-qte[data-pid="' + pid + '"]');
    var q = parseInt(qte.value, 10) || 0;
    if (q <= 0 || q > dispo) bad = true;
    else {
      // construire les champs envoyés au serveur (alignés produit_id[] / quantite[])
      var h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'produit_id[]'; h1.value = pid; form.appendChild(h1);
      var h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'quantite[]'; h2.value = q; form.appendChild(h2);
    }
  });
  if (bad) { alert('Une ou plusieurs quantités sont invalides ou dépassent le stock disponible.'); return false; }
  var sel = document.getElementById('trf-pharmacie');
  var phNom = sel && sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : 'la pharmacie';
  if (!confirm('Confirmer le transfert de ' + checks.length + ' produit(s) vers « ' + phNom + ' » ?')) return false;
  form.submit();
  return false;
}
</script>
<?php endif; ?>

<?php if (hasPermission('magasin.gerer')): ?>
<!-- ═══ Modale RETOUR PHARMACIE → MAGASIN (multi-sélection) ═══ -->
<div class="modal-overlay" id="modal-retour">
  <div class="modal" style="width:920px;max-width:94vw;">
    <div class="modal-header" style="padding:22px 28px;">
      <div class="modal-title"><?= icon('refresh',16) ?> Retour Pharmacie → Magasin</div>
      <button class="modal-close" onclick="closeModal('modal-retour')">✕</button>
    </div>
    <form method="POST" action="?onglet=stock" id="ret-form" onsubmit="return submitRetour(event)">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="retour">
      <div class="card-pad" style="padding:20px 28px;">
        <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:220px;">
            <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
            <input type="text" id="ret-search" placeholder="Filtrer les produits..." oninput="filterRetList()">
          </div>
          <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="ret-select-all" onchange="toggleAllRet(this.checked)">
            <span>Tout sélectionner</span>
          </label>
        </div>
        <style>
          #ret-table th{padding:12px 14px;}
          #ret-table td{padding:11px 14px;}
          #ret-table tbody tr:hover{background:var(--glass);}
          #ret-table .ret-qte{padding:7px 10px;}
        </style>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
          <table id="ret-table">
            <thead>
              <tr>
                <th style="width:42px;"></th><th>Médicament</th>
                <th style="text-align:right;">Stock pharmacie</th>
                <th style="text-align:right;">Qté à retourner</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produits as $p):
                $dispo = (int)$p['stock'];
              ?>
              <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>" data-pid="<?= (int)$p['id'] ?>">
                <td style="text-align:center;">
                  <input type="checkbox" class="ret-check" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" onchange="onRetCheck(this)" <?= $dispo <= 0 ? 'disabled' : '' ?>>
                </td>
                <td class="td-name"><?= e($p['nom']) ?>
                  <?php if ($p['reference']): ?><div class="text-sm td-mono" style="color:var(--text3);"><?= e($p['reference']) ?></div><?php endif; ?>
                </td>
                <td class="fw-mono text-right" style="text-align:right;<?= $dispo <= 0 ? 'color:var(--text3);' : '' ?>"><?= fmtInt($dispo) ?></td>
                <td style="text-align:right;">
                  <input type="number" class="ret-qte" data-pid="<?= (int)$p['id'] ?>" data-dispo="<?= $dispo ?>" min="1" max="<?= max(1, $dispo) ?>" value="1" disabled style="width:80px;text-align:right;" oninput="onRetQte(this)">
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$produits): ?>
              <tr><td colspan="4"><div class="empty">Aucun produit</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="ret-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
        <div class="form-group" style="margin-top:14px;">
          <label>Note (optionnel)</label>
          <input type="text" name="note" placeholder="Motif du retour..." style="width:100%;">
        </div>
      </div>
      <div class="modal-footer" style="padding:16px 28px;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-retour')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('refresh',14) ?> Valider le retour</button>
      </div>
    </form>
  </div>
</div>
<script>
function openRetourModal(pid) {
  document.querySelectorAll('#ret-table .ret-check').forEach(function(c){ c.checked = false; });
  document.querySelectorAll('#ret-table .ret-qte').forEach(function(q){ q.value = '1'; q.disabled = true; q.style.borderColor = ''; });
  document.getElementById('ret-select-all').checked = false;
  if (pid) {
    var cb = document.querySelector('#ret-table .ret-check[data-pid="' + pid + '"]');
    if (cb && !cb.disabled) {
      cb.checked = true; onRetCheck(cb);
      var row = cb.closest('tr'); if (row) row.scrollIntoView({block:'center'});
    }
  }
  updateRetSummary();
  openModal('modal-retour');
}

function onRetCheck(cb) {
  var qte = cb.closest('tr').querySelector('.ret-qte');
  if (qte) {
    qte.disabled = !cb.checked;
    if (cb.checked) { if (!qte.value) qte.value = '1'; qte.focus(); onRetQte(qte); }
    else qte.style.borderColor = '';
  }
  updateRetSummary();
}

function onRetQte(inp) {
  var dispo = parseInt(inp.getAttribute('data-dispo'), 10);
  var q = parseInt(inp.value, 10) || 0;
  if (q > dispo) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Dépasse le stock pharmacie'); }
  else if (q <= 0) { inp.style.borderColor = 'var(--red)'; inp.setCustomValidity('Quantité invalide'); }
  else { inp.style.borderColor = ''; inp.setCustomValidity(''); }
  updateRetSummary();
}

// Resume en UNE seule passe sur les lignes (O(N)).
function updateRetSummary() {
  var rows = document.querySelectorAll('#ret-table tbody tr');
  var total = 0, bad = 0, count = 0;
  for (var i = 0; i < rows.length; i++) {
    var cb = rows[i].querySelector('.ret-check');
    if (!cb || !cb.checked) continue;
    var qte = rows[i].querySelector('.ret-qte');
    var q = parseInt(qte.value, 10) || 0;
    total += q; count++;
    if (q <= 0 || q > parseInt(cb.getAttribute('data-dispo'), 10)) bad++;
  }
  var s = document.getElementById('ret-summary');
  s.textContent = count + ' produit(s) sélectionné(s) — ' + total + ' unité(s)';
  s.style.color = bad > 0 ? 'var(--red)' : 'var(--text3)';
}

// Tout sélectionner : O(N) — modifications en place + un seul recalcu du résumé.
function toggleAllRet(checked) {
  var rows = document.querySelectorAll('#ret-table tbody tr');
  for (var i = 0; i < rows.length; i++) {
    var cb = rows[i].querySelector('.ret-check');
    if (!cb || cb.disabled) continue;
    if (cb.checked === checked) continue;
    cb.checked = checked;
    var qte = rows[i].querySelector('.ret-qte');
    if (qte) {
      qte.disabled = !checked;
      if (checked) { if (!qte.value) qte.value = '1'; }
      else qte.style.borderColor = '';
    }
  }
  updateRetSummary();
}

function filterRetList() {
  var q = document.getElementById('ret-search').value.toLowerCase();
  document.querySelectorAll('#ret-table tbody tr').forEach(function(r){
    r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
  });
}

function submitRetour(e) {
  e.preventDefault();
  var form = document.getElementById('ret-form');
  var checks = document.querySelectorAll('#ret-table .ret-check:checked');
  if (checks.length === 0) { alert('Sélectionnez au moins un produit à retourner.'); return false; }
  var bad = false;
  checks.forEach(function(c){
    var pid = c.getAttribute('data-pid');
    var dispo = parseInt(c.getAttribute('data-dispo'), 10);
    var qte = document.querySelector('#ret-table .ret-qte[data-pid="' + pid + '"]');
    var q = parseInt(qte.value, 10) || 0;
    if (q <= 0 || q > dispo) bad = true;
    else {
      var h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'produit_id[]'; h1.value = pid; form.appendChild(h1);
      var h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'quantite[]'; h2.value = q; form.appendChild(h2);
    }
  });
  if (bad) { alert('Une ou plusieurs quantités sont invalides ou dépassent le stock pharmacie.'); return false; }
  if (!confirm('Confirmer le retour de ' + checks.length + ' produit(s) vers le magasin ?')) return false;
  form.submit();
  return false;
}
</script>
<?php endif; ?>

<?php if (hasPermission('magasin.gerer')): ?>
<!-- ═══ Modale AJUSTEMENT EN LOT (quantité unique appliquée à la sélection) ═══ -->
<div class="modal-overlay" id="modal-ajust-lot">
  <div class="modal" style="width:920px;max-width:94vw;">
    <div class="modal-header" style="padding:22px 28px;">
      <div class="modal-title"><?= icon('edit',16) ?> Ajustement en lot du magasin</div>
      <button class="modal-close" onclick="closeModal('modal-ajust-lot')">✕</button>
    </div>
    <form method="POST" action="?onglet=stock" id="ajl-form" onsubmit="return submitAjustLot(event)">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="ajustement_lot">
      <div class="card-pad" style="padding:20px 28px;">
        <div class="text-sm" style="margin-bottom:14px;color:var(--text3);">
          Sélectionnez les articles puis saisissez <strong>une seule quantité</strong> appliquée à toute la sélection.
          Seuls les produits dont le stock change (≠ valeur actuelle) sont journalisés (mouvement <em>ajustement</em> signé).
        </div>
        <div class="form-grid" style="grid-template-columns:1fr 200px;gap:12px;margin-bottom:16px;align-items:flex-end;">
          <div class="form-group" style="margin:0;">
            <label>Motif (optionnel)</label>
            <input type="text" name="motif" placeholder="Inventaire, réception globale, correction…" style="width:100%;">
          </div>
          <div class="form-group" style="margin:0;">
            <label>Nouvelle quantité *</label>
            <input type="number" name="quantite" id="ajl-qte" min="0" placeholder="ex: 500" required style="width:100%;text-align:right;font-family:'DM Mono',monospace;" oninput="updateAjlSummary()">
          </div>
        </div>
        <div class="flex-between" style="margin-bottom:16px;gap:12px;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:220px;">
            <span style="color:var(--text3);display:flex;"><?= icon('search',14) ?></span>
            <input type="text" id="ajl-search" placeholder="Filtrer les produits..." oninput="filterAjlList()">
          </div>
          <label class="text-sm" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" id="ajl-select-all" onchange="toggleAllAjl(this.checked)">
            <span>Tout sélectionner</span>
          </label>
        </div>
        <style>
          #ajl-table th{padding:12px 14px;}
          #ajl-table td{padding:11px 14px;}
          #ajl-table tbody tr:hover{background:var(--glass);}
        </style>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto;">
          <table id="ajl-table">
            <thead>
              <tr>
                <th style="width:42px;"></th><th>Médicament</th>
                <th style="text-align:right;">Stock mag. actuel</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produits as $p):
                $actuel = (int)$p['stock_magasin'];
              ?>
              <tr data-nom="<?= e(strtolower($p['nom'] . ' ' . $p['reference'])) ?>">
                <td style="text-align:center;">
                  <input type="checkbox" class="ajl-check" name="produit_id[]" value="<?= (int)$p['id'] ?>" data-actuel="<?= $actuel ?>" onchange="updateAjlSummary()">
                </td>
                <td class="td-name"><?= e($p['nom']) ?>
                  <?php if ($p['reference']): ?><div class="text-sm td-mono" style="color:var(--text3);"><?= e($p['reference']) ?></div><?php endif; ?>
                </td>
                <td class="fw-mono text-right" style="text-align:right;color:var(--text3);"><?= fmtInt($actuel) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$produits): ?>
              <tr><td colspan="3"><div class="empty">Aucun produit</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="ajl-summary" class="text-sm" style="margin-top:14px;color:var(--text3);">0 produit sélectionné.</div>
      </div>
      <div class="modal-footer" style="padding:16px 28px;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-ajust-lot')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('check',14) ?> Valider l'ajustement</button>
      </div>
    </form>
  </div>
</div>
<script>
function openAjustLotModal() {
  document.querySelectorAll('#ajl-table .ajl-check').forEach(function(c){ c.checked = false; });
  document.getElementById('ajl-select-all').checked = false;
  var q = document.getElementById('ajl-qte'); if (q) q.value = '';
  updateAjlSummary();
  openModal('modal-ajust-lot');
}

// Résumé O(N) : compte les cochés + ceux qui changeront réellement à la qté saisie.
function updateAjlSummary() {
  var rows = document.querySelectorAll('#ajl-table tbody tr');
  var count = 0, modif = 0;
  var qInput = document.getElementById('ajl-qte');
  var q = qInput ? parseInt(qInput.value, 10) : NaN;
  var qValid = !isNaN(q) && q >= 0;
  for (var i = 0; i < rows.length; i++) {
    var cb = rows[i].querySelector('.ajl-check');
    if (!cb || !cb.checked) continue;
    count++;
    if (!qValid) continue;
    var actuel = parseInt(cb.getAttribute('data-actuel'), 10);
    if (q !== actuel) modif++;
  }
  var s = document.getElementById('ajl-summary');
  if (count === 0) { s.textContent = '0 produit sélectionné.'; s.style.color = 'var(--text3)'; return; }
  if (!qValid) { s.textContent = count + ' produit(s) sélectionné(s) — saisir une quantité.'; s.style.color = 'var(--gold)'; return; }
  s.textContent = count + ' produit(s) sélectionné(s) — ' + modif + ' seront mis à ' + q + ' unité(s).';
  s.style.color = 'var(--text3)';
}

// Tout sélectionner : O(N) — uniquement les lignes visibles (non filtrées).
function toggleAllAjl(checked) {
  var rows = document.querySelectorAll('#ajl-table tbody tr');
  for (var i = 0; i < rows.length; i++) {
    if (rows[i].style.display === 'none') continue;
    var cb = rows[i].querySelector('.ajl-check');
    if (cb && cb.checked !== checked) cb.checked = checked;
  }
  updateAjlSummary();
}

function filterAjlList() {
  var q = document.getElementById('ajl-search').value.toLowerCase();
  document.querySelectorAll('#ajl-table tbody tr').forEach(function(r){
    r.style.display = r.getAttribute('data-nom').indexOf(q) > -1 ? '' : 'none';
  });
}

function submitAjustLot(e) {
  e.preventDefault();
  var form = document.getElementById('ajl-form');
  var checks = document.querySelectorAll('#ajl-table .ajl-check:checked');
  if (checks.length === 0) { alert('Sélectionnez au moins un produit à ajuster.'); return false; }
  var qInput = document.getElementById('ajl-qte');
  var q = parseInt(qInput.value, 10);
  if (isNaN(q) || q < 0) { alert('Saisissez une quantité valide (≥ 0).'); qInput.focus(); return false; }
  if (!confirm('Confirmer la remise à ' + q + ' unités pour ' + checks.length + ' produit(s) du magasin ?')) return false;
  // produit_id[] est déjà sérialisé via les checkboxes ; quantite est un champ unique
  form.submit();
  return false;
}
</script>
<?php endif; ?>

<?php elseif ($onglet === 'reception' && hasPermission('magasin.gerer')): ?>
<?php
// Carte de scan : référentiel reference → produit (le code-barres = colonne reference)
$scanMap = [];
foreach ($produitsSelect as $ps) {
    $ref = trim((string)($ps['reference'] ?? ''));
    if ($ref !== '' && !isset($scanMap[strtolower($ref)])) {
        $scanMap[strtolower($ref)] = [
            'id'    => (int)$ps['id'],
            'nom'   => $ps['nom'],
            'stock' => (int)$ps['stock_magasin'],
            'ref'   => $ref,
        ];
    }
}
?>
<div class="card no-print" style="margin-bottom:16px;border-color:var(--teal2);">
  <div class="card-header">
    <div class="card-title">📡 Réception par scanner (codes-barres / QR)</div>
    <span class="text-sm">Chaque scan ajoute +1 — quantités modifiables avant enregistrement.</span>
  </div>
  <div class="card-pad">
    <div class="form-group">
      <label for="scan-input">Scanner ou taper le code, puis Entrée *</label>
      <input type="text" id="scan-input" autocomplete="off" autofocus
             placeholder="Douchette : le code est envoyé + Entrée automatiquement"
             style="width:100%;font-size:16px;font-family:var(--font-mono,monospace);">
      <div id="scan-feedback" class="text-sm" style="margin-top:6px;min-height:18px;"></div>
    </div>

    <div id="scan-unknown" class="alert alert-error" style="display:none;"></div>

    <div class="table-wrap" style="margin:10px 0;">
      <table>
        <thead><tr><th>Référence</th><th>Produit</th><th style="text-align:right;">Stock actuel</th><th style="width:110px;text-align:right;">Qté reçue</th><th style="width:40px;"></th></tr></thead>
        <tbody id="scan-tbody">
          <tr><td colspan="5" class="empty">Aucun produit scanné — scannez ou tapez un code puis Entrée.</td></tr>
        </tbody>
      </table>
    </div>

    <form method="POST" action="?onglet=reception" id="scan-form">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reception_scan">
      <div id="scan-hidden"></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="flex:1;min-width:240px;margin:0;">
          <label>Motif (origine de la réception)</label>
          <input type="text" name="motif" placeholder="Ex. : livraison LABOREX du jour" style="width:100%;">
        </div>
        <button type="button" class="btn btn-ghost" onclick="scanClear()">Vider</button>
        <button type="submit" class="btn btn-primary" id="scan-submit" disabled><?= icon('save',14) ?> Enregistrer la réception</button>
      </div>
    </form>
  </div>
</div>

<script>
var SCAN_MAP   = <?= json_encode($scanMap, JSON_UNESCAPED_UNICODE) ?>;
var SCAN_LINES = {};   // pid → {nom, ref, stock, qte}

function scanFlash(msg, ok) {
  var f = document.getElementById('scan-feedback');
  f.textContent = msg;
  f.style.color = ok ? 'var(--teal2)' : 'var(--red)';
}

function scanBeep(ok) {
  try {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    var o = ctx.createOscillator(), g = ctx.createGain();
    o.frequency.value = ok ? 1200 : 300; g.gain.value = 0.06;
    o.connect(g); g.connect(ctx.destination);
    o.start(); setTimeout(function(){ o.stop(); ctx.close(); }, ok ? 90 : 220);
  } catch (e) { /* audio indisponible — feedback visuel seul */ }
}

function scanAdd(pid, info) {
  if (SCAN_LINES[pid]) SCAN_LINES[pid].qte += 1;
  else SCAN_LINES[pid] = { nom: info.nom, ref: info.ref, stock: info.stock, qte: 1 };
  scanRender();
}

function scanRender() {
  var tb = document.getElementById('scan-tbody');
  var keys = Object.keys(SCAN_LINES);
  if (keys.length === 0) {
    tb.innerHTML = '<tr><td colspan="5" class="empty">Aucun produit scanné — scannez ou tapez un code puis Entrée.</td></tr>';
  } else {
    tb.innerHTML = '';
    keys.forEach(function(pid) {
      var l = SCAN_LINES[pid];
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="td-mono">' + l.ref + '</td>' +
        '<td>' + l.nom + '</td>' +
        '<td style="text-align:right;" class="fw-mono">' + l.stock + '</td>' +
        '<td style="text-align:right;"><input type="number" min="1" value="' + l.qte + '" data-pid="' + pid + '" class="scan-qte" style="width:90px;text-align:right;"></td>' +
        '<td><button type="button" class="btn btn-ghost btn-xs" onclick="scanRemove(' + pid + ')">✕</button></td>';
      tb.appendChild(tr);
    });
  }
  document.getElementById('scan-submit').disabled = keys.length === 0;
}

document.addEventListener('input', function(e) {
  if (e.target.classList && e.target.classList.contains('scan-qte')) {
    var pid = e.target.getAttribute('data-pid');
    if (SCAN_LINES[pid]) SCAN_LINES[pid].qte = Math.max(1, parseInt(e.target.value, 10) || 1);
  }
});

document.getElementById('scan-form').addEventListener('submit', function(ev) {
  if (Object.keys(SCAN_LINES).length === 0) { ev.preventDefault(); return false; }
  if (!confirm('Confirmer la réception de ' + Object.keys(SCAN_LINES).length + ' produit(s) au magasin ?')) { ev.preventDefault(); return false; }
  var hidden = document.getElementById('scan-hidden');
  hidden.innerHTML = '';
  Object.keys(SCAN_LINES).forEach(function(pid) {
    var i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'scan_pid[]'; i1.value = pid;
    var i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'scan_qte[]'; i2.value = SCAN_LINES[pid].qte;
    hidden.appendChild(i1); hidden.appendChild(i2);
  });
});

function scanRemove(pid) { delete SCAN_LINES[pid]; scanRender(); }
function scanClear() { SCAN_LINES = {}; document.getElementById('scan-unknown').style.display = 'none'; document.getElementById('scan-feedback').textContent = ''; scanRender(); }

var scanInput = document.getElementById('scan-input');
scanInput.addEventListener('keydown', function(e) {
  if (e.key !== 'Enter' && e.key !== 'Tab') return;
  e.preventDefault();
  var code = this.value.trim();
  if (code === '') return;
  var info = SCAN_MAP[code.toLowerCase()];
  var unk = document.getElementById('scan-unknown');
  if (info) {
    scanAdd(info.id, info);
    scanFlash('✓ ' + info.nom + ' — stock magasin actuel : ' + info.stock, true);
    unk.style.display = 'none';
    scanBeep(true);
  } else {
    scanFlash('✗ Code inconnu : ' + code, false);
    unk.style.display = 'block';
    unk.textContent = 'Code « ' + code + ' » introuvable dans le catalogue. Vérifiez la référence du produit (ou créez-le d\'abord dans Médicaments).';
    scanBeep(false);
  }
  this.value = '';
  this.focus();
});
scanInput.focus();
</script>

<div class="card" style="max-width:620px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title">Saisie manuelle unitaire</div>
    <span class="text-sm">Entrée manuelle (hors commande) ou correction d'inventaire.</span>
  </div>
  <div class="card-pad">
    <form method="POST" action="?onglet=reception">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reception">
      <div class="form-group">
        <label>Produit *</label>
        <select name="produit_id" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($produits as $p): ?>
          <option value="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock_magasin'] ?>"><?= e($p['nom']) ?> (mag actuel: <?= (int)$p['stock_magasin'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Type</label>
          <select name="type">
            <option value="entrée">Entrée (réception)</option>
            <option value="ajustement">Ajustement (+/-)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Quantité *</label>
          <input type="number" name="quantite" required placeholder="ex: 50 (ou -5 pour un retrait d'ajustement)" min="-99999">
        </div>
      </div>
      <div class="form-group">
        <label>Motif</label>
        <input type="text" name="motif" placeholder="Origine de la réception / raison de l'ajustement" style="width:100%;">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px;">
        <a href="<?= url('magasin', ['onglet'=>'stock']) ?>" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary"><?= icon('save',14) ?> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($onglet === 'historique'): ?>
<!-- ═══ Onglet HISTORIQUE ══════════════════════════════════ -->
<div class="flex-between no-print" style="margin-bottom:16px;align-items:center;flex-wrap:wrap;gap:10px;">
  <div style="font-size:15px;font-weight:600;color:var(--text2);">Historique des mouvements Magasin ↔ Pharmacie</div>
  <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= icon('report',14) ?> Imprimer l'historique</button>
</div>

<div class="card no-print" style="margin-bottom:16px;">
  <div class="card-header">
    <div class="card-title">Transferts Magasin → Pharmacie</div>
    <span class="text-sm"><?= fmtInt($totalTrf) ?> transfert(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Référence</th><th>Date</th><th>Opérateur</th><th>Pharmacie</th>
        <th style="text-align:right;">Lignes</th><th style="text-align:right;">Unités</th><th>Note</th><th>Détail</th></tr>
      </thead>
      <tbody>
        <?php foreach ($transferts as $t):
          $parts = explode('→', $t['note'] ?? '');
          if (count($parts) >= 2) { $phNom = trim(array_pop($parts)); $noteAff = trim(implode('→', $parts)); }
          else { $phNom = ''; $noteAff = trim($t['note'] ?? ''); }
        ?>
        <tr>
          <td class="td-mono"><?= e($t['reference']) ?></td>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
          <td class="text-sm"><?= e(trim($t['prenom'] . ' ' . $t['u_nom'])) ?: '—' ?></td>
          <td class="text-sm fw-mono" style="color:var(--teal2);font-weight:600;"><?= e($phNom ?: '—') ?></td>
          <td style="text-align:right;"><span class="badge badge-blue"><?= (int)$t['nb_lignes'] ?></span></td>
          <td class="fw-mono" style="text-align:right;"><?= fmtInt((int)$t['total_qte']) ?></td>
          <td class="text-sm"><?= e($noteAff ?: '—') ?></td>
          <td style="white-space:nowrap;">
            <button class="btn btn-ghost btn-xs" onclick="showTrfDetail(<?= (int)$t['id'] ?>)"><?= icon('eye',13) ?> Voir</button>
            <a href="<?= url('magasin', ['bon' => (int)$t['id']]) ?>" target="_blank" class="btn btn-ghost btn-xs" style="text-decoration:none;"><?= icon('report',13) ?> Bon</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$transferts): ?>
        <tr><td colspan="8">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucun transfert</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pageTrf, $perPageTrf, $totalTrf, ['onglet'=>'historique'], 'page_trf') ?>
</div>

<div class="card no-print">
  <div class="card-header">
    <div class="card-title">Mouvements du magasin</div>
    <span class="text-sm"><?= fmtInt($totalMvt) ?> mouvement(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Produit</th><th>Type</th>
        <th style="text-align:right;">Quantité</th><th>Motif</th><th>Opérateur</th></tr>
      </thead>
      <tbody>
        <?php foreach ($mouvements as $m):
          $typeBadge = ['entrée'=>'badge-green','sortie'=>'badge-gold','ajustement'=>'badge-purple'];
        ?>
        <tr>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td class="td-name"><?= e($m['pnom'] ?? '—') ?></td>
          <td><span class="badge <?= $typeBadge[$m['type']] ?? 'badge-gray' ?>"><?= e($m['type']) ?></span></td>
          <td class="fw-mono" style="text-align:right;color:<?= $m['type']==='entrée'?'var(--teal2)':($m['type']==='sortie'?'var(--gold)':'var(--purple,#9b59b6)') ?>;"><?= ($m['quantite'] > 0 ? '+' : '') . fmtInt((int)$m['quantite']) ?></td>
          <td class="text-sm"><?= e($m['motif'] ?? '—') ?></td>
          <td class="text-sm"><?= e(trim($m['prenom'] . ' ' . $m['u_nom'])) ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$mouvements): ?>
        <tr><td colspan="6">
          <div class="empty">
            <div style="color:var(--text3);margin-bottom:8px;"><?= icon('history',36) ?></div>
            <div>Aucun mouvement</div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pageMvt, $perPageMvt, $totalMvt, ['onglet'=>'historique'], 'page_mvt') ?>
</div>

<!-- ── Bloc impression A4 ── -->
<div id="print-history" class="print-only">
  <div style="text-align:center;margin-bottom:6px;">
    <div style="font-size:18px;font-weight:700;">Historique des mouvements — Magasin ↔ Pharmacie</div>
    <div style="font-size:12px;color:#555;">Édité le <?= date('d/m/Y à H:i') ?> — <?= e($appNom ?? 'PharmaCare') ?></div>
  </div>

  <h3 style="font-size:14px;margin:18px 0 8px;">Transferts Magasin → Pharmacie (<?= count($transferts) ?>)</h3>
  <table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
      <tr>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Référence</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Date</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Opérateur</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Pharmacie</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Lignes</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Unités</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Note</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($transferts as $t):
        // La note est enregistrée sous la forme « motif utilisateur → Nom pharmacie »
        // (sans motif : « → Nom pharmacie »). On éclate sur la flèche seule.
        $parts = explode('→', $t['note'] ?? '');
        if (count($parts) >= 2) { $phNom = trim(array_pop($parts)); $noteAff = trim(implode('→', $parts)); }
        else { $phNom = ''; $noteAff = trim($t['note'] ?? ''); }
      ?>
      <tr>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($t['reference']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e(trim($t['prenom'] . ' ' . $t['u_nom'])) ?: '—' ?></td>
        <td style="border:1px solid #999;padding:5px 7px;font-weight:600;"><?= e($phNom ?: '—') ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= (int)$t['nb_lignes'] ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= fmtInt((int)$t['total_qte']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($noteAff ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$transferts): ?>
      <tr><td colspan="7" style="border:1px solid #999;padding:8px;text-align:center;">Aucun transfert</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 style="font-size:14px;margin:18px 0 8px;">Mouvements du magasin (<?= count($mouvements) ?>)</h3>
  <table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
      <tr>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Date</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Produit</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Type</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:right;background:#eee;">Quantité</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Motif</th>
        <th style="border:1px solid #999;padding:5px 7px;text-align:left;background:#eee;">Opérateur</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($mouvements as $m): ?>
      <tr>
        <td style="border:1px solid #999;padding:5px 7px;"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['pnom'] ?? '—') ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['type']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;text-align:right;"><?= ($m['quantite'] > 0 ? '+' : '') . fmtInt((int)$m['quantite']) ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e($m['motif'] ?? '—') ?></td>
        <td style="border:1px solid #999;padding:5px 7px;"><?= e(trim($m['prenom'] . ' ' . $m['u_nom'])) ?: '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$mouvements): ?>
      <tr><td colspan="6" style="border:1px solid #999;padding:8px;text-align:center;">Aucun mouvement</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal détail transfert -->
<div class="modal-overlay" id="modal-trf">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="trf-ref">Détail transfert</div>
      <button class="modal-close" onclick="closeModal('modal-trf')">✕</button>
    </div>
    <div id="trf-body" class="card-pad"></div>
    <div class="modal-footer" style="padding:0;display:flex;gap:8px;">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('modal-trf')">Fermer</button>
      <a id="trf-bon-link" href="#" target="_blank" class="btn btn-primary btn-sm" style="text-decoration:none;">
        <?= icon('report',13) ?> Imprimer le bon
      </a>
    </div>
  </div>
</div>
<script>
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
var trfLignes = <?= json_encode($trfLignes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var trfData = <?= json_encode(array_column($transferts, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
function showTrfDetail(tid) {
  var t = trfData[tid];
  if (!t) return;
  document.getElementById('trf-ref').textContent = t.reference;
  // Extraire la pharmacie cible (segment après la dernière flèche « → » de la note)
  var noteParts = String(t.note || '').split('→');
  var phNom = noteParts.length >= 2 ? noteParts.pop().trim() : '';
  var motif = noteParts.length >= 1 ? noteParts.join('→').trim() : '';
  var lignes = trfLignes[tid] || [];
  var rows = lignes.length ? lignes.map(function(l){
    return '<tr style="border-bottom:1px solid var(--border)"><td style="padding:8px 7px;">'+escHtml(l.produit_nom||'—')+'</td><td style="padding:8px 7px;text-align:right;font-family:var(--font-mono,monospace);">'+l.quantite+'</td></tr>';
  }).join('') : '<tr><td colspan="2" style="padding:16px;text-align:center;color:var(--text3);">Aucune ligne</td></tr>';
  document.getElementById('trf-body').innerHTML =
    '<div style="font-size:12px;color:var(--text3);margin-bottom:4px;">' + new Date(t.created_at).toLocaleString('fr-FR') + '</div>' +
    (phNom ? '<div style="margin-bottom:8px;">'+icon('building',14)+' <strong style="color:var(--teal2);">' + escHtml(phNom) + '</strong>' + (motif ? ' — <span style="color:var(--text3);">' + escHtml(motif) + '</span>' : '') + '</div>' : (motif ? '<div style="font-size:12px;color:var(--text3);margin-bottom:8px;">' + escHtml(motif) + '</div>' : '')) +
    '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:1px solid var(--border)"><th style="padding:7px;text-align:left;color:var(--text3);font-size:10px;text-transform:uppercase;">Produit</th><th style="padding:7px;text-align:right;color:var(--text3);font-size:10px;text-transform:uppercase;">Qté</th></tr></thead><tbody>'+rows+'</tbody></table>';
  var bonLink = document.getElementById('trf-bon-link');
  if (bonLink) bonLink.href = (window.APP_URL || '') + '/magasin?bon=' + tid;
  openModal('modal-trf');
}
</script>
<?php elseif ($onglet === 'etat-date'): ?>
<?php
// ════════════════════════════════════════════════════════════
// État du stock à une date quelconque (reconstitution rétroactive)
// ════════════════════════════════════════════════════════════
// Principe : stock_à_D = stock_actuel − Σ(mouvements postérieurs à D).
//   On remonte l'état en FIN de journée D : tout mouvement du jour D est
//   inclus dans l'état, seuls les mouvements strictement postérieurs sont
//   « rembobinés ».
//
//   • Magasin   : mouvements_magasin  — entrée (+), sortie (−), ajustement (signé).
//   • Pharmacie : transferts reçus (entrée +) + ventes non annulées (sortie −),
//     par pharmacie (via transferts_magasin.pharmacie_id et ventes.pharmacie_id).
//
//   Limite documentée : la reconstitution par pharmacie reflète les transferts
//   reçus et les ventes (les deux flux correctement attribués à une pharmacie).
//   Les ajustements et les retours-vers-magasin ne concernent que la pharmacie
//   principale (produits.stock) et ne sont pas attribuables à une pharmacie
//   donnée — ils ne sont donc pas reflétés dans la colonne d'une pharmacie
//   spécifique. Le magasin, lui, est reconstitué à 100 % depuis mouvements_magasin.
// ════════════════════════════════════════════════════════════

$dateReq = $_GET['date'] ?? date('Y-m-d');
$scope   = $_GET['scope'] ?? 'magasin';

$dt = DateTime::createFromFormat('Y-m-d', $dateReq);
$dateOk = $dt && $dt->format('Y-m-d') === $dateReq;
$date     = $dateOk ? $dateReq : date('Y-m-d');
$boundary = $date . ' 23:59:59';   // tout mouvement du jour D est inclus

// ── Deltas MAGASIN postérieurs à D (par produit) ───────────────
$magDelta = [];
$st = $db->prepare("SELECT produit_id,
        COALESCE(SUM(CASE WHEN type='entrée'      THEN quantite END),0) AS e,
        COALESCE(SUM(CASE WHEN type='sortie'      THEN quantite END),0) AS s,
        COALESCE(SUM(CASE WHEN type='ajustement'  THEN quantite END),0) AS a
    FROM mouvements_magasin WHERE created_at > :b GROUP BY produit_id");
$st->execute([':b' => $boundary]);
foreach ($st->fetchAll() as $r) $magDelta[(int)$r['produit_id']] = $r;

// ── Transferts reçus par pharmacie postérieurs à D ─────────────
$trfDelta = [];   // [pharmacie_id][produit_id] => quantité entrée
$st = $db->prepare("SELECT t.pharmacie_id AS phid, tl.produit_id AS pid,
        COALESCE(SUM(tl.quantite),0) AS q
    FROM transfert_lignes tl
    JOIN transferts_magasin t ON t.id = tl.transfert_id
    WHERE t.created_at > :b
    GROUP BY t.pharmacie_id, tl.produit_id");
$st->execute([':b' => $boundary]);
foreach ($st->fetchAll() as $r) $trfDelta[(int)$r['phid']][(int)$r['pid']] = (int)$r['q'];

// ── Ventes par pharmacie postérieures à D ──────────────────────
$vteDelta = [];   // [pharmacie_id][produit_id] => quantité sortie
$st = $db->prepare("SELECT v.pharmacie_id AS phid, vl.produit_id AS pid,
        COALESCE(SUM(vl.quantite),0) AS q
    FROM vente_lignes vl
    JOIN ventes v ON v.id = vl.vente_id
    WHERE v.est_annulee = 0 AND v.created_at > :b
    GROUP BY v.pharmacie_id, vl.produit_id");
$st->execute([':b' => $boundary]);
foreach ($st->fetchAll() as $r) $vteDelta[(int)$r['phid']][(int)$r['pid']] = (int)$r['q'];

// ── Périmètre : magasin | ph:<id> | toutes ─────────────────────
$scopePhId = 0;
if (str_starts_with($scope, 'ph:')) $scopePhId = (int)substr($scope, 3);
$scopeAll = ($scope === 'toutes');
$scopeMag = ($scope === 'magasin');

// Pharmacie affichée quand scope = ph:<id>
$phAff = null;
if ($scopePhId > 0) {
    foreach ($pharmacies as $ph) if ((int)$ph['id'] === $scopePhId) { $phAff = $ph; break; }
    if (!$phAff) { $scopePhId = 0; $scopeMag = true; $scope = 'magasin'; } // pharmacie inexistante → fallback
}

// ── Calcul de l'état par produit ───────────────────────────────
$rows = [];
foreach ($produits as $p) {
    $pid = (int)$p['id'];
    $row = ['id' => $pid, 'nom' => $p['nom'], 'reference' => $p['reference'], 'cat' => $p['cat']];
    if ($scopeMag || $scopeAll) {
        $cur = (int)$p['stock_magasin'];
        $d   = $magDelta[$pid] ?? null;
        $e   = $d ? (int)$d['e'] : 0;
        $s   = $d ? (int)$d['s'] : 0;
        $a   = $d ? (int)$d['a'] : 0;
        $row['mag'] = $cur - $e + $s - $a;   // rembobiner : - entrées + sorties - ajustements
    }
    if ($scopePhId > 0) {
        $cur = $ppMap[$pid][$scopePhId] ?? 0;
        $trf = $trfDelta[$scopePhId][$pid] ?? 0;
        $vte = $vteDelta[$scopePhId][$pid] ?? 0;
        $row['ph'] = $cur - $trf + $vte;     // rembobiner : - entrées transfert + ventes
    }
    if ($scopeAll) {
        $row['phs'] = [];
        foreach ($pharmacies as $ph) {
            $phid = (int)$ph['id'];
            $cur  = $ppMap[$pid][$phid] ?? 0;
            $trf  = $trfDelta[$phid][$pid] ?? 0;
            $vte  = $vteDelta[$phid][$pid] ?? 0;
            $row['phs'][$phid] = $cur - $trf + $vte;
        }
    }
    $rows[] = $row;
}

// ── Totaux ─────────────────────────────────────────────────────
$totMag = 0; $totPh = 0; $totPhs = [];
foreach ($rows as $r) {
    if (isset($r['mag'])) $totMag += (int)$r['mag'];
    if (isset($r['ph']))  $totPh  += (int)$r['ph'];
    if (isset($r['phs'])) foreach ($r['phs'] as $phid => $v) $totPhs[$phid] = ($totPhs[$phid] ?? 0) + (int)$v;
}
$aujourdhui = date('Y-m-d');
?>

<div class="flex-between no-print" style="margin-bottom:16px;align-items:center;flex-wrap:wrap;gap:10px;">
  <div style="font-size:15px;font-weight:600;color:var(--text2);">État du stock à une date</div>
  <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= icon('report',14) ?> Imprimer</button>
</div>

<!-- ── Formulaire de requête ── -->
<div class="card no-print" style="margin-bottom:16px;">
  <form method="GET" action="<?= url('magasin') ?>" class="card-pad" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="onglet" value="etat-date">
    <div>
      <label style="display:block;font-size:12px;color:var(--text3);margin-bottom:4px;">Date</label>
      <input type="date" name="date" value="<?= e($date) ?>" max="<?= e($aujourdhui) ?>" class="input" style="min-width:160px;">
    </div>
    <div>
      <label style="display:block;font-size:12px;color:var(--text3);margin-bottom:4px;">Périmètre</label>
      <select name="scope" class="input" style="min-width:220px;">
        <option value="magasin" <?= $scopeMag ? 'selected' : '' ?>>Magasin (dépôt central)</option>
        <?php foreach ($pharmacies as $ph): ?>
        <option value="ph:<?= (int)$ph['id'] ?>" <?= $scopePhId === (int)$ph['id'] ? 'selected' : '' ?>>Pharmacie — <?= e($ph['nom']) ?></option>
        <?php endforeach; ?>
        <option value="toutes" <?= $scopeAll ? 'selected' : '' ?>>Toutes (magasin + pharmacies)</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('search',14) ?> Calculer</button>
  </form>
</div>

<?php if (!$dateOk): ?>
<div class="card no-print" style="margin-bottom:16px;"><div class="card-pad" style="color:var(--gold);"><?= icon('alert',14) ?> Date invalide — affichage pour aujourd'hui.</div></div>
<?php endif; ?>

<!-- ── Résultat ── -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <?php if ($scopeMag): ?>Stock du dépôt central au <?= date('d/m/Y', strtotime($date)) ?>
      <?php elseif ($scopePhId > 0): ?>Stock de la pharmacie « <?= e($phAff['nom']) ?> » au <?= date('d/m/Y', strtotime($date)) ?>
      <?php else: ?>État global au <?= date('d/m/Y', strtotime($date)) ?>
      <?php endif; ?>
    </div>
    <span class="text-sm"><?= count($rows) ?> référence(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Médicament</th>
          <th>Réf.</th>
          <th>Catégorie</th>
          <?php if ($scopeMag || $scopeAll): ?>
          <th style="text-align:right;">Stock magasin</th>
          <?php endif; ?>
          <?php if ($scopePhId > 0): ?>
          <th style="text-align:right;color:var(--teal2);">Stock pharmacie</th>
          <?php endif; ?>
          <?php if ($scopeAll): foreach ($pharmacies as $i => $ph): ?>
          <th style="text-align:right;color:var(--teal2);<?= $i === 0 ? 'border-left:2px solid var(--border2);' : '' ?>" title="Stock de la pharmacie « <?= e($ph['nom']) ?> »"><?= e($ph['nom']) ?></th>
          <?php endforeach; endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="td-name"><?= e($r['nom']) ?></td>
          <td class="td-mono"><?= e($r['reference']) ?></td>
          <td class="text-sm"><?= e($r['cat'] ?? '—') ?></td>
          <?php if ($scopeMag || $scopeAll): ?>
          <td class="fw-mono" style="text-align:right;<?= (int)$r['mag'] <= 0 ? 'color:var(--text3);' : '' ?>"><?= fmtInt((int)$r['mag']) ?></td>
          <?php endif; ?>
          <?php if ($scopePhId > 0): ?>
          <td class="fw-mono" style="text-align:right;<?= (int)$r['ph'] <= 0 ? 'color:var(--text3);' : '' ?>"><?= fmtInt((int)$r['ph']) ?></td>
          <?php endif; ?>
          <?php if ($scopeAll): foreach ($pharmacies as $i => $ph):
            $v = (int)($r['phs'][(int)$ph['id']] ?? 0);
          ?>
          <td class="fw-mono" style="text-align:right;<?= ($i === 0 ? 'border-left:2px solid var(--border2);' : '') . ($v <= 0 ? 'color:var(--text3);' : '') ?>"><?= fmtInt($v) ?></td>
          <?php endforeach; endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="<?= ($scopeAll ? 3 + 1 + count($pharmacies) : 4) ?>">
          <div class="empty"><div style="color:var(--text3);margin-bottom:8px;"><?= icon('box',36) ?></div><div>Aucun produit</div></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot>
        <tr style="border-top:2px solid var(--border2);">
          <td colspan="<?= ($scopeAll ? 3 : 3) ?>" style="font-weight:600;text-align:right;">Total unités</td>
          <?php if ($scopeMag || $scopeAll): ?>
          <td class="fw-mono" style="text-align:right;font-weight:600;"><?= fmtInt($totMag) ?></td>
          <?php endif; ?>
          <?php if ($scopePhId > 0): ?>
          <td class="fw-mono" style="text-align:right;font-weight:600;color:var(--teal2);"><?= fmtInt($totPh) ?></td>
          <?php endif; ?>
          <?php if ($scopeAll): foreach ($pharmacies as $i => $ph): ?>
          <td class="fw-mono" style="text-align:right;font-weight:600;color:var(--teal2);<?= $i === 0 ? 'border-left:2px solid var(--border2);' : '' ?>"><?= fmtInt((int)($totPhs[(int)$ph['id']] ?? 0)) ?></td>
          <?php endforeach; endif; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($scopePhId > 0 || $scopeAll): ?>
  <div class="card-pad" style="font-size:12px;color:var(--text3);border-top:1px solid var(--border);">
    <?= icon('alert',13) ?> La reconstitution par pharmacie reflète les <strong>transferts reçus</strong> et les <strong>ventes</strong>. Les ajustements et retours-vers-magasin (qui ne concernent que la pharmacie principale) ne sont pas attribuables à une pharmacie spécifique et ne sont pas reflétés ici. Le magasin, lui, est reconstitué intégralement depuis le journal des mouvements.
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>