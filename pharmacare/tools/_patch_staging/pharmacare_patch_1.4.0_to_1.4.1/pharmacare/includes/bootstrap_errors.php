<?php
declare(strict_types=1);
/**
 * Gestion des erreurs fatales / exceptions non attrapées au niveau du bootstrap.
 *
 * Inclu depuis la fin de config/env.php (après définition de IS_PROD), il s'exécute
 * avant tout point d'entrée applicatif. Objectifs :
 *  - ob_start() dès le démarrage pour pouvoir vider toute sortie partielle et
 *    afficher une page d'erreur brandée même si du HTML a déjà été émis.
 *  - set_exception_handler() : toute Throwable non attrapée → page 500 brandée
 *    (afficher_erreur) + error_log() en prod. Message détaillé uniquement en dev.
 *  - register_shutdown_function() : capture des E_ERROR/E_PARSE/E_CORE_* via
 *    error_get_last() pour rendre la même page 500 si rien n'a encore été émis.
 *
 * Désactivé en CLI (scripts de migration, outils) pour ne pas masquer les
 * stack traces utiles en ligne de commande.
 */

if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
    return;
}

/**
 * Rend la page d'erreur brandée 500 en vidant les buffers partiels.
 */
function _pharma_render_fatal(string $detail): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8');
    }
    require_once __DIR__ . '/erreur.php';
    if (defined('IS_PROD') && IS_PROD) {
        afficher_erreur(
            500,
            'Erreur serveur',
            'Une erreur inattendue est survenue. L\'équipe technique a été notifiée ; veuillez réessayer dans quelques instants.',
            'Erreur 500 · Service',
            '500'
        );
    } else {
        afficher_erreur(
            500,
            'Erreur serveur',
            $detail,
            'Erreur 500 · Développement',
            '500'
        );
    }
}

set_exception_handler(function (Throwable $ex): void {
    if (defined('IS_PROD') && IS_PROD) {
        error_log('PharmaCare uncaught exception: ' . $ex->getMessage()
            . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    }
    $detail = (defined('IS_PROD') && IS_PROD)
        ? 'Erreur interne.'
        : ($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine());
    _pharma_render_fatal($detail);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;

    if (defined('IS_PROD') && IS_PROD) {
        error_log('PharmaCare fatal error: ' . $err['message']
            . ' @ ' . $err['file'] . ':' . $err['line']);
    }
    // Si des en-têtes ont déjà été envoyés (HTML partiel rendu), on ne peut plus
    // afficher proprement la page brandée — on se contente de journaliser.
    if (!headers_sent()) {
        $detail = (defined('IS_PROD') && IS_PROD)
            ? 'Erreur fatale.'
            : $err['message'] . "\n" . $err['file'] . ':' . $err['line'];
        _pharma_render_fatal($detail);
    }
});

// Buffer de sortie global — permet de tout vider avant de rendre une page d'erreur.
if (ob_get_level() === 0) {
    ob_start();
}