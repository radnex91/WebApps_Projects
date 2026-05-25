<?php
class Permission extends Model {
    protected $table = 'permissions';

    public function __construct() {
        parent::__construct();
    }

    public function getAllGroupedByModule() {
        $sql = "SELECT * FROM {$this->table} ORDER BY module, name";
        return $this->db->query($sql)->fetchAll();
    }

    public function getByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function getRoles($permissionId) {
        $sql = "SELECT r.* FROM roles r
                INNER JOIN permission_role pr ON r.id = pr.role_id
                WHERE pr.permission_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$permissionId]);
        return $stmt->fetchAll();
    }
}
