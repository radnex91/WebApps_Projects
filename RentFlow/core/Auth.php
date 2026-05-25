<?php
class Auth {
    private static $maxAttempts = 5;
    private static $lockoutTime = 900; // 15 minutes

    public static function attempt($email, $password) {
        if (self::isLockedOut($email)) {
            logWarning('Login attempt while locked out', ['email' => $email, 'ip' => self::getClientIp()]);
            return false;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            self::clearLoginAttempts($email);
            session_regenerate_id(true);
            logInfo('User logged in', ['email' => $email, 'user_id' => $user['id']]);

            $stmt = $db->prepare("SELECT r.name FROM roles r
                                  INNER JOIN role_user ru ON r.id = ru.role_id
                                  WHERE ru.user_id = ?");
            $stmt->execute([$user['id']]);
            $roles = array_column($stmt->fetchAll(), 'name');

            $stmt = $db->prepare("SELECT DISTINCT p.name FROM permissions p
                                  INNER JOIN permission_role pr ON p.id = pr.permission_id
                                  INNER JOIN role_user ru ON pr.role_id = ru.role_id
                                  WHERE ru.user_id = ?");
            $stmt->execute([$user['id']]);
            $permissions = array_column($stmt->fetchAll(), 'name');

            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'roles' => $roles,
                'permissions' => $permissions
            ];
            return true;
        }

        self::recordLoginAttempt($email);
        logWarning('Failed login attempt', ['email' => $email, 'ip' => self::getClientIp()]);
        return false;
    }

    public static function check() {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['name']);
    }

    public static function user() {
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        return ['name' => 'Invité', 'email' => '', 'roles' => [], 'permissions' => []];
    }

    public static function userId() {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function logout() {
        $userName = $_SESSION['user']['name'] ?? 'Unknown';
        logInfo('User logged out', ['user' => $userName]);
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    public static function requireAuth() {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    public static function hasRole($roleName) {
        if (!self::check()) return false;
        return in_array($roleName, $_SESSION['user']['roles'] ?? []);
    }

    public static function hasPermission($permissionName) {
        if (!self::check()) return false;

        if (in_array('super_admin', $_SESSION['user']['roles'] ?? [])) {
            return true;
        }

        return in_array($permissionName, $_SESSION['user']['permissions'] ?? []);
    }

    public static function isAdmin() {
        return self::hasRole('admin') || self::hasRole('super_admin');
    }

    public static function getClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function getLockoutMessage($email) {
        if (self::isLockedOut($email)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT attempted_at FROM login_attempts
                 WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                 ORDER BY attempted_at DESC LIMIT 1"
            );
            $stmt->execute([$email, self::getClientIp(), self::$lockoutTime]);
            $lastAttempt = $stmt->fetch();

            if ($lastAttempt) {
                $remaining = self::$lockoutTime - (time() - strtotime($lastAttempt['attempted_at']));
                return "Trop de tentatives. Réessayez dans " . ceil($remaining / 60) . " minute(s).";
            }
        }
        return null;
    }

    private static function ensureLoginAttemptsTable() {
        $db = Database::getInstance()->getConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_ip (email, ip_address),
            INDEX idx_attempted_at (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function recordLoginAttempt($email) {
        self::ensureLoginAttemptsTable();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
        $stmt->execute([$email, self::getClientIp()]);

        // Purge old entries older than 1 hour
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }

    private static function clearLoginAttempts($email) {
        self::ensureLoginAttemptsTable();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE email = ? AND ip_address = ?");
        $stmt->execute([$email, self::getClientIp()]);
    }

    private static function isLockedOut($email) {
        self::ensureLoginAttemptsTable();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$email, self::getClientIp(), self::$lockoutTime]);
        return (int)$stmt->fetchColumn() >= self::$maxAttempts;
    }
}
