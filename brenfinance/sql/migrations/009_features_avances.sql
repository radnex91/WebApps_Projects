-- BrenFinance Suite - Migration 009: Fonctionnalités Avancées
-- Paie, Rapprochement bancaire, Clôture exercice, Bons de commande, Compta analytique
-- Exécuter: mysql -u root brenfinance < sql/migrations/009_features_avances.sql

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- ============================================================
-- MODULE EXERCICES (Verrouillage, #19)
-- ============================================================

CREATE TABLE IF NOT EXISTS exercices (
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
    UNIQUE KEY uk_exercice_code (code)
) ENGINE=InnoDB;

-- Seed exercice courant
INSERT INTO exercices (code, date_debut, date_fin, statut) VALUES
('2025', '2025-01-01', '2025-12-31', 'cloture_definitive'),
('2026', '2026-01-01', '2026-12-31', 'ouvert');

-- Ajout verrouillage à entreprises
ALTER TABLE entreprises
    ADD COLUMN IF NOT EXISTS exercice_verrouille TINYINT(1) DEFAULT 0 AFTER exercice_courant,
    ADD COLUMN IF NOT EXISTS date_verrouillage DATE NULL AFTER exercice_verrouille;

-- Ajout exercice_id aux écritures comptables
ALTER TABLE ecritures_comptables
    ADD COLUMN IF NOT EXISTS exercice_id INT NULL AFTER journal_id;

-- ============================================================
-- MODULE IMMOBILISATIONS & AMORTISSEMENTS (lié #10)
-- ============================================================

CREATE TABLE IF NOT EXISTS immobilisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(300) NOT NULL,
    compte_immobilisation VARCHAR(20) NOT NULL COMMENT '22x, 23x, 24x OHADA',
    compte_amortissement VARCHAR(20) NOT NULL COMMENT '28x OHADA',
    date_acquisition DATE NOT NULL,
    date_mise_service DATE NOT NULL,
    valeur_acquisition DECIMAL(15,2) NOT NULL,
    valeur_residuelle DECIMAL(15,2) DEFAULT 0,
    duree_ans INT NOT NULL,
    taux_amortissement DECIMAL(5,2) NOT NULL COMMENT '% annuel',
    mode_amortissement ENUM('lineaire','degressif') DEFAULT 'lineaire',
    cumul_amortissement DECIMAL(15,2) DEFAULT 0,
    date_dernier_amortissement DATE NULL,
    statut ENUM('actif','cede','reforme') DEFAULT 'actif',
    date_cession DATE NULL,
    prix_cession DECIMAL(15,2) NULL,
    plus_value DECIMAL(15,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dotations_amortissement (
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

-- ============================================================
-- MODULE CLOTURE EXERCICE (#10)
-- ============================================================

CREATE TABLE IF NOT EXISTS ecritures_inventaire (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercice_id INT NOT NULL,
    type ENUM('stock','amortissement','provision','can','cca','charge_a_payer','produit_a_recevoir','autre') NOT NULL,
    libelle VARCHAR(300) NOT NULL,
    compte_debit VARCHAR(20) NOT NULL,
    compte_credit VARCHAR(20) NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    date_ecriture DATE NOT NULL,
    reference VARCHAR(50),
    ecriture_comptable_id INT NULL,
    cree_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exercice_id) REFERENCES exercices(id),
    FOREIGN KEY (ecriture_comptable_id) REFERENCES ecritures_comptables(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE PAIE (#6) - 6 tables
-- ============================================================

CREATE TABLE IF NOT EXISTS periodes_paie (
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

CREATE TABLE IF NOT EXISTS rubriques_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    type ENUM('gain','retenue','cotisation_patronale','indemnite') NOT NULL,
    calcul ENUM('montant_fixe','pourcentage_base','pourcentage_brut','formule') DEFAULT 'montant_fixe',
    valeur DECIMAL(15,2) NULL COMMENT 'montant fixe ou pourcentage',
    formule TEXT NULL COMMENT 'expression PHP evalable: $salaire_base, $brut, $anciennete',
    imposable TINYINT(1) DEFAULT 1,
    cotisable_cnps TINYINT(1) DEFAULT 1,
    actif TINYINT(1) DEFAULT 1,
    ordre_affichage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contrats_employes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL UNIQUE,
    salaire_base DECIMAL(15,2) NOT NULL,
    date_embauche DATE NOT NULL,
    date_sortie DATE NULL,
    type_contrat ENUM('cdi','cdd','stage','prestataire','interim') DEFAULT 'cdi',
    matricule_cnps VARCHAR(50),
    numero_compte VARCHAR(50) COMMENT 'RIB virement',
    banque VARCHAR(100),
    statut ENUM('actif','suspendu','termine') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bulletins_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    salaire_base DECIMAL(15,2) NOT NULL,
    total_gains DECIMAL(15,2) DEFAULT 0,
    total_retenues DECIMAL(15,2) DEFAULT 0,
    net_a_payer DECIMAL(15,2) NOT NULL,
    net_en_faveur DECIMAL(15,2) DEFAULT 0,
    charges_patronales DECIMAL(15,2) DEFAULT 0,
    cout_total DECIMAL(15,2) DEFAULT 0,
    statut ENUM('brouillon','valide','paye') DEFAULT 'brouillon',
    date_paiement DATE NULL,
    reference_paiement VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_id) REFERENCES periodes_paie(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    UNIQUE KEY uk_bulletin (periode_id, utilisateur_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lignes_bulletin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulletin_id INT NOT NULL,
    rubrique_id INT NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    type ENUM('gain','retenue','cotisation_patronale','indemnite') NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    ordre INT DEFAULT 0,
    FOREIGN KEY (bulletin_id) REFERENCES bulletins_paie(id) ON DELETE CASCADE,
    FOREIGN KEY (rubrique_id) REFERENCES rubriques_paie(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS declarations_paie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    type ENUM('cnps','dgi_irpp','dgi_cac','etat_paie') NOT NULL,
    reference VARCHAR(50),
    montant_declare DECIMAL(15,2) NOT NULL,
    date_declaration DATE NOT NULL,
    date_echeance DATE NULL,
    statut ENUM('brouillon','declaree','payee') DEFAULT 'brouillon',
    fichier VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_id) REFERENCES periodes_paie(id)
) ENGINE=InnoDB;

-- Seed rubriques paie standards Cameroun
INSERT INTO rubriques_paie (code, libelle, type, calcul, valeur, ordre_affichage) VALUES
('SAL_BASE', 'Salaire de base', 'gain', 'montant_fixe', NULL, 1),
('IND_TRANSP', 'Indemnité de transport', 'gain', 'montant_fixe', NULL, 2),
('IND_LOGEM', 'Indemnité de logement', 'gain', 'pourcentage_base', 15.00, 3),
('IND_RESP', 'Indemnité de responsabilité', 'gain', 'montant_fixe', NULL, 4),
('PR_ANCIEN', 'Prime d''ancienneté', 'gain', 'pourcentage_base', 2.00, 5),
('HE_SUPP', 'Heures supplémentaires', 'gain', 'montant_fixe', NULL, 6),
('AV_NATURE', 'Avantage en nature', 'gain', 'montant_fixe', NULL, 7),
('RET_CNPS', 'Retenue CNPS (part salariale)', 'retenue', 'pourcentage_brut', 4.20, 1),
('RET_IRPP', 'Retenue IRPP', 'retenue', 'formule', NULL, 2),
('COT_CNPS_P', 'Cotisation CNPS patronale', 'cotisation_patronale', 'pourcentage_brut', 8.40, 1),
('COT_ACC', 'Accidents du travail (patronale)', 'cotisation_patronale', 'pourcentage_brut', 1.75, 2),
('COT_PF', 'Prestations familiales (patronale)', 'cotisation_patronale', 'pourcentage_brut', 7.00, 3);

-- ============================================================
-- MODULE RAPPROCHEMENT BANCAIRE (#7) - 3 tables
-- ============================================================

CREATE TABLE IF NOT EXISTS rapprochements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_bancaire_id INT NOT NULL,
    date_rapprochement DATE NOT NULL,
    solde_comptable DECIMAL(15,2) NOT NULL,
    solde_banque DECIMAL(15,2) NOT NULL,
    ecart DECIMAL(15,2) DEFAULT 0,
    statut ENUM('brouillon','finalise') DEFAULT 'brouillon',
    cree_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_bancaire_id) REFERENCES comptes_bancaires(id),
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS releves_bancaires (
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

CREATE TABLE IF NOT EXISTS lignes_releve (
    id INT AUTO_INCREMENT PRIMARY KEY,
    releve_id INT NOT NULL,
    date_operation DATE NOT NULL,
    date_valeur DATE NULL,
    libelle VARCHAR(300) NOT NULL,
    reference VARCHAR(100),
    montant DECIMAL(15,2) NOT NULL COMMENT 'positif=credit, negatif=debit',
    type ENUM('credit','debit') NOT NULL,
    pointe TINYINT(1) DEFAULT 0,
    operation_bancaire_id INT NULL,
    rapproche_id INT NULL,
    FOREIGN KEY (releve_id) REFERENCES releves_bancaires(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_bancaire_id) REFERENCES operations_bancaires(id),
    FOREIGN KEY (rapproche_id) REFERENCES rapprochements(id),
    INDEX idx_non_pointe (pointe)
) ENGINE=InnoDB;

-- ============================================================
-- VALIDATION BANCAIRE (#16) - modification operations_bancaires
-- ============================================================

ALTER TABLE operations_bancaires
    ADD COLUMN IF NOT EXISTS statut ENUM('brouillon','soumis','valide_comptable','valide_daf','valide','rejete') DEFAULT 'valide' AFTER rapproche,
    ADD COLUMN IF NOT EXISTS valide_par_comptable INT NULL AFTER statut,
    ADD COLUMN IF NOT EXISTS date_validation_comptable TIMESTAMP NULL AFTER valide_par_comptable,
    ADD COLUMN IF NOT EXISTS valide_par_daf INT NULL AFTER date_validation_comptable,
    ADD COLUMN IF NOT EXISTS date_validation_daf TIMESTAMP NULL AFTER valide_par_daf,
    ADD COLUMN IF NOT EXISTS commentaire_rejet VARCHAR(500) NULL AFTER date_validation_daf,
    ADD COLUMN IF NOT EXISTS cree_par INT NULL AFTER commentaire_rejet;

-- ============================================================
-- MODULE BONS DE COMMANDE (#12) - 2 tables
-- ============================================================

CREATE TABLE IF NOT EXISTS bons_commande (
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

CREATE TABLE IF NOT EXISTS lignes_bon_commande (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bon_commande_id INT NOT NULL,
    ordre INT DEFAULT 0,
    libelle VARCHAR(300) NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    unite VARCHAR(20) DEFAULT 'unité',
    prix_unitaire DECIMAL(15,2) NOT NULL,
    montant_ligne DECIMAL(15,2) NOT NULL,
    quantite_recue INT DEFAULT 0,
    FOREIGN KEY (bon_commande_id) REFERENCES bons_commande(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MODULE COMPTABILITE ANALYTIQUE (#14) - 3 tables
-- ============================================================

CREATE TABLE IF NOT EXISTS axes_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('centre_cout','projet','activite','produit','region','client_interne') NOT NULL,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    parent_id INT NULL COMMENT 'hiérarchie',
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES axes_analytiques(id),
    UNIQUE KEY uk_type_code (type, code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sections_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    libelle VARCHAR(200) NOT NULL,
    type ENUM('pourcentage','montant','unite_oeuvre') NOT NULL,
    unite_oeuvre VARCHAR(50) COMMENT 'm2, heures, km, tonnes...',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS affectations_analytiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_comptable_id INT NULL,
    source_type ENUM('ecriture','ligne_bulletin','operation_caisse','operation_bancaire','engagement_ligne') NOT NULL,
    source_id INT NOT NULL,
    axe_id INT NOT NULL,
    section_id INT NULL,
    montant DECIMAL(15,2) NOT NULL,
    pourcentage DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (axe_id) REFERENCES axes_analytiques(id),
    FOREIGN KEY (section_id) REFERENCES sections_analytiques(id),
    FOREIGN KEY (ecriture_comptable_id) REFERENCES ecritures_comptables(id),
    INDEX idx_source (source_type, source_id)
) ENGINE=InnoDB;

-- Seed axes analytiques par défaut
INSERT INTO axes_analytiques (type, code, libelle) VALUES
('centre_cout', 'CC_ADMIN', 'Centre Administration'),
('centre_cout', 'CC_FIN', 'Centre Finances'),
('centre_cout', 'CC_TECH', 'Centre Technique'),
('centre_cout', 'CC_RH', 'Centre Ressources Humaines'),
('projet', 'PROJ_GEN', 'Fonctionnement général'),
('projet', 'PROJ_INV', 'Projets d''investissement'),
('region', 'REG_SIEGE', 'Siège social'),
('region', 'REG_NORD', 'Région Nord'),
('region', 'REG_SUD', 'Région Sud');

-- Seed sections analytiques
INSERT INTO sections_analytiques (code, libelle, type) VALUES
('REP_EGALE', 'Répartition équitable', 'pourcentage'),
('REP_EFFECTIF', 'Répartition par effectif', 'pourcentage'),
('REP_SURFACE', 'Répartition par surface (m²)', 'unite_oeuvre');

SET FOREIGN_KEY_CHECKS=1;
