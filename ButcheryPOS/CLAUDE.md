# ButcheryPOS

Bilingual (FR/EN) Point of Sale system for butchery/fish shops in West Africa.

## Stack
- PHP 8 (vanilla, no framework) + MySQL/MariaDB + Bootstrap 5
- XAMPP environment (Apache + PHP + MySQL)
- AJAX for real-time POS operations
- Python bridge for weighing scale (RS232/USB)

## Project Structure
- `config/` — Bootstrap, config, i18n (en.php, fr.php)
- `src/Core/` — Database, Auth, Csrf, Permission, Router, Helpers, Validator
- `src/Models/` — 14 Eloquent-like models (BaseModel + table-specific)
- `src/Repositories/` — BaseRepository (generic PDO CRUD)
- `src/Services/` — Business logic (Auth, Rbac, Product, Stock, Sale, Payment, Expiry, PosSession, I18n, Settings)
- `src/Payments/` — PaymentGatewayInterface + Cash, Card, 3 Mobile Money (Orange, MTN, Wave)
- `src/Scale/` — Scale bridge interfaces + DemoScaleBridge
- `src/Middleware/` — Auth, Csrf, Permission, ApiAuth
- `public/` — Front controller (index.php), API (api/index.php, api/scale.php), assets
- `templates/` — App shell (app.php), page templates, print/receipt
- `database/` — schema.sql + migrations
- `bridge/` — Python scale bridge script

## Database
- 17 tables: app_settings, app_roles, users, role_module_permissions, categories, units, products, stock_batches, stock_movements, suppliers, customers, pos_sessions, sales, sale_items, payments, expiry_alerts, scale_readings
- Default admin: `admin` / `admin123`

## Key Architecture
- **RBAC**: 6 permission flags per module/role (view/create/edit/delete/print/manage). Admin bypasses all.
- **FIFO**: Stock sold via `stock_batches` ordered by expiry_date ASC. `SELECT ... FOR UPDATE` for concurrency.
- **i18n**: `t('key', ['placeholder' => value])` with FR/EN fallback chain.
- **Multi-terminal**: `pos_sessions` table with token isolation.
- **Payments**: Factory pattern with gateway interface. Mobile money = placeholders.
- **Scale**: Python bridge reads serial, POSTs to PHP API. Frontend polls every 1.5s.

## i18n
- Translation keys in `config/lang/en.php` and `fr.php`
- Use `t('key')` in all templates — no hardcoded strings
- User preference stored in `users.preferred_language`

## Running
1. Import `database/schema.sql` into MySQL
2. Access `http://localhost/ButcheryPOS/`
3. Login: admin / admin123
4. For scale: `python bridge/scale_bridge.py --demo`