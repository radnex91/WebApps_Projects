# Mise en production PharmaCare

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre PharmaCare prêt pour un déploiement en production en corrigeant la configuration, la sécurité des sessions, les headers HTTP, et l'exposition des données de démo.

**Architecture:** On ajoute un fichier `config/env.php` (chargé avant `database.php`) qui détecte l'environnement et charge les valeurs appropriées. Un `.htaccess` est ajouté pour la sécurité HTTP. Les sessions sont configurées avec les flags `secure`/`httponly`/`samesite`. Les credentials de démo sont cachés derrière un flag `ENV=dev`.

**Tech Stack:** Vanilla PHP 7.4+, Apache .htaccess, pas de dépendances externes.

---

## File Map

| Action | Fichier | Rôle |
|--------|---------|------|
| Create | `.htaccess` | Headers sécurité, restriction d'accès fichiers config |
| Create | `config/env.php` | Détection environnement + valeurs par environnement |
| Modify | `config/database.php` | Lire DB_HOST/NAME/USER/PASS depuis env.php, cacher erreur PDO en prod |
| Modify | `includes/auth.php` | `session_set_cookie_params()` avec secure/httponly/samesite, `ini_set` pour erreurs |
| Modify | `includes/layout.php` | Injection headers sécurité dans `<head>` |
| Modify | `index.php` | Cacher section démo quand `ENV=prod` |
| Create | `config/env.prod.example.php` | Template de configuration production |
| Create | `404.php` | Page 404 personnalisée |

---

### Tâche 1: Créer `config/env.php` — configuration par environnement

**Files:**
- Create: `config/env.php`

- [ ] **Étape 1: Créer le fichier env.php**

```php
<?php
/**
 * Détection de l'environnement et configuration associée.
 * Chargé AVANT database.php et auth.php.
 * 
 * Pour passer en production, créer un fichier config/env.prod.php
 * ou définir la variable d'environnement PHARMACARE_ENV=prod.
 */

// ── Détection environnement ─────────────────────────────────
$env = getenv('PHARMACARE_ENV') ?: 'dev';

// Override si un fichier env.prod.php existe (prioritaire)
if (file_exists(__DIR__ . '/env.prod.php')) {
    $env = 'prod';
}

define('APP_ENV', $env);
define('IS_PROD', $env === 'prod');

// ── Configuration BDD par environnement ────────────────────
if (IS_PROD) {
    $prodConfig = require __DIR__ . '/env.prod.php';
    define('DB_HOST', $prodConfig['DB_HOST']);
    define('DB_NAME', $prodConfig['DB_NAME']);
    define('DB_USER', $prodConfig['DB_USER']);
    define('DB_PASS', $prodConfig['DB_PASS']);
    define('APP_URL', $prodConfig['APP_URL']);
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pharmacare');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('APP_URL', 'http://localhost/pharmacare');
}

// ── Constantes partagées ───────────────────────────────────
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'PharmaCare');
define('APP_VERSION', '1.0.0');
define('SESSION_NAME', 'pharmacare_session');
```

- [ ] **Étape 2: Créer le template `config/env.prod.example.php`**

```php
<?php
/**
 * TEMPLATE de configuration production.
 * 
 * Instructions :
 * 1. Copier ce fichier → config/env.prod.php
 * 2. Remplir les valeurs réelles
 * 3. Le fichier env.prod.php est dans .gitignore — il ne sera jamais commité
 */
return [
    'DB_HOST'  => 'localhost',
    'DB_NAME'  => 'pharmacare',
    'DB_USER'  => 'pharmacare_user',
    'DB_PASS'  => 'CHANGE_ME',
    'APP_URL'  => 'https://votre-domaine.com/pharmacare',
];
```

- [ ] **Étape 3: Ajouter `.gitignore` pour ignorer le fichier de config production**

Si aucun `.gitignore` n'existe, en créer un:

```
config/env.prod.php
```

- [ ] **Étape 4: Commit**

```bash
git add config/env.php config/env.prod.example.php .gitignore
git commit -m "feat: add environment-based configuration"
```

---

### Tâche 2: Modifier `config/database.php` — utiliser env.php + cacher erreurs prod

**Files:**
- Modify: `config/database.php`

Le fichier actuel définit toutes les constantes. On va retirer ces définitions (elles sont maintenant dans env.php) et ne garder que `getDB()`. On protège aussi le message d'erreur en production.

- [ ] **Étape 1: Remplacer le contenu de database.php**

```php
<?php
// ── Configuration chargée depuis env.php ─────────────────
require_once __DIR__ . '/env.php';

// ── Connexion PDO ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (IS_PROD) {
                error_log('PharmaCare DB Error: ' . $e->getMessage());
                header('HTTP/1.1 503 Service Unavailable');
                die('<div style="font-family:sans-serif;padding:40px;background:#1a0a0a;color:#ff5e57;border:1px solid #ff5e57;border-radius:8px;margin:40px auto;max-width:600px;">
                    <h3>Service temporairement indisponible</h3>
                    <p style="color:#aaa;font-size:13px;">L\'application est en maintenance. Veuillez réessayer dans quelques minutes.</p>
                </div>');
            }
            die('<div style="font-family:sans-serif;padding:40px;background:#1a0a0a;color:#ff5e57;border:1px solid #ff5e57;border-radius:8px;margin:40px auto;max-width:600px;">
                <h3>Erreur de connexion à la base de données</h3>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p style="color:#aaa;font-size:13px;">Vérifiez que XAMPP est démarré et que la base <strong>pharmacare</strong> existe.</p>
            </div>');
        }
    }
    return $pdo;
}
```

- [ ] **Étape 2: Commit**

```bash
git add config/database.php
git commit -m "fix: hide DB errors in production mode"
```

---

### Tâche 3: Sécuriser les sessions PHP

**Files:**
- Modify: `includes/auth.php`

- [ ] **Étape 1: Modifier la fonction `startSession()` dans auth.php**

Remplacer la fonction `startSession()` existante (lignes 4-9) par:

```php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurer les cookies de session AVANT session_start()
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => IS_PROD,          // HTTPS only en prod
            'httponly' => true,             // Inaccessible via JS
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($cookieParams);
        session_name(SESSION_NAME);
        session_start();
    }
}
```

- [ ] **Étape 2: Ajouter la config d'erreur PHP dans `startSession()`**

Ajouter en haut de la fonction, avant le `if`:

```php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (IS_PROD) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL);
        }
        // ... reste de la fonction
```

L'ouverture complète de la fonction doit donc être:

```php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (IS_PROD) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL);
        }
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => IS_PROD,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($cookieParams);
        session_name(SESSION_NAME);
        session_start();
    }
}
```

- [ ] **Étape 3: Commit**

```bash
git add includes/auth.php
git commit -m "feat: secure session cookies and disable error display in prod"
```

---

### Tâche 4: Créer le `.htaccess` de sécurité

**Files:**
- Create: `.htaccess`

- [ ] **Étape 1: Créer le fichier .htaccess**

```apache
# ── Bloquer l'accès aux fichiers de configuration ──────────
<FilesMatch "^(env\.prod\.php|database\.sql|patch_.*\.sql)$">
    Require all denied
</FilesMatch>

# ── Rediriger HTTP → HTTPS en production ─────────────────
# Décommenter quand le certificat SSL est installé:
# RewriteEngine On
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}/pharmacare/$1 [R=301,L]

# ── Headers de sécurité ──────────────────────────────────
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

# ── Désactiver le directory listing ──────────────────────
Options -Indexes

# ── Page d'erreur personnalisée ──────────────────────────
ErrorDocument 403 /pharmacare/index.php
ErrorDocument 404 /pharmacare/404.php
```

- [ ] **Étape 2: Commit**

```bash
git add .htaccess
git commit -m "feat: add security .htaccess"
```

---

### Tâche 5: Cacher les credentials de démo en production

**Files:**
- Modify: `index.php`

- [ ] **Étape 1: Entourer le bloc démo d'une condition dans index.php**

Remplacer le bloc des credentials de démo (lignes 658-666):

```php
            <?php if (!IS_PROD): ?>
            <div class="demo-credentials">
                <div class="demo-title">Comptes de démonstration</div>
                <div class="demo-users">
                    <span class="demo-badge">admin</span>
                    <span class="demo-badge">pharmacien</span>
                    <span class="demo-badge">caissier</span>
                </div>
                <div class="demo-pass">Mot de passe: <code>password</code></div>
            </div>
            <?php endif; ?>
```

- [ ] **Étape 2: Commit**

```bash
git add index.php
git commit -m "fix: hide demo credentials in production"
```

---

### Tâche 6: Ajouter les headers de sécurité dans le `<head>`

**Files:**
- Modify: `includes/layout.php`

- [ ] **Étape 1: Ajouter une balise meta CSP dans layout_head()**

Juste après la ligne `<meta name="viewport" content="width=device-width, initial-scale=1.0">` (ligne 93), ajouter:

```php
<?php if (IS_PROD): ?>
<meta http-equiv="Content-Security-Policy" content="default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com;">
<?php endif; ?>
```

- [ ] **Étape 2: Commit**

```bash
git add includes/layout.php
git commit -m "feat: add CSP header in production"
```

---

### Tâche 7: Créer la page 404

**Files:**
- Create: `404.php`

- [ ] **Étape 1: Créer 404.php**

```php
<?php
require_once __DIR__ . '/config/database.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page introuvable — PharmaCare</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0F172A;color:#E2E8F0;}
.container{text-align:center;padding:40px;}
h1{font-size:72px;color:#0D9488;margin-bottom:16px;}
p{color:#64748B;margin-bottom:24px;}
a{color:#5EEAD4;text-decoration:none;font-weight:600;}
</style>
</head>
<body>
<div class="container">
<h1>404</h1>
<p>La page que vous cherchez n'existe pas.</p>
<a href="<?= APP_URL ?>/dashboard.php">Retour au tableau de bord</a>
</div>
</body>
</html>
```

- [ ] **Étape 2: Commit**

```bash
git add 404.php
git commit -m "feat: add custom 404 page"
```

---

## Checklist de déploiement (à faire manuellement sur le serveur)

1. Copier `config/env.prod.example.php` → `config/env.prod.php` et remplir les vraies valeurs
2. Créer un utilisateur MySQL dédié (PAS `root`) avec permissions limitées sur la BDD `pharmacare`
3. Installer un certificat SSL (Let's Encrypt gratuit)
4. Décommenter les règles de redirection HTTP→HTTPS dans `.htaccess`
5. Changer le mot de passe par défaut `password` des comptes: `admin`, `pharmacien`, `caissier`
6. Configurer le `php.ini` de production: `session.gc_maxlifetime=1440`, `expose_php=Off`, `log_errors=On`
7. Mettre en place un backup quotidien de la BDD (tâche cron)
8. Vérifier que `config/env.prod.php` n'est PAS accessible depuis le navigateur
