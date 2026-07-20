-- ============================================================
-- PharmaCare â€” Base de donnÃ©es complÃ¨te
-- Compatible MySQL 5.7+ / MariaDB 10+
-- Import unique : mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- TABLES DE BASE
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

-- â”€â”€ RÃ´les â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(60)  NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    est_systeme TINYINT(1)   DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ Permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(80)  NOT NULL UNIQUE,
    libelle     VARCHAR(150) NOT NULL,
    module      VARCHAR(60)  NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ RÃ´le â†” Permission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- â”€â”€ Utilisateurs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE utilisateurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    login       VARCHAR(60) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role_id     INT NOT NULL DEFAULT 3,
    actif       TINYINT(1) DEFAULT 1,
    derniere_connexion DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- â”€â”€ CatÃ©gories â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE categories (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nom     VARCHAR(100) NOT NULL UNIQUE,
    couleur VARCHAR(7) DEFAULT '#00c9a7'
);

-- â”€â”€ Fournisseurs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE fournisseurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    contact     VARCHAR(100),
    telephone   VARCHAR(20),
    email       VARCHAR(150),
    adresse     TEXT,
    ville       VARCHAR(100),
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ Clients â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(20) DEFAULT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ RÃ¨glements des dettes clients â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE reglements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    vente_id        INT DEFAULT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode_paiement   ENUM('espÃ¨ces','carte','chÃ¨que','mobile') NOT NULL DEFAULT 'espÃ¨ces',
    note            VARCHAR(255) DEFAULT NULL,
    date_reglement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id)
);

-- â”€â”€ ParamÃ¨tres globaux â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE parametres (
    cle     VARCHAR(60) PRIMARY KEY,
    valeur  TEXT NOT NULL,
    label   VARCHAR(120),
    groupe  VARCHAR(60) DEFAULT 'gÃ©nÃ©ral'
);

-- â”€â”€ MÃ©dicaments / Produits â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE produits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(200) NOT NULL,
    reference       VARCHAR(50) UNIQUE,
    categorie_id    INT,
    fournisseur_id  INT,
    description     TEXT,
    stock           INT DEFAULT 0,
    seuil_alerte    INT DEFAULT 10,
    stock_magasin   INT DEFAULT 0,
    seuil_magasin   INT DEFAULT 20,
    prix_achat      DECIMAL(10,2) DEFAULT 0,
    prix_vente      DECIMAL(10,2) DEFAULT 0,
    tva             DECIMAL(5,2) DEFAULT 9.00,
    date_expiration DATE,
    actif           TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL
);

-- â”€â”€ Ventes (entÃªte) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE ventes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) UNIQUE NOT NULL,
    client_nom      VARCHAR(150),
    client_telephone VARCHAR(20),
    client_id       INT NULL,
    caissier_id     INT,
    sous_total      DECIMAL(10,2) DEFAULT 0,
    tva_total       DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) DEFAULT 0,
    mode_paiement   ENUM('espÃ¨ces','carte','chÃ¨que','assurance','crÃ©dit') DEFAULT 'espÃ¨ces',
    statut_paiement ENUM('payÃ©','en_attente','partiel') DEFAULT 'payÃ©',
    montant_recu    DECIMAL(10,2) DEFAULT 0,
    monnaie         DECIMAL(10,2) DEFAULT 0,
    note            TEXT,
    remise_pct      DECIMAL(5,2) DEFAULT 0,
    remise_montant  DECIMAL(10,2) DEFAULT 0,
    autorise_par    INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (autorise_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- â”€â”€ Codes d'autorisation de remise (Ã  usage unique) â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE codes_remise (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(10) UNIQUE NOT NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    remise_pct      DECIMAL(5,2) NOT NULL DEFAULT 0,
    used            TINYINT(1) DEFAULT 0,
    used_at         DATETIME NULL,
    used_vente_id   INT NULL,
    used_remise_pct DECIMAL(5,2) NULL,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
    FOREIGN KEY (used_vente_id) REFERENCES ventes(id) ON DELETE SET NULL
);

-- â”€â”€ Approbateurs de remise (liste gÃ©rÃ©e par l'admin) â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE remise_approbateurs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    actif          TINYINT(1) DEFAULT 1,
    added_by       INT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_utilisateur (utilisateur_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- â”€â”€ Compteurs de numÃ©rotation (sÃ©quence atomique sans race) â”€â”€
CREATE TABLE compteurs_ref (
    prefix   VARCHAR(8)  NOT NULL,
    annee    SMALLINT     NOT NULL,
    compteur INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (prefix, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- â”€â”€ Lignes de vente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE vente_lignes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    vente_id    INT NOT NULL,
    produit_id  INT,
    produit_nom VARCHAR(200),
    quantite    INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    tva         DECIMAL(5,2) DEFAULT 9.00,
    total_ligne DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL
);

-- â”€â”€ Retours caisse (vente â†’ stock) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE retours_vente (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    reference         VARCHAR(20) UNIQUE NOT NULL,        -- RET-2026-0001
    vente_id          INT NOT NULL,
    utilisateur_id    INT,
    date_retour       DATE NOT NULL,
    montant_ht        DECIMAL(10,2) DEFAULT 0,
    montant_tva       DECIMAL(10,2) DEFAULT 0,
    montant_total     DECIMAL(10,2) DEFAULT 0,
    cout_achat_total  DECIMAL(10,2) DEFAULT 0,            -- inventaire intermittent (sortie stock au coÃ»t)
    mode_remboursement ENUM('espÃ¨ces','carte','chÃ¨que','assurance','crÃ©dit') DEFAULT 'espÃ¨ces',
    note              VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vente_id) REFERENCES ventes(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- â”€â”€ Lignes de retour â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE retour_vente_lignes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    retour_id       INT NOT NULL,
    vente_ligne_id  INT,
    produit_id      INT,
    produit_nom     VARCHAR(200),
    quantite        INT NOT NULL,                          -- quantitÃ© retournÃ©e (â‰¤ reste retournable)
    prix_unitaire   DECIMAL(10,2) NOT NULL,
    tva             DECIMAL(5,2) DEFAULT 0,
    total_ligne     DECIMAL(10,2) NOT NULL,
    cout_achat      DECIMAL(10,2) DEFAULT 0,               -- qte Ã— prix_achat (stock au coÃ»t)
    FOREIGN KEY (retour_id) REFERENCES retours_vente(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL
);

-- â”€â”€ Commandes fournisseurs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE commandes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) UNIQUE NOT NULL,
    fournisseur_id  INT,
    utilisateur_id  INT,
    statut          ENUM('en_attente','en_cours','livrÃ©e','annulÃ©e') DEFAULT 'en_attente',
    date_commande   DATE,
    date_livraison  DATE,
    note            TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- â”€â”€ Lignes de commande â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE commande_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    commande_id   INT NOT NULL,
    produit_id    INT,
    designation   VARCHAR(200) NOT NULL,
    quantite      INT DEFAULT 1,
    prix_unitaire DECIMAL(10,2) DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE SET NULL
);

-- â”€â”€ Mouvements de stock â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE mouvements_stock (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    produit_id  INT,
    type        ENUM('entrÃ©e','sortie','ajustement') NOT NULL,
    quantite    INT NOT NULL,
    motif       VARCHAR(255),
    utilisateur_id INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- â”€â”€ Magasin (dÃ©pÃ´t central) : Fournisseur â†’ Magasin â†’ Pharmacie â”€
CREATE TABLE mouvements_magasin (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    produit_id    INT,
    type          ENUM('entrÃ©e','sortie','ajustement') NOT NULL,
    quantite      INT NOT NULL,
    motif         VARCHAR(255),
    utilisateur_id INT,
    transfert_id  INT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id)    REFERENCES produits(id)       ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

CREATE TABLE transferts_magasin (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reference     VARCHAR(20) UNIQUE NOT NULL,
    utilisateur_id INT,
    note          TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

CREATE TABLE transfert_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    transfert_id  INT NOT NULL,
    produit_id    INT,
    produit_nom   VARCHAR(200),
    quantite      INT NOT NULL,
    FOREIGN KEY (transfert_id) REFERENCES transferts_magasin(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)   REFERENCES produits(id)          ON DELETE SET NULL
);

-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- MODULE CAISSE
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

-- â”€â”€ Postes de caisse â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE caisses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    actif      TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ Sessions de caisse (ouverture â†’ fermeture) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE sessions_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    caissier_id     INT NOT NULL,
    fond_initial    DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_ouverture  DATETIME NOT NULL,
    date_fermeture  DATETIME NULL,
    solde_attendu   DECIMAL(10,2) NULL,
    solde_reel      DECIMAL(10,2) NULL,
    ecart           DECIMAL(10,2) NULL,
    statut          ENUM('ouverte','fermÃ©e') NOT NULL DEFAULT 'ouverte',
    FOREIGN KEY (caisse_id)   REFERENCES caisses(id),
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)
);

-- â”€â”€ Mouvements de caisse (entrÃ©es / sorties) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NOT NULL,
    type            ENUM('entrÃ©e','sortie') NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    motif           VARCHAR(255) NOT NULL,
    moyen           ENUM('espÃ¨ces','carte','chÃ¨que','assurance','crÃ©dit') DEFAULT 'espÃ¨ces',
    reference_vente VARCHAR(20) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions_caisse(id)
);

-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- MODULE COMPTABILITÃ‰ (OHADA â€” CEMAC)
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

-- â”€â”€ Plan comptable OHADA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE plan_comptable (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    compte      VARCHAR(15) NOT NULL UNIQUE,
    intitule    VARCHAR(200) NOT NULL,
    classe      TINYINT(1) NOT NULL,
    nature      ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    compte_parent INT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_parent) REFERENCES plan_comptable(id)
);

-- â”€â”€ Exercices comptables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE exercices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(9) NOT NULL UNIQUE,
    libelle     VARCHAR(100) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    cloture     TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ Ã‰critures comptables (entÃªte) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE ecritures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(30) NOT NULL UNIQUE,
    libelle         VARCHAR(255) NOT NULL,
    date_ecriture   DATE NOT NULL,
    exercice_id     INT,
    utilisateur_id  INT,
    source          VARCHAR(30) DEFAULT 'manuel',
    source_ref      VARCHAR(30) NULL,
    verrouillee     TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- â”€â”€ Lignes d'Ã©criture (double partie) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE ecriture_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ecriture_id   INT NOT NULL,
    compte_id     INT NOT NULL,
    debit         DECIMAL(12,2) DEFAULT 0,
    credit        DECIMAL(12,2) DEFAULT 0,
    libelle_ligne VARCHAR(255),
    FOREIGN KEY (ecriture_id) REFERENCES ecritures(id) ON DELETE CASCADE,
    FOREIGN KEY (compte_id)   REFERENCES plan_comptable(id)
);

-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- MODULE MARKETING (RadnexMarketer)
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

-- â”€â”€ Campagnes promotionnelles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE campagnes_promo (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(200) NOT NULL,
    description TEXT,
    type        ENUM('pourcentage','montant_fixe') NOT NULL DEFAULT 'pourcentage',
    valeur      DECIMAL(10,2) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- â”€â”€ Produits liÃ©s Ã  une campagne promo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE promo_produits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    produit_id  INT NOT NULL,
    FOREIGN KEY (campagne_id) REFERENCES campagnes_promo(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE CASCADE
);

-- â”€â”€ Points fidÃ©litÃ© clients â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE fidelite_points (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    points      INT NOT NULL,
    type        ENUM('gagnÃ©','utilisÃ©') NOT NULL DEFAULT 'gagnÃ©',
    reference   VARCHAR(100),
    note        VARCHAR(255),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- DONNÃ‰ES DE RÃ‰FÃ‰RENCE
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

-- â”€â”€ RÃ´les systÃ¨me â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO roles (id, code, libelle, est_systeme) VALUES
(1, 'admin',      'Administrateur', 1),
(2, 'pharmacien', 'Pharmacien',     1),
(3, 'caissier',   'Caissier',       1);

-- â”€â”€ Permissions (tous modules) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO permissions (code, libelle, module) VALUES
-- Dashboard
('dashboard.voir',       'Voir le tableau de bord',       'dashboard'),
-- Vente
('vente.creer',          'CrÃ©er des ventes (Point de Vente)', 'vente'),
('remise.approuver',     'Approuver une remise (gÃ©nÃ©rer un code)', 'vente'),
('remise.approbateurs.gerer','GÃ©rer la liste des approbateurs de remise','remise'),
-- Stock
('stock.voir',           'Voir le stock',                 'stock'),
('stock.ajuster',        'Ajuster le stock',              'stock'),
-- Produits
('produits.voir',        'Voir les mÃ©dicaments',          'produits'),
('produits.ajouter',     'Ajouter des mÃ©dicaments',       'produits'),
('produits.modifier',    'Modifier des mÃ©dicaments',      'produits'),
('produits.archiver',    'Archiver des mÃ©dicaments',      'produits'),
-- Fournisseurs
('fournisseurs.voir',    'Voir les fournisseurs',         'fournisseurs'),
('fournisseurs.ajouter', 'Ajouter des fournisseurs',      'fournisseurs'),
('fournisseurs.modifier','Modifier des fournisseurs',     'fournisseurs'),
('fournisseurs.supprimer','Supprimer des fournisseurs',   'fournisseurs'),
-- Commandes
('commandes.voir',       'Voir les commandes',            'commandes'),
('commandes.creer',      'CrÃ©er des commandes',           'commandes'),
('commandes.modifier',   'Modifier des commandes',        'commandes'),
-- Historique ventes
('ventes_hist.voir',     'Voir l''historique des ventes', 'ventes_hist'),
-- Rapports
('rapports.voir',        'Voir les rapports',             'rapports'),
('rapports_caissier.voir','Voir ses rapports personnels',  'rapports'),
-- Utilisateurs
('utilisateurs.voir',    'Voir les utilisateurs',         'utilisateurs'),
('utilisateurs.gerer',   'GÃ©rer les utilisateurs',        'utilisateurs'),
-- CatÃ©gories
('categories.voir',      'Voir les catÃ©gories',           'categories'),
('categories.gerer',     'GÃ©rer les catÃ©gories',          'categories'),
-- ParamÃ¨tres
('parametres.voir',      'Voir les paramÃ¨tres',           'parametres'),
('parametres.gerer',     'GÃ©rer les paramÃ¨tres',          'parametres'),
-- RÃ´les
('roles.voir',           'Voir les rÃ´les & permissions',  'roles'),
('roles.gerer',          'GÃ©rer les rÃ´les & permissions', 'roles'),
-- Clients
('clients.voir',         'Voir la liste des clients',     'clients'),
('clients.ajouter',      'CrÃ©er un client',               'clients'),
('clients.modifier',     'Modifier une fiche client',     'clients'),
('clients.supprimer',    'DÃ©sactiver un client',          'clients'),
('clients.paiements',    'Enregistrer des rÃ¨glements',    'clients'),
-- Caisse
('caisse.voir',          'Voir le dashboard des caisses', 'caisse'),
('caisse.gerer',         'GÃ©rer les caisses',             'caisse'),
('caisse.ouvrir',        'Ouvrir une session de caisse',  'caisse'),
-- ComptabilitÃ©
('comptabilite.voir',    'AccÃ©der Ã  la comptabilitÃ©',     'comptabilite'),
('comptabilite.saisie',  'Saisir des Ã©critures manuelles','comptabilite'),
('comptabilite.plan',    'GÃ©rer le plan comptable',       'comptabilite'),
-- Marketing
('marketing.voir',       'Voir le marketing',             'marketing'),
('marketing.promos',     'GÃ©rer les promotions',          'marketing'),
('marketing.fidelite',   'GÃ©rer la fidÃ©litÃ© clients',     'marketing'),
-- Magasin (dÃ©pÃ´t central)
('magasin.voir',         'Voir le stock magasin',         'magasin'),
('magasin.gerer',        'GÃ©rer le magasin (rÃ©ceptions & transferts)', 'magasin'),
-- Retours caisse
('retours.gerer',        'GÃ©rer les retours de ventes',   'retours');

-- â”€â”€ Permissions par rÃ´le â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

-- Admin : TOUTES les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Pharmacien : 17 permissions + clients.voir + magasin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN (
    'dashboard.voir', 'vente.creer', 'stock.voir', 'stock.ajuster',
    'produits.voir', 'produits.ajouter', 'produits.modifier', 'produits.archiver',
    'fournisseurs.voir',
    'commandes.voir', 'commandes.creer', 'commandes.modifier',
    'ventes_hist.voir', 'rapports.voir',
    'clients.voir',
    'marketing.voir', 'marketing.promos',
    'magasin.voir', 'magasin.gerer',
    'retours.gerer',
    'remise.approuver'
);

-- Caissier : 9 permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN (
    'dashboard.voir', 'vente.creer', 'stock.voir',
    'ventes_hist.voir', 'rapports.voir',
    'rapports_caissier.voir',
    'clients.voir', 'clients.ajouter', 'clients.paiements',
    'retours.gerer'
);

-- Manager : gestion de la liste des approbateurs de remise
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE code IN ('remise.approbateurs.gerer');

-- â”€â”€ Utilisateurs (mot de passe : password) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role_id) VALUES
('Administrateur', 'SystÃ¨me', 'admin@pharmacare.dz', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Maaref', 'Sabrina', 's.maaref@pharmacare.dz', 'pharmacien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Belkacemi', 'Yasmine', 'y.belkacemi@pharmacare.dz', 'caissier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

-- â”€â”€ Approbateurs de remise initiaux (dÃ©tenteurs de remise.approuver) â”€â”€
INSERT INTO remise_approbateurs (utilisateur_id, added_by)
SELECT u.id, 1 FROM utilisateurs u
JOIN role_permissions rp ON rp.role_id = u.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE p.code = 'remise.approuver' AND u.actif = 1;

-- â”€â”€ CatÃ©gories â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO categories (nom, couleur) VALUES
('Antalgiques', '#00c9a7'),
('Antibiotiques', '#4895ef'),
('Anti-inflammatoires', '#f0b429'),
('Vitamines & ComplÃ©ments', '#9b59b6'),
('Cardiologie', '#e74c3c'),
('DiabÃ©tologie', '#e67e22'),
('Respiratoire', '#1abc9c'),
('Gastro-entÃ©rologie', '#3498db'),
('Allergologie', '#e91e63'),
('Dermatologie', '#795548');

-- â”€â”€ Fournisseurs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO fournisseurs (nom, contact, telephone, email, ville) VALUES
('PharmaDist AlgÃ©rie', 'Mohamed Amine Khelif', '0555 12 34 56', 'contact@pharmadist.dz', 'Alger'),
('MediSupply', 'Fatima Zahra Benali', '0666 78 90 12', 'info@medisupply.dz', 'Oran'),
('SantÃ©Dist', 'Karim Boudiaf', '0777 34 56 78', 'sante@santedist.dz', 'Constantine'),
('PharmaGros', 'Nadia Rouibet', '0551 22 33 44', 'contact@pharmagros.dz', 'Blida');

-- â”€â”€ ParamÃ¨tres â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO parametres (cle, valeur, label, groupe) VALUES
('devise',        'XAF',                  'Devise',               'gÃ©nÃ©ral'),
('devise_symbole','FCFA',                 'Symbole devise',       'gÃ©nÃ©ral'),
('devise_pos',    'after',                'Position symbole',     'gÃ©nÃ©ral'),
('tva',           '19.25',                'Taux TVA (%)',         'gÃ©nÃ©ral'),
('app_nom',       'PharmaCare',           'Nom de la pharmacie',  'gÃ©nÃ©ral'),
('theme',         'dark-cyan',            'ThÃ¨me couleur',        'apparence'),
('police',        'Manrope',              'Police principale',    'apparence'),
('police_titre',  'Manrope',              'Police titres',        'apparence'),
('caisse_fermeture_mode', 'manuel',       'Mode fermeture caisse','caisse'),
('caisse_heure_fermeture','22:00',        'Heure fermeture auto', 'caisse'),
('delai_inactivite_min',  '15',           'DÃ©connexion auto (min)','sÃ©curitÃ©'),
('remise_code_ttl_min',  '15',           'ValiditÃ© code remise (min)','ventes'),
('remise_max_pct',       '100',          'Remise max (%)',       'ventes');

-- â”€â”€ Postes de caisse â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO caisses (id, nom) VALUES
(1, 'Caisse 1'),
(2, 'Caisse 2'),
(3, 'Caisse 3');

-- â”€â”€ Plan comptable OHADA (Pharmacie CEMAC) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

-- Classe 1 : Capitaux
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('1',     'Comptes de capitaux',       1, 'credit'),
('101',   'Capital social',            1, 'credit'),
('1011',  'Capital individuel',        1, 'credit'),
('106',   'RÃ©serves',                  1, 'credit'),
('1061',  'RÃ©serves lÃ©gales',          1, 'credit'),
('1063',  'RÃ©serves libres',           1, 'credit'),
('12',    'RÃ©sultat de l''exercice',   1, 'credit'),
('129',   'RÃ©sultat en instance d''affectation', 1, 'credit');

-- Classe 2 : Immobilisations
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('2',     'Comptes d''immobilisations', 2, 'debit'),
('218',   'Autres immobilisations',    2, 'debit'),
('2183',  'MatÃ©riel et outillage',     2, 'debit'),
('2184',  'Mobilier de bureau',        2, 'debit'),
('2185',  'MatÃ©riel informatique',     2, 'debit'),
('281',   'Amortissements',            2, 'credit');

-- Classe 3 : Stocks
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('3',     'Comptes de stocks',         3, 'debit'),
('311',   'Marchandises en stock',     3, 'debit'),
('3111',  'MÃ©dicaments en stock',      3, 'debit'),
('3112',  'Produits parapharmaceutiques', 3, 'debit');

-- Classe 4 : Tiers
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('4',     'Comptes de tiers',          4, 'debit'),
('401',   'Fournisseurs',              4, 'credit'),
('4011',  'Fournisseurs achats',       4, 'credit'),
('411',   'Clients',                   4, 'debit'),
('4111',  'Clients - Assurance',       4, 'debit'),
('421',   'Personnel rÃ©munÃ©rations',   4, 'credit'),
('431',   'SÃ©curitÃ© sociale',          4, 'credit'),
('441',   'Ã‰tat - TVA collectÃ©e',      4, 'credit'),
('4411',  'TVA collectÃ©e 19.25%',      4, 'credit'),
('445',   'Ã‰tat - TVA rÃ©cupÃ©rable',    4, 'debit'),
('4451',  'TVA rÃ©cupÃ©rable 19.25%',    4, 'debit'),
('471',   'Compte d''attente',         4, 'credit');

-- Classe 5 : TrÃ©sorerie
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('5',     'Comptes de trÃ©sorerie',     5, 'debit'),
('511',   'ChÃ¨ques Ã  encaisser',       5, 'debit'),
('512',   'Banque',                    5, 'debit'),
('571',   'Caisse',                    5, 'debit'),
('5711',  'Caisse principale',         5, 'debit'),
('581',   'Virements internes',        5, 'debit');

-- Classe 6 : Charges
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('6',     'Comptes de charges',        6, 'debit'),
('601',   'Achats de marchandises',    6, 'debit'),
('6011',  'Achats de mÃ©dicaments',     6, 'debit'),
('603',   'Variation des stocks',      6, 'debit'),
('6031',  'Variation stocks marchandises', 6, 'debit'),
('611',   'Transports sur achats',     6, 'debit'),
('623',   'PublicitÃ© et publications', 6, 'debit'),
('625',   'DÃ©placements',              6, 'debit'),
('626',   'Frais postaux',             6, 'debit'),
('627',   'Services bancaires',        6, 'debit'),
('641',   'Salaires',                  6, 'debit'),
('645',   'Charges sociales',          6, 'debit'),
('658',   'Charges diverses',          6, 'debit'),
('681',   'Dotations aux amortissements', 6, 'debit'),
('695',   'ImpÃ´ts et taxes',           6, 'debit');

-- Classe 7 : Produits
INSERT INTO plan_comptable (compte, intitule, classe, nature) VALUES
('7',     'Comptes de produits',       7, 'credit'),
('701',   'Ventes de marchandises',    7, 'credit'),
('7011',  'Ventes de mÃ©dicaments',     7, 'credit'),
('7119',  'Rabais, remises et ristournes accordÃ©s', 7, 'debit'),
('708',   'Produits des activitÃ©s annexes', 7, 'credit'),
('751',   'Produits financiers',       7, 'credit'),
('758',   'Produits divers',           7, 'credit'),
('771',   'Produits exceptionnels',    7, 'credit');

-- â”€â”€ Exercice comptable 2026 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO exercices (code, libelle, date_debut, date_fin) VALUES
('2026', 'Exercice 2026', '2026-01-01', '2026-12-31');

-- â”€â”€ Produits â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO produits (nom, reference, categorie_id, fournisseur_id, stock, seuil_alerte, prix_achat, prix_vente, date_expiration) VALUES
('ParacÃ©tamol 1g', 'MED-001', 1, 1, 145, 20, 45.00, 75.00, '2026-08-31'),
('Amoxicilline 500mg', 'MED-002', 2, 2, 7, 15, 120.00, 195.00, '2025-12-31'),
('IbuprofÃ¨ne 400mg', 'MED-003', 3, 1, 89, 20, 55.00, 90.00, '2027-03-31'),
('Doliprane 1000mg', 'MED-004', 1, 3, 3, 25, 50.00, 80.00, '2026-06-30'),
('Ventoline 100Âµg', 'MED-005', 7, 2, 32, 10, 280.00, 420.00, '2026-11-30'),
('Augmentin 1g', 'MED-006', 2, 1, 41, 10, 350.00, 520.00, '2026-09-30'),
('Metformine 500mg', 'MED-007', 6, 3, 6, 15, 85.00, 130.00, '2026-04-30'),
('Atorvastatine 20mg', 'MED-008', 5, 2, 58, 10, 190.00, 290.00, '2027-01-31'),
('Vitamine C 1000mg', 'MED-009', 4, 1, 210, 30, 30.00, 55.00, '2027-06-30'),
('Smecta sachet x10', 'MED-010', 8, 3, 75, 20, 40.00, 68.00, '2026-12-31'),
('Doliprane 500mg', 'MED-011', 1, 1, 180, 30, 35.00, 60.00, '2026-08-31'),
('Xyzall 5mg', 'MED-012', 9, 2, 44, 10, 210.00, 330.00, '2026-10-31'),
('OmÃ©prazole 20mg', 'MED-013', 8, 4, 95, 15, 70.00, 115.00, '2027-02-28'),
('Aspirine 500mg', 'MED-014', 1, 1, 120, 20, 25.00, 45.00, '2027-04-30'),
('Biseptol 480mg', 'MED-015', 2, 3, 0, 10, 95.00, 150.00, '2026-07-31');

-- â”€â”€ Commandes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO commandes (reference, fournisseur_id, utilisateur_id, statut, date_commande, date_livraison) VALUES
('CMD-2026-001', 1, 1, 'livrÃ©e',    '2026-04-01', '2026-04-04'),
('CMD-2026-002', 2, 2, 'en_cours',  '2026-04-08', NULL),
('CMD-2026-003', 3, 1, 'en_attente','2026-04-10', NULL);

-- â”€â”€ Ventes de dÃ©mo (TVA 19.25%) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO ventes (reference, client_nom, caissier_id, sous_total, tva_total, total, mode_paiement, montant_recu, monnaie, created_at) VALUES
('VNT-2026-001', 'Fatima Bouhel', 3, 383.00, 73.73, 456.73, 'espÃ¨ces',   500.00, 43.27, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('VNT-2026-002', 'Ahmed Kouachi', 3, 625.00, 120.31, 745.31, 'carte',     745.31,  0.00, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('VNT-2026-003', '',              2, 130.00, 25.03,  155.03, 'espÃ¨ces',   200.00, 44.97, DATE_SUB(NOW(), INTERVAL 6 HOUR));

INSERT INTO vente_lignes (vente_id, produit_id, produit_nom, quantite, prix_unitaire, tva, total_ligne) VALUES
(1, 1,  'ParacÃ©tamol 1g',     2, 75.00,  19.25, 150.00),
(1, 9,  'Vitamine C 1000mg',  3, 55.00,  19.25, 165.00),
(1, 10, 'Smecta sachet x10',  1, 68.00,  19.25, 68.00),
(2, 6,  'Augmentin 1g',       1, 520.00, 19.25, 520.00),
(2, 11, 'Doliprane 500mg',    1, 60.00,  19.25, 60.00),
(2, 14, 'Aspirine 500mg',     1, 45.00,  19.25, 45.00),
(3, 7,  'Metformine 500mg',   1, 130.00, 19.25, 130.00);



-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
-- TABLE D'AUDIT
-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

CREATE TABLE audit_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT          DEFAULT NULL,
    action         VARCHAR(80)  NOT NULL,
    details        VARCHAR(500) DEFAULT '',
    ip             VARCHAR(45)  DEFAULT '0.0.0.0',
    target_id      INT          DEFAULT NULL,
    reference      VARCHAR(80)  DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_audit_action (action),
    INDEX idx_audit_user   (utilisateur_id),
    INDEX idx_audit_date   (created_at),
    INDEX idx_audit_ref    (reference),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Index de performance (requetes filtres par date + caissier/client) ──
CREATE INDEX idx_ventes_created_at        ON ventes (created_at);
CREATE INDEX idx_ventes_caissier_date     ON ventes (caissier_id, created_at);
CREATE INDEX idx_ventes_client_date       ON ventes (client_id, created_at);
CREATE INDEX idx_ventes_mode_date         ON ventes (mode_paiement, created_at);
CREATE INDEX idx_ventes_statut_date       ON ventes (statut_paiement, created_at);
CREATE INDEX idx_vente_lignes_vente       ON vente_lignes (vente_id);
CREATE INDEX idx_mouvements_stock_prod    ON mouvements_stock (produit_id, created_at);
CREATE INDEX idx_commandes_statut         ON commandes (statut, created_at);
CREATE INDEX idx_ecritures_date           ON ecritures (date_ecriture);
CREATE INDEX idx_sessions_caisse_statut   ON sessions_caisse (statut);
CREATE INDEX idx_retours_vente_date       ON retours_vente (date_retour);