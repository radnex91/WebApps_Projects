<?php
namespace App\Middleware;

use App\Core\Auth;

class AuthMiddleware
{
    /**
     * Check if user is authenticated, redirect to login if not
     */
    public static function handle(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        return true;
    }
}