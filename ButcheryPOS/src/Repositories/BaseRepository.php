<?php
namespace App\Repositories;

use PDO;

class BaseRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all rows from a table with optional conditions
     */
    public function all(string $table, array $conditions = [], string $orderBy = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                $clauses[] = "{$key} = :where_{$key}";
                $params["where_{$key}"] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= " ORDER BY {$orderBy}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find a single row by ID
     */
    public function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a single row by a column value
     */
    public function findBy(string $table, string $column, $value): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$column} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insert a row and return the last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update rows by ID
     */
    public function update(string $table, int $id, array $data): bool
    {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $sets);
        $data['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$setClause} WHERE id = :id");
        return $stmt->execute($data);
    }

    /**
     * Delete a row by ID
     */
    public function delete(string $table, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Run a custom query with parameters and return fetched rows
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a custom statement (INSERT/UPDATE/DELETE) and return row count
     */
    public function statement(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Count rows in a table with optional conditions
     */
    public function count(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                $clauses[] = "{$key} = :count_{$key}";
                $params["count_{$key}"] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Get the PDO instance for raw operations
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}