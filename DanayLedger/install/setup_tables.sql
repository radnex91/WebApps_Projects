-- DanayLedger v2 - Tables manquantes
-- Exécuter après le setup initial

CREATE TABLE IF NOT EXISTS recette (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    date DATE NOT NULL,
    montantexpedition DECIMAL(15,2) NOT NULL DEFAULT 0,
    montantAccompagnement DECIMAL(15,2) NOT NULL DEFAULT 0,
    agence_id INT DEFAULT NULL,
    nomoperateur VARCHAR(100) DEFAULT NULL,
    nomediteur VARCHAR(100) DEFAULT NULL,
    datesaisie DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dateedite DATE DEFAULT NULL,
    numerorecu VARCHAR(50) DEFAULT NULL,
    destination VARCHAR(100) DEFAULT NULL,
    guichetier VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    justificatif_path VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    validated_by INT DEFAULT NULL,
    statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    INDEX idx_date (date),
    INDEX idx_agence (agence_id),
    INDEX idx_statut (statut),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    date_depense DATE NOT NULL,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    categorie_id INT DEFAULT NULL,
    type_operation_id INT DEFAULT NULL,
    agence_id INT DEFAULT NULL,
    vehicule_id INT DEFAULT NULL,
    nature VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    justificatif_path VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    validated_by INT DEFAULT NULL,
    statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (type_operation_id) REFERENCES types_operations(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    INDEX idx_date (date_depense),
    INDEX idx_agence (agence_id),
    INDEX idx_statut (statut),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recettes_camions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    date_recette DATE NOT NULL,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0,
    vehicule_id INT DEFAULT NULL,
    agence_id INT DEFAULT NULL,
    categorie_id INT DEFAULT NULL,
    trajet VARCHAR(200) DEFAULT NULL,
    client VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    justificatif_path VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    validated_by INT DEFAULT NULL,
    statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    INDEX idx_date (date_recette),
    INDEX idx_vehicule (vehicule_id),
    INDEX idx_agence (agence_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS versement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    dateversement DATE NOT NULL,
    refversement VARCHAR(50) DEFAULT NULL,
    sommeverse DECIMAL(15,2) NOT NULL DEFAULT 0,
    agenceverse_id INT DEFAULT NULL,
    bank_id INT DEFAULT NULL,
    nomoperateur VARCHAR(100) DEFAULT NULL,
    nomediteur VARCHAR(100) DEFAULT NULL,
    dateedite DATE DEFAULT NULL,
    datesaisie DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recettejourneeagence DECIMAL(15,2) DEFAULT NULL,
    ecart DECIMAL(15,2) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    justificatif_path VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    validated_by INT DEFAULT NULL,
    statut ENUM('en_attente','validee','annulee') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agenceverse_id) REFERENCES agence(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_id) REFERENCES bank(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    INDEX idx_date (dateversement),
    INDEX idx_agence (agenceverse_id),
    INDEX idx_bank (bank_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proprietaire (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomProprio VARCHAR(100) NOT NULL,
    codeproprio VARCHAR(20) NOT NULL UNIQUE,
    groupe VARCHAR(100) DEFAULT NULL,
    statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recette_proprio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recette_id INT NOT NULL,
    proprietaire_id INT NOT NULL,
    date1 DATE DEFAULT NULL,
    date2 DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recette_id) REFERENCES recette(id) ON DELETE CASCADE,
    FOREIGN KEY (proprietaire_id) REFERENCES proprietaire(id) ON DELETE CASCADE,
    INDEX idx_recette (recette_id),
    INDEX idx_proprietaire (proprietaire_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cle_repartition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proprietaire_id INT NOT NULL,
    part1 DECIMAL(5,2) NOT NULL COMMENT 'Pourcentage partie 1',
    part2 DECIMAL(5,2) NOT NULL COMMENT 'Pourcentage partie 2',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proprietaire_id) REFERENCES proprietaire(id) ON DELETE CASCADE,
    INDEX idx_proprietaire (proprietaire_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS justificatifs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    type_mime VARCHAR(100) DEFAULT NULL,
    taille INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    details JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    lien VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sauvegardes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(255) NOT NULL,
    taille INT DEFAULT NULL,
    type ENUM('auto','manuel') NOT NULL DEFAULT 'manuel',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapprochement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    agence_id INT DEFAULT NULL,
    total_recettes DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_versements DECIMAL(15,2) NOT NULL DEFAULT 0,
    ecart DECIMAL(15,2) NOT NULL DEFAULT 0,
    statut ENUM('en_cours','valide','ecart_detecte') NOT NULL DEFAULT 'en_cours',
    validated_by INT DEFAULT NULL,
    observations TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agence_id) REFERENCES agence(id) ON DELETE SET NULL,
    FOREIGN KEY (validated_by) REFERENCES users(id),
    INDEX idx_dates (date_debut, date_fin),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donnees par defaut
INSERT IGNORE INTO agence (codeagence, nomagence) VALUES ('DAN-001', 'DANAY EXPRESS - Siege');

INSERT IGNORE INTO bank (codeBank, nombank) VALUES ('BICIGUI', 'BICIGUI');
INSERT IGNORE INTO bank (codeBank, nombank) VALUES ('ECOBANK', 'Ecobank Guinee');
INSERT IGNORE INTO bank (codeBank, nombank) VALUES ('SGBG', 'Societe Generale de Banque en Guinee');
INSERT IGNORE INTO bank (codeBank, nombank) VALUES ('UBG', 'Union de Banques Guineennes');

INSERT IGNORE INTO categories (code, nom, type) VALUES ('transport_marchandises', 'Transport de marchandises', 'recette');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('location_vehicule', 'Location de vehicule', 'recette');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('commission', 'Commission', 'recette');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('vente', 'Vente', 'recette');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('autre_recette', 'Autre recette', 'recette');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('carburant', 'Carburant', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('maintenance', 'Maintenance & Reparations', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('salaires', 'Salaires & Charges', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('assurance', 'Assurance', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('frais_douane', 'Frais de douane', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('loyer', 'Loyer', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('fournitures', 'Fournitures de bureau', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('telecommunications', 'Telecommunications', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('frais_route', 'Frais de route & Peages', 'depense');
INSERT IGNORE INTO categories (code, nom, type) VALUES ('autre_depense', 'Autre depense', 'depense');

INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('transport', 'Transport', 'les_deux');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('location', 'Location', 'recette');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('achat_carburant', 'Achat carburant', 'depense');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('reparation', 'Reparation', 'depense');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('paiement_salaire', 'Paiement salaire', 'depense');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('versement_banque', 'Versement en banque', 'les_deux');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('retrait', 'Retrait', 'depense');
INSERT IGNORE INTO types_operations (code, nom, type) VALUES ('encaissement', 'Encaissement', 'recette');

INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('app_name', 'DANAY EXPRESS SARL', 'Nom de l entreprise');
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('app_currency', 'XOF', 'Devise par defaut');
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('app_email', 'contact@danayexpress.com', 'Email de contact');
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('app_telephone', '+224 XXX XXX XXX', 'Telephone');
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('backup_auto', '0', 'Sauvegarde automatique (0=non, 1=oui)');
INSERT IGNORE INTO parametres (cle, valeur, description) VALUES ('backup_frequency', 'daily', 'Frequence sauvegarde (daily/weekly/monthly)');