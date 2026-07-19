# Clean URLs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `APP_URL/modules/<module>.php?action=<a>&id=<n>&onglet=<o>…` GET URLs with clean, personalized URLs (`/vente`, `/produits/5-paracetamol-500mg/edit`, `/magasin/stock`, `/comptabilite/cloture`) via a `.htaccess` rewrite + a PHP `url()`/`slugify()` helper, without changing module dispatch logic or the DB schema.

**Architecture:** Approach C (hybrid). `.htaccess` rewrites `/<clean>` → `modules/<file>.php?<query>` (relative targets, works in dev subfolder and prod domain root). A new `includes/url.php` defines a route table + `slugify()` + `url()` that generate clean links for all GET navigation and post-redirect targets. POST forms keep their existing `action=""` (old-style or relative — both still routed). Modules' `$action = $_GET['action'] ?? 'list'` dispatch is untouched. Direct `modules/xxx.php?…` access stays valid (backward compat, no 301). The slug text is decorative: regex captures `(\d+)`, the rest of the segment is ignored.

**Tech Stack:** PHP 8.x, Apache 2.4 `mod_rewrite`, PHPUnit (existing, `phpunit.xml`, `tests/bootstrap.php`), MySQL/MariaDB (BDD `pharmacare_test` for tests).

## Global Constraints

- All paths are relative to `C:\xampp\htdocs\pharmacare`.
- `APP_URL` is defined in `config/env.php` (`http://localhost/pharmacare` in dev). Every generated URL must be prefixed by `APP_URL`.
- `e()` HTML-escaping helper exists in `includes/auth.php` (`function e(?string $s): string`). Reuse it; do not redefine.
- PHPUnit: run from project root with `./vendor/bin/phpunit` (Windows: `php vendor/bin/phpunit`). `tests/bootstrap.php` sets `PHARMACARE_ENV=test` and loads `config/env.php`, `config/database.php`, `config/settings.php`, `config/comptabilite.php`, `includes/auth.php`. New helpers loaded via `auth.php` are available to tests automatically.
- Git: project root is `C:\xampp\htdocs`. Commit each task. End commit messages with `Co-Authored-By: Claude <noreply@anthropic.com>`. Stage only the files each task touches.
- `.htaccess` edits must preserve the existing security block (`Options -Indexes`, blocage `config/`/`sql/`/`.git`/`config/.rate_limit/`, headers, `<FilesMatch>` denials). Merge routing rules into the existing `<IfModule mod_rewrite.c>` block, do not rewrite the file.
- Rewrite targets must be **relative** (`modules/x.php`, no leading `/`) and carry `[L,QSA]` so extra query params (csrf, q, page, debut, fin…) are preserved.
- Slugs are decorative and optional in the URL (`(?:-[^/]+)?`); `url()` emits them from `nom`, the rewrite ignores them.
- POST form `action=""` attributes are **not** converted — they stay old-style or relative and keep working. Only GET links (`href`, `header('Location:')`, GET `<form method="GET" action="...">` targets) are converted.

---

## File Structure

- **Create** `includes/url.php` — route table `$ROUTES`, `slugify()`, `url()`. One responsibility: build clean URLs. Loaded via `includes/auth.php`.
- **Modify** `includes/auth.php` — append `require_once __DIR__ . '/url.php';` so `url()` is available in every entry point and in tests.
- **Modify** `.htaccess` — add routing RewriteRules in the existing rewrite block.
- **Modify** `includes/layout.php` — nav + dropdowns → `url()`.
- **Modify** `includes/dashboard-admin.php`, `includes/dashboard-caissier.php`, `includes/dashboard-pharmacien.php` — tiles → `url()`.
- **Modify** `modules/*.php` (per group, see Tasks 6–9) — GET `href` + `header('Location:')` + GET `<form action>` → `url()`.
- **Create** `tests/UrlTest.php` — unit tests for `slugify()` + `url()`.
- **Create** `tests/CleanUrlLinksTest.php` — regression test asserting converted files contain no old-style GET `href` to `/modules/<x>.php`.

---

### Task 1: `slugify()` + `url()` helper + route table (TDD)

**Files:**
- Create: `includes/url.php`
- Test: `tests/UrlTest.php`

**Interfaces:**
- Consumes: `APP_URL` (constant from `config/env.php`), `e()` (not needed here).
- Produces:
  - `function slugify(string $text): string`
  - `function url(string $module, array $params = [], ?string $name = null): string`
  - global `array $ROUTES` (module → `{base:string, idSlug:bool, actions:array<string,string>, onglet?:array<string,string>}`)

- [ ] **Step 1: Write the failing test**

Create `tests/UrlTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testSlugifyLowercasesAndDashes(): void
    {
        $this->assertSame('paracetamol-500mg', slugify('Paracétamol 500mg'));
        $this->assertSame('pharmacie-centrale', slugify('Pharmacie Centrale'));
        $this->assertSame('doliprane', slugify('  Doliprane  '));
    }

    public function testSlugifyEmptyOnBlank(): void
    {
        $this->assertSame('', slugify(''));
        $this->assertSame('', slugify('   '));
    }

    public function testSlugifyStripsPunctuation(): void
    {
        $this->assertSame('abc-123', slugify('a,b.c!d?e@f#g 123'));
    }

    public function testUrlBaseNoParams(): void
    {
        $this->assertSame(APP_URL . '/vente', url('vente'));
        $this->assertSame(APP_URL . '/comptabilite', url('comptabilite'));
    }

    public function testUrlProducesIdSlugEdit(): void
    {
        $u = url('produits', ['action' => 'edit', 'id' => 5], 'Paracétamol 500mg');
        $this->assertSame(APP_URL . '/produits/5-paracetamol-500mg/edit', $u);
    }

    public function testUrlDetailWithoutActionSuffix(): void
    {
        $u = url('clients', ['action' => 'detail', 'id' => 12], 'Pharmacie Centrale');
        $this->assertSame(APP_URL . '/clients/12-pharmacie-centrale', $u);
    }

    public function testUrlIdOnlyWhenNameMissing(): void
    {
        $u = url('produits', ['action' => 'edit', 'id' => 5]);
        $this->assertSame(APP_URL . '/produits/5/edit', $u);
    }

    public function testUrlOnglet(): void
    {
        $this->assertSame(APP_URL . '/magasin/stock', url('magasin', ['onglet' => 'stock']));
        $this->assertSame(APP_URL . '/magasin', url('magasin'));
    }

    public function testUrlComptabilitePage(): void
    {
        $this->assertSame(APP_URL . '/comptabilite/cloture', url('comptabilite', ['action' => 'cloture']));
        $this->assertSame(APP_URL . '/comptabilite/journal', url('comptabilite', ['action' => 'journal']));
    }

    public function testUrlComptaPlanEditWithId(): void
    {
        $u = url('comptabilite', ['action' => 'plan_edit', 'id' => 7]);
        $this->assertSame(APP_URL . '/comptabilite/plan/7', $u);
    }

    public function testUrlResidualQuery(): void
    {
        $u = url('produits', ['q' => 'doliprane', 'page' => 2]);
        $this->assertSame(APP_URL . '/produits?q=doliprane&page=2', $u);
    }

    public function testUrlActionAndIdWithResidualQuery(): void
    {
        $t = 'TOKEN123';
        $u = url('clients', ['action' => 'disable', 'id' => 12, 'csrf' => $t], 'Pharmacie Centrale');
        $this->assertSame(APP_URL . '/clients/12-pharmacie-centrale/disable?csrf=' . $t, $u);
    }

    public function testUrlUnmappedActionFallsBackToOldStyle(): void
    {
        // 'livrer' is a POST-only action, not in the routes map → old-style fallback.
        $u = url('commandes', ['action' => 'livrer', 'id' => 7]);
        $this->assertSame(APP_URL . '/modules/commandes.php?action=livrer&id=7', $u);
    }

    public function testUrlUnmappedModuleFallsBack(): void
    {
        $u = url('inexistant_module', ['action' => 'x', 'id' => 1]);
        $this->assertSame(APP_URL . '/modules/inexistant_module.php?action=x&id=1', $u);
    }

    public function testUrlNewAction(): void
    {
        $this->assertSame(APP_URL . '/produits/new', url('produits', ['action' => 'add']));
        $this->assertSame(APP_URL . '/clients/new', url('clients', ['action' => 'add']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/UrlTest.php`
Expected: FAIL with `Call to undefined function slugify()` / `url()` (functions not defined yet; `includes/url.php` does not exist and is not loaded).

- [ ] **Step 3: Write minimal implementation**

Create `includes/url.php`:

```php
<?php
declare(strict_types=1);

/**
 * Clean URLs — table de routage + générateur de liens.
 *
 * slugify() + url() produisent les URLs propres (/produits/5-slug/edit, /magasin/stock…).
 * Le slug texte est décoratif : seul l'ID est exploité côté serveur (la regex .htaccess
 * capture \d+ et ignore le reste du segment). Aucune modification de la BDD.
 *
 * Chargé via includes/auth.php → disponible dans tous les points d'entrée et les tests.
 */

// Table de routage : module => forme d'URL.
//  base    : segment de base de l'URL propre
//  idSlug  : true si l'ID porte un slug (ressource avec un nom)
//  actions : map "valeur de action (GET)" => "segment URL" ('' = segment vide, ex. detail)
//  onglet  : map "valeur d'onglet" => "segment URL" (magasin)
//
// Seules les actions GET y figurent. Les actions POST-only (livrer, cloture_exec,
// saisie_save, plan_new, plan_update, delete, toggle, add-promo POST…) ne sont PAS
// listées : url() bascule alors en fallback old-style (modules/x.php?action=…),
// qui reste routé par .htaccess (accès direct module toujours valide).
$ROUTES = [
    'vente'               => ['base' => 'vente'],
    'caisse'              => ['base' => 'caisse', 'actions' => [
        'ouvrir' => 'ouvrir', 'x' => 'x', 'z' => 'z', 'rapport_session' => 'rapport_session',
        'historique' => 'historique', 'mouvement' => 'mouvement',
    ]],
    'stock'               => ['base' => 'stock'],
    'produits'            => ['base' => 'produits', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit',
    ]],
    'fournisseurs'        => ['base' => 'fournisseurs', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit', 'delete' => 'delete',
    ]],
    'clients'             => ['base' => 'clients', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit', 'detail' => '', 'reglement' => 'reglement',
        'disable' => 'disable', 'enable' => 'enable',
    ]],
    'commandes'           => ['base' => 'commandes', 'actions' => [
        'add' => 'new', 'edit' => 'edit', 'bon' => 'bon', 'livrer_form' => 'livrer',
    ]],
    'retours'             => ['base' => 'retours', 'actions' => [
        'new' => 'new', 'create' => 'create', 'detail' => 'detail',
    ]],
    'magasin'             => ['base' => 'magasin', 'onglet' => [
        'stock' => 'stock', 'reception' => 'reception', 'historique' => 'historique',
    ]],
    'marketing'           => ['base' => 'marketing', 'actions' => [
        'add-promo' => 'new', 'edit-promo' => 'edit', 'fidelite' => 'fidelite', 'add-points' => 'add-points',
    ]],
    'ventes_hist'         => ['base' => 'ventes-hist'],
    'rapports'            => ['base' => 'rapports'],
    'rapports_caissier'   => ['base' => 'rapports-caissier'],
    'comptabilite'        => ['base' => 'comptabilite', 'actions' => [
        'saisie' => 'saisie', 'journal' => 'journal', 'balance' => 'balance',
        'resultat' => 'resultat', 'bilan' => 'bilan', 'grand-livre' => 'grand-livre',
        'cloture' => 'cloture', 'plan' => 'plan', 'plan_edit' => 'plan_edit',
    ]],
    'utilisateurs'        => ['base' => 'utilisateurs', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit',
    ]],
    'remise_approbateurs' => ['base' => 'remise-approbateurs'],
    'remise_codes'        => ['base' => 'remise-codes', 'actions' => [
        'show' => 'show', 'generer' => 'generer',
    ]],
    'roles'               => ['base' => 'roles'],
    'categories'          => ['base' => 'categories'],
    'parametres'          => ['base' => 'parametres'],
];

function slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    // Suppression des accents (translitère vers ASCII).
    if (function_exists('transliterator_transliterate')) {
        $translit = @transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        if ($translit !== false) {
            $text = $translit;
        }
    }
    $text = strtolower($text);
    // Tout ce qui n'est pas alphanum => tiret.
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 80);
    } else {
        $text = substr($text, 0, 80);
    }
    return $text;
}

/**
 * Génère une URL propre.
 *
 * @param string        $module Clé dans $ROUTES (ex: 'produits').
 * @param array<string,mixed> $params Paramètres : 'action', 'id', 'onglet' sont
 *                                     consommés par la route ; le reste va en query string.
 * @param string|null   $name   Nom utilisé pour le slug (ex: produit.nom). Null = ID seul.
 */
function url(string $module, array $params = [], ?string $name = null): string
{
    global $ROUTES;

    // Module non répertorié : fallback old-style.
    if (!isset($ROUTES[$module])) {
        return APP_URL . '/modules/' . $module . '.php' . build_query($params);
    }

    $route  = $ROUTES[$module];
    $base   = $route['base'];
    $action = isset($params['action']) ? (string)$params['action'] : null;
    $onglet = isset($params['onglet']) ? (string)$params['onglet'] : null;
    $id     = isset($params['id']) ? (int)$params['id'] : null;

    // Cas spécial : plan_edit (comptabilite) => /comptabilite/plan/<id>
    if ($module === 'comptabilite' && $action === 'plan_edit' && $id !== null) {
        $path = APP_URL . '/' . $base . '/plan/' . $id;
        $rest = build_query(array_diff_key($params, ['action' => 1, 'id' => 1]));
        return $path . $rest;
    }

    // Action non listée dans la route => fallback old-style (POST-only, etc.).
    if ($action !== null && (!isset($route['actions'][$action]))) {
        return APP_URL . '/modules/' . $module . '.php' . build_query($params);
    }

    $segments = [APP_URL, $base];

    // Segment id-slug.
    if ($id !== null && !empty($route['idSlug'])) {
        $slug = slugify((string)$name);
        $segments[] = $slug !== '' ? ($id . '-' . $slug) : (string)$id;
    } elseif ($id !== null && $action !== null && isset($route['actions'][$action])) {
        // Module non-sluggable mais avec id+action (commandes, retours…).
        $segments[] = (string)$id;
    } elseif ($id !== null) {
        // id sans action ni idSlug : on garde l'id en segment (ex. stock_ajust).
        $segments[] = (string)$id;
    }

    // Segment d'action.
    if ($action !== null && isset($route['actions'][$action])) {
        $seg = $route['actions'][$action];
        if ($seg !== '') {
            $segments[] = $seg;
        }
    }

    // Segment d'onglet.
    if ($onglet !== null && isset($route['onglet'][$onglet])) {
        $segments[] = $route['onglet'][$onglet];
    } elseif ($onglet !== null && !isset($route['onglet'][$onglet])) {
        // Onglet non listé => fallback old-style.
        return APP_URL . '/modules/' . $module . '.php' . build_query($params);
    }

    $path = implode('/', $segments);

    // Query string résiduelle (tout sauf action/id/onglet consommés).
    $rest = array_diff_key($params, ['action' => 1, 'id' => 1, 'onglet' => 1]);
    return $path . build_query($rest);
}

/**
 * Construit une query string préfixée par '?' (ou '' si vide).
 * @param array<string,mixed> $params
 */
function build_query(array $params): string
{
    if (empty($params)) {
        return '';
    }
    $parts = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $parts[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    }
    return $parts ? '?' . implode('&', $parts) : '';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/UrlTest.php`
Expected: PASS (15 tests). If a test fails, fix `includes/url.php` until all pass.

- [ ] **Step 5: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/includes/url.php pharmacare/tests/UrlTest.php
git -c commit.gpgsign=false commit -m "feat(url): helper slugify()+url() + route table pour clean URLs

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Wire `url.php` into the bootstrap

**Files:**
- Modify: `includes/auth.php` (append one require at end of the require block at top)

**Interfaces:**
- Produces: `url()`, `slugify()` available in every entry point (`index.php`, `dashboard.php`, all `modules/*.php`) and in PHPUnit (bootstrap loads `auth.php`).

- [ ] **Step 1: Append the require**

In `includes/auth.php`, the top require block is currently:
```php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/rate_limit.php';
require_once __DIR__ . '/audit.php';
```
Add a fifth line after `audit.php`:
```php
require_once __DIR__ . '/url.php';
```

- [ ] **Step 2: Verify tests still pass and url() is loadable**

Run: `php vendor/bin/phpunit tests/UrlTest.php`
Expected: PASS (15 tests) — confirms `url()`/`slugify()` load via `auth.php` with no double-define errors.

- [ ] **Step 3: Verify full suite isn't broken**

Run: `php vendor/bin/phpunit`
Expected: all existing tests still PASS (no new failures).

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/includes/auth.php
git -c commit.gpgsign=false commit -m "feat(bootstrap): charge includes/url.php dans auth.php

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: `.htaccess` routing rules

**Files:**
- Modify: `.htaccess` (insert routing rules inside the existing `<IfModule mod_rewrite.c>` block, after the security `RewriteRule ^config/` lines, before the commented HTTPS redirect)

**Interfaces:**
- Consumes: none (Apache-level).
- Produces: clean URLs served as `modules/<file>.php?<query>`. No PHP change.

- [ ] **Step 1: Insert the routing rules**

Inside the existing `<IfModule mod_rewrite.c>` block, immediately after the four blocage rules (`^config/`, `^sql/`, `^\.git`, `^config/\.rate_limit/`) and before the commented HTTPS redirect, add:

```apache
    # ── Clean URLs : /<segment> → modules/<file>.php?<query> ──
    # Cibles relatives (pas de / initial) : fonctionne en sous-dossier (dev) et racine (prod).
    # Slug décoratif optionnel (?:-[^/]+)?  — seul l'ID est capturé.
    # QSA préserve les paramètres résiduels (csrf, q, page, debut, fin, mode…).

    # Pages sans query
    RewriteRule ^dashboard/?$          dashboard.php [L,QSA]
    RewriteRule ^logout/?$             logout.php [L,QSA]
    RewriteRule ^vente/?$              modules/vente.php [L,QSA]
    RewriteRule ^caisse/?$             modules/caisse.php [L,QSA]
    RewriteRule ^stock/?$               modules/stock.php [L,QSA]
    RewriteRule ^fournisseurs/?$        modules/fournisseurs.php [L,QSA]
    RewriteRule ^commandes/?$          modules/commandes.php [L,QSA]
    RewriteRule ^retours/?$            modules/retours.php [L,QSA]
    RewriteRule ^marketing/?$          modules/marketing.php [L,QSA]
    RewriteRule ^ventes-hist/?$        modules/ventes_hist.php [L,QSA]
    RewriteRule ^rapports/?$           modules/rapports.php [L,QSA]
    RewriteRule ^rapports-caissier/?$   modules/rapports_caissier.php [L,QSA]
    RewriteRule ^utilisateurs/?$       modules/utilisateurs.php [L,QSA]
    RewriteRule ^remise-approbateurs/?$ modules/remise_approbateurs.php [L,QSA]
    RewriteRule ^remise-codes/?$       modules/remise_codes.php [L,QSA]
    RewriteRule ^roles/?$               modules/roles.php [L,QSA]
    RewriteRule ^categories/?$         modules/categories.php [L,QSA]
    RewriteRule ^parametres/?$         modules/parametres.php [L,QSA]

    # Produits (id-slug + action)
    RewriteRule ^produits/new/?$                       modules/produits.php?action=add [L,QSA]
    RewriteRule ^produits/(\d+)(?:-[^/]+)?/edit/?$      modules/produits.php?action=edit&id=$1 [L,QSA]
    RewriteRule ^produits/?$                            modules/produits.php [L,QSA]

    # Fournisseurs (id-slug + action)
    RewriteRule ^fournisseurs/new/?$                    modules/fournisseurs.php?action=add [L,QSA]
    RewriteRule ^fournisseurs/(\d+)(?:-[^/]+)?/delete/?$ modules/fournisseurs.php?action=delete&id=$1 [L,QSA]
    RewriteRule ^fournisseurs/(\d+)(?:-[^/]+)?/edit/?$  modules/fournisseurs.php?action=edit&id=$1 [L,QSA]
    RewriteRule ^fournisseurs/?$                       modules/fournisseurs.php [L,QSA]

    # Clients (id-slug + action ; detail sans suffixe)
    RewriteRule ^clients/new/?$                        modules/clients.php?action=add [L,QSA]
    RewriteRule ^clients/(\d+)(?:-[^/]+)?/edit/?$       modules/clients.php?action=edit&id=$1 [L,QSA]
    RewriteRule ^clients/(\d+)(?:-[^/]+)?/reglement/?$ modules/clients.php?action=reglement&id=$1 [L,QSA]
    RewriteRule ^clients/(\d+)(?:-[^/]+)?/disable/?$   modules/clients.php?action=disable&id=$1 [L,QSA]
    RewriteRule ^clients/(\d+)(?:-[^/]+)?/enable/?$    modules/clients.php?action=enable&id=$1 [L,QSA]
    RewriteRule ^clients/(\d+)(?:-[^/]+)?/?$          modules/clients.php?action=detail&id=$1 [L,QSA]
    RewriteRule ^clients/?$                            modules/clients.php [L,QSA]

    # Utilisateurs (id-slug + action)
    RewriteRule ^utilisateurs/new/?$                   modules/utilisateurs.php?action=add [L,QSA]
    RewriteRule ^utilisateurs/(\d+)(?:-[^/]+)?/edit/?$ modules/utilisateurs.php?action=edit&id=$1 [L,QSA]
    RewriteRule ^utilisateurs/(\d+)(?:-[^/]+)?/?$       modules/utilisateurs.php [L,QSA]

    # Commandes (id + action, pas de slug)
    RewriteRule ^commandes/new/?$                      modules/commandes.php?action=add [L,QSA]
    RewriteRule ^commandes/(\d+)/bon/?$                modules/commandes.php?action=bon&id=$1 [L,QSA]
    RewriteRule ^commandes/(\d+)/livrer/?$             modules/commandes.php?action=livrer_form&id=$1 [L,QSA]
    RewriteRule ^commandes/(\d+)/edit/?$               modules/commandes.php?action=edit&id=$1 [L,QSA]
    RewriteRule ^commandes/(\d+)/?$                    modules/commandes.php [L,QSA]

    # Retours (id + action)
    RewriteRule ^retours/new/?$                       modules/retours.php?action=new [L,QSA]
    RewriteRule ^retours/(\d+)/detail/?$               modules/retours.php?action=detail&id=$1 [L,QSA]
    RewriteRule ^retours/?$                            modules/retours.php [L,QSA]

    # Caisse (action + id)
    RewriteRule ^caisse/ouvrir/?$                      modules/caisse.php?action=ouvrir [L,QSA]
    RewriteRule ^caisse/(historique|mouvement)/?$      modules/caisse.php?action=$1 [L,QSA]
    RewriteRule ^caisse/(\d+)/(x|z|rapport_session)/?$ modules/caisse.php?action=$2&id=$1 [L,QSA]

    # Magasin (onglet=)
    RewriteRule ^magasin/(stock|reception|historique)/?$ modules/magasin.php?onglet=$1 [L,QSA]
    RewriteRule ^magasin/?$                            modules/magasin.php [L,QSA]

    # Marketing (action + id)
    RewriteRule ^marketing/new/?$                      modules/marketing.php?action=add-promo [L,QSA]
    RewriteRule ^marketing/(\d+)/edit/?$               modules/marketing.php?action=edit-promo&id=$1 [L,QSA]
    RewriteRule ^marketing/fidelite/?$                  modules/marketing.php?action=fidelite [L,QSA]
    RewriteRule ^marketing/add-points/?$               modules/marketing.php?action=add-points [L,QSA]
    RewriteRule ^marketing/?$                          modules/marketing.php [L,QSA]

    # Remise-codes (action + id)
    RewriteRule ^remise-codes/(\d+)/?$                 modules/remise_codes.php?action=show&id=$1 [L,QSA]
    RewriteRule ^remise-codes/generer/?$               modules/remise_codes.php?action=generer [L,QSA]
    RewriteRule ^remise-codes/?$                       modules/remise_codes.php [L,QSA]

    # Comptabilité (action=) — plan_edit avec id avant le générique
    RewriteRule ^comptabilite/plan/(\d+)/?$            modules/comptabilite.php?action=plan_edit&id=$1 [L,QSA]
    RewriteRule ^comptabilite/(saisie|journal|balance|resultat|bilan|grand-livre|cloture|plan)/?$ modules/comptabilite.php?action=$1 [L,QSA]
    RewriteRule ^comptabilite/?$                        modules/comptabilite.php [L,QSA]
```

Leave the commented HTTPS redirect block and everything after `</IfModule>` (headers, FilesMatch) untouched.

- [ ] **Step 2: Manual verification in dev (XAMPP)**

This task is not unit-testable (requires Apache + mod_rewrite). Verify by hand:

1. Ensure Apache is running and `mod_rewrite` is enabled in XAMPP (it is by default).
2. Log in to PharmaCare at `http://localhost/pharmacare/`.
3. In the browser, visit these clean URLs directly and confirm each loads the right module (no 404, no redirect loop):
   - `http://localhost/pharmacare/vente`
   - `http://localhost/pharmacare/produits`
   - `http://localhost/pharmacare/magasin/stock`
   - `http://localhost/pharmacare/comptabilite/cloture`
   - `http://localhost/pharmacare/dashboard`
4. Confirm backward compat: `http://localhost/pharmacare/modules/produits.php?action=edit&id=1` still loads.
5. Confirm an ID-only clean URL works: `http://localhost/pharmacare/produits/1/edit` loads the edit form for product 1.

If a clean URL 404s in the subfolder, add `RewriteBase /pharmacare/` after `RewriteEngine On` and re-test; remove it again only if prod needs `/` (prod vhost is domain-root, so `RewriteBase /` would be needed there instead — document the choice in the vhost file). Default: keep relative targets, no RewriteBase; only add if verification fails.

- [ ] **Step 3: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/.htaccess
git -c commit.gpgsign=false commit -m "feat(htaccess): règles de rewrite pour les clean URLs

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: Regression test scaffold for link migration

**Files:**
- Create: `tests/CleanUrlLinksTest.php`

**Interfaces:**
- Produces: `assertNoOldStyleGetHref(string $file)` helper + a test per converted file. Later tasks add their files to the list. Fails until the listed files are converted.

- [ ] **Step 1: Write the failing test scaffold**

Create `tests/CleanUrlLinksTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Régression : aucun lien GET (href) ne doit pointer vers /modules/<x>.php
 * après migration vers les clean URLs. Les formulaires POST (action="") et les
 * header('Location:') sont exclus du contrôle (action= n'est pas href=).
 *
 * Chaque tâche de conversion ajoute ses fichiers à la liste couverte.
 */
final class CleanUrlLinksTest extends TestCase
{
    /**
     * Fichiers à vérifier. Ajouter au fur et à mesure des tâches de conversion.
     */
    private function convertedFiles(): array
    {
        return [
            'includes/layout.php',
            'includes/dashboard-admin.php',
            'includes/dashboard-caissier.php',
            'includes/dashboard-pharmacien.php',
        ];
    }

    private function assertNoOldStyleGetHref(string $relPath): void
    {
        $abs = __DIR__ . '/../' . $relPath;
        $this->assertFileExists($abs, "Fichier manquant : $relPath");
        $src = file_get_contents($abs);
        // href="...modules/<x>.php" ou href='...modules/<x>.php' = lien GET old-style.
        $pattern = '/href\s*=\s*["\'][^"\']*\/modules\/[a-z_]+\.php/i';
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            $src,
            "Lien GET old-style vers modules/ encore présent dans $relPath — convertir en url()."
        );
    }

    public function testConvertedFilesHaveNoOldStyleGetHref(): void
    {
        foreach ($this->convertedFiles() as $f) {
            $this->assertNoOldStyleGetHref($f);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: FAIL — `includes/layout.php` still contains `href="<?= APP_URL ?>/modules/vente.php` style links.

- [ ] **Step 3: Commit (scaffold only, test expected to fail until Task 5)**

```bash
cd C:/xampp/htdocs
git add pharmacare/tests/CleanUrlLinksTest.php
git -c commit.gpgsign=false commit -m "test(clean-urls): scaffold régression liens GET old-style

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Convert `includes/layout.php` (nav + dropdowns)

**Files:**
- Modify: `includes/layout.php` (nav items ~lines 189–305, dropdown alert links ~349–405)

**Interfaces:**
- Consumes: `url()` from Task 1.
- Produces: sidebar/dropdowns emit clean URLs; `CleanUrlLinksTest` passes for `includes/layout.php`.

- [ ] **Step 1: Convert nav `<a>` hrefs**

Every nav link of the form `href="<?= APP_URL ?>/modules/<x>.php"` becomes `href="<?= url('<x>') ?>"`. For links with query (`?action=…&id=…`, `?onglet=…`, `?filtre=…`), use the matching `url()` call.

Pattern replacements (apply each in `includes/layout.php`):

| Before | After |
|---|---|
| `href="<?= APP_URL ?>/modules/vente.php"` | `href="<?= url('vente') ?>"` |
| `href="<?= APP_URL ?>/modules/remise_codes.php"` | `href="<?= url('remise_codes') ?>"` |
| `href="<?= APP_URL ?>/modules/caisse.php"` | `href="<?= url('caisse') ?>"` |
| `href="<?= APP_URL ?>/modules/stock.php"` | `href="<?= url('stock') ?>"` |
| `href="<?= APP_URL ?>/modules/produits.php"` | `href="<?= url('produits') ?>"` |
| `href="<?= APP_URL ?>/modules/fournisseurs.php"` | `href="<?= url('fournisseurs') ?>"` |
| `href="<?= APP_URL ?>/modules/clients.php"` | `href="<?= url('clients') ?>"` |
| `href="<?= APP_URL ?>/modules/commandes.php"` | `href="<?= url('commandes') ?>"` |
| `href="<?= APP_URL ?>/modules/retours.php"` | `href="<?= url('retours') ?>"` |
| `href="<?= APP_URL ?>/modules/magasin.php"` | `href="<?= url('magasin') ?>"` |
| `href="<?= APP_URL ?>/modules/marketing.php"` | `href="<?= url('marketing') ?>"` |
| `href="<?= APP_URL ?>/modules/ventes_hist.php"` | `href="<?= url('ventes_hist') ?>"` |
| `href="<?= APP_URL ?>/modules/rapports.php"` | `href="<?= url('rapports') ?>"` |
| `href="<?= APP_URL ?>/modules/rapports_caissier.php"` | `href="<?= url('rapports_caissier') ?>"` |
| `href="<?= APP_URL ?>/modules/comptabilite.php"` | `href="<?= url('comptabilite') ?>"` |
| `href="<?= APP_URL ?>/modules/utilisateurs.php"` | `href="<?= url('utilisateurs') ?>"` |
| `href="<?= APP_URL ?>/modules/remise_approbateurs.php"` | `href="<?= url('remise_approbateurs') ?>"` |
| `href="<?= APP_URL ?>/modules/roles.php"` | `href="<?= url('roles') ?>"` |
| `href="<?= APP_URL ?>/modules/categories.php"` | `href="<?= url('categories') ?>"` |
| `href="<?= APP_URL ?>/modules/parametres.php"` | `href="<?= url('parametres') ?>"` |

Dropdown / alert links with query (keep their specific params):

| Before | After |
|---|---|
| `href="<?= APP_URL ?>/modules/produits.php?action=edit&id=<?= $ap['id'] ?>"` | `href="<?= url('produits', ['action'=>'edit','id'=>$ap['id']], $ap['nom'] ?? null) ?>"` |
| `href="<?= APP_URL ?>/modules/stock.php?filtre=alerte"` | `href="<?= url('stock', ['filtre'=>'alerte']) ?>"` |
| `href="<?= APP_URL ?>/modules/magasin.php?onglet=stock"` | `href="<?= url('magasin', ['onglet'=>'stock']) ?>"` |

Where `$ap['nom']` (the alert product name) is available in scope, pass it as the third `url()` arg so the slug is emitted; if the variable name differs in that block, use the actual product-name field available there (read the surrounding code). Do not invent a variable — if no name is in scope, pass `null`.

- [ ] **Step 2: Run the regression test**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: `includes/layout.php` passes the GET-href assertion (other files in the list may still fail — acceptable for this task; the test as a whole passes only when all listed files are converted, which completes in Task 6).

If `CleanUrlLinksTest` still fails on `layout.php`, search for any remaining `href="…/modules/…php` and convert it.

- [ ] **Step 3: Manual smoke test**

Log in, confirm the sidebar still highlights the active item and every nav link navigates to a clean URL in the address bar.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/includes/layout.php
git -c commit.gpgsign=false commit -m "feat(layout): nav + dropdowns vers clean URLs (url())

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 6: Convert dashboard tiles (`includes/dashboard-*.php`)

**Files:**
- Modify: `includes/dashboard-admin.php`, `includes/dashboard-caissier.php`, `includes/dashboard-pharmacien.php`

**Interfaces:**
- Consumes: `url()`.
- Produces: dashboard tiles link to clean URLs; `CleanUrlLinksTest` passes for all three dashboard files.

- [ ] **Step 1: Convert tile links**

In the three dashboard files, replace each `href="<?= APP_URL ?>/modules/<x>.php"` and `href="<?= APP_URL ?>/modules/<x>.php?<query>` with the matching `url()` call, using the same mapping as Task 5. Specific known patterns:

- `dashboard-admin.php`:
  - `href="<?= APP_URL ?>/modules/stock.php"` → `href="<?= url('stock') ?>"`
  - `href="<?= APP_URL ?>/modules/ventes_hist.php"` → `href="<?= url('ventes_hist') ?>"`
  - `href="<?= APP_URL ?>/modules/commandes.php"` → `href="<?= url('commandes') ?>"`
- `dashboard-caissier.php`:
  - `href="<?= APP_URL ?>/modules/vente.php"` → `href="<?= url('vente') ?>"`
- `dashboard-pharmacien.php`:
  - `href="<?= APP_URL ?>/modules/stock.php"` → `href="<?= url('stock') ?>"`

Scan each file for any other `APP_URL ?>/modules/` and convert with the Task 5 mapping.

- [ ] **Step 2: Run regression test**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: PASS — all four files in `convertedFiles()` now pass.

- [ ] **Step 3: Manual smoke test**

Log in as admin / pharmacien / caissier; confirm each role's dashboard tiles navigate to clean URLs.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/includes/dashboard-admin.php pharmacare/includes/dashboard-caissier.php pharmacare/includes/dashboard-pharmacien.php
git -c commit.gpgsign=false commit -m "feat(dashboard): tuiles vers clean URLs (url())

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 7: Convert id-slug resource modules (`produits`, `clients`, `fournisseurs`, `utilisateurs`)

**Files:**
- Modify: `modules/produits.php`, `modules/clients.php`, `modules/fournisseurs.php`, `modules/utilisateurs.php`
- Modify: `tests/CleanUrlLinksTest.php` (add these files to `convertedFiles()`)

**Interfaces:**
- Consumes: `url()`.
- Produces: GET links (edit/detail/disable/enable/delete row buttons, add buttons) and post-redirect `header('Location:')` use clean URLs. POST form `action=""` stays old-style (still works).

- [ ] **Step 1: Add files to the regression test**

In `tests/CleanUrlLinksTest.php`, extend `convertedFiles()`:
```php
        return [
            'includes/layout.php',
            'includes/dashboard-admin.php',
            'includes/dashboard-caissier.php',
            'includes/dashboard-pharmacien.php',
            'modules/produits.php',
            'modules/clients.php',
            'modules/fournisseurs.php',
            'modules/utilisateurs.php',
        ];
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: FAIL on the four newly-added module files (they still contain `href="…/modules/…php` GET links).

- [ ] **Step 3: Convert `modules/produits.php`**

Replace GET links and redirects:
- Edit link in list rows: `href="<?= APP_URL ?>/modules/produits.php?action=edit&id=<?= $p['id'] ?>"` → `href="<?= url('produits', ['action'=>'edit','id'=>$p['id']], $p['nom'] ?? null) ?>"`
- Add button: `href="<?= APP_URL ?>/modules/produits.php?action=add"` → `href="<?= url('produits', ['action'=>'add']) ?>"`
- Any `header('Location: ' . APP_URL . '/modules/produits.php?…')` → `header('Location: ' . url('produits', [...params...]))` (use the same params the redirect used, e.g. `['action'=>'edit','id'=>$id]` with the product name if in scope).
- Relative redirect `header('Location: ?action=list')` or `header('Location: produits.php')` → `header('Location: ' . url('produits'))`.
- GET search `<form method="GET" action="...">` (line ~183): if its `action` attribute points to `modules/produits.php`, change to empty `action=""` so it stays on the current clean URL (`/produits?q=…`). If it already has empty/no action, leave it.
- **Do not change** `<form method="POST">` actions (e.g. line ~80) — they stay as-is.

- [ ] **Step 4: Convert `modules/clients.php`**

- Detail link: `href="<?= APP_URL ?>/modules/clients.php?action=detail&id=<?= $c['id'] ?>"` → `href="<?= url('clients', ['action'=>'detail','id'=>$c['id']], $c['nom'] ?? null) ?>"`
- Edit link: `…?action=edit&id=<?= $c['id'] ?>` → `url('clients', ['action'=>'edit','id'=>$c['id']], $c['nom'] ?? null)`
- Add button: `…?action=add` → `url('clients', ['action'=>'add'])`
- Disable/enable GET links: `…?action=disable&id=<?= $c['id'] ?>&csrf=<?= csrf() ?>` → `url('clients', ['action'=>'disable','id'=>$c['id'],'csrf'=>csrf()], $c['nom'] ?? null)` ; same for `enable`.
- `header('Location: ' . APP_URL . '/modules/clients.php?…')` → `url('clients', […])`.
- **Do not change** POST `<form action="<?= APP_URL ?>/modules/clients.php?action=reglement">`, `…?action=edit&id=…`, `…?action=add` (lines ~269, ~383, ~494) — these are POST actions, leave them old-style (they still route). Same for the POST `disable`/`enable` handlers if any.

- [ ] **Step 5: Convert `modules/fournisseurs.php`**

- Edit link: `…?action=edit&id=<?= $f['id'] ?>` → `url('fournisseurs', ['action'=>'edit','id'=>$f['id']], $f['nom'] ?? null)`
- Add: `…?action=add` → `url('fournisseurs', ['action'=>'add'])`
- Delete (GET link, if present): `…?action=delete&id=<?= $f['id'] ?>` → `url('fournisseurs', ['action'=>'delete','id'=>$f['id']], $f['nom'] ?? null)` — keep any `csrf` param in the array.
- Redirects → `url('fournisseurs', […])`.
- **Do not change** POST form actions.

- [ ] **Step 6: Convert `modules/utilisateurs.php`**

- Edit: `…?action=edit&id=<?= $u['id'] ?>` → `url('utilisateurs', ['action'=>'edit','id'=>$u['id']], trim(($u['prenom'] ?? '').' '.($u['nom'] ?? '')) ?: null)`
- Add: `…?action=add` → `url('utilisateurs', ['action'=>'add'])`
- Redirects → `url('utilisateurs', […])`.
- **Do not change** POST form actions.

- [ ] **Step 7: Run regression test**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: PASS — all 8 files now pass the GET-href assertion.

- [ ] **Step 8: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 9: Manual smoke test**

In the browser: open `/produits`, click a product's Edit button → address bar shows `/produits/<id>-<slug>/edit`, edit saves and redirects to a clean URL. Repeat for clients detail/edit, fournisseurs edit, utilisateurs edit. Confirm disable/enable links carry `csrf` in the query.

- [ ] **Step 10: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/modules/produits.php pharmacare/modules/clients.php pharmacare/modules/fournisseurs.php pharmacare/modules/utilisateurs.php pharmacare/tests/CleanUrlLinksTest.php
git -c commit.gpgsign=false commit -m "feat(modules): produits/clients/fournisseurs/utilisateurs vers clean URLs

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 8: Convert tabbed / action-page modules (`magasin`, `comptabilite`, `caisse`, `marketing`, `remise_codes`)

**Files:**
- Modify: `modules/magasin.php`, `modules/comptabilite.php`, `modules/caisse.php`, `modules/marketing.php`, `modules/remise_codes.php`
- Modify: `tests/CleanUrlLinksTest.php` (add these files)

**Interfaces:**
- Consumes: `url()`.
- Produces: tab/page links and post-redirects use clean URLs. POST actions stay old-style (notably `comptabilite cloture_exec`, `caisse` POST handlers, `marketing toggle/add-points POST`).

- [ ] **Step 1: Add files to the regression test**

Extend `convertedFiles()` with:
```php
            'modules/magasin.php',
            'modules/comptabilite.php',
            'modules/caisse.php',
            'modules/marketing.php',
            'modules/remise_codes.php',
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: FAIL on the five new files.

- [ ] **Step 3: Convert `modules/magasin.php`**

- Onglet links: `href="<?= APP_URL ?>/modules/magasin.php?onglet=stock"` (and relative `?onglet=stock`) → `href="<?= url('magasin', ['onglet'=>'stock']) ?>"` ; same for `reception`, `historique`.
- Any `href="<?= APP_URL ?>/modules/magasin.php"` (base) → `url('magasin')`.
- `header('Location: …modules/magasin.php?onglet=…)` → `url('magasin', ['onglet'=>…])`.
- **Do not change** POST form actions (transfert/retour/reception POST handlers) — they keep `action=""` (empty or relative). If a POST form uses an absolute `APP_URL/modules/magasin.php` action, it still routes; leave it.
- Note: the existing `showTrfDetail()` JS reads from `json_encode` (unaffected by this task).

- [ ] **Step 4: Convert `modules/comptabilite.php`**

- Page/tab links: `href="<?= APP_URL ?>/modules/comptabilite.php?action=journal"` (and relative `?action=journal`) → `href="<?= url('comptabilite', ['action'=>'journal']) ?>"` ; same for `saisie`, `balance`, `resultat`, `bilan`, `grand-livre`, `cloture`, `plan`.
- Plan edit link: `…?action=plan_edit&id=<?= $c['id'] ?>` → `url('comptabilite', ['action'=>'plan_edit','id'=>$c['id']])`.
- Base link `modules/comptabilite.php` → `url('comptabilite')`.
- Redirects → `url('comptabilite', […])`.
- **Do not change** POST forms: `?action=cloture_exec` (cloture confirmation POST), `?action=saisie_save`, `?action=plan_new`, `?action=plan_update`, `?action=plan_edit` when it's a POST. Leave POST actions old-style.

- [ ] **Step 5: Convert `modules/caisse.php`**

- `?action=ouvrir` → `url('caisse', ['action'=>'ouvrir'])`.
- `?action=historique` → `url('caisse', ['action'=>'historique'])` ; `?action=mouvement` → `url('caisse', ['action'=>'mouvement'])`.
- Session links with id: `?action=x&id=<?= $s['id'] ?>` → `url('caisse', ['action'=>'x','id'=>$s['id']])` ; same for `z`, `rapport_session`.
- Base `modules/caisse.php` → `url('caisse')`. Redirects → `url('caisse', […])`.
- **Do not change** POST form actions (ouvrir session, mouvement POST).

- [ ] **Step 6: Convert `modules/marketing.php`**

- `?action=add-promo` → `url('marketing', ['action'=>'add-promo'])` (maps to `/marketing/new`).
- `?action=edit-promo&id=<?= $p['id'] ?>` → `url('marketing', ['action'=>'edit-promo','id'=>$p['id']])` (maps to `/marketing/<id>/edit`).
- `?action=fidelite` → `url('marketing', ['action'=>'fidelite'])`.
- `?action=add-points` → `url('marketing', ['action'=>'add-points'])`.
- Cross-link to clients detail: `href="<?= APP_URL ?>/modules/clients.php?action=detail&id=<?= $c['id'] ?>"` → `url('clients', ['action'=>'detail','id'=>$c['id']], $c['nom'] ?? null)`.
- **Do not change** POST form actions (`toggle`, `add-points`, `add-promo`, `edit-promo` POST handlers, csrf).

- [ ] **Step 7: Convert `modules/remise_codes.php`**

- `?action=show&id=<?= $code['id'] ?>` → `url('remise_codes', ['action'=>'show','id'=>$code['id']])`.
- `?action=generer` → `url('remise_codes', ['action'=>'generer'])`.
- Base `modules/remise_codes.php` → `url('remise_codes')`. Redirects → `url('remise_codes', […])`.
- **Do not change** POST form actions.

- [ ] **Step 8: Run regression test**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: PASS — all 13 files pass.

- [ ] **Step 9: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 10: Manual smoke test**

Browser: `/magasin/stock`, switch tabs to `reception`/`historique` (clean URLs). `/comptabilite/journal`, `/comptabilite/cloture` (GET form) then submit (POST exec, old-style — confirm it still works and redirects to a clean URL). `/caisse/ouvrir`. `/marketing/new`.

- [ ] **Step 11: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/modules/magasin.php pharmacare/modules/comptabilite.php pharmacare/modules/caisse.php pharmacare/modules/marketing.php pharmacare/modules/remise_codes.php pharmacare/tests/CleanUrlLinksTest.php
git -c commit.gpgsign=false commit -m "feat(modules): magasin/comptabilite/caisse/marketing/remise-codes vers clean URLs

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 9: Convert remaining modules (`commandes`, `retours`, `stock`, `stock_ajust`, `rapports`, `rapports_caissier`, `ventes_hist`, `vente`, `roles`, `categories`, `parametres`, `remise_approbateurs`)

**Files:**
- Modify: the 12 module files listed above.
- Modify: `tests/CleanUrlLinksTest.php` (add these files)

**Interfaces:**
- Consumes: `url()`.
- Produces: all remaining GET links + redirects use clean URLs; the entire suite passes.

- [ ] **Step 1: Add files to the regression test**

Extend `convertedFiles()` with:
```php
            'modules/commandes.php',
            'modules/retours.php',
            'modules/stock.php',
            'modules/stock_ajust.php',
            'modules/rapports.php',
            'modules/rapports_caissier.php',
            'modules/ventes_hist.php',
            'modules/vente.php',
            'modules/roles.php',
            'modules/categories.php',
            'modules/parametres.php',
            'modules/remise_approbateurs.php',
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: FAIL on the newly-added files (those that have GET links).

- [ ] **Step 3: Convert `modules/commandes.php`**

- List "Livrer" button: `href="?action=livrer_form&id=<?= $c['id'] ?>"` → `href="<?= url('commandes', ['action'=>'livrer_form','id'=>$c['id']]) ?>"` (maps to `/commandes/<id>/livrer`).
- Add: `href="?action=add"` (or absolute) → `url('commandes', ['action'=>'add'])` (maps to `/commandes/new`).
- Edit: `?action=edit&id=<?= $c['id'] ?>` → `url('commandes', ['action'=>'edit','id'=>$c['id']])` (`/commandes/<id>/edit`).
- Bon: `?action=bon&id=<?= $c['id'] ?>` → `url('commandes', ['action'=>'bon','id'=>$c['id']])` (`/commandes/<id>/bon`).
- Redirect `header('Location: ' . APP_URL . '/modules/commandes.php?action=livrer_form&id=' . $id)` → `header('Location: ' . url('commandes', ['action'=>'livrer_form','id'=>$id]))`.
- Other redirects → `url('commandes', […])`.
- **Do not change** POST form actions: the livrer form at line ~217 `action="?action=livrer&id=<?= $id ?>"` (POST) stays — it posts to the old-style `?action=livrer` which routes fine. The main commande form (`id="cmd-form"`, line ~317) and the delete POST handler stay as-is.

- [ ] **Step 4: Convert `modules/retours.php`**

- `?action=new` → `url('retours', ['action'=>'new'])` (`/retours/new`).
- `?action=new&id=<?= $v['id'] ?>` → `url('retours', ['action'=>'new','id'=>$v['id']])`.
- `?action=detail&id=<?= $r['id'] ?>` → `url('retours', ['action'=>'detail','id'=>$r['id']])` (`/retours/<id>/detail`).
- Cross-link to `ventes_hist.php?action=detail&id=…` → `url('ventes_hist', ['action'=>'detail','id'=>…])` — note `ventes_hist` route has no `actions` map, so `action=detail` is unmapped → `url()` returns old-style fallback `/modules/ventes_hist.php?action=detail&id=…`. That's correct and acceptable (the link still works). Do not force a clean path for it.
- Redirects → `url('retours', […])`. **Do not change** POST form actions.

- [ ] **Step 5: Convert `modules/stock.php`**

- `?filtre=alerte` and other `?filtre=`/`?cat=` links: `url('stock', ['filtre'=>'alerte'])` (filtre/cat stay in query string — `url('stock')` has no actions, so residual params go to `?filtre=…`).
- Cross-links to produits edit/add: `modules/produits.php?action=edit&id=<?= $p['id'] ?>` → `url('produits', ['action'=>'edit','id'=>$p['id']], $p['nom'] ?? null)`.
- Cross-link `modules/stock_ajust.php?id=<?= $p['id'] ?>` → `url('stock_ajust', ['id'=>$p['id']])`. **Add `stock_ajust` to the route table** (Task 1's `$ROUTES`): `'stock_ajust' => ['base' => 'stock-ajust']` so `url('stock_ajust', ['id'=>5])` → `/stock-ajust/5`. Add the matching `.htaccess` rule: `RewriteRule ^stock-ajust/(\d+)/?$ modules/stock_ajust.php?id=$1 [L,QSA]` and `RewriteRule ^stock-ajust/?$ modules/stock_ajust.php [L,QSA]` (place with the base routes in Task 3's block).
- Base `modules/stock.php` → `url('stock')`. Redirects → `url('stock', […])`.

- [ ] **Step 6: Convert `modules/stock_ajust.php`**

- Return link to `stock.php` → `url('stock')`.
- Any redirect → `url('stock', […])` or `url('stock_ajust', […])`.
- **Do not change** POST form actions.

- [ ] **Step 7: Convert `modules/rapports.php` and `modules/rapports_caissier.php`**

- These use `?periode=…&debut=…&fin=…&mvt_page=…` (rapports) and `?periode=…&debut=…&fin=…` (rapports_caissier). All residual params → `url('rapports', ['periode'=>…,'debut'=>…,'fin'=>…,'mvt_page'=>…])` → `/rapports?periode=…&debut=…` (base + query). Same for `rapports_caissier` → `/rapports-caissier?…`.
- Cross-link from `rapports_caissier` to `ventes_hist.php?debut=…&fin=…` → `url('ventes_hist', ['debut'=>…,'fin'=>…])` → `/ventes-hist?debut=…`.
- Reset link `href="?"` → `href="<?= url('rapports') ?>"` (resp. `url('rapports_caissier')`).
- **Do not change** POST form actions if any.

- [ ] **Step 8: Convert `modules/ventes_hist.php`**

- Reset `href="?"` → `url('ventes_hist')`.
- `?debut=…&fin=…&mode=…&page=…` filter links → `url('ventes_hist', ['debut'=>…,'fin'=>…,'mode'=>…,'page'=>…])` → `/ventes-hist?debut=…`.
- Cross-link `?action=detail&id=…` (if present) → `url('ventes_hist', ['action'=>'detail','id'=>…])` (unmapped action → old-style fallback, which is fine).
- **Do not change** POST form actions.

- [ ] **Step 9: Convert `modules/vente.php`**

- `?receipt=…` link → `url('vente', ['receipt'=>…])` → `/vente?receipt=…`.
- Cross-link to `caisse.php?action=ouvrir` → `url('caisse', ['action'=>'ouvrir'])`.
- Cross-link to `remise_codes.php` → `url('remise_codes')`.
- Cross-link to `ventes_hist.php` → `url('ventes_hist')`.
- **Do not change** the POS POST form (the checkout) — keep its `action=""`.

- [ ] **Step 10: Convert `modules/roles.php`, `modules/categories.php`, `modules/parametres.php`, `modules/remise_approbateurs.php`**

These have few/no GET links (mostly POST actions):
- `roles.php`: `?action=add` (POST) — do not change. Any base/redirect → `url('roles')`.
- `categories.php`: POST delete — do not change. Any redirect → `url('categories')`.
- `parametres.php`: no GET params. Any base link → `url('parametres')`.
- `remise_approbateurs.php`: POST `ajouter` — do not change. Any base/redirect → `url('remise_approbateurs')`.
- Run the regression test after each — these files likely already pass (no GET `href` to `/modules/`), so no edits may be needed beyond confirming.

- [ ] **Step 11: Run regression test**

Run: `php vendor/bin/phpunit tests/CleanUrlLinksTest.php`
Expected: PASS — all 25 files in `convertedFiles()` pass.

- [ ] **Step 12: Run full suite**

Run: `php vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 13: Manual smoke test**

Browser walkthrough:
- `/commandes/new` (add form), create a commande, livrer flow: `/commandes/<id>/livrer` (GET form) → submit POST (old-style) → redirect clean.
- `/retours/new?id=<vente>`, `/retours/<id>/detail`.
- `/stock?filtre=alerte`, click a product edit → `/produits/<id>-slug/edit`, click "Ajuster" → `/stock-ajust/<id>`.
- `/rapports?periode=30j`, `/rapports-caissier?periode=30j`, `/ventes-hist?debut=…`.
- `/vente` (POS), print a receipt, click the receipt link → `/vente?receipt=…`.
- `/roles`, `/categories`, `/parametres`, `/remise-approbateurs` load.
- Confirm old-style URLs still work (backward compat): `modules/produits.php?action=edit&id=1`.

- [ ] **Step 14: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/modules/commandes.php pharmacare/modules/retours.php pharmacare/modules/stock.php pharmacare/modules/stock_ajust.php pharmacare/modules/rapports.php pharmacare/modules/rapports_caissier.php pharmacare/modules/ventes_hist.php pharmacare/modules/vente.php pharmacare/modules/roles.php pharmacare/modules/categories.php pharmacare/modules/parametres.php pharmacare/modules/remise_approbateurs.php pharmacare/includes/url.php pharmacare/.htaccess pharmacare/tests/CleanUrlLinksTest.php
git -c commit.gpgsign=false commit -m "feat(modules): clean URLs pour commandes/retours/stock/rapports/ventes_hist/vente + stock_ajust route

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 10: Final verification & doc note

**Files:**
- Modify: `deploy/DEPLOIEMENT.md` (add a short note about clean URLs + Apache `mod_rewrite` requirement)

**Interfaces:** none.

- [ ] **Step 1: Add a deployment note**

In `deploy/DEPLOIEMENT.md`, add a section:

```markdown
## URLs propres (clean URLs)

PharmaCare utilise des URLs propres (`/vente`, `/produits/5-paracetamol-500mg/edit`,
`/magasin/stock`, `/comptabilite/cloture`) via `mod_rewrite`. Prérequis de production :

- Apache 2.4 avec `mod_rewrite` activé (`a2enmod rewrite`).
- `AllowOverride All` sur le `DocumentRoot` (déjà dans la conf vhost fournie).
- Les règles de rewrite utilisent des **cibles relatives** — fonctionnent en sous-dossier
  (dev) et à la racine du domaine (prod). Si une clean URL 404 en prod, ajouter
  `RewriteBase /` après `RewriteEngine On` dans `.htaccess`.

Les anciennes URLs `modules/<x>.php?…` restent valides (aucune redirection imposée).
Les formulaires POST conservent leurs `action=""` d'origine (old-style ou relatif),
routés via l'accès direct module qui demeure autorisé.
```

- [ ] **Step 2: Run the full PHPUnit suite one last time**

Run: `php vendor/bin/phpunit`
Expected: all tests PASS (UrlTest, CleanUrlLinksTest, existing tests).

- [ ] **Step 3: Final manual end-to-end walkthrough**

Log in as each role (admin, pharmacien, caissier); navigate every sidebar item; open a product/client/fournisseur/utilisateur in edit via clean URL; submit a save and confirm the post-redirect lands on a clean URL; open magasin tabs, compta pages, caisse, rapports. Confirm the address bar shows clean URLs throughout and nothing 404s.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs
git add pharmacare/deploy/DEPLOIEMENT.md
git -c commit.gpgsign=false commit -m "docs(deploy): note clean URLs + prérequis mod_rewrite

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** §4 helper → Task 1; §5 .htaccess → Task 3 (plus the `stock_ajust` additions in Task 9); §6.1 conversions → Tasks 5–9; §6.2 POST untouched → stated as "Do not change" in every conversion task; §6.3 exceptions (`commandes livrer`, `comptabilite cloture`) → handled by the "unmapped action → fallback" rule (Task 1 `url()` + Task 8/9 "Do not change POST"); §7 edge cases → Task 3 manual verification (order, backward compat, slug optional); §8 tests → `tests/UrlTest.php` (Task 1) + `tests/CleanUrlLinksTest.php` (Tasks 4–9).
- **Type consistency:** `url(string, array, ?string)` signature used identically across all tasks; `slugify(string)` consistent; `build_query` defined once and used internally.
- **stock_ajust** was not in the original `$ROUTES` (Task 1) nor in the `.htaccess` block (Task 3); Task 9 Step 5 adds both the route entry and the rewrite rules, and the Task 9 commit stages `includes/url.php` + `.htaccess` to capture those edits.
- **No placeholders:** every code step contains concrete code or a concrete before/after table; manual verification steps list exact URLs to visit.