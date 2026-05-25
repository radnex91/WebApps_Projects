# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MediCore ERP is a hospital management system (French-language UI) built with vanilla PHP on XAMPP. No framework — each page is a self-contained PHP file handling its own routing, POST processing, and HTML rendering. MySQL database via PDO with a singleton connection (`getDB()`).

## Tech Stack

- **Backend**: PHP 7.4+ (vanilla, no framework)
- **Database**: MySQL 5.7+ via PDO (all queries use prepared statements)
- **Frontend**: HTML5, CSS3, vanilla JavaScript (no build step, no bundler)
- **Server**: Apache via XAMPP
- **No package manager** — no composer.json or package.json

## Running the Application

1. Start Apache + MySQL in XAMPP
2. Import database: `mysql -u root < sql/medicore.sql` (or via phpMyAdmin)
3. If upgrading an existing install, also run `sql/update.sql`
4. Access at `http://localhost/medicore`
5. Default login: `admin@medicore.fr` / `admin123`

No build, compile, lint, or test commands exist. Development is edit-and-refresh.

## Architecture

### Request Flow

Every page follows the same pattern:
```php
$currentPage = 'module_name';           // matches key in ALL_PAGES (permissions.php)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/layout.php';
requirePageAccess('module_name');
// ... POST handling (csrf_verify() first) ...
// ... DB queries via db_select/db_row/db_scalar/db_exec ...
// ... HTML rendering ...
require_once __DIR__ . '/includes/footer.php';
```

`index.php` is the only page that doesn't include layout.php — it's the login screen.

### Include Chain

`config.php` → `security.php` (loaded by auth.php) → `auth.php` → `permissions.php` (loaded by auth.php) → `layout.php`

- **config.php**: DB connection (`getDB()` PDO singleton), app settings (`get_settings()`), roles helpers, formatting (`fmt_money`, `fmt_date`), icons include
- **security.php**: CSRF tokens, input sanitization (`clean`, `get_str`, `post_str`, `post_int`, `post_float`), URL signing (`url_sign`/`url_verify`/`get_signed_id`), DB query helpers (`db_select`, `db_row`, `db_scalar`, `db_exec`), IDOR protection (`assert_owns`), rate limiting, `Validator` class, output helpers (`h()`, `e()`)
- **auth.php**: Session management, login/logout, `requireLogin()`, `hasRole()`, `currentUser()`, `logActivity()`
- **permissions.php**: Role-based page access (`canAccessPage`, `requirePageAccess`), action permissions (`can()`), navigation builder (`getAccessibleNav()`), `ALL_PAGES` and `ALL_ACTIONS` constants
- **layout.php**: HTML head, sidebar navigation, header with notifications — opens `<div class="content">`
- **footer.php**: Closes layout divs, loads `app.js`, flushes output buffer

### Database Access Pattern

Always use the helper functions — never raw PDO:
- `db_select($sql, $params)` → array of rows
- `db_row($sql, $params)` → single row or null
- `db_scalar($sql, $params)` → single value
- `db_exec($sql, $params)` → lastInsertId or rowCount

All use prepared statements. Never concatenate values into SQL.

### Security Patterns (must be preserved)

- **CSRF**: Every POST form includes `csrf_field()`, handlers call `csrf_verify()` before processing
- **URL signing**: Links to individual records use `secure_url()` or `get_signed_id()` — never accept bare `?id=` without `&tok=` signature
- **XSS**: All output uses `h()` or `htmlspecialchars()` — the `e()` function echoes escaped output
- **Input**: Use `post_str()`, `post_int()`, `post_float()`, `post_email()` instead of accessing `$_POST` directly
- **Validation**: Use the `Validator` class for form validation chains

### Module Pages

Each `.php` file at the root is a module. Key modules: `patients`, `urgences`, `pharmacie`, `laboratoire`, `caisse`, `facturation`, `medecins`, `lits`, `appointments`, `dossiers`, `stocks`, `rh`, `analytics`, `rapports`, `utilisateurs`, `parametres`, `roles`.

### Database Tables

Core: `utilisateurs`, `patients`, `departements`, `lits`, `hospitalisations`, `rendez_vous`, `medicaments`, `ordonnances`, `ordonnance_lignes`, `analyses`, `factures`, `stocks`, `activite_log`
Config: `app_settings`, `roles_config`, `role_page_access`, `role_action_access`

### Permissions System

- Admin role bypasses all checks (`can()` and `canAccessPage()` return true)
- Other roles have per-page and per-action permissions stored in `role_page_access` and `role_action_access` tables
- Permissions are cached in session (`$_SESSION['_perm_pages']`, `$_SESSION['_perm_actions']`)
- Call `invalidate_permissions_cache()` after modifying role permissions

### Settings System

- App settings stored in `app_settings` table (key-value)
- Accessed via `setting($key, $default)` or `get_settings()`
- Controls fonts, currency, date format, theme colors, logo, etc.
- Roles are also dynamic from DB via `roles_config` table and `get_roles_map()`

## Conventions

- Application UI is in French — maintain French for all user-facing strings
- Code comments are in French
- Icons are emoji-based, centralized in `includes/icons.php` (`ICON_NAV`, `ICON_BTN`, `ICON_STATUT`, etc.)
- CSS is a single file: `assets/css/main.css` (dark theme with CSS custom properties)
- JS is a single file: `assets/js/app.js` (minimal — tab switching, search, auto-dismiss alerts, confirm dialogs)
- The `{$currentPage}` variable must be set before including layout.php for sidebar active state