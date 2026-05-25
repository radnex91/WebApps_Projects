<?php
class PermissionMiddleware {
    public static function check($permission) {
        return Auth::hasPermission($permission);
    }

    public static function role($role) {
        return Auth::hasRole($role);
    }

    public static function requirePermission($permission) {
        if (!self::check($permission)) {
            http_response_code(403);
            if (file_exists(__DIR__ . '/../app/views/errors/403.php')) {
                require __DIR__ . '/../app/views/errors/403.php';
            } else {
                echo "<h1>403 - Accès refusé</h1>";
                echo "<p>Vous n'avez pas la permission d'accéder à cette page.</p>";
            }
            exit;
        }
    }

    public static function requireRole($role) {
        if (!self::role($role)) {
            http_response_code(403);
            if (file_exists(__DIR__ . '/../app/views/errors/403.php')) {
                require __DIR__ . '/../app/views/errors/403.php';
            } else {
                echo "<h1>403 - Accès refusé</h1>";
                echo "<p>Vous n'avez pas le rôle requis pour accéder à cette page.</p>";
            }
            exit;
        }
    }
}
