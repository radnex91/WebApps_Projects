# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BrenFinance Suite is a PHP/MySQL financial management application for Cameroonian organizations. It handles cash registers (caisse), bank/treasury operations, financial commitment workflows with multi-level approval, accounting (SYSCOHADA standard), budgets, reporting, and audit logging. The UI is in French; currency is FCFA.

## Running Locally

1. Start Apache and MySQL via XAMPP control panel
2. Import the database: `mysql -u root < sql/schema.sql` (or via phpMyAdmin import)
3. Access at `http://localhost/brenfinance/`
4. Run `setup.php` once in the browser to create the admin account — it also writes `config/database.php` with your DB credentials
5. Delete `setup.php` after setup

**Requirements:** PHP 7.4+ (8.1+ recommended), MySQL 5.7+/MariaDB 10.3+, PHP extensions: PDO, pdo_mysql, json, session. No build step, no test suite, no linter, no Composer/npm. All PHP files are served directly.

## Architecture

**Procedural single-file PHP** — no framework, no MVC, no routing, no template engine. Each module page is a standalone `index.php` that mixes POST handling, DB queries, and HTML output. The only exception is `modules/caisse/create_caisse.php`, a POST-only endpoint that redirects back to `index.php`.

**Common page pattern** (every `modules/*/index.php` follows this):
1. `require_once` includes/functions.php (starts session, loads DB, helpers)
2. `requireLogin()` guard
3. Handle POST actions (CRUD with PDO prepared statements)
4. Fetch data for display
5. `include header.php` (sidebar, topbar, flash messages)
6. HTML with inline PHP for data binding
7. Modal HTML for forms
8. Inline `<script>` for page-specific JS
9. `include footer.php`

**Key files:**
- `config/database.php` — PDO singleton via `getDB()`, credentials as constants. **Written by `setup.php`** — do not hand-edit unless you understand the implications
- `includes/functions.php` — Auth (`requireLogin`, `hasPermission`), `auditLog()`, `flash()`, `sanitize()`, `generateNumero()`, `formatMontant()`, `jsonResponse()`. Also defines `APP_NAME`, `APP_VERSION`, `BASE_PATH`, `BASE_URL` — change `BASE_URL` if the app is not at `/brenfinance`
- `includes/header.php` — Sidebar nav + topbar. Sets `$currentModule` from `$_SERVER['SCRIPT_FILENAME']` for active nav state
- `includes/footer.php` — Mobile bottom nav + `app.js` script tag
- `assets/js/app.js` — Sidebar toggle/collapse, modal open/close, tab switching, table search, `formatMontant()` JS helper, `postJSON()` AJAX helper, live clock, auto-dismiss alerts
- `assets/css/app.css` — All custom styles. Uses CSS custom properties (`:root` block) for theming — colors, spacing, shadows, transitions. Breakpoints: xs<480, sm<640, md<768, lg<1024, xl<1280+
- `sql/schema.sql` — Full DDL + seed data (single source of truth for DB schema). Includes roles, operation types, payment modes, journals, chart of accounts, and default company/agency/service

**Database:** PDO with prepared statements throughout. Singleton pattern in `getDB()`. Tables use French naming (`utilisateurs`, `operations_caisse`, etc.). All monetary columns are `DECIMAL(15,2)`.

**Auth:** Session-based (`$_SESSION['user_id']`, `$_SESSION['user']`). Role permissions stored as JSON in the `roles.permissions` column. `hasPermission($module, $action)` checks the tree: `{"all": true}` grants full access; per-module grants like `{"caisse": {"saisir": true}}`. Roles: `super_admin`, `daf`, `comptable`, `caissier`, `demandeur`, `valideur_n1`.

**Engagement workflow:** 5-status validation pipeline: `brouillon → soumis → valide_hierarchie → valide_comptable → valide_daf/approuve → execute`. Rejections go to `rejete`. Each step is recorded in `validations_engagement` with the validator's identity, action, and comment.

**Number generation:** `generateNumero($prefix)` produces document numbers like `ENG-2025-04201` using `mt_rand` — not sequential or unique-guaranteed. The `numero` column has a UNIQUE constraint so duplicates will throw a PDOException.

## Request flow

- Login: `index.php` handles POST, verifies credentials with `password_verify()`, stores user row in `$_SESSION['user']`, redirects to `dashboard.php`
- Module pages: POST actions use `header('Location: index.php'); exit;` (PRG pattern) with `flash()` for status messages
- Logout: `logout.php` logs the event via `auditLog()`, destroys session, redirects to login

## Conventions

- All UI text is in French
- Currency formatted as `number_format($amount, 0, ',', ' ') . ' FCFA'` (PHP) or `Intl.NumberFormat('fr-CM')` (JS)
- DB credentials and `BASE_URL` are PHP constants (no `.env` file)
- Every significant action is logged via `auditLog()` — writes to `journal_audit` with user ID, action, module, table, record ID, old/new values (JSON), IP, user agent
- Output escaping uses `sanitize()` (wraps `htmlspecialchars` with `ENT_QUOTES` and UTF-8)
- Password hashing: `PASSWORD_BCRYPT` with cost 12 (in `setup.php`)
- No CSRF token mechanism exists
- All POST handlers follow the PRG (Post-Redirect-Get) pattern — process, `flash()`, `header('Location: ...')`, `exit`