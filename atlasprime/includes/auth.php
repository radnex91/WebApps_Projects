<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class Auth {
    private static ?array $requestPermissions = null;

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function login(string $username, string $password): array {
        $user = Database::fetchOne(
            "SELECT * FROM utilisateurs WHERE (username = ? OR email = ?) AND actif = 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::log(null, 'LOGIN_ECHEC', null, null, "Tentative: $username");
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }

        // Démarrer session
        self::startSession();
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['nom']       = $user['prenom'] . ' ' . $user['nom'];
        $roleRow = Database::fetchOne("SELECT nom FROM roles WHERE id = ?", [$user['role_id']]);
        $_SESSION['role']      = $roleRow ? $roleRow['nom'] : 'operateur';
        $_SESSION['role_id']   = $user['role_id'];
        $_SESSION['agence_id'] = $user['agence_id'] ?? null;
        $_SESSION['login_time']= time();

        // Mettre à jour dernière connexion
        Database::execute(
            "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?",
            [$user['id']]
        );

        self::log($user['id'], 'LOGIN', null, null, 'Connexion réussie');
        return ['success' => true, 'redirect' => 'index.php'];
    }

    public static function logout(): void {
        self::startSession();
        if (isset($_SESSION['user_id'])) {
            self::log($_SESSION['user_id'], 'LOGOUT', null, null, 'Déconnexion');
        }
        session_unset();
        session_destroy();
header('Location: ' . APP_ROOT . 'login.php');
        exit;
    }

    public static function check(): void {
        self::startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . APP_ROOT . 'login.php');
            exit;
        }
        // Vérifier timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            self::logout();
        }
        $_SESSION['login_time'] = time(); // Renouveler
    }

    public static function isLoggedIn(): bool {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function currentUser(): ?array {
        if (!self::isLoggedIn()) return null;
        $role = $_SESSION['role'] ?? null;
        if (!$role && !empty($_SESSION['role_id'])) {
            $roleRow = Database::fetchOne("SELECT nom FROM roles WHERE id = ?", [$_SESSION['role_id']]);
            $role = $roleRow ? $roleRow['nom'] : 'operateur';
            $_SESSION['role'] = $role;
        }
        return [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nom'      => $_SESSION['nom'],
            'role'     => $role ?? 'operateur',
            'role_id'  => $_SESSION['role_id'] ?? null,
            'agence_id'=> $_SESSION['agence_id'] ?? null,
        ];
    }

    public static function hasRole(string ...$roles): bool {
        self::startSession();
        $user = self::currentUser();
        return $user && in_array($user['role'], $roles);
    }

    public static function hasPermission(string $permission): bool {
        self::startSession();
        if (!isset($_SESSION['user_id'])) return false;

        if (self::$requestPermissions === null) {
            self::loadPermissions();
        }

        return in_array($permission, self::$requestPermissions);
    }

    public static function loadPermissions(): void {
        self::startSession();
        $roleId = $_SESSION['role_id'] ?? null;
        if (!$roleId) {
            $user = self::currentUser();
            $roleId = $user['role_id'] ?? 3;
        }

        $rows = Database::fetchAll(
            "SELECT p.nom FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ?",
            [$roleId]
        );
        self::$requestPermissions = array_column($rows, 'nom');
        $_SESSION['permissions'] = self::$requestPermissions;
    }

    public static function requirePermission(string $permission): void {
        if (!self::hasPermission($permission)) {
            header('Location: ' . APP_ROOT . 'index.php?error=access_denied');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void {
        if (!self::hasRole(...$roles)) {
            header('Location: ' . APP_ROOT . 'index.php?error=access_denied');
            exit;
        }
    }

    private static function log(?int $userId, string $action, ?string $table, ?int $recId, ?string $details): void {
        try {
            Database::execute(
                "INSERT INTO audit_logs (utilisateur_id, action, table_cible, enregistrement_id, details, ip_address) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $action, $table, $recId, $details, $_SERVER['REMOTE_ADDR'] ?? '']
            );
        } catch (Exception $e) { /* silencieux */ }
    }

    public static function logAction(string $action, ?string $table = null, ?int $recId = null, ?string $details = null): void {
        $user = self::currentUser();
        self::log($user['id'] ?? null, $action, $table, $recId, $details);
    }

    public static function getAgenceFilter(): ?array {
        $user = self::currentUser();
        if (!$user || !$user['agence_id']) return null;
        return [
            'agence_id' => $user['agence_id'],
            'is_admin' => in_array($user['role'], ['admin', 'superviseur'])
        ];
    }
}
