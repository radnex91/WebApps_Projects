# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Atlas Prime Logistics is a PHP/MySQL parcel and expedition management system for a Cameroon-based logistics company. The UI is entirely in French. It runs on XAMPP (Apache + MySQL + PHP 8.0+) with zero external dependencies — no Composer, no npm, no build step.

## Development Setup

- Place the project folder in `C:/xampp/htdocs/atlas_prime/`
- Start Apache and MySQL via XAMPP Control Panel
- Access the app at `http://localhost/atlas_prime/login.php`
- Run `setup.php` in browser to initialize the database and create an admin account, then **delete `setup.php`**
- Demo credentials: `admin`/`password`, `marie.d`/`password` (operateur), `jean.m`/`password` (superviseur)

There are no build, test, or lint commands. Files are served directly by Apache with no compilation step.

## Architecture

**No MVC framework.** Each page is a standalone PHP file accessed by direct URL. Protected pages include `includes/header.php`, which calls `Auth::check()` to enforce authentication. Pages use `Auth::requirePermission('permission_name')` for fine-grained access control based on the `role_permissions` table. The sidebar uses `Auth::hasPermission()` to conditionally show/hide navigation links.

### Core includes (`includes/`)

| File | Role |
|------|------|
| `config.php` | App constants: DB credentials, session lifetime (8h), timezone (Africa/Douala), app name |
| `database.php` | `Database` singleton — static methods `query()`, `fetchAll()`, `fetchOne()`, `insert()`, `execute()` wrapping PDO with prepared statements |
| `auth.php` | `Auth` class — session-based auth, permission-based access (`requirePermission`/`hasPermission`), session-cached permissions, bcrypt cost 12, `logAction()` for audit trail |
| `functions.php` | Utilities: `genererNumeroColis()`, `formaterMontant()`, `formaterDate()`, `getStatutBadge()` |
| `header.php` | Sidebar + topbar HTML, session check, flash message display |
| `footer.php` | Closing HTML, Chart.js (CDN), main.js include |

### Page structure (`pages/`)

All pages follow the pattern: include header → process POST → query data → render HTML. Form submissions use standard HTML forms with POST and redirect-after-POST. Status updates on list pages use inline `<form>` elements with auto-submit on `<select>` change. Flash messages are stored in `$_SESSION['flash']`.

### Database

Schema is in `database.sql` — 8 tables, 1 view (`vue_colis` for dashboard queries), 1 stored procedure (`generer_numero_colis` for parcel number generation in `APLYYYYMMNNNNN` format). Key tables:

- `colis` — main parcels table with 6 status states, FK to `voyages` and `agences`
- `voyages` — trips with 4 statuses (planifie/en_cours/arrive/annule)
- `suivi_colis` — tracking history per parcel
- `utilisateurs` — users with role ENUM
- `contacts` — sender/recipient contacts (defined in schema but **not yet wired to any page**)

All queries use PDO prepared statements — never string interpolation.

### Frontend

- Vanilla HTML/CSS/JS, no framework
- Dark theme with CSS variables (defined in `assets/css/main.css`)
- Modals via CSS class toggling (`.modal-backdrop.open`)
- Print styles for bordereau output (`bordereau_print.php`)
- CDN dependencies only: Chart.js, Font Awesome, Google Fonts (Sora, Space Mono)

## Important Conventions

- DB host must be `127.0.0.1` (not `localhost`) — this is a PDO/MySQL quirk on Windows
- Parcel numbers are generated server-side by the `generer_numero_colis` stored procedure, not in PHP
- The `contacts` table exists in the schema but has no UI — it is reserved for future use
- All monetary formatting goes through `formaterMontant()` in `functions.php`
- CSRF protection is not currently implemented — forms lack CSRF tokens