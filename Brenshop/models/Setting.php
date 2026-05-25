<?php
class Setting extends BaseModel {
    protected string $table = 'settings';
    protected string $primaryKey = 'key';

    public function getAll(): array {
        $rows = $this->query("SELECT `key`, `value` FROM {$this->table}");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function get(string $key, string $default = ''): string {
        $row = $this->queryOne("SELECT `value` FROM {$this->table} WHERE `key` = ? LIMIT 1", [$key]);
        return $row ? ($row['value'] ?? $default) : $default;
    }

    public function set(string $key, string $value): void {
        $this->execute(
            "INSERT INTO {$this->table} (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );
    }

    public function setMany(array $pairs): void {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }
}