<?php
// ============================================================
//  MediCore ERP - Comptabilité SYSCOHADA (helpers + mappings)
//  Dégradation gracieuse : si compta_ecritures absente, tout helper
//  court-circuite silencieusement (les modules existants restent OK).
// ============================================================

/** True si la table compta_ecritures existe (migration v9 jouée). */
function compta_enabled(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        getDB()->query("SELECT 1 FROM compta_ecritures LIMIT 1");
        $ok = true;
    } catch (Exception $e) {
        $ok = false;
    }
    return $ok;
}

/** ID de l'exercice OUVERT couvrant $date (Y-m-d), sinon 0. */
function compta_exercice_id(string $date): int {
    if (!compta_enabled()) return 0;
    return (int)db_scalar(
        "SELECT id FROM compta_exercices WHERE ? BETWEEN date_debut AND date_fin AND statut='ouvert' LIMIT 1",
        [$date]
    );
}

/** ID d'un compte par son numéro. Lève si introuvable. */
function compta_compte_id(string $numero): int {
    $id = (int)db_scalar("SELECT id FROM compta_comptes WHERE numero=? AND statut='actif' LIMIT 1", [$numero]);
    if (!$id) throw new RuntimeException("Compte comptable introuvable: $numero");
    return $id;
}

/** Numéro séquentiel d'écriture par journal/année (ex VTE-2026-00001). */
function compta_next_numero(string $journal_code, int $annee): string {
    $n = (int)db_scalar(
        "SELECT COUNT(*) FROM compta_ecritures e JOIN compta_journaux j ON j.id=e.journal_id
         WHERE j.code=? AND YEAR(e.date_ecriture)=?",
        [$journal_code, $annee]
    );
    return $journal_code . '-' . $annee . '-' . str_pad((string)($n + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * Génère une écriture équilibrée (statut 'validee').
 * $lignes = [['compte'=>'571','debit'=>5000.0,'credit'=>0.0,'tiers'=>'...'], ...]
 * Lève si : déséquilibré, compte absent, exercice clôturé, exercice introuvable.
 * Retourne 0 si compta désactivée ou flux nul.
 */
function compta_generer_ecriture(string $journal_code, string $date, string $libelle, array $lignes, ?string $source_table = null, ?int $source_id = null, ?int $utilisateur_id = null): int {
    if (!compta_enabled()) return 0;
    if (!$lignes) return 0;

    $totalD = 0.0; $totalC = 0.0;
    foreach ($lignes as $l) {
        $totalD += (float)($l['debit'] ?? 0);
        $totalC += (float)($l['credit'] ?? 0);
    }
    if (abs($totalD - $totalC) > 0.005) {
        throw new RuntimeException("Écriture déséquilibrée (débit=$totalD, crédit=$totalC) : $libelle");
    }
    if ($totalD == 0 && $totalC == 0) return 0; // flux nul, rien à booker

    $exo = db_row("SELECT id, statut FROM compta_exercices WHERE ? BETWEEN date_debut AND date_fin LIMIT 1", [$date]);
    if (!$exo) throw new RuntimeException("Aucun exercice pour la date $date");
    if ($exo['statut'] === 'cloture') throw new RuntimeException("Exercice clôturé : écriture refusée pour la date $date");
    $exercice_id = (int)$exo['id'];

    $journal = db_row("SELECT id FROM compta_journaux WHERE code=? LIMIT 1", [$journal_code]);
    if (!$journal) throw new RuntimeException("Journal inconnu: $journal_code");

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $numero = compta_next_numero($journal_code, (int)substr($date, 0, 4));
        $eid = db_exec(
            "INSERT INTO compta_ecritures (numero, date_ecriture, journal_id, exercice_id, libelle, source_table, source_id, utilisateur_id, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'validee')",
            [$numero, $date, $journal['id'], $exercice_id, $libelle, $source_table, $source_id, $utilisateur_id]
        );
        foreach ($lignes as $l) {
            $cid = compta_compte_id($l['compte']);
            db_exec(
                "INSERT INTO compta_ecriture_lignes (ecriture_id, compte_id, debit, credit, tiers_libelle) VALUES (?, ?, ?, ?, ?)",
                [$eid, $cid, (float)($l['debit'] ?? 0), (float)($l['credit'] ?? 0), $l['tiers'] ?? null]
            );
        }
        $pdo->commit();
        return (int)$eid;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Écriture miroir inversée pour annuler un flux (jamais de DELETE).
 * Retourne l'id de l'écriture miroir, ou null si aucune écriture originale n'existe.
 */
function compta_annuler_ecriture(string $source_table, int $source_id, ?int $utilisateur_id = null): ?int {
    if (!compta_enabled()) return null;
    $orig = db_row(
        "SELECT id, numero, date_ecriture, journal_id, libelle FROM compta_ecritures
         WHERE source_table=? AND source_id=? AND statut='validee' AND libelle NOT LIKE 'ANNULATION%' ORDER BY id DESC LIMIT 1",
        [$source_table, $source_id]
    );
    if (!$orig) return null;

    $lignes = db_select(
        "SELECT compte_id, debit, credit, tiers_libelle FROM compta_ecriture_lignes WHERE ecriture_id=?",
        [$orig['id']]
    );
    if (!$lignes) return null;

    // Inverse débit/crédit
    $mirror = [];
    foreach ($lignes as $l) {
        $numero = db_scalar("SELECT numero FROM compta_comptes WHERE id=?", [$l['compte_id']]);
        $mirror[] = [
            'compte' => $numero,
            'debit'  => (float)$l['credit'],
            'credit' => (float)$l['debit'],
            'tiers'  => $l['tiers_libelle'],
        ];
    }
    $journal_code = db_scalar("SELECT code FROM compta_journaux WHERE id=?", [$orig['journal_id']]);
    return compta_generer_ecriture(
        $journal_code,
        $orig['date_ecriture'],
        'ANNULATION — ' . $orig['libelle'],
        $mirror,
        $source_table,
        $source_id,
        $utilisateur_id
    ) ?: null;
}

// ══ Wrappers par flux (mappings SYSCOHADA) ═══════════════════

/** Renvoie le compte de trésorerie selon le mode de paiement. */
function compta_compte_tresorerie(string $mode): string {
    switch ($mode) {
        case 'especes':
        case 'mobile_money': return '571'; // Caisse
        case 'carte':
        case 'cheque':
        case 'virement':    return '521'; // Banque
        case 'assurance':    return '426'; // Assureur (tiers)
        case 'gratuit':      return '658'; // Autres charges
        default:             return '571';
    }
}

/** Ticket caisse (vente médicament OU ticket service) — statut paye. */
function compta_on_vente_caisse(array $v): ?int {
    if (!compta_enabled()) return null;
    $montant = (float)($v['montant_total'] ?? 0);
    if ($montant <= 0) return null; // flux nul : pas d'écriture

    $mode = $v['mode_paiement'] ?? 'especes';
    $type = $v['type_vente'] ?? 'medicament';
    $credit = ($type === 'medicament') ? '701' : '706'; // marchandises vs prestations
    $debit = compta_compte_tresorerie($mode);

    $tiers = trim(($v['patient_nom'] ?? '') . ' ' . ($v['numero_ticket'] ?? ''));
    return compta_generer_ecriture(
        'CA',
        substr((string)($v['date_vente'] ?? date('Y-m-d H:i:s')), 0, 10),
        'Ticket caisse ' . ($v['numero_ticket'] ?? '') . ' — ' . ($type === 'medicament' ? 'vente médicaments' : $type),
        [
            ['compte' => $debit,  'debit' => $montant, 'credit' => 0, 'tiers' => $tiers],
            ['compte' => $credit, 'debit' => 0, 'credit' => $montant, 'tiers' => $tiers],
        ],
        'caisse_ventes',
        (int)($v['id'] ?? 0),
        $v['caissier_id'] ?? null
    ) ?: null;
}

/** Facture : création (reconnaissance revenu + créances) OU règlement (encaissement). */
function compta_on_facture(array $f, string $etape): ?int {
    if (!compta_enabled()) return null;
    $date = substr((string)($f['date_emission'] ?? date('Y-m-d H:i:s')), 0, 10);

    if ($etape === 'creation') {
        $total = (float)($f['montant_total'] ?? 0);
        if ($total <= 0) return null;
        $partPatient = (float)($f['montant_patient'] ?? 0);
        $partAssur   = (float)($f['montant_assurance'] ?? 0);
        $tiers = 'Facture ' . ($f['numero'] ?? '') . ' — ' . ($f['patient_nom'] ?? '');
        $lignes = [];
        if ($partPatient > 0)  $lignes[] = ['compte'=>'411','debit'=>$partPatient,'credit'=>0,'tiers'=>$tiers];
        if ($partAssur > 0)   $lignes[] = ['compte'=>'426','debit'=>$partAssur,'credit'=>0,'tiers'=>$tiers];
        $lignes[] = ['compte'=>'706','debit'=>0,'credit'=>$total,'tiers'=>$tiers];
        return compta_generer_ecriture('VTE', $date, 'Facture ' . ($f['numero'] ?? '') . ' (création)', $lignes, 'factures', (int)($f['id'] ?? 0), $f['created_by'] ?? null) ?: null;
    }

    if ($etape === 'reglement') {
        $partPatient = (float)($f['montant_patient'] ?? 0);
        if ($partPatient <= 0) return null; // rien à encaisser côté patient
        $mode = $f['mode_paiement'] ?? 'especes';
        $debit = compta_compte_tresorerie($mode);
        $journal = ($debit === '521') ? 'BQ' : 'CA';
        $tiers = 'Règlement facture ' . ($f['numero'] ?? '');
        $dateRegl = substr((string)($f['date_reglement'] ?? date('Y-m-d H:i:s')), 0, 10);
        return compta_generer_ecriture(
            $journal, $dateRegl, 'Encaissement facture ' . ($f['numero'] ?? ''),
            [
                ['compte'=>$debit,'debit'=>$partPatient,'credit'=>0,'tiers'=>$tiers],
                ['compte'=>'411','debit'=>0,'credit'=>$partPatient,'tiers'=>$tiers],
            ],
            'factures', (int)($f['id'] ?? 0), $f['regle_par'] ?? null
        ) ?: null;
    }
    return null;
}

/** Entrée stock validée (achat). Compte de stock selon type. */
function compta_on_achat_stock(array $e): ?int {
    if (!compta_enabled()) return null;
    $montant = (float)($e['montant_total'] ?? 0);
    if ($montant <= 0) return null;
    $stock = ($e['type'] ?? 'stock') === 'medicament' ? '360' : '300';
    $tiers = 'Achat ' . ($e['reference'] ?? '') . ' — ' . ($e['fournisseur'] ?? '');
    $date = (string)($e['date_reception'] ?? date('Y-m-d'));
    return compta_generer_ecriture(
        'ACH', substr($date, 0, 10), 'Entrée stock ' . ($e['reference'] ?? ''),
        [
            ['compte'=>$stock,'debit'=>$montant,'credit'=>0,'tiers'=>$tiers],
            ['compte'=>'401','debit'=>0,'credit'=>$montant,'tiers'=>$tiers],
        ],
        'stock_entries', (int)($e['id'] ?? 0), $e['utilisateur_id'] ?? null
    ) ?: null;
}

/** Annulation ticket caisse : écriture miroir. */
function compta_on_annulation_vente(array $v): ?int {
    return compta_annuler_ecriture('caisse_ventes', (int)($v['id'] ?? 0), $v['caissier_id'] ?? null);
}