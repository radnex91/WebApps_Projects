<?php
// models/User.php
class User extends BaseModel {
    protected string $table = 'users';

    public function findByEmail(string $email): ?array {
        return $this->queryOne("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    }

    public function authenticate(string $email, string $password): ?array {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password']) && $user['is_active']) {
            $this->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            return $user;
        }
        return null;
    }

    public function getAllWithDetails(): array {
        $users = $this->query("SELECT u.* FROM users u ORDER BY u.name");
        foreach ($users as &$u) {
            $u['stores'] = $this->getUserStores($u['id']);
            $u['warehouses'] = $this->getUserWarehouses($u['id']);
        }
        unset($u);
        return $users;
    }

    public function getByStore(int $storeId): array {
        return $this->query(
            "SELECT u.* FROM users u
             INNER JOIN user_stores us ON us.user_id = u.id
             WHERE us.store_id = ? ORDER BY u.name",
            [$storeId]
        );
    }

    public function createUser(array $data): int {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return $this->insert($data);
    }

    public function updatePassword(int $id, string $newPassword): bool {
        return $this->update($id, ['password' => password_hash($newPassword, PASSWORD_BCRYPT)]);
    }

    public function getUserStores(int $userId): array {
        return $this->query(
            "SELECT us.store_id, s.name as store_name FROM user_stores us
             JOIN stores s ON s.id = us.store_id
             WHERE us.user_id = ? ORDER BY s.name",
            [$userId]
        );
    }

    public function getUserWarehouses(int $userId): array {
        return $this->query(
            "SELECT uw.warehouse_id, w.name as warehouse_name, w.store_id, s.name as store_name
             FROM user_warehouses uw
             JOIN warehouses w ON w.id = uw.warehouse_id
             JOIN stores s ON s.id = w.store_id
             WHERE uw.user_id = ? ORDER BY s.name, w.name",
            [$userId]
        );
    }

    public function syncStores(int $userId, array $storeIds): void {
        $this->execute("DELETE FROM user_stores WHERE user_id = ?", [$userId]);
        foreach ($storeIds as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                $this->execute("INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)", [$userId, $sid]);
            }
        }
    }

    public function syncWarehouses(int $userId, array $warehouseIds): void {
        $this->execute("DELETE FROM user_warehouses WHERE user_id = ?", [$userId]);
        foreach ($warehouseIds as $wid) {
            $wid = (int)$wid;
            if ($wid > 0) {
                $this->execute("INSERT IGNORE INTO user_warehouses (user_id, warehouse_id) VALUES (?, ?)", [$userId, $wid]);
            }
        }
    }

    public function getAccessibleStores(int $userId): array {
        return $this->query(
            "SELECT s.*, us.store_id FROM user_stores us
             JOIN stores s ON s.id = us.store_id
             WHERE us.user_id = ? AND s.is_active = 1 ORDER BY s.name",
            [$userId]
        );
    }

    public function getAccessibleWarehouses(int $userId, ?int $storeId = null): array {
        $sql = "SELECT w.*, uw.warehouse_id, s.name as store_name FROM user_warehouses uw
                JOIN warehouses w ON w.id = uw.warehouse_id
                JOIN stores s ON s.id = w.store_id
                WHERE uw.user_id = ? AND w.is_active = 1";
        $params = [$userId];
        if ($storeId) {
            $sql .= " AND w.store_id = ?";
            $params[] = $storeId;
        }
        $sql .= " ORDER BY s.name, w.name";
        return $this->query($sql, $params);
    }

    public function encodePermissions(array $keys): string {
        $valid = array_keys(getPermissionMap());
        return json_encode(array_values(array_intersect($keys, $valid)));
    }
}