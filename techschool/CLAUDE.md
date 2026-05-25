# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TechSchool is a school management information system for a technical/vocational school in Cameroon. It manages students, teachers, classes, grades, report cards, payments, absences, and users with role-based access. The UI and code are entirely in French.

**Stack**: Vanilla PHP 7.4+ / MySQL 5.7+ / XAMPP — no framework, no ORM, no build system, no package manager, no test suite.

## Running the Application

1. Start XAMPP (Apache + MySQL)
2. Create database `techschool` (utf8mb4_unicode_ci) and import `database.sql`
3. Access `http://localhost/techschool/`
4. Default login: `superadmin` / `password`

Database config is in `includes/config.php` (root user, empty password by default).

## Architecture

Traditional PHP multi-page application — no MVC, no router, no templating engine. Each page is a standalone script following this pattern:

1. Include `includes/config.php` (starts session, creates `$pdo`, defines helpers)
2. Call `requireLogin()` and optionally `requirePerm()` for access control
3. Handle POST/GET logic (CRUD operations) at the top of the file
4. Include `includes/header.php` (sidebar nav + topbar + flash messages)
5. Render HTML with inline `<?php ... ?>` throughout
6. Include `includes/footer.php`

### Key Helpers (in `includes/config.php`)

- `can($perm)` / `requirePerm($perm)` — permission checks against `$_SESSION['permissions']`
- `hasRole(...$roles)` / `isSuperAdmin()` — role checks against `$_SESSION['role_code']`
- `flash($msg, $type)` / `getFlash()` — session-based flash messages
- `genMatricule($pdo, $prefix, $table)` — auto-increment IDs like `EL20240001`
- `formatMoney($n)` — formats as `1 500 FCFA`
- `calculerBulletin($pdo, $eleveId, $periodeId, $anneeId)` — computes weighted averages and grades
- `logAction($pdo, $action, $module, $details)` — audit logging to `logs` table

### Auth & RBAC

8 roles (`super_admin`, `admin`, `directeur`, `censeur`, `enseignant`, `secretaire`, `comptable`, `parent`) with 17 granular permissions mapped via `role_permissions` table. The `can()` function checks `$_SESSION['permissions']` array.

## Module Structure

- `modules/{module}/index.php` — CRUD pages with sub-pages (e.g., `eleves/ajouter.php`, `eleves/voir.php`)
- `modules/{module}.php` — standalone pages (e.g., `inscription.php`, `paiements.php`, `absences.php`, `rapports.php`)

## Database

16 tables defined in `database.sql`. All queries use raw PDO prepared statements — no query builder or ORM. Key tables: `eleves`, `enseignants`, `classes`, `notes`, `inscriptions`, `paiements`, `absences`, `users`, `roles`, `permissions`, `role_permissions`, `bulletin_config`, `conseils_classe`.

## Conventions

- **Language**: All code comments, variable names, UI labels, and database columns are in French
- **Currency**: FCFA (West/Central African CFA franc)
- **Modals**: Pure CSS overlay (`.modal-overlay.open`), toggled by `openModal()` / `closeModal()` in `js/app.js`
- **Deletion**: Via GET links with `?del={id}` and JavaScript `confirm()`
- **Pagination**: Manual `LIMIT $perPage OFFSET $offset`
- **Search/filter**: GET parameters with `LIKE` queries, assembled via `$where` arrays and `$params` arrays for prepared statements
- **CSS**: Custom theme using CSS variables defined in `css/app.css`

## Known Security Gaps

- No CSRF token protection on forms
- Delete operations use GET requests (vulnerable to CSRF/prefetch)
- `genMatricule()` uses string interpolation in a `LIKE` prefix — potential SQL injection vector
- Flash error messages can expose PDO exception messages via `$e->getMessage()`
- No rate limiting on login