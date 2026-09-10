# Comptabilité SYSCOHADA avec traçabilité financière — Design

**Date** : 2026-07-04
**Statut** : Validé (cadrage brainstorming), en attente de revue utilisateur
**Portée** : Module Comptabilité complet (plan comptable SYSCOHADA, partie double, journaux, grand livre, balance, bilan, compte de résultat, clôture d'exercice) + traçabilité financière unifiée générée automatiquement depuis les flux métier existants.

---

## 1. Contexte et état des lieux

MediCore ERP n'a **aucune comptabilité** aujourd'hui. Les flux financiers sont éparpillés dans plusieurs tables indépendantes, sans lien entre elles ni trace structurée :

- **`caisse_ventes`** (`medicore.sql:375-394`, étendue v4/v5) — tickets POS pharmacie + tickets service (consultation, création dossier). Colonnes clés : `montant_total`, `montant_recu`, `monnaie_rendue`, `mode_paiement` (especes/carte/cheque/virement/assurance/mobile_money/gratuit), `statut` (ouvert/paye/annule/rembourse), `type_vente` (medicament/creation_dossier/consultation/autre), `patient_id`, `caissier_id`, `session_id`, `ordonnance_id`, `rdv_id`, `medecin_id`, `date_vente`. Lignes dans `caisse_lignes`.
- **`factures`** (`medicore.sql:190-207`) — factures patients (part assurance/patient). `numero`, `montant_total`, `montant_assurance`, `montant_patient`, `assurance_type`, `statut` (en_attente/reglee/partielle/impayee/annulee), `date_reglement`. **Bug latent** : `facturation.php:34` écrit les formes accentuées `réglée`/`impayée` non présentes dans l'enum du schéma (`reglee`/`impayee`).
- **`stock_entries`** (`update.sql:118-132`) — bons de réception / achats. `reference`, `fournisseur`, `montant_total`, `statut` (en_attente/validee/annulee). Seul flux argent-sortant.
- **`prises_en_charge`** (`update_v3.sql:206-229`) — demandes de prise en charge assurance, liées à `factures` via `facture_id`.
- **`caisse_sessions`** (`update_v4.sql:50-66`) — sessions de caisse avec fond/écart.
- **`compagnies_assurance`** (`update_v3.sql:191-204`) — compagnies d'assurance.

**Traçabilité actuelle** : `activite_log` (`medicore.sql:224-235`) ne stocke que du texte libre dans `action` ; la colonne `details` n'est **jamais peuplée** et `logActivity()` (`auth.php:105-112`) n'a pas de paramètre structuré. Aucune table de journal, d'écritures, de mouvements, d'exercice ou de plan comptable n'existe. Le rôle **Comptable** existe (`roles_config`) mais est en lecture seule sur les flux (aucun jeton d'action financière).

**Numérotation factures** : deux schémas coexistent (`F-YYYY-NNNNN` en seed, `FAC-YYYY-NNNNN` dans `facturation.php:19`). La comptabilité n'imposera pas de migration du numéro existant (risqué) mais normalisera côté pont.

## 2. Décisions de cadrage (brainstorming)

- **Ambition** : comptabilité complète avec plan comptable (partie double, journaux, grand livre, balance, bilan, compte de résultat, clôture d'exercice). L'option « analytique seul » a été écartée par l'utilisateur en cours de cadrage.
- **Référentiel** : **SYSCOHADA (OHADA)**, classes 1 à 8, devise FCFA.
- **Mécanisme de traçabilité** : **table unifiée d'écritures persistée**, alimentée automatiquement par les modules existants + backfill de l'historique (l'option « à la volée » a été écartée).
- **Génération** : **auto + backfill** — les modules existants instrumentés génèrent les écritures en temps réel ; un script backfill remonte l'historique.

## 3. Architecture

### 3.1 Approche retenue : partie double classique

Tables `compta_ecritures` (en-tête) + `compta_ecriture_lignes` (lignes débit/crédit), équilibrées par construction. Helpers centralisés dans `includes/comptabilite.php`, appelés depuis les modules existants. Le grand livre, la balance, le bilan et le compte de résultat se déduisent par agrégation SQL sur ces tables.

Alternatives écartées : (B) vue SQL projetée sur les tables existantes — impossible de saisir des écritures manuelles, pas de clôture verrouillable, traçabilité réduite ; (C) table analytique monolatérale — contredite par le choix du PCN complet.

### 3.2 Modèle de données (migration `sql/update_v9.sql`, idempotente)

Six nouvelles tables :

**`compta_exercices`**
- `id` PK, `annee` INT UNIQUE, `date_debut` DATE, `date_fin` DATE
- `statut enum('ouvert','cloture')`, `cloture_par` INT NULL (FK→utilisateurs), `date_cloture` DATETIME NULL
- Seed : exercices 2024, 2025, 2026 — `statut='ouvert'` (2024/2025 ouverts pour permettre le backfill ; l'utilisateur les clôturera manuellement après backfill).

**`compta_journaux`**
- `id` PK, `code` VARCHAR(8) UNIQUE, `libelle` VARCHAR(100), `type enum('ventes','achats','banque','caisse','od')`
- `compte_contrepartie_id` INT NULL (FK→compta_comptes)
- Seed : VTE (Ventes), ACH (Achats), BQ (Banque), CA (Caisse), OD (Opérations diverses).

**`compta_comptes`**
- `id` PK, `numero` VARCHAR(10) UNIQUE, `libelle` VARCHAR(150), `classe` TINYINT (1-8)
- `type enum('actif','passif','charge','produit','tresorerie','tiers')`, `lettrable` TINYINT DEFAULT 0
- `parent_id` INT NULL, `statut enum('actif','inactif')`, `position` INT
- Seed : plan SYSCOHADA réduit (~40 comptes courants). Exemples :
  - Classe 1 : 101 Capital, 120 Résultat, 13 Subventions
  - Classe 2 : 20 Immobilisations, 21 Terrains, 24 Matériel, 28 Amortissements
  - Classe 3 : 30 Stocks marchandises, 31 Stocks matières, 36 Stocks de médicaments
  - Classe 4 : 401 Fournisseurs, 411 Clients, 426 Assureurs, 42 Personnel, 44 État, 47 Comptes d'attente
  - Classe 5 : 521 Banque, 571 Caisse, 580 Virements internes
  - Classe 6 : 601 Achats marchandises, 605 Autres charges, 61 Transport, 63 Frais financier, 658 Autres charges (absorption gratuit)
  - Classe 7 : 701 Ventes marchandises, 706 Prestations de services, 707 Ventes accessoires, 758 Autres produits
  - Classe 8 : 85 Résultat (comptes d'ordre)

**`compta_ecritures`** (en-tête)
- `id` PK, `numero` VARCHAR(30) (séquence par journal/an, ex `VTE-2026-00001`)
- `date_ecriture` DATE, `journal_id` FK→compta_journaux, `exercice_id` FK→compta_exercices
- `libelle` VARCHAR(255), `source_table` VARCHAR(50) NULL, `source_id` INT NULL
- `utilisateur_id` INT NULL (FK→utilisateurs), `statut enum('brouillon','validee','annulee')` DEFAULT 'validee'
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
- Index : `(source_table, source_id)` pour le backfill idempotent et la navigation source↔écriture ; `(exercice_id, journal_id)` ; `(date_ecriture)`.

**`compta_ecriture_lignes`**
- `id` PK, `ecriture_id` FK→compta_ecritures ON DELETE CASCADE
- `compte_id` FK→compta_comptes, `debit` DECIMAL(14,2) DEFAULT 0, `credit` DECIMAL(14,2) DEFAULT 0
- `tiers_libelle` VARCHAR(150) NULL (patient/fournisseur/libellé libre)
- Index : `(compte_id)`, `(ecriture_id)`.

**Lien de traçabilité** : `source_table` + `source_id` sur l'en-tête. Depuis un ticket `caisse_ventes.id=42`, on retrouve son écriture via `WHERE source_table='caisse_ventes' AND source_id=42` ; depuis l'écriture, le lien signé vers la source est rendu dans l'UI via `secure_url`.

Lettrage (`compta_lettrages`) reporté en phase 2 (YAGNI en v1).

## 4. Pont comptable & mappings SYSCOHADA

### 4.1 Include `includes/comptabilite.php`

Helpers génériques :

```php
/** Génère une écriture équilibrée. Lève une exception si ∑débit ≠ ∑crédit
 *  ou si un compte n'existe pas. Refus si la date tombe dans un exercice clôturé.
 */
compta_generer_ecriture(string $journal_code, string $date, string $libelle,
                        array $lignes, ?string $source_table, ?int $source_id,
                        ?int $utilisateur_id): int
// $lignes = [['compte'=>'571','debit'=>X,'credit'=>0,'tiers'=>'Patient ...'], ...]

/** Écriture miroir inversée pour annuler un flux (jamais de DELETE). */
compta_annuler_ecriture(string $source_table, int $source_id, ?int $utilisateur_id): ?int

/** Résout l'ID d'exercice pour une date (0 si aucun exercice ouvert couvrant la date). */
compta_exercice_id(string $date): int
```

Wrappers par flux :
- `compta_on_vente_caisse(array $vente)` — depuis un ticket caisse paye.
- `compta_on_facture(array $facture, string $etape)` — `$etape = 'creation' | 'reglement'`.
- `compta_on_achat_stock(array $entry)` — depuis une entrée stock validée.
- `compta_on_annulation_vente(array $vente)` — wrapper d'annulation pour caisse.

### 4.2 Mappings (TVA ignorée en v1 — médical souvent exonéré, YAGNI)

| Flux | Journal | Débit | Crédit |
|---|---|---|---|
| Ticket caisse médicament (paye) | CA | 571/521 selon mode | 701 Ventes marchandises |
| Ticket caisse service (consultation/dossier) (paye) | CA | 571/521 selon mode | 706 Prestations |
| Création facture (en_attente) | VTE | 411 Client (part patient) + 426 Assureur (part assurance) | 706 Prestations |
| Règlement facture (→ réglée) | CA/BQ | 571/521 selon mode | 411 Client |
| Validation entrée stock (achat) | ACH | 30 (stock type=`stock`) / 36 (médicaments type=`medicament`) / 31 (matières) | 401 Fournisseur |
| Annulation ticket/facture | (idem journal) | miroir inversé | miroir inversé |

**Règle mode_paiement → compte de trésorerie** :
- `especes`, `mobile_money` → **571 Caisse**
- `carte`, `cheque`, `virement` → **521 Banque**
- `assurance` → **411/426** (tiers à recevoir ; pas de trésorerie immédiate)
- `gratuit` → **658 Autres charges** (le coût est absorbé par l'établissement)

**Compte de stock selon `stock_entries.type`** : `medicament` → 36 (Stocks de médicaments), `stock` → 30 (Stocks de marchandises) ou 31 (Matières premières) selon la nature — 30 par défaut pour le matériel médical.

**Tickets gratuits à montant nul** : si `montant_total = 0`, aucune écriture n'est générée (pas de flux financier). Un ticket `gratuit` avec `montant_total > 0` génère bien l'écriture (débit 658 / crédit 701 ou 706).

**Prises en charge assurance** : pas d'écriture auto dédiée en v1. La facture déjà booked la part assurance en 426 à la création ; la PEC est un suivi de la créance 426 (lettrage, phase 2).

### 4.3 Instrumentation des modules existants

Ajout d'appels helpers **après** l'INSERT existant (sans toucher à la logique métier) :

- **`pages/caisse.php`**
  - `create_vente` (`caisse.php:82-159`) → après insert `caisse_ventes`, si `statut='paye'` : `compta_on_vente_caisse($vente)`.
  - `create_ticket_service` (`caisse.php:162-231`) → idem si `statut='paye'`.
  - `annuler` (`caisse.php:234-255`) → après l'UPDATE `statut='annule'` : `compta_on_annulation_vente($vente)`.
- **`pages/facturation.php`**
  - `create_facture` (`facturation.php:8-28`) → `compta_on_facture($facture, 'creation')`.
  - `update_statut` (`facturation.php:31-39`) → si statut normalisé = `reglee` : `compta_on_facture($facture, 'reglement')`.
- **`pages/stocks.php`**
  - Validation entrée (`statut→validee`) → `compta_on_achat_stock($entry)`. (L'annulation d'entrée stock génère une écriture miroir via `compta_annuler_ecriture('stock_entries', $id)`.)

Normalisation du bug `factures.statut` : le pont lit les deux formes (`reglee`/`réglée`, `impayee`/`impayée`) et normalise côté comptabilité. **Aucune migration de l'enum existant** (risqué pour les données en place) ; le pont est la seule source de vérité côté compta.

### 4.4 Backfill — `sql/backfill_compta.php`

Script PHP lancé manuellement (depuis le navigateur ou CLI), idempotent :
- Crée les exercices 2024/2025/2026 (`statut='ouvert'`) s'ils n'existent pas.
- Parcourt `caisse_ventes WHERE statut='paye'`, `factures` (toutes), `stock_entries WHERE statut='validee'`.
- Pour chaque flux : `SELECT 1 FROM compta_ecritures WHERE source_table=? AND source_id=?` — saute si déjà généré. Sinon appelle le wrapper correspondant avec la **date d'origine** du flux (`date_vente`/`date_emission`/`date_reception`).
- L'annulation (`caisse_ventes.statut='annule'`) génère aussi son écriture miroir à la date d'annulation (estimée à `date_vente` si pas de colonne dédiée).
- Re-lançable sans doubler.

## 5. Module Comptabilité — `pages/comptabilite.php`

Page autonome (pattern standard : `requirePageAccess('comptabilite')` après `layout.php`), 8 onglets :

1. **Tableau de bord** — KPIs période (recettes ∑701+706, dépenses ∑6, résultat net = ∑7 − ∑6, trésorerie ∑571+521). Sélecteur période (mois/trimestre/année) + filtre exercice. Évolution 12 mois (Chart.js, pattern `facturation.php`).
2. **Journaux** — liste des écritures du journal sélectionné (VTE/ACH/BQ/CA/OD) sur une période : lignes débit/crédit, statut, lien cliquable vers la source métier (`secure_url`). Bouton « Nouvelle saisie manuelle » (`compta.saisir`) → modal partie double, équilibrage contrôlé côté client + serveur.
3. **Grand livre** — sélection d'un compte (ou tous) → mouvements ordonnés par date avec **solde cumulé**.
4. **Balance** — tous les comptes mouvementés sur la période : total débit, total crédit, solde débiteur/créditeur. Ligne de vérification ∑débit = ∑crédit.
5. **Bilan** — comptes classes 1 à 5 (actif/passif), deux colonnes, solde net par compte.
6. **Compte de résultat** — classes 6 (charges) et 7 (produits) ; résultat net = ∑7 − ∑6.
7. **Plan comptable** (admin) — CRUD léger des `compta_comptes` (ajout/édition/désactivation), reseed.
8. **Exercices** — liste, bouton « Clôturer » (`compta.cloturer`) : `statut='cloture'`, `cloture_par`, `date_cloture`. Garde-fou serveur : `compta_generer_ecriture` refuse toute écriture dont `date_ecriture` tombe dans un exercice clôturé.

**Export CSV** sur Balance, Grand livre, Compte de résultat (pattern `patients.php?export=csv`).

## 6. RBAC & navigation

**`includes/permissions.php`** :
- `ALL_PAGES['comptabilite'] = ['label'=>'Comptabilité', 'icon'=>'comptabilite', 'section'=>'Administration']`.
- `ALL_ACTIONS` : `compta.consulter`, `compta.saisir`, `compta.cloturer`, `compta.param_comptes`.

**`includes/icons.php`** : `ICON_NAV['comptabilite'] = '🧾'` (ou 📚).

**Seed RBAC** (`sql/update_v9.sql`) :
- `admin` : page + toutes les actions = 1.
- `comptable` : `comptabilite=1`, `compta.consulter=1`, `compta.saisir=1` ; `compta.cloturer=0`, `compta.param_comptes=0` par défaut (l'admin peut les activer ensuite via `roles.php`).
- Invalidation du cache permissions (`invalidate_permissions_cache()`) après seed ; reconnexion requise.

## 7. Traçabilité, robustesse, tests

### 7.1 Traçabilité (promesse centrale)
- Chaque écriture porte `source_table` + `source_id` + `utilisateur_id` + horodatage. Navigation bidirectionnelle source ↔ écriture via lien signé.
- **Immuabilité** : les annulations génèrent une écriture miroir (jamais de DELETE/UPDATE d'une écriture existante). L'historique comptable est auditable.
- **Clôture verrouillante** : `compta_generer_ecriture` refuse une écriture sur un exercice clôturé (garde-fou serveur, tamper-proof).
- `logActivity()` reste appelé côté modules (trace métier) ; le journal comptable est la trace financière structurée.

### 7.2 Robustesse
- Équilibre débit/crédit vérifié en PHP **avant** insertion ; insertion en-tête + lignes dans une transaction PDO (rollback si déséquilibré).
- Compte introuvable → exception explicite (jamais d'écriture sur un mauvais compte).
- Backfill idempotent (skip par `source_table/source_id`).
- **Dégradation gracieuse** : si `compta_ecritures` absente (migration non jouée), les helpers court-circuitent (détection de table, comme déjà fait pour `comptes_patients`/`dossiers_medicaux`). Les modules existants continuent de fonctionner.

### 7.3 Tests (pas de framework de tests — vérifications manuelles + lint)
- `php -l` sur tout fichier modifié.
- `update_v9.sql` jouée 1× puis 2× (idempotence).
- Backfill joué 2× (2e passe = 0 nouvelle écriture).
- Scénarios :
  1. Créer un ticket caisse → écriture équilibrée débit/crédit, lien source présent.
  2. Annuler le ticket → écriture miroir présente, original conservé.
  3. Créer une facture + la passer réglée → 2 écritures (VTE création + CA encaissement).
  4. Valider une entrée stock → écriture ACH débit 30/31 crédit 401.
  5. Clôturer 2026 → un nouveau ticket daté 2026 est refusé par le garde-fou.
  6. Bilan + compte de résultat : totaux cohérents avec la balance (∑débit = ∑crédit).

## 8. Livrables (fichiers)

- `sql/update_v9.sql` — 6 tables + seed plan SYSCOHADA (~40 comptes) + 5 journaux + exercices 2024-2026 + permissions RBAC.
- `sql/backfill_compta.php` — backfill idempotent de l'historique.
- `includes/comptabilite.php` — helpers (génération, wrappers, annulation, exercice).
- `pages/comptabilite.php` — module 8 onglets.
- `includes/permissions.php` — page + actions ; `includes/icons.php` — icône navigation.
- Instrumentation : `pages/caisse.php`, `pages/facturation.php`, `pages/stocks.php` (ajout d'appels helpers).
- `sql/medicore.sql` — mention migration v9 dans la note d'installation.

## 9. Découpage du plan d'implémentation

Un seul spec ; le plan sera découpé en tâches indépendantes :

1. **Migration `sql/update_v9.sql`** — tables + seed plan + journaux + exercices + RBAC.
2. **Include `includes/comptabilite.php`** — helpers + mappings SYSCOHADA.
3. **Instrumentation modules + backfill** — `caisse.php`/`facturation.php`/`stocks.php` + `sql/backfill_compta.php`.
4. **Page comptabilité — vues lecture** — dashboard, journaux, grand livre, balance.
5. **Bilan + compte de résultat + clôture exercice**.
6. **Saisie manuelle + plan comptable admin**.
7. **RBAC + navigation + QA** (icône, nav, tests fonctionnels).

## 10. Hors périmètre (YAGNI explicite)

- TVA / taxes (médical exonéré par défaut).
- Lettrage manuel et rapprochement bancaire (phase 2).
- Écritures d'abonnement / opérations périodiques automatiques.
- Amortissements calculés (les comptes existent, pas de calcul auto).
- Comptabilité analytique par service (les comptes 70x existent ; pas de ventilation par département en v1).
- Migration de l'enum `factures.statut` (normalisation côté pont uniquement).
- Unification de la numérotation des factures existantes.