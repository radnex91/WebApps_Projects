-- ============================================
-- StockPro - Migration Sécurité & Performance
-- Exécuter cette migration après la version initiale
-- ============================================

USE stockpro;

-- ============================================
-- 1. TABLE RATE LIMITING (login_attempts)
-- ============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_email (ip_address, email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TABLE AUDIT LOG (pour traçabilité)
-- ============================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(50) NOT NULL,
    entity VARCHAR(50) NOT NULL,
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_entity (entity, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. INDEX DE PERFORMANCE
-- ============================================

-- Produits
CREATE INDEX IF NOT EXISTS idx_produits_actif_cat ON produits(actif, categorie_id);
CREATE INDEX IF NOT EXISTS idx_produits_ref ON produits(reference);
CREATE INDEX IF NOT EXISTS idx_produits_nom ON produits(nom);

-- Mouvements
CREATE INDEX IF NOT EXISTS idx_mouvements_produit ON mouvements(produit_id, created_at);
CREATE INDEX IF NOT EXISTS idx_mouvements_type ON mouvements(type, created_at);
CREATE INDEX IF NOT EXISTS idx_mouvements_utilisateur ON mouvements(utilisateur_id);

-- Utilisateurs
CREATE INDEX IF NOT EXISTS idx_utilisateurs_email ON utilisateurs(email);
CREATE INDEX IF NOT EXISTS idx_utilisateurs_actif ON utilisateurs(actif);

-- Alertes
CREATE INDEX IF NOT EXISTS idx_alertes_lue_produit ON alertes(lue, produit_id);
CREATE INDEX IF NOT EXISTS idx_alertes_type ON alertes(type);

-- Catégories
CREATE INDEX IF NOT EXISTS idx_categories_nom ON categories(nom);

-- Fournisseurs
CREATE INDEX IF NOT EXISTS idx_fournisseurs_actif ON fournisseurs(actif);

-- ============================================
-- 4. MIGRATION VERS MULTI-DÉPÔTS (Phase 2)
-- ============================================

-- Table des dépôts
CREATE TABLE IF NOT EXISTS depots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(30),
    email VARCHAR(100),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock par dépôt (pivot)
CREATE TABLE IF NOT EXISTS stocks_depot (
    produit_id INT NOT NULL,
    depot_id INT NOT NULL,
    quantite INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (produit_id, depot_id),
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (depot_id) REFERENCES depots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transferts entre dépôts
CREATE TABLE IF NOT EXISTS transferts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    depot_source INT NOT NULL,
    depot_dest INT NOT NULL,
    quantite INT NOT NULL,
    utilisateur_id INT NOT NULL,
    motif TEXT,
    statut ENUM('en_attente','valide','annule') DEFAULT 'valide',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (depot_source) REFERENCES depots(id),
    FOREIGN KEY (depot_dest) REFERENCES depots(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_statut (statut),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. MIGRATION DATES D'EXPIRATION (Phase 2)
-- ============================================

ALTER TABLE produits
ADD COLUMN IF NOT EXISTS date_expiration DATE NULL AFTER quantite_min,
ADD COLUMN IF NOT EXISTS code_barres VARCHAR(50) UNIQUE NULL AFTER reference;

CREATE INDEX IF NOT EXISTS idx_produits_expiration ON produits(date_expiration);
CREATE INDEX IF NOT EXISTS idx_produits_code_barres ON produits(code_barres);

-- Alertes avec date d'expiration
ALTER TABLE alertes
ADD COLUMN IF NOT EXISTS date_expiration DATE NULL;

-- ============================================
-- 6. TABLE CLIENTS (Phase 5 - Ventes)
-- ============================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    contact VARCHAR(100),
    telephone VARCHAR(30),
    email VARCHAR(100),
    adresse TEXT,
    societe VARCHAR(150),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actif (actif),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TABLE VENTES (Phase 5 - Facturation)
-- ============================================
CREATE TABLE IF NOT EXISTS ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) UNIQUE,
    client_id INT,
    utilisateur_id INT NOT NULL,
    depot_id INT,
    date_vente DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_ht DECIMAL(15,2) DEFAULT 0,
    total_tva DECIMAL(15,2) DEFAULT 0,
    total_ttc DECIMAL(15,2) DEFAULT 0,
    remise DECIMAL(5,2) DEFAULT 0,
    statut ENUM('brouillon','validée','payée','annulée') DEFAULT 'brouillon',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (depot_id) REFERENCES depots(id) ON DELETE SET NULL,
    INDEX idx_date (date_vente),
    INDEX idx_statut (statut),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ligne_vente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(15,2) NOT NULL,
    remise DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    INDEX idx_vente (vente_id),
    INDEX idx_produit (produit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. DONNÉES PAR DÉFAUT
-- ============================================

-- Dépôt principal par défaut (si aucun dépôt n'existe)
INSERT INTO depots (nom, adresse)
SELECT 'Dépôt Principal', 'Siège de l\'entreprise'
WHERE NOT EXISTS (SELECT 1 FROM depots);

-- ============================================
-- NOTES D'INSTALLATION
-- ============================================
-- 1. Sauvegarder la base avant d'exécuter
-- 2. Exécuter ce fichier dans phpMyAdmin ou MySQL CLI
-- 3. Vérifier qu'aucune erreur ne s'est produite
-- 4. Les nouvelles fonctionnalités seront disponibles automatiquement
