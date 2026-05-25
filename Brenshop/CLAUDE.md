# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Brenshop is a multi-store, multi-warehouse POS (Point of Sale) system for the Cameroonian market. Currency: XAF/FCFA (BEAC), timezone: Africa/Douala, UI language: French, default tax rate: 19.25% (TVA).

## Tech Stack

- **Backend:** Plain PHP 8.0+ (no framework, no Composer, no autoloader)
- **Database:** MySQL/MariaDB via PDO singleton (`Database::getInstance()`)
- **Frontend:** Bootstrap 5.3.2, jQuery 3.7.1, Chart.js 4.4.1 (all CDN-loaded)
- **Server:** Apache via XAMPP — no build step, no package manager, no bundler

## Development Setup

1. Import `database.sql` into MySQL via phpMyAdmin (creates `pos_system` database with schema + seed data)
2. Update DB credentials in `config/config.php` (defaults: host=127.0.0.1, user=root, pass=empty)
3. Access via `http://localhost/pos-system/` (update `BASE_URL` and `.htaccess` `RewriteBase` if your XAMPP alias differs)
4. Seed users (all passwords: `password`): admin@brenshop.cm, manager.douala@brenshop.cm, caisse.douala@brenshop.cm, manager.yaounde@brenshop.cm, caisse.yaounde@brenshop.cm

## Architecture

**MVC-ish pattern without a router:** Direct file-based routing. Controllers use `switch` on `$_GET['action']`.

### Bootstrap flow (every authenticated page)
1. Page includes `includes/bootstrap.php`
2. Bootstrap loads: config → database → helpers → models (manual `require_once` in order)
3. Starts secure session, checks 8-hour expiration
4. Page calls `requireLogin()` and optionally `requireRole()`

### Key layers

| Layer | Files | Notes |
|---|---|---|
| Config | `config/config.php`, `config/database.php` | Constants + PDO singleton |
| Bootstrap | `includes/bootstrap.php` | Manual requires in dependency order — BaseModel must load first |
| Helpers | `includes/helpers.php` | Auth checks, CSRF, formatting, flash messages, pagination, image upload |
| Models | `models/BaseModel.php`, `models/User.php`, `models/models.php`, `models/Sale.php` | `models.php` contains Store, Warehouse, Category, Product, Stock, Customer; `Sale.php` contains Sale + Transfer |
| Controllers | `controllers/auth.php`, `controllers/sale_controller.php`, `controllers/product_ajax.php`, `controllers/stock_ajax.php` | Switch-based routing; AJAX controllers return JSON |
| Views | `views/*.php` | Layout wrapper via `layout_top.php` / `layout_bottom.php`; each view mixes PHP + HTML + inline JS |

### Auth and roles
- Roles: `admin`, `manager`, `cashier`
- Session stores: `user_id`, `user_role`, `store_id`, `warehouse_id`
- `requireRole('admin')` or `requireRole('admin', 'manager')` guards pages
- Admin can switch active store/warehouse via `controllers/auth.php`

### AJAX endpoints
- `controllers/product_ajax.php?action=search_pos` — POS product search (JSON)
- `controllers/product_ajax.php?action=barcode` — barcode lookup (JSON)
- `controllers/stock_ajax.php?action=warehouse_stock` — warehouse stock (JSON)
- `controllers/sale_controller.php` — sale creation (JSON POST)

### Database transactions
Used in `Sale::create()` and `Transfer::complete()` — beginTransaction/commit/rollBack around multi-table writes.

## Known Issues

- **Bootstrap file mismatch:** `bootstrap.php` requires individual model files (`Store.php`, `Warehouse.php`, etc.) that don't exist. The actual classes live in `models/models.php` and `models/Sale.php`. This causes fatal errors on every page load. Fix: either update bootstrap to require the actual files, or split `models.php` into individual files matching bootstrap's expectations.
- **SQL injection risk in `views/sales.php`:** Date filters are interpolated into SQL instead of using parameterized queries.
- **Hardcoded tax rate (fixed):** POS JavaScript now reads `tax_rate` from `$appSettings` dynamically.
- **Unused schema tables:** `activity_logs` and `product_prices` exist in `database.sql` but have no model or view code.
- **BASE_URL mismatch:** Config says `/pos-system/` but the directory is `Brenshop` — update both `config.php` and `.htaccess`.

## Database Schema (13 tables)

`stores` → `warehouses` (by store), `users` (by store), `categories` (by store), `products` (by store), `customers` (by store), `stock` (product × warehouse), `stock_movements` (audit trail), `sales` → `sale_items`, `transfers` → `transfer_items`

Key enums: user role (admin/manager/cashier), stock movement type (in/out/transfer_in/transfer_out/adjustment/sale/return), payment method (cash/mobile_money/orange_money/momo/card/credit/mixed), sale status (completed/pending/cancelled/refunded), transfer status (pending/in_transit/completed/cancelled)