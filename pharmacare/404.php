<?php
/**
 * 404 — Page introuvable.
 * Servie par le catch-all du .htaccess (RewriteRule ^ 404.php) pour toute
 * URL inconnue. Aucune dépendance BDD : s'affiche même si MySQL est tombé.
 */
require_once __DIR__ . '/includes/erreur.php';
afficher_erreur(
    404,
    'Page introuvable',
    'La page que vous cherchez n\'existe pas, a été déplacée, ou l\'adresse est incorrecte. Vérifiez le lien ou revenez à l\'application.',
    'Erreur 404 · Ressource introuvable',
    '404'
);