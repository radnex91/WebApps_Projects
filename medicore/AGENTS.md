# AGENTS.md — MediCore ERP

## Project

Hospital management system (French UI). Vanilla PHP + MySQL on XAMPP. No framework, no build step, no package manager. Edit-and-refresh development.

## Quick Start

1. Start Apache + MySQL in XAMPP
2. Import DB: `mysql -u root < sql/medicore.sql`
3. Apply migrations if needed: `sql/update.sql`, `sql/update_v3.sql`
4. Open `http://localhost/medicore` — login `admin@medicore.fr` / `admin123`
5. DB credentials in `includes/config.php`

No lint, typecheck, test, or build commands exist.

## Architecture

### URL routing (`.htaccess`)

- `/module` → `pages/module.php` (clean URLs via rewrite)
- `/api/*` → `pages/api.php`
- `/auth` → `includes/auth.php`, `/poll` → `includes/poll.php`
- Direct access to `includes/`, `pages/`, `sql/`, dotfiles is **blocked** (HTTP 403)
- `index.php` = login screen (only page not using layout.php)

### Request flow (every page)

```php
$currentPage = 'module_name';  // must match key in ALL_PAGES (permissions.php)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('module_name');
// POST: csrf_verify() FIRST, then process
// DB: db_select / db_row / db_scalar / db_exec
// HTML rendering...
require_once __DIR__ . '/../includes/footer.php';
```

### DB access — use helpers ONLY, never raw PDO

| Function | Returns |
|---|---|
| `db_select($sql, $params)` | array of rows |
| `db_row($sql, $params)` | single row or null |
| `db_scalar($sql, $params)` | single value |
| `db_exec($sql, $params)` | lastInsertId or rowCount |

All use prepared statements. Never concatenate values into SQL.

### Security — must preserve on every change

- **CSRF**: every POST form has `csrf_field()`, handler calls `csrf_verify()` first
- **URL signing**: record links use `secure_url()` or `get_signed_id()` — never bare `?id=` without `&tok=`
- **XSS**: output via `h()` or `e()` (echoes escaped)
- **Input**: use `post_str()`, `post_int()`, `post_float()`, `post_email()` — never `$_POST` directly
- **Validation**: use `Validator` class chains
- **IDOR**: call `assert_owns()` before mutating records

## Key Files

| Path | Purpose |
|---|---|
| `includes/config.php` | DB singleton `getDB()`, settings, constants |
| `includes/error_handler.php` | Global error/exception handler, logs to `logs/errors.log` |
| `includes/security.php` | CSRF, sanitization, URL signing, DB helpers, `Validator` |
| `includes/auth.php` | Sessions, `requireLogin()`, `hasRole()`, `logActivity()` |
| `includes/permissions.php` | RBAC: `can()`, `canAccessPage()`, `ALL_PAGES`, `ALL_ACTIONS` |
| `includes/layout.php` | Sidebar nav, header, opens `<div class="content">` |
| `includes/footer.php` | Closes layout, loads `app.js` |
| `includes/notifier.php` | Notification system |
| `includes/poll.php` | Polling endpoint |
| `pages/*.php` | 32 module pages (patients, urgences, pharmacie, etc.) |
| `assets/css/main.css` | Dark theme, CSS custom properties |
| `assets/js/app.js` | Tab switching, search, confirm dialogs |
| `sql/medicore.sql` | Full schema + seed data |

## Conventions

- UI strings and code comments are in **French**
- Icons are emoji, centralized in `includes/icons.php` (`ICON_NAV`, `ICON_BTN`, `ICON_STATUT`)
- `$currentPage` must be set **before** including `layout.php` for sidebar active state
- Admin role bypasses all permission checks
- Non-admin permissions cached in session — call `invalidate_permissions_cache()` after changing role permissions
- App settings from `app_settings` table via `setting($key, $default)`

## Error Handling

- Global handler in `includes/error_handler.php` — loaded first by `config.php`
- All errors logged to `logs/errors.log` (auto-rotated at 5 MB)
- **Dev mode**: set `APP_ENV = 'dev'` in `config.php` to show errors on screen
- **Prod mode** (default): errors logged, user sees friendly error page
- Never use `die()` — use `_respond_error($code, $message)` instead (detects API vs HTML)
- Never leave `catch` blocks empty — always call `_log_error($level, $msg, __FILE__, __LINE__, $e)`
- API requests get JSON responses, HTML requests get styled error pages
- Shutdown handler catches fatal errors that bypass exception handling
