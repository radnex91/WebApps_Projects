<?php
namespace App\Core;
class Model
{
    protected static string $table;
    public static function all(): array
    {
        return Database::fetchAll("SELECT * FROM " . static::$table);
    }
    public static function find(int $id)
    {
        return Database::fetch("SELECT * FROM " . static::$table . " WHERE id = :id", ['id' => $id]);
    }
    public static function where(string $column, $value): array
    {
        return Database::fetchAll("SELECT * FROM " . static::$table . " WHERE {$column} = :value", ['value' => $value]);
    }
    public static function whereFirst(string $column, $value)
    {
        return Database::fetch("SELECT * FROM " . static::$table . " WHERE {$column} = :value", ['value' => $value]);
    }
    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }
    public static function updateRecord(int $id, array $data): int
    {
        return Database::update(static::$table, $data, "id = :id", ['id' => $id]);
    }
    public static function deleteRecord(int $id): int
    {
        return Database::delete(static::$table, "id = :id", ['id' => $id]);
    }
    public static function count(): int
    {
        return (int) Database::fetch("SELECT COUNT(*) as count FROM " . static::$table)->count;
    }
    public static function countWhere(string $column, $value): int
    {
        return (int) Database::fetch("SELECT COUNT(*) as count FROM " . static::$table . " WHERE {$column} = :value", ['value' => $value])->count;
    }
    public static function paginate(int $page = 1, int $perPage = 10, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $total = static::count();
        $items = Database::fetchAll("SELECT * FROM " . static::$table . " ORDER BY {$orderBy} {$orderDir} LIMIT {$perPage} OFFSET {$offset}");
        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'lastPage'   => (int) ceil($total / $perPage),
        ];
    }
}
