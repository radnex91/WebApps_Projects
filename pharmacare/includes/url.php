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
    'stock_ajust'         => ['base' => 'stock-ajust'],
    'produits'            => ['base' => 'produits', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit',
        'import' => 'importer', 'import_template' => 'importer/modele',
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
        'new' => 'new', 'detail' => 'detail',
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
    'suivi_caissiers'     => ['base' => 'suivi-caissiers'],
    'mdp_oublie'          => ['base' => 'mot-de-passe-oublie'],
    'utilisateurs_enligne' => ['base' => 'utilisateurs-en-ligne'],
    'ping'                 => ['base' => 'ping'],
    'mon_compte'           => ['base' => 'mon-compte'],
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
    'pharmacies'          => ['base' => 'pharmacies', 'idSlug' => true, 'actions' => [
        'add' => 'new', 'edit' => 'edit', 'stock' => 'stock',
    ]],
    'menus'               => ['base' => 'menus'],
    'sauvegarde'          => ['base' => 'sauvegarde'],
    'assistant'           => ['base' => 'assistant'],
];

function slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    // Suppression des accents.
    if (function_exists('transliterator_transliterate')) {
        $translit = @transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        if ($translit !== false && $translit !== '') {
            $text = $translit;
        }
    }
    // Fallback deterministic accent map (intl absent — ex. XAMPP sans ext-intl).
    // Couvre les accents Latin-1 courants (FR/ES/PT/DE…). Suffixe majuscules.
    $text = strtr($text, [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
        'ç'=>'c',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ñ'=>'n',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
        'ý'=>'y','ÿ'=>'y',
        'œ'=>'oe','Œ'=>'OE',
        'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
        'Ç'=>'C',
        'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
        'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
        'Ñ'=>'N',
        'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
        'Ý'=>'Y',
    ]);
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
