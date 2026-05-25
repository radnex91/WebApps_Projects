# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PharmaCare is a pharmacy management application (POS + inventory + reporting) for the CEMAC/Cameroon market. Built in French — all UI text, code identifiers, and documentation are in French (e.g., `fournisseurs`, `ventes`, `parametres`).

**Stack:** Vanilla PHP 7.4+/8.2 with MySQL/MariaDB on XAMPP. No framework, no Composer, no npm, no build step.

## Setup & Running

1. Copy `pharmacare/` into `C:\xampp\htdocs\`
2. Start XAMPP (Apache + MySQL)
3. Import DB: `mysql -u root pharmacare < database.sql` then `mysql -u root pharmacare < patch_parametres.sql` then `mysql -u root pharmacare < patch_rbac.sql`
4. Open `http://localhost/pharmacare`
5. Demo login: `admin` / `password`, `pharmacien` / `password`, `caissier` / `password`

No build, test, or lint commands exist. There is no automated test suite.

## Architecture

### Request Flow (every page follows this pattern)

1. `require_once` auth.php, layout.php, settings.php
2. `requirePermission('code')` for access control (or `requireLogin()` for public pages)
3. `getDB()` → PDO singleton
4. Handle POST with redirect-after-POST (PRG pattern)
5. Fetch display data
6. `layout_head($title, $activePage)` → renders sidebar, topbar, opens content div
7. Inline HTML/PHP template
8. `layout_foot()` → closes HTML, includes app.js

### Key Files

| Path | Role |
|---|---|
| `config/database.php` | PDO singleton (`getDB()`), DB constants |
| `config/settings.php` | `getParam()`/`getAllParams()` with static cache, `fmtMoney()`, theme/font/currency option arrays |
| `includes/auth.php` | Session mgmt, login/logout, CSRF (`csrf()`/`verifyCsrf()`), RBAC (`hasPermission()`/`requirePermission()`), `e()` (XSS), `fmt()`, `genRef()`, `flash()`/`showFlash()` |
| `includes/layout.php` | `layout_head()`/`layout_foot()`, SVG `icon()` system (~40 named icons), theme CSS variable injection, permission-based sidebar |
| `assets/js/app.js` | POS cart logic, modal open/close, live search, notifications |
| `modules/roles.php` | Role & permission management (admin only) |

### Database

- **11 tables:** `utilisateurs`, `roles`, `permissions`, `role_permissions`, `categories`, `fournisseurs`, `produits`, `ventes`, `vente_lignes`, `commandes`, `mouvements_stock`, `parametres`
- All queries use **prepared statements** with `?` placeholders for user input
- Static queries (no user input) use `$db->query()` directly
- **Soft deletes:** products and suppliers use `actif=0`, users use `actif` toggle

### Auth & RBAC

- **Roles** stored in `roles` table (`id`, `code`, `libelle`, `est_systeme`)
- **Permissions** stored in `permissions` table (`id`, `code`, `libelle`, `module`)
- **Role-Permission mapping** in `role_permissions` pivot table
- `utilisateurs.role_id` → FK to `roles.id`
- 3 system roles (non-deletable): admin, pharmacien, caissier
- Pharmacien: 15 permissions (fournisseurs = view only, no add/modify)
- 25 permissions organized by module (e.g., `produits.voir`, `produits.ajouter`)
- `hasPermission(string $code): bool` — checks session permissions, admin always returns true
- `requirePermission(string $code): void` — redirects to dashboard if no permission
- `isAdmin()` / `isPharmacien()` — deprecated wrappers, use `hasPermission()` instead
- Session stores: `user_role_id`, `user_role_libelle`, `user_permissions` (array of permission codes)
- `refreshUserPermissions()` — reloads permissions from DB into session

### Theme System

- 8 themes (6 dark + 2 light), each with 4 accent colors
- Colors injected as CSS custom properties via PHP in `layout_head()`
- Dim/transparent variants computed by `$dim($hex, $alpha)` closure
- Theme changeable live from the parametres page

### POS (vente.php)

- Two-column: cart panel (left) + product catalog grid (right)
- Cart managed entirely in JavaScript, serialized to JSON in `<input name="cart_data">`
- On POST: server validates cart + prices from DB, DB transaction inserts `ventes` + `vente_lignes`, decrements stock, records stock movement, commits
- 4 payment modes: especes, carte, cheque, assurance

### Order Status Workflow (commandes)

`en_attente` → `en_cours` → `livree` or `annulee`

- "Livrer" button on `en_attente`/`en_cours` orders sets status to `livree` and date_livraison to today
- Delivery validation requires `commandes.modifier` permission
- CSRF token passed via `csrf` query parameter on GET-based delivery action

## Conventions

- All code identifiers and UI in French
- PRG (Post-Redirect-Get) on all form submissions
- Money formatting via `fmtMoney()` which reads currency config from `parametres`
- TVA rate comes from `parametres` table (default `19.25` for Cameroon)
- CSRF protection via `csrf()` / `verifyCsrf()` on all forms
- No dependency manager — all code is standalone PHP/JS/CSS
- Permission codes use dot notation: `module.action` (e.g., `produits.ajouter`, `stock.voir`)

## Migration

- `database.sql` — Fresh install schema (includes RBAC tables)
- `patch_parametres.sql` — Adds `parametres` table to existing DB
- `patch_rbac.sql` — Adds RBAC tables (`roles`, `permissions`, `role_permissions`) and migrates `utilisateurs.role` ENUM to `utilisateurs.role_id` FK