<?php
namespace App\Middleware;

use App\Core\Auth;

class ApiAuthMiddleware
{
    /**
     * Validate API request - check session or API key
     */
    public static function handle(string $apiKey = ''): bool
    {
        // Check session-based auth first (AJAX from POS)
        if (Auth::check()) {
            return true;
        }

        // Check API key (for scale bridge, etc.)
        $requestKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        if ($apiKey && $requestKey && hash_equals($apiKey, $requestKey)) {
            return true;
        }

        return false;
    }
}