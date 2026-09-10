PharmaCare — patch de mise a jour 1.0.9 -> 1.1.0
==================================================

Ce patch contient UNIQUEMENT les fichiers modifies. Il ne touche pas a la
base de donnees ni a votre configuration de production (env.prod.php).

Fichiers inclus (7) :
  - pharmacare/config/env.php
  - pharmacare/config/settings.php
  - pharmacare/includes/layout.php
  - pharmacare/index.php
  - pharmacare/modules/parametres.php
  - pharmacare/modules/vente.php
  - pharmacare/modules/commandes.php

APPLICATION (Windows, XAMPP) :
  1. Decompressez l'archive .zip ou vous voulez (ex: Bureau).
  2. Double-cliquez sur apply_patch.bat.
  3. Confirmez le dossier d'installation (defaut C:\xampp\htdocs\pharmacare).
  4. Les anciens fichiers sont sauvegardes dans pharmacare\_patch_backup\<horodatage>.

APPLICATION (Linux, LAMPP) :
  1. tar -xzf pharmacare_patch_1.0.9_to_1.1.0.tar.gz
  2. cd pharmacare_patch_1.0.9_to_1.1.0
  3. ./apply_patch.sh [/opt/lampp/htdocs/pharmacare]   (sudo si necessaire)

Apres application : videz le cache du navigateur (le CSS/JS est cache-buste
via APP_VERSION, desormais 1.1.0).

ROLLBACK : restaurer les fichiers depuis pharmacare/_patch_backup/<horodatage>.
