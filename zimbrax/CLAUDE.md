# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ZimbraX is a full-featured webmail application (Zimbra clone) built with procedural PHP/MySQL on XAMPP. It provides email, calendar, contacts, and tasks in a single dark-themed UI. All UI text is in French.

## Tech Stack

- **Backend**: PHP 7.4+ (procedural, no framework), raw PDO prepared statements
- **Database**: MySQL 5.7+ (utf8mb4), schema in `database.sql` (9 tables + seed data)
- **Frontend**: Vanilla JavaScript (no framework), single `js/app.js`
- **CSS**: Single `css/app.css`, dark theme with CSS custom properties, Google Fonts (DM Sans, DM Mono)
- **Server**: Apache via XAMPP, no build tooling

## Setup

1. Start XAMPP (Apache + MySQL)
2. Create database `zimbrax` via phpMyAdmin, import `database.sql`
3. Access at `http://localhost/zimbrax/`
4. Demo accounts: `admin`/`password` (admin@zimbrax.cm), `alice`/`password` (alice@zimbrax.cm)

No build, lint, or test commands exist. Files are served directly by Apache.

## Architecture

### Backend: Procedural PHP with switch-based routing

- `includes/config.php` — Central bootstrap included by every page and API endpoint. Provides: PDO connection, session setup (with httponly/samesite cookies), auth helpers (`isLoggedIn`, `requireLogin`, `currentUser`), flash messages, utility functions (`sanitize`, `timeAgo`, `formatSize`, `initials`), JSON response helpers (`apiSuccess`, `apiError`, `jsonResponse`), and `getFolders`. `currentUser()` strips `password` and `mail_password` from session data.
- `api/*.php` — Each endpoint reads `php://input` once into `$rawInput`, parses JSON body, extracts `$action` from GET/JSON body, dispatches via `switch($action)`. All return JSON via `apiSuccess()`/`apiError()`.
- **Exception**: `api/folders.php` uses traditional form POST + redirect with flash messages (not JSON).
- `modules/settings/index.php` — Standalone settings page with its own sidebar, uses PRG pattern (POST-Redirect-GET) with flash messages.
- `api/emails.php` supports file uploads via `FormData` for the `send` action (multipart/form-data).

### Frontend: Single app shell with AJAX

- `index.php` renders the full UI: sidebar, email list/view, compose modal, calendar/contacts/tasks right panel, all modals.
- `js/app.js` handles all interactivity via DOM manipulation. Communicates with backend via `fetch()` calls to `api/*.php`.
- `api()` helper supports JSON body, FormData (for file uploads), and plain GET requests.
- Email HTML content is sanitized via `sanitizeHtml()` (DOMParser-based, strips scripts and event handlers) before rendering.
- PHP injects `APP_CONFIG` object into JS (base URL, user ID, inbox ID, user name/email).
- State in global JS variables (`currentFolder`, `currentEmail`, `currentEmailData`, `selectedEmails`, `calDate`, `allContacts`).
- Modals toggled via CSS class `open` on backdrop elements. Escape key closes modals.

### Database

Tables: `users`, `folders`, `emails`, `email_tags`, `attachments`, `contacts`, `events`, `event_attendees`, `tasks`, `sessions`, `user_settings`. All user-scoped data has `user_id` foreign key with ownership checks in queries.

## Key Conventions

- **Security**: All queries use PDO prepared statements. Output sanitized via `sanitize()` (htmlspecialchars). Every API endpoint verifies `user_id` ownership on data. Session cookies use httponly + samesite flags. `session_regenerate_id(true)` called on login.
- **Internal mail routing**: When sending email, if the recipient address matches another ZimbraX user, the email is inserted into that user's inbox automatically.
- **IMAP/SMTP settings**: The settings UI allows configuring IMAP/SMTP, but no code actually connects to external mail servers — settings are saved but unused.
- **API pattern**: New API actions follow the `switch($action)` dispatch pattern in the relevant `api/*.php` file, returning `apiSuccess()` or `apiError()`.
- **Adding a user**: Insert into `users` table with `password_hash()` for the password column.
- **Attachments**: `uploads/attachments/` stores uploaded files. Protected by `.htaccess` denying PHP execution.

## Known Issues

- Database credentials are hardcoded in `includes/config.php` (no `.env` file) — typical for XAMPP dev.
- IMAP/SMTP settings are saved but never used by application code.
- `email_tags`, `sessions`, `event_attendees`, and `user_settings` tables exist in schema but are unused by application code.