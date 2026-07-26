PharmaCare — patch de mise a jour 1.1.2 -> 1.1.3
==================================================

Ce patch contient UNIQUEMENT les fichiers modifies. Il ne touche pas a la
base de donnees ni a votre configuration de production (env.prod.php).

Fichiers inclus (2) :
  - pharmacare/config/env.php
  - pharmacare/modules/ventes_hist.php

APPLICATION (Windows, XAMPP) :
  1. Decompressez l'archive .zip ou vous voulez (ex: Bureau).
  2. Double-cliquez sur apply_patch.bat.
  3. Confirmez le dossier d'installation (defaut C:\xampp\htdocs\pharmacare).
  4. Les anciens fichiers sont sauvegardes dans pharmacare\_patch_backup\<horodatage>.

APPLICATION (Linux, LAMPP) :
  1. tar -xzf pharmacare_patch_1.1.2_to_1.1.3.tar.gz
  2. cd pharmacare_patch_1.1.2_to_1.1.3
  3. ./apply_patch.sh [/opt/lampp/htdocs/pharmacare]   (sudo si necessaire)

Apres application : videz le cache du navigateur (le CSS/JS est cache-buste
via APP_VERSION, desormais 1.1.3).

ROLLBACK : restaurer les fichiers depuis pharmacare/_patch_backup/<horodatage>.
