<?php
namespace App\Helpers;
use App\Models\User;
class Auth
{
    public static function login(string $email, string $password): bool
    {
        $user = User::whereFirst('email', $email);
        if ($user && password_verify($password, $user->mot_de_passe)) {
            self::clearAttempts($email);
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_name'] = $user->nom;
            return true;
        }
        self::recordAttempt($email);
        return false;
    }

    private static function recordAttempt(string $email): void
    {
        if (!isset($_SESSION['_login_attempts'])) {
            $_SESSION['_login_attempts'] = [];
        }
        $key = $email . '|' . $_SERVER['REMOTE_ADDR'];
        $_SESSION['_login_attempts'][$key][] = time();
    }

    private static function clearAttempts(string $email): void
    {
        $key = $email . '|' . $_SERVER['REMOTE_ADDR'];
        unset($_SESSION['_login_attempts'][$key]);
    }

    public static function isThrottled(string $email): array
    {
        $key = $email . '|' . $_SERVER['REMOTE_ADDR'];
        $attempts = $_SESSION['_login_attempts'][$key] ?? [];
        $window = 900;
        $limit = 5;
        $attempts = array_values(array_filter($attempts, fn($t) => $t > time() - $window));
        $_SESSION['_login_attempts'][$key] = $attempts;
        if (count($attempts) >= $limit) {
            $oldest = min($attempts);
            $retryAfter = $window - (time() - $oldest);
            return ['throttled' => true, 'retryAfter' => $retryAfter];
        }
        return ['throttled' => false, 'remaining' => $limit - count($attempts)];
    }
    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
        session_destroy();
    }
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }
    public static function user()
    {
        if (self::check()) {
            return User::find($_SESSION['user_id']);
        }
        return null;
    }
    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }
    public static function name(): ?string
    {
        return $_SESSION['user_name'] ?? null;
    }
    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /gestion-support/login');
            exit;
        }
    }
    public static function requireRole(string $role): void
    {
        self::requireAuth();
        if (self::role() !== $role) {
            header('Location: /gestion-support/' . self::role() . '/dashboard');
            exit;
        }
    }
}
