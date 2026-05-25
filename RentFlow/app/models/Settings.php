<?php
class Settings extends Model {
    protected $table = 'settings';
    protected $allowedFields = ['setting_key', 'setting_value', 'created_at', 'updated_at'];

    public function getAll($limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getSettings() {
        $results = $this->getAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function get($key) {
        $sql = "SELECT setting_value FROM {$this->table} WHERE setting_key = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }

    public function update($key, $value) {
        $existing = $this->get($key);

        if ($existing !== null) {
            $sql = "UPDATE {$this->table} SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$value, $key]);
        } else {
            $sql = "INSERT INTO {$this->table} (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$key, $value]);
        }
    }
}
