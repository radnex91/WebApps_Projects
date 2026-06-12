# Atlas Prime — Optimisation Performance & Rate Limiting

Date : 2026-06-12  
Statut : En validation

## Contexte

Atlas Prime est une application PHP/MySQL de gestion logistique avec >10k colis. Plusieurs pages sont lentes à charger à cause de requêtes non indexées, de patterns N+1, et de pages sans pagination. L'application n'a aucun rate limiting — les logins, formulaires et requêtes globales ne sont pas protégés contre les abus. Les ressources CDN (Font Awesome 370KB, Google Fonts) bloquent le premier rendu.

## Approche

Optimisation incrémentale par 3 couches indépendantes, chacune déployable séparément :
1. **Couche DB** — Index, requêtes sargables, consolidation, N+1, pagination
2. **Couche Rate Limiting** — Protection login, throttling formulaires, limitation globale
3. **Couche Frontend** — Ressources locales, Font Awesome subset, preload, font-display

---

## Couche 1 — Optimisation base de données

### 1.1 Index manquants

La table `colis` (>10k lignes, la plus interrogée) n'a aucun index secondaire. Ajout :

```sql
-- colis
CREATE INDEX idx_colis_statut ON colis(statut);
CREATE INDEX idx_colis_date_expedition ON colis(date_expedition);
CREATE INDEX idx_colis_agence_depart ON colis(agence_depart_id);
CREATE INDEX idx_colis_agence_arrivee ON colis(agence_arrivee_id);
CREATE INDEX idx_colis_voyage_id ON colis(voyage_id);
CREATE INDEX idx_colis_statut_paiement ON colis(statut_paiement);
CREATE INDEX idx_colis_cree_par ON colis(cree_par);
CREATE INDEX idx_colis_escale ON colis(escale_actuelle_id);
CREATE INDEX idx_colis_composite_statut_date ON colis(statut, date_expedition);

-- suivi_colis
CREATE INDEX idx_suivi_colis_id ON suivi_colis(colis_id);

-- audit_logs
CREATE INDEX idx_audit_user ON audit_logs(utilisateur_id);
CREATE INDEX idx_audit_date ON audit_logs(date_action);
```

Impact estimé : 50-80% de réduction du temps de requête sur les pages filtrant par statut/date.

### 1.2 Requêtes sargables

Les colonnes enveloppées dans des fonctions (`DATE()`, `MONTH()`, `YEAR()`) empêchent l'utilisation des index. Remplacement systématique par des plages :

| Avant (non-sargable) | Après (sargable) |
|---|---|
| `WHERE DATE(date_expedition) BETWEEN ? AND ?` | `WHERE date_expedition >= ? AND date_expedition < ?+1day` |
| `WHERE DATE(date_expedition) = CURDATE()` | `WHERE date_expedition >= CURDATE() AND date_expedition < CURDATE() + INTERVAL 1 DAY` |
| `WHERE MONTH(date_expedition) = MONTH(CURDATE()) AND YEAR(...)` | `WHERE date_expedition >= DATE_FORMAT(NOW(),'%Y-%m-01') AND date_expedition < DATE_FORMAT(NOW() + INTERVAL 1 MONTH,'%Y-%m-01')` |

Pages affectées : `index.php`, `rapports.php`, `bordereaux.php`.

### 1.3 Consolidation des COUNT du dashboard

`index.php` exécute 8 requêtes COUNT/SUM séparées sur `colis`. Fusion en 1 requête :

```sql
SELECT
  COUNT(*) AS total_colis,
  SUM(CASE WHEN statut='livre' THEN 1 ELSE 0 END) AS nb_livres,
  SUM(CASE WHEN statut IN ('en_transit','en_livraison') THEN 1 ELSE 0 END) AS nb_transit,
  COALESCE(SUM(montant_total),0) AS ca_total,
  SUM(CASE WHEN date_expedition >= CURDATE() AND date_expedition < CURDATE() + INTERVAL 1 DAY THEN montant_total ELSE 0 END) AS ca_jour,
  SUM(CASE WHEN date_expedition >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN montant_total ELSE 0 END) AS ca_mois,
  SUM(CASE WHEN statut_paiement != 'paye' THEN 1 ELSE 0 END) AS nb_impayes,
  SUM(CASE WHEN date_expedition >= CURDATE() AND date_expedition < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS colis_jour
FROM colis
```

### 1.4 N+1 → requêtes groupées

| Pattern N+1 | Solution |
|---|---|
| Escales par trajet : 1 query/trajet dans `manifest.php`, `nouveau_voyage.php` | `WHERE trajet_id IN (...)` en 1 requête, PHP dispatch par trajet_id |
| `suivi_colis` INSERT par colis dans `manifest.php`, `manifest_complement.php` | Multi-row INSERT unique |
| `colis_details` INSERT par ligne dans `nouveau_colis.php`, `edit_colis.php` | Multi-row INSERT unique |
| Voyages filtrés par escale : 1 query/voyage dans `manifest.php` | Jointure directe `trajet_escales` au lieu de sous-requête |
| `getVoyagesOuverts()` appelé 4+ fois par request | Cache request-level (static variable) dans la fonction |
| `getAgences()` appelé 6+ fois par request | Cache request-level (static variable) dans la fonction |
| `transitionVoyageStatut()` : 2-3 queries par colis | Batch UPDATE + batch INSERT pour tous les colis du voyage |

### 1.5 Pagination des pages sans limite

| Page | Actuel | Après |
|---|---|---|
| `manifest.php` | Charge TOUS les colis dispos + transit | 30 par page avec COUNT + LIMIT/OFFSET |
| `manifest_complement.php` | Charge TOUS les colis | 30 par page |
| `bordereaux.php` | Charge TOUS les colis filtrés | 50 par page |
| `detail_voyage.php` | Tous les colis du voyage | 30 par page (onglets colis/décharges) |

Pages déjà paginées (`colis.php` 20/page, `voyages.php` 15/page) : inchangées.

### 1.6 Sélectivité des colonnes (SELECT *)

Remplacer `SELECT c.*` par les colonnes réellement utilisées dans les pages liste (manifest, bordereaux, rapports) pour réduire le transfert mémoire/réseau. Conserver `SELECT *` uniquement sur les pages détail qui affichent tous les champs.

---

## Couche 2 — Rate limiting

### 2.1 Architecture

Nouveau fichier `includes/rate_limit.php` avec classe `RateLimit` et 3 méthodes statiques :

```php
RateLimit::checkLogin($ip)    — protection login (incrément sur échec seulement)
RateLimit::checkForm($ip)     — throttling formulaires POST (incrément sur tout POST)
RateLimit::checkGlobal($ip)   — limitation globale par IP (incrément sur toute requête)
```

### 2.2 Table MySQL

```sql
CREATE TABLE rate_limits (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip_hash      VARCHAR(64) NOT NULL,    -- SHA-256 de l'IP (RGPD: pas de stockage en clair)
  action       VARCHAR(50) NOT NULL,    -- 'login', 'form', 'global'
  attempts     INT DEFAULT 1,
  window_start DATETIME NOT NULL,
  UNIQUE KEY idx_rate_unique (ip_hash, action, window_start),
  INDEX idx_rate_cleanup (window_start)
);
```

L'IP est hashée en SHA-256 pour la conformité RGPD. La clé unique sur (ip_hash, action, window_start) permet un upsert atomique avec `ON DUPLICATE KEY UPDATE attempts = attempts + 1`.

### 2.3 Limites configurables (config.php)

```php
define('RATE_LIMIT_LOGIN_MAX', 5);        // 5 tentatives de login
define('RATE_LIMIT_LOGIN_WINDOW', 900);    // par fenêtre de 15 min
define('RATE_LIMIT_FORM_MAX', 20);        // 20 soumissions de formulaire
define('RATE_LIMIT_FORM_WINDOW', 60);     // par minute
define('RATE_LIMIT_GLOBAL_MAX', 120);     // 120 requêtes globales
define('RATE_LIMIT_GLOBAL_WINDOW', 60);   // par minute
```

### 2.4 Comportement au blocage

- **Login bloqué** : Message « Trop de tentatives. Réessayez dans X minutes. » + désactivation du bouton de login
- **Formulaire bloqué** : `showToast()` avec message d'attente, formulaire reste affiché
- **Global bloqué** : HTTP 429 (Too Many Requests) avec page minimaliste

### 2.5 Intégration

- `login.php` : `RateLimit::checkLogin($ip)` avant vérification des credentials. Incrément uniquement si login échoue.
- `header.php` : `RateLimit::checkGlobal($ip)` sur toute requête + `RateLimit::checkForm($ip)` si POST
- Aucun changement sur les pages individuelles — tout est centralisé

### 2.6 Nettoyage automatique

Nettoyage probabiliste : à chaque vérification, 1% de chances d'exécuter `DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 HOUR`. Pas de cron externe nécessaire.

---

## Couche 3 — Optimisation frontend

### 3.1 Ressources locales

Remplacement de tous les appels CDN par des copies locales dans `assets/vendor/` :

| Ressource | Chemin local | Taille |
|---|---|---|
| Font Awesome 6.5.0 (subset) | `assets/vendor/fontawesome/css/all.min.css` + `webfonts/` | ~50KB |
| Sora (woff2) | `assets/vendor/fonts/sora.woff2` | ~80KB |
| Space Mono (woff2) | `assets/vendor/fonts/spacemono.woff2` | ~40KB |
| Chart.js 4.4.0 | `assets/vendor/chartjs/chart.umd.min.js` | ~200KB |

### 3.2 Subset Font Awesome

Font Awesome full bundle = ~370KB gzippé. L'app utilise ~25-30 icônes. Subset réduit à ~20-30KB :
- Scan du codebase pour lister les icônes `fa-*` utilisées
- Génération d'un CSS minimal avec uniquement les classes nécessaires
- Suppression des webfonts inutiles (brands, regular si pas utilisé)

### 3.3 Chargement optimisé

```html
<!-- Preload des fonts critiques -->
<link rel="preload" href="assets/vendor/fonts/sora.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/vendor/fonts/spacemono.woff2" as="font" type="font/woff2" crossorigin>

<!-- CSS locaux -->
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">

<!-- Font-face avec swap -->
<style>
  @font-face {
    font-family: 'Sora';
    src: url('assets/vendor/fonts/sora.woff2') format('woff2');
    font-display: swap;
  }
  @font-face {
    font-family: 'Space Mono';
    src: url('assets/vendor/fonts/spacemono.woff2') format('woff2');
    font-display: swap;
  }
</style>
```

`font-display: swap` : texte affiché en police système immédiatement, swap quand la police charge.
`preload` : fichiers woff2 téléchargés en parallèle du CSS.
Plus de DNS lookup ni connexion TLS vers cdnjs.cloudflare.com / fonts.googleapis.com.

### 3.4 Fichiers à mettre à jour

- `includes/header.php` — liens CDN → locaux + preload + font-face swap
- `login.php` — liens CDN → locaux
- `bordereau_print.php` — Google Fonts → local
- `includes/footer.php` — Chart.js CDN → local (reste conditionnel via `$loadChart`)

### 3.5 Gzip Apache

Ajout dans `.htaccess` (si pas déjà configuré) pour compresser CSS, JS, woff2, SVG :

```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/css application/javascript image/svg+xml
</IfModule>
```

---

## Dépendances entre couches

Les 3 couches sont indépendantes :
- Couche 1 (DB) peut être déployée seule
- Couche 2 (Rate limiting) nécessite la nouvelle table + les index (couche 1.1) pour performer
- Couche 3 (Frontend) est totalement indépendante

Ordre de déploiement recommandé : 1 → 2 → 3.

## Risques et mitigations

| Risque | Mitigation |
|---|---|
| Index ralentit les INSERT | Impact négligeable : la table colis reçoit ~inserts/minute, pas des milliers |
| Rate limiting bloque des utilisateurs légitimes | Limites généreuses (120 req/min global, 20 forms/min) ; IP hashée permet reset via cleanup |
| Font Awesome subset manque des icônes | Scan exhaustif du codebase avant subset ; test visuel de toutes les pages |
| CDN fallback manquant | Les ressources locales éliminent le besoin de fallback |
| Pagination modifie l'UX | Pagination cohérente avec les pages existantes (colis, voyages) |