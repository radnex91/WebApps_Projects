-- =============================================
-- ButcheryPOS - Migration 002: Breakdown (Découpe)
-- Adds meat breakdown/cutting feature
-- =============================================

USE butcherypos;

-- 1. Extend movement_type ENUM to include 'breakdown'
ALTER TABLE stock_movements
  MODIFY COLUMN movement_type ENUM('purchase','sale','adjustment','waste','return','breakdown') NOT NULL;

-- 2. Add is_carcass flag to products
ALTER TABLE products
  ADD COLUMN is_carcass TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whole/half animal product, source for breakdown'
  AFTER is_active;

-- 3. Create breakdowns table (header record)
CREATE TABLE breakdowns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(80) NOT NULL UNIQUE,
    source_product_id INT NOT NULL,
    source_batch_id INT NOT NULL,
    source_quantity DECIMAL(12,2) NOT NULL COMMENT 'Weight taken from source batch (kg)',
    source_unit_cost DECIMAL(12,2) NOT NULL COMMENT 'Unit cost of source at time of breakdown',
    total_output_weight DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Sum of all output weights',
    waste_weight DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Weight lost/wasted (kg)',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_breakdown_source_product FOREIGN KEY (source_product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_breakdown_source_batch FOREIGN KEY (source_batch_id) REFERENCES stock_batches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_breakdown_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_breakdown_source_product (source_product_id),
    INDEX idx_breakdown_source_batch (source_batch_id),
    INDEX idx_breakdown_date (created_at)
) ENGINE=InnoDB;

-- 4. Create breakdown_items table (each output cut)
CREATE TABLE breakdown_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    breakdown_id INT NOT NULL,
    output_product_id INT NOT NULL,
    output_batch_id INT DEFAULT NULL COMMENT 'New batch created for this output',
    output_quantity DECIMAL(12,2) NOT NULL COMMENT 'Weight of this cut (kg)',
    output_unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Inherited cost per kg',
    is_byproduct TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Offal, head, tripe, etc.',
    CONSTRAINT fk_bitem_breakdown FOREIGN KEY (breakdown_id) REFERENCES breakdowns(id) ON DELETE CASCADE,
    CONSTRAINT fk_bitem_product FOREIGN KEY (output_product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bitem_batch FOREIGN KEY (output_batch_id) REFERENCES stock_batches(id) ON DELETE SET NULL,
    INDEX idx_bitem_breakdown (breakdown_id),
    INDEX idx_bitem_product (output_product_id)
) ENGINE=InnoDB;

-- 5. RBAC permissions for breakdown module
INSERT INTO role_module_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print, can_manage) VALUES
(1, 'breakdown', 1,1,1,1,1,1),   -- admin: full access
(2, 'breakdown', 1,1,1,0,1,0),   -- manager: view/create/edit/print
(4, 'breakdown', 1,1,0,0,0,0);   -- stock_clerk: view/create only

-- 6. Sample carcass products
INSERT INTO products (name, sku, category_id, unit_id, cost_price, sale_price, quantity_in_stock, reorder_level, track_expiry, default_shelf_life_days, is_active, is_carcass) VALUES
('Demi-porc', 'PORC-CAR-001', 2, 1, 1800, 0, 0, 0, 1, 5, 1, 1),
('Quart de bœuf', 'BOEUF-CAR-001', 1, 1, 2500, 0, 0, 0, 1, 7, 1, 1);