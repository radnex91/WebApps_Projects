# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ESADISS partner pricing platform — a PHP + MySQL web app that shows products with dual pricing (partner price vs. client price). Designed for shared hosting (OVH, o2switch, Hostinger). No build tools, no npm, no Composer, no framework.

## Tech Stack

- **Backend**: PHP 7.4/8.x, no framework — each PHP file is a direct URL entry point
- **Database**: MySQL/MariaDB (InnoDB, utf8mb4)
- **Frontend**: Vanilla JavaScript (no bundler), single `styles.css`
- **Auth**: PHP sessions + bcrypt (`password_hash`/`password_verify`)
- **Server**: Apache with `.htaccess` (blocks direct access to `config.php` and `database.sql`)

## Architecture

```
config.php          → DB credentials + WhatsApp phone (supports config.local.php override)
    ↓
db.php              → PDO singleton, all query functions
    ↓
auth.php            → Session management, requireLogin(), role hierarchy (client < partenaire < admin)
    ↓
api.php             → REST JSON API (resource=produits|utilisateurs|stock)
```

Each PHP page includes `auth.php` (which includes `db.php` → `config.php`). Pages enforce access via `requireLogin($minRole)`.

### Key Request Flow

- **Public catalog** (`index.php` + `accueil.js`): No auth needed. Calls `api.php?resource=produits&public=1`.
- **Partner view** (`partenaire.php` + `partenaire.js`): Requires partner role. Shows dual pricing.
- **Admin pages** (`admin*.php` + `admin.js`): Require admin role. CRUD via `api.php`.
- **API** (`api.php`): All endpoints require auth except `GET ?resource=produits&public=1`.

### JavaScript Structure

- `catalog-shared.js`: Shared between public and partner views — WhatsApp selection toolbar, price formatting, image fallback system
- `accueil.js`: Public catalog logic
- `partenaire.js`: Partner catalog logic (dual pricing)
- `admin.js`: All admin client-side logic (product CRUD, user management, stock)

### Database Schema (6 tables)

Defined in `database.sql`: `produits`, `utilisateurs`, `depots`, `rayons`, `stock_niveau`, `stock_mouvements`

**Critical rule**: All DDL changes must be made in `database.sql` only — never in other files. Apply changes manually via phpMyAdmin or MySQL client.

## Development Setup

1. Configure `config.php` with MySQL credentials (or copy `config.local.example.php` to `config.local.php` for local overrides — `config.local.php` is gitignored)
2. Run `install.php` in the browser to create tables + admin account (admin/Admin123!), then **delete `install.php`**
3. Optionally run `seed.php` for 30 sample products

No build step, no test framework, no CI/CD. Test by opening pages in the browser.

## Stock Visibility Logic

When `gestion_stock = 1` for a product, the `visible_partenaire` flag is auto-synced: products at zero stock are hidden from partner view but remain visible in the public catalog. This sync happens in `syncVisiblePartenaireForProduit()` called after any stock movement or product update.

## WhatsApp Integration

Configured via `WHATSAPP_PHONE` in `config.php`. Powers the floating FAB button (`whatsapp_widget.php`) and product selection toolbar for sending inquiries via `wa.me` links.

## Image Handling

Product images use a fallback system in JavaScript (`imageCandidates()` / `bindImageFallbacks()`) that tries multiple paths: `uploads/`, `uploads/produits/`, `images/`, `assets/`, then falls back to absolute URLs.

## API Endpoints

| Endpoint | Auth | Methods | Notes |
|---|---|---|---|
| `?resource=produits&public=1` | None | GET | Public catalog (client prices only) |
| `?resource=produits` | Partner+ | GET | Partner sees partner prices; admin sees all with `admin=1` |
| `?resource=produits` | Admin | POST/PUT/DELETE | Product CRUD |
| `?resource=utilisateurs` | Admin | GET/POST/PUT | User management (role filter: `?role=partenaire|client|all`) |
| `?resource=stock` | Admin | GET/POST | Stock data and movements |