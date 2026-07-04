# Comptabilité SYSCOHADA + Traçabilité financière — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un module Comptabilité complet (plan comptable SYSCOHADA, partie double, journaux, grand livre, balance, bilan, compte de résultat, clôture d'exercice) à MediCore ERP, avec génération automatique des écritures depuis les flux financiers existants (caisse, factures, achats) et backfill de l'historique.

**Architecture:** Nouvelles tables `compta_*` (migration idempotente `sql/update_v9.sql`) + include `includes/comptabilite.php` exposant des helpers de génération d'écritures en partie double. Les modules existants (`caisse.php`, `facturation.php`, `stocks.php`) sont instrumentés pour appeler ces helpers après leurs INSERT existants. Un script `sql/backfill_compta.php` remonte l'historique de façon idempotente. La page `pages/comptabilite.php` présente 8 vues (dashboard, journaux, grand livre, balance, bilan, compte de résultat, plan comptable, exercices). RBAC via le système existant.

**Tech Stack:** PHP 7.4+ vanilla, MySQL via PDO (helpers `db_select`/`db_row`/`db_scalar`/`db_exec`), CSS `assets/css/main.css`, JS `assets/js/app.js`. Pas de framework, pas de build.

## Global Constraints

- **Convention de code** : comments en français, UI en français (CLAUDE.md).
- **Accès DB** : exclusivement `db_select`/`db_row`/`db_scalar`/`db_exec` avec prepared statements — jamais de PDO brut ni de concaténation SQL.
- **Sécurité** : tout POST appelle `csrf_verify()` avant traitement ; toute sortie utilise `h()` / `htmlspecialchars()` ; les liens vers enregistrements utilisent `secure_url()` / `get_signed_id()`.
- **Migrations idempotentes** : chaque `ALTER`/`CREATE` gardé par `INFORMATION_SCHEMA` + `PREPARE/EXECUTE` (pattern `update_v5/6/7/8.sql`). Re-exécutables sans erreur.
- **Migration exécutée** via `/c/xampp/mysql/bin/mysql.exe -u root medicore < sql/update_vN.sql`.
- **Pas de framework de tests** : vérifier avec `php -l` + scénarios fonctionnels manuels + idempotence (migration/backfill joués 2×).
- **Devise** : FCFA, `fmt_money()` existant.
- **Dégradation gracieuse** : tout helper comptable détecte l'absence des tables `compta_*` et court-circute silencieusement (les modules existants doivent continuer à fonctionner sans la migration).
- **Immuabilité comptable** : les annulations génèrent une écriture miroir (jamais de DELETE/UPDATE d'une écriture existante).
- **Cache permissions** : appeler `invalidate_permissions_cache()` après tout seed RBAC ; l'utilisateur doit se reconnecter.
- **Branche** : travailler sur une branche `feat/comptabilite` créée depuis `main`.

**Interfaces partagées (référence — définies dans Task 2, utilisées par 3+)**

```php
// includes/comptabilite.php
function compta_enabled(): bool;                              // true si compta_ecritures existe
function compta_exercice_id(string $date): int;              // id exercice ouvert couvrant $date, sinon 0
function compta_compte_id(string $numero): int;              // id compte par numero, lève Exception si absent
function compta_next_numero(string $journal_code, int $annee): string; // ex "VTE-2026-00001"
function compta_generer_ecriture(string $journal_code, string $date, string $libelle, array $lignes, ?string $source_table, ?int $source_id, ?int $utilisateur_id): int; // id écriture ; lève si déséquilibré / exercice clôturé / compte absent
function compta_annuler_ecriture(string $source_table, int $source_id, ?int $utilisateur_id): ?int; // id écriture miroir, ou null si pas d'écriture originale
function compta_on_vente_caisse(array $vente): ?int;         // wrapper ticket caisse (paye)
function compta_on_facture(array $facture, string $etape): ?int; // $etape = 'creation' | 'reglement'
function compta_on_achat_stock(array $entry): ?int;          // wrapper entrée stock validée
function compta_on_annulation_vente(array $vente): ?int;     // wrapper annulation ticket caisse
```

Format `$lignes` : `[['compte'=>'571','debit'=>5000.0,'credit'=>0.0,'tiers'=>'Patient ...'], ...]`. ∑débit doit égaler ∑crédit.

---

## Task 1: Migration `sql/update_v9.sql` — tables + seed plan + journaux + exercices + RBAC

**Files:**
- Create: `sql/update_v9.sql`
- Modify: `sql/medicore.sql` (note d'installation)

**Interfaces:**
- Produces: tables `compta_exercices`, `compta_journaux`, `compta_comptes`, `compta_ecritures`, `compta_ecriture_lignes` ; seed plan SYSCOHADA (~40 comptes) ; 5 journaux ; exercices 2024-2026 ; permissions RBAC (`comptabilite` page + 4 actions) pour `admin` et `comptable`.

- [ ] **Step 1: Créer la branche**

```bash
cd /c/xampp/htdocs/medicore
git checkout -b feat/comptabilite
```

- [ ] **Step 2: Écrire `sql/update_v9.sql`**

```sql
-- ============================================================
--  MediCore ERP — Migration v9
--  Comptabilité SYSCOHADA + traçabilité financière.
--  Idempotent : chaque création/ALTER/INSERT est gardé.
-- ============================================================

-- ── 1. compta_exercices ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_exercices');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_exercices` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `annee` INT(11) NOT NULL,
     `date_debut` DATE NOT NULL,
     `date_fin` DATE NOT NULL,
     `statut` ENUM(''ouvert'',''cloture'') NOT NULL DEFAULT ''ouvert'',
     `cloture_par` INT(11) DEFAULT NULL,
     `date_cloture` DATETIME DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_exercice_annee` (`annee`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. compta_comptes ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_comptes');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_comptes` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `numero` VARCHAR(10) NOT NULL,
     `libelle` VARCHAR(150) NOT NULL,
     `classe` TINYINT(2) NOT NULL,
     `type` ENUM(''actif'',''passif'',''charge'',''produit'',''tresorerie'',''tiers'') NOT NULL,
     `lettrable` TINYINT(1) DEFAULT 0,
     `parent_id` INT(11) DEFAULT NULL,
     `statut` ENUM(''actif'',''inactif'') NOT NULL DEFAULT ''actif'',
     `position` INT(11) DEFAULT 0,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_compte_numero` (`numero`),
     KEY `idx_compte_classe` (`classe`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. compta_journaux ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_journaux');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_journaux` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `code` VARCHAR(8) NOT NULL,
     `libelle` VARCHAR(100) NOT NULL,
     `type` ENUM(''ventes'',''achats'',''banque'',''caisse'',''od'') NOT NULL,
     `compte_contrepartie_id` INT(11) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_journal_code` (`code`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. compta_ecritures ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_ecritures');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_ecritures` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `numero` VARCHAR(30) NOT NULL,
     `date_ecriture` DATE NOT NULL,
     `journal_id` INT(11) NOT NULL,
     `exercice_id` INT(11) NOT NULL,
     `libelle` VARCHAR(255) NOT NULL,
     `source_table` VARCHAR(50) DEFAULT NULL,
     `source_id` INT(11) DEFAULT NULL,
     `utilisateur_id` INT(11) DEFAULT NULL,
     `statut` ENUM(''brouillon'',''validee'',''annulee'') NOT NULL DEFAULT ''validee'',
     `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_ecriture_numero` (`numero`),
     KEY `idx_ecriture_source` (`source_table`,`source_id`),
     KEY `idx_ecriture_journal` (`exercice_id`,`journal_id`),
     KEY `idx_ecriture_date` (`date_ecriture`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. compta_ecriture_lignes ──
SET @v := (SELECT COUNT(*) FROM information_schema.tables
           WHERE table_schema=DATABASE() AND table_name='compta_ecriture_lignes');
SET @s := IF(@v=0,
  'CREATE TABLE `compta_ecriture_lignes` (
     `id` INT(11) NOT NULL AUTO_INCREMENT,
     `ecriture_id` INT(11) NOT NULL,
     `compte_id` INT(11) NOT NULL,
     `debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
     `credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
     `tiers_libelle` VARCHAR(150) DEFAULT NULL,
     PRIMARY KEY (`id`),
     KEY `idx_ligne_ecriture` (`ecriture_id`),
     KEY `idx_ligne_compte` (`compte_id`),
     CONSTRAINT `fk_ligne_ecriture` FOREIGN KEY (`ecriture_id`) REFERENCES `compta_ecritures` (`id`) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 6. Seed : plan comptable SYSCOHADA réduit ──
INSERT IGNORE INTO `compta_comptes` (`numero`,`libelle`,`classe`,`type`,`position`) VALUES
('101','Capital',1,'passif',10),
('120','Résultat de l''exercice',1,'passif',20),
('130','Subventions d''équipement',1,'passif',30),
('200','Immobilisations incorporelles',2,'actif',100),
('210','Terrains',2,'actif',110),
('220','Constructions',2,'actif',120),
('240','Matériel et outillage',2,'actif',130),
('280','Amortissements',2,'passif',140),
('300','Stocks de marchandises',3,'actif',200),
('310','Stocks de matières premières',3,'actif',210),
('360','Stocks de médicaments',3,'actif',220),
('401','Fournisseurs',4,'tiers',300),
('411','Clients',4,'tiers',310),
('420','Personnel',4,'tiers',320),
('426','Assureurs',4,'tiers',330),
('440','État',4,'tiers',340),
('470','Comptes d''attente',4,'tiers',350),
('521','Banque',5,'tresorerie',400),
('571','Caisse',5,'tresorerie',410),
('580','Virements internes',5,'tresorerie',420),
('601','Achats de marchandises',6,'charge',500),
('605','Autres achats',6,'charge',510),
('610','Transports',6,'charge',520),
('630','Charges financières',6,'charge',530),
('658','Autres charges',6,'charge',540),
('701','Ventes de marchandises',7,'produit',600),
('706','Prestations de services',7,'produit',610),
('707','Ventes et produits accessoires',7,'produit',620),
('758','Autres produits',7,'produit',630);

-- ── 7. Seed : journaux ──
INSERT IGNORE INTO `compta_journaux` (`code`,`libelle`,`type`,`compte_contrepartie_id`) VALUES
('VTE','Journal des ventes','ventes',(SELECT id FROM compta_comptes WHERE numero='411')),
('ACH','Journal des achats','achats',(SELECT id FROM compta_comptes WHERE numero='401')),
('BQ','Journal de banque','banque',(SELECT id FROM compta_comptes WHERE numero='521')),
('CA','Journal de caisse','caisse',(SELECT id FROM compta_comptes WHERE numero='571')),
('OD','Opérations diverses','od',NULL);

-- ── 8. Seed : exercices 2024-2026 (ouverts pour backfill) ──
INSERT IGNORE INTO `compta_exercices` (`annee`,`date_debut`,`date_fin`,`statut`) VALUES
(2024,'2024-01-01','2024-12-31','ouvert'),
(2025,'2025-01-01','2025-12-31','ouvert'),
(2026,'2026-01-01','2026-12-31','ouvert');

-- ── 9. RBAC : page + actions (idempotent via INSERT IGNORE / NOT EXISTS) ──
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`,`updated_at`)
SELECT 'admin','comptabilite',1,NOW() WHERE NOT EXISTS (SELECT 1 FROM role_page_access WHERE role='admin' AND page='comptabilite');
INSERT IGNORE INTO `role_page_access` (`role`,`page`,`allowed`,`updated_at`)
SELECT 'comptable','comptabilite',1,NOW() WHERE NOT EXISTS (SELECT 1 FROM role_page_access WHERE role='comptable' AND page='comptabilite');

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'admin',a.act,1,NOW()
FROM (SELECT 'compta.consulter' AS act UNION SELECT 'compta.saisir' UNION SELECT 'compta.cloturer' UNION SELECT 'compta.param_comptes') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='admin' AND ra.action=a.act);

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'comptable',a.act,1,NOW()
FROM (SELECT 'compta.consulter' AS act UNION SELECT 'compta.saisir') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='comptable' AND ra.action=a.act);

INSERT IGNORE INTO `role_action_access` (`role`,`action`,`allowed`,`updated_at`)
SELECT 'comptable',a.act,0,NOW()
FROM (SELECT 'compta.cloturer' AS act UNION SELECT 'compta.param_comptes') a
WHERE NOT EXISTS (SELECT 1 FROM role_action_access ra WHERE ra.role='comptable' AND ra.action=a.act);
```

- [ ] **Step 3: Jouer la migration une 1re fois**

Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore < /c/xampp/htdocs/medicore/sql/update_v9.sql && echo OK`
Expected: `OK` sans erreur.

- [ ] **Step 4: Vérifier le schéma**

Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore -e "SELECT COUNT(*) AS nb_comptes FROM compta_comptes; SELECT COUNT(*) AS nb_journaux FROM compta_journaux; SELECT annee,statut FROM compta_exercices ORDER BY annee;"`
Expected: `nb_comptes = 29`, `nb_journaux = 5`, 3 exercices (2024/2025/2026, statut `ouvert`).

- [ ] **Step 5: Re-jouer la migration (idempotence)**

Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore < /c/xampp/htdocs/medicore/sql/update_v9.sql && echo OK_RERUN`
Expected: `OK_RERUN` ; re-vérifier que `nb_comptes` reste 29 (aucun doublon).

- [ ] **Step 6: Mettre à jour la note d'installation dans `sql/medicore.sql`**

Modifier la ligne (~655) :
```sql
-- Modules v3+ : lancer aussi sql/update_v3.sql, ..., update_v8.sql, update_v9.sql pour les nouveaux modules
```

- [ ] **Step 7: Commit**

```bash
git add sql/update_v9.sql sql/medicore.sql
git commit -m "feat(compta): migration v9 — tables compta_* + seed plan SYSCOHADA + journaux + exercices + RBAC

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Include `includes/comptabilite.php` — helpers + mappings

**Files:**
- Create: `includes/comptabilite.php`

**Interfaces:**
- Consumes: tables de Task 1 ; helpers `db_select`/`db_row`/`db_scalar`/`db_exec` (security.php) ; `currentUser()` (auth.php).
- Produces: toutes les fonctions listées dans le bloc **Interfaces partagées** du header — utilisées par Task 3 (instrumentation + backfill) et Task 4-6 (page).

- [ ] **Step 1: Écrire `includes/comptabilite.php`**

```php
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
 * Refus si compta désactivée (retourne 0).
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

    $exercice_id = compta_exercice_id($date);
    if (!$exercice_id) {
        throw new RuntimeException("Aucun exercice ouvert pour la date $date");
    }

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
         WHERE source_table=? AND source_id=? AND statut='validee' ORDER BY id DESC LIMIT 1",
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
    return compta_generer_ecriture(
        db_scalar("SELECT code FROM compta_journaux WHERE id=?", [$orig['journal_id']]),
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
```

- [ ] **Step 2: Lint**

Run: `php -l C:/xampp/htdocs/medicore/includes/comptabilite.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke test rapide (boot du helper)**

Run: `php -r "define('APP_URL',''); require 'C:/xampp/htdocs/medicore/includes/config.php'; require 'C:/xampp/htdocs/medicore/includes/comptabilite.php'; echo compta_enabled()?'ENABLED':'DISABLED'; echo PHP_EOL;"`
Expected: `ENABLED` (la migration v9 est jouée).

- [ ] **Step 4: Test fonctionnel — générer une écriture à la main**

Run:
```bash
php -r "
define('APP_URL','');
require 'C:/xampp/htdocs/medicore/includes/config.php';
require 'C:/xampp/htdocs/medicore/includes/comptabilite.php';
\$id = compta_generer_ecriture('CA', date('Y-m-d'), 'Test manuel', [
  ['compte'=>'571','debit'=>1000,'credit'=>0,'tiers'=>'Test'],
  ['compte'=>'701','debit'=>0,'credit'=>1000,'tiers'=>'Test'],
], null, null, 1);
echo \"Ecriture ID=\$id\".PHP_EOL;
\$bal = db_scalar('SELECT SUM(debit)-SUM(credit) FROM compta_ecriture_lignes WHERE ecriture_id=?', [\$id]);
echo \"Balance ligne=\$bal\".PHP_EOL;
db_exec('DELETE FROM compta_ecritures WHERE id=?', [\$id]);
echo 'Cleaned'.PHP_EOL;
"
```
Expected: `Ecriture ID=N`, `Balance ligne=0` (équilibre), `Cleaned`.

- [ ] **Step 5: Test du garde-fou (écriture déséquilibrée doit échouer)**

Run:
```bash
php -r "
define('APP_URL','');
require 'C:/xampp/htdocs/medicore/includes/config.php';
require 'C:/xampp/htdocs/medicore/includes/comptabilite.php';
try { compta_generer_ecriture('CA', date('Y-m-d'), 'Bad', [['compte'=>'571','debit'=>1000,'credit'=>0]], null,null,null); echo 'NO_THROW'.PHP_EOL; }
catch (RuntimeException \$e) { echo 'OK_THROW: '.\$e->getMessage().PHP_EOL; }
"
```
Expected: `OK_THROW: Écriture déséquilibrée (débit=1000, crédit=0)...`

- [ ] **Step 6: Commit**

```bash
git add includes/comptabilite.php
git commit -m "feat(compta): helpers comptabilite (generation, annulation, mappings SYSCOHADA)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Instrumentation modules + backfill

**Files:**
- Modify: `pages/caisse.php` (create_vente, create_ticket_service, annuler)
- Modify: `pages/facturation.php` (create_facture, update_statut)
- Modify: `pages/stocks.php` (validation entrée, annulation)
- Create: `sql/backfill_compta.php`

**Interfaces:**
- Consumes: `compta_on_vente_caisse`, `compta_on_facture`, `compta_on_achat_stock`, `compta_on_annulation_vente`, `compta_annuler_ecriture` (Task 2).
- Produces: écritures auto-générées pour tout nouveau flux ; `sql/backfill_compta.php` remonte l'historique.

- [ ] **Step 1: Instrumenter `pages/caisse.php` — create_vente**

Dans `caisse.php`, juste après l'insert des `caisse_lignes` et **avant** `logActivity(... 'Ticket créé ...')` du bloc `create_vente` (~ligne 155), ajouter :
```php
    // Comptabilité : génère l'écriture si le ticket est payé
    if ($statut === 'paye' && function_exists('compta_on_vente_caisse')) {
        compta_on_vente_caisse([
            'id' => $venteId, 'numero_ticket' => $numeroTicket, 'type_vente' => 'medicament',
            'montant_total' => $total, 'mode_paiement' => $mode, 'date_vente' => date('Y-m-d H:i:s'),
            'patient_id' => $patientId, 'caissier_id' => currentUser()['id'],
        ]);
    }
```
(Conserver `$venteId = lastInsertId` — vérifier le nom de variable réellement utilisé dans `caisse.php` ; s'appuyer sur la variable contenant l'ID du ticket.)

- [ ] **Step 2: Instrumenter `pages/caisse.php` — create_ticket_service**

Après l'insert du ticket service (~ligne 219), avant `logActivity`, ajouter :
```php
    if ($statut === 'paye' && function_exists('compta_on_vente_caisse')) {
        compta_on_vente_caisse([
            'id' => $ticketId, 'numero_ticket' => $numeroTicket, 'type_vente' => $typeVente,
            'montant_total' => $montant, 'mode_paiement' => $mode, 'date_vente' => date('Y-m-d H:i:s'),
            'patient_id' => $patientId, 'caissier_id' => currentUser()['id'],
        ]);
    }
```

- [ ] **Step 3: Instrumenter `pages/caisse.php` — annuler**

Dans le bloc `annuler` (~ligne 251), après l'UPDATE `statut='annule'` et la restitution de stock, avant `logActivity`, ajouter :
```php
    if (function_exists('compta_on_annulation_vente')) {
        compta_on_annulation_vente(['id' => $id, 'caissier_id' => currentUser()['id']]);
    }
```

- [ ] **Step 4: Instrumenter `pages/facturation.php` — create_facture**

Après l'INSERT facture (~ligne 25), avant `logActivity`, ajouter :
```php
    if (function_exists('compta_on_facture')) {
        compta_on_facture([
            'id' => $factureId, 'numero' => $numero, 'patient_id' => $patientId,
            'montant_total' => $montantTotal, 'montant_assurance' => $montantAssurance,
            'montant_patient' => $montantPatient, 'date_emission' => date('Y-m-d H:i:s'),
        ], 'creation');
    }
```

- [ ] **Step 5: Instrumenter `pages/facturation.php` — update_statut**

Dans le bloc `update_statut` (~ligne 37), normaliser le statut et appeler le wrapper règlement :
```php
    // Normalisation du bug enum (reglee/réglée, impayee/impayée)
    $statutNorm = strtolower(trim($statut));
    $statutNorm = str_replace(['é','è'], ['e','e'], $statutNorm);
    db_exec("UPDATE factures SET statut=?, date_reglement=? WHERE id=?", [$statut, ($statutNorm==='reglee' ? date('Y-m-d H:i:s') : null), $id]);
    if ($statutNorm === 'reglee' && function_exists('compta_on_facture')) {
        $f = db_row("SELECT id, numero, montant_patient, date_reglement FROM factures WHERE id=?", [$id]);
        compta_on_facture($f + ['mode_paiement' => post_str('mode_paiement', 'especes'), 'regle_par' => currentUser()['id']], 'reglement');
    }
```
(Ajuster pour conserver la structure existante de la requête — remplacer la ligne `UPDATE factures SET statut=?, date_reglement=?` actuelle.)

- [ ] **Step 6: Instrumenter `pages/stocks.php` — validation entrée**

Dans le bloc de validation (`statut→validee`, ~ligne 136), après l'UPDATE, ajouter :
```php
    if (function_exists('compta_on_achat_stock')) {
        $e = db_row("SELECT id, reference, type, fournisseur, montant_total, date_reception, utilisateur_id FROM stock_entries WHERE id=?", [$id]);
        compta_on_achat_stock($e);
    }
```
Dans le bloc annulation entrée (~ligne 149), après l'UPDATE `statut='annulee'`, ajouter :
```php
    if (function_exists('compta_annuler_ecriture')) {
        compta_annuler_ecriture('stock_entries', $id, currentUser()['id']);
    }
```

- [ ] **Step 7: Lint des 3 fichiers modifiés**

Run: `php -l C:/xampp/htdocs/medicore/pages/caisse.php && php -l C:/xampp/htdocs/medicore/pages/facturation.php && php -l C:/xampp/htdocs/medicore/pages/stocks.php`
Expected: `No syntax errors detected` ×3.

- [ ] **Step 8: Écrire `sql/backfill_compta.php`**

```php
<?php
// ============================================================
//  MediCore ERP - Backfill comptable idempotent
//  Remonte l'historique des flux existants en écritures compta.
//  Idempotent : skip les sources déjà générées.
//  Usage : php sql/backfill_compta.php  (ou via navigateur)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';

if (!compta_enabled()) { echo "Compta désactivée (migration v9 requise).\n"; exit(1); }

$stats = ['caisse' => 0, 'factures' => 0, 'stocks' => 0, 'skip' => 0];

// 1. Caisse : tickets payés (hors annulés)
$ventes = db_select("SELECT v.*, cp.id AS compte_id FROM caisse_ventes v LEFT JOIN comptes_patients cp ON cp.patient_id=v.patient_id WHERE v.statut='paye' ORDER BY v.id ASC");
foreach ($ventes as $v) {
    $exists = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='caisse_ventes' AND source_id=? LIMIT 1", [$v['id']]);
    if ($exists) { $stats['skip']++; continue; }
    compta_on_vente_caisse($v);
    $stats['caisse']++;
}

// 1b. Caisse : tickets annulés -> écriture miroir (à la date de vente)
$annuls = db_select("SELECT * FROM caisse_ventes WHERE statut='annule' ORDER BY id ASC");
foreach ($annuls as $v) {
    $exists = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='caisse_ventes' AND source_id=? AND libelle LIKE 'ANNULATION%' LIMIT 1", [$v['id']]);
    if ($exists) { $stats['skip']++; continue; }
    // Génère d'abord l'écriture originale (payé->annulé : on book l'original puis la miroir)
    compta_on_vente_caisse($v);
    compta_on_annulation_vente($v);
    $stats['caisse'] += 2;
}

// 2. Factures : création + règlement
$facts = db_select("SELECT * FROM factures ORDER BY id ASC");
foreach ($facts as $f) {
    $existsCrea = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='factures' AND source_id=? AND libelle LIKE '%création%' LIMIT 1", [$f['id']]);
    if (!$existsCrea) { compta_on_facture($f, 'creation'); $stats['factures']++; }
    else { $stats['skip']++; }
    // Règlement si statut normalisé = reglee
    $statutNorm = str_replace(['é','è'], ['e','e'], strtolower(trim($f['statut'])));
    if ($statutNorm === 'reglee') {
        $existsRegl = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='factures' AND source_id=? AND libelle LIKE 'Encaissement%' LIMIT 1", [$f['id']]);
        if (!$existsRegl) {
            compta_on_facture($f + ['mode_paiement' => 'especes', 'regle_par' => null], 'reglement');
            $stats['factures']++;
        }
    }
}

// 3. Stocks : entrées validées
$entries = db_select("SELECT * FROM stock_entries WHERE statut='validee' ORDER BY id ASC");
foreach ($entries as $e) {
    $exists = db_scalar("SELECT 1 FROM compta_ecritures WHERE source_table='stock_entries' AND source_id=? LIMIT 1", [$e['id']]);
    if ($exists) { $stats['skip']++; continue; }
    compta_on_achat_stock($e);
    $stats['stocks']++;
}

echo "Backfill terminé : caisse={$stats['caisse']} factures={$stats['factures']} stocks={$stats['stocks']} skips={$stats['skip']}\n";
$total = (int)db_scalar("SELECT SUM(debit) FROM compta_ecriture_lignes");
echo "Total débit écritures = $total (doit égaler total crédit)\n";
```

- [ ] **Step 9: Lint backfill**

Run: `php -l C:/xampp/htdocs/medicore/sql/backfill_compta.php`
Expected: `No syntax errors detected`

- [ ] **Step 10: Jouer le backfill une 1re fois**

Run: `php C:/xampp/htdocs/medicore/sql/backfill_compta.php`
Expected: `Backfill terminé : caisse=X factures=Y stocks=Z skips=0` (skips=0 à la 1re passe) ; total débit = total crédit.

- [ ] **Step 11: Vérifier l'équilibre global**

Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore -e "SELECT SUM(debit) AS total_d, SUM(credit) AS total_c FROM compta_ecriture_lignes;"`
Expected: `total_d == total_c`.

- [ ] **Step 12: Re-jouer le backfill (idempotence — 2e passe)**

Run: `php C:/xampp/htdocs/medicore/sql/backfill_compta.php`
Expected: `caisse=0 factures=0 stocks=0 skips=N` (N = nombre de flux déjà bookés à la 1re passe).

- [ ] **Step 13: Test fonctionnel end-to-end — nouveau ticket caisse**

Dans l'app (navigateur, connecté comme admin/caissier), créer un ticket caisse paye. Puis vérifier en SQL :
Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore -e "SELECT e.numero, e.libelle, l.compte_id, c.numero AS compte, l.debit, l.credit FROM compta_ecritures e JOIN compta_ecriture_lignes l ON l.ecriture_id=e.id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.source_table='caisse_ventes' ORDER BY e.id DESC LIMIT 4;"`
Expected: 2 lignes (débit 571/521, crédit 701/706) équilibrées, `source_table='caisse_ventes'`, plus récentes que le backfill.

- [ ] **Step 14: Test — annulation génère une écriture miroir**

Annuler le ticket créé au Step 13. Re-vérifier en SQL :
Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore -e "SELECT e.numero, e.libelle FROM compta_ecritures e WHERE e.source_table='caisse_ventes' ORDER BY e.id DESC LIMIT 4;"`
Expected: une écriture `Ticket caisse ...` ET une écriture `ANNULATION — Ticket caisse ...` (l'original est conservé, pas de DELETE).

- [ ] **Step 15: Commit**

```bash
git add pages/caisse.php pages/facturation.php pages/stocks.php sql/backfill_compta.php
git commit -m "feat(compta): instrumentation caisse/facturation/stocks + backfill idempotent

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Page comptabilité — vues lecture (dashboard, journaux, grand livre, balance)

**Files:**
- Create: `pages/comptabilite.php`
- Modify: `includes/permissions.php` (ALL_PAGES + ALL_ACTIONS)
- Modify: `includes/icons.php` (ICON_NAV['comptabilite'])

**Interfaces:**
- Consumes: tables `compta_*` ; helpers `can()`, `requirePageAccess()`, `db_select/db_scalar`, `fmt_money`, `fmt_date`, `secure_url`, `h()`.
- Produces: page `comptabilite` accessible (admin + comptable), 4 onglets lecture.

- [ ] **Step 1: Enregistrer la page + actions dans `includes/permissions.php`**

Dans `ALL_PAGES` (après `'facturation'`), ajouter :
```php
'comptabilite' => ['label'=>"Comptabilité", 'icon'=>'comptabilite', 'section'=>'Administration'],
```
Dans `ALL_ACTIONS`, ajouter (avant la fermeture) :
```php
'compta.consulter'      => ['label'=>"Consulter la comptabilité", 'module'=>'comptabilite'],
'compta.saisir'         => ['label'=>"Saisir une écriture manuelle", 'module'=>'comptabilite'],
'compta.cloturer'       => ['label'=>"Clôturer un exercice", 'module'=>'comptabilite'],
'compta.param_comptes'  => ['label'=>"Paramétrer le plan comptable", 'module'=>'comptabilite'],
```

- [ ] **Step 2: Ajouter l'icône dans `includes/icons.php`**

Dans le tableau `ICON_NAV`, ajouter :
```php
'comptabilite' => '🧾',
```

- [ ] **Step 3: Lint permissions + icons**

Run: `php -l C:/xampp/htdocs/medicore/includes/permissions.php && php -l C:/xampp/htdocs/medicore/includes/icons.php`
Expected: `No syntax errors detected` ×2.

- [ ] **Step 4: Écrire `pages/comptabilite.php` — squelette + onglets lecture**

```php
<?php
$currentPage = 'comptabilite';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/comptabilite.php';
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('comptabilite');

if (!compta_enabled()) {
    echo '<div class="alert alert-red">Module comptabilité non installé. Exécutez sql/update_v9.sql.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$tab = in_whitelist(get_str('tab'), ['dashboard','journaux','gl','balance','bilan','cr','plan','exercices'], 'dashboard');
$exercice = (int)get_str('exercice', date('Y'));
$dateDebut = get_str('du') ?: date('Y-01-01');
$dateFin   = get_str('au') ?: date('Y-12-31');

// ── KPIs tableau de bord (période) ──
$recettes  = (float)db_scalar("SELECT COALESCE(SUM(l.credit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=7 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut,$dateFin]);
$depenses  = (float)db_scalar("SELECT COALESCE(SUM(l.debit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=6 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut,$dateFin]);
$treso     = (float)db_scalar("SELECT COALESCE(SUM(l.debit)-SUM(l.credit),0) FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=5 AND e.date_ecriture BETWEEN ? AND ?", [$dateDebut,$dateFin]);
$resultat  = $recettes - $depenses;
$exercices = db_select("SELECT annee, statut FROM compta_exercices ORDER BY annee DESC");
?>
<div class="page-header-row">
  <div><h2>🧾 Comptabilité</h2><p>Synthèse financière SYSCOHADA — exercice <?= $exercice ?></p></div>
</div>

<div class="tab-bar" style="display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach (['dashboard'=>'Tableau de bord','journaux'=>'Journaux','gl'=>'Grand livre','balance'=>'Balance','bilan'=>'Bilan','cr'=>'Compte de résultat','plan'=>'Plan comptable','exercices'=>'Exercices'] as $t=>$l): ?>
    <a href="?tab=<?= $t ?>" class="portail-tab <?= $tab===$t?'active':'' ?>" style="padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:<?= $tab===$t?'var(--accent)':'var(--bg)' ?>;color:<?= $tab===$t?'#fff':'var(--text2)' ?>;font-size:13px;font-weight:500;cursor:pointer"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <input type="hidden" name="tab" value="<?= h($tab) ?>">
  <label style="font-size:12px;color:var(--text3)">Exercice</label>
  <select name="exercice" onchange="this.form.submit()" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
    <?php foreach ($exercices as $ex): ?><option value="<?= $ex['annee'] ?>" <?= $exercice==(int)$ex['annee']?'selected':'' ?>><?= $ex['annee'] ?> (<?= $ex['statut'] ?>)</option><?php endforeach; ?>
  </select>
  <input type="date" name="du" value="<?= h($dateDebut) ?>" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
  <input type="date" name="au" value="<?= h($dateFin) ?>" style="padding:6px 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text)">
  <button class="btn btn-blue btn-sm">Filtrer</button>
</form>

<?php if ($tab === 'dashboard'): ?>
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px">
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Recettes (classe 7)</div><div style="font-size:22px;font-weight:700;color:var(--green)"><?= fmt_money($recettes) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Dépenses (classe 6)</div><div style="font-size:22px;font-weight:700;color:var(--red)"><?= fmt_money($depenses) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Résultat net</div><div style="font-size:22px;font-weight:700;color:<?= $resultat>=0?'var(--green)':'var(--red)' ?>"><?= fmt_money($resultat) ?></div></div>
  <div class="card" style="padding:16px"><div style="color:var(--text3);font-size:11px;text-transform:uppercase">Trésorerie (classe 5)</div><div style="font-size:22px;font-weight:700"><?= fmt_money($treso) ?></div></div>
</div>

<?php
// Évolution 12 mois
$evol = db_select("SELECT DATE_FORMAT(e.date_ecriture,'%Y-%m') AS m, SUM(l.debit) AS debits, SUM(l.credit) AS credits
  FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id
  WHERE e.date_ecriture >= DATE_SUB(?, INTERVAL 11 MONTH)
  GROUP BY m ORDER BY m", [$dateFin]);
?>
<div class="card"><div class="card-header"><h3>Évolution 12 mois</h3></div>
<table><thead><tr><th>Mois</th><th>Débits</th><th>Crédits</th></tr></thead><tbody>
<?php foreach ($evol as $r): ?><tr><td><?= h($r['m']) ?></td><td><?= fmt_money($r['debits']) ?></td><td><?= fmt_money($r['credits']) ?></td></tr><?php endforeach; ?>
<?php if (!$evol): ?><tr><td colspan="3" style="text-align:center;padding:16px;color:var(--text3)">Aucun mouvement.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($tab === 'journaux'):
  $journal = get_str('journal') ?: 'CA';
  $rows = db_select("SELECT e.*, u.nom AS user_nom FROM compta_ecritures e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id JOIN compta_journaux j ON j.id=e.journal_id WHERE j.code=? AND e.date_ecriture BETWEEN ? AND ? ORDER BY e.date_ecriture DESC, e.id DESC LIMIT 200", [$journal,$dateDebut,$dateFin]);
?>
<div class="card"><div class="card-header"><h3>Journal <?= h($journal) ?> (200 dernières écritures)</h3></div>
<table><thead><tr><th>Numéro</th><th>Date</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Tiers</th><th>Source</th></tr></thead><tbody>
<?php foreach ($rows as $e):
  $lignes = db_select("SELECT l.*, c.numero AS compte, c.libelle AS compte_lib FROM compta_ecriture_lignes l JOIN compta_comptes c ON c.id=l.compte_id WHERE l.ecriture_id=?", [$e['id']]);
  foreach ($lignes as $l): ?>
  <tr><td><?= h($e['numero']) ?></td><td><?= fmt_date($e['date_ecriture']) ?></td><td><?= h($e['libelle']) ?></td><td><?= h($l['compte']) ?> <?= h($l['compte_lib']) ?></td><td><?= $l['debit']>0?fmt_money($l['debit']):'' ?></td><td><?= $l['credit']>0?fmt_money($l['credit']):'' ?></td><td><?= h($l['tiers_libelle']??'') ?></td>
  <td><?php if ($e['source_table']): ?><a href="<?= APP_URL ?>/<?= secure_source_link($e['source_table'], $e['source_id']) ?>"><?= h($e['source_table']) ?>#<?= (int)$e['source_id'] ?></a><?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; ?>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;padding:16px;color:var(--text3)">Aucune écriture sur la période.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($tab === 'gl'):
  $compte = get_str('compte');
  $where = $compte ? "AND c.numero=?" : "";
  $params = array_merge([$dateDebut,$dateFin], $compte?[$compte]:[]);
  $mvt = db_select("SELECT e.date_ecriture, e.numero, e.libelle, c.numero AS compte, l.debit, l.credit
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE e.date_ecriture BETWEEN ? AND ? $where ORDER BY e.date_ecriture, e.id", $params);
  $solde = 0.0;
?>
<div class="card"><div class="card-header"><h3>Grand livre <?= $compte?'compte '.h($compte):'(tous comptes)' ?></h3></div>
<table><thead><tr><th>Date</th><th>Numéro</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Solde cumulé</th></tr></thead><tbody>
<?php foreach ($mvt as $r): $solde += (float)$r['debit'] - (float)$r['credit']; ?>
  <tr><td><?= fmt_date($r['date_ecriture']) ?></td><td><?= h($r['numero']) ?></td><td><?= h($r['libelle']) ?></td><td><?= h($r['compte']) ?></td><td><?= fmt_money($r['debit']) ?></td><td><?= fmt_money($r['credit']) ?></td><td><?= fmt_money($solde) ?></td></tr>
<?php endforeach; ?>
<?php if (!$mvt): ?><tr><td colspan="7" style="text-align:center;padding:16px;color:var(--text3)">Aucun mouvement.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($tab === 'balance'):
  $bal = db_select("SELECT c.numero, c.libelle, c.classe, SUM(l.debit) AS td, SUM(l.credit) AS tc
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE e.date_ecriture BETWEEN ? AND ? GROUP BY c.id ORDER BY c.numero", [$dateDebut,$dateFin]);
  $totD = 0; $totC = 0;
?>
<div class="card"><div class="card-header"><h3>Balance des comptes</h3></div>
<table><thead><tr><th>Compte</th><th>Libellé</th><th>Classe</th><th>Total débit</th><th>Total crédit</th><th>Solde débiteur</th><th>Solde créditeur</th></tr></thead><tbody>
<?php foreach ($bal as $b):
  $totD += (float)$b['td']; $totC += (float)$b['tc'];
  $sd = (float)$b['td']-(float)$b['tc']; ?>
  <tr><td><?= h($b['numero']) ?></td><td><?= h($b['libelle']) ?></td><td><?= (int)$b['classe'] ?></td><td><?= fmt_money($b['td']) ?></td><td><?= fmt_money($b['tc']) ?></td><td><?= $sd>0?fmt_money($sd):'' ?></td><td><?= $sd<0?fmt_money(-$sd):'' ?></td></tr>
<?php endforeach; ?>
<tr style="font-weight:700;border-top:2px solid var(--border2)"><td colspan="3">TOTAUX</td><td><?= fmt_money($totD) ?></td><td><?= fmt_money($totC) ?></td><td colspan="2"><?= abs($totD-$totC)<0.01?'✅ équilibré':'❌ déséquilibré' ?></td></tr>
</tbody></table>
<a href="?tab=balance&export=csv&du=<?= h($dateDebut) ?>&au=<?= h($dateFin) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
```

Ajouter en haut de fichier (après les helpers, avant le rendu) la fonction helper de lien source :
```php
// Helper local : lien signé vers la source d'une écriture
function secure_source_link(string $table, int $id): string {
    switch ($table) {
        case 'caisse_ventes': return 'caisse.php?action=view&' . http_build_query(['id' => $id, 'tok' => url_sign($id)]);
        case 'factures':      return 'facturation.php?' . http_build_query(['id' => $id, 'tok' => url_sign($id)]);
        case 'stock_entries': return 'stocks.php?' . http_build_query(['entry' => $id, 'tok' => url_sign($id)]);
        default: return '#';
    }
}
```
(S'appuyer sur `url_sign` existant — vérifier sa signature réelle dans `security.php` ; si `url_sign($id)` retourne une chaîne à passer dans `&tok=`, l'utiliser tel quel.)

- [ ] **Step 5: Lint**

Run: `php -l C:/xampp/htdocs/medicore/pages/comptabilite.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Test fonctionnel — accès page**

Se connecter en `admin` (reconnexion pour rafraîchir le cache permissions), aller sur `http://localhost/medicore/comptabilite.php`. Vérifier : les 8 onglets s'affichent, le dashboard montre les 4 KPIs avec des valeurs non nulles (le backfill a peuplé des écritures), la balance affiche `✅ équilibré`. Se connecter en `comptable` → page accessible, dashboard OK.

- [ ] **Step 7: Commit**

```bash
git add pages/comptabilite.php includes/permissions.php includes/icons.php
git commit -m "feat(compta): page comptabilite — dashboard, journaux, grand livre, balance

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Bilan + Compte de résultat + Clôture d'exercice

**Files:**
- Modify: `pages/comptabilite.php` (onglets bilan, cr, exercices)

**Interfaces:**
- Consumes: tables `compta_*` ; action `compta.cloturer`.
- Produces: vues bilan/cr opérationnelles ; clôture verrouillante.

- [ ] **Step 1: Ajouter l'onglet Bilan dans `pages/comptabilite.php`**

Dans la chaîne `elseif`, après `balance`, ajouter :
```php
<?php elseif ($tab === 'bilan'):
  // Actif (classes 2,3,5 — solde débiteur), Passif (classes 1,4 — solde créditeur)
  $actif = db_select("SELECT c.numero, c.libelle, SUM(l.debit)-SUM(l.credit) AS solde
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE c.classe IN (2,3,5) AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut,$dateFin]);
  $passif = db_select("SELECT c.numero, c.libelle, SUM(l.credit)-SUM(l.debit) AS solde
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE c.classe IN (1,4) AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut,$dateFin]);
  $totA = 0; $totP = 0;
?>
<div class="card"><div class="card-header"><h3>Bilan</h3></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div><h4>Actif</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($actif as $a): $totA += (float)$a['solde']; ?><tr><td><?= h($a['numero']) ?></td><td><?= h($a['libelle']) ?></td><td><?= fmt_money($a['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total actif</td><td><?= fmt_money($totA) ?></td></tr>
  </tbody></table></div>
  <div><h4>Passif</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($passif as $p): $totP += (float)$p['solde']; ?><tr><td><?= h($p['numero']) ?></td><td><?= h($p['libelle']) ?></td><td><?= fmt_money($p['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total passif</td><td><?= fmt_money($totP) ?></td></tr>
  </tbody></table></div>
</div>
<div class="alert alert-<?= abs($totA-$totP)<0.01?'green':'red' ?>" style="margin-top:12px">Équilibre actif/passif : <?= abs($totA-$totP)<0.01?'✅':'❌ écart '.fmt_money($totA-$totP) ?></div>
</div>
```

- [ ] **Step 2: Ajouter l'onglet Compte de résultat**

```php
<?php elseif ($tab === 'cr'):
  $charges = db_select("SELECT c.numero, c.libelle, SUM(l.debit)-SUM(l.credit) AS solde
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE c.classe=6 AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut,$dateFin]);
  $produits = db_select("SELECT c.numero, c.libelle, SUM(l.credit)-SUM(l.debit) AS solde
    FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id
    WHERE c.classe=7 AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$dateDebut,$dateFin]);
  $totC = 0; $totP = 0;
?>
<div class="card"><div class="card-header"><h3>Compte de résultat</h3></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div><h4>Charges (classe 6)</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($charges as $c): $totC += (float)$c['solde']; ?><tr><td><?= h($c['numero']) ?></td><td><?= h($c['libelle']) ?></td><td><?= fmt_money($c['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total charges</td><td><?= fmt_money($totC) ?></td></tr>
  </tbody></table></div>
  <div><h4>Produits (classe 7)</h4><table><thead><tr><th>Compte</th><th>Libellé</th><th>Solde</th></tr></thead><tbody>
  <?php foreach ($produits as $p): $totP += (float)$p['solde']; ?><tr><td><?= h($p['numero']) ?></td><td><?= h($p['libelle']) ?></td><td><?= fmt_money($p['solde']) ?></td></tr><?php endforeach; ?>
  <tr style="font-weight:700"><td colspan="2">Total produits</td><td><?= fmt_money($totP) ?></td></tr>
  </tbody></table></div>
</div>
<div class="alert alert-<?= ($totP-$totC)>=0?'green':'red' ?>" style="margin-top:12px">Résultat net = <?= fmt_money($totP-$totC) ?> (<?= ($totP-$totC)>=0?'bénéfice':'perte' ?>)</div>
<a href="?tab=cr&export=csv&du=<?= h($dateDebut) ?>&au=<?= h($dateFin) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>
```

- [ ] **Step 3: Ajouter l'onglet Exercices (avec clôture verrouillante)**

Avant le bloc de rendu des onglets, ajouter le handler POST de clôture :
```php
// ── POST : clôturer un exercice ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cloturer_exercice') {
    csrf_verify();
    if (can('compta.cloturer')) {
        $eid = post_int('exercice_id');
        db_exec("UPDATE compta_exercices SET statut='cloture', cloture_par=?, date_cloture=NOW() WHERE id=? AND statut='ouvert'", [currentUser()['id'], $eid]);
        logActivity('Exercice clôturé', 'yellow', 'comptabilite', $eid);
        invalidate_permissions_cache();
    }
    header('Location: ' . APP_URL . '/comptabilite.php?tab=exercices'); exit;
}
```

Onglet :
```php
<?php elseif ($tab === 'exercices'):
  $exs = db_select("SELECT ex.*, u.nom AS cloture_nom FROM compta_exercices ex LEFT JOIN utilisateurs u ON u.id=ex.cloture_par ORDER BY ex.annee DESC");
?>
<div class="card"><div class="card-header"><h3>Exercices comptables</h3></div>
<table><thead><tr><th>Année</th><th>Période</th><th>Statut</th><th>Clôturé par</th><th>Date clôture</th><th>Action</th></tr></thead><tbody>
<?php foreach ($exs as $ex): ?>
  <tr><td><?= (int)$ex['annee'] ?></td><td><?= fmt_date($ex['date_debut']) ?> → <?= fmt_date($ex['date_fin']) ?></td>
    <td><span class="badge <?= $ex['statut']==='ouvert'?'badge-green':'badge-gray' ?>"><?= h($ex['statut']) ?></span></td>
    <td><?= h($ex['cloture_nom']??'—') ?></td><td><?= !empty($ex['date_cloture'])?fmt_date($ex['date_cloture'],true):'—' ?></td>
    <td><?php if ($ex['statut']==='ouvert' && can('compta.cloturer')): ?>
      <form method="POST" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="cloturer_exercice"><input type="hidden" name="exercice_id" value="<?= (int)$ex['id'] ?>">
        <button type="submit" class="btn btn-sm btn-ghost" onclick="return confirm('Clôturer l\'exercice <?= (int)$ex['annee'] ?> ? Aucune nouvelle écriture ne pourra y être ajoutée.')">Clôturer</button>
      </form>
    <?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
```

- [ ] **Step 4: Ajouter le garde-fou clôture dans `compta_generer_ecriture` (includes/comptabilite.php)**

Dans `compta_generer_ecriture`, remplacer la vérification d'exercice par :
```php
    $exo = db_row("SELECT id, statut FROM compta_exercices WHERE ? BETWEEN date_debut AND date_fin LIMIT 1", [$date]);
    if (!$exo) throw new RuntimeException("Aucun exercice pour la date $date");
    if ($exo['statut'] === 'cloture') throw new RuntimeException("Exercice clôturé : écriture refusée pour la date $date");
    $exercice_id = (int)$exo['id'];
```

- [ ] **Step 5: Lint**

Run: `php -l C:/xampp/htdocs/medicore/pages/comptabilite.php && php -l C:/xampp/htdocs/medicore/includes/comptabilite.php`
Expected: `No syntax errors detected` ×2.

- [ ] **Step 6: Test — bilan + CR équilibrés**

Dans l'app, onglets Bilan et Compte de résultat. Vérifier : bilan affiche `✅ équilibré` (actif = passif) ; CR affiche un résultat net cohérent avec le dashboard.

- [ ] **Step 7: Test — clôture verrouillante**

Clôturer l'exercice 2026 (onglet Exercices, bouton Clôturer). Tenter de créer un ticket caisse daté aujourd'hui (2026) → l'écriture doit être refusée (message d'erreur affiché / exception). Rouvrir en clôturant pas 2026 — alternative : re-créer un exercice 2027 ouvert via SQL pour tester, puis annuler. En pratique : re-clôturer impossible, donc rouvrir manuellement pour le test :
Run: `/c/xampp/mysql/bin/mysql.exe -u root medicore -e "UPDATE compta_exercices SET statut='ouvert', cloture_par=NULL, date_cloture=NULL WHERE annee=2026;"`
(Re-ouvrir 2026 après le test pour ne pas bloquer les tâches suivantes.)

- [ ] **Step 8: Commit**

```bash
git add pages/comptabilite.php includes/comptabilite.php
git commit -m "feat(compta): bilan + compte de resultat + cloture exercice verrouillante

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Saisie manuelle + Plan comptable admin + Export CSV

**Files:**
- Modify: `pages/comptabilite.php` (onglets plan, handler saisie manuelle, export CSV)

**Interfaces:**
- Consumes: actions `compta.saisir`, `compta.param_comptes` ; helpers `compta_generer_ecriture`, `Validator`.
- Produces: saisie manuelle d'écritures ; CRUD plan comptable ; export CSV balance/CR/GL.

- [ ] **Step 1: Handler POST — saisie manuelle (en haut de `pages/comptabilite.php`, après le handler clôture)**

```php
// ── POST : saisie manuelle d'écriture (partie double) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'saisie_manuelle' && can('compta.saisir')) {
    csrf_verify();
    $date = post_str('date');
    $libelle = post_str('libelle');
    $journal = post_str('journal') ?: 'OD';
    $comptes = $_POST['compte'] ?? [];
    $debits  = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $tiers   = $_POST['tiers'] ?? [];
    $lignes = [];
    for ($i = 0; $i < count($comptes); $i++) {
        if (trim($comptes[$i]) === '') continue;
        $lignes[] = [
            'compte' => trim($comptes[$i]),
            'debit'  => (float)($debits[$i] ?? 0),
            'credit' => (float)($credits[$i] ?? 0),
            'tiers'  => $tiers[$i] ?? null,
        ];
    }
    try {
        compta_generer_ecriture($journal, $date, $libelle, $lignes, 'saisie_manuelle', null, currentUser()['id']);
        logActivity('Écriture comptable saisie manuellement', 'blue', 'comptabilite', 0);
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/comptabilite.php?tab=journaux' . (isset($err) ? '&err=' . urlencode($err) : ''));
    exit;
}

// ── POST : CRUD plan comptable ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'compte_save' && can('compta.param_comptes')) {
    csrf_verify();
    $id = post_int('id');
    $numero = post_str('numero');
    $libelle = post_str('libelle');
    $classe = post_int('classe');
    $type = post_str('type');
    if ($id) {
        db_exec("UPDATE compta_comptes SET numero=?, libelle=?, classe=?, type=?, statut=? WHERE id=?", [$numero,$libelle,$classe,$type,post_str('statut','actif'),$id]);
    } else {
        db_exec("INSERT INTO compta_comptes (numero, libelle, classe, type, statut, position) VALUES (?, ?, ?, ?, 'actif', 0)", [$numero,$libelle,$classe,$type]);
    }
    logActivity('Compte comptable enregistré: '.$numero, 'blue', 'comptabilite', $id);
    header('Location: ' . APP_URL . '/comptabilite.php?tab=plan'); exit;
}
```

- [ ] **Step 2: Onglet Plan comptable**

Dans la chaîne `elseif`, ajouter avant `exercices` :
```php
<?php elseif ($tab === 'plan' && can('compta.param_comptes')):
  $comptes = db_select("SELECT * FROM compta_comptes ORDER BY classe, numero");
?>
<div class="card"><div class="card-header"><h3>Plan comptable</h3></div>
<table><thead><tr><th>Numéro</th><th>Libellé</th><th>Classe</th><th>Type</th><th>Statut</th><th>Modifier</th></tr></thead><tbody>
<?php foreach ($comptes as $c): ?>
  <tr>
    <form method="POST" style="display:contents"><?= csrf_field() ?>
    <input type="hidden" name="action" value="compte_save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <td><input name="numero" value="<?= h($c['numero']) ?>" style="width:70px"></td>
    <td><input name="libelle" value="<?= h($c['libelle']) ?>" style="width:220px"></td>
    <td><select name="classe" style="width:60px"><?php for($k=1;$k<=8;$k++): ?><option value="<?= $k ?>" <?= (int)$c['classe']===$k?'selected':'' ?>><?= $k ?></option><?php endfor; ?></select></td>
    <td><select name="type"><?php foreach(['actif','passif','charge','produit','tresorerie','tiers'] as $t): ?><option value="<?= $t ?>" <?= $c['type']===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></td>
    <td><select name="statut"><option value="actif" <?= $c['statut']==='actif'?'selected':'' ?>>actif</option><option value="inactif" <?= $c['statut']==='inactif'?'selected':'' ?>>inactif</option></select></td>
    <td><button class="btn btn-sm btn-blue">Enregistrer</button></td>
    </form>
  </tr>
<?php endforeach; ?>
<tr>
  <form method="POST" style="display:contents"><?= csrf_field() ?>
  <input type="hidden" name="action" value="compte_save"><input type="hidden" name="id" value="0">
  <td><input name="numero" placeholder="ex 706" style="width:70px"></td>
  <td><input name="libelle" placeholder="Libellé" style="width:220px"></td>
  <td><select name="classe"><?php for($k=1;$k<=8;$k++): ?><option value="<?= $k ?>"><?= $k ?></option><?php endfor; ?></select></td>
  <td><select name="type"><?php foreach(['actif','passif','charge','produit','tresorerie','tiers'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select></td>
  <td><select name="statut"><option value="actif">actif</option></select></td>
  <td><button class="btn btn-sm btn-green">+ Ajouter</button></td>
  </form>
</tr>
</tbody></table></div>
<?php elseif ($tab === 'plan' && !can('compta.param_comptes')): ?>
<div class="alert alert-red">Vous n'avez pas la permission de modifier le plan comptable.</div>
<?php endif; ?>
```

- [ ] **Step 3: Onglet Journaux — bouton « Nouvelle saisie manuelle »**

Dans le bloc `journaux`, avant la table, ajouter (si `can('compta.saisir')`) :
```php
<?php if (can('compta.saisir')): ?>
<button class="btn btn-blue btn-sm" onclick="document.getElementById('modal-saisie').style.display='flex'" style="margin-bottom:12px">+ Nouvelle saisie manuelle</button>
<div id="modal-saisie" class="modal-overlay" style="display:none;align-items:center;justify-content:center;z-index:300" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(640px,95vw);padding:24px">
    <h3 style="margin-bottom:16px">Saisie manuelle (partie double)</h3>
    <form method="POST"><?= csrf_field() ?>
      <input type="hidden" name="action" value="saisie_manuelle">
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required style="flex:1">
        <select name="journal" style="flex:1"><?php foreach(['VTE','ACH','BQ','CA','OD'] as $j): ?><option value="<?= $j ?>"><?= $j ?></option><?php endforeach; ?></select>
      </div>
      <input name="libelle" placeholder="Libellé de l'écriture" required style="width:100%;margin-bottom:12px">
      <table style="width:100%"><thead><tr><th>Compte</th><th>Débit</th><th>Crédit</th><th>Tiers</th></tr></thead><tbody id="saisie-lignes">
        <tr><td><input name="compte[]" placeholder="571" style="width:80px"></td><td><input name="debit[]" type="number" step="0.01" style="width:100px"></td><td><input name="credit[]" type="number" step="0.01" style="width:100px"></td><td><input name="tiers[]" style="width:160px"></td></tr>
        <tr><td><input name="compte[]" placeholder="701" style="width:80px"></td><td><input name="debit[]" type="number" step="0.01" style="width:100px"></td><td><input name="credit[]" type="number" step="0.01" style="width:100px"></td><td><input name="tiers[]" style="width:160px"></td></tr>
      </tbody></table>
      <button type="button" class="btn btn-ghost btn-sm" onclick="const t=document.getElementById('saisie-lignes'); t.insertAdjacentHTML('beforeend', t.rows[0].outerHTML)">+ Ligne</button>
      <div style="text-align:right;margin-top:16px"><button type="submit" class="btn btn-blue">Valider l'écriture</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
```

- [ ] **Step 4: Export CSV (balance / CR / grand livre)**

En haut de `pages/comptabilite.php`, après le calcul du `$tab` et **avant** tout rendu HTML, ajouter :
```php
// ── Export CSV (balance / CR / grand livre) ──
if (get_str('export') === 'csv' && in_array($tab, ['balance','cr','gl'], true)) {
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compta_'.$tab.'_'.$dateDebut.'_'.$dateFin.'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($tab === 'balance') {
        fputcsv($out, ['Compte','Libellé','Classe','Total débit','Total crédit','Solde débiteur','Solde créditeur'], ';');
        $bal = db_select("SELECT c.numero, c.libelle, c.classe, SUM(l.debit) AS td, SUM(l.credit) AS tc FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? GROUP BY c.id ORDER BY c.numero", [$dateDebut,$dateFin]);
        foreach ($bal as $b) { $sd=(float)$b['td']-(float)$b['tc']; fputcsv($out, [$b['numero'],$b['libelle'],$b['classe'],$b['td'],$b['tc'], $sd>0?$sd:'', $sd<0?-$sd:''], ';'); }
    } elseif ($tab === 'cr') {
        fputcsv($out, ['Compte','Libellé','Solde'], ';');
        foreach ([6=>'charges',7=>'produits'] as $classe=>$label) {
            $rows = db_select("SELECT c.numero, c.libelle, ".($classe===6?"SUM(l.debit)-SUM(l.credit)":"SUM(l.credit)-SUM(l.debit)")." AS solde FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE c.classe=? AND e.date_ecriture BETWEEN ? AND ? GROUP BY c.id HAVING solde!=0 ORDER BY c.numero", [$classe,$dateDebut,$dateFin]);
            foreach ($rows as $r) fputcsv($out, [$label.' :: '.$r['numero'], $r['libelle'], $r['solde']], ';');
        }
    } else { // gl
        fputcsv($out, ['Date','Numéro','Libellé','Compte','Débit','Crédit','Solde cumulé'], ';');
        $mvt = db_select("SELECT e.date_ecriture, e.numero, e.libelle, c.numero AS compte, l.debit, l.credit FROM compta_ecriture_lignes l JOIN compta_ecritures e ON e.id=l.ecriture_id JOIN compta_comptes c ON c.id=l.compte_id WHERE e.date_ecriture BETWEEN ? AND ? ORDER BY e.date_ecriture, e.id", [$dateDebut,$dateFin]);
        $solde = 0.0;
        foreach ($mvt as $r) { $solde += (float)$r['debit']-(float)$r['credit']; fputcsv($out, [$r['date_ecriture'],$r['numero'],$r['libelle'],$r['compte'],$r['debit'],$r['credit'],$solde], ';'); }
    }
    fclose($out);
    exit;
}
```

- [ ] **Step 5: Lint**

Run: `php -l C:/xampp/htdocs/medicore/pages/comptabilite.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Test — saisie manuelle**

Onglet Journaux → « + Nouvelle saisie manuelle » → saisir une écriture équilibrée (débit 571 1000 / crédit 701 1000) → Valider. Vérifier en SQL qu'une nouvelle écriture `source_table='saisie_manuelle'` existe, équilibrée.

- [ ] **Step 7: Test — plan comptable**

Onglet Plan comptable (admin) → ajouter un compte (ex 758bis), vérifier qu'il apparaît. Le comptable (sans `compta.param_comptes`) doit voir le message d'erreur de permission.

- [ ] **Step 8: Test — export CSV**

Cliquer « Export CSV » sur la balance → fichier téléchargé, ouvert dans Excel → lignes cohérentes avec la balance affichée.

- [ ] **Step 9: Commit**

```bash
git add pages/comptabilite.php
git commit -m "feat(compta): saisie manuelle + CRUD plan comptable + export CSV

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: RBAC fin + navigation + QA finale

**Files:**
- Verify: `sql/update_v9.sql` (RBAC déjà seedé en Task 1)
- Verify: `includes/permissions.php`, `includes/icons.php`, `pages/comptabilite.php`
- Modify: `sql/seed_permissions.php` (si seeder centralisé utilisé)

**Interfaces:**
- Consumes: tout ce qui précède.
- Produces: navigation sidebar, rôles cohérents, QA de bout en bout.

- [ ] **Step 1: Vérifier le seeder permissions (si `sql/seed_permissions.php` centralise les RBAC)**

Inspecter `sql/seed_permissions.php`. S'il référence `comptabilite`, le compléter pour y inclure la page + les 4 actions (admin full, comptable consulter+saisir). Sinon, s'assurer que `update_v9.sql` reste la source de vérité (déjà fait en Task 1).

- [ ] **Step 2: Vérifier la navigation sidebar**

Se connecter en `admin` → l'entrée « 🧾 Comptabilité » doit apparaître dans la sidebar section Administration. Se connecter en `comptable` → l'entrée doit aussi apparaître. Un rôle sans accès (ex `medecin`) → l'entrée absente.

- [ ] **Step 3: Invalidation du cache + reconnexion**

Déconnexion/reconnexion pour rafraîchir `$_SESSION['_perm_pages']` et `_perm_actions`. Vérifier qu'aucune page n'est accessible sans permission (tester `/comptabilite.php` en `medecin` → bloqué par `requirePageAccess`).

- [ ] **Step 4: QA fonctionnelle de bout en bout**

Scénarios (vérifier chaque résultat visuellement + en SQL) :
1. Créer un ticket caisse médicament paye → écriture CA équilibrée, lien source `caisse_ventes#id`.
2. Créer un ticket consultation paye → écriture CA, crédit 706.
3. Annuler un ticket → écriture miroir `ANNULATION — ...`, original conservé.
4. Créer une facture + la passer réglée → 2 écritures (VTE création débit 411/426 crédit 706 ; CA/BQ encaissement débit 571/521 crédit 411).
5. Valider une entrée stock → écriture ACH débit 360/300 crédit 401.
6. Dashboard : recettes/dépenses/résultat/trésorerie non nuls et cohérents.
7. Balance → `✅ équilibré`.
8. Bilan → `✅ équilibré` (actif = passif).
9. Compte de résultat → résultat net = dashboard.
10. Clôturer 2024 (historique) → tentative d'écriture datée 2024 refusée.
11. Saisie manuelle équilibrée → valide ; déséquilibrée → refusée.
12. Export CSV balance + CR → fichiers valides.

- [ ] **Step 5: Test final d'idempotence (migration + backfill)**

Run:
```bash
/c/xampp/mysql/bin/mysql.exe -u root medicore < /c/xampp/htdocs/medicore/sql/update_v9.sql && echo OK_MIGRATION
php C:/xampp/htdocs/medicore/sql/backfill_compta.php
```
Expected: `OK_MIGRATION` ; backfill `caisse=0 factures=0 stocks=0 skips=N` (rien de neuf).

- [ ] **Step 6: Lint de tous les fichiers modifiés**

Run: `php -l C:/xampp/htdocs/medicore/includes/comptabilite.php && php -l C:/xampp/htdocs/medicore/pages/comptabilite.php && php -l C:/xampp/htdocs/medicore/pages/caisse.php && php -l C:/xampp/htdocs/medicore/pages/facturation.php && php -l C:/xampp/htdocs/medicore/pages/stocks.php && php -l C:/xampp/htdocs/medicore/sql/backfill_compta.php`
Expected: `No syntax errors detected` ×6.

- [ ] **Step 7: Commit final + fusion**

```bash
git add -A
git commit -m "feat(compta): RBAC + navigation + QA finale

Co-Authored-By: Claude <noreply@anthropic.com>"
git checkout main
git merge --no-ff feat/comptabilite -m "Merge feat/comptabilite: Comptabilite SYSCOHADA + tracabilite financiere"
```

---

## Notes de mise en œuvre

- **Ordre strict** : Task 1 → 2 → 3 (les wrappers sont nécessaires au backfill). Tasks 4-6 dépendent de 1-2. Task 7 en dernier.
- **Vérifier les signatures réelles** avant d'instrumenter : `url_sign()` dans `security.php`, le nom de la variable contenant l'ID du ticket dans `caisse.php` (ex `$venteId` vs `db_exec` retour), et la structure exacte du bloc `update_statut` dans `facturation.php`. Adapter le code du plan à la réalité du fichier.
- **Pas de migration de l'enum `factures.statut`** : le pont normalise uniquement en lecture (Task 3 Step 5 + Task 2 wrapper). Ne pas modifier l'enum existant.
- **Re-câbler `compta_on_facture` règlement** : `facturation.php` ne capture pas le mode de paiement aujourd'hui. Si l'UI ne le propose pas, utiliser `especes` par défaut (le wrapper le gère) — ou ajouter un champ `mode_paiement` au formulaire de règlement (optionnel, YAGNI en v1).