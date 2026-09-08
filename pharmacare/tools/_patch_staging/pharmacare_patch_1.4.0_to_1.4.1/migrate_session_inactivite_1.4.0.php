<?php
declare(strict_types=1);
/**
 * PharmaCare 1.4.0 — Déconnexion après inactivité RÉELLE (10-15 min).
 *
 * Contexte : le paramètre `delai_inactivite_min` valait 3 min (politique 1.3.1).
 * La déconnexion automatique repose désormais sur l'inactivité RÉELLE de
 * l'utilisateur (détection souris/clavier côté navigateur, partagée entre
 * onglets) :
 *   - tant que l'utilisateur travaille, sa session est rafraîchie
 *     automatiquement → il n'est JAMAIS déconnecté pendant le travail ;
 *   - sans AUCUNE interaction pendant ce délai : avertissement 60 s avant
 *     (bouton « Rester connecté »), puis déconnexion automatique au login
 *     avec le message « Votre session a expiré ».
 *
 * On aligne toutes les installations sur 15 minutes (recommandation 10-15 min).
 * L'admin peut ensuite ajuster la valeur dans :
 *   Paramètres → Informations générales → « Déconnexion auto après inactivité ».
 *
 * Complément offline-first (même version) : la lecture des paramètres survit
 * à une panne MySQL (cache disque du dernier état connu) — la session et le
 * ping de présence ne sont plus tués par la page 503 pendant une panne.
 *
 * Idempotent : INSERT ... ON DUPLICATE KEY UPDATE. Ré-exécuter est sûr.
 *
 * Utilisation (depuis apply_patch / update_prod ou manuel) :
 *   php migrate_session_inactivite_1.4.0.php [chemin_pharmacare]
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

// ── Délai d'inactivité réelle : 15 minutes ─────────────────────
// Aligné sur le défaut du code (includes/auth.php → sessionTimeoutSeconds)
// et sur le réglage admin (modules/parametres.php, champ delai_inactivite_min).
// 0 désactiverait la déconnexion auto — non souhaité.
$pdo->prepare("INSERT INTO parametres (cle, valeur, label, groupe)
               VALUES ('delai_inactivite_min', '15', 'Délai d''inactivité (min)', 'general')
               ON DUPLICATE KEY UPDATE valeur = '15', label = VALUES(label)")->execute();
echo "  ~ paramètre delai_inactivite_min = 15 min (déconnexion après inactivité réelle)\n";
echo "  ~ l'admin peut l'ajuster : Paramètres → Informations générales\n";

// ── Contrôle ──────────────────────────────────────────────────
$v = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'delai_inactivite_min'")->fetchColumn();
echo "Contrôle : delai_inactivite_min = " . var_export($v, true) . " (attendu '15')\n";
if ((string)$v !== '15') {
    fwrite(STDERR, "ECHEC : la valeur n'a pas été appliquée.\n");
    exit(1);
}

echo "OK. Les utilisateurs déjà connectés garderont l'ancien délai jusqu'à leur\n";
echo "prochaine requête (le timeout est lu à chaque requête → bascule immédiate).\n";

exit(0);