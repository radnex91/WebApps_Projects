<?php
namespace Core;

class Session
{
    private static ?Session $instance = null;
    private bool $started = false;

    private function __construct()
    {
        $this->start();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) return;
        $config = require ROOT_DIR . 'config/app.php';
        ini_set('session.cookie_lifetime', $config['session']['lifetime']);
        ini_set('session.cookie_httponly', $config['session']['http_only']);
        ini_set('session.cookie_secure', $config['session']['secure']);
        session_start();
        $this->started = true;
    }

    public function set(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public function has(string $key): bool { return isset($_SESSION[$key]); }
    public function remove(string $key): void { unset($_SESSION[$key]); }

    public function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public function getFlash(string $type, string $default = ''): string
    {
        $msg = $_SESSION['_flash'][$type] ?? $default;
        unset($_SESSION['_flash'][$type]);
        return $msg;
    }

    public function hasFlash(string $type): bool
    {
        return isset($_SESSION['_flash'][$type]);
    }

    public function getAllFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->started = false;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }
}