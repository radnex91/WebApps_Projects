-- ============================================
-- PayNovaRH - Base de données
-- Application de Gestion des Ressources Humaines
-- ============================================

CREATE DATABASE IF NOT EXISTS paynovarh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE paynovarh;

-- ============================================
-- AUTHENTIFICATION & ROLES
-- ============================================

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    UNIQUE KEY unique_perm (module, action)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ============================================
-- EMPLOYÉS & STRUCTURE
-- ============================================

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    department_id INT NOT NULL,
    description TEXT,
    salary_min DECIMAL(12,2) DEFAULT 0,
    salary_max DECIMAL(12,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Maroc',
    birth_date DATE,
    gender ENUM('homme', 'femme') DEFAULT 'homme',
    marital_status ENUM('célibataire', 'marié(e)', 'divorcé(e)', 'veuf(ve)') DEFAULT 'célibataire',
    children_count INT DEFAULT 0,
    cin VARCHAR(20),
    cnss VARCHAR(20),
    photo VARCHAR(255),
    department_id INT,
    position_id INT,
    hire_date DATE NOT NULL,
    status ENUM('actif', 'inactif', 'en_congé', 'résilié') DEFAULT 'actif',
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    bank_name VARCHAR(100),
    bank_account VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add manager FK now that employees table exists
ALTER TABLE departments ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type ENUM('CDI', 'CDD', 'Stage', 'Freelance', 'Intérim') NOT NULL DEFAULT 'CDI',
    start_date DATE NOT NULL,
    end_date DATE,
    salary DECIMAL(12,2) NOT NULL,
    renewal_date DATE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- CONGÉS
-- ============================================

CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    days_allowed INT NOT NULL DEFAULT 0,
    is_paid TINYINT(1) DEFAULT 1,
    requires_approval TINYINT(1) DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    total_days INT NOT NULL DEFAULT 0,
    used_days INT NOT NULL DEFAULT 0,
    remaining_days INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_balance (employee_id, leave_type_id, year),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,1) NOT NULL,
    reason TEXT,
    status ENUM('en_attente', 'approuvé', 'refusé', 'annulé') DEFAULT 'en_attente',
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- POINTAGE
-- ============================================

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    late_minutes INT DEFAULT 0,
    early_leave_minutes INT DEFAULT 0,
    overtime_minutes INT DEFAULT 0,
    status ENUM('présent', 'absent', 'retard', 'congé', 'mi-journée') DEFAULT 'présent',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (employee_id, date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- PAIE
-- ============================================

CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month INT NOT NULL,
    year INT NOT NULL,
    status ENUM('brouillon', 'en_cours', 'clôturé') DEFAULT 'brouillon',
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (month, year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_period_id INT NOT NULL,
    employee_id INT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_amount DECIMAL(12,2) DEFAULT 0,
    bonus DECIMAL(12,2) DEFAULT 0,
    other_allowances DECIMAL(12,2) DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    cnss_employee DECIMAL(12,2) DEFAULT 0,
    cnss_employer DECIMAL(12,2) DEFAULT 0,
    amo_employee DECIMAL(12,2) DEFAULT 0,
    amo_employer DECIMAL(12,2) DEFAULT 0,
    cimr DECIMAL(12,2) DEFAULT 0,
    itr DECIMAL(12,2) DEFAULT 0,
    advance DECIMAL(12,2) DEFAULT 0,
    other_deductions DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('brouillon', 'validé', 'payé') DEFAULT 'brouillon',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (payroll_period_id, employee_id),
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- RECRUTEMENT
-- ============================================

CREATE TABLE IF NOT EXISTS job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    department_id INT,
    position_id INT,
    description TEXT NOT NULL,
    requirements TEXT,
    salary_range VARCHAR(100),
    location VARCHAR(100),
    type ENUM('CDI', 'CDD', 'Stage', 'Freelance') DEFAULT 'CDI',
    status ENUM('brouillon', 'publié', 'clôturé') DEFAULT 'brouillon',
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_posting_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    cover_letter TEXT,
    resume VARCHAR(255),
    status ENUM('nouveau', 'entretien', 'test', 'offre', 'embauché', 'refusé') DEFAULT 'nouveau',
    interview_date DATETIME,
    interview_notes TEXT,
    score INT,
    employee_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- ÉVALUATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS evaluation_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('brouillon', 'en_cours', 'clôturé') DEFAULT 'brouillon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluation_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    max_score INT NOT NULL DEFAULT 5,
    weight DECIMAL(3,2) DEFAULT 1.00,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_period_id INT NOT NULL,
    employee_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    status ENUM('brouillon', 'auto_évaluation', 'évaluation_manager', 'complété') DEFAULT 'brouillon',
    overall_score DECIMAL(5,2) DEFAULT 0,
    comments TEXT,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_period_id) REFERENCES evaluation_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluation_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    criteria_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    comment TEXT,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id) REFERENCES evaluation_criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- FORMATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    trainer VARCHAR(100),
    start_date DATE,
    end_date DATE,
    location VARCHAR(150),
    capacity INT DEFAULT 0,
    budget DECIMAL(12,2) DEFAULT 0,
    status ENUM('planifié', 'en_cours', 'terminé', 'annulé') DEFAULT 'planifié',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS training_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_program_id INT NOT NULL,
    employee_id INT NOT NULL,
    status ENUM('inscrit', 'en_cours', 'terminé', 'annulé') DEFAULT 'inscrit',
    score DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (training_program_id, employee_id),
    FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- DOCUMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category ENUM('contrat', 'CV', 'diplôme', 'attestation', 'autre') DEFAULT 'autre',
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(20),
    file_size INT,
    description TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- NOTIFICATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- DONNÉES SEED
-- ============================================

-- Rôles
INSERT INTO roles (name, description, is_default) VALUES
('admin', 'Administrateur RH - Accès complet', 0),
('manager', 'Manager - Gestion de son équipe', 0),
('employé', 'Employé - Consultation et demandes', 1);

-- Permissions
INSERT INTO permissions (module, action, description) VALUES
('dashboard', 'view', 'Voir le tableau de bord'),
('employees', 'view', 'Voir les employés'),
('employees', 'create', 'Créer un employé'),
('employees', 'edit', 'Modifier un employé'),
('employees', 'delete', 'Supprimer un employé'),
('departments', 'view', 'Voir les départements'),
('departments', 'create', 'Créer un département'),
('departments', 'edit', 'Modifier un département'),
('departments', 'delete', 'Supprimer un département'),
('positions', 'view', 'Voir les postes'),
('positions', 'create', 'Créer un poste'),
('positions', 'edit', 'Modifier un poste'),
('positions', 'delete', 'Supprimer un poste'),
('contracts', 'view', 'Voir les contrats'),
('contracts', 'create', 'Créer un contrat'),
('contracts', 'edit', 'Modifier un contrat'),
('contracts', 'delete', 'Supprimer un contrat'),
('leaves', 'view', 'Voir les congés'),
('leaves', 'create', 'Demander un congé'),
('leaves', 'approve', 'Approuver/Refuser un congé'),
('leaves', 'delete', 'Supprimer un congé'),
('attendance', 'view', 'Voir le pointage'),
('attendance', 'create', 'Enregistrer un pointage'),
('attendance', 'edit', 'Modifier un pointage'),
('attendance', 'delete', 'Supprimer un pointage'),
('payroll', 'view', 'Voir la paie'),
('payroll', 'create', 'Créer une période de paie'),
('payroll', 'edit', 'Modifier la paie'),
('payroll', 'delete', 'Supprimer la paie'),
('recruitment', 'view', 'Voir le recrutement'),
('recruitment', 'create', 'Créer une offre'),
('recruitment', 'edit', 'Modifier une offre'),
('recruitment', 'delete', 'Supprimer une offre'),
('evaluations', 'view', 'Voir les évaluations'),
('evaluations', 'create', 'Créer une évaluation'),
('evaluations', 'edit', 'Modifier une évaluation'),
('evaluations', 'delete', 'Supprimer une évaluation'),
('training', 'view', 'Voir les formations'),
('training', 'create', 'Créer une formation'),
('training', 'edit', 'Modifier une formation'),
('training', 'delete', 'Supprimer une formation'),
('documents', 'view', 'Voir les documents'),
('documents', 'create', 'Ajouter un document'),
('documents', 'edit', 'Modifier un document'),
('documents', 'delete', 'Supprimer un document'),
('reports', 'view', 'Voir les rapports'),
('reports', 'export', 'Exporter les rapports'),
('roles', 'view', 'Voir les rôles'),
('roles', 'create', 'Créer un rôle'),
('roles', 'edit', 'Modifier un rôle'),
('roles', 'delete', 'Supprimer un rôle');

-- Admin : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'admin';

-- Manager : vue + gestion + approbation congés
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'manager' AND p.module IN ('dashboard', 'employees', 'departments', 'positions', 'leaves', 'attendance', 'evaluations', 'training', 'documents', 'reports')
AND p.action IN ('view', 'create', 'edit');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'manager' AND p.module = 'leaves' AND p.action = 'approve';

-- Employé : consultation + demandes personnelles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'employé' AND p.module IN ('dashboard', 'leaves', 'attendance', 'training', 'documents', 'evaluations')
AND p.action = 'view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'employé' AND p.module = 'leaves' AND p.action = 'create';

-- Types de congés
INSERT INTO leave_types (name, days_allowed, is_paid, requires_approval, description) VALUES
('Congé annuel', 18, 1, 1, 'Congé payé annuel'),
('Congé maladie', 10, 1, 1, 'Congé pour raison médicale'),
('Congé maternité', 90, 1, 1, 'Congé maternité légal'),
('Congé paternité', 3, 1, 1, 'Congé paternité'),
('Congé sans solde', 0, 0, 1, 'Congé non rémunéré'),
('Congé exceptionnel', 3, 1, 1, 'Événements familiaux exceptionnels');

-- Critères d'évaluation
INSERT INTO evaluation_criteria (name, description, max_score, weight, category) VALUES
('Qualité du travail', 'Précision et fiabilité du travail', 5, 1.50, 'Compétence'),
('Productivité', 'Quantité et efficacité du travail', 5, 1.50, 'Compétence'),
('Connaissances techniques', 'Maîtrise des compétences requises', 5, 1.00, 'Compétence'),
('Travail d''équipe', 'Collaboration et communication', 5, 1.00, 'Comportement'),
('Initiative', 'Proactivité et proposition d''idées', 5, 1.00, 'Comportement'),
('Ponctualité', 'Respect des horaires et délais', 5, 1.00, 'Comportement'),
('Atteinte des objectifs', 'Réalisation des objectifs fixés', 5, 2.00, 'Résultats');

-- Utilisateur admin (mot de passe: admin123)
INSERT INTO users (username, email, password, role_id, is_active) VALUES
('admin', 'admin@paynovarh.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

-- Départements
INSERT INTO departments (name, description) VALUES
('Direction Générale', 'Direction et gestion stratégique de l''entreprise'),
('Ressources Humaines', 'Gestion du personnel et de l''administration'),
('Informatique', 'Développement et maintenance des systèmes informatiques'),
('Finance & Comptabilité', 'Gestion financière et comptabilité'),
('Marketing', 'Stratégie marketing et communication'),
('Ventes', 'Commercialisation et relation client');

-- Postes
INSERT INTO positions (title, department_id, salary_min, salary_max) VALUES
('Directeur Général', 1, 30000, 60000),
('Responsable RH', 2, 15000, 25000),
('Développeur Senior', 3, 12000, 22000),
('Développeur Junior', 3, 6000, 10000),
('Comptable', 4, 8000, 14000),
('Responsable Marketing', 5, 12000, 20000),
('Commercial', 6, 7000, 15000);

-- Employés démo
INSERT INTO employees (user_id, first_name, last_name, email, phone, department_id, position_id, hire_date, status, gender) VALUES
(1, 'Admin', 'Système', 'admin@paynovarh.ma', '0600000000', 2, 2, '2024-01-01', 'actif', 'homme');

INSERT INTO employees (first_name, last_name, email, phone, department_id, position_id, hire_date, status, gender, birth_date, cin) VALUES
('Mohammed', 'Benali', 'm.benali@paynovarh.ma', '0612345678', 3, 3, '2023-03-15', 'actif', 'homme', '1990-05-20', 'AB123456'),
('Fatima', 'Zahra', 'f.zahra@paynovarh.ma', '0623456789', 4, 5, '2022-08-01', 'actif', 'femme', '1992-11-10', 'CD789012'),
('Youssef', 'Amrani', 'y.amrani@paynovarh.ma', '0634567890', 5, 6, '2023-06-20', 'actif', 'homme', '1988-03-25', 'EF345678');

-- Contrats démo
INSERT INTO contracts (employee_id, type, start_date, salary) VALUES
(1, 'CDI', '2024-01-01', 25000),
(2, 'CDI', '2023-03-15', 18000),
(3, 'CDI', '2022-08-01', 12000),
(4, 'CDD', '2023-06-20', 16000);

-- Soldes congés démo
INSERT INTO leave_balances (employee_id, leave_type_id, year, total_days, used_days, remaining_days)
SELECT e.id, lt.id, YEAR(CURDATE()), lt.days_allowed, 0, lt.days_allowed
FROM employees e CROSS JOIN leave_types lt
WHERE lt.days_allowed > 0;