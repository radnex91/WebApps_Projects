# 🏥 MediCore ERP Hospitalier — Guide d'installation XAMPP

## ⚡ Installation en 4 étapes

### 1. Copier dans XAMPP
```
Windows : C:\xampp\htdocs\medicore\
Linux   : /opt/lampp/htdocs/medicore/
macOS   : /Applications/XAMPP/htdocs/medicore/
```

### 2. Démarrer Apache + MySQL dans XAMPP

### 3. Importer la base de données
**phpMyAdmin** → http://localhost/phpmyadmin → Importer → `sql/medicore.sql` → Exécuter

**OU ligne de commande :**
```bash
mysql -u root -p < sql/medicore.sql
```

### 4. Configurer `includes/config.php` si nécessaire
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // votre mot de passe XAMPP
define('APP_SECRET', '...');     // ⚠️ CHANGER en production !
```

## 🚀 Accès
**http://localhost/medicore**  
Login: `admin@medicore.fr` / `admin123`

---

## 🔒 Sécurité intégrée

| Protection | Détail |
|---|---|
| **SQL Injection** | 100% PDO requêtes préparées — aucune concaténation SQL |
| **XSS** | `h()` sur toutes les sorties, `htmlspecialchars` systématique |
| **CSRF** | Token HMAC signé sur tous les formulaires POST |
| **URL Tampering** | IDs signés HMAC-SHA256 (empêche falsification d'URL) |
| **IDOR** | `assert_owns()` vérifie existence avant toute opération |
| **Brute Force** | Rate limiting 5 tentatives/min par IP sur le login |
| **Session** | `httponly`, `samesite=Lax`, expiration 8h, `session_regenerate_id()` |
| **Path Traversal** | `safe_filename()` sur tous les noms de fichiers |
| **En-têtes HTTP** | CSP, X-Frame-Options, X-XSS-Protection, NOSNIFF |
| **Validation** | Classe `Validator` avec whitelist, type, longueur |

## 📁 Structure
```
medicore/
├── index.php              ← Connexion (CSRF protégé)
├── dashboard.php          ← Tableau de bord (données BDD)
├── patients.php           ← CRUD sécurisé (CSRF + URLs signées)
├── pharmacie.php          ← Médicaments (validé + CSRF)
├── facturation.php        ← Factures
├── [autres modules].php
├── includes/
│   ├── config.php         ← BDD + APP_SECRET
│   ├── security.php       ← 🔒 Middleware sécurité central
│   ├── auth.php           ← Sessions + rate limiting
│   ├── layout.php         ← Sidebar + header
│   └── footer.php
├── assets/css/main.css
├── assets/js/app.js
└── sql/medicore.sql       ← Base de données complète
```

## Technologies
- PHP 7.4+ · PDO · Sessions sécurisées · bcrypt
- MySQL 5.7+ · XAMPP
- HTML5 · CSS3 · JavaScript vanilla
