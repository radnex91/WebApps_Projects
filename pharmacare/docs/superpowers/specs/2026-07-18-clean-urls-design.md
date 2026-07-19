# Clean URLs — Design Spec

**Date :** 2026-07-18
**Branche :** `feat/comptabilite-ohada-stock-reglements-cloture` (suite)
**Projet :** PharmaCare (`C:\xampp\htdocs\pharmacare`)
**Approche retenue :** C — Hybride (`.htaccess` rewrite + helper PHP `url()`)

## 1. Objectif

Remplacer les URLs `APP_URL/modules/<module>.php?action=<a>&id=<n>&onglet=<o>…` par des URLs propres et personnalisées de type :

- `/vente`
- `/produits/5-paracetamol-500mg/edit`
- `/clients/12-pharmacie-centrale`
- `/magasin/stock`
- `/comptabilite/cloture`

Slugs **figés au code** (pas de config admin). Le slug texte est **décoratif** : seul l'ID compte côté serveur. Zéro migration de BDD (slug calculé depuis le champ `nom` à la volée).

## 2. Contexte technique

- Dev : `http://localhost/pharmacare` (sous-dossier XAMPP). Prod : vhost dédié, domaine racine (`pharmacare.exemple.cm`). `APP_URL` défini dans `config/env.php`.
- Modules actuels : `dashboard.php`, `index.php` (login), `logout.php`, `404.php`, `modules/*.php` (~21 modules).
- Dispatch actuel : chaque module fait `$action = $_GET['action'] ?? 'list'` puis branche sur `$_SERVER['REQUEST_METHOD']`. L'action est portée par la **query string**, pour GET comme pour POST.
- `magasin.php` utilise `onglet=` ; `comptabilite.php` et les autres utilisent `action=`. Aucun module n'utilise `p=`.
- Ressources avec un champ slug utilisable (id + nom) : `produits`, `clients`, `fournisseurs`, `categories`, `caisses`, `utilisateurs` (prenom+nom), `roles`. Non-sluggable : `commandes`, `retours`, `ventes_hist`, `vente`, plan comptable, `marketing`, `parametres`, `stock`, `stock_ajust`, `magasin` (vues sur produits).

## 3. Architecture

### 3.1 Principe

- `.htaccess` ajoute des RewriteRules `/<clean> → modules/<file>.php?<query>`.
- Les accès directs `modules/xxx.php?…` **restent valides** (backward compat, bookmarks, cibles POST). Aucun `301` imposé.
- Un helper PHP `url()` + `slugify()` (nouveau fichier `includes/url.php`) génère les liens propres. Il remplace tous les liens GET (nav + vues) et les cibles de `header('Location:')`.
- Les modules **ne changent pas côté dispatch** (`$action = $_GET['action'] ?? 'list'`).
- La regex de rewrite capture `(\d+)` (l'ID) et ignore le slug texte.

### 3.2 Compatibilité dev/prod

Cibles de rewrite **relatives** (`modules/x.php`, sans `/` initial). Apache 2.4 résout une substitution relative par rapport au dossier du `.htaccess` → fonctionne en sous-dossier (dev) comme à la racine du domaine (prod), sans `RewriteBase`.

## 4. Helper `url()` + `slugify()`

Nouveau fichier `includes/url.php`, chargé après `includes/auth.php` (et avant tout rendu/layout).

### 4.1 `slugify(string $text): string`

Normalisation : `mb_strtolower`, suppression des accents, `[^\a-z0-9]+` → `-`, trim des `-`, longueur plafonnée (ex. 80 chars). Retourne `''` si vide.

### 4.2 Table de routes `$ROUTES`

Tableau PHP associant chaque module à la forme de ses URLs. Champs :

- `base` : segment de base (`vente`, `produits`, `ventes-hist`…).
- `idSlug` : `true` si l'ID porte un slug (`produits`, `clients`, `fournisseurs`, `utilisateurs`).
- `actions` : map `valeur de action → segment` ; `''` = segment vide (ex. `detail` → `/clients/12-…`).
- `onglet` : si le module utilise `onglet=` (`magasin`) : map `valeur onglet → segment`.

### 4.3 `url(string $module, array $params = [], ?string $name = null): string`

Logique :

1. Look up `$ROUTES[$module]`. Si absent, fallback : `APP_URL . '/modules/' . $module . '.php'` + query string du tableau `$params` (compat descendante).
2. Extrait de `$params` les clés gérées par la route (`action`, `id`, `onglet`) et les consomme ; les clés restantes (`q`, `page`, `csrf`, `debut`, `fin`, `mode`, `filtre`, `cat`, `mvt_page`, `periode`…) vont en query string.
3. Construit le path :
   - `APP_URL` + `/` + `base`
   - Si `id` présent et `idSlug` : `/<id>-<slugify(name)>` (slug omis si `name` null → `/<id>`).
   - Si `action` mappée à un segment non vide : `/<segment>` (ex. `edit`, `reglement`, `disable`). Si mappée à `''` (detail) : rien.
   - Si `onglet` mappé : `/<segment>`.
   - Si `action` non listée dans `actions` (ex. valeurs internes POST comme `livrer`, `cloture_exec`, `saisie_save`, `plan_update`, `plan_new`, `delete`, `toggle`, `add-promo`) : ne produit **pas** de clean path pour cette action → fallback old-style (`modules/x.php?action=…&…`). Cela gère nativement les exceptions POST sans logique spéciale.
4. Ajoute `?` + query string (URL-encodée) pour les params restants.

### 4.4 Exemples

```
url('vente')                                          → /vente
url('produits', ['action'=>'edit','id'=>5], 'Paracétamol 500mg')
                                                     → /produits/5-paracetamol-500mg/edit
url('clients', ['action'=>'detail','id'=>12], 'Pharmacie Centrale')
                                                     → /clients/12-pharmacie-centrale
url('magasin', ['onglet'=>'stock'])                   → /magasin/stock
url('comptabilite', ['action'=>'cloture'])            → /comptabilite/cloture
url('produits', ['q'=>'doliprane','page'=>2])          → /produits?q=doliprane&page=2
url('clients', ['action'=>'disable','id'=>12,'csrf'=>$t], 'Pharmacie Centrale')
                                                     → /clients/12-pharmacie-centrale/disable?csrf=TOKEN
url('commandes', ['action'=>'livrer','id'=>7])        → /modules/commandes.php?action=livrer&id=7  (fallback)
```

## 5. `.htaccess`

Fusionné dans le bloc `<IfModule mod_rewrite.c>` existant (ne pas écraser les règles de sécurité courantes : blocage `config/`, `sql/`, `.git`, headers). Règles **ordonnées** (spécifiques avant génériques).

### 5.1 Routes de base (sans query)

```
RewriteRule ^dashboard/?$       dashboard.php [L]
RewriteRule ^logout/?$          logout.php [L]
RewriteRule ^vente/?$           modules/vente.php [L,QSA]
RewriteRule ^caisse/?$          modules/caisse.php [L,QSA]
RewriteRule ^stock/?$           modules/stock.php [L,QSA]
RewriteRule ^fournisseurs/?$    modules/fournisseurs.php [L,QSA]
RewriteRule ^commandes/?$       modules/commandes.php [L,QSA]
RewriteRule ^retours/?$         modules/retours.php [L,QSA]
RewriteRule ^marketing/?$       modules/marketing.php [L,QSA]
RewriteRule ^ventes-hist/?$     modules/ventes_hist.php [L,QSA]
RewriteRule ^rapports/?$        modules/rapports.php [L,QSA]
RewriteRule ^rapports-caissier/?$ modules/rapports_caissier.php [L,QSA]
RewriteRule ^utilisateurs/?$    modules/utilisateurs.php [L,QSA]
RewriteRule ^remise-approbateurs/?$ modules/remise_approbateurs.php [L,QSA]
RewriteRule ^remise-codes/?$    modules/remise_codes.php [L,QSA]
RewriteRule ^roles/?$           modules/roles.php [L,QSA]
RewriteRule ^categories/?$      modules/categories.php [L,QSA]
RewriteRule ^parametres/?$      modules/parametres.php [L,QSA]
```

### 5.2 Ressources id-slug + action

Forme générale `^<base>/(\d+)(?:-[^/]+)?/<seg>?$`. Slug optionnel. Exemple produits :

```
RewriteRule ^produits/new/?$                       modules/produits.php?action=add [L,QSA]
RewriteRule ^produits/(\d+)(?:-[^/]+)?/edit/?$      modules/produits.php?action=edit&id=$1 [L,QSA]
RewriteRule ^produits/?$                           modules/produits.php [L,QSA]
```

Idem pour `clients` (avec detail sans suffixe), `fournisseurs` (add/edit/delete), `utilisateurs` (add/edit). Pour `clients`, ordre : `new` → `edit` → `reglement` → `disable` → `enable` → `detail` (`^clients/(\d+)(?:-[^/]+)?/?$`) → base.

### 5.3 Onglets magasin

```
RewriteRule ^magasin/(stock|reception|historique)/?$  modules/magasin.php?onglet=$1 [L,QSA]
RewriteRule ^magasin/?$                              modules/magasin.php [L,QSA]
```

### 5.4 Pages comptabilite (action=)

```
RewriteRule ^comptabilite/plan/(\d+)/?$             modules/comptabilite.php?action=plan_edit&id=$1 [L,QSA]
RewriteRule ^comptabilite/(saisie|journal|balance|resultat|bilan|grand-livre|cloture|plan)/?$  modules/comptabilite.php?action=$1 [L,QSA]
RewriteRule ^comptabilite/?$                        modules/comptabilite.php [L,QSA]
```

### 5.5 Autres modules à action

`caisse` (action=ouvrir|x|z|rapport_session|historique|mouvement, +id), `commandes` (action=add|edit|bon|livrer_form, +id), `retours` (action=new|detail, +id), `remise-codes` (action=show|generer, +id), `marketing` (action=add-promo|edit-promo|toggle|fidelite|add-points, +id) : mêmes formes, règles dédiées. Seules les actions GET sont mappées ; les POST-only (`livrer`, `cloture_exec`, `saisie_save`, `delete`, `toggle`, `plan_update`, `plan_new`) ne sont **pas** mappées → fallback old-style (les formulaires POST postent en old-style, ce qui fonctionne).

`QSA` préserve les query params supplémentaires (csrf, q, page, debut, fin, mode, filtre, cat, periode, mvt_page).

## 6. Périmètre des conversions

### 6.1 À convertir en clean URLs

- `includes/layout.php` : nav principale + dropdowns (tous `APP_URL ?>/modules/xxx.php`).
- `includes/dashboard-admin.php`, `dashboard-caissier.php`, `dashboard-pharmacien.php` : tuiles/liens.
- `modules/*.php` : tous les `href` GET (boutons edit/detail/disable/enable, onglets, « Voir », tabs), via `url()`.
- Toutes les cibles `header('Location: ' . APP_URL . '/modules/xxx.php?…')` et redirects relatifs `?action=list` → clean URLs (via `url()`).

### 6.2 Inchangé

- `action=""` des formulaires POST : restent en old-style (`modules/xxx.php?action=…` ou relatif `?action=…`). Toujours routés, fonctionnels. Aucune édition de la logique de dispatch des modules.
- Logique de dispatch (`$action = $_GET['action'] ?? 'list'`) : non touchée.
- Accès directs `modules/xxx.php?…` : valides (pas de rewrite sur `modules/`).

### 6.3 Exceptions GET/POST action-values distincts

- `commandes` : GET `?action=livrer_form` → clean URL d'affichage du formulaire ; POST `?action=livrer` → old-style.
- `comptabilite` : GET `?action=cloture` → clean URL ; POST `?action=cloture_exec` → old-style.

Gérées nativement par la règle « action non listée dans `actions` → fallback old-style » (§4.3).

## 7. Edge cases

- Ordre des règles : spécifiques avant génériques (`new` avant `(\d+)`, `edit` avant `detail` sans suffixe, `plan/(\d+)` avant `plan`).
- `produits.php?action=edit&id=5` direct → fonctionne (pas de rewrite sur `modules/`).
- `assets/`, `config/`, `sql/`, `.git`, `tests/`, `deploy/` : non matchés par les règles + déjà bloqués.
- `$activePage` dans `layout.php` (basé sur nom de module) : inchangé, reste fonctionnel.
- Slug vide ou nom absent : URL `/produits/5/edit` (ID seul) — valide grâce au slug optionnel.
- `index.php` reste le `DirectoryIndex` (login). `/dashboard` route vers `dashboard.php` (qui appelle `requireLogin()` → redirect vers login si non connecté).

## 8. Tests

- `tests/UrlTest.php` (PHPUnit) : `slugify()` (accents, espaces, vide, longueur) ; `url()` pour chaque forme de route (base, id-slug+action, detail sans suffixe, onglet, page comptable, plan_edit avec id, query résiduelle, fallback action non mappée, fallback module non listé).
- Vérification manuelle dev (XAMPP) : naviguer `/vente`, `/produits/5-…/edit` (GET form + POST save), `/magasin/stock`, `/comptabilite/cloture` (GET) puis POST exec (old-style), redirect propre après save, accès direct `modules/produits.php?action=edit&id=5` toujours OK.
- `phpunit.xml` déjà présent.

## 9. Non-goals

- Pas de slugs configurables par l'admin.
- Pas de colonne `slug` en BDD, pas de slug persistant, pas de gestion d'unicité.
- Pas de `301` des anciennes URLs vers les nouvelles.
- Pas de routeur frontal PHP centralisé (approche A écartée).
- Pas de `.htaccess`-only sans helper (approche B écartée).