<?php
namespace App\Middleware;

use App\Core\Csrf;

class CsrfMiddleware
{
    /**
     * Validate CSRF token on POST requests
     */
    public static function handle(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }

        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return Csrf::validate($token);
    }
}