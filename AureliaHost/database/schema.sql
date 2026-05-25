-- Base de données AureliaHost
CREATE DATABASE IF NOT EXISTS aureliahost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aureliahost;

-- Utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','receptionist','hr','accountant','manager') NOT NULL DEFAULT 'receptionist',
    telephone VARCHAR(20) DEFAULT '',
    avatar VARCHAR(255) DEFAULT '',
    statut TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Types de chambres
CREATE TABLE room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    prix_base DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacite INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Chambres
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(10) NOT NULL UNIQUE,
    room_type_id INT,
    etage INT DEFAULT 0,
    statut ENUM('disponible','occupee','maintenance','nettoyage') NOT NULL DEFAULT 'disponible',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(180) DEFAULT '',
    telephone VARCHAR(20) DEFAULT '',
    adresse TEXT DEFAULT '',
    ville VARCHAR(100) DEFAULT '',
    pays VARCHAR(100) DEFAULT 'Maroc',
    document_type ENUM('cin','passeport') DEFAULT 'cin',
    document_numero VARCHAR(50) DEFAULT '',
    date_naissance DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Réservations
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    user_id INT,
    date_checkin DATE NOT NULL,
    date_checkout DATE NOT NULL,
    statut ENUM('confirmee','en_cours','terminee','annulee') NOT NULL DEFAULT 'confirmee',
    montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_paye DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lien réservation-chambres
CREATE TABLE reservation_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    room_id INT NOT NULL,
    prix_par_nuit DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Services
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) NOT NULL DEFAULT 0,
    categorie ENUM('restaurant','spa','blanchisserie','transport','autre') NOT NULL DEFAULT 'autre',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Lien réservation-services
CREATE TABLE reservation_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    service_id INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_service DATE DEFAULT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Entretien
CREATE TABLE housekeeping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT,
    date_nettoyage DATE NOT NULL,
    statut ENUM('planifie','en_cours','termine') NOT NULL DEFAULT 'planifie',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Départements
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Employés
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(180) DEFAULT '',
    telephone VARCHAR(20) DEFAULT '',
    adresse TEXT DEFAULT '',
    department_id INT,
    poste VARCHAR(100) DEFAULT '',
    date_embauche DATE DEFAULT NULL,
    salaire_base DECIMAL(10,2) NOT NULL DEFAULT 0,
    statut ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
    type_contrat ENUM('cdi','cdd','stage','freelance') NOT NULL DEFAULT 'cdi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Présences
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    heure_entree TIME DEFAULT NULL,
    heure_sortie TIME DEFAULT NULL,
    statut ENUM('present','absent','retard','congé') NOT NULL DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Congés
CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type ENUM('conge_paye','maladie','maternite','sans_solde','autre') NOT NULL DEFAULT 'conge_paye',
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    motif TEXT,
    statut ENUM('en_attente','approuve','refuse') NOT NULL DEFAULT 'en_attente',
    approuve_par INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approuve_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Paie
CREATE TABLE payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    mois DATE NOT NULL,
    salaire_base DECIMAL(10,2) NOT NULL DEFAULT 0,
    primes DECIMAL(10,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
    salaire_net DECIMAL(10,2) NOT NULL DEFAULT 0,
    statut ENUM('brouillon','genere','paye') NOT NULL DEFAULT 'brouillon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Factures
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    client_id INT,
    numero_facture VARCHAR(50) NOT NULL UNIQUE,
    date_emission DATE NOT NULL,
    date_echeance DATE DEFAULT NULL,
    montant_ht DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_tva DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
    statut ENUM('brouillon','envoyee','payee','annulee') NOT NULL DEFAULT 'brouillon',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lignes de facture
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Paiements
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL DEFAULT 0,
    mode_paiement ENUM('especes','carte','virement','cheque') NOT NULL DEFAULT 'especes',
    reference VARCHAR(100) DEFAULT '',
    date_paiement DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dépenses
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    montant DECIMAL(10,2) NOT NULL DEFAULT 0,
    categorie ENUM('salaires','maintenance','fournitures','services','loyer','autre') NOT NULL DEFAULT 'autre',
    date_depense DATE NOT NULL,
    user_id INT,
    justificatif VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Taxes
CREATE TABLE taxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    taux DECIMAL(5,2) NOT NULL DEFAULT 0,
    type ENUM('tva','is','autre') NOT NULL DEFAULT 'tva',
    description TEXT,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Données par défaut
INSERT INTO users (nom, prenom, email, password_hash, role) VALUES
('Admin', 'Super', 'admin@aureliahost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Mot de passe: password
INSERT INTO users (nom, prenom, email, password_hash, role) VALUES
('Réception', 'Chef', 'reception@aureliahost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist');

INSERT INTO room_types (nom, description, prix_base, capacite) VALUES
('Chambre Simple', 'Chambre avec lit simple, salle de bain privée', 350.00, 1),
('Chambre Double', 'Chambre avec lit double, salle de bain privée, balcon', 500.00, 2),
('Suite Junior', 'Suite spacieuse avec salon, lit king-size, vue mer', 850.00, 2),
('Suite Royale', 'Suite luxueuse, 2 chambres, jacuzzi, terrasse panoramique', 1500.00, 4);

INSERT INTO rooms (numero, room_type_id, etage, statut, description) VALUES
('101', 1, 1, 'disponible', 'Chambre côté cour'),
('102', 1, 1, 'disponible', 'Chambre côté cour'),
('103', 1, 1, 'occupee', 'Chambre côté cour'),
('201', 2, 2, 'disponible', 'Chambre avec balcon'),
('202', 2, 2, 'disponible', 'Chambre avec balcon'),
('203', 2, 2, 'occupee', 'Chambre avec balcon, vue piscine'),
('301', 3, 3, 'disponible', 'Suite junior, vue mer'),
('302', 3, 3, 'maintenance', 'Suite junior, vue mer'),
('401', 4, 4, 'disponible', 'Suite royale, terrasse panoramique');

INSERT INTO departments (nom, description) VALUES
('Direction', 'Direction générale de l''hôtel'),
('Réception', 'Service d''accueil et réservations'),
('Restauration', 'Restaurant et service en chambre'),
('Entretien', 'Entretien et maintenance des chambres'),
('Spa & Bien-être', 'Centre spa et massages'),
('Comptabilité', 'Gestion financière et comptable');

INSERT INTO taxes (nom, taux, type, description) VALUES
('TVA Standard', 20.00, 'tva', 'TVA au taux normal'),
('TVA Réduite', 10.00, 'tva', 'TVA au taux réduit (restauration)'),
('IS', 31.00, 'is', 'Impôt sur les sociétés');

INSERT INTO services (nom, description, prix, categorie) VALUES
('Petit-déjeuner continental', 'Café, thé, viennoiseries, jus de fruits', 50.00, 'restaurant'),
('Déjeuner buffet', 'Buffet complet avec entrées, plats, desserts', 120.00, 'restaurant'),
('Dîner gastronomique', 'Menu 4 services par le chef', 200.00, 'restaurant'),
('Massage relaxant 60min', 'Massage du corps complet aux huiles essentielles', 250.00, 'spa'),
('Soin du visage', 'Soin hydratant et anti-âge 45min', 180.00, 'spa'),
('Blanchisserie', 'Service de lavage et repassage par pièce', 30.00, 'blanchisserie'),
('Navette aéroport', 'Transport privé vers/depuis l''aéroport', 150.00, 'transport'),
('Room service 24/7', 'Service en chambre', 0.00, 'autre');
