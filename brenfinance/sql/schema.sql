-- BrenFinance Suite - Schéma de base de données
-- MySQL 5.7+

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS brenfinance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE brenfinance;

-- ============================================================
-- MODULE ADMINISTRATION
-- ============================================================

CREATE TABLE IF NOT EXISTS entreprises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    sigle VARCHAR(50),
    adresse TEXT,
    telephone VARCHAR(50),
    email VARCHAR(100),
    registre_commerce VARCHAR(100),
    numero_contribuable VARCHAR(100),
    logo VARCHAR(255),
    theme VARCHAR(20) DEFAULT 'default',
    police VARCHAR(100) DEFAULT 'Segoe UI',
    devise VARCHAR(10) DEFAULT 'FCFA',
    exercice_courant YEAR,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(50),
    responsable VARCHAR(100),
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    responsable VARCHAR(100),
    responsable_id INT,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id),
    FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT,
    service_id INT,
    role_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    matricule VARCHAR(30) UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    telephone VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    statut ENUM('actif','inactif','suspendu') DEFAULT 'actif',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE RÉFÉRENTIELS
-- ============================================================

CREATE TABLE IF NOT EXISTS types_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    sens ENUM('debit','credit') NOT NULL,
    categorie ENUM('caisse','banque','virement') DEFAULT 'caisse',
    statut ENUM('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS modes_paiement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beneficiaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('interne','externe') DEFAULT 'externe',
    code VARCHAR(30) UNIQUE,
    nom VARCHAR(150) NOT NULL,
    user_id INT,
    adresse TEXT,
    telephone VARCHAR(50),
    email VARCHAR(100),
    rib VARCHAR(50),
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) UNIQUE,
    nom VARCHAR(150) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(50),
    email VARCHAR(100),
    rib VARCHAR(50),
    delai_paiement INT DEFAULT 30,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_comptable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(200) NOT NULL,
    classe TINYINT NOT NULL,
    type_compte ENUM('actif','passif','charge','produit','bilan') NOT NULL,
    sens_normal ENUM('debit','credit') NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS groupes_proprietaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- MODULE CAISSE
-- ============================================================

CREATE TABLE IF NOT EXISTS caisses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    devise VARCHAR(10) DEFAULT 'FCFA',
    solde_initial DECIMAL(15,2) DEFAULT 0,
    solde_actuel DECIMAL(15,2) DEFAULT 0,
    responsable_id INT,
    statut ENUM('ouverte','fermee','suspendue') DEFAULT 'fermee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id),
    FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions_caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_ouverture TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    solde_ouverture DECIMAL(15,2) NOT NULL,
    date_fermeture TIMESTAMP NULL,
    solde_fermeture DECIMAL(15,2) NULL,
    solde_theorique DECIMAL(15,2) NULL,
    ecart DECIMAL(15,2) NULL,
    observations TEXT,
    statut ENUM('ouverte','fermee') DEFAULT 'ouverte',
    FOREIGN KEY (caisse_id) REFERENCES caisses(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operations_caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    caisse_id INT NOT NULL,
    type_operation_id INT NOT NULL,
    type_depense_id INT NULL DEFAULT NULL,
    groupe_proprietaire_id INT,
    beneficiaire_id INT,
    beneficiaire_nom VARCHAR(150),
    destination_id INT,
    mode_paiement_id INT,
    numero_piece VARCHAR(50),
    date_operation DATE NOT NULL,
    heure_operation TIME NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    sens ENUM('debit','credit') NOT NULL,
    solde_apres DECIMAL(15,2) NOT NULL,
    reference_externe VARCHAR(100),
    engagement_id INT NULL,
    service_id INT,
    saisi_par INT NOT NULL,
    annule TINYINT(1) DEFAULT 0,
    motif_annulation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions_caisse(id),
    FOREIGN KEY (caisse_id) REFERENCES caisses(id),
    FOREIGN KEY (type_operation_id) REFERENCES types_operations(id),
    FOREIGN KEY (groupe_proprietaire_id) REFERENCES groupes_proprietaires(id),
    FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id),
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id),
    FOREIGN KEY (destination_id) REFERENCES destinations(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transferts_caisse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caisse_source_id INT NOT NULL,
    caisse_destination_id INT NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    date_transfert DATE NOT NULL,
    motif TEXT,
    statut ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
    valide_par INT,
    date_validation TIMESTAMP NULL,
    operation_source_id INT,
    operation_destination_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caisse_source_id) REFERENCES caisses(id),
    FOREIGN KEY (caisse_destination_id) REFERENCES caisses(id),
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE TRÉSORERIE (BANQUE)
-- ============================================================

CREATE TABLE IF NOT EXISTS comptes_bancaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT NOT NULL,
    banque VARCHAR(100) NOT NULL,
    agence_banque VARCHAR(100),
    numero_compte VARCHAR(50) NOT NULL UNIQUE,
    rib VARCHAR(50),
    libelle VARCHAR(100) NOT NULL,
    devise VARCHAR(10) DEFAULT 'FCFA',
    solde_initial DECIMAL(15,2) DEFAULT 0,
    solde_actuel DECIMAL(15,2) DEFAULT 0,
    solde_rapproche DECIMAL(15,2) DEFAULT 0,
    date_derniere_releve DATE,
    statut ENUM('actif','inactif','cloture') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operations_bancaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_id INT NOT NULL,
    type_operation_id INT,
    date_operation DATE NOT NULL,
    date_valeur DATE,
    libelle VARCHAR(255) NOT NULL,
    reference VARCHAR(100),
    montant DECIMAL(15,2) NOT NULL,
    sens ENUM('debit','credit') NOT NULL,
    solde_apres DECIMAL(15,2) NOT NULL,
    rapproche TINYINT(1) DEFAULT 0,
    date_rapprochement DATE,
    engagement_id INT,
    saisi_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_id) REFERENCES comptes_bancaires(id),
    FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS previsions_tresorerie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_id INT,
    periode_debut DATE NOT NULL,
    periode_fin DATE NOT NULL,
    type ENUM('entree','sortie') NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    montant_prevu DECIMAL(15,2) NOT NULL,
    montant_realise DECIMAL(15,2) DEFAULT 0,
    observations TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE ENGAGEMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS types_depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    statut ENUM('actif','inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_engagement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NOT NULL UNIQUE,
    demandeur_id INT NOT NULL,
    service_id INT NOT NULL,
    fournisseur_id INT,
    beneficiaire_id INT,
    mode_paiement_id INT,
    type_operation_id INT NOT NULL DEFAULT 3,
    type_depense_id INT NULL DEFAULT NULL,
    groupe_proprietaire_id INT,
    destination_id INT,
    objet TEXT NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    montant_execute DECIMAL(15,2) DEFAULT 0,
    montant_restant DECIMAL(15,2) DEFAULT 0,
    motif_solder TEXT NULL,
    date_solder TIMESTAMP NULL,
    date_besoin DATE,
    priorite ENUM('normale','urgente','tres_urgente') DEFAULT 'normale',
    pieces_jointes JSON,
    statut ENUM('brouillon','soumis','valide_hierarchie','valide_comptable','valide_daf','approuve','execution_partielle','execute','solde','rejete','renvoye','annule') DEFAULT 'brouillon',
    commentaire_rejet TEXT,
    compte_imputation VARCHAR(20),
    budget_ligne_id INT,
    caisse_id INT,
    compte_bancaire_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (type_operation_id) REFERENCES types_operations(id),
    FOREIGN KEY (groupe_proprietaire_id) REFERENCES groupes_proprietaires(id),
    FOREIGN KEY (destination_id) REFERENCES destinations(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS validations_engagement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    engagement_id INT NOT NULL,
    etape ENUM('hierarchie','comptable','daf','execution','execution_partielle','solder') NOT NULL,
    valideur_id INT NOT NULL,
    action ENUM('approuve','rejete','renvoi') NOT NULL,
    commentaire TEXT,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id),
    FOREIGN KEY (valideur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lignes_engagement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    engagement_id INT NOT NULL,
    ordre INT NOT NULL DEFAULT 1,
    libelle VARCHAR(255) NOT NULL,
    quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
    cout_unitaire DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MODULE ORDRE DE MISSION
-- ============================================================

CREATE TABLE IF NOT EXISTS ordres_mission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30) NOT NULL UNIQUE,
    demandeur_id INT NOT NULL,
    service_id INT NOT NULL,
    destination_id INT,
    objet TEXT NOT NULL,
    lieu_mission VARCHAR(200),
    adresse_mission TEXT,
    date_depart DATE NOT NULL,
    heure_depart VARCHAR(5),
    date_retour DATE NOT NULL,
    heure_retour VARCHAR(5),
    moyen_transport VARCHAR(100),
    personne_urgence VARCHAR(100),
    tel_urgence VARCHAR(20),
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    mode_paiement_id INT,
    caisse_id INT,
    plafond_hebergement DECIMAL(15,2) DEFAULT NULL,
    plafond_repas DECIMAL(15,2) DEFAULT NULL,
    priorite ENUM('normale','urgente','tres_urgente') DEFAULT 'normale',
    pieces_jointes JSON,
    statut ENUM('brouillon','soumis','valide_hierarchie','approuve','execute','rejete','renvoye','annule') DEFAULT 'brouillon',
    commentaire_rejet TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (destination_id) REFERENCES destinations(id),
    FOREIGN KEY (mode_paiement_id) REFERENCES modes_paiement(id),
    FOREIGN KEY (caisse_id) REFERENCES caisses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lignes_ordre_mission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordre_mission_id INT NOT NULL,
    ordre INT NOT NULL DEFAULT 1,
    libelle VARCHAR(255) NOT NULL,
    quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
    cout_unitaire DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (ordre_mission_id) REFERENCES ordres_mission(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS validations_ordre_mission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordre_mission_id INT NOT NULL,
    etape ENUM('hierarchie','daf','execution') NOT NULL,
    valideur_id INT NOT NULL,
    action ENUM('approuve','rejete','renvoi') NOT NULL,
    commentaire TEXT,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ordre_mission_id) REFERENCES ordres_mission(id),
    FOREIGN KEY (valideur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE BUDGET
-- ============================================================

CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agence_id INT,
    service_id INT,
    exercice YEAR NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    type ENUM('previsionnel','revise','supplementaire') DEFAULT 'previsionnel',
    statut ENUM('brouillon','valide','cloture') DEFAULT 'brouillon',
    date_validation DATE,
    valide_par INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agences(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lignes_budget (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    compte_comptable VARCHAR(20),
    libelle VARCHAR(200) NOT NULL,
    montant_prevu DECIMAL(15,2) DEFAULT 0,
    montant_engage DECIMAL(15,2) DEFAULT 0,
    montant_realise DECIMAL(15,2) DEFAULT 0,
    seuil_alerte DECIMAL(5,2) DEFAULT 80.00,
    notes TEXT,
    FOREIGN KEY (budget_id) REFERENCES budgets(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE COMPTABILITÉ
-- ============================================================

CREATE TABLE IF NOT EXISTS journaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL,
    type ENUM('caisse','banque','vente','achat','od','ouverture','cloture') NOT NULL,
    compte_contrepartie VARCHAR(20),
    statut ENUM('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ecritures_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_id INT NOT NULL,
    exercice YEAR NOT NULL,
    periode TINYINT NOT NULL,
    date_ecriture DATE NOT NULL,
    numero_piece VARCHAR(50),
    libelle VARCHAR(255) NOT NULL,
    compte VARCHAR(20) NOT NULL,
    tiers_id INT,
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    lettre VARCHAR(10),
    date_lettrage DATE,
    rapproche TINYINT(1) DEFAULT 0,
    source_type ENUM('caisse','banque','engagement','manuel') DEFAULT 'manuel',
    source_id INT,
    saisi_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_id) REFERENCES journaux(id),
    FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ============================================================
-- MODULE AUDIT
-- ============================================================

CREATE TABLE IF NOT EXISTS journal_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    table_cible VARCHAR(50),
    enregistrement_id INT,
    anciennes_valeurs JSON,
    nouvelles_valeurs JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (utilisateur_id),
    INDEX idx_module (module),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- REMEMBER ME TOKENS
-- ============================================================

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    selector VARCHAR(64) NOT NULL UNIQUE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_selector (selector),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    engagement_id INT,
    ordre_mission_id INT,
    type ENUM('soumis','valide','approuve','rejete','renvoye','execute') NOT NULL,
    titre VARCHAR(200) NOT NULL,
    message TEXT,
    lue TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (engagement_id) REFERENCES demandes_engagement(id),
    FOREIGN KEY (ordre_mission_id) REFERENCES ordres_mission(id),
    INDEX idx_user_lue (utilisateur_id, lue),
    INDEX idx_user_date (utilisateur_id, created_at)
) ENGINE=InnoDB;

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

INSERT INTO roles (nom, description, permissions) VALUES
('super_admin', 'Administrateur système', '{"all": true}'),
('daf', 'Directeur Administratif et Financier', '{"engagements": {"valider_daf": true, "consulter": true, "executer": true}, "budget": {"all": true}, "reporting": {"all": true}, "tresorerie": {"consulter": true}, "comptabilite": {"consulter": true}, "audit": {"consulter": true}, "ordre_mission": {"valider_daf": true, "consulter": true}, "operations_caisse": {"consulter": true}, "radnex": {"consulter": true}, "decharge": {"all": true}, "rh": {"consulter": true, "importer": true, "modifier": true}}'),
('comptable', 'Comptable', '{"caisse": {"all": true}, "operations_caisse": {"all": true}, "tresorerie": {"all": true}, "comptabilite": {"all": true}, "engagements": {"valider_comptable": true, "consulter": true}, "audit": {"consulter": true}, "referentiels": {"consulter": true, "saisir": true}, "ordre_mission": {"consulter": true}, "radnex": {"consulter": true}, "decharge": {"valider": true, "consulter": true}, "budget": {"consulter": true}}'),
('caissier', 'Caissier', '{"caisse": {"saisir": true, "consulter": true}, "operations_caisse": {"saisir": true, "consulter": true, "annuler": true}, "ordre_mission": {"executer": true}, "radnex": {"consulter": true}, "decharge": {"executer": true, "consulter": true}, "engagements": {"consulter": true}}'),
('demandeur', 'Demandeur', '{"engagements": {"creer": true, "consulter_propres": true}, "ordre_mission": {"creer": true, "consulter_propres": true}, "radnex": {"consulter": true}, "decharge": {"creer": true, "consulter": true}}'),
('valideur_n1', 'Valideur hiérarchique N+1', '{"engagements": {"valider_hierarchie": true, "consulter": true}, "ordre_mission": {"valider_hierarchie": true, "consulter": true}, "radnex": {"consulter": true}, "decharge": {"creer": true, "consulter": true}}');

INSERT INTO types_operations (code, libelle, sens, categorie) VALUES
('ENT_ESP', 'Entrée espèces', 'credit', 'caisse'),
('SOR_ESP', 'Sortie espèces', 'debit', 'caisse'),
('PAI_FOURN', 'Paiement fournisseur', 'debit', 'caisse'),
('REG_CLIENT', 'Règlement client', 'credit', 'caisse'),
('AVANCE', 'Avance sur salaire', 'debit', 'caisse'),
('REMBT', 'Remboursement', 'credit', 'caisse'),
('VIR_BNQ', 'Virement bancaire sortant', 'debit', 'banque'),
('REC_BNQ', 'Virement bancaire entrant', 'credit', 'banque'),
('CHQ_EMI', 'Chèque émis', 'debit', 'banque'),
('CHQ_REC', 'Chèque reçu', 'credit', 'banque'),
('ANNUL', 'Annulation Opération', 'credit', 'caisse'),
('BON_PROPRIETAIRE', 'Bon propriétaire', 'debit', 'caisse');

INSERT INTO modes_paiement (code, libelle) VALUES
('ESP', 'Espèces'),
('CHQ', 'Chèque'),
('VIR', 'Virement'),
('MOBI', 'Mobile Money'),
('CB', 'Carte bancaire'),
('LC', 'Lettre de crédit');

INSERT INTO types_depenses (code, libelle) VALUES
('FOURN', 'Fournitures de bureau'),
('PREST', 'Prestations de service'),
('DEPLAC', 'Déplacements et missions'),
('MAINT', 'Maintenance et réparations'),
('LOYER', 'Loyers et charges locatives'),
('COMMUN', 'Frais de communication'),
('ASSUR', 'Assurances'),
('SALAIRE', 'Rémunérations et charges sociales'),
('FORM', 'Formation du personnel'),
('DIVERS', 'Dépenses diverses'),
('BON_PROPRIETAIRE', 'Bon propriétaire');

INSERT INTO destinations (code, libelle) VALUES
('SIEGE', 'Siège social'),
('AGENCE', 'Antenne régionale'),
('PROJET', 'Projet'),
('SERVICE', 'Service interne'),
('EXTERN', 'Extérieur');

INSERT INTO groupes_proprietaires (code, libelle) VALUES
('GP_A', 'Groupe A'),
('GP_B', 'Groupe B');

INSERT INTO journaux (code, libelle, type) VALUES
('CAI', 'Journal de caisse', 'caisse'),
('BNQ', 'Journal de banque', 'banque'),
('VTE', 'Journal des ventes', 'vente'),
('ACH', 'Journal des achats', 'achat'),
('OD', 'Opérations diverses', 'od');

INSERT INTO plan_comptable (compte, libelle, classe, type_compte, sens_normal) VALUES
('101000', 'Capital social', 1, 'passif', 'credit'),
('401000', 'Fournisseurs', 4, 'passif', 'credit'),
('411000', 'Clients', 4, 'actif', 'debit'),
('421000', 'Personnel - rémunérations dues', 4, 'passif', 'credit'),
('512000', 'Banque', 5, 'actif', 'debit'),
('571000', 'Caisse', 5, 'actif', 'debit'),
('601000', 'Achats de marchandises', 6, 'charge', 'debit'),
('621000', 'Personnel extérieur', 6, 'charge', 'debit'),
('622000', 'Rémunérations intermédiaires', 6, 'charge', 'debit'),
('623000', 'Publicité, pub, relations publiques', 6, 'charge', 'debit'),
('624000', 'Transports de biens et livraisons', 6, 'charge', 'debit'),
('625000', 'Déplacements, missions, réceptions', 6, 'charge', 'debit'),
('626000', 'Frais postaux et frais télécom', 6, 'charge', 'debit'),
('627000', 'Services bancaires', 6, 'charge', 'debit'),
('700000', 'Ventes de marchandises', 7, 'produit', 'credit'),
('706000', 'Prestations de services', 7, 'produit', 'credit');

-- Entreprise par défaut
INSERT INTO entreprises (nom, sigle, devise, exercice_courant) VALUES
('Mon Entreprise', 'ME', 'FCFA', YEAR(CURDATE()));

-- Agence par défaut
INSERT INTO agences (entreprise_id, code, nom) VALUES (1, 'SIE', 'Siège Social');

-- Service par défaut
INSERT INTO services (agence_id, code, nom) VALUES (1, 'DAF', 'Direction Administrative et Financière');

-- ⚠ Le compte administrateur est créé via setup.php (http://localhost/brenfinance/setup.php)
-- setup.php génère un hash bcrypt valide directement sur votre serveur PHP
-- Ne pas insérer un hash ici : il dépend de la version PHP installée sur votre machine

SET FOREIGN_KEY_CHECKS=1;
