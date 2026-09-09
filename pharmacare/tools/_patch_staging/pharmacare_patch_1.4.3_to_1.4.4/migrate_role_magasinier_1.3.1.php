<?php
declare(strict_types=1);
/**
 * PharmaCare — Création idempotente du rôle « Magasinier ».
 *
 * Rôle réceptionnaire : gère le stock magasin (réceptions, transferts vers les
 * pharmacies, ajustements), suit les commandes fournisseurs et enregistre les
 * livraisons (le bon de livraison commandes.php prévoit déjà la signature
 * « Le Magasinier (réceptionnaire) »). Ne crée PAS de commandes (décision
 * pharmacien/manager) et n'ajuste PAS le stock des pharmacies (stock.ajuster).
 *
 * Périmètre accordé :
 *   dashboard.voir, magasin.voir, magasin.gerer, stock.voir, produits.voir,
 *   commandes.voir, commandes.modifier, assistant.utiliser
 *
 * Prérequis : les permissions doivent exister (patch migrate_permissions_1.3.1.php).
 * Idempotent : UNIQUE sur roles.code + PK (role_id, permission_id). Ré-exécuter
 * est sûr — le rôle et les attributions ne sont jamais dupliqués, jamais retirés.
 *
 * Utilisation (depuis apply_patch ou manuel) :
 *   php migrate_role_magasinier_1.3.1.php [chemin_pharmacare]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script ne doit être exécuté qu'en ligne de commande (php CLI).\n");
    exit(1);
}

$live = $argv[1] ?? 'C:\\xampp\\htdocs\\pharmacare';
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

echo "PharmaCare — création du rôle « Magasinier » (base : " . DB_NAME . ")\n";

// ── 1) Le rôle (INSERT IGNORE : clé UNIQUE sur code) ────────────────────────
$pdo->prepare("INSERT IGNORE INTO roles (code, libelle, est_systeme) VALUES ('magasinier', 'Magasinier', 0)")
    ->execute();
$roleId = (int)$pdo->query("SELECT id FROM roles WHERE code = 'magasinier'")->fetchColumn();
if ($roleId === 0) {
    fwrite(STDERR, "  ! Impossible de créer/récupérer le rôle magasinier.\n");
    exit(1);
}
echo "  + rôle magasinier (id $roleId)" . ($pdo->query("SELECT est_systeme FROM roles WHERE id=$roleId")->fetchColumn() ? '' : ", personnalisé (modifiable via l'éditeur de rôles)") . "\n";

// ── 2) Périmètre (idempotent : PK composite + INSERT IGNORE) ────────────────
$permCodes = [
    'dashboard.voir',   // atterrissage post-connexion
    'magasin.voir',     // consultation du stock magasin
    'magasin.gerer',    // réceptions magasin, transferts vers pharmacies, ajustements
    'stock.voir',       // vue du stock des pharmacies (où envoyer)
    'produits.voir',    // catalogue produits
    'commandes.voir',   // suivi des commandes fournisseurs
    'commandes.modifier', // enregistrement des livraisons (action 'livrer')
    'assistant.utiliser', // aide universelle (même principe que l'auto-seed assistant)
];
$stGrant = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT ?, id FROM permissions WHERE code = ?");
$stHave = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
    WHERE rp.role_id = ? AND p.code = ?");
$granted = 0;
foreach ($permCodes as $code) {
    $stHave->execute([$roleId, $code]);
    $have = (int)$stHave->fetchColumn();
    if ($have === 0) {
        $stGrant->execute([$roleId, $code]);
        $stHave->execute([$roleId, $code]);
        $have = (int)$stHave->fetchColumn();
    }
    if ($have > 0) {
        echo ($have === 1 ? "  + $code\n" : "  = $code (déjà accordée)\n");
        if ($have === 1) $granted++;
    } else {
        echo "  ! $code ABSENTE de la table permissions — lancez d'abord migrate_permissions_1.3.1.php\n";
    }
}

echo "\n$granted permission(s) accordée(s) au rôle magasinier.\n";
echo "Les utilisateurs à créer/associer : Utilisateurs → rôle « Magasinier ».\n";
echo "Contrôle : php tools/audit_permissions.php\n";

exit(0);