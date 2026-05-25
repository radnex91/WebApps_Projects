<?php
$uri = $_SERVER['REQUEST_URI'];
$uriPath = parse_url($uri, PHP_URL_PATH);
$stripped = false;

foreach (['/gestion-support/app/public', '/gestion-support'] as $prefix) {
    if (strpos($uriPath, $prefix . '/') === 0 || $uriPath === $prefix) {
        $newPath = substr($uriPath, strlen($prefix)) ?: '/';
        $uri = str_replace($uriPath, $newPath, $uri);
        $uriPath = $newPath;
        $stripped = true;
        break;
    }
}
$_SERVER['REQUEST_URI'] = $uri;

if (php_sapi_name() === 'cli-server') {
    $filePath = __DIR__ . '/app/public' . $uriPath;
    if (is_file($filePath)) {
        return false;
    }
}
require __DIR__ . '/app/public/index.php';
