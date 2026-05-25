-- =============================================
-- ButcheryPOS Database Schema
-- Phase 1: Core Operations
-- =============================================

CREATE DATABASE IF NOT EXISTS butcherypos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE butcherypos;

-- =============================================
-- CORE: Settings & Configuration
-- =============================================

CREATE TABLE app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB;

-- =============================================
-- AUTH & RBAC
-- =============================================

CREATE TABLE app_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(80) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system_role TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    preferred_language ENUM('en','fr') NOT NULL DEFAULT 'fr',
    phone VARCHAR(40) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES app_roles(id) ON DELETE RESTRICT,
    INDEX idx_user_username (username),
    INDEX idx_user_role (role_id),
    INDEX idx_user_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE role_module_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    can_manage TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_role_module (role_id, module_key),
    CONSTRAINT fk_perm_role FOREIGN KEY (role_id) REFERENCES app_roles(id) ON DELETE CASCADE,
    INDEX idx_perm_role (role_id)
) ENGINE=InnoDB;

-- =============================================
-- SUPPLIERS (must be before stock_batches FK)
-- =============================================

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- CUSTOMERS
-- =============================================

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_phone (phone)
) ENGINE=InnoDB;

-- =============================================
-- PRODUCT CATALOG
-- =============================================

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    unit_type ENUM('weight','piece','volume') NOT NULL DEFAULT 'weight',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(80) DEFAULT NULL UNIQUE,
    category_id INT DEFAULT NULL,
    unit_id INT NOT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity_in_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    track_expiry TINYINT(1) NOT NULL DEFAULT 1,
    default_shelf_life_days INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    image VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_product_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    INDEX idx_product_category (category_id),
    INDEX idx_product_active (is_active),
    INDEX idx_product_sku (sku)
) ENGINE=InnoDB;

-- =============================================
-- STOCK & FIFO (Batch Tracking)
-- =============================================

CREATE TABLE stock_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_reference VARCHAR(80) NOT NULL,
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_remaining DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    supplier_id INT DEFAULT NULL,
    is_fully_consumed TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_batch_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_batch_product (product_id),
    INDEX idx_batch_expiry (expiry_date),
    INDEX idx_batch_remaining (product_id, quantity_remaining, is_fully_consumed)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_id INT DEFAULT NULL,
    movement_type ENUM('purchase','sale','adjustment','waste','return') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    quantity_before DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity_after DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    reference_table VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movement_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_movement_batch FOREIGN KEY (batch_id) REFERENCES stock_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_movement_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movement_product (product_id),
    INDEX idx_movement_type (movement_type),
    INDEX idx_movement_date (created_at)
) ENGINE=InnoDB;

-- =============================================
-- POS & SALES
-- =============================================

CREATE TABLE pos_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    terminal_name VARCHAR(60) NOT NULL DEFAULT 'Terminal-1',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_cash DECIMAL(12,2) DEFAULT NULL,
    status ENUM('open','closed','suspended') NOT NULL DEFAULT 'open',
    CONSTRAINT fk_pos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pos_token (session_token),
    INDEX idx_pos_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_session_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    reference VARCHAR(80) NOT NULL UNIQUE,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
    payment_status ENUM('paid','partial','credit','pending') NOT NULL DEFAULT 'paid',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_session FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_sale_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sale_reference (reference),
    INDEX idx_sale_session (pos_session_id),
    INDEX idx_sale_date (created_at),
    INDEX idx_sale_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT DEFAULT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_item_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_item_batch FOREIGN KEY (batch_id) REFERENCES stock_batches(id) ON DELETE SET NULL,
    INDEX idx_item_sale (sale_id),
    INDEX idx_item_product (product_id)
) ENGINE=InnoDB;

-- =============================================
-- PAYMENTS
-- =============================================

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    payment_gateway VARCHAR(60) DEFAULT NULL,
    gateway_reference VARCHAR(120) DEFAULT NULL,
    gateway_status VARCHAR(40) DEFAULT NULL,
    gateway_response TEXT DEFAULT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    INDEX idx_payment_sale (sale_id),
    INDEX idx_payment_method (payment_method)
) ENGINE=InnoDB;

-- =============================================
-- EXPIRY ALERTS
-- =============================================

CREATE TABLE expiry_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    product_id INT NOT NULL,
    alert_type ENUM('warning','critical','expired') NOT NULL,
    days_until_expiry INT NOT NULL,
    is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
    dismissed_by INT DEFAULT NULL,
    dismissed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_batch FOREIGN KEY (batch_id) REFERENCES stock_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_dismissed FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_alert_dismissed (is_dismissed),
    UNIQUE KEY uniq_alert_batch_type (batch_id, alert_type)
) ENGINE=InnoDB;

-- =============================================
-- SCALE READINGS
-- =============================================

CREATE TABLE scale_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weight_value DECIMAL(12,4) NOT NULL,
    unit VARCHAR(10) NOT NULL DEFAULT 'kg',
    device_code VARCHAR(80) NOT NULL DEFAULT 'default-scale',
    raw_payload TEXT DEFAULT NULL,
    pos_session_id INT DEFAULT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_scale_session FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id) ON DELETE SET NULL,
    INDEX idx_scale_captured (captured_at DESC)
) ENGINE=InnoDB;

-- =============================================
-- SEED DATA
-- =============================================

-- Roles
INSERT INTO app_roles (role_name, display_name, description, is_system_role) VALUES
('admin', 'Administrateur', 'Accès complet au système, paramètres sensibles', 1),
('manager', 'Gestionnaire', 'Gérer les produits, le stock, les ventes et les rapports', 0),
('cashier', 'Caissier', 'Ventes au comptoir, pesée, encaissement', 0),
('stock_clerk', 'Magasinier', 'Gestion du stock, réception, contrôle des péremptions', 0);

-- Admin user (password: admin123)
INSERT INTO users (full_name, username, password, role_id, preferred_language, phone, is_active) VALUES
('Administrateur principal', 'admin', '$2y$10$i5TAnYTrBqP8ho7dmiqhf.GuwTBbNqRQvtdVyvv6z73XM2XQ0KkW6', 1, 'fr', '0000000000', 1);

-- Walk-in customer
INSERT INTO customers (name, phone) VALUES
('Client comptoir', '0000000000');

-- Default supplier
INSERT INTO suppliers (name, phone) VALUES
('Fournisseur par défaut', '0000000000');

-- Units
INSERT INTO units (name, abbreviation, unit_type) VALUES
('Kilogramme', 'kg', 'weight'),
('Gramme', 'g', 'weight'),
('Pièce', 'pcs', 'piece'),
('Litre', 'L', 'volume'),
('Botte', 'bte', 'piece');

-- Categories (butchery-specific)
INSERT INTO categories (name, description, sort_order) VALUES
('Viande bovine', 'Beef cuts', 1),
('Viande porcine', 'Pork cuts', 2),
('Volaille', 'Poultry', 3),
('Poisson', 'Fish and seafood', 4),
('Épices', 'Spices and seasonings', 5),
('Produits transformés', 'Processed meats', 6);

-- Sample products
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days) VALUES
('Côte de bœuf', 'BOEUF-001', 1, 1, 3500, 5500, 40, 10, 1, 5),
('Filet de porc', 'PORC-001', 2, 1, 2800, 4200, 25, 8, 1, 4),
('Poulet entier', 'VOL-001', 3, 3, 2200, 3500, 30, 12, 1, 3),
('Tilapia frais', 'POISS-001', 4, 1, 1800, 2800, 20, 6, 1, 2),
('Poivre noir', 'EPICE-001', 5, 3, 500, 800, 50, 15, 0, NULL),
('Saucisse fumée', 'TRANS-001', 6, 3, 1500, 2500, 35, 10, 1, 7);

-- Admin permissions (role_id=1, full access)
INSERT INTO role_module_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print, can_manage) VALUES
(1, 'dashboard', 1,0,0,0,0,0),
(1, 'pos', 1,1,1,1,1,1),
(1, 'products', 1,1,1,1,1,1),
(1, 'categories', 1,1,1,1,1,1),
(1, 'stock', 1,1,1,1,1,1),
(1, 'sales', 1,1,1,1,1,1),
(1, 'customers', 1,1,1,1,1,1),
(1, 'suppliers', 1,1,1,1,1,1),
(1, 'users', 1,1,1,1,1,1),
(1, 'roles', 1,1,1,1,1,1),
(1, 'settings', 1,1,1,1,1,1),
(1, 'expiry', 1,1,1,1,1,1),
(1, 'scale', 1,1,1,1,1,1),
(1, 'reports', 1,1,1,1,1,1);

-- Manager permissions (role_id=2)
INSERT INTO role_module_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print, can_manage) VALUES
(2, 'dashboard', 1,0,0,0,0,0),
(2, 'pos', 1,1,1,0,1,0),
(2, 'products', 1,1,1,0,0,0),
(2, 'categories', 1,1,0,0,0,0),
(2, 'stock', 1,1,1,0,0,0),
(2, 'sales', 1,1,0,0,1,0),
(2, 'customers', 1,1,1,0,0,0),
(2, 'suppliers', 1,1,0,0,0,0),
(2, 'expiry', 1,1,1,0,0,0),
(2, 'scale', 1,1,0,0,0,0);

-- Cashier permissions (role_id=3)
INSERT INTO role_module_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print, can_manage) VALUES
(3, 'dashboard', 1,0,0,0,0,0),
(3, 'pos', 1,1,0,0,1,0),
(3, 'sales', 1,0,0,0,1,0),
(3, 'customers', 1,1,0,0,0,0),
(3, 'scale', 1,1,0,0,0,0);

-- Stock clerk permissions (role_id=4)
INSERT INTO role_module_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print, can_manage) VALUES
(4, 'dashboard', 1,0,0,0,0,0),
(4, 'stock', 1,1,1,0,0,0),
(4, 'products', 1,0,1,0,0,0),
(4, 'expiry', 1,1,1,0,0,0),
(4, 'scale', 1,1,0,0,0,0);

-- App settings
INSERT INTO app_settings (setting_key, setting_value, is_sensitive) VALUES
('app_name', 'ButcheryPOS', 0),
('company_name', 'Ma Boucherie', 0),
('company_tagline', 'Gestion de boucherie et poissonnerie', 0),
('company_phone', '+237 600 000 000', 0),
('company_address', 'Douala, Cameroun', 0),
('company_currency', 'XAF', 0),
('scale_enabled', '0', 0),
('scale_api_key', 'CHANGE_ME_SCALE_KEY', 1),
('scale_device_name', 'Dibal Scale Bridge', 0),
('scale_poll_interval_ms', '1500', 0),
('scale_protocol', 'dibal', 0),
('scale_serial_port', 'COM3', 0),
('scale_baud_rate', '9600', 0),
('expiry_warning_days', '3', 0),
('expiry_critical_days', '1', 0),
('branding_logo_path', '', 0),
('branding_theme_primary', '#9b2c2c', 0),
('branding_theme_secondary', '#2c1712', 0),
('branding_theme_surface', '#fffaf4', 0),
('branding_receipt_footer', 'Merci pour votre achat!', 0),
('branding_receipt_width', '80', 0),
('default_language', 'fr', 0),
('mobile_money_orange_enabled', '0', 0),
('mobile_money_mtn_enabled', '0', 0),
('mobile_money_wave_enabled', '0', 0);