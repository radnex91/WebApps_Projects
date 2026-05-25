<?php
/**
 * Gestion des sessions sécurisées
 */
class Session
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }

    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy()
    {
        self::start();
        session_destroy();
        $_SESSION = [];
    }

    public static function regenerate()
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function setFlash($type, $message)
    {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }

    public static function getFlash($type = null)
    {
        self::start();
        if ($type) {
            $message = $_SESSION['flash'][$type] ?? null;
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    public static function hasFlash($type = null)
    {
        self::start();
        if ($type) {
            return isset($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }

    /**
     * CSRF Token generation and validation
     */
    public static function csrfToken()
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="_token" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf()
    {
        $token = $_POST['_token'] ?? '';
        self::start();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}