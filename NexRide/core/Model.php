<?php
namespace Core;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $casts = [];

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable) || empty($this->fillable)) {
                $this->attributes[$key] = $this->castValue($key, $value);
            }
        }
    }

    public function __get(string $name): mixed { return $this->attributes[$name] ?? null; }
    public function __set(string $name, mixed $value): void { $this->attributes[$name] = $this->castValue($name, $value); }
    public function __isset(string $name): bool { return isset($this->attributes[$name]); }
    public function toArray(): array { return $this->attributes; }
    public function toJson(): string { return json_encode($this->attributes, JSON_UNESCAPED_UNICODE); }

    private function castValue(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) return $value;
        return match ($this->casts[$key]) {
            'int' => (int) $value, 'float' => (float) $value,
            'bool' => (bool) $value, 'string' => (string) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    public static function db(): Database { return Database::getInstance(); }

    public static function find(int|string $id): ?static
    {
        $result = static::db()->fetch("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?", [$id]);
        return $result ? new static((array) $result) : null;
    }

    public static function all(): array
    {
        $results = static::db()->fetchAll("SELECT * FROM " . static::$table . " ORDER BY id DESC");
        return array_map(fn($r) => new static((array) $r), $results);
    }

    public static function where(string $column, string $operator, mixed $value): array
    {
        $results = static::db()->fetchAll("SELECT * FROM " . static::$table . " WHERE {$column} {$operator} ? ORDER BY id DESC", [$value]);
        return array_map(fn($r) => new static((array) $r), $results);
    }

    public static function whereFirst(string $column, string $operator, mixed $value): ?static
    {
        $result = static::db()->fetch("SELECT * FROM " . static::$table . " WHERE {$column} {$operator} ? LIMIT 1", [$value]);
        return $result ? new static((array) $result) : null;
    }

    public static function count(): int
    {
        return (int) static::db()->fetch("SELECT COUNT(*) as cnt FROM " . static::$table)->cnt;
    }

    public static function paginate(int $page = 1, int $perPage = 20, string $where = '', array $params = []): array
    {
        $offset = ($page - 1) * $perPage;
        $whereClause = $where ? "WHERE {$where}" : '';
        $countResult = static::db()->fetch("SELECT COUNT(*) as cnt FROM " . static::$table . " {$whereClause}", $params);
        $total = (int) $countResult->cnt;
        $results = static::db()->fetchAll("SELECT * FROM " . static::$table . " {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$perPage, $offset]));
        return [
            'data' => array_map(fn($r) => new static((array) $r), $results),
            'total' => $total, 'per_page' => $perPage,
            'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function save(): static
    {
        $db = static::db();
        if (isset($this->attributes[static::$primaryKey]) && $this->attributes[static::$primaryKey]) {
            $id = $this->attributes[static::$primaryKey];
            $db->update(static::$table, $this->attributes, static::$primaryKey . " = ?", [$id]);
        } else {
            $id = $db->insert(static::$table, $this->attributes);
            $this->attributes[static::$primaryKey] = $id;
        }
        return $this;
    }

    public function delete(): bool
    {
        if (!isset($this->attributes[static::$primaryKey])) return false;
        static::db()->delete(static::$table, static::$primaryKey . " = ?", [$this->attributes[static::$primaryKey]]);
        return true;
    }

    public static function raw(string $sql, array $params = []): array
    {
        $results = static::db()->fetchAll($sql, $params);
        return array_map(fn($r) => new static((array) $r), $results);
    }
}