<?php
/**
 * Authentification et autorisation
 */
class Auth
{
    private static $user = null;
    private static $permissions = null;

    public static function check()
    {
        return Session::has('user_id');
    }

    public static function user()
    {
        if (self::$user !== null) return self::$user;
        if (!self::check()) return null;

        $userModel = new UserModel();
        self::$user = $userModel->find(Session::get('user_id'));

        if (self::$user) {
            $roleModel = new RoleModel();
            $role = $roleModel->find(self::$user['role_id']);
            self::$user['role_name'] = $role ? $role['name'] : '';

            $employeeModel = new EmployeeModel();
            self::$user['employee'] = $employeeModel->findOneBy(['user_id' => self::$user['id']]);
        }

        return self::$user;
    }

    public static function id()
    {
        return Session::get('user_id');
    }

    public static function attempt($username, $password)
    {
        $userModel = new UserModel();
        $user = $userModel->findOneBy(['username' => $username]);

        if (!$user) {
            $user = $userModel->findOneBy(['email' => $username]);
        }

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        if (!$user['is_active']) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('role_id', $user['role_id']);

        $userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        return true;
    }

    public static function logout()
    {
        Session::destroy();
        self::$user = null;
        self::$permissions = null;
    }

    public static function hasPermission($module, $action)
    {
        if (!self::check()) return false;

        if (self::$permissions === null) {
            $permModel = new PermissionModel();
            self::$permissions = $permModel->getByRoleId(Session::get('role_id'));
        }

        foreach (self::$permissions as $perm) {
            if ($perm['module'] === $module && $perm['action'] === $action) {
                return true;
            }
        }

        return false;
    }

    public static function hasAnyPermission($module)
    {
        if (!self::check()) return false;

        if (self::$permissions === null) {
            $permModel = new PermissionModel();
            self::$permissions = $permModel->getByRoleId(Session::get('role_id'));
        }

        foreach (self::$permissions as $perm) {
            if ($perm['module'] === $module) {
                return true;
            }
        }

        return false;
    }

    public static function isAdmin()
    {
        $user = self::user();
        return $user && $user['role_name'] === 'admin';
    }

    public static function isManager()
    {
        $user = self::user();
        return $user && ($user['role_name'] === 'admin' || $user['role_name'] === 'manager');
    }

    public static function getPermissions()
    {
        if (self::$permissions === null) {
            $permModel = new PermissionModel();
            self::$permissions = $permModel->getByRoleId(Session::get('role_id'));
        }
        return self::$permissions;
    }
}