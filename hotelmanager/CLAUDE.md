# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HotelPro Suite — a hotel management application (reservations, clients, invoicing, payments, reporting, staff). Written in French, targeted at hotels in Cameroon (FCFA currency, 19.25% VAT, Africa/Douala timezone). Custom hand-rolled PHP MVC with no framework.

## Running the Application

- **Stack**: XAMPP (Apache + MySQL) on localhost — offline-first, no Docker or remote server
- **Setup**: Import `database/hotel_schema.sql` into MySQL, run `setup.php` once for default password hashes, then delete `setup.php`
- **Access**: `http://localhost/hotelmanager/`
- **Dependencies**: Bootstrap 5.3 and Chart.js 4 must be manually downloaded to `public/css/` and `public/js/` for offline use (CDN fallbacks exist but require internet). No composer.json or package.json.

## Architecture

**Single-entry-point MVC**: `index.php` is the sole entry point. It reads `$_GET['page']` and `$_GET['action']`, maps them through a route table, instantiates the matching controller, and calls the method.

**No model layer**: The autoloader references `app/models/` but that directory does not exist. All SQL is written directly in controllers using `Database::query($sql, $params)` — a static PDO wrapper in `config/database.php`. There is no ORM, query builder, or repository pattern.

**Views**: Plain PHP templates (no Twig/Blade). Each controller method manually includes `header.php` and `footer.php` around its content view. Layouts are in `app/views/layouts/`.

**Key utility functions** in `app/helpers.php`:
- `e()` — HTML escaping
- `format_money()` — FCFA formatting
- `csrf_token()` / `verify_csrf()` — CSRF protection on all POST forms
- `set_flash()` / `get_flash()` — session-based flash messages
- `has_permission()` — checks role-based permissions (JSON array from `roles` table; admin has `["all"]`)
- `log_action()` — audit logging to `logs` table

**Configuration**: All constants (DB credentials, hotel info, session lifetime, pagination, paths) are in `config/config.php`.

## Routing

Routes are defined in `index.php` as a `$routes` array mapping `page` values to controller classes. The `action` parameter maps to method names on each controller. Key routes:

| page param      | Controller               | Key actions |
|-----------------|--------------------------|-------------|
| reservations    | ReservationController    | create, store, show, checkin, checkout, cancel, checkAvailability |
| chambres        | ChambreController       | create, store, updateStatut |
| clients         | ClientController         | create, store, show, edit, update |
| facturation     | FacturationController    | generate, show, pay, print |
| paiements       | PaiementController       | index (view is inline in controller) |
| personnel       | PersonnelController      | create, store, toggleStatut (admin-only) |
| rapports        | RapportController        | index |
| profil          | ProfilController          | updatePassword, updateInfo |

## Database

- MySQL/MariaDB, schema in `database/hotel_schema.sql` (includes seed data and two views: `v_chambres_disponibles`, `v_reservations_detail`)
- 9 tables: `roles`, `users`, `types_chambres`, `chambres`, `clients`, `reservations`, `factures`, `paiements`, `logs`
- All queries use parameterized PDO statements
- References use `reference` columns with formatted patterns (e.g., `RES-202604-00001`, `FAC-202604-00001`)

## Notable Quirks

- **Malformed directory**: A `{app/{controllers,...}/` directory exists from a failed brace expansion — it's empty and can be deleted
- **PaiementController** renders HTML directly in the controller method instead of using a separate view file
- **No test suite** exists — no PHPUnit or any test configuration
- **No git repository** — the project is not under version control
- **Application language is French** — all UI text, comments, and documentation are in French
- **setup.php** contains default credentials and must be deleted after initial setup