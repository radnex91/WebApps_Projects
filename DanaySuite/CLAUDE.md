# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

DanaySuite Mail is a Zimbra-inspired webmail application built with vanilla PHP + MySQL, designed to run on XAMPP. No framework, no build tools, no package manager dependencies.

## Architecture

### File Layout

- **Entry points**: `index.php` (dashboard), `login.php`, `logout.php`, `setup.php` (one-time installer)
- **`includes/`**: Loaded via `bootstrap.php` → `config.php` → `database.php` → `helpers.php` → `auth.php`
- **`includes/app.php`**: All business logic functions (mail, contacts, events, tasks, admin). Loaded only on pages that need it (index.php, but not login/setup)
- **CSS**: Single file `assets/css/app.css` (~720 lines, no framework)
- **JS**: `assets/js/app.js` (6 lines — checkbox event stopPropagation only)
- **Schema**: `database/schema.sql` (7 tables, re-runnable with `CREATE TABLE IF NOT EXISTS`)
- **Uploads**: Stored in `uploads/attachments/`

### Database (7 tables)

- `users` — id, full_name, email, password_hash, role (Administrator/Manager/User)
- `messages` — sender info, subject, body (MEDIUMTEXT)
- `message_recipients` — message_id → user mapping (one row per recipient)
- `mailbox_entries` — user_id + message_id + folder (inbox/starred/sent/drafts/spam/trash) + is_read + is_starred
- `contacts`, `events`, `tasks` — per-user CRUD
- `attachments` — per-message files, max 5 MB each

### Key Patterns

- **Database**: PDO singleton via `db()`, `declare(strict_types=1)` everywhere, prepared statements with named parameters
- **Auth**: PHP sessions with CSRF tokens. `requireAuth()` guards the dashboard, `isAdmin()` gates admin actions
- **Routing**: No router — actions dispatched via `$_POST['action']` in a single if-chain
- **Messages**: Simulated local delivery — when sending, looks up recipient emails in `users` table and creates `mailbox_entries`
- **Flash messages**: Session-based, consumed once per render via `consumeFlash()`
- **XSS prevention**: `e()` helper wraps `htmlspecialchars()` — used consistently in all templates
- **Migrations**: `runMigrations()` re-runs `schema.sql` on every request (idempotent via `IF NOT EXISTS`), guarded by a static `$done` flag

### Key Conventions

- All PHP files use `<?php` open tags only (no closing `?>`)
- Helper functions in `helpers.php` (redirect, e, post, flash, session helpers, formatting)
- Business logic functions in `app.php` (mailbox queries, CRUD, search, admin)
- CSS uses CSS custom properties for theming with two responsive breakpoints (1240px, 980px, 720px)
- Strings are hard-coded in French (UI labels, flash messages, validation errors)

## Development

### Requirements

- PHP 8.0+ with PDO MySQL extension
- MySQL / MariaDB
- XAMPP recommended (Apache + MySQL)

### Setup

1. Place in `C:\xampp\htdocs\DanaySuite`
2. Start Apache + MySQL in XAMPP
3. Visit `http://localhost/DanaySuite/setup.php`
4. Login at `http://localhost/DanaySuite/login.php`

### Default Accounts

| Email | Password | Role |
|---|---|---|
| admin@danaysuite.local | admin123 | Administrator |
| amina.okoro@danaysuite.local | amina123 | Manager |
| kevin.mendy@danaysuite.local | kevin123 | User |

### Testing

There are no automated tests. Manual testing flow:
- Open the app in browser after setup
- Login as admin, verify mailbox, contacts, calendar, tasks panels render
- Send a message to another known user, verify it appears in their inbox
- Test search, star, trash, admin user creation/role updates
- Test attachment upload (max 5 MB per file)

### Making Changes

- CSS changes go in `assets/css/app.css` — no preprocessor
- Business logic additions go in `includes/app.php` as standalone functions
- New database columns need corresponding `schema.sql` update (migration runs automatically)
- All user-facing strings are in French
