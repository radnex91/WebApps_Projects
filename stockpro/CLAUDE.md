# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

StockPro is a PHP/MySQL inventory management application (French language UI) running on XAMPP. It provides CRUD for products, categories, suppliers, clients, purchases, stock movements, physical inventory, user management, and reporting — all with role-based access control.

## Architecture

**Traditional PHP multi-page app** — no framework, no build step, no npm. Each page in `pages/` is a standalone PHP file that handles both GET (display) and POST (action) requests. Business logic is inline, not separated into controllers or services.

**Key includes:**
- `includes/config.php` — DB connection (PDO singleton via `getDB()`), session/auth (`requireAuth()`, `canDo()`), CSRF protection (`csrf_field()`, `csrf_verify()`), input validation (`clean()`, `validateString()`, `validateEmail()`, `validatePhone()`, `validateInt()`, `validateFloat()`, `validateColor()`), audit logging (`logAudit()`), `formatMoney()`, `movementBadge()`, `paginate()` and `paginationHtml()`.
- `includes/header.php` — Full layout shell: sidebar nav, topbar, CSS variables driven by `entreprise` table (primary/secondary colors, body/heading fonts, font size), theme/font/size preference panel (persisted in localStorage). Each page sets `$page_title` and `$page_id` before including this file. All inline CSS (~400 lines) and appearance JS live here.
- `includes/footer.php` — Closing HTML + shared JS (toast notifications via `showToast(message, type)`, modal helpers `openModal(id)`/`closeModal(id)`, keyboard shortcuts).

**Entry point:** `index.php` — login page with IP-based rate limiting (5 attempts/15 min). Redirects to dashboard if session exists.

**Logout:** `logout.php` — destroys session and redirects.

**Database:** MySQL via PDO, configured in `config.php` constants (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`). Schema lives in `setup.sql` (initial), `migration_security.sql` (rate limiting, audit log, indexes, multi-depot/sales/clients tables), `migration_improvements.sql` (soft delete on categories, unique indexes, heading font column), and `migrate_quincaillerie.sql` (realistic hardware store data replacing demo data).

**Auth & permissions:** 5-role system (super_admin, admin, gestionnaire, caissier, lecteur). `canDo()` checks a hardcoded permission map with wildcard support (e.g., `produit_*`). Session-stored user object in `$_SESSION['user']`. Login has IP-based rate limiting via `login_attempts` table.

**Page pattern:** Every page follows this structure:
```php
$page_title = 'Title';
$page_id = 'page_id';
require_once '../includes/header.php';
requireAuth();
// Optional: permission check with canDo()
$db = getDB();
// POST handling with csrf_verify(), queries, HTML rendering
require_once '../includes/footer.php';
```

POST handling always uses `csrf_verify()` and checks `canDo()` before mutations. Soft deletes set `actif=0` instead of actual DELETE.

**UI system:** CSS custom properties (`--primary`, `--bg`, `--card`, etc.) with light/dark/auto theme variants toggled via `data-theme` attribute on `<html>`. 9 body font choices via `data-font`. 7 heading font choices via `data-heading`. Font size slider via `html.style.fontSize`. All preference state in localStorage (`sp_theme`, `sp_font`, `sp_heading`, `sp_size`). Modals use `.modal-bg.open` toggle pattern. Print styles hide sidebar/topbar and format for A4.

**JS (inline in header/footer):** No external JS files. Feather Icons loaded via CDN for sidebar icons. All interaction JS lives in `header.php` (appearance panel) and `footer.php` (toasts, modals, keyboard shortcuts like `N` for new product, `M` for movement, `D` for dashboard, `Ctrl+K` for search focus, `Escape` to close modals).

## Database Setup

1. Start Apache & MySQL in XAMPP Control Panel
2. Create a `stockpro` database in phpMyAdmin
3. Import `setup.sql` first, then `migration_security.sql`, then `migration_improvements.sql` (optional: `migrate_quincaillerie.sql` for realistic hardware store data)
4. Default login: `admin@stockpro.com` / `password` (bcrypt hashed). Additional accounts: `gestionnaire@stockpro.com` / `password`, `caissier@stockpro.com` / `password`

## Key Conventions

- All UI text is in French
- Soft deletes: products, suppliers, and clients use `actif=0/1` flag, not actual DELETE. Categories also use soft delete (added in `migration_improvements.sql`)
- Money formatting uses `formatMoney()` which reads the currency from `entreprise.devise` (default: FCFA)
- CSRF token is required on all POST forms via `csrf_field()`, verified by `csrf_verify()`
- Passwords hashed with `password_hash()` (bcrypt), verified with `password_verify()`
- Products use a `reference` field (unique string identifier like `INFO-001`, `PLM-001`)
- Stock movements track `quantite_avant` and `quantite_apres` for audit trail
- The `entreprise` table is single-row (always `LIMIT 1`) storing global settings: name, slogan, logo, colors, currency, fonts, font size
- Audit logging via `logAudit($db, $action, $entity, $entity_id, $details)` — action is lowercase string like `create`, `update`, `delete`, `login`; entity is table name like `produit`; details is optional associative array JSON-encoded
- Pagination uses `paginate($total, $per_page, $current_page)` and `paginationHtml($pg, $base_url, $params)`
- Stock-level operations (purchases/entries) use `SELECT ... FOR UPDATE` within transactions to prevent race conditions

## Permission Map

| Role | Permissions |
|------|------------|
| super_admin | `*` (everything) |
| admin | `produit_*`, `categorie_*`, `fournisseur_*`, `mouvement_*`, `user_view`, `rapport_*` |
| gestionnaire | `produit_*`, `categorie_view`, `fournisseur_view`, `mouvement_*` |
| caissier | `produit_view`, `mouvement_sortie` |
| lecteur | `produit_view`, `categorie_view` |

## Database Tables

Core: `entreprise`, `utilisateurs`, `categories`, `fournisseurs`, `produits`, `mouvements`, `alertes`

Extended (from migrations): `login_attempts`, `audit_log`, `depots`, `stocks_depot`, `transferts`, `clients`, `ventes`, `ligne_vente`

Products table has optional `date_expiration` and `code_barres` columns (added in `migration_security.sql`). Products also have `prix_achat` and `prix_vente` for margin tracking.

## File Structure

- `index.php` — Login page (standalone, not using header/footer includes)
- `logout.php` — Session destruction
- `.htaccess` — Disables directory listing, hides `.sql`/`.md` files, sets upload limits
- `includes/config.php` — All shared functions and DB config
- `includes/header.php` — Layout shell, CSS, sidebar, topbar, appearance panel
- `includes/footer.php` — Closing HTML, JS (toasts, modals, keyboard shortcuts)
- `pages/dashboard.php` — Dashboard with activity chart
- `pages/produits.php` — Products CRUD with search/filter/pagination
- `pages/mouvements.php` — Stock movements (entries, exits, adjustments, returns)
- `pages/categories.php` — Categories with color icons
- `pages/fournisseurs.php` — Suppliers
- `pages/achats.php` — Purchase entries (stock-in with supplier and document reference)
- `pages/clients.php` — Clients CRUD
- `pages/inventaire.php` — Physical inventory with gap calculation
- `pages/rapports.php` — Reports and statistics
- `pages/exports.php` — CSV export and printable stock state
- `pages/utilisateurs.php` — User management
- `pages/profil.php` — User profile (edit info + change password)
- `pages/parametres.php` — Company settings (logo, colors, currency, fonts)
- `uploads/logos/` — Uploaded company logos