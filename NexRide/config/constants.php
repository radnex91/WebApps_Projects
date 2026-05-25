<?php
define('BASE_URL', 'http://localhost/NexRide');
define('ROOT_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APP_NAME', 'NexRide');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Africa/Abidjan');
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');

define('BILLET_STATUS', serialize([
    'EN_ATTENTE' => 'En attente',
    'CONFIRME'   => 'Confirmé',
    'ANNULE'     => 'Annulé',
    'REMBOURSE'  => 'Remboursé'
]));

define('TYPE_VOYAGE', serialize([
    'URBAIN'    => 'Urbain',
    'INTERURBAIN' => 'Interurbain',
    'NAVETTE'   => 'Navette',
    'EXCURSION' => 'Excursion'
]));

define('DEVISE', 'FCFA');
define('TVA', 0.18);