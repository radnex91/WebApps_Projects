<?php
namespace App\Models;

class Setting extends BaseModel
{
    protected string $table = 'app_settings';
    protected array $fillable = ['setting_key', 'setting_value', 'is_sensitive'];

    public function getAllKeyed(): array
    {
        $rows = $this->repo->all($this->table);
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function getByKey(string $key): ?string
    {
        $row = $this->repo->findBy($this->table, 'setting_key', $key);
        return $row ? $row['setting_value'] : null;
    }

    public function setByKey(string $key, string $value): bool
    {
        $existing = $this->repo->findBy($this->table, 'setting_key', $key);
        if ($existing) {
            return $this->repo->update($this->table, $existing['id'], [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->repo->insert($this->table, [
            'setting_key' => $key,
            'setting_value' => $value,
            'is_sensitive' => 0,
        ]);
        return true;
    }
}