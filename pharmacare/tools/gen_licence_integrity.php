<?php
declare(strict_types=1);
/**
 * Génère le manifeste d'intégrité signé — USAGE DÉVELOPPEUR, avant chaque déploiement.
 *
 * Calcule l'empreinte SHA-256 des fichiers critiques du système de licence,
 * signe la liste avec la clé privée RSA, et écrit config/licence_integrity.php
 * (livré au client — public, mais non forgeable sans la clé privée).
 *
 * L'app (config/licence.php :: licence_integrity_check) vérifie ce manifeste :
 *   - signature RSA (clé publique embarquée),
 *   - empreinte de chaque fichier.
 * Une édition d'un fichier couvert → détection + avertissement + audit_log.
 *
 * Usage :  php tools/gen_licence_integrity.php
 *
 * ⚠ Régénérez ce manifeste après TOUTE modification d'un fichier couvert
 *   (y compris un changement de LICENCE_FREE_CAP), sinon l'app signalera une
 *   intégrité compromise au prochain déploiement.
 *   (Note : la GUI régénère automatiquement le manifeste quand tu changes le
 *   palier gratuit via le bouton « Enregistrer ».)
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }

require_once __DIR__ . '/licence_lib.php';

try {
    $r = licence_lib_generate_integrity();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Manifeste d'intégrité généré : config/licence_integrity.php\n\n";
echo $r['count'] . " fichiers couverts :\n";
foreach ($r['files'] as $rel => $h) {
    echo "  $rel  " . substr($h, 0, 16) . "...\n";
}
echo "\nL'app vérifiera ces empreintes (signature RSA via la clé publique) à l'affichage\n";
echo "de la page Licence. Toute modification d'un fichier couvert sera détectée + journalisée.\n";