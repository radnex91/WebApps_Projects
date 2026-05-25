# Spécification — 9 Fonctionnalités Avancées BrenFinance Suite

**Date :** 2026-05-04
**Périmètre :** Modules Paie, Rapprochement bancaire, Export données, Clôture exercice, Bons de commande, Compta analytique, Ratios/SIG, Validation bancaire, Verrouillage exercice

---

## 1. Schéma de base de données

### 1.1 Nouvelles tables

#### MODULE PAIE (6 tables)

```sql
-- Périodes de paie (mensuelles)
CREATE TABLE periodes_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice_id INT NOT NULL,
    mois TINYINT NOT NULL COMMENT '1-12',
    statut ENUM('brouillon','cloture','valide') DEFAULT 'brouillon',
    date_cloture TIMESTAMP NULL,
    cloture_par INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exercice_id) REFERENCES exercices(id),
    FOREIGN KEY (cloture_par) REFERENCES utilisateurs(id),
    UNIQUE KEY uk_periode (exercice_id, mois)
) ENGINE=InnoDB;

-- Rubriques de paie (primes, retenues, indemnités, etc.)
CREATE TABLE rubriques_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    type ENUM('gain','retenue','cotisation_patronale','indemnite') NOT NULL,
    calcul ENUM('montant_fixe','pourcentage_base','pourcentage_brut','formule') DEFAULT 'montant_fixe',
    valeur DECIMAL(15,2) NULL COMMENT 'montant fixe ou pourcentage',
    formule TEXT NULL COMMENT 'expression PHP évaluable ($salaire_base, $brut, $anciennete dispo)',
    imposable TINYINT(1) DEFAULT 1,
    cotisable_cnps TINYINT(1) DEFAULT 1,
    actif TINYINT(1) DEFAULT 1,
    ordre_affichage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Configuration salariale par employé
CREATE TABLE contrats_employes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL UNIQUE,
    salaire_base DECIMAL(15,2) NOT NULL,
    date_embauche DATE NOT NULL,
    date_sortie DATE NULL,
    type_contrat ENUM('cdi','cdd','stage','prestataire','interim') DEFAULT 'cdi',
    matricule_cnps VARCHAR(50),
    numero_compte VARCHAR(50) COMMENT 'RIB pour virement salaire',
    banque VARCHAR(100),
    statut ENUM('actif','suspendu','termine') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Bulletins de paie individuels
CREATE TABLE bulletins_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    salaire_base DECIMAL(15,2) NOT NULL,
    total_gains DECIMAL(15,2) DEFAULT 0,
    total_retenues DECIMAL(15,2) DEFAULT 0,
    net_a_payer DECIMAL(15,2) NOT NULL,
    net_en_faveur DECIMAL(15,2) DEFAULT 0 COMMENT 'montant après ordre de virement',
    charges_patronales DECIMAL(15,2) DEFAULT 0,
    cout_total DECIMAL(15,2) DEFAULT 0 COMMENT 'net + cotisations patronales',
    statut ENUM('brouillon','valide','paye') DEFAULT 'brouillon',
    date_paiement DATE NULL,
    reference_paiement VARCHAR(100) NULL COMMENT 'n° virement ou chèque',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_id) REFERENCES periodes_paie(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    UNIQUE KEY uk_bulletin (periode_id, utilisateur_id)
) ENGINE=InnoDB;

-- Lignes détaillées d'un bulletin
CREATE TABLE lignes_bulletin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulletin_id INT NOT NULL,
    rubrique_id INT NOT NULL,
    libelle VARCHAR(100) NOT NULL COMMENT 'copie au moment du calcul',
    type ENUM('gain','retenue','cotisation_patronale','indemnite') NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    ordre INT DEFAULT 0,
    FOREIGN KEY (bulletin_id) REFERENCES bulletins_paie(id),
    FOREIGN KEY (rubrique_id) REFERENCES rubriques_paie(id)
) ENGINE=InnoDB;

-- Déclarations sociales/fiscales (CNPS, DGI)
CREATE TABLE declarations_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    type ENUM('cnps','dgi_irpp','dgi_cac','etat_paie') NOT NULL,
    reference VARCHAR(50),
    montant_declare DECIMAL(15,2) NOT NULL,
    date_declaration DATE NOT NULL,
    date_echeance DATE NULL,
    statut ENUM('brouillon','declarée','payee') DEFAULT 'brouillon',
    fichier VARCHAR(255) NULL COMMENT 'PDF de la déclaration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_id) REFERENCES periodes_paie(id)
) ENGINE=InnoDB;
```

#### MODULE RAPPROCHEMENT BANCAIRE (3 tables)

```sql
-- Relevés bancaires importés
CREATE TABLE releves_bancaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_bancaire_id INT NOT NULL,
    libelle VARCHAR(200),
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    solde_debut DECIMAL(15,2) NOT NULL,
    solde_fin DECIMAL(15,2) NOT NULL,
    date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    importe_par INT,
    FOREIGN KEY (compte_bancaire_id) REFERENCES comptes_bancaires(id),
    FOREIGN KEY (importe_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Lignes du relevé bancaire
CREATE TABLE lignes_releve (
    id INT AUTO_INCREMENT PRIMARY KEY,
    releve_id INT NOT NULL,
    date_operation DATE NOT NULL,
    date_valeur DATE NULL,
    libelle VARCHAR(300) NOT NULL,
    reference VARCHAR(100),
    montant DECIMAL(15,2) NOT NULL COMMENT 'positif=credit, negatif=debit',
    type ENUM('credit','debit') NOT NULL,
    pointe TINYINT(1) DEFAULT 0,
    operation_bancaire_id INT NULL COMMENT 'lié à une opération saisie',
    rapproche_id INT NULL,
    FOREIGN KEY (releve_id) REFERENCES releves_bancaires(id),
    FOREIGN KEY (operation_bancaire_id) REFERENCES operations_bancaires(id),
    FOREIGN KEY (rapproche_id) REFERENCES rapprochements(id)
) ENGINE=InnoDB;

-- Sessions de rapprochement
CREATE TABLE rapprochements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_bancaire_id INT NOT NULL,
    date_rapprochement DATE NOT NULL COMMENT 'date d arrêté',
    solde_comptable DECIMAL(15,2) NOT NULL,
    solde_banque DECIMAL(15,2) NOT NULL,
    ecart DECIMAL(15,2) DEFAULT 0,
    statut ENUM('brouillon','finalise') DEFAULT 'brouillon',
    cree_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_bancaire_id) REFERENCES comptes_bancaires(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;
```

#### MODULE CLÔTURE D'EXERCICE (2 tables)

```sql
-- Gestion des exercices comptables
CREATE TABLE exercices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL COMMENT '2026, 2025...',
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('ouvert','cloture_provisoire','cloture_definitive','archive') DEFAULT 'ouvert',
    date_cloture_provisoire TIMESTAMP NULL,
    date_cloture_definitive TIMESTAMP NULL,
    cloture_par INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cloture_par) REFERENCES utilisateurs(id),
    UNIQUE KEY uk_code (code)
) ENGINE=InnoDB;

-- Écritures d'inventaire (clôture)
CREATE TABLE ecritures_inventaire (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice_id INT NOT NULL,
    type ENUM('stock','amortissement','provision','can','cca','charge_a_payer','produit_a_recevoir','autre') NOT NULL,
    libelle VARCHAR(300) NOT NULL,
    compte_debit VARCHAR(20) NOT NULL,
    compte_credit VARCHAR(20) NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    date_ecriture DATE NOT NULL,
    reference VARCHAR(50),
    ecriture_comptable_id INT NULL COMMENT 'lié après saisie en compta',
    cree_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exercice_id) REFERENCES exercices(id),
    FOREIGN KEY (ecriture_comptable_id) REFERENCES ecritures_comptables(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Immobilisations et amortissements
CREATE TABLE immobilisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(300) NOT NULL,
    compte_immobilisation VARCHAR(20) NOT NULL COMMENT 'n° compte OHADA (22x, 23x, 24x...)',
    compte_amortissement VARCHAR(20) NOT NULL COMMENT 'n° compte 28x correspondant',
    date_acquisition DATE NOT NULL,
    date_mise_service DATE NOT NULL,
    valeur_acquisition DECIMAL(15,2) NOT NULL,
    valeur_residuelle DECIMAL(15,2) DEFAULT 0,
    duree_ans INT NOT NULL COMMENT 'durée amortissement',
    taux_amortissement DECIMAL(5,2) NOT NULL COMMENT 'taux annuel %',
    mode_amortissement ENUM('lineaire','degressif') DEFAULT 'lineaire',
    cumul_amortissement DECIMAL(15,2) DEFAULT 0,
    date_dernier_amortissement DATE NULL,
    statut ENUM('actif','cede','reforme') DEFAULT 'actif',
    date_cession DATE NULL,
    prix_cession DECIMAL(15,2) NULL,
    plus_value DECIMAL(15,2) NULL COMMENT 'calculé à la cession',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Dotations aux amortissements (historique)
CREATE TABLE dotations_amortissement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    immobilisation_id INT NOT NULL,
    exercice_id INT NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    date_dotation DATE NOT NULL,
    ecriture_comptable_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (immobilisation_id) REFERENCES immobilisations(id),
    FOREIGN KEY (exercice_id) REFERENCES exercices(id),
    FOREIGN KEY (ecriture_comptable_id) REFERENCES ecritures_comptables(id)
) ENGINE=InnoDB;
```

#### MODULE BONS DE COMMANDE (2 tables)

```sql
-- Bons de commande (issus d'un engagement validé)
CREATE TABLE bons_commande (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NOT NULL UNIQUE,
    engagement_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    date_emission DATE NOT NULL,
    date_livraison_prevue DATE NULL,
    montant_total DECIMAL(15,2) NOT NULL,
    statut ENUM('brouillon','emis','recu_partiel','recu_total','facture','annule') DEFAULT 'brouillon',
    commentaire TEXT,
    cree_par INT NOT NULL,
    valide_par INT NULL,
    date_validation TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id),
    FOREIGN KEY (valide_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Lignes de bon de commande
CREATE TABLE lignes_bon_commande (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bon_commande_id INT NOT NULL,
    ordre INT DEFAULT 0,
    libelle VARCHAR(300) NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    unite VARCHAR(20) DEFAULT 'unité',
    prix_unitaire DECIMAL(15,2) NOT NULL,
    montant_ligne DECIMAL(15,2) NOT NULL,
    quantite_recue INT DEFAULT 0,
    FOREIGN KEY (bon_commande_id) REFERENCES bons_commande(id)
) ENGINE=InnoDB;
```

#### MODULE COMPTABILITÉ ANALYTIQUE (3 tables)

```sql
-- Axes analytiques (centres de coût, projets, activités...)
CREATE TABLE axes_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('centre_cout','projet','activite','produit','region','client_interne') NOT NULL,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    parent_id INT NULL COMMENT 'hiérarchie arborescente',
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES axes_analytiques(id),
    UNIQUE KEY uk_type_code (type, code)
) ENGINE=InnoDB;

-- Sections analytiques (clés de répartition)
CREATE TABLE sections_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE COMMENT 'clé unique genre ADMIN/IT/25',
    libelle VARCHAR(200) NOT NULL,
    type ENUM('pourcentage','montant','unite_oeuvre') NOT NULL,
    unite_oeuvre VARCHAR(50) COMMENT 'm2, heures, km, tonnes...',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Affectations analytiques (liées à une écriture comptable ou ligne)
CREATE TABLE affectations_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_comptable_id INT NULL,
    source_type ENUM('ecriture','ligne_bulletin','operation_caisse','operation_bancaire','engagement_ligne') NOT NULL,
    source_id INT NOT NULL,
    axe_id INT NOT NULL,
    section_id INT NULL,
    montant DECIMAL(15,2) NOT NULL,
    pourcentage DECIMAL(5,2) NULL COMMENT 'si répartition en %',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (axe_id) REFERENCES axes_analytiques(id),
    FOREIGN KEY (section_id) REFERENCES sections_analytiques(id),
    FOREIGN KEY (ecriture_comptable_id) REFERENCES ecritures_comptables(id)
) ENGINE=InnoDB;
```

### 1.2 Modifications aux tables existantes

```sql
-- Ajout workflow validation aux opérations bancaires
ALTER TABLE operations_bancaires
    ADD COLUMN statut ENUM('brouillon','soumis','valide_comptable','valide_daf','valide','rejete') DEFAULT 'valide' AFTER rapproche,
    ADD COLUMN valide_par_comptable INT NULL AFTER statut,
    ADD COLUMN date_validation_comptable TIMESTAMP NULL AFTER valide_par_comptable,
    ADD COLUMN valide_par_daf INT NULL AFTER date_validation_comptable,
    ADD COLUMN date_validation_daf TIMESTAMP NULL AFTER valide_par_daf,
    ADD COLUMN commentaire_rejet VARCHAR(500) NULL AFTER date_validation_daf,
    ADD COLUMN cree_par INT NULL AFTER commentaire_rejet,
    ADD FOREIGN KEY (valide_par_comptable) REFERENCES utilisateurs(id),
    ADD FOREIGN KEY (valide_par_daf) REFERENCES utilisateurs(id),
    ADD FOREIGN KEY (cree_par) REFERENCES utilisateurs(id);

-- Ajout lien exercice aux écritures comptables
ALTER TABLE ecritures_comptables
    ADD COLUMN exercice_id INT NULL AFTER journal_id,
    ADD FOREIGN KEY (exercice_id) REFERENCES exercices(id);

-- Ajout statut de verrouillage sur l'exercice
ALTER TABLE entreprises
    ADD COLUMN exercice_verrouille TINYINT(1) DEFAULT 0 AFTER exercice_courant,
    ADD COLUMN date_verrouillage DATE NULL AFTER exercice_verrouille;

-- Index pour performance
ALTER TABLE affectations_analytiques ADD INDEX idx_source (source_type, source_id);
ALTER TABLE lignes_releve ADD INDEX idx_non_pointe (pointe);
ALTER TABLE bulletins_paie ADD INDEX idx_periode_statut (periode_id, statut);
```

### 1.3 Seed data

```sql
-- Rubriques de paie standards (Cameroun)
INSERT INTO rubriques_paie (code, libelle, type, calcul, valeur, ordre_affichage) VALUES
('SAL_BASE', 'Salaire de base', 'gain', 'montant_fixe', NULL, 1),
('IND_TRANSP', 'Indemnité de transport', 'gain', 'montant_fixe', NULL, 2),
('IND_LOGEM', 'Indemnité de logement', 'gain', 'pourcentage_base', 15.00, 3),
('IND_RESP', 'Indemnité de responsabilité', 'gain', 'montant_fixe', NULL, 4),
('PR_ANCIEN', 'Prime d ancienneté', 'gain', 'pourcentage_base', 2.00, 5),
('HE_SUPP', 'Heures supplémentaires', 'gain', 'montant_fixe', NULL, 6),
('RET_CNPS', 'Retenue CNPS (part salariale)', 'retenue', 'pourcentage_brut', 4.20, 1),
('RET_IRPP', 'Retenue IRPP (impôt sur le revenu)', 'retenue', 'formule', NULL, 2),
('AV_NATURE', 'Avantage en nature', 'gain', 'montant_fixe', NULL, 7),
('COT_CNPS_P', 'Cotisation CNPS patronale', 'cotisation_patronale', 'pourcentage_brut', 8.40, 1),
('COT_ACC', 'Accidents du travail', 'cotisation_patronale', 'pourcentage_brut', 1.75, 2),
('COT_PF', 'Prestations familiales', 'cotisation_patronale', 'pourcentage_brut', 7.00, 3);

-- Axes analytiques par défaut
INSERT INTO axes_analytiques (type, code, libelle) VALUES
('centre_cout', 'CC_ADMIN', 'Centre Administration'),
('centre_cout', 'CC_PROD', 'Centre Production'),
('centre_cout', 'CC_COMM', 'Centre Commercial'),
('projet', 'PROJ_GEN', 'Fonctionnement général'),
('region', 'REG_SIEGE', 'Siège social');

-- Un exercice courant
INSERT INTO exercices (code, date_debut, date_fin, statut) VALUES
('2026', '2026-01-01', '2026-12-31', 'ouvert');
```

---

## 2. Conception détaillée par fonctionnalité

### 2.1 — #19 Verrouillage des exercices (Séparation)

**Principe :** Empêcher toute saisie (caisse, banque, compta, engagements) sur un exercice verrouillé.

**Implémentation :**
- Une page `modules/exercices/index.php` pour gérer les exercices (liste, création, ouverture/fermeture)
- Dans `includes/functions.php`, fonction `exerciceEstVerrouille($date)` qui vérifie si la date tombe dans un exercice verrouillé
- Chaque page de saisie appelle cette fonction avant INSERT/UPDATE
- Message d'erreur : « L'exercice XX est verrouillé. Impossible de saisir une opération à cette date. »
- Les rôles `super_admin` et `daf` peuvent verrouiller/déverrouiller — pas le comptable ni le caissier

**Interface :** Liste des exercices avec statut visuel (vert = ouvert, orange = provisoire, rouge = définitif, gris = archivé). Boutons : Clôture provisoire | Clôture définitive | Rouvrir.

---

### 2.2 — #9 Export de données

**Principe :** Boutons d'export Excel (.xlsx) et CSV sur chaque module.

**Implémentation :**
- Bibliothèque : **PhpSpreadsheet** (pure PHP, pas de dépendance composer — téléchargement manuel du .phar ou dossier `libs/PhpSpreadsheet/`)
- Fonction `exportExcel($data, $headers, $filename)` dans `includes/export.php`
- Fonction `exportCSV($data, $headers, $filename)` dans le même fichier
- Un `<select>` dropdown (Excel / CSV) + bouton « Exporter » en haut de chaque tableau de données
- Export déclenché par GET : `?export=excel` → le PHP renvoie un fichier téléchargeable
- Formats : colonnes monétaires en nombre, dates en format date, bordures et en-têtes en gras

**Sécurité :** L'export respecte les filtres actifs (exercice, statut, recherche, plage de dates). Le nom du fichier est horodaté : `journal_caisse_2026-05-04.xlsx`.

---

### 2.3 — #16 Workflow de validation bancaire

**Principe :** Toute opération bancaire suit un mini-workflow avant d'être comptabilisée :
1. **Brouillon** → saisie par le comptable
2. **Soumis** → envoyé pour validation
3. **Validé comptable** → premier niveau (optionnel, configurable)
4. **Validé DAF** → validation finale
5. **Validé** → opération postée, écriture comptable générée
6. **Rejeté** → retour au brouillon avec commentaire

**Implémentation :**
- Dans `modules/tresorerie/index.php`, ajouter colonne « Statut » + badge
- Boutons Valider/Rejeter visibles selon le rôle
- Audit log sur chaque validation
- `auditLog('valider_operation_bancaire', 'tresorerie', 'operations_bancaires', $id)`

---

### 2.4 — #12 Bons de commande (Procurement)

**Principe :** Après validation N+1 d'un engagement, possibilité de générer un bon de commande fournisseur.

**Workflow :**
1. Engagement validé N+1 → bouton « Générer BC » apparaît
2. L'utilisateur sélectionne/confirme le fournisseur, date livraison
3. Le BC reprend les lignes de l'engagement (modifiables : quantités, prix unitaires)
4. BC émis → numéro séquentiel BC-2026-00123
5. Réception partielle/totale → met à jour `quantite_recue`
6. Statut « facture » quand la facture fournisseur est reçue

**Interface :** 
- Liste des BC dans `modules/bons_commande/index.php`
- Création depuis l'engagement ou depuis la liste (mode autonome)
- Détail BC avec historique réception
- Impression BC (format A4 avec logo, coordonnées fournisseur, tableau lignes, totaux)

---

### 2.5 — #7 Rapprochement bancaire complet

**Principe :** Importer un relevé bancaire (CSV), faire correspondre automatiquement et manuellement.

**Algorithme de matching automatique :**
1. Par montant exact + date proche (±3 jours) — priorité 1
2. Par montant exact uniquement — priorité 2
3. Par libellé fuzzy (levenshtein < 5 sur les 30 premiers caractères) + montant — priorité 3

**Interface :**
- Importer un CSV (colonnes mappées interactivement : date, libellé, montant, type)
- Écran de rapprochement en 2 colonnes :
  - Colonne gauche : opérations bancaires saisies (non rapprochées)
  - Colonne droite : lignes du relevé (non pointées)
  - Click pour lier, bouton « Délier »
- Tableau « État de rapprochement » avec 4 sections :
  1. **Soldes de départ** (comptable vs banque)
  2. **Opérations pointées** (tableau)
  3. **Écarts non rapprochés** (débits banque non comptabilisés, crédits banque non comptabilisés, opérations comptables non bancaires)
  4. **Soldes de fin rapprochés**
- Impression état de rapprochement

---

### 2.6 — #10 Clôture d'exercice

**Principe :** Procédure guidée de clôture annuelle en 4 étapes.

**Étape 1 — Écritures d'inventaire :**
- Saisie des CCA (charges constatées d'avance), CAN (produits constatés d'avance)
- Dotations aux amortissements (générées depuis le tableau d'immobilisation)
- Provisions pour risques et charges
- Charges à payer, produits à recevoir
- Régularisation des stocks (variation de stock)

**Étape 2 — Balance de clôture :**
- Vérification que toutes les écritures d'inventaire sont passées
- Balance générale avant détermination du résultat

**Étape 3 — Détermination du résultat :**
- Calcul automatique : total classe 7 - total classe 6
- Écriture de détermination du résultat (OD de clôture) :
  - Débit compte 12 si perte, crédit compte 12 si bénéfice
  - Contrepartie : soldes des comptes 6 et 7 soldés

**Étape 4 — Clôture définitive :**
- Génération du bilan, compte de résultat
- Verrouillage de l'exercice
- Report à nouveau automatique (compte 11 ou 12 en N+1)

**Interface :** Page `modules/cloture/index.php` avec assistant pas à pas (stepper visuel : ① → ② → ③ → ④). Chaque étape affiche les écritures à passer avec possibilité de les modifier/valider.

---

### 2.7 — #6 Module Paie

**Principe :** Calcul mensuel des bulletins avec rubriques paramétrables.

**Processus mensuel :**
1. **Création période de paie** → sélection mois, exercice (ex: Mai 2026)
2. **Génération des bulletins** → pour tous les employés actifs avec contrat
   - Calcul ligne par ligne selon le type de rubrique :
     - `montant_fixe` → valeur de la rubrique
     - `pourcentage_base` → (valeur/100) × salaire_base
     - `pourcentage_brut` → (valeur/100) × (salaire_base + total_gains)
     - `formule` → expression PHP évaluée. Variables : `$salaire_base`, `$brut`, `$anciennete` (années)
   - **Calcul IRPP simplifié :** Barème progressif camerounais codé en dur :
     - 0 - 2 000 000 XAF : 0%
     - 2 000 001 - 3 000 000 : 10%
     - 3 000 001 - 5 000 000 : 15%
     - 5 000 001 - 10 000 000 : 25%
     - > 10 000 000 : 35%
   - **Calcul CNPS :** 4.20% salarié + 8.40% patronal + 1.75% AT + 7% PF
3. **Révision manuelle** → ajustement des bulletins individuels
4. **Validation** → les bulletins sont figés
5. **Paiement** → génération des ordres de virement (intégration trésorerie)

**États générés :**
- Bulletin individuel (PDF imprimable)
- État de paie global (tous les employés, totaux par rubrique)
- Journal de paie (écritures comptables : 64x débit, 422/431/447 crédit)
- Déclaration CNPS mensuelle
- Déclaration DGI (IRPP retenu à la source)

**Fichiers :**
- `modules/paie/index.php` — tableau de bord paie (périodes, stats)
- `modules/paie/bulletins.php` — gestion d'une période (liste bulletins)
- `modules/paie/bulletin_detail.php` — détail/modification d'un bulletin
- `modules/paie/rubriques.php` — CRUD rubriques de paie
- `modules/paie/declarations.php` — déclarations CNPS/DGI

---

### 2.8 — #14 Comptabilité analytique

**Principe :** Les charges/produits sont ventilés sur des axes analytiques (centres de coût, projets, activités) selon des clés de répartition.

**Deux modes de saisie :**
1. **Affectation directe** — lors de la saisie d'écriture comptable, possibilité de choisir un axe analytique pour chaque ligne de charge/produit
2. **Répartition a posteriori** — saisir des clés de répartition (ex: loyer réparti 40% CC_ADMIN, 35% CC_PROD, 25% CC_COMM)

**Reports produits :**
- Tableau de bord par centre de coût : charges, produits, marge
- Comparaison budgétaire par centre de coût
- Rapport de rentabilité par projet

**Fichiers :**
- `modules/compta_analytique/index.php` — configuration des axes
- `modules/compta_analytique/repartition.php` — clés de répartition
- Extension de `modules/comptabilite/index.php` — ajout colonne « axe » dans la saisie d'écriture

---

### 2.9 — #15 Ratios et indicateurs financiers avancés (SIG)

**Principe :** Calcul automatique des Soldes Intermédiaires de Gestion (SIG) selon le plan comptable OHADA.

**SIG calculés :**
| SIG | Calcul OHADA simplifié |
|---|---|
| Marge commerciale | Ventes (70) - Achats (60) |
| Production de l'exercice | Production vendue (70) + Production stockée (71) + Production immobilisée (72) |
| Valeur ajoutée | Marge commerciale + Production - Consommations externes (61/62/63/64 sauf 64x personnel) |
| EBE (Excédent Brut d'Exploitation) | VA + Subventions (71) - Impôts/taxes (64x) - Charges personnel (64x personnel) |
| Résultat d'exploitation | EBE + Reprises (78) - Dotations (68) - Autres charges (65/66) |
| Résultat financier | Produits financiers (77) - Charges financières (67) |
| Résultat net | Résultat exploitation + Résultat financier + Résultat HAO (78-68 HAO) |

**Ratios clés :**
- **Liquidité générale** = Actif circulant / Passif circulant
- **Liquidité réduite** = (Actif circulant - Stocks) / Passif circulant
- **Solvabilité** = Capitaux propres / Total passif
- **Autonomie financière** = Capitaux propres / Dettes financières
- **ROE** = Résultat net / Capitaux propres
- **ROA** = Résultat net / Total actif
- **Rentabilité commerciale** = Résultat net / Chiffre d'affaires

**Affichage :**
- Tableau SIG avec colonnes : N, N-1, Variation (%), Évolution
- Tableau ratios avec interprétation (couleur : vert = bon, orange = acceptable, rouge = dégradé)
- Graphique comparatif N vs N-1
- Export Excel intégré

**Fichier :** Extension de `modules/reporting/index.php` avec nouvel onglet « SIG & Ratios »

---

## 3. Architecture des fichiers

### Nouveaux fichiers créés

| Fichier | Module |
|---|---|
| `modules/paie/index.php` | Paie — dashboard et liste périodes |
| `modules/paie/bulletins.php` | Paie — gestion période |
| `modules/paie/bulletin_detail.php` | Paie — détail bulletin |
| `modules/paie/rubriques.php` | Paie — CRUD rubriques |
| `modules/paie/declarations.php` | Paie — déclarations |
| `modules/paie/imprimer_bulletin.php` | Paie — impression bulletin |
| `modules/paie/etat_paie.php` | Paie — état global |
| `modules/bons_commande/index.php` | Procurement — liste BC |
| `modules/bons_commande/creer.php` | Procurement — création BC |
| `modules/bons_commande/detail.php` | Procurement — détail BC |
| `modules/bons_commande/imprimer.php` | Procurement — impression BC |
| `modules/cloture/index.php` | Clôture exercice — assistant |
| `modules/exercices/index.php` | Exercices — gestion |
| `modules/compta_analytique/index.php` | Compta analytique — axes |
| `modules/compta_analytique/repartition.php` | Compta analytique — clés |
| `includes/export.php` | Export Excel/CSV (fonctions) |
| `includes/rapprochement.php` | Logique matching bancaire |
| `libs/PhpSpreadsheet/` | Bibliothèque externe |
| `sql/migrations/009_features_avances.sql` | Migration SQL |

### Fichiers modifiés

| Fichier | Modification |
|---|---|
| `modules/tresorerie/index.php` | +Import relevé, +Workflow validation |
| `modules/comptabilite/index.php` | +Colonne analytique saisie écriture |
| `modules/reporting/index.php` | +Onglet SIG & Ratios |
| `includes/functions.php` | +`exerciceEstVerrouille()`, +`calculerSIG()` |
| `includes/header.php` | +Liens nav (Paie, BC, Clôture, Analytique) |
| `includes/footer.php` | +Export JS si nécessaire |
| `dashboard.php` | +KPIs paie, BC, rapprochement |
| `sql/schema.sql` | +Ajout tables (pour nouvelle install) |

---

## 4. Permissions (nouvelles entrées dans la matrice)

```json
{
  "paie": {
    "consulter": true,
    "gerer_rubriques": false,
    "gerer_bulletins": true,
    "valider_paie": false,
    "declarer": false
  },
  "bons_commande": {
    "consulter": true,
    "creer": true,
    "valider": false,
    "recevoir": true
  },
  "cloture": {
    "consulter": true,
    "gerer_exercice": false,
    "ecritures_inventaire": false,
    "cloturer": false
  },
  "compta_analytique": {
    "consulter": true,
    "configurer": false,
    "affecter": true
  },
  "tresorerie": {
    "valider_operations": false,
    "rapprocher": false,
    "importer_releve": false
  }
}
```

---

## 5. Ordre d'implémentation recommandé

1. **Migration SQL** (tables + modifications + seed data)
2. **#19 Verrouillage exercices** (fondation, touche tout le reste)
3. **#9 Export données** (bibliothèque, utilisée par tous les modules)
4. **#16 Validation bancaire** (modification légère, impact immédiat)
5. **#10 Clôture exercice** (inclut immobilisations, dépend de #19)
6. **#12 Bons de commande** (dépend des engagements existants)
7. **#7 Rapprochement bancaire** (dépend de #16)
8. **#15 Ratios/SIG** (dernier, purement reporting)
9. **#6 Paie** (le plus complexe, en dernier)
10. **#14 Compta analytique** (touche à tout, en dernier)
