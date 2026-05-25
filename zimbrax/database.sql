-- ============================================================
-- ZIMBRAX — Base de données complète
-- Webmail + Calendrier + Contacts + Tâches
-- ============================================================
CREATE DATABASE IF NOT EXISTS zimbrax CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zimbrax;

-- UTILISATEURS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    avatar_color VARCHAR(7) DEFAULT '#4f8ef7',
    signature TEXT,
    timezone VARCHAR(50) DEFAULT 'Africa/Douala',
    theme ENUM('dark','light') DEFAULT 'dark',
    imap_host VARCHAR(100),
    imap_port INT DEFAULT 993,
    imap_ssl TINYINT(1) DEFAULT 1,
    smtp_host VARCHAR(100),
    smtp_port INT DEFAULT 587,
    smtp_ssl TINYINT(1) DEFAULT 1,
    mail_password VARCHAR(255),
    quota_mb INT DEFAULT 1024,
    active TINYINT(1) DEFAULT 1,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- DOSSIERS MAIL
CREATE TABLE folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('inbox','sent','drafts','spam','trash','custom') DEFAULT 'custom',
    color VARCHAR(7) DEFAULT '#4f8ef7',
    icon VARCHAR(20) DEFAULT '📁',
    sort_order INT DEFAULT 99,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- EMAILS
CREATE TABLE emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folder_id INT NOT NULL,
    message_id VARCHAR(255),
    from_name VARCHAR(150),
    from_email VARCHAR(150) NOT NULL,
    to_email TEXT NOT NULL,
    cc_email TEXT,
    bcc_email TEXT,
    subject VARCHAR(500),
    body_html LONGTEXT,
    body_text LONGTEXT,
    is_read TINYINT(1) DEFAULT 0,
    is_starred TINYINT(1) DEFAULT 0,
    is_important TINYINT(1) DEFAULT 0,
    has_attachment TINYINT(1) DEFAULT 0,
    thread_id VARCHAR(100),
    reply_to_id INT NULL,
    size_bytes INT DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    INDEX idx_user_folder (user_id, folder_id),
    INDEX idx_sent_at (sent_at DESC),
    INDEX idx_thread (thread_id)
);

-- TAGS EMAILS
CREATE TABLE email_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#4f8ef7',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE
);

-- PIÈCES JOINTES
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    size_bytes INT DEFAULT 0,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE
);

-- CONTACTS
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    email VARCHAR(150),
    email2 VARCHAR(150),
    phone VARCHAR(30),
    phone2 VARCHAR(30),
    company VARCHAR(150),
    job_title VARCHAR(150),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    website VARCHAR(200),
    notes TEXT,
    avatar_color VARCHAR(7) DEFAULT '#4f8ef7',
    group_name VARCHAR(100),
    is_favorite TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_email (user_id, email)
);

-- CALENDRIER — ÉVÉNEMENTS
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    location VARCHAR(300),
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day TINYINT(1) DEFAULT 0,
    color VARCHAR(7) DEFAULT '#4f8ef7',
    recurrence ENUM('none','daily','weekly','monthly','yearly') DEFAULT 'none',
    recurrence_end DATE NULL,
    reminder_minutes INT DEFAULT 15,
    is_private TINYINT(1) DEFAULT 0,
    category VARCHAR(50) DEFAULT 'default',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, start_datetime)
);

-- PARTICIPANTS ÉVÉNEMENTS
CREATE TABLE event_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- TÂCHES
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    due_date DATE NULL,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status ENUM('todo','in_progress','done','cancelled') DEFAULT 'todo',
    list_name VARCHAR(100) DEFAULT 'Tâches',
    color VARCHAR(7) DEFAULT '#4f8ef7',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- SESSIONS
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PARAMÈTRES UTILISATEUR
CREATE TABLE user_settings (
    user_id INT PRIMARY KEY,
    emails_per_page INT DEFAULT 25,
    auto_reply TINYINT(1) DEFAULT 0,
    auto_reply_message TEXT,
    show_preview TINYINT(1) DEFAULT 1,
    thread_view TINYINT(1) DEFAULT 1,
    default_font VARCHAR(50) DEFAULT 'DM Sans',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- Utilisateur admin (mdp: password)
INSERT INTO users (username, email, password, display_name, avatar_color) VALUES
('admin', 'admin@zimbrax.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Thomas Kengne', '#4f8ef7'),
('alice', 'alice@zimbrax.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Martin', '#a78bfa');

-- Set admin as admin
UPDATE users SET is_admin=1 WHERE username='admin';

-- Dossiers par défaut user 1
INSERT INTO folders (user_id, name, type, color, icon, sort_order) VALUES
(1,'Boîte de réception','inbox','#4f8ef7','📥',1),
(1,'Envoyés','sent','#3dd68c','📤',2),
(1,'Brouillons','drafts','#f7a84f','📝',3),
(1,'Spam','spam','#f75f5f','🚫',4),
(1,'Corbeille','trash','#8b8fa8','🗑',5),
(1,'Travail','custom','#4f8ef7','💼',6),
(1,'Personnel','custom','#3dd68c','🏠',7),
(1,'Projets','custom','#a78bfa','📁',8);

-- Dossiers user 2
INSERT INTO folders (user_id, name, type, color, icon, sort_order) VALUES
(2,'Boîte de réception','inbox','#a78bfa','📥',1),
(2,'Envoyés','sent','#3dd68c','📤',2),
(2,'Brouillons','drafts','#f7a84f','📝',3),
(2,'Spam','spam','#f75f5f','🚫',4),
(2,'Corbeille','trash','#8b8fa8','🗑',5);

-- Emails démo pour user 1
INSERT INTO emails (user_id, folder_id, from_name, from_email, to_email, subject, body_html, body_text, is_read, is_starred, received_at) VALUES
(1, 1, 'Marie Dupont', 'm.dupont@acme.fr', 'admin@zimbrax.cm',
 'Re: Livraison du projet Phase 3',
 '<p>Bonjour Thomas,</p><p>J\'ai bien reçu vos fichiers et j\'ai eu l\'occasion de les parcourir avec l\'équipe. Dans l\'ensemble, le travail est de <strong>très bonne qualité</strong>.</p><p>Quelques points à ajuster :</p><ul><li>La section 3.2 nécessite plus de détails sur l\'architecture</li><li>Les tests unitaires manquent pour le module d\'authentification</li><li>La documentation API est incomplète</li></ul><p>Pourriez-vous corriger ces points avant <strong>vendredi 17h</strong> ?</p><p>Cordialement,<br>Marie Dupont<br><em>Chef de projet — ACME Corp</em></p>',
 'Bonjour Thomas, j\'ai bien reçu vos fichiers...', 0, 1, NOW()),

(1, 1, 'GitHub', 'noreply@github.com', 'admin@zimbrax.cm',
 '[zimbrax-core] Pull request #47 merged',
 '<p>Pull request <strong>#47</strong> has been successfully merged into <code>main</code>.</p><p>Changes included:</p><ul><li>Added OAuth2 support</li><li>Improved email parsing performance by 40%</li><li>Fixed memory leak in IMAP connector</li></ul>',
 'Pull request #47 merged into main.', 0, 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

(1, 1, 'Jean-Baptiste Fouda', 'jb.fouda@gmail.com', 'admin@zimbrax.cm',
 'Invitation dîner vendredi',
 '<p>Salut Thomas !</p><p>On organise un petit dîner vendredi soir à la maison vers 20h. Ce sera l\'occasion de fêter la fin du projet.</p><p>Tu peux venir ? Dis-moi si tu as des restrictions alimentaires.</p><p>À bientôt,<br>JB</p>',
 'Salut Thomas ! On organise un petit dîner vendredi...', 0, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

(1, 1, 'Banque UBA', 'noreply@uba.cm', 'admin@zimbrax.cm',
 'Relevé de compte — Mars 2025',
 '<p>Cher client,</p><p>Votre relevé de compte du mois de <strong>Mars 2025</strong> est maintenant disponible.</p><p>Solde au 31/03/2025 : <strong>1 247 500 FCFA</strong></p><p>Veuillez vous connecter à votre espace client pour consulter le détail de vos opérations.</p>',
 'Votre relevé de compte Mars 2025 est disponible.', 1, 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),

(1, 1, 'Anthropic Team', 'team@anthropic.com', 'admin@zimbrax.cm',
 'Claude API — Nouveautés avril 2025',
 '<p>Bonjour,</p><p>Nous sommes ravis de vous présenter les <strong>nouveautés de l\'API Claude</strong> :</p><ul><li>Claude Sonnet 4.5 — performances améliorées</li><li>Fenêtre de contexte étendue à 200K tokens</li><li>Nouveaux outils : computer use, web search</li><li>Prix réduits de 20% sur tous les modèles</li></ul>',
 'Nouveautés API Claude avril 2025', 1, 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),

(1, 1, 'Kofi Mensah', 'kofi@techghana.com', 'admin@zimbrax.cm',
 'Partenariat — Proposition commerciale',
 '<p>Bonjour Thomas,</p><p>Suite à notre échange lors de la conférence AfricaTech à Accra, je me permets de vous adresser notre proposition de partenariat.</p><p>Notre société <strong>TechGhana</strong> est spécialisée dans le déploiement de solutions SaaS en Afrique subsaharienne.</p><p>Seriez-vous disponible pour un appel la semaine prochaine ?</p><p>Cordialement,<br>Kofi Mensah<br><em>CEO — TechGhana Ltd</em></p>',
 'Proposition de partenariat TechGhana', 0, 0, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Email envoyé
INSERT INTO emails (user_id, folder_id, from_name, from_email, to_email, subject, body_html, body_text, is_read, sent_at) VALUES
(1, 2, 'Thomas Kengne', 'admin@zimbrax.cm', 'm.dupont@acme.fr',
 'Livraison du projet Phase 3',
 '<p>Bonjour Marie,</p><p>Veuillez trouver ci-joint les livrables de la Phase 3 du projet.</p><p>Cordialement,<br>Thomas</p>',
 'Bonjour Marie, veuillez trouver ci-joint...', 1, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Contacts
INSERT INTO contacts (user_id, first_name, last_name, email, phone, company, job_title, avatar_color, is_favorite) VALUES
(1,'Marie','Dupont','m.dupont@acme.fr','+33 1 23 45 67 89','ACME Corp','Chef de projet','#4f8ef7',1),
(1,'Jean-Baptiste','Fouda','jb.fouda@gmail.com','+237 691 234 567',NULL,'Développeur freelance','#3dd68c',1),
(1,'Kofi','Mensah','kofi@techghana.com','+233 20 123 4567','TechGhana Ltd','CEO','#f7a84f',0),
(1,'Alice','Martin','alice@zimbrax.cm','+237 677 890 123','ZimbraX','Développeuse','#a78bfa',1),
(1,'David','Eto\'o','d.etoo@canal.cm','+237 655 678 901','Canal+','Directeur Technique','#f75f5f',0),
(1,'Sarah','Nkemdirim','s.nkemdirim@ng.com','+234 802 345 6789','TechNG','Product Manager','#3dd68c',0),
(1,'Pierre','Lambert','p.lambert@startup.io','+33 6 78 90 12 34','StartupIO','CTO','#8b8fa8',0),
(1,'Fatou','Diallo','f.diallo@orange.sn','+221 77 123 45 67','Orange Sénégal','Ingénieure','#f7a84f',0);

-- Événements calendrier
INSERT INTO events (user_id, title, description, location, start_datetime, end_datetime, color, category) VALUES
(1,'Réunion équipe dev','Review du sprint en cours','Salle Conférence A', DATE_FORMAT(NOW(),'%Y-%m-%d 14:00:00'), DATE_FORMAT(NOW(),'%Y-%m-%d 15:30:00'),'#4f8ef7','work'),
(1,'Review sprint #12','Démo des fonctionnalités','Google Meet', DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 09:00:00'), INTERVAL 1 DAY), DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 10:00:00'), INTERVAL 1 DAY),'#3dd68c','work'),
(1,'Démo client Yaoundé','Présentation ZimbraX','Hôtel Mont Fébé', DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 15:00:00'), INTERVAL 3 DAY), DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 16:30:00'), INTERVAL 3 DAY),'#f7a84f','work'),
(1,'Formation sécurité','Cybersécurité pour développeurs','En ligne', DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 10:00:00'), INTERVAL 4 DAY), DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 12:00:00'), INTERVAL 4 DAY),'#a78bfa','training'),
(1,'Anniversaire Alice','','', DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL 7 DAY), DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 23:59:00'), INTERVAL 7 DAY),'#f75f5f','personal'),
(1,'Appel Kofi Mensah','Discussion partenariat TechGhana','WhatsApp', DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 11:00:00'), INTERVAL 5 DAY), DATE_ADD(DATE_FORMAT(NOW(),'%Y-%m-%d 12:00:00'), INTERVAL 5 DAY),'#f7a84f','work');

-- Tâches
INSERT INTO tasks (user_id, title, description, due_date, priority, status, list_name, color) VALUES
(1,'Finaliser le rapport Phase 3','Corriger les sections signalées par Marie',DATE_ADD(CURDATE(),INTERVAL 2 DAY),'high','todo','Travail','#f75f5f'),
(1,'Review PR GitHub #47','Vérifier les changements OAuth2',CURDATE(),'normal','done','Travail','#4f8ef7'),
(1,'Appel Kofi Mensah','Préparer la proposition',DATE_ADD(CURDATE(),INTERVAL 5 DAY),'normal','todo','Travail','#f7a84f'),
(1,'Payer facture hébergement','OVH — 45 000 FCFA',DATE_ADD(CURDATE(),INTERVAL 1 DAY),'high','todo','Finance','#3dd68c'),
(1,'Préparer démo client','Slides + démo live',DATE_ADD(CURDATE(),INTERVAL 3 DAY),'urgent','in_progress','Travail','#a78bfa'),
(1,'Lire documentation Claude API','Nouveaux endpoints',DATE_ADD(CURDATE(),INTERVAL 7 DAY),'low','todo','Perso','#8b8fa8'),
(1,'Commander nourriture dîner','Pour vendredi soir',DATE_ADD(CURDATE(),INTERVAL 4 DAY),'normal','todo','Perso','#3dd68c');

-- Paramètres
INSERT INTO user_settings (user_id) VALUES (1),(2);
