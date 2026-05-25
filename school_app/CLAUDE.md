# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

French-language school management web application ("Complexe Scolaire") built with vanilla PHP/MySQL on XAMPP. No framework, no build tools, no npm, no Composer. All pages are server-rendered HTML with minimal vanilla JS for UI sugar.

## Running the App

- **Start XAMPP** (Apache + MySQL), then visit `http://localhost/school_app/`
- **Database setup**: Create DB `complexe_scolaire` in phpMyAdmin, import `database.sql`
- **Default login**: `admin` / `password`
- **No build step, no test suite, no CI/CD** — files are served directly by Apache

## Architecture

### Request Flow

Every protected page follows this pattern:
1. Include `includes/config.php` (DB connection, session start, helper functions)
2. Call `requireLogin()` to enforce authentication
3. Set `$pageTitle` for the topbar/header
4. Include `includes/header.php` (HTML head, sidebar, flash messages)
5. Page-specific logic (queries, form handling, HTML)
6. Include `includes/footer.php` (closing tags, `js/app.js`)

### Module Structure

Each module lives in `modules/{name}/` and contains some combination of:
- `index.php` — list page with search, filters, pagination
- `ajouter.php` — add/edit form (dual-purpose: add by default, edit when `?id=X` is passed)
- `modifier.php` — thin wrapper that sets `$_GET['id']` and includes `ajouter.php`
- `supprimer.php` — delete handler

Some modules (classes, inscription, absences, emploi_temps) embed the add form directly in `index.php` instead of using a separate `ajouter.php`.

Standalone pages (`paiements.php`, `parametres.php`) sit directly in `modules/` without a subdirectory.

### Key Files

- `includes/config.php` — DB constants, PDO connection (`$pdo`), auth helpers (`requireLogin()`, `hasRole()`), flash messages, `sanitize()`, `getAnneeActive()`
- `includes/header.php` — sidebar nav, topbar with user info/role badge, flash message display. Includes a guard that loads config and calls `requireLogin()` if `APP_NAME` is not defined
- `database.sql` — full schema (13 tables) + seed data (levels, subjects, periods, admin user). This is the only "migration"
- `js/app.js` — alert auto-close, active nav detection, delete confirmation (`data-confirm`), sidebar toggle, grade input coloring, print helper

### Database

- MySQL via PDO with `FETCH_ASSOC` and `EMULATE_PREPARES = false`
- Global `$pdo` object from `config.php`
- Active school year retrieved via `getAnneeActive($pdo)` — most modules filter by the active year
- 13 tables: `niveaux`, `annees_scolaires`, `classes`, `parents`, `eleves`, `inscriptions`, `enseignants`, `matieres`, `affectations`, `periodes`, `notes`, `emploi_temps`, `absences`, `paiements`, `utilisateurs`

### Authentication & Roles

- Session-based: `$_SESSION['user_id']` / `$_SESSION['role']`
- Five roles in DB ENUM: `admin`, `directeur`, `enseignant`, `comptable`, `secretaire`
- `hasRole(...$roles)` checks role membership; `requireLogin()` redirects to `login.php`
- Role gating is minimal — only `parametres.php` restricts to `admin`/`directeur`. Most pages rely on `requireLogin()` only
- Passwords use `password_hash()`/`password_verify()` (bcrypt)

## Conventions

- **Language**: All UI text, comments, and documentation are in French
- **Currency**: FCFA; payment methods include "mobile money"
- **School levels**: Maternelle (PS–GS) → Primaire (CP–CM2) → Collège (6ème–3ème) → Lycée (Seconde–Terminale)
- **URL pattern**: `modules/{module}/index.php`, `?id=X` for edit, `?del=X` for delete, `?page=N` for pagination
- **Flash messages**: Set with `flash($msg, $type)`, displayed in header via `getFlash()`
- **Output sanitization**: Always use `sanitize()` (htmlspecialchars wrapper) when echoing user input
- **CSS custom properties**: Defined in `css/style.css` — use them for theming consistency
- **Font Awesome 6.5** (CDN) for icons throughout the sidebar and pages

## Known Issues to Be Aware Of

- Delete actions use GET requests with no CSRF protection
- Some SQL queries interpolate `$annee_id` directly instead of using prepared statements
- No rate limiting on login attempts
- No automated tests exist