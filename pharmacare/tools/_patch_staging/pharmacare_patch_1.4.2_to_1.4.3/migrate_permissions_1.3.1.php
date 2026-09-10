<?php
declare(strict_types=1);
/**
 * PharmaCare — Injection idempotente des permissions manquantes.
 *
 * Contexte : plusieurs permissions vérifiées dans le code (requirePermission /
 * hasPermission) n'ont jamais été insérées dans la table `permissions`. Elles
 * n'apparaissent donc pas dans l'éditeur de rôles (modules/roles.php) et sont
 * impossibles à accorder à un rôle autre qu'admin (bypass). Audit via :
 *   php tools/audit_permissions.php
 *
 * Couvert :
 *   - marketing.voir / marketing.promos / marketing.fidelite (manquantes sur
 *     TOUTES les installations — module Marketing inaccessible hors admin) ;
 *   - suivi_caissiers.voir / suivi_caissiers.recompenser / enligne.voir /
 *     rapports_caissier.voir / assistant.utiliser (présentes en dev 1.3.0 mais
 *     absentes du dump d'installation database.sql → manquantes sur les
 *     installations fraîches).
 *
 * Aucune attribution automatique : la permission devient VISIBLE dans
 * l'éditeur de rôles, l'admin coche ensuite qui en dispose (RBAC explicite).
 * Idempotent : INSERT IGNORE + clé UNIQUE sur `code`. Ré-exécuter est sûr.
 *
 * Utilisation (depuis apply_patch ou manuel) :
 *   php migrate_permissions_1.3.1.php [chemin_pharmacare]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script ne doit être exécuté qu'en ligne de commande (php CLI).\n");
    exit(1);
}

$live = $argv[1] ?? 'C:\\xampp\\htdocs\\pharmacare';
// Normalise les slashes Windows
$live = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($live, '/\\'));

$envFile = $live . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php';
if (!file_exists($envFile)) {
    fwrite(STDERR, "config/env.php introuvable sous : $live\n");
    exit(1);
}
require $envFile; // définit DB_* (env.prod.php chargé automatiquement si présent)

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// code => [libellé, module] — modules alignés sur le regroupement de l'éditeur
// de rôles (roles.php : $byModule[$p['module']]).
$perms = [
    'marketing.voir'              => ['Voir le module marketing (promos & fidélité)', 'marketing'],
    'marketing.promos'            => ['Créer et gérer les promotions', 'marketing'],
    'marketing.fidelite'          => ['Gérer le programme de fidélité', 'marketing'],
    'suivi_caissiers.voir'        => ['Voir le suivi des caissiers', 'suivi_caissiers'],
    'suivi_caissiers.recompenser' => ['Attribuer des récompenses aux caissiers', 'suivi_caissiers'],
    'enligne.voir'                => ['Voir les utilisateurs en ligne', 'en_ligne'],
    'rapports_caissier.voir'      => ['Voir les rapports caissier', 'rapports_caissier'],
    'assistant.utiliser'          => ['Utiliser l\'assistant intégré', 'assistant'],
];

// ── Guard : la table existe-t-elle (installations très anciennes) ? ─────────
$tableExists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'permissions'")->fetchColumn();
if ($tableExists === 0) {
    fwrite(STDERR, "Table `permissions` absente — installation non initialisée ?\n");
    exit(1);
}

echo "PharmaCare — seed des permissions manquantes (base : " . DB_NAME . ")\n";
$added = 0;
$stmt = $pdo->prepare("INSERT IGNORE INTO permissions (code, libelle, module) VALUES (?, ?, ?)");
foreach ($perms as $code => [$libelle, $module]) {
    $stmt->execute([$code, $libelle, $module]);
    if ($stmt->rowCount() > 0) {
        echo "  + $code  ($module)\n";
        $added++;
    } else {
        echo "  = $code  (déjà présente)\n";
    }
}
echo "\n$added permission(s) ajoutée(s) — " . (count($perms) - $added) . " déjà en place.\n";

// ── Attributions par défaut (décision métier 1.3.1) ─────────────────────
// Marketing est accordé aux rôles de direction dont le périmètre prévu
// l'incluait (audit : c'était leur seul trou). Les autres rôles restent
// à la discrétion de l'admin via l'éditeur de rôles.
// Idempotent : PK (role_id, permission_id) + INSERT IGNORE.
$grants = [
    ['marketing.voir',     ['directeur', 'informaticien']],
    ['marketing.promos',   ['directeur', 'informaticien']],
    ['marketing.fidelite', ['directeur', 'informaticien']],
];
$granted = 0;
$stGrant = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = ?
    WHERE r.code = ?");
foreach ($grants as [$code, $roleCodes]) {
    foreach ($roleCodes as $roleCode) {
        $stGrant->execute([$code, $roleCode]);
        if ($stGrant->rowCount() > 0) {
            echo "  ~ $code → rôle $roleCode\n";
            $granted++;
        }
    }
}
echo "$granted attribution(s) marketing effectuée(s)\n";

// ── Paramètre applicatif : délai d'inactivité 15 min ────────────
// Politique de sécurité : la session expire après 15 min sans AUCUNE
// interaction réelle (souris/clavier). Tant que l'utilisateur travaille,
// sa session est rafraîchie automatiquement → jamais déconnecté.
// 0 désactiverait la déconnexion auto — non souhaité.
$pdo->prepare("INSERT INTO parametres (cle, valeur, label, groupe) VALUES ('delai_inactivite_min', '15', 'Délai d''inactivité (min)', 'general')
              ON DUPLICATE KEY UPDATE valeur = '15'")->execute();
echo "  ~ paramètre delai_inactivite_min = 15 min (déconnexion après inactivité réelle)\n";

echo "Les utilisateurs concernés doivent se reconnecter (cache de session).\n";
echo "Contrôle : php tools/audit_permissions.php\n";

exit(0);