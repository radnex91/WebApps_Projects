# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

DanayLedger is a PHP/MySQL financial management web application for **DANAY EXPRESS SARL**, a logistics/transport company. The entire UI is in **French**. It runs on XAMPP (Apache + MySQL) with no framework — pure procedural PHP with Bootstrap 5.3.

## Development Environment

- **Web server**: XAMPP (Apache) at `http://localhost/DanayLedger`
- **Database**: MySQL via `localhost`, database `danay_ledger`, user `root` (no password)
- **No build tools, no npm, no composer, no tests** — PHP files are served directly
- **No git repository** — changes are made directly on filesystem
- Database can be reset via `install/setup.php` (creates all tables and a default admin user)

## Architecture

### Request Flow

Every protected page follows this pattern:
1. `includes/header.php` — starts session, calls `requireLogin()`, sets `$currentUser` and `$currentPage`
2. `includes/sidebar.php` — renders navigation filtered by user permissions
3. Page logic (CRUD operations, permission checks via `requirePermission()`)
4. `includes/footer.php` — closes layout, loads Bootstrap/Chart.js/app.js

### Key Files

| File | Purpose |
|------|---------|
| `config/database.php` | PDO singleton (`getDB()`), connection constants |
| `config/constants.php` | Roles, permissions, statuses, categories, upload limits |
| `includes/auth.php` | Session management, login/logout, `hasPermission()`, `addAuditLog()`, `addNotification()` |
| `includes/functions.php` | CSRF tokens, flash messages, `formatMoney()`, `paginate()`, `uploadJustificatif()`, `exportCSV()`, dashboard stats |
| `middleware/role_check.php` | `requireRole()`, permission helper functions |
| `assets/js/app.js` | Sidebar toggle, auto-hide alerts, client-side CSV export, number animation, form validation |
| `assets/css/style.css` | Custom CSS variables, sidebar layout, login page, card styles |

### Module Structure

Each feature is a directory under the root containing CRUD pages:
- `recettes/` — Daily revenue (recettes journalières)
- `depenses/` — Expenses
- `recettes-camions/` — Truck revenue
- `versements/` — Bank payments (versements bancaires)
- `settings/` — Agences, categories, operation types, vehicles, system params
- `users/` — User management and profiles
- `rapports/` — Reports
- `rapprochement/` — Reconciliation
- `recherche/` — Search
- `audit/` — Audit log viewer
- `sauvegarde/` — Database backup
- `imports/` / `exports/` — Data import/export
- `justificatifs/` — Receipt/document uploads
- `impression/` — Print views

Empty directories (`clients/`, `drivers/`, `vehicles/`, `transactions/`) are placeholders.

### Database Tables

Core tables: `users`, `login_attempts`, `recettes_journalieres`, `depenses`, `recettes_camions`, `versements_bancaires`, `agences`, `categories`, `types_operations`, `vehicules`, `parametres`, `audit_logs`, `notifications`, `justificatifs`

### Permission System

Three roles defined in `config/constants.php`:
- **admin** — Full access (CRUD + validate + delete + settings + audit + backup)
- **utilisateur** — Create/edit own records, no delete/validate/settings
- **visiteur** — Read-only access

Each module has granular permissions (e.g., `recettes_create`, `depenses_validate`). Permission checks use `requirePermission()` at page top and `hasPermission()` in templates.

### Status Workflow

Records follow: `en_attente` → `validee` or `annulee`. Only admin can validate or cancel.

## Coding Conventions

- All PHP files use procedural style — no classes except the PDO singleton
- Prepared statements (`$db->prepare()->execute()`) for all SQL queries — never concatenate user input
- `e()` function for HTML escaping (wraps `htmlspecialchars`)
- `cleanInput()` for POST data sanitization
- CSRF token on every form: `csrfField()` in HTML, `verifyCSRFToken()` on POST
- Money formatted in FCFA (XOF) using `formatMoney()`
- References use `generateReference()` with prefix (RJR for recettes, DEP for dépenses, etc.)
- Flash messages via `setFlash()` / `getFlash()` with redirect pattern (`redirectWithMessage()`)
- Audit logging via `addAuditLog()` on all create/update/delete/validate actions