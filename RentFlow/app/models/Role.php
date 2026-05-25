<?php
class Role extends Model {
    protected $table = 'roles';
    protected $allowedFields = ['name', 'label'];

    public function getAllWithUsersCount() {
        $sql = "SELECT r.*, COUNT(ru.user_id) as users_count
                FROM roles r
                LEFT JOIN role_user ru ON r.id = ru.role_id
                GROUP BY r.id
                ORDER BY r.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function getUsers($roleId) {
        $sql = "SELECT u.* FROM users u
                INNER JOIN role_user ru ON u.id = ru.user_id
                WHERE ru.role_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }

    public function assignToUser($roleId, $userId) {
        // Remove existing roles
        $stmt = $this->db->prepare("DELETE FROM role_user WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Assign new role
        $sql = "INSERT INTO role_user (role_id, user_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$roleId, $userId]);
    }

    public function getUserRoles($userId) {
        $sql = "SELECT r.* FROM roles r
                INNER JOIN role_user ru ON r.id = ru.role_id
                WHERE ru.user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function hasPermission($roleId, $permissionName) {
        $sql = "SELECT COUNT(*) as count FROM permission_role pr
                INNER JOIN permissions p ON pr.permission_id = p.id
                WHERE pr.role_id = ? AND p.name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId, $permissionName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    public function getPermissions($roleId) {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN permission_role pr ON p.id = pr.permission_id
                WHERE pr.role_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }

    public function syncPermissions($roleId, $permissionIds) {
        // Remove existing permissions
        $stmt = $this->db->prepare("DELETE FROM permission_role WHERE role_id = ?");
        $stmt->execute([$roleId]);

        if (empty($permissionIds)) {
            return true;
        }

        // Insert new permissions
        $placeholders = implode(',', array_fill(0, count($permissionIds), '(?, ?)'));
        $values = [];
        foreach ($permissionIds as $permissionId) {
            $values[] = $roleId;
            $values[] = $permissionId;
        }
        $sql = "INSERT INTO permission_role (role_id, permission_id) VALUES $placeholders";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
}
