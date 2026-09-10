<?php
/**
 * 403 — Accès refusé.
 * Servie quand un dossier protégé (config/, tools/, backups/, _archive/, sql/,
 * .git) est requis directement : le .htaccess réécrit ces chemins vers 403.php
 * (cible relative → fonctionne en sous-dossier dev ET en racine prod).
 */
require_once __DIR__ . '/includes/erreur.php';
afficher_erreur(
    403,
    'Accès refusé',
    'Vous n\'avez pas l\'autorisation d\'accéder à cette ressource. Si vous pensez qu\'il s\'agit d\'une erreur, reconnectez-vous et réessayez.',
    'Erreur 403 · Action interdite',
    '403'
);