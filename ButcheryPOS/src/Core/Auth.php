<?php
namespace App\Core;

class Auth
{
    /**
     * Attempt to log in a user
     * @return bool True on success
     */
    public static function attempt(\PDO $pdo, string $username, string $password): bool
    {
        $stmt = $pdo->prepare(
            "SELECT u.*, r.role_name, r.display_name AS role_display
             FROM users u
             INNER JOIN app_roles r ON r.id = u.role_id
             WHERE u.username = :username AND u.is_active = 1"
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Set session data
        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'role_display' => $user['role_display'],
            'preferred_language' => $user['preferred_language'],
        ];

        $_SESSION['lang'] = $user['preferred_language'];

        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);

        // Regenerate CSRF token on login
        Csrf::regenerate();

        // Clear failed attempts
        unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);

        return true;
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Get current user data from session
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get a specific user field
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION['user'][$key] ?? $default;
    }

    /**
     * Log out the current user
     */
    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['pos_session_id'], $_SESSION['pos_session_token']);
        session_regenerate_id(true);
    }

    /**
     * Check and handle login rate limiting
     * @return bool True if locked out
     */
    public static function isLockedOut(): bool
    {
        if (isset($_SESSION['login_lockout_until'])) {
            if (time() < $_SESSION['login_lockout_until']) {
                return true;
            }
            unset($_SESSION['login_lockout_until'], $_SESSION['login_attempts']);
        }
        return false;
    }

    /**
     * Record a failed login attempt
     */
    public static function recordFailedAttempt(int $maxAttempts = 5, int $lockoutMinutes = 15): void
    {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['login_lockout_until'] = time() + ($lockoutMinutes * 60);
        }
    }

    /**
     * Get remaining lockout time in minutes
     */
    public static function getLockoutRemaining(): int
    {
        if (!isset($_SESSION['login_lockout_until'])) return 0;
        return max(0, (int)ceil(($_SESSION['login_lockout_until'] - time()) / 60));
    }
}