# AGENTS.md — PharmaCare

## Quick Facts

- **Stack:** Vanilla PHP 7.4+/8.2 + MySQL/MariaDB on XAMPP. No framework, no Composer, no npm, no build/test/lint.
- **Language:** All code identifiers, UI text, and DB column names are in French.
- **No automated tests.** Verify changes manually via the browser.

## Setup

1. Copy `pharmacare/` into `C:\xampp\htdocs\`
2. Start XAMPP (Apache + MySQL)
3. Import DB :
   ```
   mysql -u root < database.sql
   ```
4. Open `http://localhost/pharmacare`
5. Demo logins: `admin` / `password`, `pharmacien` / `password`, `caissier` / `password`

## Env System

- `config/env.php` detects dev vs prod via `PHARMACARE_ENV` env var or presence of `config/env.prod.php`.
- Dev: hardcoded `root@localhost/pharmacare`. Prod: reads from `env.prod.php` (see `env.prod.example.php`).
- `config/database.php` loads `env.php` first, then provides `getDB()` PDO singleton.

## Request Pattern (every page)

```php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('module.action');   // or requireLogin()
$db = getDB();
// POST handling with verifyCsrf() → redirect-after-POST
// Fetch data
layout_head($title, $activePage);
// inline HTML/PHP
layout_foot();
```

## Module Map

| File | Permission | Description |
|---|---|---|
| `index.php` | — | Login page |
| `dashboard.php` | `dashboard.voir` | Role-specific dashboard (3 variants in `includes/`) |
| `modules/vente.php` | `vente.creer` | Point of Sale (POS) — JS cart, JSON POST |
| `modules/caisse.php` | `caisse.voir` / `caisse.ouvrir` | Cash register sessions (open/close/movements) |
| `modules/produits.php` | `produits.*` | Product CRUD |
| `modules/stock.php` | `stock.voir` | Stock view with alerts |
| `modules/stock_ajust.php` | `stock.ajuster` | Manual stock adjustments |
| `modules/fournisseurs.php` | `fournisseurs.*` | Supplier CRUD |
| `modules/commandes.php` | `commandes.*` | Supplier orders (en_attente → en_cours → livrée/annulée) |
| `modules/ventes_hist.php` | `ventes_hist.voir` | Sales history |
| `modules/rapports.php` | `rapports.voir` | Reports & charts |
| `modules/clients.php` | `clients.*` | Customer CRUD |
| `modules/categories.php` | `categories.*` | Category CRUD |
| `modules/utilisateurs.php` | `utilisateurs.*` | User management (admin) |
| `modules/roles.php` | `roles.*` | Role & permission management (admin) |
| `modules/parametres.php` | `parametres.gerer` | App settings (theme, currency, TVA, fonts) |
| `modules/comptabilite.php` | `comptabilite.*` | OHADA accounting (journal, balance, bilan, plan comptable) |

## Key Files

| File | Role |
|---|---|
| `config/env.php` | Env detection (dev/prod), DB constants |
| `config/database.php` | `getDB()` PDO singleton |
| `config/settings.php` | `getParam()`, `getAllParams()`, `fmtMoney()`, theme/font/currency arrays |
| `config/comptabilite.php` | OHADA accounting helpers (`ecritureCreate`, `balanceGet`, etc.) |
| `config/rate_limit.php` | Login rate limiting (file-based, 5 attempts / 15 min lockout) |
| `includes/auth.php` | Session, login, CSRF, RBAC (`hasPermission`, `requirePermission`), `e()`, `fmt()`, `flash()` |
| `includes/layout.php` | `layout_head()`/`layout_foot()`, SVG `icon()` system (~40 icons), theme CSS injection |
| `assets/js/app.js` | POS cart logic, modals, live search, notifications |

## Auth & RBAC

- Permission codes use dot notation: `module.action` (e.g., `produits.ajouter`)
- `hasPermission('code')` — admin always returns true
- `requirePermission('code')` — redirects to `dashboard.php?err=access` if denied
- `isAdmin()` / `isPharmacien()` are deprecated wrappers — use `hasPermission()` instead
- 35+ permission codes across modules (see `database.sql`)

## DB Schema (21 tables)

Core: `utilisateurs`, `roles`, `permissions`, `role_permissions`, `categories`, `fournisseurs`, `clients`, `reglements`, `produits`, `ventes`, `vente_lignes`, `commandes`, `commande_lignes`, `mouvements_stock`, `parametres`

Caisse: `caisses`, `sessions_caisse`, `mouvements_caisse`

Comptabilité: `plan_comptable`, `exercices`, `ecritures`, `ecriture_lignes`

- All user-input queries use prepared statements with `?` placeholders
- Static queries (no user input) use `$db->query()` directly
- Soft deletes: `actif=0` on products, suppliers, users

## Conventions

- **PRG pattern** on all form submissions (POST → redirect → GET)
- **CSRF** via `csrf()` / `verifyCsrf()` on all forms
- **Money** via `fmtMoney()` (reads currency from `parametres` table)
- **TVA** from `parametres` table (default `19.25` for Cameroon)
- **Timezone:** `Africa/Douala` (set in `settings.php`)
- **No dependency manager** — all code is standalone PHP/JS/CSS

## Accounting Module (comptabilite)

- Follows OHADA chart of accounts (CEMAC region)
- Double-entry bookkeeping enforced in `ecritureCreate()` — debit must equal credit (±0.01 tolerance)
- Must be called within an existing PDO transaction
- Auto-links to open `exercices` by date
- Sources: `vente`, `commande`, `stock`, `caisse`, `manuel`
