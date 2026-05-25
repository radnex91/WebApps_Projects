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

    foreach ($balance as $c) {
        $solde = (float)$c['total_debit'] - (float)$c['total_credit'];
        if (abs($solde) < 0.01) continue;

        $classe = (int)$c['classe'];

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
