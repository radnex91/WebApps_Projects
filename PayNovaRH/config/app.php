<?php
// Configuration générale de l'application
define('APP_NAME', 'PayNovaRH');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/PayNovaRH/public');
define('APP_ROOT', dirname(__DIR__));

// Chemins
define('CONTROLLERS_PATH', APP_ROOT . '/controllers');
define('MODELS_PATH', APP_ROOT . '/models');
define('VIEWS_PATH', APP_ROOT . '/views');
define('UPLOADS_PATH', APP_ROOT . '/public/uploads');

// Fuseau horaire
date_default_timezone_set('Africa/Casablanca');

// Session
define('SESSION_LIFETIME', 3600); // 1 heure

// Pagination
define('ITEMS_PER_PAGE', 15);

// Upload
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 Mo
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);