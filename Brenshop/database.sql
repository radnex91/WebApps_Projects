SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
CREATE DATABASE IF NOT EXISTS `pos_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos_system`;

-- ============================================================
-- Base tables first (no foreign keys)
-- ============================================================

CREATE TABLE `stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL DEFAULT '',
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'FCFA',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Tables depending on stores
-- ============================================================

CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_id` (`store_id`),
  CONSTRAINT `fk_warehouse_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
  `permissions` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_store_id` (`store_id`),
  CONSTRAINT `fk_user_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Activity logs (no FK constraints, only indexes)
-- ============================================================

CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `model_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Business tables
-- ============================================================

CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT 'box',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_id` (`store_id`),
  CONSTRAINT `fk_category_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `total_purchases` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_phone` (`phone`),
  CONSTRAINT `fk_customer_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `cost_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(30) DEFAULT 'pcs',
  `min_stock_alert` int(11) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barcode_store` (`barcode`,`store_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_category_id` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_product_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_warehouse` (`product_id`,`warehouse_id`),
  KEY `idx_warehouse_id` (`warehouse_id`),
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method` enum('cash','mobile_money','orange_money','momo','card','credit','mixed') NOT NULL DEFAULT 'cash',
  `status` enum('completed','pending','cancelled','refunded') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice` (`invoice_number`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_warehouse_id` (`warehouse_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_sale_date` (`sale_date`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sale_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sale_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `cost_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `from_warehouse_id` int(10) unsigned NOT NULL,
  `to_warehouse_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`reference`),
  KEY `idx_from_warehouse` (`from_warehouse_id`),
  KEY `idx_to_warehouse` (`to_warehouse_id`),
  KEY `fk_transfer_user` (`user_id`),
  CONSTRAINT `fk_transfer_from` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfer_to` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity_requested` decimal(15,3) NOT NULL,
  `quantity_transferred` decimal(15,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_titem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_titem_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('in','out','transfer_in','transfer_out','adjustment','sale','return') NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `quantity_before` decimal(15,3) NOT NULL DEFAULT 0.000,
  `quantity_after` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_warehouse_id` (`warehouse_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_movement_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_movement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_movement_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Remaining tables
-- ============================================================

CREATE TABLE `settings` (
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `product_prices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `selling_price` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_store` (`product_id`,`store_id`),
  KEY `fk_price_store` (`store_id`),
  CONSTRAINT `fk_price_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_price_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_store` (`user_id`, `store_id`),
  KEY `idx_us_user` (`user_id`),
  KEY `idx_us_store` (`store_id`),
  CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_us_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_warehouse` (`user_id`, `warehouse_id`),
  KEY `idx_uw_user` (`user_id`),
  KEY `idx_uw_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_uw_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uw_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Seed data
-- ============================================================

INSERT INTO `stores` (`id`, `name`, `code`, `address`, `phone`, `email`, `logo`, `currency`, `tax_rate`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'BrenShop Informatique Douala', 'BID', 'Rue Joss, Bonapriso, Douala', '+237 699 123 456', 'douala@brenshop.cm', NULL, 'FCFA', '19.25', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('2', 'BrenShop Informatique Yaoundé', 'BIY', 'Boulevard de la République, Yaoundé', '+237 677 987 654', 'yaounde@brenshop.cm', NULL, 'FCFA', '19.25', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28');

INSERT INTO `warehouses` (`id`, `store_id`, `name`, `address`, `phone`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'Dépôt Principal Douala', 'Zone Industrielle Douala Bassa', NULL, '1', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('2', '1', 'Stock Boutique Douala Akwa', 'Rue Joss, Akwa, Douala', NULL, '0', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('3', '2', 'Dépôt Yaoundé', 'Zone Commerciale Yaoundé', NULL, '1', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('4', '2', 'Stock Boutique Yaoundé Bastos', 'Quartier Bastos, Yaoundé', NULL, '0', '1', '2026-04-24 01:20:28', '2026-04-24 01:20:28');

INSERT INTO `users` (`id`, `store_id`, `warehouse_id`, `name`, `email`, `password`, `role`, `phone`, `avatar`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
('1', '1', '1', 'Paul Fotso', 'admin@brenshop.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, '1', NULL, '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('2', '1', '2', 'Marthe Biyong', 'manager.douala@brenshop.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL, NULL, '1', NULL, '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('3', '1', '2', 'Estelle Nganou', 'caisse.douala@brenshop.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', NULL, NULL, '1', NULL, '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('4', '2', '3', 'Alain Ngassa', 'manager.yaounde@brenshop.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL, NULL, '1', NULL, '2026-04-24 01:20:28', '2026-04-24 01:20:28'),
('5', '2', '3', 'Chloé Mballa', 'caisse.yaounde@brenshop.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', NULL, NULL, '1', NULL, '2026-04-24 01:20:28', '2026-04-24 01:20:28');

INSERT INTO `user_stores` (`user_id`, `store_id`) VALUES
(1, 1), (1, 2),
(2, 1),
(3, 1),
(4, 2),
(5, 2);

INSERT INTO `user_warehouses` (`user_id`, `warehouse_id`) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 2),
(3, 2),
(4, 3),
(5, 3);

INSERT INTO `categories` (`id`, `store_id`, `name`, `description`, `color`, `icon`, `is_active`, `created_at`) VALUES
('1', '1', 'Ordinateurs', 'PC portables et de bureau', '#3B82F6', 'laptop', '1', '2026-04-24 01:20:29'),
('2', '1', 'Accessoires', 'Souris, claviers, sacs et supports', '#10B981', 'mouse', '1', '2026-04-24 01:20:29'),
('3', '1', 'Réseau & Connectique', 'Câbles, routeurs et switchs', '#8B5CF6', 'wifi', '1', '2026-04-24 01:20:29'),
('4', '1', 'Périphériques', 'Écrans, imprimantes, webcams', '#F59E0B', 'printer', '1', '2026-04-24 01:20:29'),
('5', '1', 'Stockage', 'Disques durs, clés USB, cartes mémoire', '#EC4899', 'hard-drive', '1', '2026-04-24 01:20:29'),
('6', '2', 'Ordinateurs', 'PC portables et de bureau', '#3B82F6', 'laptop', '1', '2026-04-24 01:20:29'),
('7', '2', 'Accessoires', 'Souris, claviers, sacs et supports', '#10B981', 'mouse', '1', '2026-04-24 01:20:29'),
('8', '2', 'Réseau & Connectique', 'Câbles, routeurs et switchs', '#8B5CF6', 'wifi', '1', '2026-04-24 01:20:29');

INSERT INTO `products` (`id`, `store_id`, `category_id`, `name`, `barcode`, `sku`, `description`, `image`, `cost_price`, `selling_price`, `unit`, `min_stock_alert`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', '1', 'HP Laptop 15s Ryzen 5 8Go/256Go', '0195428212345', 'ORD-001', 'PC portable HP 15.6 pouces écran Full HD', NULL, '175000.00', '225000.00', 'pcs', '3', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('2', '1', '1', 'Dell Latitude 3440 i5 8Go/256Go', '0884116453210', 'ORD-002', 'PC portable professionnel Dell', NULL, '250000.00', '320000.00', 'pcs', '2', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('3', '1', '1', 'Lenovo IdeaPad 3 i3 4Go/128Go', '0193745678901', 'ORD-003', 'PC portable entrée de gamme', NULL, '135000.00', '175000.00', 'pcs', '4', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('4', '1', '2', 'Souris sans fil Logitech M170', '0978554321098', 'ACC-001', 'Souris optique sans fil USB', NULL, '5000.00', '9500.00', 'pcs', '10', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('5', '1', '2', 'Clavier USB Logitech K120', '0978554789012', 'ACC-002', 'Clavier filaire azerty', NULL, '3500.00', '7000.00', 'pcs', '8', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('6', '1', '2', 'Sac à dos laptop 15.6 pouces', '0698432109876', 'ACC-003', 'Sac rembourré pour ordinateur portable', NULL, '7000.00', '14000.00', 'pcs', '6', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('7', '1', '2', 'Refroidisseur laptop USB', '0698432543210', 'ACC-004', 'Support refroidissement avec ventilateurs', NULL, '4500.00', '9000.00', 'pcs', '5', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('8', '1', '3', 'Câble HDMI 2m', '4549292109876', 'RES-001', 'Câble HDMI haute vitesse 2m', NULL, '2500.00', '5000.00', 'pcs', '20', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('9', '1', '3', 'Routeur WiFi TP-Link Archer C6', '6934177765432', 'RES-002', 'Routeur double bande AC1200', NULL, '12000.00', '22000.00', 'pcs', '5', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('10', '1', '3', 'Câble RJ45 Cat6 5m', '4549292567890', 'RES-003', 'Câble réseau Cat6 blindé', NULL, '1500.00', '3500.00', 'pcs', '25', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('11', '1', '3', 'Switch 5 ports TP-Link', '6934177123456', 'RES-004', 'Switch Ethernet 5 ports 10/100Mbps', NULL, '8000.00', '15000.00', 'pcs', '4', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('12', '1', '4', 'Écran LG 24 pouces Full HD', '0198765432109', 'PER-001', 'Moniteur LED 24 pouces IPS', NULL, '65000.00', '95000.00', 'pcs', '3', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('13', '1', '4', 'Imprimante HP DeskJet 2710', '0195428765432', 'PER-002', 'Imprimante multifonction WiFi', NULL, '35000.00', '55000.00', 'pcs', '2', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('14', '1', '4', 'Webcam Logitech C270 HD', '0978554234567', 'PER-003', 'Webcam HD 720p avec micro', NULL, '8000.00', '15000.00', 'pcs', '6', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('15', '1', '4', 'Cartouche d\'encre HP 305 noire', '0195428987654', 'PER-004', 'Cartouche d\'encre noire originale', NULL, '8000.00', '14000.00', 'pcs', '10', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('16', '1', '5', 'Clé USB 64Go SanDisk', '6196591234567', 'STK-001', 'Clé USB 3.0 64Go', NULL, '3000.00', '6000.00', 'pcs', '15', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('17', '1', '5', 'Disque dur externe 1To Seagate', '7196591234567', 'STK-002', 'Disque dur externe USB 3.0 1To', NULL, '28000.00', '45000.00', 'pcs', '4', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('18', '1', '5', 'Carte mémoire MicroSD 32Go', '6196591987654', 'STK-003', 'MicroSD avec adaptateur SD', NULL, '2000.00', '4000.00', 'pcs', '20', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('19', '2', '6', 'HP Laptop 15s Ryzen 5 8Go/256Go', '0195428212350', 'ORD-T01', 'PC portable HP 15.6 pouces écran Full HD', NULL, '175000.00', '225000.00', 'pcs', '2', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('20', '2', '6', 'Lenovo IdeaPad 3 i3 4Go/128Go', '0193745678960', 'ORD-T02', 'PC portable entrée de gamme', NULL, '135000.00', '175000.00', 'pcs', '3', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('21', '2', '7', 'Souris sans fil Logitech M170', '0978554321188', 'ACC-T01', 'Souris optique sans fil USB', NULL, '5000.00', '9500.00', 'pcs', '8', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('22', '2', '7', 'Clavier USB Logitech K120', '0978554789123', 'ACC-T02', 'Clavier filaire azerty', NULL, '3500.00', '7000.00', 'pcs', '6', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('23', '2', '8', 'Câble HDMI 2m', '4549292109987', 'RES-T01', 'Câble HDMI haute vitesse 2m', NULL, '2500.00', '5000.00', 'pcs', '15', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('24', '2', '8', 'Routeur WiFi TP-Link Archer C6', '6934177765544', 'RES-T02', 'Routeur double bande AC1200', NULL, '12000.00', '22000.00', 'pcs', '3', '1', '2026-04-24 01:20:29', '2026-04-24 01:20:29');

INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`, `updated_at`) VALUES
('1', '1', '1', '12.000', '2026-04-24 01:20:29'),
('2', '2', '1', '6.000', '2026-04-24 01:20:29'),
('3', '3', '1', '15.000', '2026-04-24 01:20:29'),
('4', '4', '1', '45.000', '2026-04-24 01:20:29'),
('5', '5', '1', '35.000', '2026-04-24 01:20:29'),
('6', '6', '1', '20.000', '2026-04-24 01:20:29'),
('7', '7', '1', '18.000', '2026-04-24 01:20:29'),
('8', '8', '1', '80.000', '2026-04-24 01:20:29'),
('9', '9', '1', '10.000', '2026-04-24 01:20:29'),
('10', '10', '1', '100.000', '2026-04-24 01:20:29'),
('11', '11', '1', '8.000', '2026-04-24 01:20:29'),
('12', '12', '1', '7.000', '2026-04-24 01:20:29'),
('13', '13', '1', '5.000', '2026-04-24 01:20:29'),
('14', '14', '1', '25.000', '2026-04-24 01:20:29'),
('15', '15', '1', '40.000', '2026-04-24 01:20:29'),
('16', '16', '1', '60.000', '2026-04-24 01:20:29'),
('17', '17', '1', '10.000', '2026-04-24 01:20:29'),
('18', '18', '1', '80.000', '2026-04-24 01:20:29'),
('19', '1', '2', '3.000', '2026-04-24 01:20:29'),
('20', '2', '2', '2.000', '2026-04-24 01:20:29'),
('21', '3', '2', '5.000', '2026-04-24 01:20:29'),
('22', '4', '2', '15.000', '2026-04-24 01:20:29'),
('23', '5', '2', '12.000', '2026-04-24 01:20:29'),
('24', '6', '2', '8.000', '2026-04-24 01:20:29'),
('25', '7', '2', '6.000', '2026-04-24 01:20:29'),
('26', '8', '2', '30.000', '2026-04-24 01:20:29'),
('27', '9', '2', '3.000', '2026-04-24 01:20:29'),
('28', '10', '2', '25.000', '2026-04-24 01:20:29'),
('29', '11', '2', '2.000', '2026-04-24 01:20:29'),
('30', '12', '2', '2.000', '2026-04-24 01:20:29'),
('31', '13', '2', '2.000', '2026-04-24 01:20:29'),
('32', '14', '2', '10.000', '2026-04-24 01:20:29'),
('33', '15', '2', '15.000', '2026-04-24 01:20:29'),
('34', '16', '2', '20.000', '2026-04-24 01:20:29'),
('35', '17', '2', '3.000', '2026-04-24 01:20:29'),
('36', '18', '2', '30.000', '2026-04-24 01:20:29'),
('37', '19', '3', '8.000', '2026-04-24 01:20:29'),
('38', '20', '3', '10.000', '2026-04-24 01:20:29'),
('39', '21', '3', '30.000', '2026-04-24 01:20:29'),
('40', '22', '3', '20.000', '2026-04-24 01:20:29'),
('41', '23', '3', '50.000', '2026-04-24 01:20:29'),
('42', '24', '3', '5.000', '2026-04-24 01:20:29'),
('43', '19', '4', '3.000', '2026-04-24 01:20:29'),
('44', '20', '4', '4.000', '2026-04-24 01:20:29'),
('45', '21', '4', '10.000', '2026-04-24 01:20:29'),
('46', '22', '4', '8.000', '2026-04-24 01:20:29'),
('47', '23', '4', '20.000', '2026-04-24 01:20:29'),
('48', '24', '4', '2.000', '2026-04-24 01:20:29');

INSERT INTO `customers` (`id`, `store_id`, `name`, `phone`, `email`, `address`, `loyalty_points`, `total_purchases`, `notes`, `created_at`, `updated_at`) VALUES
('1', '1', 'Ousmane Diop', '+221 77 123 4567', 'ousmane@email.sn', NULL, '0', '0.00', NULL, '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('2', '1', 'Mariama Fall', '+221 78 987 6543', 'mariama.fall@email.sn', NULL, '0', '0.00', NULL, '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('3', '1', 'Client anonyme', NULL, NULL, NULL, '0', '0.00', NULL, '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('4', '2', 'Moussa Ndiaye', '+221 76 456 7890', NULL, NULL, '0', '0.00', NULL, '2026-04-24 01:20:29', '2026-04-24 01:20:29'),
('5', '2', 'Coumba Sy', '+221 77 234 5678', 'coumba.sy@email.sn', NULL, '0', '0.00', NULL, '2026-04-24 01:20:29', '2026-04-24 01:20:29');

INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES
('accent2_color', '#10B981', '2026-04-24 01:20:29'),
('app_logo', NULL, '2026-04-24 01:20:29'),
('app_name', 'BrenShop Informatique', '2026-04-24 01:20:29'),
('app_subtitle', 'Gestion Commerciale', '2026-04-24 01:20:29'),
('body_font', 'DM Sans', '2026-04-24 01:20:29'),
('currency_code', 'XAF', '2026-04-24 01:20:29'),
('currency_name', 'Franc CFA BEAC', '2026-04-24 01:20:29'),
('currency_symbol', 'FCFA', '2026-04-24 01:20:29'),
('heading_font', 'Syne', '2026-04-24 01:20:29'),
('primary_color', '#6366F1', '2026-04-24 01:20:29'),
('receipt_footer', 'Merci pour votre achat chez BrenShop Informatique !', '2026-04-24 01:20:29'),
('sidebar_bg', '#0F172A', '2026-04-24 01:20:29'),
('tax_rate', '19.25', '2026-04-24 01:20:29'),
('timezone', 'Africa/Douala', '2026-04-24 01:20:29');

-- ============================================================
-- Caisses (Cash Registers)
-- ============================================================

CREATE TABLE IF NOT EXISTS `caisses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caisse_store` (`store_id`),
  CONSTRAINT `fk_caisse_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add caisse_id to sales
ALTER TABLE `sales`
  ADD COLUMN `caisse_id` int(10) unsigned DEFAULT NULL AFTER `warehouse_id`,
  ADD KEY `idx_sale_caisse` (`caisse_id`),
  ADD CONSTRAINT `fk_sale_caisse` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`) ON DELETE SET NULL;

INSERT INTO `caisses` (`id`, `store_id`, `name`, `is_active`) VALUES
(1, 1, 'Caisse Principale Douala', 1),
(2, 1, 'Caisse Secondaire Douala', 1),
(3, 2, 'Caisse Principale Yaoundé', 1),
(4, 2, 'Caisse Secondaire Yaoundé', 1);

-- ============================================================
-- Caisse Sessions (Cash Register Sessions)
-- ============================================================

CREATE TABLE IF NOT EXISTS `caisse_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `caisse_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `closing_balance_expected` decimal(15,2) DEFAULT NULL,
  `closing_balance_actual` decimal(15,2) DEFAULT NULL,
  `closing_discrepancy` decimal(15,2) DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closing_time` timestamp NULL DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caisse_id` (`caisse_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_session_caisse` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_session_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_session_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `caisse_operations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_operation_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_operation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `sales`
  ADD COLUMN `session_id` int(10) unsigned DEFAULT NULL AFTER `caisse_id`,
  ADD KEY `idx_sale_session` (`session_id`),
  ADD CONSTRAINT `fk_sale_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE SET NULL;
