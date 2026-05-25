<?php
namespace App\Core;

class Csrf
{
    /**
     * Generate a CSRF token and store in session
     */
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf'] = $token;
        return $token;
    }

    /**
     * Get the current CSRF token (generate if missing)
     */
    public static function getToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            return self::generate();
        }
        return $_SESSION['_csrf'];
    }

    /**
     * Validate a CSRF token against the session
     */
    public static function validate(string $token): bool
    {
        $sessionToken = $_SESSION['_csrf'] ?? '';
        if (empty($sessionToken) || empty($token)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate token (call after login)
     */
    public static function regenerate(): string
    {
        return self::generate();
    }

    /**
     * Output a hidden CSRF input field
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::getToken()) . '">';
    }
}