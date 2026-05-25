<?php
class User extends Model {
    protected $table = 'users';
    protected $allowedFields = ['name', 'email', 'password'];

    public function createDefaultUser() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];

        if ($count == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute(['Administrateur', 'admin@rentflow.com', $password]);

            // Assign super_admin role
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'super_admin'");
            $stmt->execute();
            $roleId = $stmt->fetch()['id'] ?? null;

            if ($roleId) {
                $stmt = $db->prepare("INSERT INTO role_user (role_id, user_id) VALUES (?, ?)");
                $stmt->execute([$roleId, $userId]);
            }
        }
    }

    public function getWithRoles() {
        $sql = "SELECT u.*, GROUP_CONCAT(r.name) as roles
                FROM users u
                LEFT JOIN role_user ru ON u.id = ru.user_id
                LEFT JOIN roles r ON ru.role_id = r.id
                GROUP BY u.id
                ORDER BY u.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getRoles($userId) {
        $sql = "SELECT r.* FROM roles r
                INNER JOIN role_user ru ON r.id = ru.role_id
                WHERE ru.user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function hasRole($userId, $roleName) {
        $sql = "SELECT COUNT(*) as count FROM role_user ru
                INNER JOIN roles r ON ru.role_id = r.id
                WHERE ru.user_id = ? AND r.name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $roleName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function hasPermission($userId, $permissionName) {
        $sql = "SELECT COUNT(*) as count FROM role_user ru
                INNER JOIN permission_role pr ON ru.role_id = pr.role_id
                INNER JOIN permissions p ON pr.permission_id = p.id
                WHERE ru.user_id = ? AND p.name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $permissionName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function getAllRoles() {
        $sql = "SELECT * FROM roles ORDER BY id";
        return $this->db->query($sql)->fetchAll();
    }

    public function getAllPermissions() {
        $sql = "SELECT * FROM permissions ORDER BY module, name";
        return $this->db->query($sql)->fetchAll();
    }
}
