# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TransportManager is a transport agency management system (French UI) built with procedural PHP/MySQL on XAMPP. No frameworks, no build tools, no package managers, no test suites.

## Development Environment

- **Stack:** PHP 7.4+, MySQL 5.7+, XAMPP Apache
- **Access:** `http://localhost/SystracoPro/`
- **Database:** `transport_db` (utf8mb4) — import `database.sql` via phpMyAdmin
- **DB credentials:** hardcoded in `includes/config.php` (root / empty password)
- **No CLI commands** — no composer, npm, test runner, or linter. All development is file-edit-and-refresh.

## Architecture

**Procedural PHP with file-based routing.** Every `.php` file is directly accessible via URL. No front controller, no router, no MVC.

### Request flow
1. Every page includes `includes/config.php` → starts session, creates `$pdo` (PDO), loads auth/format helpers
2. Calls `requireLogin()` and optionally `requirePerm('code')`
3. Business logic + inline SQL (PDO prepared statements)
4. Includes `includes/header.php` (sidebar nav, topbar, flash messages) and `includes/footer.php` (closing tags, JS)

### Key files
- `includes/config.php` — DB connection, session auth, RBAC helpers (`can()`, `hasRole()`, `requirePerm()`), formatting (`money()`, `fdate()`), flash messages, `genNumero()` (sequential ID generation like `TKT-20250414-0001`), `logAction()`, `addNotif()`, CSRF protection (`csrfToken()`, `csrfField()`, `csrfCheck()`), caisse helpers (`caisseOuverte()`, `requireCaisseOuverte()`)
- `includes/header.php` — sidebar navigation (module links), topbar (user info, notifications), flash display
- `database.sql` — full schema (22 tables) + seed data (6 demo accounts, 6 agencies, 25 permissions)

### Multi-tenancy
Data is scoped by `agence_id`. Admin-level roles see all agencies; others see only their own. Enforced via `agenceFilter()` and `ticketAgenceFilter()` which return SQL WHERE fragments.

### RBAC
6 roles (super_admin > admin > chef_agence > chef_guichet > guichetier > operateur) with 25 granular permissions in a many-to-many mapping (`role_permissions` table). Permissions checked via `can('permission_code')`.

### Module structure
Each module lives in `modules/{name}/` with one file per action (e.g., `vente.php`, `liste.php`, `imprimer.php`). Cross-cutting modules (`notifications.php`, `export.php`, `sauvegarde.php`) are directly in `modules/`.

### Workflow states
- Tickets: vendu → annule/utilise/reserve
- Voyages: programme → en_cours → arrive/annule/reporte
- Bordereaux: genere → transmis → saisi → valide → cloture
- Expenses: en_attente → approuve/rejete → paye

### Number generation
`genNumero($pdo, $table, $column, $prefix)` generates IDs like `{PREFIX}-{YYYYMMDD}-{0001}`. Used for tickets (TKT), bordereaux (BDR), voyages (VOY), reservations (RES), versements (VRS), depenses (DEP).

## Conventions

- **Language:** All UI text and comments are in French
- **Currency:** FCFA (configurable via `parametres` table)
- **Payment modes:** especes (cash), om (Orange Money), momo (MTN MoMo) — carte and cheque removed from ticket sales
- **Password hashing:** bcrypt via `password_hash()` / `password_verify()`
- **XSS prevention:** `sanitize()` (htmlspecialchars wrapper) used for output
- **Transactions:** `beginTransaction()`/`commit()`/`rollBack()` for multi-step operations (ticket sales, bordereau generation)
- **Print views:** CSS `@media print` hides nav; `printDiv()` JS opens print window
- **Modals:** CSS overlay modals via `openModal()`/`closeModal()` in `js/app.js`
- **Pagination:** manual LIMIT/OFFSET with page buttons

## Demo accounts (password: `password`)

| Username | Role | Scope |
|----------|------|-------|
| `superadmin` | Super Admin | All |
| `admin` | Admin | All |
| `chef_yde` | Chef Agence | Yaoundé agency |
| `chef_guichet1` | Chef Guichet | Reports, payments |
| `guichetier1` | Guichetier | Sales, bordereaux |
| `operateur1` | Opérateur | Data entry |

## Known gaps

- Some query fragments interpolate variables directly (e.g., `agenceFilter()` returns raw SQL conditions concatenated into queries)
- No input validation library — manual `trim()` and type casting

## Gestion de caisse (Cash Register)

Guichetiers must open their caisse daily before selling tickets. Full workflow in `modules/caisse/index.php`.

### Tables
- `caisses` — one per guichetier per day (fond_initial, statut: ouverte/fermee, fond_physique at closure)
- `mouvements_caisse` — auto-created for each ticket sale, expense, or other receipt
- `transferts_caisse` — ticket transfers between guichetiers at closure
- `transfert_tickets` — individual tickets in a transfer

### Key functions
- `caisseOuverte()` — returns open caisse row for current guichetier today, or null
- `requireCaisseOuverte()` — redirects guichetier to caisse page if no caisse open

### Guards
- `modules/tickets/liste.php` — calls `requireCaisseOuverte()` to block page access
- `modules/tickets/vente.php` — checks `caisseOuverte()` on POST (returns JSON error)
- `logout.php` — shows pause/cloture dialog when guichetier has caisse open
- `includes/header.php` — sidebar link under FINANCES section

### Business rules
- Vente libre is the primary sales button (leftmost), vente rattachée is secondary
- Ticket transfers at closure: vendu/reserve tickets are transferable, utilise/annule are blocked
- Only cash (especes) requires physical reconciliation at closure
- Supervisors (chef_guichet, chef_agence, admin) can view all caisses and force-close