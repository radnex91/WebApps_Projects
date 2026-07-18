<?php
// config/comptabilite.php — Helpers pour la comptabilité OHADA

/**
 * Crée une écriture comptable (double partie) dans une transaction.
 * Appeler depuis une transaction PDO déjà ouverte.
 *
 * @param PDO    $db
 * @param string $libelle     Libellé général
 * @param string $date        Date Y-m-d
 * @param array  $lignes      [ [compte_id, debit, credit, libelle_ligne], ... ]
 * @param string $source      'vente','commande','stock','caisse','manuel'
 * @param string $sourceRef   Référence source (ex: VNT-2026-001)
 * @param int    $userId
 * @return int   ID de l'écriture créée
 */
function ecritureCreate(PDO $db, string $libelle, string $date, array $lignes,
                        string $source = 'manuel', string $sourceRef = '',
                        int $userId = 0): int {
    // Vérifier équilibre
    $totalDebit  = 0;
    $totalCredit = 0;
    foreach ($lignes as $l) {
        $totalDebit  += (float)($l[1] ?? 0);
        $totalCredit += (float)($l[2] ?? 0);
    }
    if (abs($totalDebit - $totalCredit) > 0.01) {
        throw new Exception('Écriture déséquilibrée : débit=' . $totalDebit . ' crédit=' . $totalCredit);
    }

    // Trouver l'exercice
    $exSql = "SELECT id FROM exercices WHERE date_debut <= ? AND date_fin >= ? AND cloture = 0 LIMIT 1";
    $exSt = $db->prepare($exSql);
    $exSt->execute([$date, $date]);
    $ex = $exSt->fetch();
    $exerciceId = $ex ? (int)$ex['id'] : null;

    // Garde-fou : interdire la saisie sur un exercice clôturé.
    if ($ex === false) {
        $closed = $db->prepare("SELECT id FROM exercices WHERE date_debut <= ? AND date_fin >= ? AND cloture = 1 LIMIT 1");
        $closed->execute([$date, $date]);
        if ($closed->fetch()) {
            throw new Exception('Exercice clôturé : saisie impossible pour la date ' . $date);
        }
    }

    // Référence séquentielle
    $ecLike = 'EC-' . date('Y') . '-%';
    $ecLast = $db->prepare("SELECT reference FROM ecritures WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1");
    $ecLast->execute([$ecLike]);
    $ecNext = 1;
    if ($ecLastRef = $ecLast->fetchColumn()) {
        $ecNext = (int)end(explode('-', $ecLastRef)) + 1;
    }
    $ref = 'EC-' . date('Y') . '-' . str_pad($ecNext, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO ecritures (reference, libelle, date_ecriture, exercice_id, utilisateur_id, source, source_ref)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$ref, $libelle, $date, $exerciceId, $userId ?: null, $source, $sourceRef ?: null]);
    $eid = (int)$db->lastInsertId();

    $stmtL = $db->prepare("
        INSERT INTO ecriture_lignes (ecriture_id, compte_id, debit, credit, libelle_ligne)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($lignes as $l) {
        $stmtL->execute([$eid, (int)$l[0], (float)($l[1] ?? 0), (float)($l[2] ?? 0), $l[3] ?? '']);
    }

    return $eid;
}

/**
 * Récupère le plan comptable (arborescence plate triée par compte)
 */
function planComptableAll(PDO $db): array {
    return $db->query("SELECT * FROM plan_comptable WHERE actif = 1 ORDER BY compte")->fetchAll();
}

/**
 * Récupère les exercices
 */
function exercicesAll(PDO $db): array {
    return $db->query("SELECT * FROM exercices ORDER BY date_debut DESC")->fetchAll();
}

/**
 * Récupère les écritures avec filtres (journal)
 */
function journalGet(PDO $db, string $debut = '', string $fin = '', string $source = ''): array {
    $sql = "SELECT e.*, u.prenom, u.nom AS u_nom
            FROM ecritures e
            LEFT JOIN utilisateurs u ON e.utilisateur_id = u.id
            WHERE 1=1";
    $params = [];
    if ($debut) { $sql .= " AND e.date_ecriture >= ?"; $params[] = $debut; }
    if ($fin)   { $sql .= " AND e.date_ecriture <= ?"; $params[] = $fin; }
    if ($source) { $sql .= " AND e.source = ?"; $params[] = $source; }
    $sql .= " ORDER BY e.date_ecriture DESC, e.id DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Récupère les lignes d'une écriture
 */
function ecritureLignes(PDO $db, int $ecritureId): array {
    $stmt = $db->prepare("
        SELECT el.*, pc.compte, pc.intitule
        FROM ecriture_lignes el
        JOIN plan_comptable pc ON el.compte_id = pc.id
        WHERE el.ecriture_id = ?
        ORDER BY pc.compte
    ");
    $stmt->execute([$ecritureId]);
    return $stmt->fetchAll();
}

/**
 * Balance générale — total débit/crédit et solde par compte
 */
function balanceGet(PDO $db, string $debut = '', string $fin = ''): array {
    $sql = "SELECT pc.id, pc.compte, pc.intitule, pc.classe, pc.nature,
                   COALESCE(SUM(el.debit), 0) AS total_debit,
                   COALESCE(SUM(el.credit), 0) AS total_credit
            FROM plan_comptable pc
            LEFT JOIN ecriture_lignes el ON el.compte_id = pc.id
            LEFT JOIN ecritures e ON el.ecriture_id = e.id
            WHERE pc.actif = 1";
    $params = [];
    if ($debut) { $sql .= " AND e.date_ecriture >= ?"; $params[] = $debut; }
    if ($fin)   { $sql .= " AND e.date_ecriture <= ?"; $params[] = $fin; }
    $sql .= " GROUP BY pc.id, pc.compte, pc.intitule, pc.classe, pc.nature
              ORDER BY pc.compte";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Grand-livre : écritures pour un compte donné
 */
function grandLivreGet(PDO $db, int $compteId, string $debut = '', string $fin = ''): array {
    $sql = "SELECT el.*, e.date_ecriture, e.reference, e.libelle AS ecr_libelle, e.source
            FROM ecriture_lignes el
            JOIN ecritures e ON el.ecriture_id = e.id
            WHERE el.compte_id = ?";
    $params = [$compteId];
    if ($debut) { $sql .= " AND e.date_ecriture >= ?"; $params[] = $debut; }
    if ($fin)   { $sql .= " AND e.date_ecriture <= ?"; $params[] = $fin; }
    $sql .= " ORDER BY e.date_ecriture, e.id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Compte de résultat : total charges (classe 6) et produits (classe 7)
 */
function compteResultat(PDO $db, string $debut = '', string $fin = ''): array {
    $sql = "SELECT pc.id, pc.compte, pc.intitule, pc.classe,
                   COALESCE(SUM(el.debit), 0) AS total_debit,
                   COALESCE(SUM(el.credit), 0) AS total_credit
            FROM plan_comptable pc
            LEFT JOIN ecriture_lignes el ON el.compte_id = pc.id
            LEFT JOIN ecritures e ON el.ecriture_id = e.id
            WHERE pc.actif = 1 AND pc.classe IN (6, 7)";
    $params = [];
    if ($debut) { $sql .= " AND e.date_ecriture >= ?"; $params[] = $debut; }
    if ($fin)   { $sql .= " AND e.date_ecriture <= ?"; $params[] = $fin; }
    $sql .= " GROUP BY pc.id, pc.compte, pc.intitule, pc.classe
              ORDER BY pc.compte";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Bilan simplifié : total actif et passif
 */
function bilanGet(PDO $db, string $debut = '', string $fin = ''): array {
    // Récupère la balance complète
    $balance = balanceGet($db, $debut, $fin);

    $actif  = ['total' => 0, 'comptes' => []];
    $passif = ['total' => 0, 'comptes' => []];

    // Résultat net de la période (classes 6/7) — réinjecté au passif (compte 12)
    // pour équilibrer Actif = Passif + Résultat à tout moment.
    $totalProduits = 0;
    $totalCharges  = 0;

    foreach ($balance as $c) {
        $solde = (float)$c['total_debit'] - (float)$c['total_credit'];
        $classe = (int)$c['classe'];

        if ($classe === 6) {
            $totalCharges += $solde;   // charge = solde débiteur
            continue;
        }
        if ($classe === 7) {
            $totalProduits += -$solde; // produit = solde créditeur
            continue;
        }

        if (abs($solde) < 0.01) continue;

        // Actif : classes 2 (immos) + 3 (stocks) + 4 client (débiteur) + 5 (trésorerie)
        if ($classe === 2 || $classe === 3 || $classe === 5) {
            if ($solde > 0) {
                $actif['total'] += $solde;
                $actif['comptes'][] = ['compte' => $c['compte'], 'intitule' => $c['intitule'], 'montant' => $solde];
            }
        } elseif ($classe === 4) {
            $code = $c['compte'];
            // Clients (411) côté débiteur → actif
            if (str_starts_with($code, '411') || str_starts_with($code, '445') || str_starts_with($code, '471')) {
                if ($solde > 0) {
                    $actif['total'] += $solde;
                    $actif['comptes'][] = ['compte' => $c['compte'], 'intitule' => $c['intitule'], 'montant' => $solde];
                }
            } else {
                // Fournisseurs, Etat TVA collectée → passif (solde créditeur)
                $soldePassif = -$solde;
                if ($soldePassif > 0) {
                    $passif['total'] += $soldePassif;
                    $passif['comptes'][] = ['compte' => $c['compte'], 'intitule' => $c['intitule'], 'montant' => $soldePassif];
                }
            }
        } elseif ($classe === 1) {
            // Capitaux → passif
            $passif['total'] += -$solde;
            $passif['comptes'][] = ['compte' => $c['compte'], 'intitule' => $c['intitule'], 'montant' => -$solde];
        }
    }

    // Réinjection du résultat de l'exercice au passif (compte 12).
    // Signé : positif (bénéfice) augmente le passif, négatif (perte) le diminue.
    $resultat = round($totalProduits - $totalCharges, 2);
    if (abs($resultat) >= 0.01) {
        $passif['total'] += $resultat;
        $passif['comptes'][] = ['compte' => '12', 'intitule' => 'Résultat de l\'exercice', 'montant' => $resultat];
    }

    return ['actif' => $actif, 'passif' => $passif];
}

/**
 * Associe un compte plan_comptable.id à partir d'un code compte
 * Crée le compte s'il n'existe pas (lazy creation)
 */
function compteFindOrCreate(PDO $db, string $code, string $intitule, int $classe, string $nature = 'debit'): int {
    $stmt = $db->prepare("SELECT id FROM plan_comptable WHERE compte = ?");
    $stmt->execute([$code]);
    $r = $stmt->fetch();
    if ($r) return (int)$r['id'];

    $db->prepare("INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES (?, ?, ?, ?)")
       ->execute([$code, $intitule, $classe, $nature]);
    return (int)$db->lastInsertId();
}

/**
 * Clôture d'un exercice (OHADA — détermination du résultat).
 * À appeler dans une transaction PDO déjà ouverte.
 *
 * Écrit une écriture de clôture (source 'cloture') qui solde chaque compte
 * de classe 6 (charge) et 7 (produit) dans le compte 12 (Résultat de l'exercice),
 * verrouille toutes les écritures de l'exercice, marque l'exercice clôturé et
 * crée l'exercice suivant.
 *
 * @return array{resultat:float, produits:float, charges:float, nb_lignes:int, ecriture_id:int, next_code:?string}
 */
function clotureExercice(PDO $db, int $exerciceId, int $userId): array {
    $st = $db->prepare("SELECT * FROM exercices WHERE id = ?");
    $st->execute([$exerciceId]);
    $ex = $st->fetch();
    if (!$ex) throw new Exception('Exercice introuvable.');
    if ((int)$ex['cloture'] === 1) throw new Exception('Exercice déjà clôturé.');

    $debut = $ex['date_debut'];
    $fin   = $ex['date_fin'];

    // Soldes nets des comptes 6/7 sur la période (on exclut les écritures de clôture
    // pour garantir l'idempotence du calcul).
    $sql = "SELECT pc.id, pc.compte, pc.intitule, pc.classe,
                   COALESCE(SUM(el.debit),0) AS d, COALESCE(SUM(el.credit),0) AS c
            FROM plan_comptable pc
            JOIN ecriture_lignes el ON el.compte_id = pc.id
            JOIN ecritures e ON el.ecriture_id = e.id
            WHERE pc.classe IN (6,7) AND pc.actif = 1
              AND e.date_ecriture >= ? AND e.date_ecriture <= ?
              AND e.source <> 'cloture'
            GROUP BY pc.id, pc.compte, pc.intitule, pc.classe
            ORDER BY pc.compte";
    $st2 = $db->prepare($sql);
    $st2->execute([$debut, $fin]);
    $rows = $st2->fetchAll();

    $compte12 = compteFindOrCreate($db, '12', 'Résultat de l\'exercice', 1, 'credit');
    $lignes = [];
    $totalProduits = 0.0;
    $totalCharges  = 0.0;

    foreach ($rows as $r) {
        $classe = (int)$r['classe'];
        $net = (float)$r['d'] - (float)$r['c'];   // classe 6 : débiteur ; classe 7 : -(crédit)
        if ($classe === 6) {
            $montant = $net;                        // solde débiteur de la charge
            if (abs($montant) < 0.01) continue;
            $totalCharges += $montant;
            $lignes[] = [(int)$r['id'], 0, round($montant, 2), 'Solde charge ' . $r['compte'] . ' (clôture)'];
        } else { // classe 7
            $montant = -$net;                       // solde créditeur du produit
            if (abs($montant) < 0.01) continue;
            $totalProduits += $montant;
            $lignes[] = [(int)$r['id'], round($montant, 2), 0, 'Solde produit ' . $r['compte'] . ' (clôture)'];
        }
    }

    // Le compte 12 absorbe la différence : D 12 = totalCharges, C 12 = totalProduits.
    if ($totalCharges > 0)  $lignes[] = [$compte12, round($totalCharges, 2), 0, 'Solde charges (clôture)'];
    if ($totalProduits > 0) $lignes[] = [$compte12, 0, round($totalProduits, 2), 'Solde produits (clôture)'];

    if (count($lignes) < 2) {
        throw new Exception('Aucun mouvement à solder sur les classes 6/7 pour cet exercice.');
    }

    $eid = ecritureCreate($db,
        'Clôture exercice ' . $ex['code'] . ' — détermination du résultat',
        $fin,
        $lignes,
        'cloture', 'CLO-' . $ex['code'], $userId
    );

    // Verrouiller toutes les écritures de l'exercice (clôture incluse).
    $db->prepare("UPDATE ecritures SET verrouillee = 1 WHERE exercice_id = ?")->execute([$exerciceId]);
    $db->prepare("UPDATE ecritures SET verrouillee = 1 WHERE id = ?")->execute([$eid]);

    // Marquer l'exercice clôturé.
    $db->prepare("UPDATE exercices SET cloture = 1 WHERE id = ?")->execute([$exerciceId]);

    // Créer l'exercice suivant s'il n'existe pas.
    $nextDebut = date('Y-m-d', strtotime($fin . ' +1 day'));
    $nextFin   = date('Y-m-d', strtotime($nextDebut . ' +1 year -1 day'));
    $nextCode  = date('Y', strtotime($nextDebut));
    $chk = $db->prepare("SELECT id FROM exercices WHERE code = ?");
    $chk->execute([$nextCode]);
    $nextCodeOut = null;
    if (!$chk->fetch()) {
        $db->prepare("INSERT INTO exercices (code, libelle, date_debut, date_fin) VALUES (?,?,?,?)")
           ->execute([$nextCode, 'Exercice ' . $nextCode, $nextDebut, $nextFin]);
        $nextCodeOut = $nextCode;
    }

    return [
        'resultat'    => round($totalProduits - $totalCharges, 2),
        'produits'    => round($totalProduits, 2),
        'charges'     => round($totalCharges, 2),
        'nb_lignes'   => count($lignes),
        'ecriture_id' => $eid,
        'next_code'   => $nextCodeOut,
    ];
}
