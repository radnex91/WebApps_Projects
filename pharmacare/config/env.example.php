<?php
/**
 * PharmaCare — Configuration de production
 * 
 * 1. Copiez ce fichier en env.prod.php :  cp config/env.example.php config/env.prod.php
 * 2. Remplissez les valeurs réelles (base de données, URL)
 * 3. Vérifiez que PHP lit la variable PHARMACARE_ENV=prod OU que le fichier env.prod.php existe
 *
 * ⚠️  Ne commitez PAS env.prod.php dans le dépôt Git !
 *     Ajoutez-le à .gitignore.
 */

return [
    // Base de données MySQL/MariaDB
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'pharmacare',
    'DB_USER' => 'pharmacare_user',
    'DB_PASS' => 'CHANGEZ_MOI_PAR_UN_MOT_DE_PASSE_FORT',

    // URL publique de l'application (sans slash final)
    'APP_URL' => 'https://votre-domaine.com/pharmacare',
];
