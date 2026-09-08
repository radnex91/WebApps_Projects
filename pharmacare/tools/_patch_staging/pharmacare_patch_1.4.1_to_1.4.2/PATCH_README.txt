PharmaCare — patch de mise a jour 1.4.1 -> 1.4.2
==================================================

Ce patch contient UNIQUEMENT les fichiers modifies. Il ne touche pas a la
base de donnees ni a votre configuration de production (env.prod.php).

Fichiers inclus (2) :
  - pharmacare/includes/layout.php
  - pharmacare/config/env.php
  - pharmacare/config/licence_integrity.php  (manifeste d'integrite regenere)
  + 5 script(s) de migration BDD (migrate_*.php, idempotents)

APPLICATION (Windows, XAMPP) :
  1. Decompressez l'archive .zip ou vous voulez (ex: Bureau).
  2. Double-cliquez sur apply_patch.bat.
  3. Confirmez le dossier d'installation (defaut C:\xampp\htdocs\pharmacare).
  4. Les anciens fichiers sont sauvegardes dans pharmacare\_patch_backup\<horodatage>.
  5. Les migrations BDD (index) sont executees automatiquement si php CLI est trouve.

APPLICATION (Linux, LAMPP) :
  1. tar -xzf pharmacare_patch_1.4.1_to_1.4.2.tar.gz
  2. cd pharmacare_patch_1.4.1_to_1.4.2
  3. ./apply_patch.sh [/opt/lampp/htdocs/pharmacare]   (sudo si necessaire)
  4. Les migrations BDD (index) sont executees automatiquement si php CLI est trouve.

Apres application : videz le cache du navigateur (le CSS/JS est cache-buste
via APP_VERSION, desormais 1.4.2).

ROLLBACK : restaurer les fichiers depuis pharmacare/_patch_backup/<horodatage>.
