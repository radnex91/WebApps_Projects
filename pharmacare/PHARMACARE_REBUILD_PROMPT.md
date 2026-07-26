# Prompt de recréation — PharmaCare (application de gestion pharmaceutique)

> **Consigne pour le modèle IA** : tu dois recréer **à l'identique** l'application **PharmaCare** décrite ci-dessous. Tu reproduis l'architecture, le schéma de base de données, les modules, les règles métier, les contraintes de sécurité, la charte de marque (branding) et le mécanisme de déploiement **avec précision**. Toute la spécification est normative : chaque section dit **exactement** ce qui doit exister. Code en PHP 8.2 (plain PHP, **sans framework, sans composer/vendor**), MySQL/MariaDB 10.4, Apache (XAMPP). L'UI est en français. Ne **jamais** simplifier, omettre ou « améliorer » une règle marquée **[OBLIGATOIRE]**. Les contraintes marquées **[NON-ÉVIDENT]** sont des pièges connus — les respecter scrupuleusement est ce qui différencie une recréation fidèle d'une recréation approximative.

---

## 0. Identité & périmètre

- **Nom de l'app** : `PharmaCare`. Marque propriété de **RADNEX** (voir §11 Branding).
- **Usage** : gestion on-premise d'une pharmacie/dépôt central multi-pharmacies : POS (vente de comptoir), stock deux-niveaux (magasin ↔ pharmacies), commandes fournisseurs, retours, caisse/sessions, comptabilité OHADA (SYSCOHADA) double-partie, clients/crédit + règlements, marketing/promo/fidélité, rapports, utilisateurs/rôles/permissions, paramètres, sauvegarde/restauration, licence par code d'activation, assistant intégré déterministe.
- **Runtime** : on-premise. Dev = sous-dossier `http://localhost/pharmacare`. Prod = racine du docroot (pas de `/pharmacare`). Doit fonctionner **dans les deux cas** (env-agnostic).
- **Version cible** : `1.2.1` (bump depuis `1.2.0`).

---

## 1. Stack & invariants d'architecture [OBLIGATOIRE]

- **PHP 8.2**, plain PHP. **Pas de front controller** : chaque page = un point d'entrée PHP autonome.
- **Pas de framework, pas de composer, pas de vendor.** Aucune dépendance externe (sauf intl pour la translittération des slugs, avec fallback).
- **MySQL/MariaDB 10.4**, `utf8mb4` / `utf8mb4_unicode_ci`, timezone `Africa/Douala` (`date_default_timezone_set`).
- **PDO singleton** via `getDB()` : `FETCH_ASSOC`, `ERRMODE_EXCEPTION`, **`EMULATE_PREPARES = false`** (vraies prepared statements).
- **Routage** = Apache `mod_rewrite` dans `.htaccess` qui traduit des clean-URLs vers `modules/<file>.php?action=…&id=…&onglet=…`. Aucun routeur PHP. Le helper `url()` (PHP) produit des URLs **compatibles** avec ces règles (la table `$ROUTES` et les règles `.htaccess` se miroirent).
- **Layout** : `layout_head($title,$activePage)` … `layout_foot()` encadrent chaque page (sidebar + topbar injectées). Les modales et scripts sont rendus dans le corps.
- **Sessions** : nom `pharmacare_session`, cookie `httponly=true`, `samesite=Lax`, `secure` **déduit du schéma de requête réel** (voir §6.2 [NON-ÉVIDENT]).
- **CSRF** : un token par session en `$_SESSION['csrf']`, vérifié sur **tout POST** via `verifyCsrf()`.
- **Permissions (RBAC)** : tables `roles` / `permissions` / `role_permissions` ; codes de permission en session (`$_SESSION['user_permissions']`) ; `hasPermission()`/`requirePermission()` sont les **seuls** gardiens. Le rôle `admin` court-circuite (toujours vrai).
- **Settings** : table clé/valeur `parametres`, cache statique in-process via `getAllParams()` ; invalider avec `paramCacheClear()` après une écriture directe.
- **Pas de CDN** : polices et icônes SVG auto-hébergées. CSP `default-src 'self'` (voir §6.7).
- **Cache-busting** CSS/JS via `?v=APP_VERSION` (bump à chaque évolution).

---

## 2. Arborescence des fichiers

```
pharmacare/
├── index.php              # Page de LOGIN (pas un routeur). startSession, login(), redirect dashboard si loggé.
├── dashboard.php          # Dispatcheur de tableau de bord par rôle -> includes/dashboard-<role>.php
├── logout.php             # logout()
├── 403.php 404.php 500.php # Pages d'erreur brandées (standalone)
├── .htaccess              # Routage, blocages, en-têtes de sécurité, pages d'erreur
├── config/
│   ├── env.php           # Détection env + constantes APP_* + error display
│   ├── env.prod.php      # (généré par l'installeur) force IS_PROD
│   ├── env.example.php / env.prod.example.php
│   ├── database.php      # getDB() PDO singleton + 503 brandé
│   ├── settings.php      # getParam/getAllParams/paramCacheClear/fmtMoney/pharmacieLogo*/fideliteActive/getThemes/getPolices/getDevises
│   ├── rate_limit.php    # Rate-limiting fichier (4 niveaux)
│   ├── comptabilite.php  # Helpers OHADA : ecritureCreate, compteFindOrCreate, journal/balance/grand-livre/résultat/bilan, clotureExercice
│   ├── licence.php       # Licence RSA (code d'activation)
│   ├── licence_integrity.php  # Manifeste signé (anti-falsification)
│   └── assistant.php     # Assistant déterministe (moteur + auto-seed + widget)
├── includes/
│   ├── auth.php          # Session, login/logout, CSRF, RBAC, helpers (e, fmt, fmtInt, today, genRef, flash, showFlash)
│   ├── audit.php         # auditLog()
│   ├── url.php           # $ROUTES, slugify(), url(), build_query()
│   ├── layout.php        # layout_head/foot, icon(), sidebar+topbar, alertes stock
│   ├── erreur.php        # afficher_erreur() — renderer autonome SANS BDD
│   ├── pagination.php   # renderPagination, paginateOffset
│   ├── sauvegarde.php   # sauve_make_backup/restore/delete/list
│   └── dashboard-{admin,caissier,pharmacien}.php  # Vues dashboards par rôle
├── modules/              # Un fichier par module métier (voir §9)
├── assets/
│   ├── css/style.css
│   ├── js/app.js         # openModal/closeModal, confirmDeletePost, showConfirm, fmtMoney, panier POS, Assistant
│   ├── fonts/            # Auto-hébergées (DM Sans/Manrope…)
│   └── img/{logo-icon.svg, pharmacie_logo.<ext>}
├── data/                 # Catalogue d'import client (CSV, XLSX) — inclus dans les bundles
├── tools/                # Build patchs/bundles, installeurs, générateurs de licence — JAMAIS dans les bundles
├── _archive/
│   ├── database.sql      # Schéma + seed PROPRE (seul fichier de _archive livré)
│   └── sql/update_*.sql  # Migrations idempotentes (update_magasin, update_pharmacies, update_unite…)
└── backups/              # Sauvegardes — JAMAIS dans les bundles
```

---

## 3. Routage (`.htaccess` + `includes/url.php`)

### 3.1 `.htaccess` — règles
- `Options -Indexes`, `ServerSignature Off`.
- **Blocage d'accès direct** → réécrit vers `403.php` (**cible relative**, pas de `/` initial) : `config/`, `sql/`, `_archive/`, `.git`, `config/.rate_limit/`, `tools/`, `backups/`.
- **Clean-URLs** : une `RewriteRule` par module. ID capturé `(\d+)` + slug décoratif optionnel `(?:-[^/]+)?` ignoré. `[L,QSA]` préserve la query résiduelle (csrf, page, q, debut, fin, mode…). **Cibles relatives.**

  Exemples à reproduire :
  ```
  RewriteRule ^dashboard/?$                                       dashboard.php [L,QSA]
  RewriteRule ^produits/new/?$                                   modules/produits.php?action=add [L,QSA]
  RewriteRule ^produits/(\d+)(?:-[^/]+)?/edit/?$                 modules/produits.php?action=edit&id=$1 [L,QSA]
  RewriteRule ^produits/importer/modele/?$                       modules/produits.php?action=import_template [L,QSA]
  RewriteRule ^caisse/(\d+)/(x|z|rapport_session)/?$             modules/caisse.php?action=$2&id=$1 [L,QSA]
  RewriteRule ^magasin/(stock|reception|historique|etat-date)/?$ modules/magasin.php?onglet=$1 [L,QSA]
  RewriteRule ^comptabilite/(saisie|journal|balance|resultat|bilan|grand-livre|cloture|plan)/?$ modules/comptabilite.php?action=$1 [L,QSA]
  RewriteRule ^assistant/?$                                      modules/assistant.php [L,QSA]
  ```
- **Catch-all 404** : `RewriteCond %{REQUEST_FILENAME} !-f / !-d` puis `RewriteRule ^ 404.php [L]` (en fin de bloc, après les routes spécifiques).
- **ErrorDocument prod-only** (voir §6.4) :
  ```
  <If "%{HTTP_HOST} !~ /localhost/i">
      ErrorDocument 403 /403.php
      ErrorDocument 500 /500.php
  </If>
  ```

### 3.2 `includes/url.php`
- `$ROUTES` : map `module => ['base'=>segment, 'idSlug'=>bool, 'actions'=>[actionValue=>urlSegment], 'onglet'=>[ongletValue=>urlSegment]]`. Seules les actions GET sont listées ; les actions POST-only (livrer, delete, saisie_save…) tombent sur le fallback `modules/x.php?action=…`.
- `slugify(string)` : translittération accents (intl `Any-Latin; Latin-ASCII; Lower()` si dispo, fallback `strtr` accent map), `strtolower`, `[^a-z0-9]+`→`-`, tronque à 80.
- `url(string $module, array $params=[], ?string $name=null): string` — construit `[APP_URL, base]`, puis id-slug (`<id>-<slug>` si idSlug et name), puis segment action (vide = fiche), puis onglet, puis query résiduelle. Ex : `url('produits', ['action'=>'edit','id'=>5], 'Doliprane 500mg')` → `http://localhost/pharmacare/produits/5-doliprane-500mg/edit`.
- `build_query(array)` : `?k=v&…` (rawurlencode, skip null/empty).

---

## 4. Constantes (`config/env.php`) [OBLIGATOIRE]

- Détection env : `getenv('PHARMACARE_ENV') ?: 'dev'` ; si `config/env.prod.php` existe → forcé à `'prod'` (le fichier prend priorité).
- `APP_ENV`, `IS_PROD` (`==='prod'`), `IS_TEST` (`==='test'`).
- Creds BDD : prod → `require env.prod.php` (retourne `DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL`) ; test → `localhost/pharmacare_test/root/''`, APP_URL `http://localhost/pharmacare` ; dev → `localhost/pharmacare/root/''`, APP_URL `http://localhost/pharmacare`.
- **Constantes partagées** : `DB_CHARSET='utf8mb4'`, `APP_NAME='PharmaCare'`, `APP_VERSION='1.2.1'`, `SESSION_NAME='pharmacare_session'`.
- Erreurs : prod → `display_errors=0`, `log_errors=1`, `E_ALL & ~E_DEPRECATED & ~E_STRICT` ; dev → `display_errors=1`, `E_ALL`. Appliqué **en haut** de `env.php` avant toute logique.

---

## 5. Helpers fondamentaux (signatures exactes)

### `includes/auth.php`
```php
sessionTimeoutSeconds(): int            // getParam('delai_inactivite_min','15')*60 ; 0 = désactivé
startSession(): void                    // cookie secure = schéma réel (§6.2) ; timeout inactivité ; regénère last_activity
isLoggedIn(): bool
requireLogin(): void                    // redirect index.php si pas loggé
requireRole(string ...$roles): void
currentUser(): array                    // ['id','nom','prenom','role','role_id','login','email'] depuis session
loadPermissions(int $roleId): array     // SELECT p.code FROM role_permissions rp JOIN permissions p ...
hasPermission(string $code): bool       // pas loggé → false ; role admin → true ; sinon in_array strict sur $_SESSION['user_permissions']
requirePermission(string $code): void   // requireLogin + redirect dashboard.php?err=access si refus
refreshUserPermissions(): void
isAdmin(): bool                         // = hasPermission('utilisateurs.gerer')
isPharmacien(): bool                    // = hasPermission('produits.ajouter')  (wrappers obsolètes)
menuActif(string $code): bool           // cache statique SELECT code,actif FROM menus ; true si actif ou code inconnu
login(string $loginInput, string $password): array   // rate-limit IP, password_verify, session_regenerate_id(true), remplit session, audit
logout(): void
csrf(): string                          // bin2hex(random_bytes(32)) en $_SESSION['csrf']
verifyCsrf(): void                      // hash_equals($_POST['csrf'],$_SESSION['csrf']) ; sinon http_response_code(403) + page brandée + redirect
e(?string $s): string                   // htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8')  [NON-ÉVIDENT : ENT_QUOTES + UTF-8 explicite]
fmt(float $n): string                   // number_format($n,2,',',' ')
fmtInt(int $n): string                   // number_format($n,0,',',' ')
today(): string                         // date('Y-m-d')
genRef(string $prefix): string          // INSERT compteurs_ref ... ON DUPLICATE KEY UPDATE compteur=LAST_INSERT_ID(compteur+1) ; SELECT LAST_INSERT_ID() ; retour "<PREFIX>-<YYYY>-<NNNN>" (4 chiffres). Fallback MAX(reference) LIKE... sur table mappée (VNT→ventes, CMD→commandes, TRF→transferts_magasin).
flash(string $msg, string $type='success'): void
showFlash(): void                       // toast <div class="alert alert-{type}"> + auto-dismiss 4s
```

### `config/settings.php`
```php
getAllParams(bool $refresh=false): array  // cache statique SELECT cle,valeur FROM parametres ; defaults si table absente
getParam(string $key, string $default=''): string
paramCacheClear(): void                   // getAllParams(true) après écriture DB
pharmacieLogoUrl(): string                // URL cache-bustée du logo pharmacie uploadé si présent, sinon ''
pharmacieLogoPath(): string
fideliteActive(): bool                    // getParam('fidelite_active','0')==='1'
fmtMoney(float $n): string                // number_format($n,0,',',' ') (0 décimales — FCFA), symbole avant/après selon devise_pos
getThemes(): array                         // ['dark-navy'=>[...8 hex...], 'dark-rose'=>[...]]
getPolices(): array / getPolicesTitres(): array
getDevises(): array                        // XAF,CDF,AOA,XOF,GNF,DZD,MAD,TND,EUR,USD => [symbol,position,label]
```
Defaults `getAllParams` si table absente : `devise='XAF'`, `devise_symbole='FCFA'`, `devise_pos='after'`, `tva='19.25'`, `app_nom='PharmaCare'`, `theme='dark-navy'`, `police='Manrope'`, `ticket_sous_titre='Gestion Pharmacie'`, `ticket_pied='Merci pour votre achat !'`, `prefix_vente='VNT'`, `caisse_fermeture_mode='manuel'`, `caisse_heure_fermeture='22:00'`, `assistant_active='1'`.

### `includes/layout.php`
- `icon(string $name, int $size=18, string $extra=''): string` — SVG inline `viewBox="0 0 24 24" fill=none stroke=currentColor`. Grande map de noms (dashboard, cart, box, pill, truck, clipboard, chart, trending, users, tag, settings, logout, search, plus, edit, trash, eye, alert, check, x, save, money, receipt, history, report, lock, unlock, calendar, filter, refresh, chevron-left, building, book, balance, megaphone, upload, download, pulse, bag, cash-register, boxes, capsule, warehouse, calculator, shield, stethoscope, percent, key, list). Inconnu → `settings`.
- `layout_head(string $title, string $activePage=''): void` — `<!DOCTYPE html>`, `<head>` (font + style.css + `:root{--teal…}` vars thème), sidebar (items gardés par `hasPermission()` && `menuActif()`), user card, logout, topbar (panneaux alertes stock + horloge), ouvre `#content`. Charge `getAllParams()` (thème, app_nom…), map de couleurs `dark-navy`/`dark-rose` (8 vars CSS chacun).
- `layout_foot(): void` — ferme `#content/#main` ; appelle `assistant_ensure_schema()` + `assistant_widget()` si actif ; injecte `window.CSRF_TOKEN`, `window.APP_URL`, `window.PHARMCARE_PAGE` ; charge `assets/js/app.js` ; horloge live.
- Sidebar : sections **Principal / Gestion / Rapports / Administration**. Copyright footer `© {année} {APP_NAME} — Tous droits réservés`.

### `assets/js/app.js`
- `openModal(id)` / `closeModal(id)` (toggle classe `open` ; clic overlay ferme).
- `confirmDelete(url,msg)` (GET redirect), `confirmDeletePost(action,id,msg)` (construit un `<form method=POST action=''>` avec `action`, `id`, `csrf=window.CSRF_TOKEN`, submit) — **pour actions destructives POST avec CSRF**.
- `showConfirm(title,message,onConfirm)` (modale `#confirm-overlay`).
- `fmtMoney(n)` (miroir client via globaux `POS_DEV_SYM`/`POS_DEV_POS`).
- Panier POS : `CART_KEY='pharmacare_cart_'+POS_USER_ID`, `saveCart/loadCart` localStorage.
- Bloc Assistant IIFE à la fin.

### `includes/audit.php`
```php
auditLog(string $action, string $details='', ?int $targetId=null, ?string $ref=null, ?string $ip=null): void
// INSERT INTO audit_log (utilisateur_id, action, details, ip, target_id, reference) ; try/catch — ne lève jamais
```

---

## 6. Sécurité — contraintes [NON-ÉVIDENT]

### 6.1 CSRF — code HTTP **403**, jamais 419/418
`verifyCsrf()` sur **tout POST**. Échec → `http_response_code(403)` + page inline « Session expirée » + `<meta refresh 3;…/index.php?timeout=1>`. **[NON-ÉVIDENT]** Sur XAMPP (Apache + mod_php), `http_response_code(419)` (et 418) est rendu en **500** au client — le SAPI ne reconnaît pas ces codes non-RFC. **Règle** : n'utiliser que 400 / 403 / 404 / 422 / 451 pour les refus.

### 6.2 Cookie `secure` = schéma de requête réel, PAS `IS_PROD`
```php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https')
        || (($_SERVER['SERVER_PORT'] ?? 0)==443);
// cookie secure = $isHttps
```
**[NON-ÉVIDENT]** En prod sur LAN en HTTP (pas de TLS), `secure=true` empêcherait le navigateur d'envoyer le cookie → session perdue → CSRF cassé. Ne **jamais** lier `secure` à `IS_PROD`. Cookie : `httponly=true`, `samesite=Lax` (Lax, pas Strict, pour le flux redirect POST login).

### 6.3 Échappement XSS `e()`
`e(?string $s): string = htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8')`. Utilisé partout en contexte texte HTML. Le renderer d'erreur (`includes/erreur.php`) ré-applique `htmlspecialchars(...,ENT_QUOTES,'UTF-8')` **indépendamment** (ne dépend pas de `e()` — doit marcher même si `auth.php` n'a pas chargé).

### 6.4 Pages d'erreur env-agnostic, SANS BDD, SANS assets
- **RewriteRule cibles relatives** (pas de `/` initial) → fonctionne en sous-dossier dev ET en racine prod. Catch-all 404 → `404.php` ; dossiers protégés → `403.php` (relative).
- **Renderer autonome** `afficher_erreur($code,$titre,$message,$souTitre,$icone)` : CSS inline, **aucun** `getParam`/`getDB`/asset externe. `APP_NAME` lu depuis `env.php` avec fallback littéral `'PharmaCare'` ; `APP_URL` reconstruit depuis `SCRIPT_NAME` si `env.php` cassé. S'affiche même si MySQL est tombé (503).
- **ErrorDocument** 403/500 **prod-only** via `<If "%{HTTP_HOST} !~ /localhost/i">` (sinon `/500.php` absolu casserait le dev sous-dossier).

### 6.5 Rate-limiting fichier (`config/rate_limit.php`)
Stockage `config/.rate_limit/` (dossier auto-créé `0700` + `.htaccess` `Deny from all` ; noms de fichiers `md5($key).json`). 4 niveaux :
- **Login** : `RATE_LIMIT_MAX_ATTEMPTS=5`, `WINDOW=900`, `LOCKOUT=900` (15 min). `rateLimitFail()` lockout progressif après 5 échecs.
- **Global** : `120 req/60s/IP`, auto-déclenché à l'inclusion (`_rateLimitGlobalGuard()`), skip CLI/assets statiques → renvoie `429` + `Retry-After` + JSON.
- **POS** : `rateLimitConsume('pos.vente:uid', 20, 60)` (20 ventes/min/user).
- **Client** : throttle export/print JS.
- `clientIp()` honore `X-Forwarded-For`/`X-Real-IP` **seulement si** `REMOTE_ADDR` ∈ `RATE_LIMIT_TRUSTED_PROXIES` (`['127.0.0.1','::1']`) — anti-spoofing. Cleanup probabiliste (~10%).

### 6.6 Prédicats SARGABLE & index
- `produit_pharmacie` : **PK composée `(produit_id, pharmacie_id)`** — toutes les requêtes stock par pharmacie frappent cette clé (SARGABLE).
- Index FK couvrants sur **toutes** les colonnes FK ; clés business uniques (`roles.code`, `permissions.code`, `menus.code`, `produits.reference`, `ventes.reference`, `commandes.reference`, `compteurs_ref(prefix,annee)`).
- Prédicats de date SARGABLE (`created_at >= CURDATE()`) avec index `idx_*_date`.
- **[NON-ÉVIDENT]** Recherche par nom de produit : `LIKE "%$q%" ESCAPE '\\\\'` — full-scan **volontaire** sur le petit catalogue. Le `ESCAPE '\\\\'` échappe `\`,`%`,`_` (défense contre l'injection wildcard) — **ne pas le retirer**.

### 6.7 En-têtes de sécurité (`.htaccess`)
`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`. CSP stricte : `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`. (`'unsafe-inline'` requis : icônes SVG inline + scripts de vue.) HSTS commenté (à activer après TLS). `<FilesMatch>` deny `*.sql|*.log|*.md|*.bak|*.env|*.ini` + scripts `alter_db|_mvt_check`.

---

## 7. Schéma de base de données (complet)

Toutes `ENGINE=InnoDB`, `utf8mb4_unicode_ci`. Source de vérité = `_archive/database.sql`.

### 7.1 Référentiels
- **`categories`** : `id AI PK`, `nom VARCHAR(100) UNIQUE`, `couleur VARCHAR(7) DEFAULT '#00c9a7'`. (15 seed.)
- **`fournisseurs`** : `id AI PK`, `nom`, `contact`, `telephone`, `email`, `adresse TEXT`, `ville`, `actif TINYINT DEFAULT 1`, `created_at`. Soft-delete via `actif`.
- **`clients`** : `id AI PK`, `nom VARCHAR(150) NOT NULL`, `telephone VARCHAR(20)`, `actif TINYINT DEFAULT 1`, `created_at` (collation `utf8mb4_general_ci`).
- **`pharmacies`** : `id AI PK`, `nom VARCHAR(150)`, `adresse VARCHAR(255)`, `telephone VARCHAR(30)`, `actif TINYINT DEFAULT 1`, `created_at`. **id=1 = PRINCIPALE, ne peut pas être désactivée** (le code exclut `id<>1` du toggle).
- **`caisses`** : `id AI PK`, `nom VARCHAR(100)`, `actif TINYINT DEFAULT 1`, `created_at`.

### 7.2 Produits & stock
- **`produits`** (médicaments) :
  ```
  id AI PK
  nom VARCHAR(200) NOT NULL
  reference VARCHAR(50) UNIQUE                 -- nullable ; vide -> NULL (pour éviter clash UNIQUE)
  unite VARCHAR(30) DEFAULT NULL               -- [NOUVEAU 1.2.1] unité/conditionnement (comprimé, boîte, flacon…) — saisi via datalist
  categorie_id INT FK->categories ON DELETE SET NULL
  fournisseur_id INT FK->fournisseurs ON DELETE SET NULL
  description TEXT
  stock INT DEFAULT 0                           -- MIROIR pharmacie principale (id=1), ne JAMAIS écrire directement pour un ravitaillement
  stock_magasin INT DEFAULT 0                   -- dépôt central (indépendant)
  seuil_magasin INT DEFAULT 20
  seuil_alerte INT DEFAULT 10
  prix_achat DECIMAL(10,2) DEFAULT 0.00         -- coût (COGS)
  prix_vente DECIMAL(10,2) DEFAULT 0.00
  tva DECIMAL(5,2) DEFAULT 0.00                 -- TVA par produit ; défaut 9%
  date_expiration DATE
  actif TINYINT(1) DEFAULT 1                    -- soft delete (archive)
  created_at DATETIME DEFAULT current_timestamp()
  KEY categorie_id, fournisseur_id
  ```
- **`produit_pharmacie`** (stock par pharmacie — **la vraie source**) :
  ```
  produit_id INT FK->produits ON DELETE CASCADE
  pharmacie_id INT FK->pharmacies ON DELETE CASCADE
  stock INT NOT NULL DEFAULT 0
  seuil_alerte INT NOT NULL DEFAULT 10
  PRIMARY KEY (produit_id, pharmacie_id)        -- [NON-ÉVIDENT] PK composée
  ```
  Upserts : `ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)`.
- **`mouvements_stock`** (mouvements pharmacie) : `id AI PK`, `produit_id INT FK->produits ON DELETE SET NULL`, `type ENUM('entrée','sortie','ajustement')`, `quantite INT`, `motif VARCHAR(255)`, `utilisateur_id INT FK->utilisateurs ON DELETE SET NULL`, `created_at`. Index `idx_msto_date`.
- **`mouvements_magasin`** (mouvements dépôt) : idem + `transfert_id INT NULL` (lien vers `transferts_magasin`, sans FK). Index `idx_mmag_date`.
- **`transferts_magasin`** (entête transfert dépôt→pharmacie) : `id AI PK`, `reference VARCHAR(20) UNIQUE` (`genRef('TRF')`), `utilisateur_id INT FK->utilisateurs`, `note TEXT` (suffixe ` → NomPharmacie`), `created_at`, `pharmacie_id INT FK->pharmacies` (cible).
- **`transfert_lignes`** : `id AI PK`, `transfert_id INT FK->transferts_magasin ON DELETE CASCADE`, `produit_id INT FK->produits ON DELETE SET NULL`, `produit_nom VARCHAR(200)` (snapshot), `quantite INT`.

### 7.3 Ventes & retours
- **`ventes`** (entête) :
  ```
  id AI PK ; reference VARCHAR(20) UNIQUE (genRef('VNT') ou prefix_vente)
  client_nom VARCHAR(150) ; client_telephone VARCHAR(20) ; client_id INT FK->clients
  caissier_id INT FK->utilisateurs ON DELETE SET NULL
  sous_total DECIMAL(10,2) ; tva_total DECIMAL(10,2) ; total DECIMAL(10,2)
  mode_paiement VARCHAR(50) NOT NULL  -- 'espèces','carte','chèque','assurance','crédit'
  statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé'
  montant_recu DECIMAL(10,2) ; monnaie DECIMAL(10,2) ; note TEXT ; created_at
  est_annulee TINYINT(1) DEFAULT 0
  remise_pct DECIMAL(5,2) DEFAULT 0.00 ; remise_montant DECIMAL(10,2) DEFAULT 0.00
  autorise_par INT FK->utilisateurs ON DELETE SET NULL   -- l'approbateur de la remise
  pharmacie_id INT FK->pharmacies                       -- pharmacie de la session (stock débité)
  ```
- **`vente_lignes`** : `id AI PK`, `vente_id INT FK->ventes ON DELETE CASCADE`, `produit_id INT FK->produits ON DELETE SET NULL`, `produit_nom` (snapshot), `quantite INT`, `prix_unitaire DECIMAL(10,2)`, `tva DECIMAL(5,2) DEFAULT 9.00`, `total_ligne DECIMAL(10,2)`.
- **`retours_vente`** : `id AI PK`, `reference VARCHAR(20) UNIQUE` (`genRef('RET')`), `vente_id INT FK->ventes`, `utilisateur_id INT FK->utilisateurs`, `date_retour DATE`, `montant_ht/tva/total DECIMAL(10,2)`, `cout_achat_total DECIMAL(10,2)`, `mode_remboursement ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces'`, `note`, `created_at`.
- **`retour_vente_lignes`** : `id AI PK`, `retour_id INT FK->retours_vente ON DELETE CASCADE`, `vente_ligne_id INT` (pas de FK), `produit_id INT FK->produits ON DELETE SET NULL`, `produit_nom` (snapshot), `quantite`, `prix_unitaire` (P.U. NET après facteur remise), `tva`, `total_ligne` (HT net), `cout_achat DECIMAL(10,2)`.

### 7.4 Commandes
- **`commandes`** : `id AI PK`, `reference VARCHAR(20) UNIQUE` (`genRef('CMD')`), `fournisseur_id INT FK->fournisseurs`, `utilisateur_id INT FK->utilisateurs`, `statut ENUM('en_attente','en_cours','livrée','annulée') DEFAULT 'en_attente'`, `date_commande DATE`, `date_livraison DATE`, `note TEXT`, `created_at`, `montant_total DECIMAL(10,2)`.
- **`commande_lignes`** : `id AI PK`, `commande_id INT FK->commandes ON DELETE CASCADE`, `produit_id INT FK->produits ON DELETE SET NULL` (nullable — désignation libre), `designation VARCHAR(200) NOT NULL`, `quantite INT DEFAULT 1`, `prix_unitaire DECIMAL(10,2)`, `created_at`.

### 7.5 Caisse
- **`sessions_caisse`** : `id AI PK`, `caisse_id INT FK->caisses`, `caissier_id INT FK->utilisateurs`, `fond_initial DECIMAL(10,2) DEFAULT 0`, `date_ouverture DATETIME NOT NULL`, `date_fermeture DATETIME`, `solde_attendu DECIMAL(10,2)`, `solde_reel DECIMAL(10,2)`, `ecart DECIMAL(10,2)`, `statut ENUM('ouverte','fermée') DEFAULT 'ouverte'`, `pharmacie_id INT FK->pharmacies`. **Contraintes applicatives** : 1 session ouverte par caisse ET 1 par caissier.
- **`mouvements_caisse`** : `id AI PK`, `session_id INT FK->sessions_caisse`, `type ENUM('entrée','sortie')`, `montant DECIMAL(10,2)`, `motif VARCHAR(255)`, `moyen ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces'`, `reference_vente VARCHAR(20)`, `created_at`.

### 7.6 Règlements & remise
- **`reglements`** : `id AI PK`, `client_id INT FK->clients`, `vente_id INT FK->ventes` (nullable), `montant DECIMAL(10,2)`, `mode_paiement ENUM('espèces','carte','chèque','mobile') DEFAULT 'espèces'`, `note`, `date_reglement DATETIME` (collation `utf8mb4_general_ci`). **FIFO** : à l'insertion, recalcule `ventes.statut_paiement` sur toutes les ventes à crédit/partielles du client.
- **`codes_remise`** : `id AI PK`, `code VARCHAR(10) UNIQUE` (6 chars, alphabet **exclut I,O,0,1**), `created_by INT FK->utilisateurs` (l'approbateur), `created_at`, `expires_at DATETIME NOT NULL` (`created_at + remise_code_ttl_min`, défaut 15 min), `remise_pct DECIMAL(5,2) DEFAULT 0.00` (**le taux figé à la génération**), `used TINYINT DEFAULT 0`, `used_at DATETIME`, `used_vente_id INT FK->ventes ON DELETE SET NULL`, `used_remise_pct DECIMAL(5,2)`. **Usage unique**.
- **`remise_approbateurs`** : `id AI PK`, `utilisateur_id INT UNIQUE FK->utilisateurs ON DELETE CASCADE`, `actif TINYINT DEFAULT 1`, `added_by INT`, `created_at`.

### 7.7 Marketing
- **`campagnes_promo`** : `id AI PK`, `nom VARCHAR(200)`, `description TEXT`, `type ENUM('pourcentage','montant_fixe') DEFAULT 'pourcentage'`, `valeur DECIMAL(10,2)`, `date_debut DATE`, `date_fin DATE`, `actif TINYINT DEFAULT 1`, `created_at`.
- **`promo_produits`** : `id AI PK`, `campagne_id INT FK->campagnes_promo ON DELETE CASCADE`, `produit_id INT FK->produits ON DELETE CASCADE`.
- **`fidelite_points`** : `id AI PK`, `client_id INT FK->clients`, `points INT`, `type ENUM('gagné','utilisé') DEFAULT 'gagné'`, `reference`, `note`, `created_at`. (Gated par `fidelite_active`.)

### 7.8 Comptabilité OHADA (SYSCOHADA)
- **`plan_comptable`** : `id AI PK`, `compte VARCHAR(15) UNIQUE` (code OHADA, ex `5711`), `intitule VARCHAR(200)`, `classe TINYINT` (1..7), `nature ENUM('debit','credit') DEFAULT 'debit'`, `compte_parent INT FK->plan_comptable` (auto-référence hiérarchique), `actif TINYINT DEFAULT 1`, `created_at`. **Seed plan complet** : classe 1 (101, 106, 12…), 2 (218, 281…), 3 (311, **3111=Médicaments en stock**, 3112), 4 (401/4011 Fournisseurs, 411/4111 Clients-Assurance, 4112 Clients-Crédit, 421 Personnel, 431 Sécurité sociale, 441/4411 TVA collectée 19.25%, 445/4451 TVA récupérable 19.25%, 471 Compte d'attente), 5 (511 Chèques, 512 Banque, 571/5711 Caisse principale, 581 Virements internes), 6 (6011 Achats médicaments, 6031 Variation stocks, 623 Publicité, 641 Salaires, 681 Dotations…), 7 (7011 Ventes médicaments, 708, 751, 758, 771, et **7119 = Rabais, remises et ristournes accordés** nature=debit — absorbe les remises).
- **`exercices`** : `id AI PK`, `code VARCHAR(9) UNIQUE` (ex `2026`), `libelle`, `date_debut DATE`, `date_fin DATE`, `cloture TINYINT DEFAULT 0`, `created_at`.
- **`ecritures`** (entête) : `id AI PK`, `reference VARCHAR(30) UNIQUE` (`EC-YYYY-NNNN`), `libelle VARCHAR(255)`, `date_ecriture DATE`, `exercice_id INT` (pas de FK), `utilisateur_id INT FK->utilisateurs`, `source VARCHAR(30) DEFAULT 'manuel'` (valeurs : `vente`,`commande`,`stock`,`caisse`,`manuel`,`cloture`,`retour`), `source_ref VARCHAR(30)`, `verrouillee TINYINT DEFAULT 0`, `created_at`.
- **`ecriture_lignes`** : `id AI PK`, `ecriture_id INT FK->ecritures ON DELETE CASCADE`, `compte_id INT FK->plan_comptable`, `debit DECIMAL(12,2)`, `credit DECIMAL(12,2)`, `libelle_ligne VARCHAR(255)`. **Double-partie** : Σdébit = Σcrédit (vérifié dans `ecritureCreate`).

### 7.9 RBAC & système
- **`roles`** : `id AI PK`, `code VARCHAR(60) UNIQUE`, `libelle VARCHAR(100)`, `est_systeme TINYINT DEFAULT 0` (les rôles système ne se suppriment pas). Seed : 1 admin, 2 pharmacien, 3 caissier, 4 manager, 5 superviseur (système) ; 6 directeur, 7 informaticien, 8 régisseur (custom, `est_systeme=0`).
- **`permissions`** : `id AI PK`, `code VARCHAR(80) UNIQUE`, `libelle`, `module`, `created_at`. ~58 codes groupés par module (voir §9 + §7.9.1).
- **`role_permissions`** : PK `(role_id, permission_id)` FKs cascade. Role 1 (admin) = **toutes** les permissions.
- **`utilisateurs`** : `id AI PK`, `nom`, `prenom`, `email UNIQUE`, `login UNIQUE`, `mot_de_passe VARCHAR(255)` (bcrypt), `role_id INT FK->roles DEFAULT 3`, `actif TINYINT DEFAULT 1`, `derniere_connexion DATETIME`, `created_at`. Seed `admin`/`pharmacien`/`caissier` avec `mot_de_passe='LOCKED_INSTALL'` (voir §12.4).
- **`menus`** : `code VARCHAR(60) PK`, `libelle VARCHAR(120)`, `actif TINYINT DEFAULT 1`, `position INT`. ~22 entrées. `retours` `actif=0` par défaut.
- **`audit_log`** : `id AI PK`, `utilisateur_id INT FK->utilisateurs ON DELETE SET NULL`, `action VARCHAR(80)`, `details VARCHAR(500)`, `ip VARCHAR(45)`, `target_id INT`, `reference VARCHAR(80)`, `created_at`.
- **`compteurs_ref`** : PK `(prefix VARCHAR(8), annee SMALLINT)`, `compteur INT`. Seed CMD/TRF/VNT 2026. Utilisé par `genRef()`.
- **`parametres`** : `cle VARCHAR(60) PK`, `valeur TEXT NOT NULL`, `label VARCHAR(120)`, `groupe VARCHAR(60) DEFAULT 'general'`. Voir §7.9.2.

#### 7.9.1 Codes de permission (à seedder)
`dashboard.voir` ; `vente.creer`, `remise.approuver` ; `stock.voir`, `stock.ajuster` ; `produits.voir/ajouter/modifier/archiver` ; `fournisseurs.voir/ajouter/modifier/supprimer` ; `commandes.voir/creer/modifier` ; `ventes_hist.voir` ; `rapports.voir` ; `utilisateurs.voir/gerer` ; `categories.voir/gerer` ; `parametres.voir/gerer` ; `roles.voir/gerer` ; `caisse.voir/gerer/ouvrir` ; `comptabilite.voir/saisie/plan` ; `clients.voir/ajouter/modifier/supprimer/paiements` ; `magasin.voir/gerer` ; `retours.gerer` ; `remise.approbateurs.gerer` ; `pharmacies.voir/gerer` ; `menus.voir/gerer` ; `assistant.utiliser` (auto-seed par le moteur).
> **[OBLIGATOIRE — à seedder en plus]** (référencés par le code mais absents du dump permissions) : `marketing.voir`, `marketing.promos`, `marketing.fidelite`, `rapports_caissier.voir`.

#### 7.9.2 Paramètres seed (exclure `licence_*` du bundle)
`app_nom`, `devise='XAF'`, `devise_symbole='FCFA'`, `devise_pos='after'`, `tva='0.00'` (mais fallback code `'19.25'`), `theme='dark-rose'`, `police`/`police_titre='Manrope'`, `prefix_vente='VNT'`, `pharmacie_adresse`/`pharmacie_telephone`/`pharmacie_nif`, `ticket_sous_titre`/`ticket_pied`/`ticket_nb_copies` (1–5, défaut 2), `caisse_fermeture_mode='manuel'`, `caisse_heure_fermeture='18:00'`, `remise_code_ttl_min='15'`, `remise_max_pct='100'`, `fidelite_active='1'`, `delai_inactivite_min='20'`, `assistant_active='1'`.

---

## 8. Règles métier transversales [OBLIGATOIRE]

### 8.1 Modèle de stock deux-niveaux (NE PAS CONFONDRE) — **[NON-ÉVIDENT]**
1. **`produits.stock_magasin`** = stock du **MAGASIN** (dépôt central). Ravitaillé par : livraison de commande fournisseur (`commandes.php` validation → `stock_magasin += qte` + `mouvements_magasin('entrée')`), réception/ajustement magasin (`magasin.php`), **import CSV** (`produits.php`). Diminué par les **transferts** vers une pharmacie (`magasin.php` : `stock_magasin -= qte` + `mouvements_magasin('sortie', transfert_id)`).
2. **`produit_pharmacie.stock`** = stock par **PHARMACIE**. Ravitaillé par **transfert** depuis le magasin. Diminué par les **ventes** (`vente.php`).
3. **`produits.stock`** = **MIROIR** de la pharmacie principale (`p.stock = pp.stock WHERE pp.pharmacie_id=1`), maintenu par sync dans `magasin.php`, `vente.php`, `pharmacies.php`. Lu par les modules legacy (`stock.php`, dashboards, alertes `layout.php`). **Ne JAMAIS écrire directement `produits.stock` pour un ravitaillement** — c'est le miroir, pas une source.
4. **Flux métier** : fournisseur → commande → magasin → transfert → pharmacie → vente. Court-circuiter en écrivant directement le stock pharmacie casse la traçabilité.
5. **Décrément atomique** : `UPDATE … SET stock=stock-? WHERE … AND stock>=?` + check `rowCount()===0` (race/insuffisance).
6. **Import CSV ravitaille le MAGASIN** (pas les pharmacies) : `stmtIns` met `stock=0, stock_magasin=$stock` ; `stmtUpd` fait `stock_magasin = stock_magasin + $stock` (incrément) ; + `INSERT mouvements_magasin('entrée')` si qty>0 ; transaction.

### 8.2 TVA
- TVA globale en `parametres.tva` (seed `0.00`, fallback code `'19.25'`), lu via `getParam('tva','19.25')`.
- `produits.tva` défaut **9.00**.
- À la vente : `tvaRate = getParam('tva','19.25')/100` ; `vente_lignes.tva` stocke le taux appliqué. Les libellés de comptes TVA hardcodent « 19.25% » (`4411` collectée, `4451` récupérable) quel que soit le taux réel. **TVA masquée à 0** sur les 3 chemins de ticket (POS 80mm, POS A4, réimpression `ventes_hist`).

### 8.3 Codes remise + flux d'approbation
- Admin ajoute des utilisateurs à `remise_approbateurs` (`remise_approbateurs.php`).
- Un approbateur génère dans `remise_codes.php` un code 6 chars (alphabet exclut I/O/0/1, retry jusqu'à unique) lié à un `remise_pct` et un TTL (`remise_code_ttl_min`, défaut 15 min).
- Au POS : caissier saisit pct + sélectionne approbateur (`autorise_par`) + code. AJAX valide ; au submit, serveur re-valide `codes_remise WHERE code=? AND created_by=? AND used=0 AND expires_at>NOW()` et **écrase `remise_pct` par le pct stocké sur le code** (la valeur caissier est **ignorée**). Après commit, `codes_remise` marqué `used=1, used_vente_id, used_remise_pct`. **Usage unique**.
- Compta : `7011` crédité au HT brut ; `7119` (Rabais/remises/ristournes, classe 7 nature=debit) débité du `remise_montant` HT pour rééquilibrer (débit caisse/client = net TTC).
- `ventes.autorise_par` FK→`utilisateurs.id`. `remise_max_pct` plafonne (défaut 100).

### 8.4 Comptabilité OHADA (double-partie) — `config/comptabilite.php`
```php
ecritureCreate(PDO $db, string $libelle, string $date, array $lignes, string $source, string $sourceRef, int $userId): int
// $lignes = [[compte_id, debit, credit, libelle_ligne], ...] ; vérifie |Σd−Σc|<=0.01 ;
// trouve l'exercice ouvert pour $date (refuse exercice clôturé) ; génère ref EC-YYYY-NNNN ;
// INSERT ecritures + ecriture_lignes ; DOIT tourner dans une transaction PDO ouverte.
compteFindOrCreate($db,$code,$intitule,$classe,$nature): int   // lazy-création (4112,7119,5711,471…)
planComptableAll() ; exercicesAll() ; journalGet(debut,fin,source) ; ecritureLignes(ecritureId)
balanceGet(debut,fin) ; grandLivreGet(compteId,debut,fin) ; compteResultat(debut,fin) ; bilanGet(debut,fin)
clotureExercice($db,$exerciceId,$userId)   // solde classes 6/7 -> compte 12 ; verrouillee=1 ; exercices.cloture=1 ; crée exercice suivant
```
Écritures automatiques par `source` :
- **vente** : D caisse/4112 / C 7011 / C 4411 ; D 7119 si remise ; + **sortie stock** D 6031 / C 3111 à `prix_achat × qty`.
- **commande** (livraison) : D 6011 / D 4451 / C 4011 (TTC) ; + **entrée stock** D 3111 / C 6031 (HT).
- **retour** : D 7011 / D 4411 / C contrepartie (contrepassation selon mode) ; + **retour stock** D 3111 / C 6031.
- **caisse** : fond initial D 5711 / C 471 ; mvt manuel D 5711/C 471 (entrée) ou D 471/C 5711 (sortie).
- **stock** : `stock_ajust` D 3111/C 6031 (entrée) ou D 6031/C 3111 (sortie).
- **manuel** : `comptabilite.php` saisie.
- **cloture** : `clotureExercice()`.

Rapports (vues read-only `comptabilite.php`) : Journal, Balance, Grand-livre, Compte de résultat (classes 6/7), Bilan (actif : classes 2/3/5 + 411/445/471 débiteurs ; passif : classe 1 + 401/441 créditeurs + résultat réinjecté au compte 12).

### 8.5 Tickets / Bons MINSANTÉ
- `magasin.php?bon=<transfert_id>` : bon de ravitaillement A4 standalone (modèle MINSANTÉ), logo pharmacie, lignes, qtés, réf, date, signatures magasinier/pharmacien.
- `commandes.php?action=bon&id=` : bon de commande imprimable.
- `vente.php?receipt=<ref>` + `ventes_hist.php` : tickets POS (80mm + A4) via params `app_nom`/`ticket_sous_titre`/`ticket_pied`/`ticket_nb_copies` + `pharmacie_adresse`/`telephone`/`nif`. **Impression N copies** (1 dialogue → Exemplaire Client + Caisse séparés par coupure), `ticket_nb_copies` (1–5, défaut 2).

### 8.6 Références
`genRef($prefix)` → `"<PREFIX>-<YYYY>-<NNNN>"`. Préfixes : `VNT` (ventes, ou `prefix_vente`), `CMD` (commandes), `TRF` (transferts), `RET` (retours, hardcodé), `EC-` (écritures, interne).

---

## 9. Modules (`modules/*.php`) — permissions + actions POST

Chaque module : `require auth.php; require layout.php; require config/settings.php;` (compta → `require config/comptabilite.php`), `requirePermission('…')`, dispatch sur `$_GET['action']`/`$_GET['onglet']`, `verifyCsrf()` sur **tout POST**.

| Module | Permission | Actions POST clés / comportement |
|---|---|---|
| **produits.php** | `produits.voir` (écritures : `ajouter`/`modifier`/`archiver`) | `action=import_template` (CSV template BOM, `;`) ; `action=import` (`produits.ajouter`, `doublons=skip\|update`, mapping alias FR, auto-délimiteur, **stock→stock_magasin**, cat/fourn inconnu→null+avert) ; `action=delete` (`produits.archiver` → `actif=0`) ; add/edit INSERT/UPDATE produits (nom, reference, **unite**, categorie_id, fournisseur_id, description, stock, seuil_alerte, prix_achat, prix_vente, date_expiration). |
| **vente.php** | `vente.creer` | `ajax_remise=1` (JSON validation code remise) ; POST `cart_data` : rate-limit (20/60s/user), **vérif cap licence**, **relecture prix serveur** (anti-manip), HT/TVA/remise, INSERT `ventes`+`vente_lignes`, débit `produit_pharmacie.stock` (atomique `stock>=?`), sync `produits.stock` si pharmacie=1, `mouvements_stock('sortie')`, `mouvements_caisse`, **ecritureCreate** (D caisse/4112 / C 7011 / C 4411 ; D 7119 remise ; D 6031/C 3111 sortie stock à prix_achat), `codes_remise.used=1`. Session caisse ouverte requise si `caisse.ouvrir`. Vente à crédit requiert un client existant. Redirect `?receipt=REF`. |
| **magasin.php** | `magasin.voir` (actions : `magasin.gerer`) | `action=transfert` (dépôt→pharmacie : validate cible active, `stock_magasin` suffisant, `genRef('TRF')`, INSERT `transferts_magasin`+`transfert_lignes`, `stock_magasin -= qte`, upsert `produit_pharmacie`, sync si pharmacie=1, `mouvements_magasin('sortie',transfert_id)`+`mouvements_stock('entrée')`, émet `?bon=`) ; `action=retour` (pharmacie→magasin inverse) ; `action=reception` (entrée/ajustement manuel `stock_magasin`, delta signé, garde-fou négatif) ; `action=ajustement_lot` (**set absolu** `stock_magasin` pour une liste de produits cochés + **une seule quantité** cible ; mouvement `ajustement` signé par produit dont le stock change ; transaction + audit) ; onglets `stock`/`reception`/`historique`/`etat-date` (reconstitution rétroactive : `stock_à_D = stock_actuel − Σ mouvements postérieurs`). |
| **commandes.php** | `commandes.voir` (`creer`/`modifier`) | `action=add\|edit` (insert/update `commandes`+`commande_lignes`, bloque édition si `livrée`/`annulée`) ; `action=livrer` (`commandes.modifier` : `statut='livrée'`, split HT/TVA, ecritureCreate achat D 6011/D 4451/C 4011, entrée stock D 3111/C 6031, `stock_magasin += qte` + `mouvements_magasin('entrée')` par ligne) ; `action=livrer_form` (checklist) ; `action=bon` (imprimable) ; `action=delete`. |
| **retours.php** | `retours.gerer` | `action=create` : charge vente+lignes, qty déjà retournée par ligne, valide `qte<=reste`, applique **facteur remise** (`pu_net = prix_unitaire*(1−remise_pct/100)`), `genRef('RET')`, INSERT `retours_vente`+`retour_vente_lignes`, `produits.stock += qte` (retour pharmacie), `mouvements_stock('entrée')`, **contrepassation OHADA** D 7011/D 4411 / C contrepartie (caisse/banque/4111/4112 selon mode), annulation stock D 3111/C 6031, `mouvements_caisse('sortie')` si remboursement cash. `action=new&id=`, `action=detail&id=`. |
| **caisse.php** | `caisse.voir` OU `caisse.ouvrir` | `action_type=creer_poste` (`caisse.gerer`) ; `action_type=ouvrir` (`caisse.ouvrir` : validate caisse+pharmacie actives, 1 session ouverte par caisse et par caissier, INSERT `sessions_caisse`, ecritureCreate D 5711/C 471) ; `action_type=cloturer` (solde_attendu=fond+entrées−sorties, solde_reel, écart, `statut='fermée'` ; fermeture forcée admin = `motif_force`) ; `action_type=mouvement` (dépôt/retrait sur session ouverte, ecritureCreate entrée D 5711/C 471 ou sortie D 471/C 5711). Auto-close si `caisse_fermeture_mode='auto'` + `caisse_heure_fermeture`. |
| **comptabilite.php** | `comptabilite.voir` | `action=saisie_save` (`comptabilite.saisie` : ecritureCreate manuel, min 2 lignes équilibrées) ; `action=plan_new`/`plan_update` (`comptabilite.plan`) ; `action=cloture_exec` (`comptabilite.plan` : `clotureExercice()`) ; vues : `dashboard`,`saisie`,`journal`,`balance`,`resultat`,`bilan`,`grand-livre`,`cloture`,`plan`,`plan_edit`. |
| **pharmacies.php** | `pharmacies.voir` (`gerer`) | `action=toggle` (`UPDATE pharmacies SET actif=1−actif WHERE id<>1`) ; `action=update_stock` (upsert `produit_pharmacie` absolu ; resync `produits.stock` si pharmacie=1) ; add/edit (nouvelle pharmacie → crée `produit_pharmacie` à 0 pour tous produits actifs). |
| **stock.php** | `stock.voir` | Read-only. Filtres `filtre=tous\|alerte\|rupture\|ok`, `cat`. Stats : refs totales, valeur stock `SUM(stock*prix_achat)`, alerte, ruptures. Bouton « Ajuster » → `stock_ajust` (`stock.ajuster`). |
| **stock_ajust.php** | `stock.ajuster` | `barcode_lookup` (recherche par référence → redirect `?id=`) ; POST (avec id) `type∈{entrée,sortie,ajustement}`, calcule newStock, `UPDATE produits.stock`, `INSERT mouvements_stock`, si `prix_achat>0` **ecritureCreate** source='stock' (D 3111/C 6031 ou D 6031/C 3111 à `pa×delta`). `auditLog('stock.adjust')`. |
| **clients.php** | `clients.voir` | `action=add\|edit` (`ajouter`/`modifier` : nom majuscules) ; `action=reglement` (`clients.paiements` : INSERT `reglements`, ecritureCreate D trésorerie (5711/512/511/512-mobile) / C 4112, **FIFO** recompute `statut_paiement`) ; `action=disable\|enable` (`clients.supprimer`). Vues liste, `detail` (relevé + dette restante), add/edit. |
| **categories.php** | `categories.gerer` | `action=delete` (bloque si produits) ; INSERT/UPDATE (nom, couleur hex validée). |
| **fournisseurs.php** | `fournisseurs.voir` (`ajouter`/`modifier`/`supprimer`) | add/edit ; `action=delete` (`actif=0`, bloque si `nb_produits>0`). |
| **marketing.php** | `marketing.voir` | `action=add-promo\|edit-promo` (`marketing.promos` : INSERT/UPDATE `campagnes_promo` + replace `promo_produits`) ; `action=add-points` (`marketing.fidelite`, gated `fidelite_active`) ; `action=toggle` (`marketing.promos`). |
| **rapports.php** | `rapports.voir` | Read-only. Sélecteur période (`aujourdhui`/`7j`/`30j`/`trimestre`/`annee`/`perso`/`mois`). Agrégats ventes, top produits, répartition modes paiement, valeur stock, TVA collectée. |
| **rapports_caissier.php** | `rapports_caissier.voir` | Read-only, scopé `caissier_id=current_user`. Même sélecteur ; ventes count/total/avg/max + tendance période précédente. |
| **remise_codes.php** | **membership** `remise_approbateurs` (actif=1) — **pas** une permission | `action=generer` (valide `remise_pct`≤`remise_max_pct`, génère code 6 chars, INSERT `codes_remise` `expires_at=NOW()+ttl`) ; `action=show&id=` (affiche code + qr + validité). |
| **remise_approbateurs.php** | `remise.approbateurs.gerer` | `action=ajouter` (`INSERT IGNORE`) ; `action=retirer` (DELETE). |
| **utilisateurs.php** | `utilisateurs.gerer` | `action=toggle` (`actif=1−actif`, pas self-toggle) ; add/edit (bcrypt si mdp fourni ; **anti-escalation** : ne peut pas changer son propre `role_id` ; refresh session perms si self-edit). |
| **roles.php** | `roles.voir` (`gerer`) | `action=delete` (only `est_systeme=0`, réassigne users→3, delete role_permissions+role) ; update perms ; `action=add` (slugify libelle→code, `est_systeme=0`). |
| **menus.php** | `menus.voir` (`gerer`) | `action=toggle` ; `action=all`. Vue groupée par sections. |
| **parametres.php** | `parametres.gerer` | POST upsert `parametres` (app_nom, devise, tva, thème, polices, pharmacie_adresse/tel/nif, ticket_*, prefix_vente, caisse_fermeture_mode/heure, assistant_active). Upload `pharmacie_logo` (PNG/JPG/WebP/SVG ≤1Mo, `assets/img/pharmacie_logo.<ext>`, purge autres exts) + retrait. |
| **sauvegarde.php** | `parametres.gerer` (logique `includes/sauvegarde.php`) | `download`+token (CSRF) ; `action=backup` ; `action=restore` (`confirm==='RESTAURER'`, backup pré-restauration auto, `sauv_restore()`) ; `action=delete`. **Restauration = destructive** (permission + CSRF + « RESTAURER » + audit + backup pré). |
| **licence.php** | `parametres.gerer` | POST `code` → `licence_apply_code()`. Affiche instance id, cap, usage (`licence_usage()` = count `vente_lignes`), restant, contrôle intégrité. |
| **ventes_hist.php** | `ventes_hist.voir` | Read-only. Filtres date + mode ; non-admin restreint à `caissier_id` soi-même. Pagination 25/page. Stats + modale détail. Tickets via params. |
| **assistant.php** | `assistant.utiliser` (auto-seed) | Endpoint `?action=chat` POST (csrf dans le corps), JSON, rate-limit `30/60s`, `auditLog('assistant.chat')`. Voir §10. |

---

## 10. Assistant IA déterministe (`config/assistant.php` + `modules/assistant.php`)

**[NON-ÉVIDENT]** Pas de LLM, pas de serveur IA, **pas d'appel réseau sortant**. Moteur déterministe : (1) scoring d'intention par mots-clés/synonymes FR (token-exact pour mots uniques — évite « ca » dans « caisse » ; substring pour multi-mots), (2) exécution d'un outil read-only en liste blanche, (3) réponse template + données réelles + suggestions cliquables.

- **Ordre de détection** : `help` (« comment… ») **AVANT** `mutation` — « comment clôturer une caisse » = aide, pas refus.
- **5 outils read-only** : `tool_search_products` (LIKE nom/réf, top 8 ; quantité stock **seulement si** `stock.voir` sinon dispo binaire), `tool_stock_alerts` (`produit_pharmacie stock<=seuil` ; admin sans session caisse → vue agrégée toutes pharmacies), `tool_stats` (role-aware : admin/compta → global ; pharmacien → CA global + stock sa pharmacie ; caissier → **ses ventes du jour** seulement), `tool_help`, `tool_nav` (liens filtrés par permission).
- **Refus mutation** (supprimer/modifier/rembourser/ajuster/importer/clôturer/créer user/changer prix…) : orientation vers menus accessibles, texte « c'est une sécurité ».
- **Aucune donnée personnelle** : aucun SELECT sur `clients.*`, `ventes.client_*`, `utilisateurs.*`. Stats = `SUM/COUNT`.
- **Role-aware par permissions réelles** (`assistant_capabilities()` via `hasPermission()`), pas par libellé de rôle — robuste pour les 8 rôles.
- **Double-escape évité** : le moteur renvoie du texte **brut** ; c'est le JS `nl2br()`/`esc()` qui échappe au rendu.
- **Auto-seed idempotent** `assistant_ensure_schema()` : `INSERT IGNORE` permission `assistant.utiliser` + grant à tous les rôles + menu `assistant` (position 23) + param `assistant_active=1`. Garde `getParam('assistant_seeded')`. Appelé par `layout_foot()`. **Pas de SQL à part** — un patch code-only peut livrer ce module sans migration manuelle.
- **Désactivable** : `assistantActive()` = `getParam('assistant_active','1')==='1' && hasPermission('assistant.utiliser')`.
- **Widget** `assistant_widget()` : FAB + slide-over, CSS inline (pas de fichier CSS dédié), brand `APP_NAME`, expose `window.PHARMA_ASSISTANT_TOPICS` + `window.PHARMA_ASSISTANT_BRAND`. Route `.htaccess` `^assistant/?$`.

---

## 11. Branding — règle RADNEX / PharmaCare **[OBLIGATOIRE]**

> « PharmaCare » (le nom **et** le logo de l'application) est la **propriété de RADNEX**, **fixe** via la constante `APP_NAME` (`config/env.php`). Le nom de la pharmacie cliente (`app_nom`, paramètre admin) **ne remplace jamais** PharmaCare.

1. **`APP_NAME='PharmaCare'`** = constante fixe, **non** configurable GUI. Brand primaire dans :
   - sidebar logo-mark (`layout.php`),
   - login brand-title (`index.php`),
   - **copyright footer partout** (`layout.php`, `index.php`, `erreur.php`, `vente.php` ticket, `commandes.php` bon, `magasin.php` bon) — format `© {année} PharmaCare — Tous droits réservés`.
2. **`app_nom`** (getParam) = nom de l'**établissement pharmacie cliente**, configurable (`parametres.php`). Affiché **seulement** :
   - SOUS le logo (sidebar `logo-sub`, login `brand-subtitle`) — **uniquement si `app_nom !== APP_NAME`** (PharmaCare reste primaire),
   - comme en-tête « établissement émetteur » sur les **imprimés** (ticket 80mm+A4, bon de livraison, bon de ravitaillement, rapports),
   - **JAMAIS** dans le slot de marque ni dans le copyright footer.
3. **Logo de l'app** (`assets/img/logo-icon.svg`) = fixe, ne change pas avec la pharmacie.
4. **Logo de la pharmacie** (`pharmacie_logo`, uploadable `parametres.php`, PNG/JPG/WebP/SVG ≤1Mo, `assets/img/pharmacie_logo.<ext>`, helpers `pharmacieLogoUrl()`/`pharmacieLogoPath()`) = affiché dans les **imprimés uniquement** (ticket, bon), **pas** dans l'UI d'administration.

---

## 12. Déploiement **[OBLIGATOIRE]**

### 12.1 Patch delta (MAJ d'une install existante) — `tools/build_patch.sh`
- Usage : `bash tools/build_patch.sh <from> <to> <files_list>` (depuis `pharmacare/`). `<files_list>` = un chemin par ligne relatif à `pharmacare/` (commentaires `#` OK).
- Produit `pharmacare_patch_<from>_to_<to>.zip` (Windows, `Compress-Archive`) + `.tar.gz` (Linux, `tar -czf` chemin MSYS) à `<htdocs>/`.
- Contenu : `pharmacare/<fichiers modifiés>` (chemins conservés) + `apply_patch.{bat,ps1,sh}` (depuis `tools/patch/`) + `PATCH_README.txt`.
- `apply_patch.*` : détecte le dossier pharmacare (`config/env.php`+`index.php`), **backup** les anciens dans `pharmacare/_patch_backup/<horodatage>/`, puis écrase. **Pas de DB, pas de reinstall.** Cache-busting via bump `APP_VERSION`.
- **[NON-ÉVIDENT bash]** `while IFS= read -r line || [ -n "$line" ]` — sans `|| [ -n "$line" ]`, la dernière ligne sans newline final est **silencieusement SKIPPÉE**.
- **Ne pas inclure** dans un patch code-only : `tools/*`, `install_prod.*`, `*.sql`, `env.prod.php`, `*.bak/log/xlsx`. Pour un fix qui modifie la BDD : fournir un script SQL de migration idempotent dans `_archive/sql/update_<topic>.sql` (ex `update_unite.sql` `ALTER TABLE produits ADD COLUMN IF NOT EXISTS unite VARCHAR(30) DEFAULT NULL AFTER reference;`).

### 12.2 Bundles complets — `tools/build_deploy_bundles.sh`
**Toujours rebuilder après chaque évolution.** Produit :
- `pharmacare_deploy.zip` (Windows : `install_prod.exe` + `install_prod.ps1`),
- `pharmacare_deploy_linux.tar.gz` (Linux : `install_prod.sh`).
- Étape 0 : **recompile `install_prod.exe`** depuis `tools/install_prod_gui.cs` + `tools/install_prod.manifest` via `csc.exe` (.NET Framework 4) — manifeste `requireAdministrator` (UAC). Le `.exe` pilote `install_prod.ps1`.
- Curation (exclusions) : `tools/`, `backups/`, `docs/`, `.claude/`, `.git/`, `config/.rate_limit/`, `config/env.prod.php`, `*.bak/log/xlsx/pdf`, `*composer*/*phpunit*/*_mvt_check*/*alter_db*/*test_import*`, `licence_privatekey.php`/`licence_secret.php`/`licence_ledger.json`/`*licence_instance_id*`, et tout `_archive/*` **sauf `database.sql`**. **Inclut `data/`** (catalogue d'import client CSV).
- Split plateforme : bundle Win garde `.exe/.ps1`, bundle Linux garde `.sh` uniquement.
- Assertions : absents sensibles OK, `database.sql` présent (seed propre), `data/*.csv` présent.
- **[NON-ÉVIDENT]** `tar` échoue avec chemin Windows → toujours MSYS (`/c/xampp/...`). Le zip PowerShell veut des chemins Windows (`cygpath -w`). csc v4 compile du **C# 5** (pas de propriété expression-bodied `=>`, pas d'interpolation `$""`).

### 12.3 Seed propre, sans identifiants dev
- `_archive/database.sql` (livré au client) : comptes `admin`/`pharmacien`/`caissier` avec `mot_de_passe='LOCKED_INSTALL'` — **placeholder non-valide** (`password_verify()` renvoie toujours faux). Fail-safe : si l'install échoue avant reset, client bloqué (sécurisé), pas ouvert.
- La liste des comptes resetés par l'installeur **doit correspondre exactement** aux logins du seed.
- Vider tables transactionnelles de test (`audit_log`, `ecritures`, `ecriture_lignes`, `mouvements_caisse`, `sessions_caisse`, `remise_approbateurs`, `ventes`, `commandes`, `transferts`, `retours`, `reglements`, `mouvements_stock`, `mouvements_magasin`…) ; reset `compteurs_ref` à 0. **Vider aussi `produits` et `produit_pharmacie`** (le client saisit son catalogue ; reset `AUTO_INCREMENT` produits à 1). Tables référençant `produits` (`produit_pharmacie`, `promo_produits`, `mouvements_stock`, `vente_lignes`) vides aussi (sinon FK orphelines). Garder seulement les référentiels (roles, permissions, menus, pharmacies, caisses, categories, fournisseurs, plan_comptable, exercices, codes_remise).
- Exclure `licence_*` de `parametres` (le client génère sa propre instance_id).
- Mots de passe via `--defaults-extra-file` (jamais `-p` en clair CLI) ; INI **UTF-8 SANS BOM** (un BOM fait rejeter par mysql).

### 12.4 Installeur non-interactif depuis la GUI
- `install_prod.ps1` piloté par `install_prod.exe` (WinForms) → PowerShell **sans console**. Tout `Read-Host` non gardé → blocage → boucle infinie. **[NON-ÉVIDENT]**
- Params : `-NonInteractive`, `-Hostname`, `-RootPass`, `-AdminPass`, `-PharmacienPass`, `-CaissierPass`, `-ParamFile`. La GUI écrit les secrets dans un JSON `-ParamFile` (lu puis **effacé**). Secrets **jamais** sur la ligne de commande.
- Tout `Read-Host` **gardé** par `if ($NonInteractive) { ... } else { Read-Host }`. `powershell.exe -NonInteractive` = filet de sécurité.
- Hash mots de passe démo : plaintext pipé à `php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT);'` via **STDIN** (jamais interpolé dans le code PHP — robuste à `'`, `\`, `$`).
- En mode console sans params : reste interactif (compatibilité).

### 12.5 PowerShell + mysql.exe — piège stderr natif **[NON-ÉVIDENT]**
Sous `$ErrorActionPreference='Stop'`, un warning stderr de `mysql.exe` (`Using a password on the command line…`) est converti en erreur terminante → `catch` fallacieux (« Mot de passe root incorrect » même avec le bon mdp).
- **Fix 1** : passe via `--defaults-extra-file` (INI `[client]`), UTF-8 **sans BOM** (`New-Object System.Text.UTF8Encoding($false)`), échapper `\` et `"`.
- **Fix 2** `Invoke-MysqlQuiet` : `2>$errFile` (stderr → fichier temp, pas le stream PS), relâche localement `$ErrorActionPreference='Continue'`, juge le succès **uniquement** via `$LASTEXITCODE -eq 0` — **jamais** `try/catch` autour d'un exe natif.
- Import schéma : UTF-8 aux deux bouts (`[Console]::OutputEncoding=UTF8` + `--default-character-set=utf8mb4`) pour préserver les accents seed.

### 12.6 Installeur Linux (`install_prod.sh`)
Équivalent LAMP (étapes 1–10). Crée un **user BDD dédié** `pharmacare` (pas root), ownership `www-data` sur `config/.rate_limit`/`backups`/`env.prod.php`, ouvre ufw 80 si actif. Même génération `env.prod.php` + reset mots de passe démo.

---

## 13. Licence par code d'activation (`config/licence.php`)
- RSA-2048 signé. Code = `base64url(JSON{i=instance_id, c=cap_lines, n=counter, e=expiry_ts}) . "." . base64url(RSA-SHA256 sig)`. `LICENCE_FREE_CAP=150` (lignes de vente). Format court SMS `LLLLL-NNNNNN-CCCCCCCC` (HMAC).
- `licence_current()`, `licence_usage()` (count `vente_lignes`), `licence_apply_code()`, `licence_instance_id()`, `licence_integrity_check()` (anti-falsification via `config/licence_integrity.php` = manifeste signé des hashes SHA256 des fichiers liés).
- **Clé privée** uniquement dans `tools/licence_privatekey.php` (gitignored, bloqué web par `.htaccess`), **jamais** dans les bundles. Pubkey `LICENCE_PUBKEY` dans `config/licence.php`.

---

## 14. Critères d'acceptation / vérification (recréation fidèle)

L'application recréée doit passer :

1. **Install fraîche** (dev) : XAMPP + base `pharmacare` créée, `database.sql` importé → comptes `LOCKED_INSTALL` non-connectables ; après reset installeur → `admin`/`pharmacien`/`caissier` connectables.
2. **Login + rôles** : 8 rôles, permissions RBAC appliquées (sidebar gardée, actions refusées avec redirect `dashboard.php?err=access`).
3. **CSRF** : tout POST sans token → `403` (page brandée « Session expirée »), **pas** 500. `http_response_code` n'utilise que 400/403/404/422/451.
4. **Cookie secure** : sur HTTP LAN, cookie envoyé (secure=false) ; sur HTTPS, secure=true.
5. **Pages d'erreur** : `/config/` → `403.php` brandé ( RewriteRule relative) ; URL inexistante → `404.php` ; MySQL tombé → `503` brandé SANS dépendance BDD. En dev sous-dossier, aucun `/500.php` absolu ne casse.
6. **POS** : vente complète → débit `produit_pharmacie` (atomique), sync `produits.stock` si pharmacie=1, `mouvements_stock('sortie')`, écritures OHADA (7011/4411/caisse/4112 ; 7119 si remise ; 6031/3111 sortie stock), `codes_remise.used=1`, ticket imprimable N copies, TVA masquée à 0 sur le ticket.
7. **Stock deux-niveaux** : import CSV ravitaille le **magasin** (pas la pharmacie) ; transfert magasin→pharmacie décrète `stock_magasin` et crédite `produit_pharmacie` ; `produits.stock` n'est **jamais** écrit directement pour un ravitaillement.
8. **Ajustement en lot magasin** : liste à cocher + **une seule quantité** → set absolu `stock_magasin` + mouvement `ajustement` signé par produit changé ; lignes inchangées ignorées ; audit.
9. **Comptabilité OHADA** : `ecritureCreate` équilibré (Σd=Σc) ; journal, balance, grand-livre, résultat, bilan corrects ; clôture solde 6/7→12, `verrouillee=1`, crée exercice suivant.
10. **Branding** : sidebar/login/copyright = `APP_NAME='PharmaCare'` ; `app_nom` affiché seulement en sous-titre + en-tête imprimés ; copyright footer toujours PharmaCare ; logo pharmacie uniquement sur imprimés.
11. **Assistant** : refus poli des mutations, aucune donnée personnelle, role-aware par permissions, auto-seed idempotent, désactivable.
12. **Déploiement** : `build_patch.sh` produit patch delta code-only (backup auto) ; `build_deploy_bundles.sh` produit zip Win + tar Linux sans `tools/`/`env.prod.php`/`licence_*`/dev creds ; installeur GUI non-interactif via `-ParamFile` ; mysql sous PS via `--defaults-extra-file` + `$LASTEXITCODE`.
13. **Rate-limit** : 5 échecs login → lockout 15 min ; 120 req/min/IP → 429 ; POS 20 ventes/min/user.
14. **Unité** : colonne `unite` présente (form datalist, liste, import CSV template+mapping+INSERT/UPDATE).

---

## 15. Notes finales pour le modèle

- **Respecte les contraintes [NON-ÉVIDENT]** : ce sont des pièges **déjà rencontrés** en production (CSRF 419→500, cookie secure sur LAN HTTP, RewriteRule absolue en sous-dossier, stderr mysql sous PS Stop, Read-Host en GUI sans console, dernière ligne de files_list skipée, LIKE wildcard injection, `produits.stock` écrit directement, seed avec mdp dev connu). Les contourner ou les « corriger » = casser l'app en prod.
- **Ne réintègre pas** d'artefacts dev supprimés (`AGENTS.md`, `DESIGN.md`, `PRODUCT.md`, `README.md`, `composer.json`, `*.pdf`, `docs/`, `deploy/`, `alter_db.php`, `_mvt_check.js`, `test_import*`).
- **Convention de code** : plain PHP, `declare(strict_types=1)` dans les fichiers de config, helpers en fonctions globales (pas de classes), vues en PHP/HTML inline, icônes SVG inline, modales en HTML+CSS `.open`, JS vanilla (pas de framework). Commentaires en français.
- **Marque** : « PharmaCare » est la marque de **RADNEX Ltd (C)2026** — ne la remplace jamais par le nom d'une pharmacie.
- Si une information est ambiguë, choisis la solution qui préserve la **sécurité**, la **traçabilité comptable/stock** et la **marque PharmaCare**.

---

*Document généré comme spécification de recréation exhaustive de PharmaCare v1.2.1 — propriété RADNEX.*