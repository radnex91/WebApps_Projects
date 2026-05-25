<?php
namespace App\Core;

final class Permission
{
    private static ?array $matrix = null;

    /**
     * Load permission matrix from database rows
     */
    public static function loadMatrix(array $rows): void
    {
        self::$matrix = [];
        foreach ($rows as $row) {
            self::$matrix[(int)$row['role_id']][$row['module_key']] = [
                'can_view'   => (bool)$row['can_view'],
                'can_create' => (bool)$row['can_create'],
                'can_edit'   => (bool)$row['can_edit'],
                'can_delete' => (bool)$row['can_delete'],
                'can_print'  => (bool)$row['can_print'],
                'can_manage' => (bool)$row['can_manage'],
            ];
        }
    }

    /**
     * Check if a role has permission for an action on a module
     */
    public static function can(int $roleId, ?string $roleName, string $module, string $action = 'can_view'): bool
    {
        // Admin bypasses all checks
        if ($roleName === 'admin') {
            return true;
        }
        return self::$matrix[$roleId][$module][$action] ?? false;
    }

    /**
     * Check if a role does NOT have permission
     */
    public static function cannot(int $roleId, ?string $roleName, string $module, string $action = 'can_view'): bool
    {
        return !self::can($roleId, $roleName, $module, $action);
    }

    /**
     * Check if role can manage sensitive settings (admin only)
     */
    public static function canManageSensitiveSettings(string $roleName): bool
    {
        return $roleName === 'admin';
    }

    /**
     * Check if current session user has permission
     */
    public static function currentUserCan(string $module, string $action = 'can_view'): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        return self::can($user['role_id'], $user['role_name'], $module, $action);
    }

    /**
     * Check if current session user does NOT have permission
     */
    public static function currentUserCannot(string $module, string $action = 'can_view'): bool
    {
        return !self::currentUserCan($module, $action);
    }

    /**
     * Get all modules a role has view access to
     */
    public static function getAccessibleModules(int $roleId, ?string $roleName): array
    {
        if ($roleName === 'admin') {
            return [
                'dashboard', 'pos', 'products', 'categories', 'stock',
                'sales', 'customers', 'suppliers', 'users', 'roles',
                'settings', 'expiry', 'scale', 'reports', 'breakdown',
            ];
        }
        $modules = [];
        if (isset(self::$matrix[$roleId])) {
            foreach (self::$matrix[$roleId] as $module => $perms) {
                if ($perms['can_view'] ?? false) {
                    $modules[] = $module;
                }
            }
        }
        return $modules;
    }
}