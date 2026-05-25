-- ============================================
-- StockPro - Migration: Improvements
-- ============================================

-- 1. Add actif column to categories for soft delete
ALTER TABLE categories ADD COLUMN actif TINYINT(1) DEFAULT 1 AFTER couleur;

-- 2. Add unique constraint on utilisateurs email
ALTER TABLE utilisateurs ADD UNIQUE INDEX idx_unique_email (email);

-- 3. Add unique constraint on clients email
ALTER TABLE clients ADD UNIQUE INDEX idx_unique_email (email);

-- 4. Add updated_at to categories
ALTER TABLE categories ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 5. Add updated_at to fournisseurs
ALTER TABLE fournisseurs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 6. Add updated_at to clients
ALTER TABLE clients ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 7. Add updated_at to utilisateurs
ALTER TABLE utilisateurs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 8. Add heading font column to entreprise
ALTER TABLE entreprise ADD COLUMN police_titres VARCHAR(20) DEFAULT 'syne' AFTER police;