<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Permission;

class PermissionMiddleware
{
    /**
     * Check if current user has permission for a module/action
     */
    public static function check(string $module, string $action = 'can_view'): bool
    {
        if (!Auth::check()) return false;
        return Permission::currentUserCan($module, $action);
    }
}