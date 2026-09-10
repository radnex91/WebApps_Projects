<?php
declare(strict_types=1);
/**
 * Ping de présence + activité — appelé en AJAX toutes les 45 s par les pages
 * authentifiées (script injecté par layout_foot()).
 *
 *   ?active=1 : l'utilisateur a réellement interagi avec la page (souris /
 *               clavier) récemment → la session est rafraîchie : il reste
 *               connecté tant qu'il travaille.
 *   ?active=0 : l'onglet est ouvert mais AUCUNE interaction détectée → la
 *               session n'est PAS rafraîchie : après le délai d'inactivité
 *               (paramètre admin, 10-15 min), le serveur déconnecte
 *               automatiquement l'utilisateur (réponse 401 → retour au login).
 *   ?bye=1    : sendBeacon à la fermeture de l'onglet → sortie immédiate de
 *               l'état « en ligne ».
 *
 * La présence (« en ligne », utilisateurs_enligne) reste mise à jour à chaque
 * ping tant que la session est valide (l'onglet est ouvert).
 * Réponse volontairement vide (204) — aucun rendu, aucune surcharge.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';

// Session : vérifie l'expiration SANS la rafraîchir si le ping est passif
// (pas d'interaction réelle), et sans redirection (AJAX → 401 si expirée).
$active = (($_GET['active'] ?? '0') === '1');
startSession($active, false);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
ensureActivityColumn();

// ?bye=1 (sendBeacon au closing de l'onglet) → sortie immédiate de l'état en ligne
if (isset($_GET['bye'])) {
    try {
        getDB()->prepare("UPDATE utilisateurs SET derniere_activite = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")
           ->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) { /* non bloquant */ }
    http_response_code(204);
    exit;
}

// Présence (onglet ouvert) — mise à jour tant que la session est valide.
// C'est startSession($active, false) ci-dessus qui gère le rafraîchissement
// de la session selon l'activité réelle de l'utilisateur.
touchUserActivity(true); // force la mise à jour (contourne le throttle 60 s)
http_response_code(204);
exit;