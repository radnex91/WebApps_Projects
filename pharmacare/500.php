<?php
/**
 * 500 — Erreur interne du serveur.
 * Page autonome brandée. À câbler côté Apache via :
 *   ErrorDocument 500 /500.php
 * (chemin docroot — donc orienté prod-racine ; en dev sous-dossier, ouvrir
 * directement http://localhost/pharmacare/500.php pour prévisualiser).
 */
require_once __DIR__ . '/includes/erreur.php';
afficher_erreur(
    500,
    'Erreur serveur',
    'Une erreur inattendue est survenue. Nos équipes ont été notifiées. Veuillez réessayer dans quelques instants.',
    'Erreur 500 · Problème technique',
    '500'
);