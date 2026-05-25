-- ================================================================
-- TRANSPORT MANAGER — Système de Gestion d'Agence de Transport
-- Base de données complète
-- ================================================================
CREATE DATABASE IF NOT EXISTS transport_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transport_db;

-- ── AGENCES ──────────────────────────────────────────────────
CREATE TABLE agences (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10) NOT NULL UNIQUE,
    nom         VARCHAR(150) NOT NULL,
    ville       VARCHAR(100) NOT NULL,
    region      VARCHAR(100),
    adresse     TEXT,
    telephone   VARCHAR(30),
    email       VARCHAR(150),
    responsable VARCHAR(150),
    logo        VARCHAR(255),
    actif       TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── RÔLES ────────────────────────────────────────────────────
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    description TEXT,
    couleur     VARCHAR(7) DEFAULT '#2563eb',
    niveau      INT DEFAULT 1
);

-- ── UTILISATEURS ─────────────────────────────────────────────
CREATE TABLE utilisateurs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    matricule     VARCHAR(20) UNIQUE,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100),
    email         VARCHAR(150),
    telephone     VARCHAR(20),
    role_id       INT NOT NULL,
    agence_id            INT,
    agence_affectation_id INT,
    avatar_color         VARCHAR(7) DEFAULT '#2563eb',
    actif                TINYINT(1) DEFAULT 1,
    last_login           TIMESTAMP NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id)            REFERENCES roles(id),
    FOREIGN KEY (agence_id)          REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY (agence_affectation_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- ── USER-AGENCES (affectation multi-agences) ──────────────────
CREATE TABLE user_agences (
    user_id      INT NOT NULL,
    agence_id    INT NOT NULL,
    is_principal TINYINT(1) DEFAULT 0,
    PRIMARY KEY (user_id, agence_id),
    FOREIGN KEY (user_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE CASCADE
);

-- ── PERMISSIONS ───────────────────────────────────────────────
CREATE TABLE permissions (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    code   VARCHAR(80) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    nom    VARCHAR(150) NOT NULL
);
CREATE TABLE role_permissions (
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY(role_id, permission_id),
    FOREIGN KEY(role_id)       REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ── VÉHICULES ─────────────────────────────────────────────────
CREATE TABLE vehicules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    immatriculation VARCHAR(20) NOT NULL UNIQUE,
    marque          VARCHAR(80),
    modele          VARCHAR(80),
    type            ENUM('bus','minibus','voiture','camion') DEFAULT 'bus',
    capacite        INT DEFAULT 70,
    agence_id       INT,
    statut          ENUM('actif','panne','maintenance','hors_service') DEFAULT 'actif',
    annee           INT,
    assurance_fin   DATE,
    visite_fin      DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- ── CHAUFFEURS / CONVOYEURS ────────────────────────────────────
CREATE TABLE personnel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    matricule   VARCHAR(20) UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100),
    fonction    ENUM('chauffeur','convoyeur','autre') DEFAULT 'chauffeur',
    telephone   VARCHAR(20),
    permis      VARCHAR(50),
    permis_cat  VARCHAR(10),
    permis_exp  DATE,
    agence_id   INT,
    statut      ENUM('actif','inactif','conge') DEFAULT 'actif',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- ── DESTINATIONS / LIGNES ─────────────────────────────────────
CREATE TABLE destinations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    agence_depart   INT NOT NULL,
    agence_arrivee  INT NOT NULL,
    distance_km     INT,
    duree_minutes   INT,
    actif           TINYINT(1) DEFAULT 1,
    FOREIGN KEY(agence_depart)  REFERENCES agences(id),
    FOREIGN KEY(agence_arrivee) REFERENCES agences(id)
);

-- ── TARIFS ────────────────────────────────────────────────────
CREATE TABLE tarifs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    destination_id  INT NULL,
    itineraire_id   INT NULL,
    escale_depart_id INT NULL,
    escale_arrivee_id INT NULL,
    classe          ENUM('cla','vip','spc') DEFAULT 'cla',
    prix            DECIMAL(10,2) NOT NULL,
    bagages_inclus  DECIMAL(5,2) DEFAULT 0,
    date_debut      DATE DEFAULT (CURRENT_DATE),
    date_fin        DATE NULL,
    actif           TINYINT(1) DEFAULT 1,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(destination_id) REFERENCES destinations(id),
    FOREIGN KEY(itineraire_id) REFERENCES itineraires(id) ON DELETE SET NULL,
    FOREIGN KEY(escale_depart_id) REFERENCES itineraire_escales(id) ON DELETE SET NULL,
    FOREIGN KEY(escale_arrivee_id) REFERENCES itineraire_escales(id) ON DELETE SET NULL,
    FOREIGN KEY(created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── VOYAGES / DÉPARTS ─────────────────────────────────────────
CREATE TABLE itineraires (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    nom             VARCHAR(150) NOT NULL,
    agence_depart   INT NOT NULL,
    agence_arrivee  INT NOT NULL,
    distance_km     INT DEFAULT 0,
    duree_minutes   INT DEFAULT 0,
    actif           TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_depart)  REFERENCES agences(id),
    FOREIGN KEY(agence_arrivee) REFERENCES agences(id)
);

-- ── ESCALES D'ITINÉRAIRE ──────────────────────────────────────
CREATE TABLE itineraire_escales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    itineraire_id   INT NOT NULL,
    agence_id       INT NOT NULL,
    ordre           INT NOT NULL,
    distance_debut  INT DEFAULT 0,
    duree_debut     INT DEFAULT 0,
    FOREIGN KEY(itineraire_id) REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
);

-- ── CORRESPONDANCES ───────────────────────────────────────────
CREATE TABLE correspondances (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    itineraire_depart_id    INT NOT NULL,
    itineraire_arrivee_id   INT NOT NULL,
    agence_correspondance_id INT NOT NULL,
    delai_minutes           INT DEFAULT 30,
    actif                  TINYINT(1) DEFAULT 1,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(itineraire_depart_id)  REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(itineraire_arrivee_id)  REFERENCES itineraires(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_correspondance_id) REFERENCES agences(id)
);

-- ── VOYAGES / DÉPARTS ─────────────────────────────────────────
CREATE TABLE voyages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,
    vehicule_id     INT,
    chauffeur_id    INT,
    convoyeur_id    INT,
    itineraire_id   INT NULL,
    destination_id  INT NOT NULL,
    agence_id       INT NOT NULL,
    date_depart     DATETIME NOT NULL,
    heure_arrivee   DATETIME,
    statut          ENUM('programme','en_cours','arrive','annule','reporte') DEFAULT 'programme',
    classe_voyage   ENUM('cla','vip','spc') DEFAULT 'cla',
    places_dispo    INT DEFAULT 0,
    chef_depiste    VARCHAR(150),
    montant_carburant DECIMAL(10,2) DEFAULT 0,
    montant_peage   DECIMAL(10,2) DEFAULT 0,
    convoyeur_nom   VARCHAR(150),
    observations    TEXT,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicule_id)   REFERENCES vehicules(id)   ON DELETE SET NULL,
    FOREIGN KEY(chauffeur_id)  REFERENCES personnel(id)   ON DELETE SET NULL,
    FOREIGN KEY(convoyeur_id)  REFERENCES personnel(id)   ON DELETE SET NULL,
    FOREIGN KEY(itineraire_id) REFERENCES itineraires(id)  ON DELETE SET NULL,
    FOREIGN KEY(destination_id) REFERENCES destinations(id),
    FOREIGN KEY(agence_id)     REFERENCES agences(id),
    FOREIGN KEY(created_by)    REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── PASSAGERS ────────────────────────────────────────────────
CREATE TABLE passagers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100),
    telephone   VARCHAR(20),
    email       VARCHAR(150),
    cni         VARCHAR(30),
    adresse     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TICKETS DE VOYAGE ─────────────────────────────────────────
CREATE TABLE tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(30) NOT NULL UNIQUE,
    voyage_id       INT NULL,
    bordereau_id    INT NULL,
    passager_id     INT NULL,
    passager_nom    VARCHAR(150) NOT NULL,
    passager_tel    VARCHAR(20),
    passager_cni    VARCHAR(30),
    siege           VARCHAR(10),
    classe          ENUM('cla','vip','spc') DEFAULT 'cla',
    tarif_id        INT,
    montant         DECIMAL(10,2) NOT NULL,
    bagages_kg      DECIMAL(5,2) DEFAULT 0,
    montant_bagages DECIMAL(10,2) DEFAULT 0,
    montant_total   DECIMAL(10,2) NOT NULL,
    statut          ENUM('vendu','annule','utilise','reserve') DEFAULT 'vendu',
    mode_paiement   ENUM('especes','om','momo','carte','cheque') DEFAULT 'especes',
    agence_id       INT NOT NULL,
    agence_depart_id INT NULL,
    agence_arrivee_id INT NULL,
    guichetier_id   INT,
    escale_montee_id INT NULL,
    escale_descente_id INT NULL,
    transit         TINYINT(1) DEFAULT 0,
    transit_destination INT NULL,
    type_passager   ENUM('adulte','enfant') DEFAULT 'adulte',
    somme_percu     DECIMAL(10,2) DEFAULT 0,
    reliquat        DECIMAL(10,2) DEFAULT 0,
    observation     TEXT,
    date_heure      DATETIME DEFAULT NULL,
    date_vente      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_annulation TIMESTAMP NULL,
    motif_annulation TEXT,
    annule_par      INT,
    FOREIGN KEY(voyage_id)   REFERENCES voyages(id),
    FOREIGN KEY(passager_id) REFERENCES passagers(id) ON DELETE SET NULL,
    FOREIGN KEY(tarif_id)    REFERENCES tarifs(id)    ON DELETE SET NULL,
    FOREIGN KEY(agence_id)   REFERENCES agences(id),
    FOREIGN KEY(agence_depart_id) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY(agence_arrivee_id) REFERENCES agences(id) ON DELETE SET NULL,
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(escale_montee_id) REFERENCES itineraire_escales(id) ON DELETE SET NULL,
    FOREIGN KEY(escale_descente_id) REFERENCES itineraire_escales(id) ON DELETE SET NULL,
    FOREIGN KEY(transit_destination) REFERENCES destinations(id) ON DELETE SET NULL,
    FOREIGN KEY(bordereau_id)  REFERENCES bordereaux(id) ON DELETE SET NULL,
    FOREIGN KEY(annule_par)  REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── RÉSERVATIONS ─────────────────────────────────────────────
CREATE TABLE reservations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,
    voyage_id       INT NOT NULL,
    passager_nom    VARCHAR(150) NOT NULL,
    passager_tel    VARCHAR(20),
    passager_cni    VARCHAR(30),
    siege           VARCHAR(10),
    classe          ENUM('cla','vip','spc') DEFAULT 'cla',
    montant         DECIMAL(10,2) NOT NULL,
    acompte         DECIMAL(10,2) DEFAULT 0,
    statut          ENUM('active','confirmee','annulee','expiree') DEFAULT 'active',
    date_expiration DATETIME,
    agence_id       INT NOT NULL,
    guichetier_id   INT,
    ticket_id       INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(voyage_id)    REFERENCES voyages(id),
    FOREIGN KEY(agence_id)    REFERENCES agences(id),
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── BORDEREAUX ────────────────────────────────────────────────
CREATE TABLE bordereaux (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    numero              VARCHAR(20) NOT NULL UNIQUE,
    voyage_id           INT NOT NULL,
    parent_id           INT NULL,
    segment_ordre       INT DEFAULT 1,
    agence_id           INT NOT NULL,
    type                ENUM('chauffeur','comptabilite','transit','direction') DEFAULT 'chauffeur',
    -- Info voyage
    vehicule_immat      VARCHAR(20),
    chauffeur_nom       VARCHAR(150),
    chauffeur_permis    VARCHAR(50),
    convoyeur_nom       VARCHAR(150),
    agence_depart       VARCHAR(100),
    agence_arrivee      VARCHAR(100),
    date_depart         DATETIME,
    -- Financier
    nb_passagers        INT DEFAULT 0,
    recette_brute       DECIMAL(12,2) DEFAULT 0,
    montant_carburant   DECIMAL(10,2) DEFAULT 0,
    montant_peage       DECIMAL(10,2) DEFAULT 0,
    avance_chauffeur    DECIMAL(10,2) DEFAULT 0,
    autres_deductions   DECIMAL(10,2) DEFAULT 0,
    recette_nette       DECIMAL(12,2) DEFAULT 0,
    -- Statut
    statut              ENUM('genere','en_cours','cloture') DEFAULT 'genere',
    imprime             TINYINT(1) DEFAULT 0,
    valide_par          INT NULL,
    date_validation     TIMESTAMP NULL,
    saisi_par           INT,
    date_saisie         TIMESTAMP NULL,
    observations        TEXT,
    created_by          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(voyage_id)  REFERENCES voyages(id),
    FOREIGN KEY(agence_id)  REFERENCES agences(id),
    FOREIGN KEY(valide_par)  REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(saisi_par)  REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(parent_id)  REFERENCES bordereaux(id) ON DELETE SET NULL
);

-- ── LIGNES DE BORDEREAU (détail passagers) ────────────────────
CREATE TABLE bordereau_lignes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    bordereau_id    INT NOT NULL,
    ticket_id       INT,
    passager_nom    VARCHAR(150),
    siege           VARCHAR(10),
    destination     VARCHAR(100),
    montant         DECIMAL(10,2),
    classe          VARCHAR(20),
    FOREIGN KEY(bordereau_id) REFERENCES bordereaux(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_id)    REFERENCES tickets(id) ON DELETE SET NULL
);

-- ── ESCALES DE BORDEREAU ──────────────────────────────────────
CREATE TABLE bordereau_escales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    bordereau_id    INT NOT NULL,
    agence_id       INT NOT NULL,
    ordre           INT NOT NULL,
    statut          ENUM('en_attente','confirme','refuse') DEFAULT 'en_attente',
    confirme_par    INT NULL,
    date_confirmation TIMESTAMP NULL,
    observations    TEXT,
    FOREIGN KEY(bordereau_id) REFERENCES bordereaux(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id)     REFERENCES agences(id),
    FOREIGN KEY(confirme_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── DÉPENSES ──────────────────────────────────────────────────
CREATE TABLE depenses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) UNIQUE,
    agence_id       INT NOT NULL,
    categorie       ENUM('reparation','carburant','salaire','loyer','fourniture','peage','autre') DEFAULT 'autre',
    libelle         VARCHAR(300) NOT NULL,
    montant         DECIMAL(12,2) NOT NULL,
    date_depense    DATE NOT NULL DEFAULT (CURRENT_DATE),
    voyage_id       INT NULL,
    vehicule_id     INT NULL,
    beneficiaire    VARCHAR(150),
    justificatif    VARCHAR(255),
    statut          ENUM('en_attente','approuve','rejete','paye') DEFAULT 'en_attente',
    approuve_par    INT NULL,
    impute_par      INT NOT NULL,
    date_approbation TIMESTAMP NULL,
    rapport_journalier_id INT NULL,
    observations    TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_id)    REFERENCES agences(id),
    FOREIGN KEY(voyage_id)    REFERENCES voyages(id) ON DELETE SET NULL,
    FOREIGN KEY(vehicule_id)  REFERENCES vehicules(id) ON DELETE SET NULL,
    FOREIGN KEY(approuve_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(impute_par)   REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── VERSEMENTS BANCAIRES ──────────────────────────────────────
CREATE TABLE versements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) UNIQUE,
    agence_id       INT NOT NULL,
    type            ENUM('banque','om','momo','autre') DEFAULT 'banque',
    montant         DECIMAL(12,2) NOT NULL,
    date_versement  DATE NOT NULL DEFAULT (CURRENT_DATE),
    reference       VARCHAR(100),
    banque          VARCHAR(100),
    compte          VARCHAR(50),
    statut          ENUM('en_attente','confirme','rejete') DEFAULT 'en_attente',
    confirme_par    INT NULL,
    date_confirmation TIMESTAMP NULL,
    rapport_journalier_id INT NULL,
    observations    TEXT,
    saisi_par       INT NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agence_id)    REFERENCES agences(id),
    FOREIGN KEY(confirme_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(saisi_par)    REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── RAPPORTS JOURNALIERS ──────────────────────────────────────
CREATE TABLE rapports_journaliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,
    agence_id       INT NOT NULL,
    date_rapport    DATE NOT NULL,
    -- Recettes
    nb_tickets      INT DEFAULT 0,
    recette_brute   DECIMAL(12,2) DEFAULT 0,
    recette_guichet DECIMAL(12,2) DEFAULT 0,
    -- Versements
    versement_banque DECIMAL(12,2) DEFAULT 0,
    versement_om    DECIMAL(12,2) DEFAULT 0,
    versement_momo  DECIMAL(12,2) DEFAULT 0,
    -- Dépenses
    total_depenses  DECIMAL(12,2) DEFAULT 0,
    -- Solde
    solde           DECIMAL(12,2) DEFAULT 0,
    -- Statut
    statut          ENUM('brouillon','soumis','valide','cloture') DEFAULT 'brouillon',
    genere_par      INT,
    valide_par      INT NULL,
    date_validation TIMESTAMP NULL,
    observations    TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_agence_date (agence_id, date_rapport),
    FOREIGN KEY(agence_id)  REFERENCES agences(id),
    FOREIGN KEY(genere_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY(valide_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── RAPPORTS GUICHETIER ───────────────────────────────────────
CREATE TABLE rapports_guichetiers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    guichetier_id   INT NOT NULL,
    agence_id       INT NOT NULL,
    date_rapport    DATE NOT NULL,
    nb_tickets      INT DEFAULT 0,
    montant_total   DECIMAL(12,2) DEFAULT 0,
    montant_especes DECIMAL(12,2) DEFAULT 0,
    montant_om      DECIMAL(12,2) DEFAULT 0,
    montant_momo    DECIMAL(12,2) DEFAULT 0,
    nb_annulations  INT DEFAULT 0,
    montant_annule  DECIMAL(12,2) DEFAULT 0,
    statut          ENUM('en_cours','cloture') DEFAULT 'en_cours',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_guich_date (guichetier_id, date_rapport),
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
);

-- ── CAISSES (gestion caisse guichetier) ────────────────────
CREATE TABLE caisses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE,
    guichetier_id           INT NOT NULL,
    agence_id               INT NOT NULL,
    date_ouverture          DATETIME NOT NULL,
    fond_initial            DECIMAL(12,2) NOT NULL DEFAULT 0,
    statut                  ENUM('ouverte','fermee') DEFAULT 'ouverte',
    date_fermeture          DATETIME NULL,
    total_tickets_especes   DECIMAL(12,2) DEFAULT 0,
    total_tickets_om        DECIMAL(12,2) DEFAULT 0,
    total_tickets_momo      DECIMAL(12,2) DEFAULT 0,
    total_tickets_carte     DECIMAL(12,2) DEFAULT 0,
    total_tickets_cheque    DECIMAL(12,2) DEFAULT 0,
    nb_tickets_vendus       INT DEFAULT 0,
    nb_tickets_annules      INT DEFAULT 0,
    montant_annulations     DECIMAL(12,2) DEFAULT 0,
    total_depenses          DECIMAL(12,2) DEFAULT 0,
    total_autres_recettes   DECIMAL(12,2) DEFAULT 0,
    transfert_emis          DECIMAL(12,2) DEFAULT 0,
    transfert_recu          DECIMAL(12,2) DEFAULT 0,
    solde_physique          DECIMAL(12,2) DEFAULT 0,
    ecart                   DECIMAL(12,2) DEFAULT 0,
    observations            TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
);

CREATE TABLE mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    type            ENUM('depense','recette') NOT NULL,
    libelle         VARCHAR(300) NOT NULL,
    montant         DECIMAL(12,2) NOT NULL,
    mode_paiement   ENUM('especes','om','momo','carte','cheque') DEFAULT 'especes',
    date_mouvement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_id) REFERENCES caisses(id) ON DELETE CASCADE
);

CREATE TABLE transferts_caisse (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    caisse_source_id     INT NOT NULL,
    caisse_dest_id       INT NULL,
    guichetier_source_id INT NOT NULL,
    guichetier_dest_id   INT NOT NULL,
    montant_total        DECIMAL(12,2) NOT NULL,
    nb_tickets           INT DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_source_id) REFERENCES caisses(id),
    FOREIGN KEY(caisse_dest_id)   REFERENCES caisses(id),
    FOREIGN KEY(guichetier_source_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(guichetier_dest_id)   REFERENCES utilisateurs(id)
);

CREATE TABLE transfert_tickets (
    transfert_id    INT NOT NULL,
    ticket_id       INT NOT NULL,
    PRIMARY KEY(transfert_id, ticket_id),
    FOREIGN KEY(transfert_id) REFERENCES transferts_caisse(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_id)    REFERENCES tickets(id)
);

-- ── NOTIFICATIONS ─────────────────────────────────────────────
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    agence_id   INT,
    type        VARCHAR(50),
    titre       VARCHAR(200),
    message     TEXT,
    lue         TINYINT(1) DEFAULT 0,
    url         VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY(agence_id) REFERENCES agences(id) ON DELETE SET NULL
);

-- ── LOGS SYSTÈME ──────────────────────────────────────────────
CREATE TABLE logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(100),
    module     VARCHAR(50),
    details    TEXT,
    ip         VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── PARAMÈTRES SYSTÈME ────────────────────────────────────────
CREATE TABLE parametres (
    cle         VARCHAR(100) PRIMARY KEY,
    valeur      TEXT,
    description VARCHAR(300),
    modifie_par INT NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ================================================================
-- DONNÉES INITIALES
-- ================================================================

-- Rôles
INSERT INTO roles (code, nom, description, couleur, niveau) VALUES
('super_admin',    'Super Administrateur', 'Accès total, débogage', '#dc2626', 6),
('admin',          'Administrateur',       'Directeurs, Chefs services', '#7c3aed', 5),
('chef_agence',    'Chef d\'agence',        'Administration agence locale', '#2563eb', 4),
('chef_guichet',   'Chef de Guichet',      'Rapports, annulations, versements', '#0891b2', 3),
('guichetier',     'Guichetier',           'Vente tickets, bordereaux', '#16a34a', 2),
('operateur',      'Opérateur de saisie',  'Saisie bordereaux, dépenses, reçus', '#d97706', 1),
('chef_de_piste',  'Chef de Piste',        'Supervision des départs et chargement', '#0d9488', 2);

-- Permissions (organisées par section de menu)
INSERT INTO permissions (code, module, nom) VALUES
-- Billetterie
('tickets.view',          'billetterie',    'Voir les tickets'),
('tickets.create',       'billetterie',    'Vendre des tickets'),
('tickets.cancel',       'billetterie',    'Annuler / Restaurer des tickets'),
('tickets.print',        'billetterie',    'Imprimer les tickets'),
('reservations.manage',  'billetterie',    'Gérer les réservations'),
('transits.manage',      'billetterie',    'Gérer les transits'),
('tarifs.manage',        'billetterie',    'Gérer les tarifs'),
-- Voyages
('voyages.create',       'voyages',        'Programmer des voyages'),
('voyages.cancel',       'voyages',        'Annuler des voyages'),
-- Itinéraires
('itineraires.manage',       'itineraires',    'Gérer les itinéraires'),
('correspondances.manage',   'itineraires',    'Gérer les correspondances'),
('destinations.manage',      'itineraires',    'Gérer les destinations'),
-- Bordereaux
('bordereaux.create',   'bordereaux',     'Générer des bordereaux'),
('bordereaux.view',     'bordereaux',     'Voir les bordereaux'),
('bordereaux.saisie',   'bordereaux',     'Saisir les bordereaux reçus'),
('bordereaux.print',    'bordereaux',     'Imprimer les bordereaux'),
('bordereaux.validate', 'bordereaux',     'Valider les bordereaux'),
-- Rapports
('rapports.agence',     'rapports',       'Rapports journaliers agence'),
('rapports.guichet',    'rapports',       'Rapports journaliers guichetier'),
('rapports.direction',  'rapports',       'Rapports direction'),
-- Finances
('depenses.create',     'finances',       'Enregistrer des dépenses'),
('depenses.approve',    'finances',       'Approuver les dépenses'),
('versements.create',   'finances',       'Enregistrer des versements'),
('versements.confirm',  'finances',       'Confirmer les versements'),
-- Gestion
('vehicules.manage',        'gestion',    'Gérer les véhicules'),
('groupes.manage',          'gestion',    'Gérer les groupes'),
('proprietaires.manage',    'gestion',    'Gérer les propriétaires'),
('concessionnaires.manage', 'gestion',    'Gérer les concessionnaires'),
('agences.manage',          'gestion',    'Gérer les agences'),
('personnel.manage',        'gestion',    'Gérer le personnel'),
-- Administration
('users.manage',        'administration', 'Gérer les utilisateurs'),
('permissions.manage',  'administration', 'Gérer les rôles et permissions'),
('parametres.manage',   'administration', 'Gérer les paramètres'),
('caisse.manage',       'finances',       'Gérer la caisse');

-- Permissions super_admin = tout
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
-- admin = tout
INSERT INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions;
-- chef_agence = gestion complète agence (pas d'administration ni de bordereaux.saisie)
INSERT INTO role_permissions (role_id, permission_id) SELECT 3, id FROM permissions WHERE code IN ('tickets.view','tickets.create','tickets.cancel','tickets.print','bordereaux.create','bordereaux.view','bordereaux.print','bordereaux.validate','rapports.agence','rapports.guichet','depenses.create','depenses.approve','versements.create','versements.confirm','voyages.create','voyages.cancel','vehicules.manage','reservations.manage','transits.manage','tarifs.manage','agences.manage','personnel.manage','groupes.manage','proprietaires.manage','concessionnaires.manage','itineraires.manage','correspondances.manage','destinations.manage','caisse.manage');
-- chef_guichet = vente, annulation, rapports, bordereaux validation
INSERT INTO role_permissions (role_id, permission_id) SELECT 4, id FROM permissions WHERE code IN ('tickets.view','tickets.create','tickets.cancel','tickets.print','bordereaux.view','bordereaux.validate','rapports.agence','rapports.guichet','depenses.create','versements.create','voyages.cancel','reservations.manage','transits.manage','caisse.manage');
-- guichetier = vente tickets, bordereaux, rapports guichetier
INSERT INTO role_permissions (role_id, permission_id) SELECT 5, id FROM permissions WHERE code IN ('tickets.create','tickets.view','tickets.print','bordereaux.create','bordereaux.view','bordereaux.print','bordereaux.validate','reservations.manage','transits.manage','rapports.guichet','caisse.manage');
-- opérateur = saisie bordereaux, dépenses, versements
INSERT INTO role_permissions (role_id, permission_id) SELECT 6, id FROM permissions WHERE code IN ('bordereaux.saisie','bordereaux.view','depenses.create','versements.create','tickets.view');

-- Agences
INSERT INTO agences (code, nom, ville, region, telephone, adresse) VALUES
('YDE',  'Agence de Yaoundé',      'Yaoundé',    'Centre',    '+237 222 100 001', 'Carrefour Nlongkak, Yaoundé'),
('DLA',  'Agence de Douala',       'Douala',     'Littoral',  '+237 233 200 002', 'Quartier Akwa, Douala'),
('BFT',  'Agence de Bafoussam',    'Bafoussam',  'Ouest',     '+237 233 300 003', 'Centre ville, Bafoussam'),
('NGO',  'Agence de N\'Gaoundéré', 'N\'Gaoundéré','Adamaoua', '+237 222 400 004', 'Quartier Ngaoundéré'),
('BSM',  'Agence de Bertoua',      'Bertoua',    'Est',       '+237 222 500 005', 'Centre Bertoua'),
('GRB',  'Agence de Garoua',       'Garoua',     'Nord',      '+237 222 600 006', 'Quartier Plateau, Garoua');

-- Destinations
INSERT INTO destinations (agence_depart, agence_arrivee, distance_km, duree_minutes) VALUES
(1,2,250,240),(2,1,250,240),(1,3,280,300),(3,1,280,300),
(2,3,200,210),(3,2,200,210),(1,4,520,480),(4,1,520,480),
(1,5,350,360),(5,1,350,360),(2,4,600,540),(4,2,600,540);

-- Tarifs
INSERT INTO tarifs (destination_id, classe, prix, bagages_inclus) VALUES
(1,'cla',6000,20),(1,'vip',9000,30),(2,'cla',6000,20),(2,'vip',9000,30),
(3,'cla',7000,20),(3,'vip',10500,30),(4,'cla',7000,20),(4,'vip',10500,30),
(5,'cla',15000,20),(5,'vip',22000,30),(6,'cla',15000,20),(6,'vip',22000,30),
(7,'cla',10000,20),(7,'vip',15000,30),(8,'cla',10000,20),(8,'vip',15000,30);

-- Véhicules
INSERT INTO vehicules (immatriculation, marque, modele, type, capacite, agence_id, statut) VALUES
('LT-1234-A','Mercedes','O500','bus',70,1,'actif'),
('LT-5678-B','Yutong','ZK6122','bus',65,1,'actif'),
('LT-9012-C','King Long','XMQ6127','bus',60,2,'actif'),
('LT-3456-D','Isuzu','NQR','minibus',30,2,'actif'),
('LT-7890-E','Toyota','Hiace','minibus',14,3,'actif'),
('LT-2468-F','Mercedes','O500','bus',70,1,'panne');

-- Personnel
INSERT INTO personnel (matricule, nom, prenom, fonction, telephone, agence_id) VALUES
('CHF001','Mbarga','Jean','chauffeur','+237 677 100 001',1),
('CHF002','Ateba','Paul','chauffeur','+237 677 100 002',1),
('CHF003','Fouda','Pierre','chauffeur','+237 677 100 003',2),
('CNV001','Nkemdirim','Alice','convoyeur','+237 677 200 001',1),
('CNV002','Biya','Marie','convoyeur','+237 677 200 002',2);

-- Utilisateurs (mot de passe: password pour tous)
INSERT INTO utilisateurs (matricule, username, password, nom, prenom, role_id, agence_id, avatar_color) VALUES
('SA001', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Système', 'Admin', 1, NULL, '#dc2626'),
('AD001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Directeur', 'Général', 2, NULL, '#7c3aed'),
('CA001', 'chef_yde', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kamga', 'Thomas', 3, 1, '#2563eb'),
('CG001', 'chef_guichet1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mbida', 'Sylvie', 4, 1, '#0891b2'),
('GU_YGA', 'guichetier_yga', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Yagoua', 5, 1, '#16a34a'),
('GU_GRA1', 'guichetier_gra01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Garoua 1', 5, 2, '#16a34a'),
('GU_GRA2', 'guichetier_gra02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Garoua 2', 5, 3, '#16a34a'),
('GU_MRA2', 'guichetier_mra02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Maroua 2', 5, 4, '#16a34a'),
('GU_MRA1', 'guichetier_mra01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Maroua 1', 5, 5, '#16a34a'),
('GU_KLF', 'guichetier_klf', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Kalfou', 5, 6, '#16a34a'),
('GU_GDG', 'guichetier_gdg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Guidiguis', 5, 7, '#16a34a'),
('GU_KLE', 'guichetier_kle', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Kaele', 5, 8, '#16a34a'),
('GU_MKL', 'guichetier_mkl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guichetier', 'Mokolo', 5, 9, '#16a34a'),
('OP001', 'operateur1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tabi', 'Jean', 6, NULL, '#d97706');

-- Voyages démo
INSERT INTO voyages (numero, vehicule_id, chauffeur_id, convoyeur_id, destination_id, agence_id, date_depart, statut, places_dispo, montant_carburant, montant_peage, created_by) VALUES
('VOY-20250414-001', 1, 1, 4, 1, 1, DATE_ADD(NOW(), INTERVAL 2 HOUR), 'programme', 70, 45000, 3500, 5),
('VOY-20250414-002', 2, 2, 4, 3, 1, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'programme', 65, 50000, 4500, 5),
('VOY-20250413-001', 3, 3, 5, 2, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), 'arrive', 0, 40000, 3000, 5);

-- Tickets démo
INSERT INTO tickets (numero, voyage_id, passager_nom, passager_tel, siege, classe, montant, montant_total, statut, agence_id, guichetier_id) VALUES
('TKT-0001', 1, 'Kamga Maurice', '+237 677 001 001', 'A1', 'cla', 6000, 6000, 'vendu', 1, 5),
('TKT-0002', 1, 'Ateba Claire', '+237 677 001 002', 'A2', 'vip', 9000, 9000, 'vendu', 1, 5),
('TKT-0003', 1, 'Fouda Roger', '+237 677 001 003', 'B1', 'cla', 6000, 6000, 'vendu', 1, 6),
('TKT-0004', 1, 'Mbarga Pauline', '+237 677 001 004', 'B2', 'cla', 6000, 6000, 'vendu', 1, 6),
('TKT-0005', 2, 'Nkemdirim Alexis', '+237 677 001 005', 'A1', 'cla', 7000, 7000, 'vendu', 1, 5);

-- Paramètres
INSERT INTO parametres (cle, valeur, description) VALUES
('nom_entreprise',   'TRANSPORT EXPRESS CM', 'Nom de l\'entreprise'),
('slogan',           'Votre confort, notre priorité', 'Slogan'),
('adresse_siege',    'BP 1234 Yaoundé, Cameroun', 'Adresse siège'),
('telephone_siege',  '+237 222 000 001', 'Téléphone siège'),
('email_contact',    'contact@transportexpress.cm', 'Email contact'),
('monnaie',          'FCFA', 'Monnaie utilisée'),
('logo',             '', 'Chemin du logo'),
('limite_depense',   '10000', 'Limite dépense chef guichet (FCFA)'),
('delai_reservation','48', 'Délai expiration réservations (heures)'),
('prefix_ticket',    'TKT', 'Préfixe numéro ticket'),
('prefix_bordereau', 'BRD', 'Préfixe numéro bordereau'),
('prefix_voyage',    'VOY', 'Préfixe numéro voyage');
